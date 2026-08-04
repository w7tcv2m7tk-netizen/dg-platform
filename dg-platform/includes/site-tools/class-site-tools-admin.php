<?php
/**
 * Site Tools admin — cache, images, SMTP, snippets, Cloudflare, Google.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Site_Tools_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 16);
        add_action('admin_bar_menu', [__CLASS__, 'add_toolbar_items'], 998);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_toolbar_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_toolbar_assets']);
        add_action('wp_ajax_dg_purge_site_cache_ajax', [__CLASS__, 'ajax_purge_cache']);
        add_action('admin_post_dg_save_site_tools', [__CLASS__, 'handle_save']);
        add_action('admin_post_dg_purge_site_cache', [__CLASS__, 'handle_purge_cache']);
        add_action('admin_post_dg_site_tools_test_smtp', [__CLASS__, 'handle_test_smtp']);
        add_action('admin_post_dg_site_tools_bulk_images', [__CLASS__, 'handle_bulk_images']);
        add_action('admin_post_dg_site_tools_refresh_pagespeed', [__CLASS__, 'handle_refresh_pagespeed']);
        add_action('admin_post_dg_site_tools_import_cloudflare', [__CLASS__, 'handle_import_cloudflare']);
        add_action('admin_post_dg_save_site_snippet', [__CLASS__, 'handle_save_snippet']);
        add_action('admin_post_dg_delete_site_snippet', [__CLASS__, 'handle_delete_snippet']);
    }

    public static function register_menu() {
        if (!current_user_can('manage_options') || !DG_Site_Tools_Settings::can_use()) {
            return;
        }

        add_submenu_page(
            'dg-platform',
            'Site Tools',
            '🛠 Site Tools',
            'manage_options',
            'dg-platform-site-tools',
            [__CLASS__, 'render_page']
        );
    }

    public static function add_toolbar_items($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (class_exists('DG_Site_Tools_Cache')) {
            $wp_admin_bar->add_node([
                'id' => 'dg-purge-cache',
                'title' => '🔄 Purge Cache',
                'href' => '#',
                'meta' => [
                    'class' => 'dg-toolbar-item dg-purge-cache-btn',
                    'title' => 'Purge Cloudflare, page cache, and object cache',
                ],
            ]);
        }

        if (self::can_show_site_tools()) {
            $wp_admin_bar->add_node([
                'id' => 'dg-site-tools',
                'title' => '🛠 Site Tools',
                'href' => admin_url('admin.php?page=dg-platform-site-tools'),
                'meta' => [
                    'class' => 'dg-toolbar-item',
                    'title' => 'Cache, images, email, health',
                ],
            ]);
        }

        $wp_admin_bar->add_node([
            'id' => 'dg-platform-quick',
            'title' => '🧩 DG Platform',
            'href' => admin_url('admin.php?page=dg-platform'),
            'meta' => [
                'class' => 'dg-toolbar-item',
                'title' => 'Open DG Platform apps',
            ],
        ]);

        if (class_exists('DG_Onboarding')) {
            try {
                $beta = DG_Onboarding::cached_summary(false);
                if ($beta && (int) ($beta['percent'] ?? 100) < 100) {
                    $wp_admin_bar->add_node([
                        'id' => 'dg-beta-setup',
                        'title' => '🚀 Beta Setup (' . (int) $beta['percent'] . '%)',
                        'href' => admin_url('admin.php?page=dg-platform-onboarding'),
                        'meta' => [
                            'class' => 'dg-toolbar-item',
                            'title' => 'Complete beta setup checklist',
                        ],
                    ]);
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    private static function can_show_site_tools() {
        return class_exists('DG_Site_Tools_Settings') && DG_Site_Tools_Settings::can_use();
    }

    public static function enqueue_toolbar_assets() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }
        if (!class_exists('DG_Site_Tools_Cache')) {
            return;
        }

        wp_enqueue_script(
            'dg-admin-toolbar',
            DG_PLATFORM_URL . 'assets/js/admin-toolbar.js',
            [],
            DG_PLATFORM_VERSION,
            true
        );
        wp_localize_script('dg-admin-toolbar', 'dgAdminToolbar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'purgeNonce' => wp_create_nonce('dg_purge_site_cache'),
        ]);
    }

    public static function ajax_purge_cache() {
        check_ajax_referer('dg_purge_site_cache', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }

        $result = DG_Site_Tools_Cache::purge_all();
        if (!empty($result['success'])) {
            wp_send_json_success([
                'message' => $result['message'] ?? 'Cache purged.',
                'methods' => $result['methods'] ?? [],
            ]);
        }
        wp_send_json_error([
            'message' => $result['message'] ?? 'Cache purge failed.',
        ]);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        $settings = DG_Site_Tools_Settings::all();
        $cache_status = DG_Site_Tools_Cache::status();
        $cf_zone = DG_Site_Tools_Cloudflare::zone_status();
        $cf_credentials = DG_Site_Tools_Cloudflare::credentials();
        $cf_legacy = DG_Site_Tools_Cloudflare::legacy_credentials();
        $cf_analytics = DG_Site_Tools_Cloudflare::is_configured() ? DG_Site_Tools_Cloudflare::analytics_summary() : ['success' => false];
        $google = DG_Site_Tools_Google::dashboard_summary();
        $snippets = DG_Site_Tools_Snippets::all();
        $snippet_hooks = DG_Site_Tools_Snippets::allowed_hooks();
        $unoptimized = DG_Site_Tools_Images::count_unoptimized();
        $legacy = DG_Site_Tools_Admin::legacy_plugin_status();
        $health = ($tab === 'health' && class_exists('DG_Site_Tools_Health')) ? DG_Site_Tools_Health::run() : null;

        include DG_PLATFORM_PATH . 'templates/admin/site-tools.php';
    }

    /** @return array<int,array<string,string>> */
    public static function legacy_plugin_status() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $map = [
            'wp-smushit/wp-smush.php' => ['label' => 'Smush', 'replacement' => 'Site Tools → Images'],
            'fluent-smtp/fluent-smtp.php' => ['label' => 'Fluent SMTP', 'replacement' => 'Site Tools → Email'],
            'fluent-snippets/fluent-snippets.php' => ['label' => 'Fluent Snippets', 'replacement' => 'Site Tools → Snippets (or DG modules)'],
            'google-site-kit/google-site-kit.php' => ['label' => 'Google Site Kit', 'replacement' => 'Site Tools → Analytics + SEO + Analytics Pro'],
            'wp-cloudflare-page-cache/wp-cloudflare-page-cache.php' => ['label' => 'Super Page Cache', 'replacement' => 'Site Tools → Cache (Cloudflare API purge)'],
        ];

        $out = [];
        foreach ($map as $plugin => $info) {
            if (is_plugin_active($plugin)) {
                $out[] = array_merge($info, ['plugin' => $plugin]);
            }
        }
        return $out;
    }

    public static function handle_save() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_site_tools_settings')) {
            wp_die('Unauthorized');
        }

        $tab = sanitize_key($_POST['tab'] ?? 'overview');
        DG_Site_Tools_Settings::save($_POST);

        $query = ['saved' => '1'];
        if ($tab === 'cache') {
            $zone = DG_Site_Tools_Cloudflare::zone_status();
            if (!empty($zone['zone_name'])) {
                $query['cf_ok'] = '1';
            } elseif (DG_Site_Tools_Cloudflare::is_configured()) {
                $query['cf_error'] = rawurlencode($zone['message'] ?? 'Cloudflare connection failed — check token permissions.');
            } elseif (DG_Site_Tools_Cloudflare::legacy_credentials()['source'] !== 'none') {
                $query['cf_import'] = '1';
            }
        }

        wp_safe_redirect(add_query_arg($query, admin_url('admin.php?page=dg-platform-site-tools&tab=' . $tab)));
        exit;
    }

    public static function handle_import_cloudflare() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_site_tools_import_cloudflare')) {
            wp_die('Unauthorized');
        }

        $result = DG_Site_Tools_Cloudflare::import_legacy_credentials();
        $query = !empty($result['success']) ? ['cf_imported' => '1'] : ['cf_error' => rawurlencode($result['message'] ?? 'Import failed.')];
        wp_safe_redirect(add_query_arg($query, admin_url('admin.php?page=dg-platform-site-tools&tab=cache')));
        exit;
    }

    public static function handle_purge_cache() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_purge_site_cache')) {
            wp_die('Unauthorized');
        }

        $result = DG_Site_Tools_Cache::purge_all();
        $flag = $result['success'] ? 'purged=1' : 'purge_error=1';
        $redirect = wp_get_referer();
        if ($redirect && strpos($redirect, 'admin.php') !== false) {
            $redirect = add_query_arg($flag, '1', remove_query_arg(['purged', 'purge_error'], $redirect));
        } else {
            $redirect = admin_url('admin.php?page=dg-platform-site-tools&tab=cache&' . $flag);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_test_smtp() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_site_tools_test_smtp')) {
            wp_die('Unauthorized');
        }

        $result = DG_Site_Tools_Mail::send_test();
        $flag = $result['success'] ? 'smtp_ok=1' : 'smtp_error=1';
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-site-tools&tab=email&' . $flag));
        exit;
    }

    public static function handle_bulk_images() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_site_tools_bulk_images')) {
            wp_die('Unauthorized');
        }

        $result = DG_Site_Tools_Images::bulk_optimize(25);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-site-tools&tab=images&bulk=' . (int) $result['processed'] . '&saved_bytes=' . (int) $result['saved_bytes'] . '&remaining=' . (int) $result['remaining']));
        exit;
    }

    public static function handle_refresh_pagespeed() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_site_tools_refresh_pagespeed')) {
            wp_die('Unauthorized');
        }

        DG_Site_Tools_Google::refresh_pagespeed();
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-site-tools&tab=analytics&pagespeed=1'));
        exit;
    }

    public static function handle_save_snippet() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_save_site_snippet')) {
            wp_die('Unauthorized');
        }

        DG_Site_Tools_Snippets::upsert([
            'id' => sanitize_key($_POST['snippet_id'] ?? ''),
            'name' => sanitize_text_field($_POST['snippet_name'] ?? ''),
            'hook' => sanitize_key($_POST['snippet_hook'] ?? 'init'),
            'priority' => (int) ($_POST['snippet_priority'] ?? 10),
            'code' => wp_unslash($_POST['snippet_code'] ?? ''),
            'active' => !empty($_POST['snippet_active']),
        ]);

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-site-tools&tab=snippets&saved=1'));
        exit;
    }

    public static function handle_delete_snippet() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('dg_delete_site_snippet');

        $snippet_id = sanitize_key(wp_unslash($_POST['snippet_id'] ?? ''));
        if ($snippet_id === '') {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-site-tools&tab=snippets&delete_error=1'));
            exit;
        }

        $deleted = DG_Site_Tools_Snippets::delete($snippet_id);
        $query = $deleted ? 'deleted=1' : 'delete_error=1';
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-site-tools&tab=snippets&' . $query));
        exit;
    }
}
