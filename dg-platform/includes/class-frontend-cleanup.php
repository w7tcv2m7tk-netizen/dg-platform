<?php
/**
 * Suppress third-party upgrade banners and reduce logged-in frontend FOUC.
 *
 * Oxygen/Breakdance injects a "Please upgrade to Pro" bar via wp_body_open for
 * logged-in editors when free-mode pro features are detected. Rank Math shows a
 * white "Advanced Stats are available in the PRO version" analytics bar to admins.
 * Themeisle (Super Page Cache) and others add admin upsells. This module hides them
 * on the public site.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Frontend_Cleanup {

    public static function init() {
        if (is_admin()) {
            return;
        }

        add_filter('breakdance_private_subscription_mode', [__CLASS__, 'force_pro_subscription_mode'], 1);
        add_filter('rank_math/analytics/frontend_stats', '__return_false');
        add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_rank_math_frontend_stats'], 999);
        add_action('wp_head', [__CLASS__, 'print_critical_styles'], 0);
        add_action('wp_footer', [__CLASS__, 'strip_upgrade_notices_script'], 999);
        add_filter('the_content', [__CLASS__, 'strip_trustindex_markup'], 8);
        add_filter('widget_text', [__CLASS__, 'strip_trustindex_markup'], 8);
        add_filter('widget_text_content', [__CLASS__, 'strip_trustindex_markup'], 8);
        add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_trustindex_assets'], 999);

        add_filter('wp_cloudflare_page_cache_dissallowed_promotions', [__CLASS__, 'disable_spc_promotions']);
    }

    /**
     * Treat the site as Pro on the frontend so Oxygen/Breakdance skip free-mode admin bars.
     *
     * @param string $mode "free"|"pro"
     * @return string
     */
    public static function force_pro_subscription_mode($mode) {
        if (is_admin()) {
            return $mode;
        }
        return 'pro';
    }

    /**
     * Prevent Rank Math from loading its frontend analytics bar (white PRO upsell strip).
     */
    public static function dequeue_rank_math_frontend_stats() {
        wp_dequeue_style('rank-math-analytics-stats');
        wp_dequeue_script('rank-math-analytics-stats');
    }

    /**
     * Hide upgrade UI immediately (before full CSS loads) and match page background.
     */
    public static function print_critical_styles() {
        $bg = self::page_background_color();
        ?>
        <style id="dg-frontend-cleanup">
            html { background-color: <?php echo esc_attr($bg); ?>; }
            .breakdance-upgrade-to-pro-frontend-notice,
            .breakdance-pro-only-element-notice,
            .breakdance-wp-admin-upgrade-to-pro-notice,
            #rank-math-analytics-stats-wrapper,
            #rank-math-analytics-stats,
            .rank-math-analytics-stats-footer,
            #themeisle-sdk-float-widget,
            .themeisle-sdk-float-widget,
            .ti-sdk-float-widget,
            .ti-widget,
            #ti-review-list,
            .trustindex-widget,
            [class*="trustindex"] { display: none !important; visibility: hidden !important; height: 0 !important; overflow: hidden !important; margin: 0 !important; padding: 0 !important; }
        </style>
        <?php
    }

    /**
     * Remove any upgrade nodes that were injected before styles parsed (logged-in uncached views).
     */
    public static function strip_upgrade_notices_script() {
        ?>
        <script id="dg-frontend-cleanup-js">
        (function () {
            var selectors = [
                '.breakdance-upgrade-to-pro-frontend-notice',
                '.breakdance-pro-only-element-notice',
                '#rank-math-analytics-stats-wrapper',
                '#themeisle-sdk-float-widget',
                '.themeisle-sdk-float-widget'
            ];
            selectors.forEach(function (sel) {
                document.querySelectorAll(sel).forEach(function (el) { el.remove(); });
            });
            document.querySelectorAll('.ti-widget, #ti-review-list, [class*="trustindex"]').forEach(function (el) { el.remove(); });
        })();
        </script>
        <?php
    }

    /** Remove legacy TrustIndex shortcodes/widgets — use [dg_airbnb_reviews] instead. */
    public static function strip_trustindex_markup($content) {
        if (!is_string($content) || stripos($content, 'trustindex') === false) {
            return $content;
        }

        $content = preg_replace('/\[trustindex[^\]]*\]/i', '', $content);
        $content = preg_replace('/\[\/trustindex\]/i', '', $content);
        $content = preg_replace('/<script[^>]*trustindex[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*ti-widget[^"]*"[^>]*>.*?<\/div>/is', '', $content);

        return $content;
    }

    /** Stop TrustIndex plugin CSS/JS when DG Platform reviews are used. */
    public static function dequeue_trustindex_assets() {
        global $wp_scripts, $wp_styles;
        if ($wp_scripts instanceof WP_Scripts) {
            foreach ($wp_scripts->registered as $handle => $script) {
                if (stripos($handle, 'trustindex') !== false || stripos((string) ($script->src ?? ''), 'trustindex') !== false) {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
            }
        }
        if ($wp_styles instanceof WP_Styles) {
            foreach ($wp_styles->registered as $handle => $style) {
                if (stripos($handle, 'trustindex') !== false || stripos((string) ($style->src ?? ''), 'trustindex') !== false) {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                }
            }
        }
    }

    private static function page_background_color() {
        if (class_exists('DG_Site_Profile')) {
            if (DG_Site_Profile::is_digitalgate()) {
                return '#0A0E17';
            }
            if (DG_Site_Profile::is_roe_realty()) {
                return '#F5F0EB';
            }
            if (DG_Site_Profile::is_currumbin_hideaway()) {
                return '#F5F2EF';
            }
            if (DG_Site_Profile::is_aetherra()) {
                return '#0D0D0D';
            }
        }
        return '#ffffff';
    }

    /** @param array<int,string> $list */
    public static function disable_spc_promotions($list) {
        return array_merge($list, ['upgrade', 'pro', 'spc', 'all']);
    }

    /** @param array<int,string> $list */
    public static function disable_all_themeisle_promotions($list) {
        return array_merge($list, ['all', 'upgrade', 'pro', 'spc', 'optimole', 'otter', 'neve', 'rop']);
    }
}

add_action('plugins_loaded', ['DG_Frontend_Cleanup', 'init'], 5);
