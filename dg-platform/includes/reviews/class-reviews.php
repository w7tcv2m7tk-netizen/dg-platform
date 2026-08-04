<?php
/**
 * Reviews service — import and display customer reviews from major platforms.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Reviews {

    /** @return array<string,string> */
    public static function platforms() {
        return apply_filters('dg_reviews_platforms', [
            'google_business' => 'Google Business Profile',
            'airbnb' => 'Airbnb',
            'booking_com' => 'Booking.com',
            'rea' => 'realestate.com.au',
            'domain' => 'Domain.com.au',
            'tripadvisor' => 'TripAdvisor',
            'facebook' => 'Facebook',
            'trustpilot' => 'Trustpilot',
            'productreview' => 'ProductReview.com.au',
            'manual' => 'Manual / Other',
        ]);
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_reviews';
    }

    public static function ensure_table() {
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            if (class_exists('DG_Activator')) {
                DG_Activator::create_tables();
            }
        }
        self::maybe_add_columns();
    }

    private static function maybe_add_columns() {
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!in_array('listing_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN listing_id varchar(100) DEFAULT NULL AFTER external_id");
            $wpdb->query("ALTER TABLE {$table} ADD KEY listing_id (listing_id)");
        }
        if (!in_array('author_photo', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN author_photo varchar(500) DEFAULT NULL AFTER author_name");
        }
    }

    public static function exists_external($platform, $external_id) {
        if ($external_id === '') {
            return false;
        }
        self::ensure_table();
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE platform = %s AND external_id = %s LIMIT 1',
            sanitize_key($platform),
            sanitize_text_field($external_id)
        ));
        return (bool) $id;
    }

    public static function init() {
        add_shortcode('dg_reviews', [__CLASS__, 'shortcode']);
    }

    /** @param array<string,mixed> $args */
    public static function get_reviews($args = []) {
        self::ensure_table();
        global $wpdb;
        $defaults = [
            'platform' => '',
            'min_rating' => 0,
            'status' => 'published',
            'limit' => 20,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);
        $where = ['1=1'];
        $params = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        if ($args['platform']) {
            $where[] = 'platform = %s';
            $params[] = $args['platform'];
        }
        if (!empty($args['listing_id'])) {
            $where[] = '(listing_id = %s OR listing_id IS NULL OR listing_id = \'\')';
            $params[] = sanitize_text_field($args['listing_id']);
        }
        if ($args['min_rating'] > 0) {
            $where[] = 'rating >= %f';
            $params[] = (float) $args['min_rating'];
        }

        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY review_date DESC, imported_at DESC LIMIT %d OFFSET %d';
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /** @param array<string,mixed> $data */
    public static function insert($data) {
        global $wpdb;
        $row = [
            'platform' => sanitize_key($data['platform'] ?? 'manual'),
            'author_name' => sanitize_text_field($data['author_name'] ?? ''),
            'author_photo' => esc_url_raw($data['author_photo'] ?? ''),
            'rating' => min(5, max(0, (float) ($data['rating'] ?? 0))),
            'title' => sanitize_text_field($data['title'] ?? ''),
            'content' => wp_kses_post($data['content'] ?? ''),
            'review_date' => !empty($data['review_date']) ? sanitize_text_field($data['review_date']) : null,
            'source_url' => esc_url_raw($data['source_url'] ?? ''),
            'external_id' => sanitize_text_field($data['external_id'] ?? ''),
            'listing_id' => sanitize_text_field($data['listing_id'] ?? ''),
            'status' => in_array($data['status'] ?? 'published', ['published', 'hidden'], true) ? $data['status'] : 'published',
            'imported_at' => current_time('mysql'),
        ];
        $wpdb->insert(self::table(), $row);
        return (int) $wpdb->insert_id;
    }

    public static function delete($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::table(), ['id' => (int) $id], ['%d']);
    }

    public static function count_by_platform() {
        self::ensure_table();
        global $wpdb;
        $rows = $wpdb->get_results('SELECT platform, COUNT(*) AS total FROM ' . self::table() . " WHERE status = 'published' GROUP BY platform", ARRAY_A);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['platform']] = (int) $row['total'];
        }
        return $counts;
    }

    /** @param array<string,string> $atts */
    public static function shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 6,
            'platform' => '',
            'min_rating' => 0,
            'columns' => 2,
        ], $atts, 'dg_reviews');

        $reviews = self::get_reviews([
            'limit' => (int) $atts['limit'],
            'platform' => sanitize_key($atts['platform']),
            'min_rating' => (float) $atts['min_rating'],
        ]);
        if (!$reviews) {
            return '';
        }

        $platforms = self::platforms();
        ob_start();
        ?>
        <div class="dg-reviews-grid" style="display:grid;grid-template-columns:repeat(<?php echo max(1, (int) $atts['columns']); ?>,1fr);gap:1.5rem;">
            <?php foreach ($reviews as $review) : ?>
                <div class="dg-review-card" style="background:#fff;border:1px solid #E0D6CC;border-radius:16px;padding:1.25rem;">
                    <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;margin-bottom:8px;">
                        <strong style="color:#1C2B2A;"><?php echo esc_html($review->author_name ?: 'Guest'); ?></strong>
                        <span style="color:#B9A48A;font-weight:700;"><?php echo esc_html(str_repeat('★', (int) round($review->rating)) . str_repeat('☆', 5 - (int) round($review->rating))); ?></span>
                    </div>
                    <div style="font-size:12px;color:#6B7A78;margin-bottom:8px;"><?php echo esc_html($platforms[$review->platform] ?? $review->platform); ?><?php if ($review->review_date) : ?> · <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($review->review_date))); ?><?php endif; ?></div>
                    <?php if ($review->title) : ?><div style="font-weight:600;margin-bottom:6px;"><?php echo esc_html($review->title); ?></div><?php endif; ?>
                    <div style="color:#4A5B59;line-height:1.6;font-size:0.95rem;"><?php echo wp_kses_post(wpautop($review->content)); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <style>@media (max-width:768px){.dg-reviews-grid{grid-template-columns:1fr!important;}}</style>
        <?php
        return ob_get_clean();
    }
}
