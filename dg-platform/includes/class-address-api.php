<?php
/**
 * Address resolve REST + AJAX for website forms and admin tooling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Address_API {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('wp_ajax_dg_resolve_address', [__CLASS__, 'ajax_resolve']);
        add_action('wp_ajax_nopriv_dg_resolve_address', [__CLASS__, 'ajax_resolve']);
    }

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/addresses/resolve', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_resolve'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'rawAddress' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ]);
    }

    public static function can_access($request) {
        if (class_exists('DG_Dev_API') && DG_Dev_API::verify_request($request)) {
            return true;
        }

        if (class_exists('DG_RE_Form_Security')) {
            $guard = DG_RE_Form_Security::guard_rest('resolve_address');
            if (is_wp_error($guard)) {
                return $guard;
            }
            return true;
        }

        return current_user_can('edit_posts');
    }

    public static function rest_resolve($request) {
        $raw = sanitize_text_field($request->get_param('rawAddress') ?? $request->get_param('address') ?? '');
        if ($raw === '') {
            return new WP_Error('validation_error', 'rawAddress is required.', ['status' => 422]);
        }

        if (!class_exists('DG_Address_Resolver')) {
            return new WP_Error('unavailable', 'Address resolver unavailable.', ['status' => 503]);
        }

        $resolved = DG_Address_Resolver::resolve($raw, [
            'geocode' => $request->get_param('geocode') !== false,
            'forceGeocode' => (bool) $request->get_param('forceGeocode'),
            'regionBias' => sanitize_text_field($request->get_param('regionBias') ?? 'Gold Coast, QLD, Australia'),
        ]);

        if (is_wp_error($resolved)) {
            return $resolved;
        }

        return rest_ensure_response(['data' => $resolved]);
    }

    public static function ajax_resolve() {
        if (class_exists('DG_RE_Form_Security')) {
            $guard = DG_RE_Form_Security::guard('resolve_address', $_POST);
            if (empty($guard['ok'])) {
                wp_send_json_error(['message' => $guard['message'] ?? 'Request blocked.'], 429);
            }
        }

        $raw = sanitize_text_field(wp_unslash($_POST['rawAddress'] ?? $_POST['address'] ?? ''));
        if ($raw === '') {
            wp_send_json_error(['message' => 'Address is required.'], 422);
        }

        if (!class_exists('DG_Address_Resolver')) {
            wp_send_json_error(['message' => 'Address resolver unavailable.'], 503);
        }

        $resolved = DG_Address_Resolver::resolve($raw);
        if (is_wp_error($resolved)) {
            wp_send_json_error(['message' => $resolved->get_error_message()], 500);
        }

        wp_send_json_success(['data' => $resolved]);
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('DG_Address_API')) {
        DG_Address_API::init();
    }
}, 20);
