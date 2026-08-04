<?php
/**
 * AI Visibility Pro — scoring, monitoring, and llms.txt for all sites.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Visibility {

    public static function init() {
        require_once __DIR__ . '/class-ai-visibility-history.php';
        require_once __DIR__ . '/class-ai-visibility-recommendations.php';
        require_once __DIR__ . '/class-ai-visibility-scanner.php';
        require_once __DIR__ . '/class-ai-visibility-llms.php';
        require_once __DIR__ . '/class-ai-visibility-cron.php';
        require_once __DIR__ . '/class-ai-visibility-admin.php';

        if (!DG_AI_Visibility_Settings::is_enabled()) {
            return;
        }

        DG_AI_Visibility_History::ensure_table();
        DG_AI_Visibility_Llms::init();
        DG_AI_Visibility_Cron::init();

        if (is_admin()) {
            DG_AI_Visibility_Admin::init();
        }

        add_action('init', [__CLASS__, 'maybe_flush_rewrites'], 100);
    }

    public static function maybe_flush_rewrites() {
        if (get_option('dg_ai_visibility_needs_rewrite_flush')) {
            DG_AI_Visibility_Llms::register_rewrite();
            flush_rewrite_rules(false);
            delete_option('dg_ai_visibility_needs_rewrite_flush');
        }
    }
}

add_action('plugins_loaded', function () {
    require_once __DIR__ . '/class-ai-visibility-settings.php';
    DG_AI_Visibility::init();
}, 8);
