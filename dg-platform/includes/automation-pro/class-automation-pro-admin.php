<?php
/**
 * Automation Pro admin UI.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Automation_Pro_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 17);
        add_action('admin_post_dg_install_automation_template', [__CLASS__, 'handle_install_template']);
        add_action('admin_post_dg_save_automation_pro_workflow', [__CLASS__, 'handle_save_workflow']);
    }

    public static function register_menu() {
        if (!current_user_can('manage_options') || !DG_Automation_Pro_Settings::admin_visible()) {
            return;
        }

        add_submenu_page(
            'dg-platform',
            'Automation Pro',
            '⚡ Automation Pro',
            'manage_options',
            'dg-platform-automation-pro',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        $automations = DG_Automation::list();
        $templates = DG_Automation_Pro_Workflows::templates();
        $stats = DG_Automation_Pro_Workflows::log_stats();
        $queue = DG_Automation_Pro_Queue::pending_count();
        $triggers = DG_Automation_Pro_Workflows::available_triggers();
        $actions = DG_Automation_Pro_Workflows::available_actions();
        $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $edit = $edit_id ? DG_Automation::get($edit_id) : null;

        include DG_PLATFORM_PATH . 'templates/admin/automation-pro.php';
    }

    public static function handle_install_template() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_install_automation_template')) {
            wp_die('Unauthorized');
        }
        $key = sanitize_key($_POST['template'] ?? '');
        DG_Automation_Pro_Workflows::install_template($key);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-automation-pro&installed=1'));
        exit;
    }

    public static function handle_save_workflow() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_save_automation_pro_workflow')) {
            wp_die('Unauthorized');
        }

        $id = (int) ($_POST['automation_id'] ?? 0);
        $steps_raw = isset($_POST['steps']) ? wp_unslash($_POST['steps']) : '[]';
        $steps = json_decode($steps_raw, true);
        if (!is_array($steps)) {
            $steps = [];
        }

        $clean = [];
        foreach ($steps as $step) {
            if (empty($step['action'])) {
                continue;
            }
            $clean[] = array_map('sanitize_text_field', array_filter($step, 'is_scalar'));
        }

        if ($id) {
            DG_Automation_Pro_Workflows::update_steps($id, $clean);
        } else {
            $id = DG_Automation::create([
                'name' => sanitize_text_field($_POST['name'] ?? 'Workflow'),
                'module' => sanitize_text_field($_POST['module'] ?? 'core'),
                'trigger_type' => sanitize_text_field($_POST['trigger_type'] ?? ''),
                'steps' => $clean,
                'is_active' => !empty($_POST['is_active']),
            ]);
        }

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-automation-pro&edit=' . $id . '&saved=1'));
        exit;
    }
}
