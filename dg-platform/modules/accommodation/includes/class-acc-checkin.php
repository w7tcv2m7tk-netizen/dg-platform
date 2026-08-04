<?php
/**
 * Guest check-in QR flow per property.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Checkin {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_dg_accommodation', [__CLASS__, 'save_meta'], 20);
        add_action('init', [__CLASS__, 'register_rewrite']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'render_checkin_page']);
        add_shortcode('dg_checkin_qr', [__CLASS__, 'checkin_qr_shortcode']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    public static function register_rest_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/accommodation/checkin/(?P<slug>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_checkin_info'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function register_rewrite() {
        add_rewrite_rule('^check-in/([^/]+)/?$', 'index.php?dg_checkin_slug=$matches[1]', 'top');
        add_rewrite_tag('%dg_checkin_slug%', '([^&]+)');
    }

    public static function query_vars($vars) {
        $vars[] = 'dg_checkin_slug';
        return $vars;
    }

    public static function property_by_slug($slug) {
        $posts = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => 1,
            'meta_query' => [['key' => 'dg_checkin_slug', 'value' => sanitize_title($slug)]],
        ]);
        return $posts ? $posts[0] : null;
    }

    /**
     * CVH Oxygen check-in pages use slugs like check-in-rainforest-dome.
     *
     * @return array<string,string> accommodation slug => WordPress page slug
     */
    public static function default_checkin_page_map() {
        return apply_filters('dg_acc_checkin_page_map', [
            'rainforest-dome' => 'check-in-rainforest-dome',
            'sanctuary-dome' => 'check-in-sanctuary-dome',
            'canopy-dome' => 'check-in-canopy-dome',
            'starlight-dome' => 'check-in-starlight-dome',
            'tiny-home' => 'check-in-tiny-home',
            'private-studio' => 'check-in-private-studio',
        ]);
    }

    public static function property_slug($property_id) {
        $property_id = (int) $property_id;
        if ($property_id <= 0) {
            return '';
        }
        $slug = sanitize_title((string) get_post_meta($property_id, 'dg_checkin_slug', true));
        if ($slug !== '') {
            return $slug;
        }
        return sanitize_title((string) get_post_field('post_name', $property_id));
    }

    public static function checkin_page_slug_for_property($property_id) {
        $property_id = (int) $property_id;
        $page_id = (int) get_post_meta($property_id, 'dg_checkin_page_id', true);
        if ($page_id > 0) {
            $slug = get_post_field('post_name', $page_id);
            if ($slug) {
                return sanitize_title($slug);
            }
        }

        $property_slug = self::property_slug($property_id);
        if ($property_slug === '') {
            return '';
        }

        $map = self::default_checkin_page_map();
        if (isset($map[$property_slug])) {
            return sanitize_title($map[$property_slug]);
        }

        return 'check-in-' . $property_slug;
    }

    public static function page_url_by_slug($page_slug) {
        $page_slug = sanitize_title((string) $page_slug);
        if ($page_slug === '') {
            return '';
        }
        $page = get_page_by_path($page_slug);
        if ($page && $page->post_status === 'publish') {
            return get_permalink($page);
        }
        return '';
    }

    public static function checkin_url_for_property($property_id) {
        $property_id = (int) $property_id;
        if ($property_id <= 0) {
            return '';
        }

        $page_id = (int) get_post_meta($property_id, 'dg_checkin_page_id', true);
        if ($page_id > 0 && get_post_status($page_id) === 'publish') {
            return get_permalink($page_id);
        }

        $page_slug = self::checkin_page_slug_for_property($property_id);
        $oxygen_url = self::page_url_by_slug($page_slug);
        if ($oxygen_url !== '') {
            return $oxygen_url;
        }

        $slug = self::property_slug($property_id);
        if ($slug === '') {
            return '';
        }

        return home_url('/check-in/' . $slug . '/');
    }

    /**
     * Guest-facing check-in details for emails and confirmation pages.
     *
     * @return array{instructions:string,checkin_url:string,checkin_page_label:string,cleaning_url:string,wifi_password:string,checkin_time:string,checkout_time:string,address:string}
     */
    public static function get_guest_checkin_details($property_id) {
        $property_id = (int) $property_id;
        if ($property_id <= 0) {
            return [
                'instructions' => '',
                'checkin_url' => '',
                'checkin_page_label' => '',
                'cleaning_url' => '',
                'wifi_password' => '',
                'checkin_time' => '',
                'checkout_time' => '',
                'address' => '',
            ];
        }

        $property = get_post($property_id);
        $label = $property ? (string) $property->post_title : 'your accommodation';
        $cleaning_url = class_exists('DG_Acc_Cleaning')
            ? DG_Acc_Cleaning::cleaning_url_for_property($property_id)
            : '';

        return [
            'instructions' => (string) get_post_meta($property_id, 'dg_checkin_instructions', true),
            'checkin_url' => self::checkin_url_for_property($property_id),
            'checkin_page_label' => $label,
            'cleaning_url' => $cleaning_url,
            'wifi_password' => (string) get_post_meta($property_id, 'dg_wifi_password', true),
            'checkin_time' => (string) get_post_meta($property_id, 'dg_checkin_time', true),
            'checkout_time' => (string) get_post_meta($property_id, 'dg_checkout_time', true),
            'address' => (string) get_post_meta($property_id, 'dg_address', true),
        ];
    }

    public static function add_meta_box() {
        add_meta_box(
            'dg_acc_checkin',
            '📱 Check-in QR',
            [__CLASS__, 'render_meta_box'],
            'dg_accommodation',
            'side',
            'default'
        );
    }

    public static function render_meta_box($post) {
        wp_nonce_field('dg_acc_checkin_save', 'dg_acc_checkin_nonce');
        $slug = get_post_meta($post->ID, 'dg_checkin_slug', true);
        if (!$slug) {
            $slug = sanitize_title($post->post_title);
        }
        $qr_url = get_post_meta($post->ID, 'dg_checkin_qr_url', true);
        $instructions = get_post_meta($post->ID, 'dg_checkin_instructions', true);
        $wifi = get_post_meta($post->ID, 'dg_wifi_password', true);
        $checkin_page_id = (int) get_post_meta($post->ID, 'dg_checkin_page_id', true);
        $checkin_url = self::checkin_url_for_property($post->ID);
        $resolved_slug = self::checkin_page_slug_for_property($post->ID);
        ?>
        <p>
            <label for="dg_checkin_page_id"><strong>Check-in page (Oxygen)</strong></label><br>
            <?php
            wp_dropdown_pages([
                'name' => 'dg_checkin_page_id',
                'id' => 'dg_checkin_page_id',
                'selected' => $checkin_page_id,
                'show_option_none' => '— Auto: ' . ($resolved_slug ?: 'check-in-{slug}') . ' —',
                'option_none_value' => '0',
            ]);
            ?>
            <span class="description">Auto-matches CVH pages like <code>check-in-rainforest-dome</code>.</span>
        </p>
        <p>
            <label for="dg_checkin_slug"><strong>Property slug</strong></label><br>
            <input type="text" name="dg_checkin_slug" id="dg_checkin_slug" value="<?php echo esc_attr($slug); ?>" style="width:100%;">
        </p>
        <p>
            <label for="dg_checkin_qr_url"><strong>QR image URL</strong></label><br>
            <input type="url" name="dg_checkin_qr_url" id="dg_checkin_qr_url" value="<?php echo esc_attr($qr_url); ?>" style="width:100%;" placeholder="https://...">
            <span class="description">Upload QR to Media Library and paste URL, or use shortcode on a page.</span>
        </p>
        <p><strong>Guest URL:</strong><br><code style="word-break:break-all;"><?php echo esc_html($checkin_url); ?></code></p>
        <p>
            <label for="dg_wifi_password"><strong>Wi‑Fi password</strong></label><br>
            <input type="text" name="dg_wifi_password" id="dg_wifi_password" value="<?php echo esc_attr($wifi); ?>" style="width:100%;" autocomplete="off">
            <span class="description">Included in check-in emails sent to guests after payment is confirmed.</span>
        </p>
        <p>
            <label for="dg_checkin_instructions"><strong>Check-in instructions</strong></label><br>
            <textarea name="dg_checkin_instructions" id="dg_checkin_instructions" rows="6" style="width:100%;"><?php echo esc_textarea($instructions); ?></textarea>
            <span class="description">Sent to guests after PayID or card payment is confirmed.</span>
        </p>
        <?php
    }

    public static function save_meta($post_id) {
        if (!isset($_POST['dg_acc_checkin_nonce']) || !wp_verify_nonce($_POST['dg_acc_checkin_nonce'], 'dg_acc_checkin_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $slug = sanitize_title($_POST['dg_checkin_slug'] ?? '');
        if ($slug) {
            update_post_meta($post_id, 'dg_checkin_slug', $slug);
        }
        $page_id = (int) ($_POST['dg_checkin_page_id'] ?? 0);
        if ($page_id <= 0) {
            $property_slug = self::property_slug($post_id);
            if ($property_slug !== '') {
                $map = self::default_checkin_page_map();
                $page_slug = isset($map[$property_slug])
                    ? sanitize_title($map[$property_slug])
                    : 'check-in-' . $property_slug;
                $page = get_page_by_path($page_slug);
                if ($page && $page->post_status === 'publish') {
                    $page_id = (int) $page->ID;
                }
            }
        }
        update_post_meta($post_id, 'dg_checkin_page_id', $page_id);
        update_post_meta($post_id, 'dg_checkin_qr_url', esc_url_raw($_POST['dg_checkin_qr_url'] ?? ''));
        update_post_meta($post_id, 'dg_checkin_instructions', wp_kses_post($_POST['dg_checkin_instructions'] ?? ''));
        update_post_meta($post_id, 'dg_wifi_password', sanitize_text_field($_POST['dg_wifi_password'] ?? ''));
    }

    public static function render_checkin_page() {
        $slug = get_query_var('dg_checkin_slug');
        if (!$slug) {
            return;
        }
        $property = self::property_by_slug($slug);
        if (!$property) {
            status_header(404);
            wp_die('Check-in link not found.', 'Not found', ['response' => 404]);
        }
        $instructions = get_post_meta($property->ID, 'dg_checkin_instructions', true);
        $wifi = get_post_meta($property->ID, 'dg_wifi_password', true);
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Check-in — <?php echo esc_html($property->post_title); ?></title>
            <style>
                body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 2rem; }
                .card { max-width: 480px; margin: 0 auto; background: #1e293b; border-radius: 16px; padding: 2rem; border: 1px solid #334155; }
                h1 { font-size: 1.5rem; margin: 0 0 1rem; color: #fff; }
                .instructions { line-height: 1.6; white-space: pre-wrap; }
                .wifi { margin-top: 1.5rem; padding: 1rem; background: #0f172a; border-radius: 8px; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>🏡 <?php echo esc_html($property->post_title); ?></h1>
                <p>Welcome! Here are your check-in details.</p>
                <?php if ($instructions) : ?>
                    <div class="instructions"><?php echo wp_kses_post($instructions); ?></div>
                <?php else : ?>
                    <p class="instructions">Your host will send detailed arrival instructions shortly.</p>
                <?php endif; ?>
                <?php if ($wifi) : ?>
                    <div class="wifi"><strong>Wi‑Fi password:</strong> <?php echo esc_html($wifi); ?></div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    public static function checkin_qr_shortcode($atts) {
        $atts = shortcode_atts(['id' => 0, 'slug' => ''], $atts);
        $property = null;
        if ($atts['id']) {
            $property = get_post((int) $atts['id']);
        } elseif ($atts['slug']) {
            $property = self::property_by_slug($atts['slug']);
        }
        if (!$property || $property->post_type !== 'dg_accommodation') {
            return '<p>Property not found.</p>';
        }
        $qr_url = get_post_meta($property->ID, 'dg_checkin_qr_url', true);
        $checkin_url = self::checkin_url_for_property($property->ID);
        ob_start();
        ?>
        <div class="dg-checkin-qr" style="text-align:center;padding:1.5rem;">
            <?php if ($qr_url) : ?>
                <img src="<?php echo esc_url($qr_url); ?>" alt="Check-in QR for <?php echo esc_attr($property->post_title); ?>" style="max-width:240px;height:auto;">
            <?php else : ?>
                <p style="font-size:0.9rem;color:#64748b;">Add a QR image URL in the property admin.</p>
            <?php endif; ?>
            <p><strong><?php echo esc_html($property->post_title); ?></strong></p>
            <?php if ($checkin_url) : ?>
                <p><a href="<?php echo esc_url($checkin_url); ?>">Open check-in page</a></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function rest_checkin_info($request) {
        $property = self::property_by_slug($request['slug']);
        if (!$property) {
            return new WP_Error('not_found', 'Property not found', ['status' => 404]);
        }
        return rest_ensure_response([
            'id' => $property->ID,
            'title' => $property->post_title,
            'checkin_url' => self::checkin_url_for_property($property->ID),
            'qr_url' => get_post_meta($property->ID, 'dg_checkin_qr_url', true),
            'has_instructions' => (bool) get_post_meta($property->ID, 'dg_checkin_instructions', true),
        ]);
    }
}
