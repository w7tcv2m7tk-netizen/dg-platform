<?php
/**
 * Site-specific module defaults — one vertical module per production site.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Profile {

    const OPTION = 'dg_platform_site_profile_applied';

    /**
     * Production sites: hostname => config.
     * Each site runs core + exactly one vertical module.
     */
    public static function sites() {
        return [
            'digitalgate.com.au' => [
                'label' => 'DigitalGate',
                'module' => 'marketing',
                'modules' => ['core', 'marketing'],
            ],
            'roerealty.com.au' => [
                'label' => 'Roe Realty',
                'module' => 'real-estate',
                'modules' => ['core', 'real-estate'],
            ],
            'currumbinvalleyhideaway.com.au' => [
                'label' => 'Currumbin Valley Hideaway',
                'module' => 'accommodation',
                'modules' => ['core', 'accommodation'],
            ],
            'aetherra.com.au' => [
                'label' => 'Aetherra',
                'module' => 'creator',
                'modules' => ['core', 'creator'],
            ],
        ];
    }

    public static function hostname() {
        $host = parse_url(home_url(), PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    public static function matched_site() {
        $host = self::hostname();
        if ($host === '') {
            return null;
        }
        foreach (self::sites() as $domain => $config) {
            if ($host === $domain || strpos($host, $domain) !== false) {
                return array_merge($config, ['domain' => $domain]);
            }
        }
        return null;
    }

    public static function primary_module() {
        $site = self::matched_site();
        return $site ? $site['module'] : null;
    }

    public static function is_roe_realty() {
        return self::primary_module() === 'real-estate';
    }

    public static function is_digitalgate() {
        return self::primary_module() === 'marketing';
    }

    public static function is_currumbin_hideaway() {
        return self::primary_module() === 'accommodation';
    }

    public static function is_aetherra() {
        return self::primary_module() === 'creator';
    }

    public static function recommended_modules() {
        $site = self::matched_site();
        if ($site) {
            return $site['modules'];
        }
        return ['core', 'marketing'];
    }

    public static function label() {
        $site = self::matched_site();
        return $site ? $site['label'] : 'DG Platform';
    }

    /**
     * Apply hostname-based module list (first activation or forced sync).
     */
    public static function apply_recommended_modules($force = false) {
        if (!$force && get_option(self::OPTION)) {
            return;
        }
        $modules = self::recommended_modules();
        update_option('dg_platform_active_modules', $modules);
        update_option(self::OPTION, self::hostname() . '|' . implode(',', $modules));
    }

    /** @deprecated Use apply_recommended_modules() */
    public static function maybe_apply_defaults($force = false) {
        self::apply_recommended_modules($force);
    }

    /**
     * True when active modules differ from this hostname's recommended set.
     */
    public static function modules_need_sync() {
        $recommended = self::recommended_modules();
        $active = get_option('dg_platform_active_modules', ['core']);
        sort($recommended);
        $active_sorted = array_values(array_filter($active));
        sort($active_sorted);
        return $recommended !== $active_sorted;
    }
}
