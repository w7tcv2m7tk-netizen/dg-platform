<?php
/**
 * Admin email notifications for DigitalGate Marketing CRM events.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Admin_Notifications {

    public static function init() {
        add_action('dg_marketing_audit_created', [__CLASS__, 'on_audit_created'], 20, 6);
        add_action('dg_marketing_voice_lead_created', [__CLASS__, 'on_voice_lead_created'], 20, 4);
        add_action('dg_marketing_client_created', [__CLASS__, 'on_client_created'], 20, 2);
    }

    public static function admin_email() {
        return apply_filters('dg_marketing_admin_email', get_option('admin_email'));
    }

    public static function send_html($subject, $body_html, $reply_to = null) {
        $headers = class_exists('DG_Marketing_Emails')
            ? DG_Marketing_Emails::mail_headers(true)
            : ['Content-Type: text/html; charset=UTF-8'];
        if ($reply_to) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        return wp_mail(self::admin_email(), $subject, $body_html, $headers);
    }

    public static function on_audit_created($company_id, $full_name, $email, $phone, $agency_name, $audit_context) {
        $client_url = admin_url('admin.php?page=dg-platform-clients&client_id=' . (int) $company_id . '&tab=view');
        $audit_data = $audit_context['audit_data'] ?? [];
        $audit_url = $audit_context['audit_url'] ?? '';

        if (class_exists('DG_Marketing_Emails')) {
            $message = DG_Marketing_Emails::admin_notification('New Free Agency Audit', [
                'Agency' => $agency_name,
                'Website' => $audit_context['website'] ?? '',
                'Contact' => $full_name,
                'Email' => $email,
                'Phone' => $phone ?: 'Not provided',
                'Overall Score' => ($audit_data['overall_score'] ?? '') . '% (' . ($audit_data['grade'] ?? '') . ')',
                'AI Visibility' => ($audit_data['ai_score'] ?? '') . '%',
                'Website Performance' => ($audit_data['website_score'] ?? '') . '%',
            ], [
                'footer_note' => 'Internal DigitalGate notification.',
                'cta_url' => $audit_url,
                'cta_label' => 'View Audit Report',
                'secondary_cta_url' => $client_url,
                'secondary_cta_label' => 'Open Client in CRM',
            ]);
            self::send_html('New Agency Audit: ' . $agency_name, $message, $email ? $full_name . ' <' . $email . '>' : null);
            return;
        }

        if (class_exists('DG_Marketing_Email_Templates')) {
            DG_Marketing_Email_Templates::send_mail(self::admin_email(), 'audit_admin', [
                'full_name' => $full_name,
                'company_name' => $agency_name,
                'email' => $email,
                'phone' => $phone ?: 'Not provided',
                'website' => $audit_context['website'] ?? '',
                'overall_score' => $audit_data['overall_score'] ?? '',
                'grade' => $audit_data['grade'] ?? '',
                'audit_url' => $audit_url,
            ]);
        }
    }

    public static function on_voice_lead_created($company_id, $data, $score, $qualified) {
        $client_url = admin_url('admin.php?page=dg-platform-clients&client_id=' . (int) $company_id . '&tab=view');
        $booking_url = class_exists('DG_Marketing_Voice') ? DG_Marketing_Voice::booking_url($data) : home_url('/strategy-session');
        $subject = 'New AI Voice Lead: ' . sanitize_text_field($data['name'] ?? 'Unknown');

        if (class_exists('DG_Marketing_Emails')) {
            $summary = sanitize_textarea_field($data['ai_call_summary'] ?? '');
            $body_html = '';
            if ($summary !== '') {
                $body_html = '<h3 style="color:#FFFFFF;font-size:16px;margin:24px 0 8px;">Call summary</h3>'
                    . '<p style="color:#E2E8F0;font-size:15px;line-height:1.65;margin:0;">' . nl2br(esc_html($summary)) . '</p>';
            }
            $message = DG_Marketing_Emails::admin_notification('New AI Voice Lead', [
                'Name' => sanitize_text_field($data['name'] ?? 'N/A'),
                'Email' => sanitize_email($data['email'] ?? ''),
                'Phone' => sanitize_text_field($data['phone'] ?? 'N/A'),
                'Business' => sanitize_text_field($data['business_name'] ?? 'N/A'),
                'Website' => esc_url_raw($data['website_url'] ?? '') ?: 'N/A',
                'Service interest' => sanitize_text_field($data['service_interest'] ?? 'N/A'),
                'Budget' => sanitize_text_field($data['budget_range'] ?? 'N/A'),
                'Lead score' => $score . '/100',
                'Qualified' => $qualified ? 'Yes' : 'No',
                'CRM client ID' => (string) $company_id,
            ], [
                'body_html' => $body_html,
                'footer_note' => 'Internal DigitalGate notification — AI voice agent.',
                'cta_url' => $booking_url,
                'cta_label' => 'Suggested booking link',
                'secondary_cta_url' => $client_url,
                'secondary_cta_label' => 'Open client in CRM',
            ]);
            self::send_html($subject, $message);
            return;
        }

        if (class_exists('DG_Marketing_Email_Templates')) {
            DG_Marketing_Email_Templates::send_mail(self::admin_email(), 'voice_lead_admin', [
                'full_name' => sanitize_text_field($data['name'] ?? 'Unknown'),
                'email' => sanitize_email($data['email'] ?? ''),
                'phone' => sanitize_text_field($data['phone'] ?? ''),
                'business_name' => sanitize_text_field($data['business_name'] ?? ''),
                'lead_score' => (string) $score,
                'qualified' => $qualified ? 'Yes' : 'No',
                'call_summary' => sanitize_textarea_field($data['ai_call_summary'] ?? ''),
            ]);
        }
    }

    public static function on_client_created($company_id, $data) {
        if (class_exists('DG_Permissions')) {
            DG_Permissions::log_audit('marketing_client_created', 'organisation', DG_Marketing_Clients::get_org_id($company_id), null, $data);
        }
    }
}

DG_Marketing_Admin_Notifications::init();
