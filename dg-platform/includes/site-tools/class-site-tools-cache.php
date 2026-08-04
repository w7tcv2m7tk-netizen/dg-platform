<?php
/**
 * Cache purge — Cloudflare API, Super Page Cache, and common WordPress caches.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Cache {

    /** @return array{success:bool,message:string,methods:array<int,string>} */
    public static function purge_all() {
        $methods = [];

        $cf = self::purge_cloudflare_api();
        if ($cf['success']) {
            $methods[] = 'Cloudflare API';
        }

        foreach (self::purge_plugin_caches() as $label) {
            $methods[] = $label;
        }

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $methods[] = 'WordPress object cache';
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $methods[] = 'PHP OPcache';
        }

        do_action('dg_site_tools_cache_purged', $methods);

        $success = !empty($methods);
        if ($success) {
            update_option('dg_site_tools_last_cache_purge', current_time('mysql'));
            delete_transient('dg_onboarding_summary_v' . (defined('DG_PLATFORM_VERSION') ? DG_PLATFORM_VERSION : '1'));
        }

        return [
            'success' => $success,
            'message' => $success
                ? 'Cache purged via: ' . implode(', ', $methods) . '.'
                : 'No cache layers detected. Configure Cloudflare API credentials in Site Tools.',
            'methods' => $methods,
        ];
    }

    /** @return array{success:bool,message:string} */
    public static function purge_cloudflare_api() {
        $credentials = DG_Site_Tools_Cloudflare::credentials();
        $token = $credentials['token'];
        $zone = $credentials['zone_id'];
        if (!$token || !$zone) {
            return ['success' => false, 'message' => 'Cloudflare API token or zone ID not configured.'];
        }

        $response = wp_remote_post("https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['purge_everything' => true]),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 200 && $code < 300 && !empty($body['success'])) {
            return ['success' => true, 'message' => 'Cloudflare cache purged.'];
        }

        $err = $body['errors'][0]['message'] ?? 'Cloudflare purge failed.';
        return ['success' => false, 'message' => $err];
    }

    /** @return array<int,string> */
    private static function purge_plugin_caches() {
        $purged = [];

        if (function_exists('sw_cloudflare_purge_cache')) {
            sw_cloudflare_purge_cache();
            $purged[] = 'Super Page Cache';
        }

        if (function_exists('wp_cloudflare_purge_cache')) {
            wp_cloudflare_purge_cache();
            $purged[] = 'WP Cloudflare Page Cache';
        }

        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            $purged[] = 'WP Rocket';
        }

        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
            $purged[] = 'LiteSpeed Cache';
        }

        do_action('litespeed_purge_all');
        if (!in_array('LiteSpeed Cache', $purged, true) && has_action('litespeed_purge_all')) {
            $purged[] = 'LiteSpeed Cache (hook)';
        }

        if (class_exists('W3_Plugin_TotalCacheAdmin') && function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
            $purged[] = 'W3 Total Cache';
        }

        return array_values(array_unique($purged));
    }

    /** @return array<string,mixed> */
    public static function status() {
        return [
            'cloudflare_api' => DG_Site_Tools_Cloudflare::is_configured(),
            'super_page_cache' => self::is_plugin_active('wp-cloudflare-page-cache/wp-cloudflare-page-cache.php')
                || self::is_plugin_active('super-page-cache/super-page-cache.php'),
            'wp_rocket' => self::is_plugin_active('wp-rocket/wp-rocket.php'),
            'litespeed' => self::is_plugin_active('litespeed-cache/litespeed-cache.php'),
        ];
    }

    private static function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }
}
