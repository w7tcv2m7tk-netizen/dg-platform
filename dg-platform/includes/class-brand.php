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

    /**
     * Icon Light Door — canonical mark for all HTML email templates.
     * Always icon-light.png (never icon-dark / icon-navy).
     */
    public static function icon_email_url() {
        return self::icon_light_url();
    }

    /**
     * Standard &lt;img&gt; for Icon Light Door in HTML email (table-safe inline styles).
     *
     * @param int    $width       Pixel width.
     * @param string $extra_style Additional CSS (no trailing semicolon required).
     */
    public static function email_icon_img($width = 48, $extra_style = '') {
        $url = self::icon_email_url();
        $w = max(16, (int) $width);
        $style = 'max-width:' . $w . 'px;height:auto;display:inline-block;vertical-align:middle;';
        if ($extra_style !== '') {
            $style .= $extra_style;
        }

        return '<img src="' . esc_url($url) . '" alt="" width="' . $w . '" style="' . esc_attr($style) . '">';
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

    /** Email header lockup — Icon Light Door beside wordmark. */
    public static function email_header_lockup($logo_width = 200, $icon_width = 36) {
        $logo_w = max(80, (int) $logo_width);
        $icon = self::email_icon_img($icon_width, 'margin:0;');
        $logo = '<img src="' . esc_url(self::logo_light_url()) . '" alt="DigitalGate" width="' . $logo_w . '" style="max-width:' . $logo_w . 'px;height:auto;display:block;margin:0;">';

        return '<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 12px;">'
            . '<tr><td style="padding-right:10px;vertical-align:middle;">' . $icon . '</td>'
            . '<td style="vertical-align:middle;text-align:left;">' . $logo . '</td></tr>'
            . '</table>';
    }
}
