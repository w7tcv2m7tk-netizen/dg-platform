<?php
/**
 * Automation Pro — multi-step workflows, delays, webhooks.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Automation_Pro {

    public static function init() {
        require_once __DIR__ . '/class-automation-pro-settings.php';

        if (!DG_Automation_Pro_Settings::is_enabled()) {
            return;
        }

        require_once __DIR__ . '/class-automation-pro-queue.php';
        require_once __DIR__ . '/class-automation-pro-runner.php';
        require_once __DIR__ . '/class-automation-pro-workflows.php';
        require_once __DIR__ . '/class-automation-pro-triggers.php';
        require_once __DIR__ . '/class-automation-pro-admin.php';

        DG_Automation_Pro_Queue::ensure_table();
        DG_Automation_Pro_Triggers::init();

        add_action('dg_process_automations', [DG_Automation_Pro_Queue::class, 'process_due']);
        add_action('dg_automation_cron', [DG_Automation_Pro_Queue::class, 'process_due']);

        if (is_admin()) {
            DG_Automation_Pro_Admin::init();
        }
    }
}

add_action('plugins_loaded', ['DG_Automation_Pro', 'init'], 8);
