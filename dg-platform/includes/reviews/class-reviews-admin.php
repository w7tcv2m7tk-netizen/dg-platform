<?php
/**
 * Reviews admin — import and manage customer reviews.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Reviews_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 14);
        add_action('admin_post_dg_save_review', [__CLASS__, 'handle_save']);
        add_action('admin_post_dg_delete_review', [__CLASS__, 'handle_delete']);
        add_action('admin_post_dg_import_reviews_csv', [__CLASS__, 'handle_csv_import']);
    }

    public static function register_menu() {
        if (!DG_Permissions::current_user_can('dg_view_contacts') && !current_user_can('manage_options')) {
            return;
        }
        add_submenu_page(
            'dg-platform',
            'Reviews',
            '⭐ Reviews',
            DG_Permissions::menu_cap(),
            'dg-platform-reviews',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options') && !DG_Permissions::current_user_can('dg_view_contacts')) {
            wp_die('Unauthorized');
        }
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'all';
        $reviews = DG_Reviews::get_reviews(['limit' => 100, 'status' => $tab === 'hidden' ? 'hidden' : 'published']);
        $counts = DG_Reviews::count_by_platform();
        $platforms = DG_Reviews::platforms();
        $trustindex_available = class_exists('DG_Reviews_Airbnb') && DG_Reviews_Airbnb::trustindex_available();
        include DG_PLATFORM_PATH . 'templates/admin/reviews.php';
    }

    public static function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('dg_save_review');
        DG_Reviews::insert([
            'platform' => sanitize_key($_POST['platform'] ?? 'manual'),
            'author_name' => sanitize_text_field($_POST['author_name'] ?? ''),
            'rating' => (float) ($_POST['rating'] ?? 5),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'review_date' => sanitize_text_field($_POST['review_date'] ?? ''),
            'source_url' => esc_url_raw($_POST['source_url'] ?? ''),
            'external_id' => sanitize_text_field($_POST['external_id'] ?? ''),
            'status' => sanitize_key($_POST['status'] ?? 'published'),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-reviews&saved=1'));
        exit;
    }

    public static function handle_delete() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('dg_delete_review');
        DG_Reviews::delete((int) ($_GET['id'] ?? 0));
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-reviews&deleted=1'));
        exit;
    }

    public static function handle_csv_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('dg_import_reviews_csv');
        if (empty($_FILES['csv_file']['tmp_name'])) {
            wp_die('No CSV file uploaded.');
        }
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            wp_die('Could not read CSV file.');
        }
        $header = fgetcsv($handle);
        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if (!$data) {
                continue;
            }
            DG_Reviews::insert([
                'platform' => sanitize_key($data['platform'] ?? 'manual'),
                'author_name' => sanitize_text_field($data['author_name'] ?? $data['author'] ?? ''),
                'rating' => (float) ($data['rating'] ?? 5),
                'title' => sanitize_text_field($data['title'] ?? ''),
                'content' => wp_kses_post($data['content'] ?? $data['review'] ?? ''),
                'review_date' => sanitize_text_field($data['review_date'] ?? $data['date'] ?? ''),
                'source_url' => esc_url_raw($data['source_url'] ?? $data['url'] ?? ''),
                'external_id' => sanitize_text_field($data['external_id'] ?? ''),
                'listing_id' => sanitize_text_field($data['listing_id'] ?? $data['airbnb_id'] ?? ''),
            ]);
            $imported++;
        }
        fclose($handle);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-reviews&imported=' . (int) $imported));
        exit;
    }
}
