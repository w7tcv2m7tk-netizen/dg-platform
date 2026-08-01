<?php
/**
 * Warn when legacy Roe plugins duplicate DG Platform functionality.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Legacy_Notices {

    public static function init() {
        add_action('admin_notices', [__CLASS__, 'maybe_show_notices']);
    }

    public static function maybe_show_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $conflicts = [];
        if (self::is_plugin_active('roe-realty-automation.php')) {
            $conflicts[] = 'Roe Realty Follow-up Automation — email follow-ups are now built into DG Platform (v10.0.5+). Deactivate to avoid duplicate emails.';
        }
        if (self::is_plugin_active('fluent-snippets/fluent-snippets.php')) {
            $conflicts[] = 'Fluent Snippets — property/agent snippets are replaced by DG Platform Real Estate module. Disable conflicting snippets or deactivate the plugin.';
        }

        foreach ($conflicts as $message) {
            echo '<div class="notice notice-warning"><p><strong>DG Platform:</strong> ' . esc_html($message) . '</p></div>';
        }
    }

    private static function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }
}

DG_Legacy_Notices::init();
