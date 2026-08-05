<?php
/**
 * DigitalGate brand assets — bundled in plugin.
 *
 * Default: light marks on dark backgrounds.
 * Use navy marks on white/light surfaces only.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Brand {

    public static function asset_url($filename) {
        return trailingslashit(DG_PLATFORM_URL) . 'assets/brand/' . ltrim($filename, '/');
    }

    /** Light icon + wordmark for dark UI (platform default) */
    public static function icon_light_url() {
        return self::asset_url('icon-light.png');
    }

    public static function logo_light_url() {
        return self::asset_url('logo-light.png');
    }

    /** Navy marks for light/white backgrounds */
    public static function icon_navy_url() {
        return self::asset_url('icon-navy.png');
    }

    public static function logo_navy_url() {
        return self::asset_url('logo-navy.png');
    }

    /** Dark marks (alternate light-bg option) */
    public static function icon_dark_url() {
        return self::asset_url('icon-dark.png');
    }

    public static function logo_dark_url() {
        return self::asset_url('logo-dark.png');
    }

    /**
     * @param string $theme on-dark|on-light
     * @return array{icon:string,logo:string}
     */
    public static function for_theme($theme = 'on-dark') {
        if ($theme === 'on-light') {
            return [
                'icon' => self::icon_navy_url(),
                'logo' => self::logo_navy_url(),
            ];
        }
        return [
            'icon' => self::icon_light_url(),
            'logo' => self::logo_light_url(),
        ];
    }
}
