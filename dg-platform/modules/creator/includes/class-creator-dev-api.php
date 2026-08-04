<?php
/**
 * Creator REST endpoints for Cursor MCP / dev tooling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Creator_Dev_API {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/creator/summary', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_summary'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);
    }

    public static function can_access($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        if (DG_Dev_API::verify_request($request)) {
            return true;
        }
        return class_exists('DG_Creator_Permissions') && DG_Creator_Permissions::can_view();
    }

    public static function get_summary($request) {
        $summary = class_exists('DG_Creator_Reports') ? DG_Creator_Reports::summary() : [];
        $summary['site'] = home_url();
        $summary['site_profile'] = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'Creator';
        $summary['generated_at'] = current_time('mysql');
        $summary['primary_module'] = 'creator';

        return rest_ensure_response($summary);
    }
}

add_action('rest_api_init', function () {
    if (class_exists('DG_Creator_Dev_API')) {
        DG_Creator_Dev_API::register_routes();
    }
});
