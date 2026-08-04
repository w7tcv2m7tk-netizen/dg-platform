<?php
/**
 * AI Visibility Pro — site settings and business context.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Visibility_Settings {

    const OPTION = 'dg_ai_visibility_settings';

    /** @var array<string,mixed>|null */
    private static $cache = null;

    public static function is_enabled() {
        if (!class_exists('DG_Plan_Registry')) {
            return true;
        }
        return DG_Plan_Registry::has_premium_app('ai_visibility_pro');
    }

    public static function admin_visible() {
        return self::is_enabled();
    }

    public static function defaults() {
        $label = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : get_bloginfo('name');
        $host = class_exists('DG_Site_Profile') ? DG_Site_Profile::hostname() : '';

        $presets = [
            'digitalgate.com.au' => [
                'business_name' => 'DigitalGate',
                'industry' => 'Business software and digital marketing agency',
                'location' => 'Gold Coast, Queensland, Australia',
                'target_queries' => 'business operating platform, CRM for agencies, AI visibility marketing Australia',
                'competitors' => 'HubSpot, Salesforce, Semrush',
            ],
            'roerealty.com.au' => [
                'business_name' => 'Roe Realty',
                'industry' => 'Residential real estate agency',
                'location' => 'Currumbin Valley and Gold Coast, Queensland, Australia',
                'target_queries' => 'real estate agent Currumbin Valley, Gold Coast property sales, local realtor',
                'competitors' => '',
            ],
            'currumbinvalleyhideaway.com.au' => [
                'business_name' => 'Currumbin Valley Hideaway',
                'industry' => 'Luxury accommodation and glamping retreat',
                'location' => 'Currumbin Valley, Gold Coast, Queensland, Australia',
                'target_queries' => 'Currumbin Valley accommodation, luxury dome stay Gold Coast, rainforest retreat',
                'competitors' => '',
            ],
            'aetherra.com.au' => [
                'business_name' => 'Aetherra',
                'industry' => 'Creator, technology, and creative projects',
                'location' => 'Australia',
                'target_queries' => 'Aetherra creator, technology projects Australia',
                'competitors' => '',
            ],
        ];

        return array_merge([
            'business_name' => $label,
            'industry' => '',
            'location' => 'Australia',
            'website' => home_url('/'),
            'target_queries' => '',
            'competitors' => '',
            'schedule' => 'weekly',
            'llms_txt_enabled' => 1,
            'llms_txt_extra' => '',
        ], $presets[$host] ?? []);
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

    public static function get($key, $default = '') {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    /** @param array<string,mixed> $data */
    public static function save(array $data) {
        $clean = [];
        foreach (self::defaults() as $key => $default) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if ($key === 'website') {
                $clean[$key] = esc_url_raw($data[$key]);
            } elseif (in_array($key, ['llms_txt_extra', 'target_queries', 'competitors'], true)) {
                $clean[$key] = sanitize_textarea_field($data[$key]);
            } elseif ($key === 'llms_txt_enabled') {
                $clean[$key] = !empty($data[$key]) ? 1 : 0;
            } elseif ($key === 'schedule') {
                $clean[$key] = in_array($data[$key], ['off', 'weekly', 'monthly'], true) ? $data[$key] : 'weekly';
            } else {
                $clean[$key] = sanitize_text_field($data[$key]);
            }
        }
        update_option(self::OPTION, $clean);
        self::$cache = null;
    }

    /** @return array<string,string> */
    public static function business_context() {
        $s = self::all();
        return [
            'name' => $s['business_name'],
            'industry' => $s['industry'],
            'location' => $s['location'],
            'website' => $s['website'] ?: home_url('/'),
            'queries' => $s['target_queries'],
        ];
    }
}
