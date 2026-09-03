<?php
/**
 * Project CVH stay changes into Gen 2 StayBooking.
 *
 * WordPress is a connector. Gen 2 StayBooking owns the canonical booking
 * identity; the connector persists that id in dg_booking_platform_id after the
 * first successful sync and includes it on every later projection.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Platform_Sync {

    const PLATFORM_ID_META = 'dg_booking_platform_id';

    public static function init() {
        add_action('dg_booking_created', [__CLASS__, 'on_booking_created'], 40, 2);
        add_action('dg_booking_confirmed', [__CLASS__, 'on_booking_confirmed'], 40, 1);
        add_filter('rest_pre_dispatch', [__CLASS__, 'authorise_booking_patch'], 10, 3);
    }

    public static function app_url() {
        if (class_exists('DG_Address_Resolver')) {
            return DG_Address_Resolver::app_url();
        }
        return defined('DG_APP_URL') && DG_APP_URL !== ''
            ? untrailingslashit(DG_APP_URL)
            : 'https://app.digitalgate.com.au';
    }

    public static function webhook_secret() {
        if (defined('DG_STAY_BOOKING_WEBHOOK_SECRET') && DG_STAY_BOOKING_WEBHOOK_SECRET !== '') {
            return (string) DG_STAY_BOOKING_WEBHOOK_SECRET;
        }
        $stay = (string) get_option('dg_stay_booking_webhook_secret', '');
        if ($stay !== '') {
            return $stay;
        }
        if (defined('DG_DISCOVERY_WEBHOOK_SECRET') && DG_DISCOVERY_WEBHOOK_SECRET !== '') {
            return (string) DG_DISCOVERY_WEBHOOK_SECRET;
        }
        $discovery = (string) get_option('dg_discovery_webhook_secret', '');
        if ($discovery !== '') {
            return $discovery;
        }
        // CVH Dev API key is accepted by Gen 2 when DG_WP_ACCOMMODATION_API_KEY matches.
        return (string) get_option('dg_dev_api_key', '');
    }

    public static function organisation_id() {
        if (defined('DG_ACC_ORGANISATION_ID') && DG_ACC_ORGANISATION_ID !== '') {
            return (string) DG_ACC_ORGANISATION_ID;
        }
        return (string) get_option('dg_platform_organisation_id', '');
    }

    /**
     * Before WordPress mutates a booking through the connector REST endpoint,
     * require Gen 2 to accept the proposed canonical StayBooking state first.
     * Returning WP_Error aborts the WordPress write.
     *
     * @param mixed           $result
     * @param WP_REST_Server  $server
     * @param WP_REST_Request $request
     * @return mixed
     */
    public static function authorise_booking_patch($result, $server, $request) {
        if ($result !== null) {
            return $result;
        }
        if (!($request instanceof WP_REST_Request)) {
            return $result;
        }
        if (strtoupper((string) $request->get_method()) !== 'PATCH') {
            return $result;
        }

        $route = (string) $request->get_route();
        $expected = '/' . trim(DG_REST_NAMESPACE, '/') . '/accommodation/bookings';
        if ($route !== $expected) {
            return $result;
        }

        // rest_pre_dispatch may run before the route callback. Never let this
        // privileged Gen 2 webhook hop bypass the route's own manage permission.
        if (!class_exists('DG_Acc_Dev_API') || !DG_Acc_Dev_API::can_manage($request)) {
            return $result;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error('invalid_body', 'Expected JSON body.', ['status' => 400]);
        }
        $updates = isset($body['updates']) && is_array($body['updates']) ? $body['updates'] : [];
        if (!$updates) {
            return $result;
        }

        foreach ($updates as $index => $update) {
            if (!is_array($update)) {
                continue;
            }

            $booking_id = self::resolve_wp_booking_id($update);
            if ($booking_id <= 0) {
                return new WP_Error(
                    'booking_identity_not_found',
                    'Could not resolve the WordPress projection for this platform booking.',
                    ['status' => 404, 'index' => $index]
                );
            }

            $row = self::format_booking_row($booking_id);
            if (!$row) {
                return new WP_Error(
                    'booking_projection_missing',
                    'Could not build the WordPress booking projection.',
                    ['status' => 404, 'index' => $index]
                );
            }

            $row = self::merge_booking_patch($row, $update);
            $ack = self::push_row($booking_id, $row);
            if (is_wp_error($ack)) {
                return $ack;
            }
        }

        return $result;
    }

    /**
     * Resolve canonical platform identity first; WP id is migration fallback.
     *
     * @param array<string,mixed> $row
     * @return int
     */
    private static function resolve_wp_booking_id(array $row) {
        $platform_id = sanitize_text_field((string) ($row['platform_id'] ?? ''));
        if ($platform_id !== '') {
            $found = get_posts([
                'post_type' => 'dg_booking',
                'posts_per_page' => 2,
                'post_status' => 'any',
                'fields' => 'ids',
                'meta_query' => [
                    ['key' => self::PLATFORM_ID_META, 'value' => $platform_id, 'compare' => '='],
                ],
            ]);
            if (count($found) !== 1) {
                return 0;
            }
            $resolved = (int) $found[0];
            $legacy_id = (int) ($row['id'] ?? 0);
            if ($legacy_id > 0 && $legacy_id !== $resolved) {
                return 0;
            }
            return $resolved;
        }

        $legacy_id = (int) ($row['id'] ?? 0);
        return $legacy_id > 0 && get_post_type($legacy_id) === 'dg_booking' ? $legacy_id : 0;
    }

    /**
     * Overlay a REST patch onto the current WP projection without mutating WP.
     * Gen 2 therefore validates the exact proposed state before the callback runs.
     *
     * @param array<string,mixed> $current
     * @param array<string,mixed> $patch
     * @return array<string,mixed>
     */
    private static function merge_booking_patch(array $current, array $patch) {
        $text = ['ref', 'guest_name', 'phone', 'accommodation', 'checkin', 'checkout', 'status', 'source', 'paid', 'payment_method'];
        foreach ($text as $key) {
            if (array_key_exists($key, $patch)) {
                $current[$key] = sanitize_text_field((string) $patch[$key]);
            }
        }
        if (array_key_exists('email', $patch)) {
            $current['email'] = sanitize_email((string) $patch['email']);
        }
        if (array_key_exists('message', $patch)) {
            $current['message'] = sanitize_textarea_field((string) $patch['message']);
        }
        foreach (['accommodation_id', 'nights', 'guests'] as $key) {
            if (array_key_exists($key, $patch)) {
                $current[$key] = (int) $patch[$key];
            }
        }
        if (array_key_exists('total', $patch)) {
            $current['total'] = (float) $patch['total'];
        }
        if (!empty($patch['platform_id'])) {
            $current['platform_id'] = sanitize_text_field((string) $patch['platform_id']);
        }
        return $current;
    }

    /**
     * @param int $booking_id
     * @param string $ref
     */
    public static function on_booking_created($booking_id, $ref = '') {
        self::push_booking((int) $booking_id);
    }

    /** @param int $booking_id */
    public static function on_booking_confirmed($booking_id) {
        self::push_booking((int) $booking_id);
    }

    /** @param int $booking_id */
    public static function push_booking($booking_id) {
        $booking_id = (int) $booking_id;
        if ($booking_id <= 0 || get_post_type($booking_id) !== 'dg_booking') {
            return;
        }

        $row = self::format_booking_row($booking_id);
        if (!$row) {
            return;
        }

        $ack = self::push_row($booking_id, $row);
        if (is_wp_error($ack)) {
            error_log('DG Acc Platform sync failed: ' . $ack->get_error_message());
        }
    }

    /**
     * Push an exact booking projection to Gen 2 and persist the acknowledged
     * canonical identity. This method never mutates booking business fields.
     *
     * @param int                 $booking_id
     * @param array<string,mixed> $row
     * @return string|WP_Error Canonical platform id on success.
     */
    private static function push_row($booking_id, array $row) {
        $secret = self::webhook_secret();
        if ($secret === '') {
            return new WP_Error('platform_sync_unconfigured', 'Platform booking sync secret is not configured.', ['status' => 503]);
        }

        $payload = [
            'booking' => $row,
            'site_url' => home_url('/'),
            'source' => 'wordpress_projection',
        ];
        $org = self::organisation_id();
        if ($org !== '') {
            $payload['organisation_id'] = $org;
        }

        $url = self::app_url() . '/api/webhooks/dg-stay-booking';
        $response = wp_remote_post($url, [
            'timeout' => 8,
            'blocking' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-DG-Webhook-Secret' => $secret,
                'X-API-Key' => $secret,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('platform_sync_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new WP_Error(
                'platform_sync_rejected',
                'Gen 2 rejected the booking update (HTTP ' . $status . ').',
                ['status' => 409, 'platform_status' => $status]
            );
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $identities = is_array($body) && isset($body['data']['identities']) && is_array($body['data']['identities'])
            ? $body['data']['identities']
            : [];

        foreach ($identities as $identity) {
            if (!is_array($identity) || (int) ($identity['wp_id'] ?? 0) !== (int) $booking_id) {
                continue;
            }
            $platform_id = sanitize_text_field((string) ($identity['platform_id'] ?? ''));
            if ($platform_id === '') {
                continue;
            }

            $expected = sanitize_text_field((string) ($row['platform_id'] ?? ''));
            if ($expected !== '' && !hash_equals($expected, $platform_id)) {
                return new WP_Error(
                    'platform_identity_mismatch',
                    'Gen 2 returned a different canonical booking identity.',
                    ['status' => 409]
                );
            }

            update_post_meta($booking_id, self::PLATFORM_ID_META, $platform_id);
            return $platform_id;
        }

        return new WP_Error(
            'platform_identity_missing',
            'Gen 2 did not acknowledge a canonical booking identity.',
            ['status' => 502]
        );
    }

    /**
     * @param int $booking_id
     * @return array<string,mixed>|null
     */
    private static function format_booking_row($booking_id) {
        $post = get_post($booking_id);
        if (!$post) {
            return null;
        }

        $platform_id = sanitize_text_field((string) get_post_meta($booking_id, self::PLATFORM_ID_META, true));

        if (class_exists('DG_Acc_Dev_API') && method_exists('DG_Acc_Dev_API', 'format_bookings_for_platform')) {
            $rows = DG_Acc_Dev_API::format_bookings_for_platform([$post]);
            if (!empty($rows[0]) && is_array($rows[0])) {
                if ($platform_id !== '') {
                    $rows[0]['platform_id'] = $platform_id;
                }
                return $rows[0];
            }
        }

        $guest = (string) get_post_meta($booking_id, 'dg_booking_name', true);
        if ($guest === '') {
            $guest = $post->post_title ?: ('Booking #' . $booking_id);
        }
        $paid = (string) get_post_meta($booking_id, 'dg_booking_paid', true);
        $source = (string) get_post_meta($booking_id, 'dg_booking_source', true);

        $row = [
            'id' => $booking_id,
            'ref' => (string) get_post_meta($booking_id, 'dg_booking_ref', true),
            'guest_name' => $guest,
            'email' => (string) get_post_meta($booking_id, 'dg_booking_email', true),
            'phone' => (string) get_post_meta($booking_id, 'dg_booking_phone', true),
            'accommodation' => (string) get_post_meta($booking_id, 'dg_booking_accommodation_name', true),
            'accommodation_id' => (int) get_post_meta($booking_id, 'dg_booking_accommodation_id', true),
            'checkin' => (string) get_post_meta($booking_id, 'dg_booking_checkin', true),
            'checkout' => (string) get_post_meta($booking_id, 'dg_booking_checkout', true),
            'nights' => (int) get_post_meta($booking_id, 'dg_booking_nights', true) ?: null,
            'guests' => (int) get_post_meta($booking_id, 'dg_booking_guests', true) ?: null,
            'status' => (string) get_post_meta($booking_id, 'dg_booking_status', true) ?: 'pending',
            'source' => $source !== '' ? $source : 'website',
            'total' => (float) get_post_meta($booking_id, 'dg_booking_total', true),
            'paid' => $paid === '' ? null : $paid,
            'payment_method' => get_post_meta($booking_id, 'dg_booking_payment_method', true) ?: null,
            'message' => (string) get_post_meta($booking_id, 'dg_booking_message', true),
        ];

        if ($platform_id !== '') {
            $row['platform_id'] = $platform_id;
        }

        return $row;
    }
}