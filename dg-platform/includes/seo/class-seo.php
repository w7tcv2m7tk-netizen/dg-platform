<?php
/**
 * DG Platform SEO — replaces Rank Math for on-site SEO.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_SEO {

    public static function init() {
        require_once __DIR__ . '/class-seo-settings.php';
        require_once __DIR__ . '/class-seo-redirects.php';
        require_once __DIR__ . '/class-seo-meta.php';
        require_once __DIR__ . '/class-seo-sitemap.php';
        require_once __DIR__ . '/class-seo-schema.php';
        require_once __DIR__ . '/class-seo-analyzer.php';
        require_once __DIR__ . '/class-seo-ai-optimizer.php';
        require_once __DIR__ . '/class-seo-indexnow.php';
        require_once __DIR__ . '/class-seo-admin.php';

        DG_SEO_Settings::init();
        DG_SEO_IndexNow::init();
        DG_SEO_Meta::init();
        DG_SEO_Sitemap::init();
        DG_SEO_Schema::init();
        DG_SEO_Redirects::init();

        if (is_admin()) {
            DG_SEO_Admin::init();
        }

        self::disable_rank_math_frontend();
        add_action('init', [__CLASS__, 'flush_on_activation'], 100);
    }

    public static function flush_on_activation() {
        if (get_option('dg_seo_needs_rewrite_flush')) {
            DG_SEO_Sitemap::register_rewrites();
            flush_rewrite_rules(false);
            delete_option('dg_seo_needs_rewrite_flush');
        }
    }

    private static function disable_rank_math_frontend() {
        add_filter('rank_math/frontend/disable_integration', '__return_true');
        add_filter('rank_math/analytics/frontend_stats', '__return_false');

        add_filter('rank_math/modules', function ($modules) {
            if (!is_array($modules)) {
                return $modules;
            }
            unset($modules['sitemap'], $modules['redirections']);
            return $modules;
        }, 99);
    }
}

add_action('plugins_loaded', ['DG_SEO', 'init'], 8);
