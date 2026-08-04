<?php
/**
 * AI Visibility Pro admin dashboard.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Visibility_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 16);
        add_action('admin_post_dg_save_ai_visibility_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_dg_run_ai_visibility_scan', [__CLASS__, 'handle_run_scan']);
    }

    public static function register_menu() {
        if (!current_user_can('manage_options') || !DG_AI_Visibility_Settings::admin_visible()) {
            return;
        }

        add_submenu_page(
            'dg-platform',
            'AI Visibility Pro',
            '🤖 AI Visibility Pro',
            'manage_options',
            'dg-platform-ai-visibility',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        $settings = DG_AI_Visibility_Settings::all();
        $latest = DG_AI_Visibility_History::latest();
        $averages = DG_AI_Visibility_History::averages();
        $history = DG_AI_Visibility_History::recent(15);
        $integrations = DG_Integrations::get_integration_status();
        $recommendations = [];
        if ($latest && $latest->recommendations) {
            $decoded = json_decode($latest->recommendations, true);
            $recommendations = is_array($decoded) ? $decoded : [];
        }

        include DG_PLATFORM_PATH . 'templates/admin/ai-visibility.php';
    }

    public static function handle_save_settings() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_ai_visibility_settings')) {
            wp_die('Unauthorized');
        }

        DG_AI_Visibility_Settings::save($_POST);
        update_option('dg_ai_visibility_needs_rewrite_flush', 1);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-ai-visibility&tab=settings&saved=1'));
        exit;
    }

    public static function handle_run_scan() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_run_ai_visibility_scan')) {
            wp_die('Unauthorized');
        }

        $result = DG_AI_Visibility_Scanner::run('manual');
        if (is_wp_error($result)) {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-ai-visibility&error=' . rawurlencode($result->get_error_message())));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-ai-visibility&scanned=1'));
        exit;
    }
}
