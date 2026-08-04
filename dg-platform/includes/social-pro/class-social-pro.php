<?php
/**
 * Social Pro bootstrap.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Pro {

    public static function init() {
        require_once __DIR__ . '/class-social-pro-settings.php';

        if (!DG_Social_Pro_Settings::is_enabled()) {
            return;
        }

        require_once __DIR__ . '/class-social-pro-posts.php';
        require_once __DIR__ . '/class-social-pro-publisher.php';
        require_once __DIR__ . '/class-social-pro-oauth.php';
        require_once __DIR__ . '/platforms/class-social-platform.php';
        require_once __DIR__ . '/platforms/class-social-facebook.php';
        require_once __DIR__ . '/platforms/class-social-instagram.php';
        require_once __DIR__ . '/platforms/class-social-linkedin.php';
        require_once __DIR__ . '/platforms/class-social-x.php';
        require_once __DIR__ . '/platforms/class-social-pinterest.php';
        require_once __DIR__ . '/class-social-pro-admin.php';

        DG_Social_Pro_Posts::ensure_table();
        DG_Social_Pro_OAuth::init();

        add_action('dg_social_pro_cron', [DG_Social_Pro_Publisher::class, 'process_due']);
        add_action('init', [__CLASS__, 'schedule_cron']);

        if (is_admin()) {
            DG_Social_Pro_Admin::init();
        }
    }

    public static function schedule_cron() {
        if (!wp_next_scheduled('dg_social_pro_cron')) {
            wp_schedule_event(time() + 60, 'five_minutes', 'dg_social_pro_cron');
        }
    }
}

add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['five_minutes'])) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => 'Every 5 minutes',
        ];
    }
    return $schedules;
});

add_action('plugins_loaded', ['DG_Social_Pro', 'init'], 8);
