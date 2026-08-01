<?php
/**
 * Property report 5-email follow-up sequence (migrated from roe-realty-automation).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Property_Report_Followups {

    const CRON_HOOK = 'dg_re_property_report_followups';
    const AUTOMATION_OPTION = 'dg_re_property_report_automation_id';

    public static function init() {
        add_action(self::CRON_HOOK, [__CLASS__, 'process']);
        add_action('dg_re_vendor_lead_created', [__CLASS__, 'ensure_automation_record'], 10, 4);
        add_action('init', [__CLASS__, 'maybe_schedule']);
        add_action('init', [__CLASS__, 'maybe_seed_automation'], 20);
    }

    public static function maybe_schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(strtotime('tomorrow 9:00'), 'daily', self::CRON_HOOK);
        }
    }

    public static function maybe_seed_automation() {
        if (get_option(self::AUTOMATION_OPTION)) {
            return;
        }
        if (!class_exists('DG_Automation')) {
            return;
        }

        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . DG_Automation::table() . ' WHERE trigger_type = %s LIMIT 1',
            'property_report_followup_sequence'
        ));
        if ($existing) {
            update_option(self::AUTOMATION_OPTION, (int) $existing);
            return;
        }

        $id = DG_Automation::create([
            'name' => 'Property Report 5-Email Follow-up',
            'module' => 'real-estate',
            'trigger_type' => 'property_report_followup_sequence',
            'trigger_settings' => [
                'description' => 'Daily cron sends emails 2–5 after property report submission.',
                'schedule' => 'daily',
                'delays_days' => [1, 3, 5, 9],
            ],
            'steps' => [
                ['action' => 'send_email', 'delay_days' => 1, 'label' => 'Email 2 — Report insights'],
                ['action' => 'send_email', 'delay_days' => 3, 'label' => 'Email 3 — Market changes'],
                ['action' => 'send_email', 'delay_days' => 5, 'label' => 'Email 4 — Timing matters'],
                ['action' => 'send_email', 'delay_days' => 9, 'label' => 'Email 5 — Keep file open?'],
            ],
            'is_active' => 1,
        ]);
        update_option(self::AUTOMATION_OPTION, (int) $id);
    }

    public static function ensure_automation_record($lead_id, $contact_id, $pipeline_id, $data) {
        self::maybe_seed_automation();
    }

    public static function send_email($to, $subject, $body, $template_key = '') {
        $html = $template_key
            ? DG_RE_Email_Templates::format_body_html($body, $template_key)
            : DG_RE_Email_Templates::format_body_html($body, 'property_report_lead');
        return wp_mail($to, $subject, $html, DG_RE_Email_Templates::mail_headers(true));
    }

    public static function process() {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_realty_leads';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }

        self::maybe_seed_automation();
        self::send_batch(2, '-1 days', 'email_2_sent');
        self::send_batch(3, '-3 days', 'email_3_sent');
        self::send_batch(4, '-5 days', 'email_4_sent');
        self::send_batch(5, '-9 days', 'email_5_sent');
    }

    private static function send_batch($step, $offset, $flag) {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_realty_leads';
        $cutoff = date('Y-m-d H:i:s', strtotime($offset));

        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $flag = 0 AND submitted_at <= %s AND email IS NOT NULL AND email != ''",
            $cutoff
        ));

        foreach ($leads as $lead) {
            $mail = DG_RE_Email_Templates::render('followup_' . $step, [
                'first_name' => $lead->first_name,
                'full_name' => $lead->full_name,
                'property_address' => $lead->property_address,
                'email' => $lead->email,
            ]);
            [$subject, $body] = [$mail['subject'], $mail['body']];
            if (self::send_email($lead->email, $subject, $body, 'followup_' . $step)) {
                $wpdb->update($table, [$flag => 1], ['id' => $lead->id]);
                if (class_exists('DG_Activities')) {
                    DG_Activities::log([
                        'activity_type' => 'email',
                        'subject' => $subject,
                        'content' => "Property report follow-up email $step sent to {$lead->email}",
                        'metadata' => [
                            'lead_id' => $lead->id,
                            'step' => $step,
                            'property_address' => $lead->property_address,
                        ],
                    ]);
                }
            }
        }
    }
}

DG_RE_Property_Report_Followups::init();
