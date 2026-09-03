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

        $secret = self::webhook_secret();
        if ($secret === '') {
            return;
        }

        $row = self::format_booking_row($booking_id);
        if (!$row) {
            return;
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
            // Canonical identity must be acknowledged before WP can persist it.
            'blocking' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-DG-Webhook-Secret' => $secret,
                'X-API-Key' => $secret,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            error_log('DG Acc Platform sync failed: ' . $response->get_error_message());
            return;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            error_log('DG Acc Platform sync failed with HTTP ' . $status);
            return;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $identities = is_array($body) && isset($body['data']['identities']) && is_array($body['data']['identities'])
            ? $body['data']['identities']
            : [];

        foreach ($identities as $identity) {
            if (!is_array($identity) || (int) ($identity['wp_id'] ?? 0) !== $booking_id) {
                continue;
            }
            $platform_id = sanitize_text_field((string) ($identity['platform_id'] ?? ''));
            if ($platform_id !== '') {
                update_post_meta($booking_id, self::PLATFORM_ID_META, $platform_id);
            }
        }
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
