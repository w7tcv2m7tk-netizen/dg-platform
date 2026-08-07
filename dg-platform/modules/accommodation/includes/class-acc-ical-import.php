<?php
/**
 * iCal import from Airbnb / Booking.com calendar feeds.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Ical_Import {

    /**
     * Sync OTA calendars for one accommodation.
     *
     * @param int    $accommodation_id Property post ID.
     * @param string $source           airbnb|bookingcom|all
     * @return array{imported:int,updated:int,cancelled:int,errors:string[],message:string}
     */
    public static function sync_accommodation($accommodation_id, $source = 'all') {
        $accommodation_id = (int) $accommodation_id;
        $results = [
            'imported' => 0,
            'updated' => 0,
            'cancelled' => 0,
            'errors' => [],
            'sources' => [],
        ];

        if (!$accommodation_id || get_post_type($accommodation_id) !== 'dg_accommodation') {
            $results['errors'][] = 'Invalid accommodation.';
            $results['message'] = 'Invalid accommodation.';
            return $results;
        }

        $sources = self::normalize_sources($source);
        if (empty($sources)) {
            $results['errors'][] = 'Invalid sync source.';
            $results['message'] = 'Invalid sync source.';
            return $results;
        }

        foreach ($sources as $src) {
            $config = self::source_config($src);
            $url = trim((string) get_post_meta($accommodation_id, $config['url_meta'], true));
            if ($url === '') {
                continue;
            }

            $body = self::fetch($url);
            if (is_wp_error($body)) {
                $results['errors'][] = $config['label'] . ': ' . $body->get_error_message();
                update_post_meta($accommodation_id, $config['error_meta'], $body->get_error_message());
                // Do not cancel OTA bookings when the feed fails — keep last good state.
                continue;
            }

            $events = self::parse_events($body);
            $import = self::import_events($accommodation_id, $events, $src);
            $results['imported'] += (int) $import['imported'];
            $results['updated'] += (int) $import['updated'];
            $results['cancelled'] += (int) $import['cancelled'];
            $results['sources'][$src] = [
                'events' => count($events),
                'imported' => (int) $import['imported'],
                'updated' => (int) $import['updated'],
                'cancelled' => (int) $import['cancelled'],
            ];

            update_post_meta($accommodation_id, $config['sync_meta'], current_time('mysql'));
            delete_post_meta($accommodation_id, $config['error_meta']);
        }

        if (class_exists('DG_Acc_Ota')) {
            DG_Acc_Ota::rebuild_blocked_dates($accommodation_id);
        }

        $results['message'] = self::format_message($results);
        return $results;
    }

    /**
     * @return string[]|WP_Error
     */
    public static function fetch($url) {
        $url = esc_url_raw(trim((string) $url));
        if ($url === '') {
            return new WP_Error('dg_ical_empty_url', 'Calendar URL is empty.');
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 5,
            'headers' => [
                'User-Agent' => 'DG-Platform-Calendar-Sync/1.0 (+WordPress)',
                'Accept' => 'text/calendar, text/plain, application/octet-stream, */*',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('dg_ical_fetch_failed', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('dg_ical_http_error', sprintf('Calendar feed returned HTTP %d.', $code));
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '' || stripos($body, 'BEGIN:VCALENDAR') === false) {
            return new WP_Error('dg_ical_invalid', 'Response is not a valid iCalendar feed.');
        }

        return $body;
    }

    /**
     * @return array<int, array{uid:string,start:string,end:string,summary:string,cancelled:bool}>
     */
    public static function parse_events($ics) {
        $ics = self::unfold($ics);
        $events = [];

        if (!preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $matches)) {
            return $events;
        }

        foreach ($matches[1] as $block) {
            $uid = self::extract_prop($block, 'UID');
            $start_raw = self::extract_prop($block, 'DTSTART');
            $end_raw = self::extract_prop($block, 'DTEND');
            $summary = self::unescape(self::extract_prop($block, 'SUMMARY'));
            $status = strtoupper(self::extract_prop($block, 'STATUS'));

            $start = self::parse_ical_date($start_raw);
            if (!$uid || !$start) {
                continue;
            }

            $end = $end_raw ? self::parse_ical_date($end_raw) : '';
            if ($end === '' || $end <= $start) {
                $end = gmdate('Y-m-d', strtotime($start . ' +1 day'));
            }

            $events[] = [
                'uid' => $uid,
                'start' => $start,
                'end' => $end,
                'summary' => $summary,
                'cancelled' => ($status === 'CANCELLED'),
            ];
        }

        return $events;
    }

    /**
     * @param array<int, array{uid:string,start:string,end:string,summary:string,cancelled:bool}> $events
     * @return array{imported:int,updated:int,cancelled:int}
     */
    private static function import_events($accommodation_id, array $events, $source) {
        $imported = 0;
        $updated = 0;
        $seen_uids = [];
        $property_title = get_the_title($accommodation_id);

        foreach ($events as $event) {
            if (!empty($event['cancelled'])) {
                continue;
            }

            $uid = (string) $event['uid'];
            $checkin = (string) $event['start'];
            $checkout = (string) $event['end'];
            $seen_uids[] = $uid;

            $existing_id = self::find_booking_by_uid($accommodation_id, $uid, $source);
            $guest_name = self::guest_name_from_summary((string) $event['summary'], $source);
            if ($existing_id) {
                update_post_meta($existing_id, 'dg_booking_checkin', $checkin);
                update_post_meta($existing_id, 'dg_booking_checkout', $checkout);
                update_post_meta($existing_id, 'dg_booking_status', $source);
                update_post_meta($existing_id, 'dg_booking_source', $source);
                update_post_meta($existing_id, 'dg_booking_accommodation_name', $property_title);
                if ($guest_name !== '') {
                    update_post_meta($existing_id, 'dg_booking_name', $guest_name);
                }
                delete_post_meta($existing_id, 'dg_booking_ical_misses');
                if (!empty($event['summary'])) {
                    wp_update_post([
                        'ID' => $existing_id,
                        'post_title' => self::booking_title($event['summary'], $property_title, $source),
                    ]);
                }
                $updated++;
                continue;
            }

            $booking_id = wp_insert_post([
                'post_type' => 'dg_booking',
                'post_status' => 'publish',
                'post_title' => self::booking_title($event['summary'], $property_title, $source),
            ], true);

            if (is_wp_error($booking_id) || !$booking_id) {
                continue;
            }

            update_post_meta($booking_id, 'dg_booking_accommodation_id', $accommodation_id);
            update_post_meta($booking_id, 'dg_booking_checkin', $checkin);
            update_post_meta($booking_id, 'dg_booking_checkout', $checkout);
            update_post_meta($booking_id, 'dg_booking_status', $source);
            update_post_meta($booking_id, 'dg_booking_source', $source);
            update_post_meta($booking_id, 'dg_booking_ical_uid', $uid);
            update_post_meta($booking_id, 'dg_booking_accommodation_name', $property_title);
            if ($guest_name !== '') {
                update_post_meta($booking_id, 'dg_booking_name', $guest_name);
            }
            $imported++;
        }

        $cancelled = self::cancel_stale_bookings($accommodation_id, $source, $seen_uids);

        return [
            'imported' => $imported,
            'updated' => $updated,
            'cancelled' => $cancelled,
        ];
    }

    private static function find_booking_by_uid($accommodation_id, $uid, $source) {
        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post_status' => 'any',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'dg_booking_accommodation_id', 'value' => (int) $accommodation_id, 'compare' => '='],
                ['key' => 'dg_booking_ical_uid', 'value' => $uid, 'compare' => '='],
                ['key' => 'dg_booking_source', 'value' => $source, 'compare' => '='],
            ],
        ]);

        return !empty($bookings[0]) ? (int) $bookings[0] : 0;
    }

    /**
     * Soft-cancel: require two consecutive successful syncs missing a UID
     * before marking cancelled (avoids false removals on flaky feeds).
     */
    private static function cancel_stale_bookings($accommodation_id, $source, array $seen_uids) {
        $cancelled = 0;
        $seen_uids = array_flip($seen_uids);

        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'any',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'dg_booking_accommodation_id', 'value' => (int) $accommodation_id, 'compare' => '='],
                ['key' => 'dg_booking_source', 'value' => $source, 'compare' => '='],
                ['key' => 'dg_booking_ical_uid', 'compare' => 'EXISTS'],
            ],
        ]);

        foreach ($bookings as $booking_id) {
            $uid = (string) get_post_meta($booking_id, 'dg_booking_ical_uid', true);
            $status = (string) get_post_meta($booking_id, 'dg_booking_status', true);
            if ($uid === '' || isset($seen_uids[$uid]) || $status === 'cancelled') {
                if (isset($seen_uids[$uid])) {
                    delete_post_meta($booking_id, 'dg_booking_ical_misses');
                }
                continue;
            }
            $misses = (int) get_post_meta($booking_id, 'dg_booking_ical_misses', true) + 1;
            update_post_meta($booking_id, 'dg_booking_ical_misses', $misses);
            if ($misses < 2) {
                continue;
            }
            update_post_meta($booking_id, 'dg_booking_status', 'cancelled');
            $cancelled++;
        }

        return $cancelled;
    }

    private static function guest_name_from_summary($summary, $source) {
        $summary = trim((string) $summary);
        if ($summary === '') {
            return '';
        }
        // Airbnb often uses "Reserved" / "Not available" — keep channel label instead of fake guest.
        if (preg_match('/^(reserved|not available|blocked|unavailable)$/i', $summary)) {
            return $source === 'bookingcom' ? 'Booking.com guest' : 'Airbnb guest';
        }
        // "Closed - Not available" style
        if (preg_match('/not available|unavailable/i', $summary)) {
            return $source === 'bookingcom' ? 'Booking.com block' : 'Airbnb block';
        }
        return $summary;
    }

    private static function booking_title($summary, $property_title, $source) {
        $label = $summary !== '' ? $summary : ucfirst($source) . ' reservation';
        if ($property_title !== '') {
            return $label . ' — ' . $property_title;
        }
        return $label;
    }

    /** @return string[] */
    private static function normalize_sources($source) {
        $source = sanitize_key((string) $source);
        if ($source === 'all') {
            return ['airbnb', 'bookingcom'];
        }
        if (in_array($source, ['airbnb', 'bookingcom'], true)) {
            return [$source];
        }
        return [];
    }

    /** @return array{url_meta:string,sync_meta:string,error_meta:string,label:string} */
    private static function source_config($source) {
        if ($source === 'bookingcom') {
            return [
                'url_meta' => 'dg_bookingcom_ical_url',
                'sync_meta' => 'dg_bookingcom_ical_last_sync',
                'error_meta' => 'dg_bookingcom_ical_last_error',
                'label' => 'Booking.com',
            ];
        }

        return [
            'url_meta' => 'dg_ical_url',
            'sync_meta' => 'dg_ical_last_sync',
            'error_meta' => 'dg_ical_last_error',
            'label' => 'Airbnb',
        ];
    }

    private static function format_message(array $results) {
        if (!empty($results['errors']) && $results['imported'] === 0 && $results['updated'] === 0) {
            return implode(' ', $results['errors']);
        }

        $parts = [];
        if ($results['imported'] > 0) {
            $parts[] = sprintf('%d new', (int) $results['imported']);
        }
        if ($results['updated'] > 0) {
            $parts[] = sprintf('%d updated', (int) $results['updated']);
        }
        if ($results['cancelled'] > 0) {
            $parts[] = sprintf('%d removed', (int) $results['cancelled']);
        }

        $message = empty($parts) ? 'Calendar synced — no OTA changes.' : 'Synced: ' . implode(', ', $parts) . '.';
        if (!empty($results['errors'])) {
            $message .= ' Warnings: ' . implode(' ', $results['errors']);
        }

        return $message;
    }

    private static function unfold($ics) {
        $ics = str_replace(["\r\n", "\r"], "\n", (string) $ics);
        return preg_replace("/\n[ \t]/", '', $ics);
    }

    private static function extract_prop($block, $name) {
        if (preg_match('/^' . preg_quote($name, '/') . '(?:;[^:]*)?:(.+)$/m', $block, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private static function parse_ical_date($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})T/', $value, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : '';
    }

    private static function unescape($value) {
        return str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], (string) $value);
    }
}
