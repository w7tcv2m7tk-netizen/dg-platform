<?php
/**
 * Unified HTML email branding per site profile.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Email_Brand {

    /** @return string digitalgate|roe|cvh|default */
    public static function theme() {
        if (class_exists('DG_Site_Profile')) {
            if (DG_Site_Profile::is_digitalgate()) {
                return 'digitalgate';
            }
            if (DG_Site_Profile::is_roe_realty()) {
                return 'roe';
            }
            if (DG_Site_Profile::is_currumbin_hideaway()) {
                return 'cvh';
            }
        }
        return apply_filters('dg_email_brand_theme', 'default');
    }

    public static function mail_headers($html = true) {
        if (self::theme() === 'digitalgate' && class_exists('DG_Marketing_Emails')) {
            return DG_Marketing_Emails::mail_headers($html);
        }

        $from = self::from_line();
        $headers = ['From: ' . $from];
        $headers[] = $html
            ? 'Content-Type: text/html; charset=UTF-8'
            : 'Content-Type: text/plain; charset=UTF-8';

        return $headers;
    }

    public static function from_line() {
        $themes = [
            'digitalgate' => 'Ben Roe | DigitalGate <hello@digitalgate.com.au>',
            'roe' => 'Roe Realty <info@roerealty.com.au>',
            'cvh' => 'Currumbin Valley Hideaway <bookings@currumbinvalleyhideaway.com.au>',
            'default' => get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];
        return $themes[self::theme()] ?? $themes['default'];
    }

    public static function wrap($inner_html, $options = []) {
        $theme = $options['theme'] ?? self::theme();

        if ($theme === 'digitalgate' && class_exists('DG_Marketing_Emails')) {
            return DG_Marketing_Emails::wrap($inner_html, $options);
        }

        if ($theme === 'roe') {
            return self::roe_wrap($inner_html, $options);
        }

        return self::cvh_wrap($inner_html, $options);
    }

    public static function cta($url, $label, $theme = null) {
        $theme = $theme ?: self::theme();

        if ($theme === 'digitalgate' && class_exists('DG_Marketing_Emails')) {
            return DG_Marketing_Emails::cta($url, $label);
        }

        if ($theme === 'roe') {
            return self::roe_cta($url, $label);
        }

        $url = esc_url($url);
        $label = esc_html($label);

        return '<p style="margin:24px 0;text-align:center;">'
            . '<a href="' . $url . '" style="display:inline-block;padding:12px 24px;background:#B9A48A;color:#fff;text-decoration:none;border-radius:999px;font-weight:600;">'
            . $label . '</a></p>';
    }

    /**
     * @param array<string,string> $rows
     */
    public static function admin_notification($title, array $rows, $options = []) {
        if (self::theme() === 'digitalgate' && class_exists('DG_Marketing_Emails')) {
            return DG_Marketing_Emails::admin_notification($title, $rows, $options);
        }

        $inner = '<h2 style="color:#1C2B2A;margin:0 0 16px;">' . esc_html($title) . '</h2><table style="width:100%;border-collapse:collapse;">';
        foreach ($rows as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $inner .= '<tr><td style="padding:6px 0;color:#6B7A78;width:140px;vertical-align:top;">'
                . esc_html((string) $label) . '</td><td style="padding:6px 0;color:#2F2F2F;">'
                . esc_html((string) $value) . '</td></tr>';
        }
        $inner .= '</table>';

        if (!empty($options['cta_url']) && !empty($options['cta_label'])) {
            $inner .= self::cta($options['cta_url'], $options['cta_label'], $options['theme'] ?? null);
        }

        return self::wrap($inner, $options);
    }

    /**
     * @param array<string,mixed> $options
     */
    private static function cvh_wrap($inner_html, $options = []) {
        $site = $options['site_label'] ?? (class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : get_bloginfo('name'));
        $footer = $options['footer_note'] ?? '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;color:#2F2F2F;background:#F7F4EE;padding:24px;margin:0;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;padding:28px;border-radius:16px;border:1px solid #E0D6CC;">'
            . '<p style="margin:0 0 20px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#B9A48A;">'
            . esc_html($site) . '</p>'
            . $inner_html
            . ($footer !== '' ? '<p style="margin:24px 0 0;font-size:13px;color:#6B7A78;">' . esc_html($footer) . '</p>' : '')
            . '</div></body></html>';
    }

    private static function roe_wrap($inner_html, $options = []) {
        $footer = $options['footer_note'] ?? 'Roe Realty — Currumbin & Southern Gold Coast';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:24px;background:#F5F0EB;font-family:Georgia,\'Times New Roman\',serif;color:#3D4F4D;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #E8DFD6;border-radius:12px;overflow:hidden;">'
            . '<div style="padding:24px 28px;background:#1C2B2A;color:#F5F0EB;text-align:center;">'
            . '<div style="font-size:22px;font-weight:700;letter-spacing:0.04em;">Roe Realty</div>'
            . '</div><div style="padding:28px;">' . $inner_html
            . ($footer !== '' ? '<p style="margin:24px 0 0;font-size:13px;color:#8A9A98;">' . esc_html($footer) . '</p>' : '')
            . '</div></div></body></html>';
    }

    private static function roe_cta($url, $label) {
        return '<p style="margin:24px 0;text-align:center;">'
            . '<a href="' . esc_url($url) . '" style="display:inline-block;padding:14px 28px;background:#C9A46C;color:#1C2B2A;text-decoration:none;border-radius:4px;font-weight:700;">'
            . esc_html($label) . '</a></p>';
    }
}
