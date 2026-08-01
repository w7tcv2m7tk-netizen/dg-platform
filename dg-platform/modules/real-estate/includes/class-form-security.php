<?php
/**
 * Form security: honeypot, rate limiting, optional tokens for public CRM forms.
 *
 * Roe Realty's Oxygen property-report page posts to admin-ajax without a nonce.
 * Honeypot + rate limit are the primary defences; tokens are used when present.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Form_Security {

    const RATE_WINDOW = 3600;
    const RATE_MAX = 15;

    public static function nonce_bundle() {
        return [
            'property_report' => self::nonce_field('property_report'),
            'create_booking' => self::nonce_field('create_booking'),
            'booking_slots' => self::nonce_field('booking_slots'),
        ];
    }

    public static function send_nonce_response() {
        nocache_headers();
        wp_send_json_success(self::nonce_bundle());
    }

    /**
     * Stateless token — works on cached pages and regardless of login state.
     */
    public static function nonce_field($action) {
        return self::public_token($action);
    }

    private static function token_tick() {
        return (int) floor(time() / (DAY_IN_SECONDS / 2));
    }

    public static function public_token($action) {
        $tick = self::token_tick();
        return substr(hash_hmac('sha256', 'dg_re|' . $action . '|' . $tick, wp_salt('auth')), 0, 16);
    }

    public static function verify_public_token($action, $token) {
        if ($token === '' || strlen($token) !== 16) {
            return false;
        }
        foreach ([self::token_tick(), self::token_tick() - 1] as $tick) {
            $expected = substr(hash_hmac('sha256', 'dg_re|' . $action . '|' . $tick, wp_salt('auth')), 0, 16);
            if (hash_equals($expected, $token)) {
                return true;
            }
        }
        return false;
    }

    public static function verify_nonce($action, $data) {
        $nonce = sanitize_text_field($data['dg_re_nonce'] ?? $data['_wpnonce'] ?? '');
        if ($nonce === '') {
            return false;
        }
        if (self::verify_public_token($action, $nonce)) {
            return true;
        }
        return (bool) wp_verify_nonce($nonce, 'dg_re_' . $action);
    }

    public static function is_honeypot_clean($data) {
        return empty($data['website']);
    }

    public static function client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        }
        return $ip;
    }

    public static function rate_limit_ok($action) {
        $key = 'dg_re_rl_' . md5(self::client_ip() . '|' . $action);
        $count = (int) get_transient($key);
        if ($count >= self::RATE_MAX) {
            return false;
        }
        set_transient($key, $count + 1, self::RATE_WINDOW);
        return true;
    }

    public static function guard($action, $data) {
        if (!self::is_honeypot_clean($data)) {
            return ['ok' => true, 'silent' => true];
        }
        if (!self::rate_limit_ok($action)) {
            return ['ok' => false, 'message' => 'Too many requests. Please wait a while and try again.'];
        }
        // Nonce is optional: legacy Oxygen forms omit it; stale cached tokens must not block real users.
        return ['ok' => true];
    }

    public static function guard_rest($action) {
        if (!self::rate_limit_ok('rest_' . $action)) {
            return new WP_Error('rate_limited', 'Too many requests.', ['status' => 429]);
        }
        return true;
    }
}
