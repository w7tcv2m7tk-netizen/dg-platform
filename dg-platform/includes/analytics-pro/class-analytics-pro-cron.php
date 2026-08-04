<?php
/**
 * Analytics Pro scheduled snapshots and weekly email.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Analytics_Pro_Cron {

    const DAILY_HOOK = 'dg_analytics_pro_daily_snapshot';
    const WEEKLY_HOOK = 'dg_analytics_pro_weekly_report';

    public static function init() {
        add_action(self::DAILY_HOOK, [__CLASS__, 'run_daily']);
        add_action(self::WEEKLY_HOOK, [__CLASS__, 'run_weekly_email']);
        add_action('init', [__CLASS__, 'maybe_schedule']);
    }

    public static function maybe_schedule() {
        if (!DG_Analytics_Pro_Settings::is_enabled()) {
            return;
        }
        $settings = DG_Analytics_Pro_Settings::all();
        if (!empty($settings['daily_snapshots']) && !wp_next_scheduled(self::DAILY_HOOK)) {
            wp_schedule_event(strtotime('tomorrow 6:00'), 'daily', self::DAILY_HOOK);
        }
        if (!empty($settings['weekly_email']) && !wp_next_scheduled(self::WEEKLY_HOOK)) {
            wp_schedule_event(strtotime('next Monday 7:00'), 'weekly', self::WEEKLY_HOOK);
        }
    }

    public static function run_daily() {
        if (!DG_Analytics_Pro_Settings::is_enabled()) {
            return;
        }
        DG_Analytics_Pro_Snapshots::record_today(DG_Analytics_Pro_Collector::collect());
    }

    public static function run_weekly_email() {
        $settings = DG_Analytics_Pro_Settings::all();
        if (empty($settings['weekly_email'])) {
            return;
        }
        $to = $settings['email_recipient'] ?: get_option('admin_email');
        $trends = DG_Analytics_Pro_Snapshots::trends(7);
        $lines = ["Weekly Growth Intelligence — " . (class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : get_bloginfo('name')), ''];
        foreach ($trends as $key => $row) {
            $sign = $row['delta'] >= 0 ? '+' : '';
            $lines[] = sprintf('%s: %s (%s%s vs 7d ago)', $row['label'], $row['current'], $sign, round($row['delta'], 1));
        }
        $lines[] = '';
        $lines[] = 'View full dashboard: ' . admin_url('admin.php?page=dg-platform-analytics-pro');
        wp_mail($to, 'Weekly Analytics Pro Report', implode("\n", $lines));
    }
}
