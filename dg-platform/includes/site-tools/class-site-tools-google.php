<?php
/**
 * Google integrations — PageSpeed (Site Kit overlap) and Search Console status.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Google {

    /** @return array{success:bool,mobile?:int,desktop?:int,message?:string} */
    public static function refresh_pagespeed() {
        $key = class_exists('DG_Integrations') ? DG_Integrations::get_api_key('pagespeed') : get_option('dg_pagespeed_api_key', '');
        if (!$key) {
            return ['success' => false, 'message' => 'PageSpeed API key not set. Add it under DG Platform → API Settings.'];
        }

        $url = home_url('/');
        $mobile = self::fetch_pagespeed_score($url, $key, 'mobile');
        $desktop = self::fetch_pagespeed_score($url, $key, 'desktop');

        if ($mobile === null && $desktop === null) {
            return ['success' => false, 'message' => 'Unable to fetch PageSpeed scores.'];
        }

        DG_Site_Tools_Settings::save([
            'pagespeed_mobile' => $mobile,
            'pagespeed_desktop' => $desktop,
            'pagespeed_checked_at' => current_time('mysql'),
        ]);

        return [
            'success' => true,
            'mobile' => $mobile,
            'desktop' => $desktop,
        ];
    }

    private static function fetch_pagespeed_score($url, $key, $strategy) {
        $endpoint = add_query_arg([
            'url' => $url,
            'key' => $key,
            'strategy' => $strategy,
            'category' => 'performance',
        ], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed');

        $response = wp_remote_get($endpoint, ['timeout' => 45]);
        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $score = $body['lighthouseResult']['categories']['performance']['score'] ?? null;
        return $score !== null ? (int) round($score * 100) : null;
    }

    /** @return array<string,mixed> */
    public static function dashboard_summary() {
        $settings = DG_Site_Tools_Settings::all();
        $gsc = class_exists('DG_Integrations') ? DG_Integrations::get_gsc_data(home_url('/')) : ['available' => false];

        return [
            'pagespeed_mobile' => $settings['pagespeed_mobile'],
            'pagespeed_desktop' => $settings['pagespeed_desktop'],
            'pagespeed_checked_at' => $settings['pagespeed_checked_at'],
            'pagespeed_key_set' => (bool) (class_exists('DG_Integrations') ? DG_Integrations::get_api_key('pagespeed') : get_option('dg_pagespeed_api_key')),
            'gsc_property' => $settings['gsc_property'],
            'gsc_available' => !empty($gsc['available']),
            'site_kit_active' => self::is_plugin_active('google-site-kit/google-site-kit.php'),
            'analytics_pro' => class_exists('DG_Analytics_Pro_Settings') && DG_Analytics_Pro_Settings::is_enabled(),
            'seo_module' => class_exists('DG_SEO_Settings'),
        ];
    }

    private static function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }
}
