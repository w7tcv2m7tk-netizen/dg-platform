<?php
/**
 * Unified HTML email branding per site profile.
 *
 * CVH / Roe logos are bundled in the plugin and embedded as CID attachments so
 * email clients do not depend on remote /uploads URLs (often blocked by CDN).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Email_Brand {

    /** Legacy remote fallbacks (may 403 behind Cloudflare hotlink protection). */
    const ROE_LOGO_URL = 'https://roerealty.com.au/wp-content/uploads/2026/05/R-Main.png';
    const CVH_LOGO_URL = 'https://currumbinvalleyhideaway.com.au/wp-content/uploads/2026/06/CVH-Logo-and-Icon.png';

    const CID_CVH_LOGO = 'dg-cvh-logo';
    const CID_CVH_ICON = 'dg-cvh-icon';
    const CID_ROE_LOGO = 'dg-roe-logo';

    /** @var bool */
    private static $cid_hooked = false;

    /** @var array<string,string> cid => absolute filesystem path queued for this request */
    private static $pending_cids = [];

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

    /**
     * Human-readable booking channel for emails (guest + owner).
     *
     * @param string $source Raw dg_booking_source / status hint.
     */
    public static function booking_source_label($source) {
        $key = strtolower(trim((string) $source));
        $labels = [
            'airbnb' => 'Airbnb',
            'bookingcom' => 'Booking.com',
            'booking.com' => 'Booking.com',
            'website' => 'Direct',
            'direct' => 'Direct',
            'manual' => 'Manual',
            'payid' => 'Direct',
            'stripe' => 'Direct',
            'enquiry' => 'Direct',
        ];
        $label = $labels[$key] ?? '';
        if ($label === '' && $key !== '') {
            $label = ucwords(str_replace(['_', '-'], ' ', $key));
        }
        $filtered = apply_filters('dg_email_booking_source_label', $label, $key);
        return is_string($filtered) ? $filtered : $label;
    }

    /**
     * Resolve source label from a booking post ID (falls back to status / payment).
     */
    public static function booking_source_label_for($booking_id) {
        $booking_id = (int) $booking_id;
        $source = '';
        if ($booking_id > 0) {
            $source = (string) get_post_meta($booking_id, 'dg_booking_source', true);
            if ($source === '') {
                $status = (string) get_post_meta($booking_id, 'dg_booking_status', true);
                if (in_array($status, ['airbnb', 'bookingcom'], true)) {
                    $source = $status;
                }
            }
            if ($source === '') {
                $method = (string) get_post_meta($booking_id, 'dg_booking_payment_method', true);
                if (in_array($method, ['airbnb', 'bookingcom'], true)) {
                    $source = $method;
                } elseif ($method !== '') {
                    $source = 'direct';
                }
            }
        }
        $label = self::booking_source_label($source !== '' ? $source : 'direct');
        return $label !== '' ? $label : 'Direct';
    }

    /** Absolute path under assets/brand/, or empty. */
    public static function brand_asset_path($filename) {
        if (!defined('DG_PLATFORM_PATH')) {
            return '';
        }
        $path = DG_PLATFORM_PATH . 'assets/brand/' . ltrim((string) $filename, '/');
        return (is_string($path) && file_exists($path)) ? $path : '';
    }

    /** Absolute HTTPS (or site) URL for a bundled brand asset. */
    public static function brand_asset_url($filename) {
        if (!defined('DG_PLATFORM_URL') || self::brand_asset_path($filename) === '') {
            return '';
        }
        $url = trailingslashit(DG_PLATFORM_URL) . 'assets/brand/' . ltrim((string) $filename, '/');
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } elseif (strpos($url, 'http://') === 0 && is_ssl()) {
            $url = 'https://' . substr($url, 7);
        }
        return $url;
    }

    /**
     * Absolute logo URL for a theme. Empty string when unavailable (callers fall back to text).
     *
     * @param string|null $theme digitalgate|roe|cvh|default
     */
    public static function logo_url($theme = null) {
        $theme = $theme ?: self::theme();
        $url = '';

        if ($theme === 'digitalgate') {
            $url = class_exists('DG_Brand') ? DG_Brand::logo_light_url() : '';
        } elseif ($theme === 'roe') {
            $url = self::brand_asset_url('roe-logo.png');
            if ($url === '') {
                $url = self::ROE_LOGO_URL;
            }
        } elseif ($theme === 'cvh') {
            $url = self::brand_asset_url('cvh-logo.png');
            if ($url === '') {
                $url = self::CVH_LOGO_URL;
            }
        }

        $filtered = apply_filters('dg_email_brand_logo_url', $url, $theme);
        return is_string($filtered) ? $filtered : '';
    }

    /**
     * Absolute icon URL for a theme (CVH icon lockup). Empty when unavailable.
     *
     * @param string|null $theme
     */
    public static function icon_url($theme = null) {
        $theme = $theme ?: self::theme();
        $url = '';

        if ($theme === 'digitalgate') {
            $url = class_exists('DG_Brand') ? DG_Brand::icon_email_url() : '';
        } elseif ($theme === 'cvh') {
            $url = self::brand_asset_url('cvh-icon.png');
        }

        $filtered = apply_filters('dg_email_brand_icon_url', $url, $theme);
        return is_string($filtered) ? $filtered : '';
    }

    /**
     * Prefer CID (embedded) src when a local file is queued; else absolute URL.
     *
     * @param string $cid
     * @param string $fallback_url
     * @param string $filename Bundled filename for path lookup
     */
    private static function img_src($cid, $fallback_url, $filename = '') {
        $path = $filename !== '' ? self::brand_asset_path($filename) : '';
        if ($path !== '' && $cid !== '') {
            self::queue_cid($cid, $path);
            return 'cid:' . $cid;
        }
        return $fallback_url;
    }

    /**
     * Escapes img src; allows cid: (esc_url strips unknown protocols).
     */
    private static function esc_img_src($src) {
        $src = (string) $src;
        if (strpos($src, 'cid:') === 0) {
            return esc_attr($src);
        }
        return esc_url($src);
    }

    /**
     * Table-safe &lt;img&gt; for email headers. Returns '' when no logo URL (use text wordmark).
     *
     * @param string|null $theme
     * @param int         $width Max display width (~160–200).
     * @param string      $alt   Alt text; defaults per theme.
     */
    public static function logo_img($theme = null, $width = 180, $alt = '') {
        $theme = $theme ?: self::theme();
        $defaults = [
            'digitalgate' => 'DigitalGate',
            'roe' => 'Roe Realty',
            'cvh' => 'Currumbin Valley Hideaway',
        ];
        if ($alt === '') {
            $alt = $defaults[$theme] ?? (class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : get_bloginfo('name'));
        }

        $filename = $theme === 'cvh' ? 'cvh-logo.png' : ($theme === 'roe' ? 'roe-logo.png' : '');
        $cid = $theme === 'cvh' ? self::CID_CVH_LOGO : ($theme === 'roe' ? self::CID_ROE_LOGO : '');
        $url = self::logo_url($theme);
        $src = self::img_src($cid, $url, $filename);
        if ($src === '' || $src === 'cid:') {
            return '';
        }

        $w = max(80, min(240, (int) $width));

        return '<img src="' . self::esc_img_src($src) . '" alt="' . esc_attr($alt) . '" width="' . $w . '" style="max-width:' . $w . 'px;height:auto;display:block;margin:0 auto;border:0;outline:none;text-decoration:none;">';
    }

    /**
     * Icon + logo lockup for CVH (and DigitalGate via DG_Brand).
     *
     * @param string|null $theme
     * @param int         $logo_width
     * @param int         $icon_width
     */
    public static function header_lockup($theme = null, $logo_width = 180, $icon_width = 40) {
        $theme = $theme ?: self::theme();

        if ($theme === 'digitalgate' && class_exists('DG_Brand')) {
            return DG_Brand::email_header_lockup($logo_width, $icon_width);
        }

        if ($theme !== 'cvh') {
            return self::logo_img($theme, $logo_width);
        }

        $logo_w = max(80, min(240, (int) $logo_width));
        $icon_w = max(24, min(72, (int) $icon_width));

        $icon_src = self::img_src(self::CID_CVH_ICON, self::icon_url('cvh'), 'cvh-icon.png');
        $logo_src = self::img_src(self::CID_CVH_LOGO, self::logo_url('cvh'), 'cvh-logo.png');

        if ($logo_src === '' && $icon_src === '') {
            return '';
        }

        $icon_html = $icon_src !== ''
            ? '<img src="' . self::esc_img_src($icon_src) . '" alt="" width="' . $icon_w . '" style="max-width:' . $icon_w . 'px;height:auto;display:block;border:0;outline:none;">'
            : '';
        $logo_html = $logo_src !== ''
            ? '<img src="' . self::esc_img_src($logo_src) . '" alt="Currumbin Valley Hideaway" width="' . $logo_w . '" style="max-width:' . $logo_w . 'px;height:auto;display:block;border:0;outline:none;">'
            : '';

        if ($icon_html === '') {
            return '<div style="margin:0 0 20px;text-align:center;">' . $logo_html . '</div>';
        }
        if ($logo_html === '') {
            return '<div style="margin:0 0 20px;text-align:center;">' . $icon_html . '</div>';
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 20px;">'
            . '<tr><td style="padding-right:12px;vertical-align:middle;">' . $icon_html . '</td>'
            . '<td style="vertical-align:middle;text-align:left;">' . $logo_html . '</td></tr>'
            . '</table>';
    }

    private static function queue_cid($cid, $path) {
        if ($cid === '' || $path === '' || !file_exists($path)) {
            return;
        }
        self::$pending_cids[$cid] = $path;
        self::ensure_cid_hook();
    }

    private static function ensure_cid_hook() {
        if (self::$cid_hooked) {
            return;
        }
        self::$cid_hooked = true;
        add_action('phpmailer_init', [__CLASS__, 'embed_pending_cids'], 40);
        // Allow cid: through kses if any filter runs on message body.
        add_filter('kses_allowed_protocols', [__CLASS__, 'allow_cid_protocol']);
    }

    /**
     * @param array<int,string> $protocols
     * @return array<int,string>
     */
    public static function allow_cid_protocol($protocols) {
        if (!in_array('cid', $protocols, true)) {
            $protocols[] = 'cid';
        }
        return $protocols;
    }

    /**
     * @param PHPMailer\PHPMailer\PHPMailer|PHPMailer $phpmailer
     */
    public static function embed_pending_cids($phpmailer) {
        if (self::$pending_cids === []) {
            return;
        }
        foreach (self::$pending_cids as $cid => $path) {
            if (!file_exists($path)) {
                continue;
            }
            try {
                $phpmailer->addEmbeddedImage($path, $cid, basename($path), 'base64', 'image/png');
            } catch (\Throwable $e) {
                // Fall back to remote URL already in HTML if embed fails.
            }
        }
        // Clear after each send so the next mail starts fresh.
        self::$pending_cids = [];
    }

    /** Convert plain text to simple HTML paragraphs (escaped). */
    public static function plain_to_html($text) {
        $text = (string) $text;
        $parts = preg_split("/\n{2,}/", trim($text)) ?: [];
        if ($parts === []) {
            return '';
        }
        $html = '';
        foreach ($parts as $part) {
            $html .= '<p style="margin:0 0 14px;line-height:1.6;color:inherit;">'
                . nl2br(esc_html($part)) . '</p>';
        }
        return $html;
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
        $lockup = self::header_lockup('cvh', 180, 40);
        $header = $lockup !== ''
            ? $lockup
            : '<p style="margin:0 0 20px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#B9A48A;text-align:center;">'
                . esc_html($site) . '</p>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;color:#2F2F2F;background:#F7F4EE;padding:24px;margin:0;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;padding:28px;border-radius:16px;border:1px solid #E0D6CC;">'
            . $header
            . $inner_html
            . ($footer !== '' ? '<p style="margin:24px 0 0;font-size:13px;color:#6B7A78;">' . esc_html($footer) . '</p>' : '')
            . '</div></body></html>';
    }

    private static function roe_wrap($inner_html, $options = []) {
        $footer = $options['footer_note'] ?? 'Roe Realty — Currumbin & Southern Gold Coast';
        $logo = self::logo_img('roe', 160, 'Roe Realty');
        $header_mark = $logo !== ''
            ? $logo
            : '<div style="font-size:22px;font-weight:700;letter-spacing:0.04em;color:#1C2B2A;">Roe Realty</div>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:24px;background:#F5F0EB;font-family:Georgia,\'Times New Roman\',serif;color:#3D4F4D;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #E8DFD6;border-radius:12px;overflow:hidden;">'
            . '<div style="padding:24px 28px;background:#ffffff;text-align:center;border-bottom:3px solid #C9A46C;">'
            . $header_mark
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
