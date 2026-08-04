<?php
/**
 * Automation Pro settings and feature gate.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Automation_Pro_Settings {

    const OPTION = 'dg_automation_pro_settings';

    public static function is_enabled() {
        if (!class_exists('DG_Plan_Registry')) {
            return true;
        }
        return DG_Plan_Registry::has_premium_app('automation_pro');
    }

    public static function admin_visible() {
        return self::is_enabled();
    }

    public static function defaults() {
        return [
            'max_delay_days' => 30,
            'webhooks_enabled' => 1,
            'log_retention_days' => 90,
        ];
    }

    /** @return array<string,mixed> */
    public static function all() {
        $saved = get_option(self::OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }
}
