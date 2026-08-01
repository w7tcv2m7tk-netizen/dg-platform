<?php
/**
 * DigitalGate HTML email branding (audit sequence + notifications).
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

    public static function mail_headers() {
        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: Ben Roe | DigitalGate <hello@digitalgate.com.au>',
            'Reply-To: Ben Roe <hello@digitalgate.com.au>',
        ];
    }

    public static function site_url($path = '') {
        return 'https://digitalgate.com.au' . $path;
    }

    public static function wrap($inner_html, $options = []) {
        $footer_note = $options['footer_note'] ?? 'You\'re receiving this because you requested an Agency Visibility Audit.';
        $unsubscribe = $options['unsubscribe_url'] ?? self::site_url('/unsubscribe');

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#EFF3F8;font-family:Georgia,\'Times New Roman\',serif;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#EFF3F8;padding:32px 16px;"><tr><td align="center">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;background:#ffffff;border:1px solid #D8E2EF;border-radius:16px;overflow:hidden;">'
            . '<tr><td style="padding:28px 32px 16px;background:#1a1a2e;border-bottom:3px solid #3B82F6;">'
            . '<div style="font-size:22px;font-weight:700;color:#ffffff;letter-spacing:0.02em;">DigitalGate</div>'
            . '<div style="font-size:13px;color:#94A3B8;margin-top:4px;">Agency Growth &amp; AI Visibility</div>'
            . '</td></tr>'
            . '<tr><td style="padding:32px;font-family:Arial,Helvetica,sans-serif;">' . $inner_html . '</td></tr>'
            . '<tr><td style="padding:20px 32px 28px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:13px;line-height:1.6;color:#64748B;text-align:center;font-family:Arial,Helvetica,sans-serif;">'
            . 'Ben Roe | DigitalGate &nbsp;·&nbsp; <a href="' . esc_url(self::site_url()) . '" style="color:#3B82F6;text-decoration:none;">digitalgate.com.au</a>'
            . '<br><span style="font-size:12px;color:#94A3B8;margin-top:8px;display:inline-block;">'
            . esc_html($footer_note) . ' <a href="' . esc_url($unsubscribe) . '" style="color:#3B82F6;">Unsubscribe</a>'
            . '</span></td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    public static function cta($url, $label) {
        return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 8px;"><tr><td align="center">'
            . '<a href="' . esc_url($url) . '" style="display:inline-block;background:#3B82F6;color:#ffffff;text-decoration:none;font-weight:600;font-size:16px;padding:14px 32px;border-radius:999px;">'
            . esc_html($label) . '</a></td></tr></table>';
    }

    public static function score_table($rows) {
        $html = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:20px 0;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr>';
            $html .= '<td style="padding:12px 16px;border-bottom:1px solid #E2E8F0;width:50%;font-weight:600;color:#475569;background:#F8FAFC;">' . esc_html($label) . '</td>';
            $html .= '<td style="padding:12px 16px;border-bottom:1px solid #E2E8F0;font-weight:700;color:#1a1a2e;">' . esc_html((string) $value) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    public static function initial_audit_email($name, $company_name, $audit_data, $audit_url) {
        $inner = '<h1 style="color:#1a1a2e;font-size:24px;margin:0 0 16px;">Your Agency Visibility Audit</h1>';
        $inner .= '<p style="color:#334155;font-size:16px;line-height:1.65;">Hi ' . esc_html($name) . ',</p>';
        $inner .= '<p style="color:#334155;font-size:16px;line-height:1.65;">Thank you for requesting a Visibility Audit for <strong>' . esc_html($company_name) . '</strong>.</p>';
        $inner .= self::score_table([
            'Overall Score' => $audit_data['overall_score'] . '%',
            'Grade' => $audit_data['grade'],
            'AI Visibility' => $audit_data['ai_score'] . '%',
            'Website Performance' => $audit_data['website_score'] . '%',
        ]);
        if (!empty($audit_data['recommendations'])) {
            $inner .= '<h3 style="color:#1a1a2e;font-size:18px;">Top recommendations</h3><ul style="color:#334155;line-height:1.7;padding-left:20px;">';
            foreach (array_slice($audit_data['recommendations'], 0, 4) as $rec) {
                $inner .= '<li>' . esc_html($rec) . '</li>';
            }
            $inner .= '</ul>';
        }
        $inner .= self::cta($audit_url, 'View Full Report');
        return self::wrap($inner);
    }
}

DG_Marketing_Emails::init();
