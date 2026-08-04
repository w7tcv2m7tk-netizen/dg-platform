<?php
/**
 * Social Pro admin UI.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Pro_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 16);
        add_action('admin_post_dg_save_social_pro_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_dg_save_social_pro_post', [__CLASS__, 'handle_save_post']);
        add_action('admin_post_dg_disconnect_social_platform', [__CLASS__, 'handle_disconnect']);
        add_action('admin_post_dg_publish_social_post', [__CLASS__, 'handle_publish_now']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_menu() {
        if (!current_user_can('manage_options') || !DG_Social_Pro_Settings::admin_visible()) {
            return;
        }

        add_submenu_page(
            'dg-platform',
            'Social Pro',
            '📱 Social Pro',
            'manage_options',
            'dg-platform-social-pro',
            [__CLASS__, 'render_page']
        );
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'dg-platform_page_dg-platform-social-pro') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'dg-social-pro-admin',
            DG_PLATFORM_URL . 'assets/css/social-pro-admin.css',
            ['dg-platform-admin'],
            DG_PLATFORM_VERSION
        );
        wp_enqueue_script(
            'dg-social-pro-compose',
            DG_PLATFORM_URL . 'assets/js/social-pro-compose.js',
            ['jquery'],
            DG_PLATFORM_VERSION,
            true
        );

        $platforms = DG_Social_Pro_Settings::platform_definitions();
        $limits = [];
        foreach ($platforms as $key => $def) {
            $limits[$key] = (int) ($def['max_chars'] ?? 1000);
        }

        wp_localize_script('dg-social-pro-compose', 'dgSocialPro', [
            'limits' => $limits,
            'connected' => array_keys(array_filter(
                DG_Social_Pro_Settings::connections(),
                function ($c) {
                    return !empty($c['access_token']);
                }
            )),
        ]);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'compose';
        $settings = DG_Social_Pro_Settings::all();
        $platforms = DG_Social_Pro_Settings::platform_definitions();
        $connections = DG_Social_Pro_Settings::connections();
        $posts = DG_Social_Pro_Posts::list(['limit' => 30]);
        $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $edit_post = $edit_id ? DG_Social_Pro_Posts::get($edit_id) : null;

        include DG_PLATFORM_PATH . 'templates/admin/social-pro.php';
    }

    public static function handle_save_settings() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_social_pro_settings')) {
            wp_die('Unauthorized');
        }

        // Manual token override fields from connections form.
        if (!empty($_POST['manual_platform']) && !empty($_POST['manual_access_token'])) {
            $platform = sanitize_key($_POST['manual_platform']);
            $data = [
                'access_token' => sanitize_text_field(wp_unslash($_POST['manual_access_token'])),
                'account_name' => sanitize_text_field(wp_unslash($_POST['manual_account_name'] ?? '')),
            ];
            $optional = ['page_id', 'page_access_token', 'instagram_account_id', 'author_urn', 'board_id', 'board_name'];
            foreach ($optional as $key) {
                if (!empty($_POST['manual_' . $key])) {
                    $data[$key] = sanitize_text_field(wp_unslash($_POST['manual_' . $key]));
                }
            }
            DG_Social_Pro_Settings::save_connection($platform, $data);
        }

        DG_Social_Pro_Settings::save($_POST);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-social-pro&tab=connections&saved=1'));
        exit;
    }

    public static function handle_save_post() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_social_pro_post')) {
            wp_die('Unauthorized');
        }

        $platforms = isset($_POST['platforms']) && is_array($_POST['platforms'])
            ? array_map('sanitize_key', $_POST['platforms'])
            : [];

        $data = [
            'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'content' => wp_kses_post(wp_unslash($_POST['content'] ?? '')),
            'media_url' => esc_url_raw(wp_unslash($_POST['media_url'] ?? '')),
            'link_url' => esc_url_raw(wp_unslash($_POST['link_url'] ?? '')),
            'platforms' => $platforms,
            'scheduled_at' => sanitize_text_field(wp_unslash($_POST['scheduled_at'] ?? '')),
        ];

        $action = sanitize_key($_POST['post_action'] ?? 'draft');
        $edit_id = (int) ($_POST['post_id'] ?? 0);

        if ($action === 'schedule' && $data['scheduled_at'] !== '') {
            $data['status'] = 'scheduled';
        } elseif ($action === 'schedule') {
            $data['status'] = 'draft';
        } elseif ($action === 'publish') {
            $data['status'] = 'publishing';
        } else {
            $data['status'] = 'draft';
        }

        if ($edit_id) {
            DG_Social_Pro_Posts::update($edit_id, $data);
            $post_id = $edit_id;
        } else {
            $post_id = DG_Social_Pro_Posts::create($data);
        }

        if ($action === 'publish') {
            DG_Social_Pro_Publisher::publish_post($post_id);
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-social-pro&tab=history&published=' . $post_id));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-social-pro&tab=compose&edit=' . $post_id . '&saved=1'));
        exit;
    }

    public static function handle_publish_now() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_publish_social_post')) {
            wp_die('Unauthorized');
        }

        $post_id = (int) ($_POST['post_id'] ?? 0);
        if ($post_id) {
            DG_Social_Pro_Publisher::publish_post($post_id);
        }

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-social-pro&tab=history&published=' . $post_id));
        exit;
    }

    public static function handle_disconnect() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_disconnect_social_platform')) {
            wp_die('Unauthorized');
        }

        $platform = sanitize_key($_POST['platform'] ?? '');
        if ($platform) {
            DG_Social_Pro_Settings::disconnect($platform);
        }

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-social-pro&tab=connections&disconnected=1'));
        exit;
    }
}
