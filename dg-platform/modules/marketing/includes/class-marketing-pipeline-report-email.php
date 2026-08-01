<?php
/**
 * Weekly pipeline summary email for DigitalGate admin.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Pipeline_Report_Email {

    const CRON_HOOK = 'dg_marketing_weekly_pipeline_report';

    public static function init() {
        add_action(self::CRON_HOOK, [__CLASS__, 'send']);
        add_action('init', [__CLASS__, 'maybe_schedule']);
    }

    public static function maybe_schedule() {
        if (!class_exists('DG_Site_Profile') || !DG_Site_Profile::is_digitalgate()) {
            return;
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(self::next_monday_8am(), 'weekly', self::CRON_HOOK);
        }
    }

    private static function next_monday_8am() {
        $tz = wp_timezone();
        $now = new DateTime('now', $tz);
        $target = new DateTime('monday 8:00', $tz);
        if ($target <= $now) {
            $target->modify('+1 week');
        }
        return $target->getTimestamp();
    }

    public static function send() {
        if (!class_exists('DG_Marketing_Pipeline_Reports') || !class_exists('DG_Marketing_Admin_Notifications')) {
            return;
        }

        $summary = DG_Marketing_Pipeline_Reports::client_conversion_summary();
        $activity = DG_Marketing_Pipeline_Reports::recent_activity_summary(7);
        $statuses = DG_Marketing_Pipeline_Reports::status_counts();
        $sources = DG_Marketing_Pipeline_Reports::source_counts();

        $status_lines = [];
        foreach ($statuses as $row) {
            $status_lines[] = $row['label'] . ': ' . $row['count'];
        }
        $source_lines = [];
        foreach ($sources as $row) {
            $source_lines[] = ucwords(str_replace('_', ' ', $row->source)) . ': ' . $row->total;
        }

        if (class_exists('DG_Marketing_Email_Templates')) {
            DG_Marketing_Email_Templates::send_mail(DG_Marketing_Admin_Notifications::admin_email(), 'weekly_pipeline_report', [
                'report_period' => 'Last 7 days',
                'period_days' => '7',
                'audits_month' => (string) DG_Marketing_Pipeline_Reports::audits_this_month(),
                'voice_month' => (string) DG_Marketing_Pipeline_Reports::voice_leads_this_month(),
                'conversion_rate' => (string) $summary['rate'],
                'new_clients' => (string) $activity['new_clients'],
                'audits_period' => (string) $activity['audits'],
                'voice_period' => (string) $activity['voice_leads'],
                'automation_sent' => (string) $activity['automation_sent'],
                'status_pipeline' => implode("\n", $status_lines),
                'lead_sources' => $source_lines ? implode("\n", $source_lines) : 'No clients yet',
                'admin_url' => admin_url('admin.php?page=dg-marketing-pipeline-reports'),
            ]);
        }
    }
}

DG_Marketing_Pipeline_Report_Email::init();
