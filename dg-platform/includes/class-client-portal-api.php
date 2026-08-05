<?php
/**
 * Client portal REST API for Next.js / Clerk integration.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Client_Portal_API {

    const ROUTE = '/portal/me';

    public static function init() {
        if (!self::enabled()) {
            return;
        }
        add_action('dg_platform_register_rest_routes', [__CLASS__, 'register_routes']);
    }

    public static function enabled() {
        return apply_filters(
            'dg_client_portal_api_enabled',
            class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate()
        );
    }

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, self::ROUTE, [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_me'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'email' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);
    }

    public static function can_access($request) {
        return class_exists('DG_Dev_API') && DG_Dev_API::verify_request($request);
    }

    public static function get_me($request) {
        $email = sanitize_email(
            $request->get_param('email') ?: $request->get_header('X-Portal-Email')
        );
        $clerk_user_id = sanitize_text_field($request->get_header('X-Clerk-User-Id') ?: '');

        if ($email === '') {
            return new WP_Error('dg_portal_missing_email', 'Email is required.', ['status' => 400]);
        }

        if (!class_exists('DG_Client_Portal')) {
            return new WP_Error('dg_portal_unavailable', 'Client portal is unavailable.', ['status' => 503]);
        }

        return rest_ensure_response(
            DG_Client_Portal::profile_for_portal_email($email, $clerk_user_id)
        );
    }
}

add_action('plugins_loaded', ['DG_Client_Portal_API', 'init'], 12);
