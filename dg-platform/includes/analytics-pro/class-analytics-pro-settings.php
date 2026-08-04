<?php
/**
 * Analytics Pro settings.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Analytics_Pro_Settings {

    const OPTION = 'dg_analytics_pro_settings';

    public static function is_enabled() {
        if (!class_exists('DG_Plan_Registry')) {
            return true;
        }
        return DG_Plan_Registry::has_premium_app('analytics_pro');
    }

    public static function admin_visible() {
        return self::is_enabled();
    }

    public static function defaults() {
        return [
            'daily_snapshots' => 1,
            'weekly_email' => 0,
            'email_recipient' => get_option('admin_email'),
        ];
    }

    /** @return array<string,mixed> */
    public static function all() {
        $saved = get_option(self::OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    /** @param array<string,mixed> $data */
    public static function save(array $data) {
        update_option(self::OPTION, [
            'daily_snapshots' => !empty($data['daily_snapshots']) ? 1 : 0,
            'weekly_email' => !empty($data['weekly_email']) ? 1 : 0,
            'email_recipient' => sanitize_email($data['email_recipient'] ?? get_option('admin_email')),
        ]);
    }
}
