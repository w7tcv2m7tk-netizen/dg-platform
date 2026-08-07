<?php
/**
 * Accommodation REST endpoints for Gen 2 / Cursor MCP / dev tooling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Dev_API {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/accommodation/summary', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_summary'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 365],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/bookings', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_bookings'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'status' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100],
                'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                'from' => ['type' => 'string'],
                'to' => ['type' => 'string'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/properties', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_properties'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/guests', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_guests'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'limit' => ['type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/availability', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_availability'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'property_id' => ['type' => 'integer'],
                'from' => ['type' => 'string'],
                'to' => ['type' => 'string'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/housekeeping', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'list_housekeeping'],
                'permission_callback' => [__CLASS__, 'can_access'],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [__CLASS__, 'update_housekeeping'],
                'permission_callback' => [__CLASS__, 'can_manage'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/accommodation/ota-sync', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'sync_ota_calendars'],
            'permission_callback' => [__CLASS__, 'can_manage'],
            'args' => [
                'property_id' => ['type' => 'integer'],
                'source' => ['type' => 'string', 'default' => 'all'],
            ],
        ]);
    }

    public static function can_access($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        if (DG_Dev_API::verify_request($request)) {
            return true;
        }
        return class_exists('DG_Acc_Permissions') && DG_Acc_Permissions::can_view_bookings();
    }

    public static function can_manage($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        if (DG_Dev_API::verify_request($request)) {
            return true;
        }
        return class_exists('DG_Acc_Permissions') && DG_Acc_Permissions::can_manage_bookings();
    }

    public static function get_summary($request) {
        $days = (int) $request->get_param('days');
        $summary = class_exists('DG_Acc_Reports') ? DG_Acc_Reports::summary() : [];
        $summary['site'] = home_url();
        $summary['site_profile'] = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'DG Platform';
        $summary['generated_at'] = current_time('mysql');
        $summary['period_days'] = $days;

        // Gen 2 dashboard aliases.
        $summary['revenue_mtd'] = isset($summary['revenue_month'])
            ? (float) $summary['revenue_month']
            : 0.0;
        $summary['occupancy_rate'] = self::occupancy_rate_for_period($days);

        if (class_exists('DG_Acc_Housekeeping')) {
            $summary['housekeeping'] = DG_Acc_Housekeeping::status_summary();
        }

        $today = current_time('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $checkins_tomorrow = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => 'dg_booking_checkin', 'value' => $tomorrow, 'compare' => '=', 'type' => 'DATE'],
                ['key' => 'dg_booking_status', 'value' => ['cancelled', 'completed'], 'compare' => 'NOT IN'],
            ],
        ]);
        $summary['checkins_tomorrow'] = count($checkins_tomorrow);
        $summary['checkins_tomorrow_ids'] = array_map('intval', $checkins_tomorrow);

        $recent = self::format_bookings(get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ]));
        $summary['recent_bookings'] = $recent;

        return rest_ensure_response($summary);
    }

    public static function list_bookings($request) {
        $args = [
            'post_type' => 'dg_booking',
            'posts_per_page' => (int) $request->get_param('limit'),
            'offset' => (int) $request->get_param('offset'),
            'orderby' => 'meta_value',
            'meta_key' => 'dg_booking_checkin',
            'order' => 'DESC',
        ];
        $meta_query = [];
        $status = $request->get_param('status');
        if ($status) {
            $meta_query[] = ['key' => 'dg_booking_status', 'value' => sanitize_text_field($status)];
        }
        $from = sanitize_text_field((string) $request->get_param('from'));
        $to = sanitize_text_field((string) $request->get_param('to'));
        if ($from !== '') {
            $meta_query[] = ['key' => 'dg_booking_checkout', 'value' => $from, 'compare' => '>', 'type' => 'DATE'];
        }
        if ($to !== '') {
            $meta_query[] = ['key' => 'dg_booking_checkin', 'value' => $to, 'compare' => '<', 'type' => 'DATE'];
        }
        if ($meta_query) {
            $args['meta_query'] = count($meta_query) > 1
                ? array_merge(['relation' => 'AND'], $meta_query)
                : $meta_query;
        }
        return rest_ensure_response([
            'bookings' => self::format_bookings(get_posts($args)),
            'total' => (int) wp_count_posts('dg_booking')->publish,
        ]);
    }

    public static function list_properties($request) {
        $properties = [];
        foreach (get_posts(['post_type' => 'dg_accommodation', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']) as $p) {
            $properties[] = self::format_property($p);
        }
        return rest_ensure_response(['properties' => $properties, 'total' => count($properties)]);
    }

    public static function list_guests($request) {
        $guests = [];
        foreach (get_posts([
            'post_type' => 'dg_guest',
            'posts_per_page' => (int) $request->get_param('limit'),
            'post_status' => 'publish',
        ]) as $g) {
            $guests[] = [
                'id' => $g->ID,
                'name' => $g->post_title,
                'email' => get_post_meta($g->ID, 'dg_guest_email', true),
                'phone' => get_post_meta($g->ID, 'dg_guest_phone', true),
                'total_stays' => (int) get_post_meta($g->ID, 'dg_guest_total_stays', true),
            ];
        }
        return rest_ensure_response(['guests' => $guests, 'total' => (int) wp_count_posts('dg_guest')->publish]);
    }

    public static function get_availability($request) {
        $from = sanitize_text_field((string) $request->get_param('from'));
        $to = sanitize_text_field((string) $request->get_param('to'));
        if ($from === '') {
            $from = current_time('Y-m-d');
        }
        if ($to === '') {
            $to = date('Y-m-d', strtotime($from . ' +60 days'));
        }

        $property_id = (int) $request->get_param('property_id');
        $query = [
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        if ($property_id > 0) {
            $query['include'] = [$property_id];
        }
        $properties = get_posts($query);

        $units = [];
        foreach ($properties as $p) {
            $blocked = class_exists('DG_Acc_Frontend')
                ? DG_Acc_Frontend::get_blocked_dates($p->ID)
                : [];
            $blocked = array_values(array_filter($blocked, static function ($d) use ($from, $to) {
                return $d >= $from && $d <= $to;
            }));

            $bookings = self::format_bookings(get_posts([
                'post_type' => 'dg_booking',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'dg_booking_accommodation_id', 'value' => $p->ID, 'compare' => '='],
                    ['key' => 'dg_booking_checkout', 'value' => $from, 'compare' => '>', 'type' => 'DATE'],
                    ['key' => 'dg_booking_checkin', 'value' => $to, 'compare' => '<', 'type' => 'DATE'],
                    ['key' => 'dg_booking_status', 'value' => ['cancelled'], 'compare' => 'NOT IN'],
                ],
            ]));

            $units[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'listing_status' => class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::get($p->ID) : 'bookable',
                'weekday_rate' => (float) get_post_meta($p->ID, 'dg_weekday_rate', true),
                'weekend_rate' => (float) get_post_meta($p->ID, 'dg_weekend_rate', true),
                'blocked_dates' => $blocked,
                'bookings' => $bookings,
            ];
        }

        return rest_ensure_response([
            'from' => $from,
            'to' => $to,
            'units' => $units,
            'total' => count($units),
        ]);
    }

    public static function list_housekeeping($request) {
        $items = [];
        foreach (get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]) as $p) {
            $items[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'status' => get_post_meta($p->ID, 'dg_housekeeping_status', true) ?: 'unknown',
                'notes' => get_post_meta($p->ID, 'dg_housekeeping_notes', true) ?: '',
                'last_cleaned' => get_post_meta($p->ID, 'dg_housekeeping_last_cleaned', true) ?: null,
                'cleaning_form_url' => class_exists('DG_Acc_Cleaning')
                    ? DG_Acc_Cleaning::cleaning_url_for_property($p->ID)
                    : '',
                'checkin_url' => class_exists('DG_Acc_Checkin')
                    ? DG_Acc_Checkin::checkin_url_for_property($p->ID)
                    : '',
            ];
        }

        $summary = class_exists('DG_Acc_Housekeeping')
            ? DG_Acc_Housekeeping::status_summary()
            : [];

        return rest_ensure_response([
            'items' => $items,
            'summary' => $summary,
            'statuses' => class_exists('DG_Acc_Housekeeping') ? DG_Acc_Housekeeping::STATUSES : [],
            'total' => count($items),
        ]);
    }

    public static function update_housekeeping($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error('invalid_body', 'Expected JSON body.', ['status' => 400]);
        }

        $updates = [];
        if (isset($body['updates']) && is_array($body['updates'])) {
            $updates = $body['updates'];
        } elseif (isset($body['property_id'])) {
            $updates[] = $body;
        }

        if (!$updates) {
            return new WP_Error('missing_updates', 'Provide updates[{property_id,status,notes?}].', ['status' => 400]);
        }

        $allowed = class_exists('DG_Acc_Housekeeping')
            ? array_keys(DG_Acc_Housekeeping::STATUSES)
            : ['clean', 'dirty', 'in_progress', 'inspection'];

        $saved = [];
        foreach ($updates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['property_id'] ?? $row['id'] ?? 0);
            if (!$id || get_post_type($id) !== 'dg_accommodation') {
                continue;
            }
            $status = sanitize_text_field((string) ($row['status'] ?? ''));
            if (!in_array($status, $allowed, true)) {
                continue;
            }
            $prev = get_post_meta($id, 'dg_housekeeping_status', true);
            update_post_meta($id, 'dg_housekeeping_status', $status);
            if (array_key_exists('notes', $row)) {
                update_post_meta($id, 'dg_housekeeping_notes', sanitize_textarea_field((string) $row['notes']));
            }
            if ($status === 'clean' && $prev !== 'clean') {
                update_post_meta($id, 'dg_housekeeping_last_cleaned', current_time('mysql'));
            }
            $saved[] = $id;
        }

        return rest_ensure_response([
            'ok' => true,
            'updated' => $saved,
            'count' => count($saved),
        ]);
    }

    /**
     * Occupancy as 0–100 percentage for the given lookback window.
     */
    private static function occupancy_rate_for_period($days) {
        $days = max(1, (int) $days);
        $properties = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ]);
        $property_count = count($properties);
        if ($property_count === 0) {
            return 0.0;
        }

        $end = current_time('Y-m-d');
        $start = date('Y-m-d', strtotime($end . ' -' . ($days - 1) . ' days'));
        $capacity = $property_count * $days;
        $occupied = 0;

        foreach ($properties as $pid) {
            $bookings = get_posts([
                'post_type' => 'dg_booking',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'dg_booking_accommodation_id', 'value' => $pid, 'compare' => '='],
                    ['key' => 'dg_booking_checkout', 'value' => $start, 'compare' => '>', 'type' => 'DATE'],
                    ['key' => 'dg_booking_checkin', 'value' => $end, 'compare' => '<=', 'type' => 'DATE'],
                    ['key' => 'dg_booking_status', 'value' => ['cancelled'], 'compare' => 'NOT IN'],
                ],
            ]);
            foreach ($bookings as $bid) {
                $checkin = get_post_meta($bid, 'dg_booking_checkin', true);
                $checkout = get_post_meta($bid, 'dg_booking_checkout', true);
                if (!$checkin || !$checkout) {
                    continue;
                }
                $cur = max(strtotime($checkin), strtotime($start));
                $stop = min(strtotime($checkout), strtotime($end . ' +1 day'));
                while ($cur < $stop) {
                    $occupied++;
                    $cur = strtotime('+1 day', $cur);
                }
            }
        }

        return round(min(100, ($occupied / $capacity) * 100), 1);
    }

    private static function format_property($p) {
        return [
            'id' => $p->ID,
            'title' => $p->post_title,
            'slug' => $p->post_name,
            'weekday_rate' => (float) get_post_meta($p->ID, 'dg_weekday_rate', true),
            'weekend_rate' => (float) get_post_meta($p->ID, 'dg_weekend_rate', true),
            'cleaning_fee' => (float) get_post_meta($p->ID, 'dg_cleaning_fee', true),
            'housekeeping_status' => get_post_meta($p->ID, 'dg_housekeeping_status', true) ?: 'unknown',
            'housekeeping_notes' => get_post_meta($p->ID, 'dg_housekeeping_notes', true) ?: '',
            'last_cleaned' => get_post_meta($p->ID, 'dg_housekeeping_last_cleaned', true) ?: null,
            'listing_status' => class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::get($p->ID) : 'bookable',
            'checkin_slug' => get_post_meta($p->ID, 'dg_checkin_slug', true),
            'checkin_url' => class_exists('DG_Acc_Checkin')
                ? DG_Acc_Checkin::checkin_url_for_property($p->ID)
                : '',
            'cleaning_form_url' => class_exists('DG_Acc_Cleaning')
                ? DG_Acc_Cleaning::cleaning_url_for_property($p->ID)
                : '',
        ];
    }

    private static function format_bookings($posts) {
        $out = [];
        foreach ($posts as $b) {
            $guest = (string) get_post_meta($b->ID, 'dg_booking_name', true);
            if ($guest === '') {
                $guest = $b->post_title ?: '';
                // Strip " — Property" suffix from iCal titles when used as display name.
                if (strpos($guest, ' — ') !== false) {
                    $guest = trim(explode(' — ', $guest, 2)[0]);
                }
            }
            $source = (string) get_post_meta($b->ID, 'dg_booking_source', true);
            $status = (string) get_post_meta($b->ID, 'dg_booking_status', true) ?: 'pending';
            $out[] = [
                'id' => $b->ID,
                'ref' => get_post_meta($b->ID, 'dg_booking_ref', true),
                'guest_name' => $guest,
                'email' => get_post_meta($b->ID, 'dg_booking_email', true),
                'accommodation' => get_post_meta($b->ID, 'dg_booking_accommodation_name', true),
                'accommodation_id' => (int) get_post_meta($b->ID, 'dg_booking_accommodation_id', true),
                'checkin' => get_post_meta($b->ID, 'dg_booking_checkin', true),
                'checkout' => get_post_meta($b->ID, 'dg_booking_checkout', true),
                'status' => $status,
                'source' => $source !== '' ? $source : $status,
                'total' => (float) get_post_meta($b->ID, 'dg_booking_total', true),
            ];
        }
        return $out;
    }

    /**
     * Trigger Airbnb / Booking.com iCal import for one or all properties.
     */
    public static function sync_ota_calendars($request) {
        if (!class_exists('DG_Acc_Ical_Import')) {
            return new WP_Error('dg_ota_unavailable', 'OTA sync is not available on this site.', ['status' => 501]);
        }

        $source = sanitize_key((string) $request->get_param('source'));
        if ($source === '') {
            $source = 'all';
        }
        $property_id = (int) $request->get_param('property_id');

        $query = [
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ];
        if ($property_id > 0) {
            $query['include'] = [$property_id];
        }

        $ids = get_posts($query);
        $results = [];
        $imported = 0;
        $updated = 0;
        $cancelled = 0;
        $errors = [];

        foreach ($ids as $id) {
            $airbnb = get_post_meta($id, 'dg_ical_url', true);
            $bookingcom = get_post_meta($id, 'dg_bookingcom_ical_url', true);
            if (!$airbnb && !$bookingcom) {
                continue;
            }
            $sync = DG_Acc_Ical_Import::sync_accommodation((int) $id, $source);
            $imported += (int) ($sync['imported'] ?? 0);
            $updated += (int) ($sync['updated'] ?? 0);
            $cancelled += (int) ($sync['cancelled'] ?? 0);
            if (!empty($sync['errors'])) {
                $errors = array_merge($errors, $sync['errors']);
            }
            $results[] = [
                'property_id' => (int) $id,
                'title' => get_the_title($id),
                'message' => $sync['message'] ?? '',
                'airbnb_last_sync' => get_post_meta($id, 'dg_ical_last_sync', true) ?: null,
                'bookingcom_last_sync' => get_post_meta($id, 'dg_bookingcom_ical_last_sync', true) ?: null,
                'airbnb_last_error' => get_post_meta($id, 'dg_ical_last_error', true) ?: null,
                'bookingcom_last_error' => get_post_meta($id, 'dg_bookingcom_ical_last_error', true) ?: null,
            ];
        }

        return rest_ensure_response([
            'ok' => empty($errors) || ($imported + $updated) > 0,
            'imported' => $imported,
            'updated' => $updated,
            'cancelled' => $cancelled,
            'properties' => $results,
            'errors' => $errors,
            'message' => empty($results)
                ? 'No properties have Airbnb or Booking.com calendar URLs configured.'
                : sprintf('Synced %d propert%s — %d new, %d updated, %d removed.', count($results), count($results) === 1 ? 'y' : 'ies', $imported, $updated, $cancelled),
        ]);
    }
}
