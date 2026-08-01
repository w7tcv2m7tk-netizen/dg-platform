<?php
/**
 * Form security: nonces, honeypot, rate limiting for public CRM forms.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Form_Security {

    const RATE_WINDOW = 3600;
    const RATE_MAX = 15;

    public static function nonce_field($action) {
        return wp_create_nonce('dg_re_' . $action);
    }

    public static function verify_nonce($action, $data) {
        $nonce = sanitize_text_field($data['dg_re_nonce'] ?? $data['_wpnonce'] ?? '');
        return $nonce && wp_verify_nonce($nonce, 'dg_re_' . $action);
    }

    public static function is_honeypot_clean($data) {
        return empty($data['website']);
    }

    public static function client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
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
        if (!self::verify_nonce($action, $data)) {
            return ['ok' => false, 'message' => 'Security check failed. Please refresh the page and try again.'];
        }
        if (!self::rate_limit_ok($action)) {
            return ['ok' => false, 'message' => 'Too many requests. Please wait a while and try again.'];
        }
        return ['ok' => true];
    }

    public static function guard_rest($action) {
        if (!self::rate_limit_ok('rest_' . $action)) {
            return new WP_Error('rate_limited', 'Too many requests.', ['status' => 429]);
        }
        return true;
    }
}
