<?php
/**
 * Site Tools settings — cache, images, SMTP, Cloudflare, snippets.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Settings {

    const OPTION = 'dg_site_tools_settings';

    /** @var array<string,mixed>|null */
    private static $cache = null;

    public static function defaults() {
        return [
            'enabled' => 1,
            'compress_on_upload' => 1,
            'jpeg_quality' => 82,
            'max_image_width' => 2560,
            'webp_convert' => 0,
            'smtp_enabled' => 0,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_from_email' => '',
            'smtp_from_name' => '',
            'cf_api_token' => '',
            'cf_zone_id' => '',
            'cf_account_id' => '',
            'gsc_property' => '',
            'pagespeed_mobile' => null,
            'pagespeed_desktop' => null,
            'pagespeed_checked_at' => '',
        ];
    }

    /** @return array<string,mixed> */
    public static function all() {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        self::$cache = wp_parse_args($saved, self::defaults());
        return self::$cache;
    }

    public static function get($key, $default = null) {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    /** @param array<string,mixed> $data */
    public static function save(array $data) {
        $current = self::all();
        $allowed = array_keys(self::defaults());
        $next = $current;

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (in_array($key, ['enabled', 'compress_on_upload', 'webp_convert', 'smtp_enabled'], true)) {
                $next[$key] = !empty($value) ? 1 : 0;
            } elseif (in_array($key, ['jpeg_quality', 'max_image_width', 'smtp_port'], true)) {
                $next[$key] = (int) $value;
            } elseif (in_array($key, ['smtp_pass', 'cf_api_token'], true)) {
                $value = is_string($value) ? wp_unslash($value) : $value;
                if ($value !== '' && $value !== '********') {
                    $next[$key] = sanitize_text_field($value);
                }
            } elseif (in_array($key, ['pagespeed_mobile', 'pagespeed_desktop'], true)) {
                $next[$key] = $value === '' || $value === null ? null : (int) $value;
            } else {
                $next[$key] = sanitize_text_field(wp_unslash((string) $value));
            }
        }

        update_option(self::OPTION, $next);
        self::$cache = null;
    }

    public static function is_enabled() {
        return (bool) self::get('enabled', 1);
    }

    public static function can_use() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        if (!class_exists('DG_Plan_Registry')) {
            return true;
        }
        return DG_Plan_Registry::has_feature('website_manager') || DG_Plan_Registry::meets_tier('professional');
    }
}
