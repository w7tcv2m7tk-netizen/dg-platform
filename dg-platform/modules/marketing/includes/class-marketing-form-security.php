<?php
/**
 * Public form security for DigitalGate marketing endpoints.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Form_Security {

    const RATE_WINDOW = 3600;
    const RATE_MAX = 10;

    public static function guard_rest($request, $action = 'agency_audit') {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }
        if (!empty($params['website'])) {
            return new WP_REST_Response(['success' => true, 'message' => 'Received'], 200);
        }
        if (!self::rate_limit_ok($action)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Too many requests. Try again later.'], 429);
        }
        return true;
    }

    public static function rate_limit_ok($action) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $key = 'dg_mk_rate_' . md5($action . '|' . $ip);
        $count = (int) get_transient($key);
        if ($count >= self::RATE_MAX) {
            return false;
        }
        set_transient($key, $count + 1, self::RATE_WINDOW);
        return true;
    }
}
