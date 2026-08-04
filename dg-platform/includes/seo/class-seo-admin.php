<?php

/**

 * SEO admin: global settings, post meta box, redirects.

 *

 * @package DG_Platform

 */



if (!defined('ABSPATH')) {

    exit;

}



class DG_SEO_Admin {



    public static function admin_visible() {

        return class_exists('DG_Plan_Registry') && DG_Plan_Registry::has_premium_app('seo_pro');

    }



    public static function init() {

        add_action('admin_menu', [__CLASS__, 'register_menu'], 15);

        add_action('admin_post_dg_save_seo_settings', [__CLASS__, 'handle_save_settings']);

        add_action('admin_post_dg_save_seo_redirects', [__CLASS__, 'handle_save_redirects']);

        add_action('add_meta_boxes', [__CLASS__, 'register_meta_box']);

        add_action('save_post', [__CLASS__, 'save_post_meta'], 10, 2);

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_meta_box_assets']);

        add_action('admin_notices', [__CLASS__, 'rank_math_notice']);

        add_filter('manage_pages_columns', [__CLASS__, 'list_columns']);

        add_filter('manage_posts_columns', [__CLASS__, 'list_columns']);

        add_action('manage_pages_custom_column', [__CLASS__, 'list_column_content'], 10, 2);

        add_action('manage_posts_custom_column', [__CLASS__, 'list_column_content'], 10, 2);

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_list_columns_assets']);

        add_action('wp_ajax_dg_save_seo_inline', [__CLASS__, 'ajax_save_inline']);
        add_action('wp_ajax_dg_seo_analyze_page', [__CLASS__, 'ajax_analyze_page']);
        add_action('wp_ajax_dg_seo_save_audit', [__CLASS__, 'ajax_save_audit']);
        add_action('wp_ajax_dg_seo_indexnow', [__CLASS__, 'ajax_indexnow']);
        add_action('wp_ajax_dg_seo_ai_optimize', [__CLASS__, 'ajax_ai_optimize']);

    }



    /** @param array<string,string> $columns */

    public static function list_columns($columns) {

        if (!self::admin_visible() || !current_user_can('edit_posts')) {

            return $columns;

        }

        $out = [];

        foreach ($columns as $key => $label) {

            $out[$key] = $label;

            if ($key === 'title') {

                $out['dg_seo_keyword'] = 'Keyword';

                $out['dg_seo_title'] = 'SEO Title';

                $out['dg_seo_description'] = 'SEO Description';

                $out['dg_seo_robots'] = 'Robots';

                $out['dg_seo_index'] = 'Index';

            }

        }

        if (!isset($out['dg_seo_keyword'])) {

            $out['dg_seo_keyword'] = 'Keyword';

            $out['dg_seo_title'] = 'SEO Title';

            $out['dg_seo_description'] = 'SEO Description';

            $out['dg_seo_robots'] = 'Robots';

            $out['dg_seo_index'] = 'Index';

        }

        return $out;

    }



