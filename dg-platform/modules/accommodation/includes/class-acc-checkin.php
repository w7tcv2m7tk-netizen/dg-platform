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

    public static function checkin_url_for_property($property_id) {
        $slug = get_post_meta($property_id, 'dg_checkin_slug', true);
        if (!$slug) {
            return '';
        }
        return home_url('/check-in/' . $slug . '/');
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
        $checkin_url = self::checkin_url_for_property($post->ID) ?: home_url('/check-in/' . $slug . '/');
        ?>
        <p>
            <label for="dg_checkin_slug"><strong>Check-in slug</strong></label><br>
            <input type="text" name="dg_checkin_slug" id="dg_checkin_slug" value="<?php echo esc_attr($slug); ?>" style="width:100%;">
        </p>
        <p>
            <label for="dg_checkin_qr_url"><strong>QR image URL</strong></label><br>
            <input type="url" name="dg_checkin_qr_url" id="dg_checkin_qr_url" value="<?php echo esc_attr($qr_url); ?>" style="width:100%;" placeholder="https://...">
            <span class="description">Upload QR to Media Library and paste URL, or use shortcode on a page.</span>
        </p>
        <p><strong>Guest URL:</strong><br><code style="word-break:break-all;"><?php echo esc_html($checkin_url); ?></code></p>
        <p>
            <label for="dg_checkin_instructions"><strong>Check-in instructions</strong></label><br>
            <textarea name="dg_checkin_instructions" id="dg_checkin_instructions" rows="4" style="width:100%;"><?php echo esc_textarea($instructions); ?></textarea>
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
        update_post_meta($post_id, 'dg_checkin_qr_url', esc_url_raw($_POST['dg_checkin_qr_url'] ?? ''));
        update_post_meta($post_id, 'dg_checkin_instructions', wp_kses_post($_POST['dg_checkin_instructions'] ?? ''));
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

add_action('rest_api_init', function () {
    if (class_exists('DG_Acc_Checkin')) {
        register_rest_route(DG_REST_NAMESPACE, '/accommodation/checkin/(?P<slug>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [DG_Acc_Checkin::class, 'rest_checkin_info'],
            'permission_callback' => '__return_true',
        ]);
    }
});

add_action('init', function () {
    if (class_exists('DG_Acc_Checkin')) {
        DG_Acc_Checkin::init();
    }
}, 5);
