<?php
/**
 * Site-specific module defaults (DigitalGate vs Roe Realty).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Profile {

    const OPTION = 'dg_platform_site_profile_applied';

    public static function hostname() {
        $host = parse_url(home_url(), PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    public static function is_roe_realty() {
        $host = self::hostname();
        return $host !== '' && (strpos($host, 'roerealty.com.au') !== false);
    }

    public static function is_digitalgate() {
        $host = self::hostname();
        return $host !== '' && (strpos($host, 'digitalgate.com.au') !== false);
    }

    public static function recommended_modules() {
        if (self::is_roe_realty()) {
            return ['core', 'real-estate'];
        }
        if (self::is_digitalgate()) {
            return ['core', 'marketing', 'accommodation'];
        }
        return ['core', 'marketing'];
    }

    public static function label() {
        if (self::is_roe_realty()) {
            return 'Roe Realty';
        }
        if (self::is_digitalgate()) {
            return 'DigitalGate';
        }
        return 'DG Platform';
    }

    /**
     * Apply hostname-based module defaults on first activation only.
     */
    public static function maybe_apply_defaults($force = false) {
        if (!$force && get_option(self::OPTION)) {
            return;
        }

        $modules = self::recommended_modules();
        update_option('dg_platform_active_modules', $modules);
        update_option(self::OPTION, self::hostname() . '|' . implode(',', $modules));
    }
}
