<?php
/**
 * Authenticated Site Tools REST endpoints for Gen 2 platform connector.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Dev_API {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/site/health', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_site_health'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);
    }

    public static function can_access($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        return DG_Dev_API::verify_request($request);
    }

    public static function get_site_health($request) {
        if (!class_exists('DG_Site_Tools_Health')) {
            return new WP_Error('unavailable', 'Site Tools health unavailable.', ['status' => 503]);
        }

        $health = DG_Site_Tools_Health::run();
        $settings = class_exists('DG_Site_Tools_Settings') ? DG_Site_Tools_Settings::all() : [];

        $ssl = is_ssl() || (strpos(home_url(), 'https://') === 0);

        return rest_ensure_response([
            'site' => home_url(),
            'generated_at' => current_time('c'),
            'score' => (int) ($health['score'] ?? 0),
            'pass' => (int) ($health['pass'] ?? 0),
            'warn' => (int) ($health['warn'] ?? 0),
            'fail' => (int) ($health['fail'] ?? 0),
            'checks' => $health['checks'] ?? [],
            'pagespeed' => [
                'mobile' => isset($settings['pagespeed_mobile']) ? (int) $settings['pagespeed_mobile'] : null,
                'desktop' => isset($settings['pagespeed_desktop']) ? (int) $settings['pagespeed_desktop'] : null,
                'checked_at' => !empty($settings['pagespeed_checked_at']) ? $settings['pagespeed_checked_at'] : null,
            ],
            'ssl' => [
                'enabled' => $ssl,
            ],
        ]);
    }
}

add_action('rest_api_init', function () {
    if (class_exists('DG_Site_Tools_Dev_API')) {
        DG_Site_Tools_Dev_API::register_routes();
    }
});
