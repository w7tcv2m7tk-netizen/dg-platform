<?php
/**
 * Analytics Pro admin dashboard.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Analytics_Pro_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 18);
        add_action('admin_post_dg_save_analytics_pro_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_dg_analytics_pro_export', [__CLASS__, 'handle_export']);
        add_action('admin_post_dg_analytics_pro_snapshot', [__CLASS__, 'handle_snapshot_now']);
    }

    public static function register_menu() {
        if (!current_user_can('manage_options') || !DG_Analytics_Pro_Settings::admin_visible()) {
            return;
        }

        add_submenu_page(
            'dg-platform',
            'Analytics Pro',
            '📊 Analytics Pro',
            'manage_options',
            'dg-platform-analytics-pro',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        $settings = DG_Analytics_Pro_Settings::all();
        $metrics = DG_Analytics_Pro_Collector::collect();
        $trends = DG_Analytics_Pro_Snapshots::trends(30);

        include DG_PLATFORM_PATH . 'templates/admin/analytics-pro.php';
    }

    public static function handle_save_settings() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_analytics_pro_settings')) {
            wp_die('Unauthorized');
        }
        DG_Analytics_Pro_Settings::save($_POST);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-analytics-pro&tab=settings&saved=1'));
        exit;
    }

    public static function handle_snapshot_now() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_analytics_pro_snapshot')) {
            wp_die('Unauthorized');
        }
        DG_Analytics_Pro_Snapshots::record_today(DG_Analytics_Pro_Collector::collect());
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-analytics-pro&snapshot=1'));
        exit;
    }

    public static function handle_export() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_analytics_pro_export')) {
            wp_die('Unauthorized');
        }
        $module = isset($_GET['module']) ? sanitize_text_field($_GET['module']) : null;
        $rows = DG_Analytics_Pro_Collector::export_rows($module ?: null);
        DG_Reports::export_csv($rows, 'analytics-pro-' . current_time('Y-m-d') . '.csv');
    }
}
