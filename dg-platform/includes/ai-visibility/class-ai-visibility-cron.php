<?php
/**
 * Scheduled AI Visibility scans.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Visibility_Cron {

    const HOOK = 'dg_ai_visibility_scheduled_scan';

    public static function init() {
        add_action(self::HOOK, [__CLASS__, 'run']);
        add_action('init', [__CLASS__, 'maybe_schedule']);
        add_action('update_option_' . DG_AI_Visibility_Settings::OPTION, [__CLASS__, 'reschedule'], 10, 0);
    }

    public static function maybe_schedule() {
        if (!DG_AI_Visibility_Settings::is_enabled()) {
            self::unschedule();
            return;
        }

        $schedule = DG_AI_Visibility_Settings::get('schedule', 'weekly');
        if ($schedule === 'off') {
            self::unschedule();
            return;
        }

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, $schedule === 'monthly' ? 'monthly' : 'weekly', self::HOOK);
        }
    }

    public static function reschedule() {
        self::unschedule();
        self::maybe_schedule();
    }

    public static function unschedule() {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
    }

    public static function run() {
        if (!DG_AI_Visibility_Settings::is_enabled()) {
            return;
        }
        DG_AI_Visibility_Scanner::run('scheduled');
    }
}

add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => 'Once Monthly',
        ];
    }
    return $schedules;
});
