<?php
/**
 * Native Airbnb reviews — replaces TrustIndex shortcode.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Reviews_Airbnb {

    /** @var array<string,string> */
    private static $default_listing_ids = [
        'private-studio' => '1654775429707386391',
        'tiny-home' => '1556825573586601637',
    ];

    public static function init() {
        add_shortcode('dg_airbnb_reviews', [__CLASS__, 'shortcode']);
        add_action('admin_post_dg_import_trustindex_reviews', [__CLASS__, 'handle_trustindex_import']);
    }

    /** @return string */
    public static function resolve_listing_id($airbnb_id = '', $accommodation_id = 0) {
        $airbnb_id = sanitize_text_field($airbnb_id);
        if ($airbnb_id !== '') {
            return $airbnb_id;
        }

        $accommodation_id = (int) $accommodation_id;
        if ($accommodation_id > 0) {
            $stored = get_post_meta($accommodation_id, 'dg_airbnb_id', true);
            if ($stored) {
                return (string) $stored;
            }
            $post = get_post($accommodation_id);
            if ($post && !empty($post->post_name) && isset(self::$default_listing_ids[$post->post_name])) {
                return self::$default_listing_ids[$post->post_name];
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $args
     * @return object[]
     */
    public static function get_reviews_for_listing($listing_id, $args = []) {
        if (!class_exists('DG_Reviews')) {
            return [];
        }

        DG_Reviews::ensure_table();
        global $wpdb;
        $table = DG_Reviews::table();
        $listing_id = sanitize_text_field($listing_id);
        $limit = max(1, min(20, (int) ($args['limit'] ?? 6)));
        $min_rating = (float) ($args['min_rating'] ?? 0);

        $where = ["platform = 'airbnb'", "status = 'published'"];
        $params = [];

        if ($listing_id !== '') {
            $where[] = '(listing_id = %s OR listing_id IS NULL OR listing_id = \'\')';
            $params[] = $listing_id;
        }

        if ($min_rating > 0) {
            $where[] = 'rating >= %f';
            $params[] = $min_rating;
        }

        $sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY review_date DESC, imported_at DESC LIMIT %d';
        $params[] = $limit;

        if ($params) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        }

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . $table . " WHERE platform = 'airbnb' AND status = 'published' ORDER BY review_date DESC, imported_at DESC LIMIT %d",
            $limit
        ));
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function render_listing_reviews($listing_id, $args = []) {
        $listing_id = self::resolve_listing_id($listing_id, (int) ($args['accommodation_id'] ?? 0));
        $reviews = self::get_reviews_for_listing($listing_id, $args);
        if (!$reviews) {
            return '';
        }

        $title = sanitize_text_field($args['title'] ?? 'Guest Reviews');
        $limit = (int) ($args['limit'] ?? 6);

        ob_start();
        ?>
        <div class="dg-airbnb-reviews" data-listing-id="<?php echo esc_attr($listing_id); ?>">
            <div class="dg-airbnb-reviews-header">
                <span class="dg-airbnb-reviews-badge">Airbnb</span>
                <h3 class="dg-airbnb-reviews-title"><?php echo esc_html($title); ?></h3>
            </div>
            <div class="dg-airbnb-reviews-grid">
                <?php foreach (array_slice($reviews, 0, $limit) as $review) : ?>
                    <div class="dg-airbnb-review-card">
                        <div class="dg-airbnb-review-top">
                            <strong class="dg-airbnb-review-author"><?php echo esc_html($review->author_name ?: 'Guest'); ?></strong>
                            <span class="dg-airbnb-review-stars"><?php echo esc_html(self::stars((float) $review->rating)); ?></span>
                        </div>
                        <?php if (!empty($review->review_date)) : ?>
                            <div class="dg-airbnb-review-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($review->review_date))); ?></div>
                        <?php endif; ?>
                        <div class="dg-airbnb-review-text"><?php echo wp_kses_post(wpautop($review->content)); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** @param array<string,string> $atts */
    public static function shortcode($atts) {
        $atts = shortcode_atts([
            'airbnb_id' => '',
            'accommodation_id' => 0,
            'limit' => 6,
            'min_rating' => 0,
            'title' => 'Guest Reviews',
        ], $atts, 'dg_airbnb_reviews');

        $listing_id = self::resolve_listing_id($atts['airbnb_id'], (int) $atts['accommodation_id']);
        if ($listing_id === '') {
            return '';
        }

        if (class_exists('DG_Acc_Shortcode_Render')) {
            DG_Acc_Shortcode_Render::enqueue_assets();
        }

        return self::render_listing_reviews($listing_id, [
            'limit' => (int) $atts['limit'],
            'min_rating' => (float) $atts['min_rating'],
            'title' => $atts['title'],
            'accommodation_id' => (int) $atts['accommodation_id'],
        ]);
    }

    public static function trustindex_table() {
        global $wpdb;
        return $wpdb->prefix . 'trustindex_airbnb_reviews';
    }

    public static function trustindex_available() {
        global $wpdb;
        $table = self::trustindex_table();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /** @return array{imported:int,skipped:int} */
    public static function import_from_trustindex($listing_id = '') {
        if (!self::trustindex_available() || !class_exists('DG_Reviews')) {
            return ['imported' => 0, 'skipped' => 0];
        }

        global $wpdb;
        $source = self::trustindex_table();
        $rows = $wpdb->get_results("SELECT * FROM {$source} WHERE hidden = 0 ORDER BY date DESC");
        $imported = $skipped = 0;
        $listing_id = sanitize_text_field($listing_id);

        foreach ($rows as $row) {
            $external_id = sanitize_text_field($row->reviewId ?? '');
            if ($external_id !== '' && DG_Reviews::exists_external('airbnb', $external_id)) {
                $skipped++;
                continue;
            }

            DG_Reviews::insert([
                'platform' => 'airbnb',
                'author_name' => sanitize_text_field($row->user ?? ''),
                'rating' => (float) ($row->rating ?? 5),
                'content' => wp_kses_post($row->text ?? ''),
                'review_date' => !empty($row->date) ? sanitize_text_field($row->date) : null,
                'external_id' => $external_id,
                'listing_id' => $listing_id,
                'author_photo' => esc_url_raw($row->user_photo ?? ''),
                'status' => 'published',
            ]);
            $imported++;
        }

        return compact('imported', 'skipped');
    }

    public static function handle_trustindex_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('dg_import_trustindex_reviews');

        $listing_id = sanitize_text_field($_POST['listing_id'] ?? '');
        $result = self::import_from_trustindex($listing_id);

        wp_safe_redirect(admin_url(
            'admin.php?page=dg-platform-reviews&trustindex_imported=' . (int) $result['imported']
            . '&trustindex_skipped=' . (int) $result['skipped']
        ));
        exit;
    }

    private static function stars($rating) {
        $full = (int) round($rating);
        return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full));
    }

    /** Set default Airbnb listing IDs on CVH properties when empty. */
    public static function maybe_set_default_listing_ids() {
        if (!post_type_exists('dg_accommodation')) {
            return;
        }

        foreach (self::$default_listing_ids as $slug => $listing_id) {
            $posts = get_posts([
                'post_type' => 'dg_accommodation',
                'name' => $slug,
                'posts_per_page' => 1,
                'post_status' => 'any',
            ]);
            if (empty($posts)) {
                continue;
            }
            $post_id = (int) $posts[0]->ID;
            if (!get_post_meta($post_id, 'dg_airbnb_id', true)) {
                update_post_meta($post_id, 'dg_airbnb_id', $listing_id);
            }
        }
    }
}
