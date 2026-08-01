<?php
/**
 * Weekly pipeline summary email for Roe Realty admin.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Pipeline_Report_Email {

    const CRON_HOOK = 'dg_re_weekly_pipeline_report';

    public static function init() {
        add_action(self::CRON_HOOK, [__CLASS__, 'send']);
        add_action('init', [__CLASS__, 'maybe_schedule']);
    }

    public static function maybe_schedule() {
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
        if (!class_exists('DG_RE_Pipeline_Reports') || !class_exists('DG_RE_Admin_Notifications')) {
            return;
        }

        $summary = DG_RE_Pipeline_Reports::vendor_conversion_summary();
        $activity = DG_RE_Pipeline_Reports::recent_activity_summary(7);
        $vendor_stages = DG_RE_Pipeline_Reports::vendor_stage_counts();
        $buyer_stages = DG_RE_Pipeline_Reports::buyer_stage_counts();
        $sources = DG_RE_Pipeline_Reports::vendor_source_counts();

        $vendor_lines = [];
        foreach ($vendor_stages as $row) {
            $vendor_lines[] = $row['label'] . ': ' . $row['count'];
        }

        $buyer_lines = [];
        foreach ($buyer_stages as $row) {
            $buyer_lines[] = $row['label'] . ': ' . $row['count'];
        }

        $source_lines = [];
        foreach ($sources as $row) {
            $source_lines[] = ucwords(str_replace('_', ' ', $row->source)) . ': ' . $row->total;
        }

        $vars = [
            'report_period' => 'Last 7 days',
            'property_reports' => (string) DG_RE_Pipeline_Reports::property_reports_this_month(),
            'bookings_month' => (string) DG_RE_Pipeline_Reports::bookings_this_month(),
            'vendor_leads_week' => (string) $activity['vendor_leads'],
            'buyer_leads_week' => (string) $activity['buyer_leads'],
            'bookings_week' => (string) $activity['bookings'],
            'conversion_rate' => (string) $summary['rate'],
            'vendor_pipeline' => implode("\n", $vendor_lines),
            'buyer_pipeline' => implode("\n", $buyer_lines),
            'lead_sources' => $source_lines ? implode("\n", $source_lines) : 'No vendor leads yet',
            'admin_url' => admin_url('admin.php?page=dg-re-pipeline-reports'),
        ];

        DG_RE_Admin_Notifications::send_template('weekly_pipeline_report', $vars);
    }
}

DG_RE_Pipeline_Report_Email::init();
