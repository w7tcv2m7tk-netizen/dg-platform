<?php
/**
 * DigitalGate HTML email branding (audit sequence + notifications).
 * Matches digitalgate.com.au — dark premium, Inter, blue gradient CTAs.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Emails {

    public static function init() {
        if (!class_exists('DG_Site_Profile') || !DG_Site_Profile::is_digitalgate()) {
            return;
        }
        add_filter('wp_mail_from', [__CLASS__, 'mail_from_address']);
        add_filter('wp_mail_from_name', [__CLASS__, 'mail_from_name']);
    }

    public static function mail_from_address($email) {
        return 'hello@digitalgate.com.au';
    }

    public static function mail_from_name($name) {
        return 'Ben Roe | DigitalGate';
    }

    public static function mail_headers($html = true) {
        $headers = [
            'From: Ben Roe | DigitalGate <hello@digitalgate.com.au>',
            'Reply-To: Ben Roe <hello@digitalgate.com.au>',
        ];
        $headers[] = $html
            ? 'Content-Type: text/html; charset=UTF-8'
            : 'Content-Type: text/plain; charset=UTF-8';
        return $headers;
    }

    public static function site_url($path = '') {
        return 'https://digitalgate.com.au' . $path;
    }

    public static function wrap($inner_html, $options = []) {
        $footer_note = $options['footer_note'] ?? 'You\'re receiving this because you requested an Agency Visibility Audit.';
        $unsubscribe = $options['unsubscribe_url'] ?? self::site_url('/unsubscribe');

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">'
            . '</head>'
            . '<body style="margin:0;padding:0;background:#0A0F1A;font-family:Inter,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#0A0F1A;padding:32px 16px;"><tr><td align="center">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;background:#141B2B;border:1px solid rgba(59,130,246,0.12);border-radius:24px;overflow:hidden;">'
            . '<tr><td style="padding:32px 32px 24px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06);">'
            . (class_exists('DG_Brand') ? DG_Brand::email_header_lockup(200, 36) : '')
            . '<div style="font-size:12px;font-weight:600;color:#94A3B8;letter-spacing:0.12em;text-transform:uppercase;">AI Visibility &amp; Lead Generation</div>'
            . '</td></tr>'
            . '<tr><td style="padding:32px;font-family:Inter,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;color:#E2E8F0;">' . $inner_html . '</td></tr>'
            . '<tr><td style="padding:24px 32px 32px;background:#0A0F1A;border-top:1px solid rgba(255,255,255,0.06);text-align:center;">'
            . (class_exists('DG_Brand') ? DG_Brand::email_icon_img(48, 'opacity:0.85;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;') : '')
            . '<p style="margin:0 0 6px;font-size:13px;line-height:1.6;color:#64748B;">'
            . '© ' . date('Y') . ' DigitalGate. All rights reserved.'
            . '</p>'
            . '<p style="margin:0 0 6px;font-size:13px;line-height:1.6;color:#64748B;">'
            . '<a href="' . esc_url(self::site_url()) . '" style="color:#60A5FA;text-decoration:none;">digitalgate.com.au</a>'
            . '</p>'
            . '<p style="margin:12px 0 0;font-size:11px;line-height:1.5;color:#475569;">'
            . esc_html($footer_note) . ' <a href="' . esc_url($unsubscribe) . '" style="color:#60A5FA;">Unsubscribe</a>'
            . '</p>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    public static function cta($url, $label) {
        return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 8px;"><tr><td align="center">'
            . '<a href="' . esc_url($url) . '" style="display:inline-block;background:#3B82F6;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:14px 36px;border-radius:50px;box-shadow:0 8px 30px rgba(59,130,246,0.25);">'
            . esc_html($label) . '</a></td></tr></table>';
    }

    public static function admin_notification($heading, $rows, $options = []) {
        $inner = '<h1 style="color:#FFFFFF;font-size:22px;font-weight:700;margin:0 0 20px;letter-spacing:-0.02em;">' . esc_html($heading) . '</h1>';
        $inner .= self::detail_table($rows);

        if (!empty($options['body_html'])) {
            $inner .= $options['body_html'];
        }

        if (!empty($options['cta_url']) && !empty($options['cta_label'])) {
            $inner .= self::cta($options['cta_url'], $options['cta_label']);
        }

        if (!empty($options['secondary_cta_url']) && !empty($options['secondary_cta_label'])) {
            $inner .= '<p style="text-align:center;margin:0;"><a href="' . esc_url($options['secondary_cta_url']) . '" style="color:#60A5FA;text-decoration:none;font-size:14px;">'
                . esc_html($options['secondary_cta_label']) . ' →</a></p>';
        }

        return self::wrap($inner, array_merge([
            'footer_note' => 'Internal notification from DigitalGate.',
        ], $options));
    }

    public static function detail_table($rows) {
        $html = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:0 0 8px;">';
        foreach ($rows as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $display = $value;
            if (is_string($value) && preg_match('#^https?://#', $value)) {
                $display = '<a href="' . esc_url($value) . '" style="color:#60A5FA;text-decoration:none;">' . esc_html($value) . '</a>';
            } else {
                $display = esc_html((string) $value);
            }
            $html .= '<tr>';
            $html .= '<td style="padding:10px 12px;border-bottom:1px solid rgba(255,255,255,0.06);width:38%;font-weight:600;color:#94A3B8;vertical-align:top;">' . esc_html($label) . '</td>';
            $html .= '<td style="padding:10px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#E2E8F0;vertical-align:top;">' . $display . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    public static function score_table($rows) {
        $html = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:20px 0;border-radius:12px;overflow:hidden;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr>';
            $html .= '<td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.06);width:50%;font-weight:600;color:#94A3B8;background:rgba(255,255,255,0.03);">' . esc_html($label) . '</td>';
            $html .= '<td style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.06);font-weight:700;color:#FFFFFF;background:rgba(255,255,255,0.03);">' . esc_html((string) $value) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    public static function initial_audit_email($name, $company_name, $audit_data, $audit_url) {
        $first_name = class_exists('DG_Email_Names') ? DG_Email_Names::first_name($name) : $name;
        $inner = '<h1 style="color:#FFFFFF;font-size:24px;font-weight:700;margin:0 0 16px;letter-spacing:-0.02em;">Your Agency Visibility Audit</h1>';
        $inner .= '<p style="color:#E2E8F0;font-size:16px;line-height:1.65;margin:0 0 16px;">Hi ' . esc_html($first_name) . ',</p>';
        $inner .= '<p style="color:#E2E8F0;font-size:16px;line-height:1.65;margin:0 0 16px;">Thank you for requesting a Visibility Audit for <strong style="color:#60A5FA;">' . esc_html($company_name) . '</strong>.</p>';
        $inner .= self::score_table([
            'Overall Score' => $audit_data['overall_score'] . '%',
            'Grade' => $audit_data['grade'],
            'AI Visibility' => $audit_data['ai_score'] . '%',
            'Website Performance' => $audit_data['website_score'] . '%',
        ]);
        if (!empty($audit_data['recommendations'])) {
            $inner .= '<h3 style="color:#FFFFFF;font-size:18px;font-weight:600;margin:24px 0 12px;">Top recommendations</h3>';
            $inner .= '<ul style="color:#E2E8F0;line-height:1.7;padding-left:20px;margin:0;">';
            foreach (array_slice($audit_data['recommendations'], 0, 4) as $rec) {
                $inner .= '<li style="margin-bottom:8px;">' . esc_html($rec) . '</li>';
            }
            $inner .= '</ul>';
        }
        $inner .= self::cta($audit_url, 'View Full Report');
        return self::wrap($inner);
    }
}

DG_Marketing_Emails::init();