    public static function list_column_content($column, $post_id) {

        if (!self::admin_visible() || !current_user_can('edit_post', $post_id)) {

            return;

        }

        $allowed = ['dg_seo_keyword', 'dg_seo_title', 'dg_seo_description', 'dg_seo_robots'];

        if (!in_array($column, $allowed, true)) {

            return;

        }

        if ($column === 'dg_seo_robots') {
            $robots = DG_SEO_Settings::robots_value_from_meta($post_id);
            echo '<select class="dg-seo-inline dg-seo-inline-robots" data-post-id="' . (int) $post_id . '" data-field="robots" style="width:100%;min-width:130px;font-size:12px;">';
            foreach (DG_SEO_Settings::robots_options() as $opt_value => $label) {
                echo '<option value="' . esc_attr($opt_value) . '"' . selected($robots, $opt_value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            return;
        }

        if ($column === 'dg_seo_index' && class_exists('DG_SEO_IndexNow')) {
            DG_SEO_IndexNow::render_index_cell($post_id);
            return;
        }

        $prefix = DG_SEO_Settings::META_PREFIX;

        $map = [

            'dg_seo_keyword' => 'focus_keyword',

            'dg_seo_title' => 'title',

            'dg_seo_description' => 'description',

        ];

        $field = $map[$column];

        $value = get_post_meta($post_id, $prefix . $field, true);

        if ($field === 'title' || $field === 'description') {

            if (!$value) {

                $resolved = DG_SEO_Settings::get_post_seo($post_id);

                $value = $resolved[$field === 'title' ? 'title' : 'description'] ?? '';

            }

        }

        $input_type = $field === 'description' ? 'textarea' : 'input';

        $placeholder = $field === 'focus_keyword' ? 'Focus keyword' : ($field === 'title' ? 'SEO title' : 'Meta description');

        if ($input_type === 'textarea') {

            echo '<textarea class="dg-seo-inline" data-post-id="' . (int) $post_id . '" data-field="' . esc_attr($field) . '" rows="2" placeholder="' . esc_attr($placeholder) . '" style="width:100%;min-width:180px;font-size:12px;">' . esc_textarea($value) . '</textarea>';

        } else {

            echo '<input type="text" class="dg-seo-inline" data-post-id="' . (int) $post_id . '" data-field="' . esc_attr($field) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" style="width:100%;min-width:140px;font-size:12px;">';

        }

    }



    public static function enqueue_list_columns_assets($hook) {

        if ($hook !== 'edit.php' || !self::admin_visible()) {

            return;

        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (!$screen || !in_array($screen->post_type, DG_SEO_Settings::post_types_with_seo(), true)) {

            return;

        }

        wp_enqueue_script(

            'dg-seo-list-inline',

            DG_PLATFORM_URL . 'assets/js/seo-list-inline.js',

            ['jquery'],

            DG_PLATFORM_VERSION,

            true

        );

        wp_localize_script('dg-seo-list-inline', 'dgSeoListInline', [

            'ajaxUrl' => admin_url('admin-ajax.php'),

            'nonce' => wp_create_nonce('dg_seo_inline_save'),

            'indexNowNonce' => wp_create_nonce('dg_seo_indexnow'),

        ]);

    }



    public static function ajax_save_inline() {

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'dg_seo_inline_save')) {

            wp_send_json_error('Invalid nonce');

        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        $field = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';

        if (!$post_id || !current_user_can('edit_post', $post_id)) {

            wp_send_json_error('Unauthorized');

        }

        $allowed = ['focus_keyword', 'title', 'description', 'robots'];

        if (!in_array($field, $allowed, true)) {

            wp_send_json_error('Invalid field');

        }

        $value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

        if ($field === 'robots') {
            DG_SEO_Settings::apply_robots_value($post_id, sanitize_text_field($value));
            wp_send_json_success(['saved' => true]);
        }

        $value = $field === 'description' ? sanitize_textarea_field($value) : sanitize_text_field($value);

        update_post_meta($post_id, DG_SEO_Settings::META_PREFIX . $field, $value);

        wp_send_json_success(['saved' => true]);

    }

    public static function ajax_analyze_page() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'dg_seo_audit')) {
            wp_send_json_error('Invalid nonce');
        }
        if (!current_user_can('edit_posts') || !class_exists('DG_SEO_Analyzer')) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid page');
        }

        $analysis = DG_SEO_Analyzer::analyze($post_id);
        if (!empty($analysis['error'])) {
            wp_send_json_error($analysis['error']);
        }

        wp_send_json_success($analysis);
    }

    public static function ajax_save_audit() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'dg_seo_audit')) {
            wp_send_json_error('Invalid nonce');
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Unauthorized');
        }

        $prefix = DG_SEO_Settings::META_PREFIX;
        $fields = [
            'focus_keyword' => 'sanitize_text_field',
            'title' => 'sanitize_text_field',
            'description' => 'sanitize_textarea_field',
            'og_title' => 'sanitize_text_field',
            'og_description' => 'sanitize_textarea_field',
            'og_image' => 'esc_url_raw',
        ];

        foreach ($fields as $key => $sanitize) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $value = call_user_func($sanitize, wp_unslash($_POST[$key]));
            update_post_meta($post_id, $prefix . $key, $value);
        }

        if (isset($_POST['robots'])) {
            DG_SEO_Settings::apply_robots_value($post_id, sanitize_text_field(wp_unslash($_POST['robots'])));
        }

        if (class_exists('DG_SEO_Analyzer')) {
            wp_send_json_success(DG_SEO_Analyzer::analyze($post_id));
        }

        wp_send_json_success(['saved' => true]);
    }

    public static function ajax_ai_optimize() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'dg_seo_audit')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        if (!class_exists('DG_SEO_AI_Optimizer')) {
            wp_send_json_error(['message' => 'AI optimiser is not available.']);
        }

        $result = DG_SEO_AI_Optimizer::optimize($post_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public static function ajax_indexnow() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'dg_seo_indexnow')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'dg-platform')]);
        }

        if (!current_user_can('edit_posts') || !class_exists('DG_SEO_IndexNow')) {
            wp_send_json_error(['message' => __('Unauthorized', 'dg-platform')]);
        }

        $bulk = isset($_POST['bulk']) ? sanitize_key(wp_unslash($_POST['bulk'])) : '';
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($bulk === 'all') {
            $post_ids = DG_SEO_IndexNow::all_indexable_post_ids('page');
            if ($post_ids === []) {
                wp_send_json_error(['message' => __('No indexable pages found.', 'dg-platform')]);
            }
            $result = DG_SEO_IndexNow::submit_posts($post_ids);
        } elseif ($post_id > 0) {
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error(['message' => __('Unauthorized', 'dg-platform')]);
            }
            $result = DG_SEO_IndexNow::submit_posts([$post_id]);
        } else {
            wp_send_json_error(['message' => __('Invalid page.', 'dg-platform')]);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: number of URLs submitted */
                _n('%d URL submitted to IndexNow.', '%d URLs submitted to IndexNow.', (int) $result['count'], 'dg-platform'),
                (int) $result['count']
            ),
            'count' => (int) $result['count'],
            'submitted_at' => $result['submitted_at'] ?? '',
            'last_label' => $post_id > 0 ? DG_SEO_IndexNow::last_indexed_label($post_id) : '',
        ]);
    }



    public static function register_menu() {

        if (!current_user_can('manage_options') || !self::admin_visible()) {

            return;

        }



        add_submenu_page(

            'dg-platform',

            'SEO Pro',

            '🔍 SEO Pro',

            'manage_options',

            'dg-platform-seo',

            [__CLASS__, 'render_page']

        );

    }



    public static function render_page() {

        if (!current_user_can('manage_options')) {

            wp_die('Unauthorized');

        }



        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'audit';

        $settings = DG_SEO_Settings::all();
        $redirects = DG_SEO_Redirects::all();
        $audit_pages = class_exists('DG_SEO_Analyzer') ? DG_SEO_Analyzer::list_pages() : [];
        $selected_post = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        if (!$selected_post && !empty($audit_pages[0]['id'])) {
            $selected_post = (int) $audit_pages[0]['id'];
        }
        $audit_analysis = ($tab === 'audit' && $selected_post && class_exists('DG_SEO_Analyzer'))
            ? DG_SEO_Analyzer::analyze($selected_post)
            : null;
        $overview_pages = ($tab === 'pages' && class_exists('DG_SEO_Analyzer'))
            ? DG_SEO_Analyzer::list_pages_hierarchical('page')
            : [];

        include DG_PLATFORM_PATH . 'templates/admin/seo-settings.php';

    }



    public static function handle_save_settings() {

        if (!current_user_can('manage_options') || !check_admin_referer('dg_seo_settings')) {

            wp_die('Unauthorized');

        }



        DG_SEO_Settings::save($_POST);

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-seo&tab=settings&saved=1'));

        exit;

    }



    public static function handle_save_redirects() {

        if (!current_user_can('manage_options') || !check_admin_referer('dg_seo_redirects')) {

            wp_die('Unauthorized');

        }



        $rows = [];

        $from = isset($_POST['redirect_from']) && is_array($_POST['redirect_from']) ? $_POST['redirect_from'] : [];

        $to = isset($_POST['redirect_to']) && is_array($_POST['redirect_to']) ? $_POST['redirect_to'] : [];

        $code = isset($_POST['redirect_code']) && is_array($_POST['redirect_code']) ? $_POST['redirect_code'] : [];



        foreach ($from as $i => $f) {

            $rows[] = [

                'from' => sanitize_text_field($f),

                'to' => esc_url_raw($to[$i] ?? ''),

                'code' => (int) ($code[$i] ?? 301),

            ];

        }



        DG_SEO_Redirects::save($rows);

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-seo&tab=redirects&saved=1'));

        exit;

    }



    public static function register_meta_box() {

        foreach (DG_SEO_Settings::post_types_with_seo() as $post_type) {

            add_meta_box(

                'dg_seo_meta',

                'SEO Pro',

                [__CLASS__, 'render_meta_box'],

                $post_type,

                'side',

                'high'

            );

        }

    }



    public static function enqueue_meta_box_assets($hook) {

        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            self::enqueue_meta_box_scripts($hook);
        }

        if ($hook === 'dg-platform_page_dg-platform-seo' && self::admin_visible()) {
            self::enqueue_seo_admin_styles();
            wp_enqueue_script(
                'dg-seo-page-audit',
                DG_PLATFORM_URL . 'assets/js/seo-page-audit.js',
                ['jquery'],
                DG_PLATFORM_VERSION,
                true
            );
            wp_localize_script('dg-seo-page-audit', 'dgSeoAudit', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dg_seo_audit'),
                'indexNowNonce' => wp_create_nonce('dg_seo_indexnow'),
                'hasAi' => class_exists('DG_SEO_AI_Optimizer') && DG_SEO_AI_Optimizer::available(),
                'apiSettingsUrl' => admin_url('admin.php?page=dg-platform-api'),
            ]);
            wp_enqueue_script(
                'dg-seo-list-inline',
                DG_PLATFORM_URL . 'assets/js/seo-list-inline.js',
                ['jquery'],
                DG_PLATFORM_VERSION,
                true
            );
            wp_localize_script('dg-seo-list-inline', 'dgSeoListInline', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dg_seo_inline_save'),
                'indexNowNonce' => wp_create_nonce('dg_seo_indexnow'),
            ]);
        }
    }

    private static function enqueue_seo_admin_styles() {
        wp_enqueue_style(
            'dg-platform-admin',
            DG_PLATFORM_URL . 'assets/css/admin.css',
            [],
            DG_PLATFORM_VERSION
        );
        wp_enqueue_style(
            'dg-seo-admin',
            DG_PLATFORM_URL . 'assets/css/seo-admin.css',
            ['dg-platform-admin'],
            DG_PLATFORM_VERSION
        );
    }

    private static function enqueue_meta_box_scripts($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->post_type, DG_SEO_Settings::post_types_with_seo(), true)) {
            return;
        }

        self::enqueue_seo_admin_styles();

        wp_enqueue_script(
            'dg-seo-meta-box',
            DG_PLATFORM_URL . 'assets/js/seo-meta-box.js',
            ['jquery'],
            DG_PLATFORM_VERSION,
            true
        );
        wp_enqueue_script(
            'dg-seo-list-inline',
            DG_PLATFORM_URL . 'assets/js/seo-list-inline.js',
            ['jquery'],
            DG_PLATFORM_VERSION,
            true
        );
        wp_localize_script('dg-seo-list-inline', 'dgSeoListInline', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dg_seo_inline_save'),
            'indexNowNonce' => wp_create_nonce('dg_seo_indexnow'),
        ]);
    }



    public static function render_meta_box($post) {

        wp_nonce_field('dg_seo_post_meta', 'dg_seo_post_meta_nonce');



        $prefix = DG_SEO_Settings::META_PREFIX;

        $resolved = DG_SEO_Settings::get_post_seo($post->ID);

        $values = [

            'title' => get_post_meta($post->ID, $prefix . 'title', true),

            'description' => get_post_meta($post->ID, $prefix . 'description', true),

            'canonical' => get_post_meta($post->ID, $prefix . 'canonical', true),

            'og_title' => get_post_meta($post->ID, $prefix . 'og_title', true),

            'og_description' => get_post_meta($post->ID, $prefix . 'og_description', true),

            'og_image' => get_post_meta($post->ID, $prefix . 'og_image', true),

            'focus_keyword' => get_post_meta($post->ID, $prefix . 'focus_keyword', true),

            'noindex' => (bool) get_post_meta($post->ID, $prefix . 'noindex', true),

            'nofollow' => (bool) get_post_meta($post->ID, $prefix . 'nofollow', true),

            'robots' => DG_SEO_Settings::robots_value_from_meta($post->ID),

        ];



        $permalink = get_permalink($post);

        $site_name = DG_SEO_Settings::get('organization_name', get_bloginfo('name'));

        $fallback_title = $resolved['title'] ?? DG_SEO_Settings::auto_title($post);

        $fallback_description = $resolved['description'] ?? DG_SEO_Settings::auto_description($post);

        $audit_score = null;
        $audit_grade = null;
        if (class_exists('DG_SEO_Analyzer')) {
            try {
                $audit = DG_SEO_Analyzer::analyze($post->ID);
                $audit_score = isset($audit['score']) ? (int) $audit['score'] : null;
                $audit_grade = $audit['grade'] ?? null;
            } catch (Throwable $e) {
                $audit_score = null;
                $audit_grade = null;
            }
        }

        wp_localize_script('dg-seo-meta-box', 'dgSeoMetaBox', [

            'permalink' => $permalink ? wp_parse_url($permalink, PHP_URL_HOST) . wp_parse_url($permalink, PHP_URL_PATH) : '',

            'postTitle' => $post->post_title,

            'fallbackTitle' => $fallback_title,

            'fallbackDescription' => $fallback_description,

            'siteName' => $site_name,

        ]);



        include DG_PLATFORM_PATH . 'templates/admin/seo-meta-box.php';

    }



    /** @param WP_Post $post */

    public static function save_post_meta($post_id, $post) {

        if (!isset($_POST['dg_seo_post_meta_nonce']) || !wp_verify_nonce($_POST['dg_seo_post_meta_nonce'], 'dg_seo_post_meta')) {

            return;

        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {

            return;

        }

        if (!current_user_can('edit_post', $post_id)) {

            return;

        }



        $prefix = DG_SEO_Settings::META_PREFIX;

        $text_fields = ['title', 'description', 'canonical', 'og_image', 'og_title', 'og_description', 'focus_keyword'];

        foreach ($text_fields as $key) {

            $field = 'dg_seo_' . $key;

            if (!isset($_POST[$field])) {

                continue;

            }

            $val = in_array($key, ['description', 'og_description'], true)

                ? sanitize_textarea_field($_POST[$field])

                : sanitize_text_field($_POST[$field]);

            if (in_array($key, ['canonical', 'og_image'], true)) {

                $val = esc_url_raw($val);

            }

            update_post_meta($post_id, $prefix . $key, $val);

        }



        if (isset($_POST['dg_seo_robots'])) {
            DG_SEO_Settings::apply_robots_value($post_id, sanitize_text_field(wp_unslash($_POST['dg_seo_robots'])));
        }

    }



    public static function rank_math_notice() {

        if (!current_user_can('manage_options') || !DG_SEO_Settings::rank_math_active()) {

            return;

        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (!$screen || strpos($screen->id, 'dg-platform') === false) {

            return;

        }

        ?>

        <div class="notice notice-warning">

            <p><strong>SEO Pro is active.</strong> Rank Math is still installed — deactivate it to avoid duplicate meta tags and sitemaps. Existing Rank Math titles/descriptions have been copied into SEO Pro where possible.</p>

        </div>

        <?php

    }

}


