<?php
/**
 * Editable email templates for DigitalGate Marketing.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Email_Templates {

    const OPTION = 'dg_marketing_email_templates';

    public static function defaults() {
        return [
            'audit_lead_initial' => [
                'label' => 'Agency Audit — Lead Initial Email',
                'subject' => 'Your Agency Visibility Audit Results Are In',
                'body' => "Hi {full_name},\n\nYour Agency Visibility Audit for {company_name} is ready.\n\nOverall Score: {overall_score}%\nGrade: {grade}\n\nView your full report: {audit_url}",
            ],
            'audit_admin' => [
                'label' => 'Agency Audit — Admin Notification',
                'subject' => 'New Agency Audit: {company_name}',
                'body' => "New free agency audit submitted.\n\nAgency: {company_name}\nWebsite: {website}\nContact: {full_name}\nEmail: {email}\nPhone: {phone}\nScore: {overall_score}% ({grade})\n\nReport: {audit_url}",
            ],
            'voice_lead_admin' => [
                'label' => 'Voice Agent — Admin Notification',
                'subject' => 'New AI Voice Lead: {full_name}',
                'body' => "New AI voice lead captured.\n\nName: {full_name}\nEmail: {email}\nPhone: {phone}\nBusiness: {business_name}\nScore: {lead_score}/100\nQualified: {qualified}\n\nSummary:\n{call_summary}",
            ],
            'weekly_pipeline_report' => [
                'label' => 'Weekly Pipeline Report — Admin Email',
                'subject' => 'DigitalGate Weekly CRM Report',
                'body' => "Weekly CRM summary ({report_period})\n\nTHIS MONTH\nAudits: {audits_month}\nVoice leads: {voice_month}\nLead → client rate: {conversion_rate}%\n\nLAST {period_days} DAYS\nNew clients: {new_clients}\nAudits: {audits_period}\nVoice leads: {voice_period}\nAutomation emails sent: {automation_sent}\n\nCLIENT PIPELINE\n{status_pipeline}\n\nLEADS BY SOURCE\n{lead_sources}\n\nDashboard:\n{admin_url}",
            ],
            'audit_followup_1' => [
                'label' => 'Audit Sequence — Email 1 (Immediate)',
                'subject' => 'Your Agency Visibility Audit Results Are In',
                'body' => 'Uses automation HTML content from audit sequence.',
            ],
        ];
    }

    public static function all() {
        $saved = get_option(self::OPTION, []);
        $merged = [];
        foreach (self::defaults() as $key => $default) {
            $merged[$key] = wp_parse_args($saved[$key] ?? [], $default);
        }
        return $merged;
    }

    public static function get($key) {
        return self::all()[$key] ?? null;
    }

    public static function save($templates) {
        $clean = [];
        foreach (self::defaults() as $key => $default) {
            if (!isset($templates[$key])) {
                continue;
            }
            $clean[$key] = [
                'subject' => sanitize_text_field($templates[$key]['subject'] ?? $default['subject']),
                'body' => sanitize_textarea_field($templates[$key]['body'] ?? $default['body']),
            ];
        }
        update_option(self::OPTION, $clean);
    }

    public static function render($key, $vars = []) {
        $template = self::get($key);
        if (!$template) {
            return ['subject' => '', 'body' => '', 'body_html' => ''];
        }
        $replacements = [];
        foreach ($vars as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }
        $body = strtr($template['body'], $replacements);
        $subject = strtr($template['subject'], $replacements);
        $body_html = class_exists('DG_Marketing_Emails')
            ? DG_Marketing_Emails::wrap('<p style="color:#E2E8F0;line-height:1.65;">' . nl2br(esc_html($body)) . '</p>')
            : nl2br(esc_html($body));

        return compact('subject', 'body', 'body_html');
    }

    public static function send_mail($to, $template_key, $vars = [], $reply_to = null) {
        $mail = self::render($template_key, $vars);
        if ($mail['subject'] === '') {
            return false;
        }
        $headers = class_exists('DG_Marketing_Emails')
            ? DG_Marketing_Emails::mail_headers(true)
            : ['Content-Type: text/html; charset=UTF-8'];
        if ($reply_to) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        return wp_mail($to, $mail['subject'], $mail['body_html'], $headers);
    }

    public static function placeholders_help() {
        return '{full_name}, {first_name}, {company_name}, {email}, {phone}, {website}, {audit_url}, {overall_score}, {grade}, {lead_score}, {qualified}, {call_summary}, {report_period}, {admin_url}';
    }
}
