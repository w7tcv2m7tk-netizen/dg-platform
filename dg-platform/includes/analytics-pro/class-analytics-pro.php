<?php
/**
 * Analytics Pro — growth intelligence, trends, exports.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Analytics_Pro {

    public static function init() {
        require_once __DIR__ . '/class-analytics-pro-settings.php';

        if (!DG_Analytics_Pro_Settings::is_enabled()) {
            return;
        }

        require_once __DIR__ . '/class-analytics-pro-snapshots.php';
        require_once __DIR__ . '/class-analytics-pro-collector.php';
        require_once __DIR__ . '/class-analytics-pro-cron.php';
        require_once __DIR__ . '/class-analytics-pro-admin.php';

        DG_Analytics_Pro_Snapshots::ensure_table();
        DG_Analytics_Pro_Cron::init();

        if (is_admin()) {
            DG_Analytics_Pro_Admin::init();
            add_action('admin_init', [__CLASS__, 'maybe_initial_snapshot']);
        }
    }

    public static function maybe_initial_snapshot() {
        if (get_option('dg_analytics_pro_initial_snapshot')) {
            return;
        }
        DG_Analytics_Pro_Snapshots::record_today(DG_Analytics_Pro_Collector::collect());
        update_option('dg_analytics_pro_initial_snapshot', 1);
    }
}

add_action('plugins_loaded', ['DG_Analytics_Pro', 'init'], 8);
