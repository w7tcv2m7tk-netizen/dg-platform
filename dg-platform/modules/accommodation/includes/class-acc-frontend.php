<?php
/**
 * Accommodation front-end helpers — descriptions, rates, booking resolution.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Frontend {

    /** @var array<string,string> */
    private static $default_descriptions = [
        'private-studio' => "Nestled in the rainforest at Currumbin Valley Hideaway, the Private Studio offers an intimate retreat with its own entrance and valley views. Perfect for couples seeking privacy while still enjoying access to the property's communal fire pit and walking trails.\n\nWake to birdsong, unwind on your private deck, and explore the Gold Coast hinterland from this peaceful base.",
        'tiny-home' => "Experience minimalist luxury in our eco-friendly Tiny Home — a compact, thoughtfully designed stay surrounded by subtropical rainforest. Every square metre is optimised for comfort with a kitchenette, ensuite, and outdoor living space.\n\nIdeal for nature lovers and slow travellers who want a unique, low-impact hideaway minutes from Currumbin Beach and the hinterland.",
    ];

    /** @var array<string,array<string,bool>> */
    private static $default_features = [
        'private-studio' => [
            'mountain_views' => true,
            'air_conditioning' => true,
            'pet_friendly' => true,
            'wifi' => true,
            'kitchenette' => true,
            'parking' => true,
        ],
        'tiny-home' => [
            'mountain_views' => true,
            'air_conditioning' => true,
            'pet_friendly' => true,
            'wifi' => true,
            'kitchenette' => true,
            'parking' => true,
        ],
    ];

    private static $feature_labels = [
        'fire_pit' => '🔥 Fire Pit',
        'mountain_views' => '⛰️ Mountain Views',
        'sauna' => '🧖 Sauna',
        'outdoor_shower' => '🚿 Outdoor Shower',
        'air_conditioning' => '❄️ Air Conditioning',
        'pet_friendly' => '🐾 Pet Friendly',
        'wifi' => '📶 WiFi',
        'kitchenette' => '🍳 Kitchenette',
        'bbq' => '🥩 BBQ',
        'parking' => '🚗 Parking',
        'private_deck' => '🏠 Private Deck',
        'spa' => '💆 Spa',
    ];

    public static function resolve_accommodation_id($preferred = 0) {
        if (!empty($_GET['accommodation'])) {
            $id = (int) $_GET['accommodation'];
            if ($id > 0 && get_post_type($id) === 'dg_accommodation') {
                return $id;
            }
        }
        $preferred = (int) $preferred;
        if ($preferred > 0 && get_post_type($preferred) === 'dg_accommodation') {
            return $preferred;
        }
        if (is_singular('dg_accommodation')) {
            return (int) get_the_ID();
        }
        if (is_singular('page')) {
            $page_id = (int) get_queried_object_id();
            $linked = (int) get_post_meta($page_id, 'dg_linked_accommodation_id', true);
            if ($linked > 0 && get_post_type($linked) === 'dg_accommodation') {
                return $linked;
            }
            $by_landing = self::find_accommodation_by_landing_page($page_id);
            if ($by_landing) {
                return $by_landing;
            }
        }
        if (is_singular()) {
            $queried = get_queried_object();
            if ($queried && !empty($queried->post_name)) {
                $by_slug = self::find_accommodation_by_slug($queried->post_name);
                if ($by_slug) {
                    return $by_slug;
                }
            }
        }
        $path_slug = self::slug_from_request_path();
        if ($path_slug) {
            $by_path = self::find_accommodation_by_slug($path_slug);
            if ($by_path) {
                return $by_path;
            }
        }
        return 0;
    }

    public static function is_accommodation_landing_page($post_id = 0) {
        if (!$post_id) {
            $post_id = get_queried_object_id();
        }
        $post_id = (int) $post_id;
        if (!$post_id || !is_page($post_id)) {
            return false;
        }
        if ((int) get_post_meta($post_id, 'dg_linked_accommodation_id', true) > 0) {
            return true;
        }
        return (bool) self::find_accommodation_by_landing_page($post_id);
    }

    public static function get_landing_page_id($accommodation_id) {
        $accommodation_id = (int) $accommodation_id;
        if (!$accommodation_id) {
            return 0;
        }
        $page_id = (int) get_post_meta($accommodation_id, 'dg_landing_page_id', true);
        if ($page_id && get_post_status($page_id)) {
            return $page_id;
        }
        $slug = get_post_field('post_name', $accommodation_id);
        if ($slug) {
            $page = get_page_by_path($slug);
            if ($page) {
                return (int) $page->ID;
            }
        }
        return 0;
    }

    private static function find_accommodation_by_landing_page($page_id) {
        $page_id = (int) $page_id;
        if (!$page_id) {
            return 0;
        }
        $posts = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => 'dg_landing_page_id', 'value' => $page_id, 'compare' => '='],
            ],
        ]);
        return !empty($posts) ? (int) $posts[0]->ID : 0;
    }

    /** One-time: link accommodation CPT records to WP pages with matching slugs. */
    public static function maybe_link_landing_pages() {
        if (get_option('dg_acc_landing_pages_v1')) {
            return;
        }
        $accommodations = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);
        foreach ($accommodations as $acc) {
            $existing = (int) get_post_meta($acc->ID, 'dg_landing_page_id', true);
            if ($existing) {
                continue;
            }
            $page = get_page_by_path($acc->post_name);
            if ($page) {
                update_post_meta($acc->ID, 'dg_landing_page_id', $page->ID);
                update_post_meta($page->ID, 'dg_linked_accommodation_id', $acc->ID);
            }
        }
        update_option('dg_acc_landing_pages_v1', 1);
    }

    private static function slug_from_request_path() {
        if (empty($_SERVER['REQUEST_URI'])) {
            return '';
        }
        $parts = array_values(array_filter(explode('/', trim(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), '/'))));
        if (!$parts) {
            return '';
        }
        return sanitize_title(end($parts));
    }

    private static function find_accommodation_by_slug($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return 0;
        }
        $post = get_page_by_path($slug, OBJECT, 'dg_accommodation');
        if ($post) {
            return (int) $post->ID;
        }
        $posts = get_posts([
            'post_type' => 'dg_accommodation',
            'name' => $slug,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        return !empty($posts) ? (int) $posts[0]->ID : 0;
    }

    /** @return WP_Post[] */
    public static function get_bookable_accommodations() {
        $posts = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'post_status' => 'publish',
        ]);
        if (!class_exists('DG_Acc_Listing_Status')) {
            return $posts;
        }
        return array_values(array_filter($posts, function ($post) {
            return DG_Acc_Listing_Status::is_bookable($post->ID);
        }));
    }

    public static function parse_request_dates() {
        $checkin = isset($_GET['checkin']) ? sanitize_text_field(wp_unslash($_GET['checkin'])) : '';
        $checkout = isset($_GET['checkout']) ? sanitize_text_field(wp_unslash($_GET['checkout'])) : '';
        if (!$checkin || !$checkout || !self::are_booking_dates_valid($checkin, $checkout)) {
            return ['', ''];
        }
        return [$checkin, $checkout];
    }

    public static function are_booking_dates_valid($checkin, $checkout) {
        $ci = strtotime($checkin);
        $co = strtotime($checkout);
        if (!$ci || !$co || $co <= $ci) {
            return false;
        }
        if ((int) date('w', $ci) === 6) {
            return false;
        }
        if ((int) date('w', $co) === 6) {
            return false;
        }
        return true;
    }

    public static function maybe_enqueue_legacy_cleanup() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        add_action('wp_footer', [__CLASS__, 'enqueue_legacy_cleanup'], 5);
    }

    public static function enqueue_legacy_cleanup() {
        if (is_admin()) {
            return;
        }
        $asset_base = DG_PLATFORM_PATH . 'modules/accommodation/';
        wp_enqueue_script(
            'dg-acc-legacy-cleanup',
            plugins_url('assets/js/legacy-booking-cleanup.js', $asset_base . 'accommodation.php'),
            [],
            defined('DG_PLATFORM_VERSION') ? DG_PLATFORM_VERSION : '1',
            true
        );
    }

    public static function init() {
        add_action('wp', [__CLASS__, 'maybe_auto_legacy_cleanup']);
    }

    public static function init_frontend_hooks() {
        self::init();
    }

    public static function maybe_auto_legacy_cleanup() {
        if (!is_singular() || is_admin()) {
            return;
        }
        if (self::resolve_accommodation_id(0) || self::is_accommodation_landing_page()) {
            self::maybe_enqueue_legacy_cleanup();
        }
    }

    public static function default_accommodation_id() {
        $resolved = self::resolve_accommodation_id(0);
        if ($resolved) {
            return $resolved;
        }
        $bookable = self::get_bookable_accommodations();
        return !empty($bookable) ? (int) $bookable[0]->ID : 0;
    }

    public static function booking_page_url($accommodation_id = 0) {
        $accommodation_id = (int) $accommodation_id;
        if ($accommodation_id) {
            $landing = self::get_landing_page_id($accommodation_id);
            if ($landing) {
                return get_permalink($landing);
            }
        }
        $page_id = (int) get_option('dg_booking_page_id', 0);
        if ($page_id && get_post_status($page_id)) {
            $base = get_permalink($page_id);
            if ($accommodation_id) {
                $base = add_query_arg('accommodation', $accommodation_id, $base);
            }
            return $base;
        }
        return self::booking_hub_url($accommodation_id);
    }

    /** WordPress page used as the accommodation listing / booking hub. */
    public static function get_hub_page_id() {
        $page_id = (int) get_option('dg_booking_page_id', 0);
        if ($page_id && get_post_status($page_id)) {
            return $page_id;
        }
        foreach (['accommodation', 'stay', 'book-now'] as $slug) {
            $page = get_page_by_path($slug);
            if ($page && $page->post_status === 'publish') {
                return (int) $page->ID;
            }
        }
        return 0;
    }

    public static function booking_hub_permalink() {
        $hub_id = self::get_hub_page_id();
        if ($hub_id) {
            return get_permalink($hub_id);
        }

        foreach (self::get_bookable_accommodations() as $acc) {
            $landing = self::get_landing_page_id($acc->ID);
            if ($landing) {
                return get_permalink($landing);
            }
        }

        return home_url('/');
    }

    /** Fallback when no per-property landing page is linked. */
    public static function booking_hub_url($accommodation_id = 0) {
        return self::stays_back_url($accommodation_id);
    }

    /**
     * Safe URL for "Back to Stays" — prefers property landing pages over the hub listing.
     */
    public static function stays_back_url($accommodation_id = 0) {
        $accommodation_id = (int) $accommodation_id;
        if ($accommodation_id) {
            $landing = self::get_landing_page_id($accommodation_id);
            if ($landing) {
                return get_permalink($landing);
            }
        }

        foreach (self::get_bookable_accommodations() as $acc) {
            $landing = self::get_landing_page_id($acc->ID);
            if ($landing) {
                return get_permalink($landing);
            }
        }

        return self::booking_hub_permalink();
    }

    public static function get_description($post_id) {
        $meta = get_post_meta($post_id, 'dg_description', true);
        if (is_string($meta) && trim($meta) !== '') {
            return $meta;
        }
        $post = get_post($post_id);
        if ($post && trim($post->post_content) !== '') {
            return $post->post_content;
        }
        $terms = get_the_terms($post_id, 'dg_accommodation_type');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!empty($term->description)) {
                    return $term->description;
                }
                if (isset(self::$default_descriptions[$term->slug])) {
                    return self::$default_descriptions[$term->slug];
                }
            }
        }
        $slug = $post ? $post->post_name : '';
        if ($slug && isset(self::$default_descriptions[$slug])) {
            return self::$default_descriptions[$slug];
        }
        $title = $post ? strtolower($post->post_title) : '';
        if (strpos($title, 'studio') !== false) {
            return self::$default_descriptions['private-studio'];
        }
        if (strpos($title, 'tiny') !== false) {
            return self::$default_descriptions['tiny-home'];
        }
        return '';
    }

    public static function render_features($post_id) {
        $selected = get_post_meta($post_id, 'dg_features', true);
        if (!is_array($selected) || !array_filter($selected)) {
            $post = get_post($post_id);
            $slug = $post ? $post->post_name : '';
            if ($slug && isset(self::$default_features[$slug])) {
                $selected = self::$default_features[$slug];
            } else {
                $title = $post ? strtolower($post->post_title) : '';
                if (strpos($title, 'studio') !== false) {
                    $selected = self::$default_features['private-studio'];
                } elseif (strpos($title, 'tiny') !== false) {
                    $selected = self::$default_features['tiny-home'];
                } else {
                    return '';
                }
            }
        }
        $items = [];
        foreach (self::$feature_labels as $key => $label) {
            if (!empty($selected[$key])) {
                $items[] = $label;
            }
        }
        if (!$items) {
            return '';
        }
        ob_start();
        ?>
        <div class="dg-acc-features" style="margin:1.5rem 0;">
            <h3 style="font-size:1.1rem;color:#1C2B2A;margin:0 0 0.75rem;">✨ Features &amp; Amenities</h3>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <?php foreach ($items as $item) : ?>
                    <span style="background:#f5f2ef;padding:0.35rem 0.85rem;border-radius:20px;font-size:0.85rem;color:#4A5B59;"><?php echo esc_html($item); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_rates($post_id) {
        $weekday = floatval(get_post_meta($post_id, 'dg_weekday_rate', true));
        $weekend = floatval(get_post_meta($post_id, 'dg_weekend_rate', true));
        $weekday_peak = floatval(get_post_meta($post_id, 'dg_weekday_peak_rate', true));
        $weekend_peak = floatval(get_post_meta($post_id, 'dg_weekend_peak_rate', true));
        $cleaning = floatval(get_post_meta($post_id, 'dg_cleaning_fee', true));
        $deposit = floatval(get_post_meta($post_id, 'dg_security_deposit', true));
        $min_nights = (int) get_post_meta($post_id, 'dg_min_nights', true);
        $last_minute = (int) get_post_meta($post_id, 'dg_last_minute_discount', true);
        $early_bird = (int) get_post_meta($post_id, 'dg_early_bird_discount', true);
        if (!$weekday && !$weekend && !$cleaning) {
            return '';
        }

        ob_start();
        ?>
        <div class="dg-acc-rates" style="background:#f5f2ef;border-radius:12px;padding:1.25rem;margin:1rem 0;">
            <h3 style="font-size:1.1rem;color:#1C2B2A;margin:0 0 0.75rem;">💰 Rates</h3>
            <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                <?php if ($weekday) : ?>
                    <tr><td style="padding:6px 0;color:#4A5B59;">Mon–Thu</td><td style="padding:6px 0;text-align:right;font-weight:600;">$<?php echo number_format($weekday, 0); ?>/night</td></tr>
                <?php endif; ?>
                <?php if ($weekend) : ?>
                    <tr><td style="padding:6px 0;color:#4A5B59;">Fri–Sun</td><td style="padding:6px 0;text-align:right;font-weight:600;">$<?php echo number_format($weekend, 0); ?>/night</td></tr>
                <?php endif; ?>
                <?php if ($weekday_peak) : ?>
                    <tr><td style="padding:6px 0;color:#4A5B59;">Peak (weekday)</td><td style="padding:6px 0;text-align:right;font-weight:600;">$<?php echo number_format($weekday_peak, 0); ?>/night</td></tr>
                <?php endif; ?>
                <?php if ($weekend_peak) : ?>
                    <tr><td style="padding:6px 0;color:#4A5B59;">Peak (weekend)</td><td style="padding:6px 0;text-align:right;font-weight:600;">$<?php echo number_format($weekend_peak, 0); ?>/night</td></tr>
                <?php endif; ?>
                <?php if ($cleaning) : ?>
                    <tr><td style="padding:6px 0;color:#4A5B59;">Cleaning fee</td><td style="padding:6px 0;text-align:right;font-weight:600;">$<?php echo number_format($cleaning, 0); ?></td></tr>
                <?php endif; ?>
                <?php if ($deposit) : ?>
                    <tr><td style="padding:6px 0;color:#4A5B59;">Security deposit</td><td style="padding:6px 0;text-align:right;font-weight:600;">$<?php echo number_format($deposit, 0); ?></td></tr>
                <?php endif; ?>
            </table>
            <?php if ($min_nights > 1) : ?>
                <p style="margin:0.75rem 0 0;font-size:0.8rem;color:#6B7A78;">Minimum stay: <?php echo (int) $min_nights; ?> nights</p>
            <?php endif; ?>
            <?php if ($last_minute > 0 || $early_bird > 0) : ?>
                <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #E8DFD3;font-size:0.85rem;color:#4A5B59;">
                    <?php if ($last_minute > 0) : ?><div>⚡ Last minute (0–3 days): <?php echo (int) $last_minute; ?>% off</div><?php endif; ?>
                    <?php if ($early_bird > 0) : ?><div>🐦 Early bird (3–14 days): <?php echo (int) $early_bird; ?>% off</div><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_description($post_id) {
        $description = self::get_description($post_id);
        if ($description === '') {
            return '';
        }
        ob_start();
        ?>
        <div class="dg-acc-description" style="line-height:1.8;color:#4A5B59;margin:1rem 0;">
            <?php echo wp_kses_post(wpautop($description)); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_booking_summary($accommodation_id = 0) {
        $accommodation_id = (int) $accommodation_id;
        $property_name = $accommodation_id ? get_the_title($accommodation_id) : '';
        list($checkin, $checkout) = self::parse_request_dates();
        $quote = ($accommodation_id && $checkin && $checkout)
            ? self::calculate_total($accommodation_id, $checkin, $checkout)
            : ['nights' => 0, 'subtotal' => 0, 'total' => 0, 'discount_amount' => 0, 'discount_type' => '', 'cleaning_fee' => 0];
        $has_dates = $quote['nights'] > 0;

        ob_start();
        ?>
        <div id="dg-booking-summary-panel" class="dg-booking-summary-panel" style="background:#fff;border:1px solid #E0D6CC;border-radius:12px;padding:1.25rem;margin:1rem 0;">
            <h3 style="font-size:1.1rem;color:#1C2B2A;margin:0 0 0.75rem;">📋 Booking Summary</h3>
            <div data-dg-summary-empty style="<?php echo $has_dates ? 'display:none;' : ''; ?>color:#6B7A78;font-size:0.9rem;">
                Select your dates on the calendar to see your stay summary here.
            </div>
            <div data-dg-summary-content style="<?php echo $has_dates ? '' : 'display:none;'; ?>">
                <?php if ($property_name) : ?>
                    <p style="margin:0 0 0.5rem;font-weight:600;color:#1C2B2A;" data-dg-summary-property><?php echo esc_html($property_name); ?></p>
                <?php else : ?>
                    <p style="margin:0 0 0.5rem;font-weight:600;color:#1C2B2A;display:none;" data-dg-summary-property></p>
                <?php endif; ?>
                <p style="margin:0 0 0.35rem;font-size:0.9rem;color:#4A5B59;">Check-in: <strong data-dg-summary-checkin><?php echo $has_dates ? esc_html(date('j M Y', strtotime($checkin))) : ''; ?></strong></p>
                <p style="margin:0 0 0.35rem;font-size:0.9rem;color:#4A5B59;">Check-out: <strong data-dg-summary-checkout><?php echo $has_dates ? esc_html(date('j M Y', strtotime($checkout))) : ''; ?></strong></p>
                <p style="margin:0 0 0.35rem;font-size:0.9rem;color:#4A5B59;">Nights: <strong data-dg-summary-nights><?php echo $has_dates ? (int) $quote['nights'] : '0'; ?></strong></p>
                <?php if ($has_dates && !empty($quote['discount_amount'])) : ?>
                    <p style="margin:0 0 0.35rem;font-size:0.9rem;color:#2D4A2E;">Discount (<?php echo esc_html($quote['discount_type']); ?>): <strong>- $<?php echo number_format($quote['discount_amount'], 2); ?></strong></p>
                <?php endif; ?>
                <p style="margin:0.75rem 0 0;font-size:1rem;color:#1C2B2A;">Total: <strong data-dg-summary-total><?php echo $has_dates ? '$' . number_format($quote['total'], 2) : '$0.00'; ?></strong></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_booking_rules() {
        ob_start();
        ?>
        <div class="dg-booking-rules" style="margin:1rem 0;padding:1rem 1.25rem;background:#fef8e7;border-left:4px solid #f39c12;border-radius:4px;font-size:0.9rem;color:#4A5B59;">
            <strong style="display:block;margin-bottom:0.5rem;color:#1C2B2A;">📌 Booking rules</strong>
            <ul style="margin:0;padding-left:1.2rem;line-height:1.7;">
                <li>Saturdays <strong>are available</strong> for overnight stays</li>
                <li><strong>No check-ins</strong> on Saturdays</li>
                <li><strong>No check-outs</strong> on Saturdays</li>
                <li>Friday check-ins require a <strong>minimum 2-night stay</strong></li>
                <li>All other days require a <strong>minimum 1-night stay</strong></li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function get_blocked_dates($accommodation_id) {
        $blocked = [];
        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'meta_query' => [['key' => 'dg_booking_accommodation_id', 'value' => $accommodation_id, 'compare' => '=']],
        ]);
        foreach ($bookings as $b) {
            $checkin = get_post_meta($b->ID, 'dg_booking_checkin', true);
            $checkout = get_post_meta($b->ID, 'dg_booking_checkout', true);
            $status = get_post_meta($b->ID, 'dg_booking_status', true);
            if ($status === 'cancelled' || !$checkin || !$checkout) {
                continue;
            }
            $current = strtotime($checkin);
            $end = strtotime($checkout);
            while ($current < $end) {
                $blocked[] = date('Y-m-d', $current);
                $current = strtotime('+1 day', $current);
            }
        }
        $manual = get_post_meta($accommodation_id, 'dg_blocked_dates', true);
        if (is_string($manual) && trim($manual) !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $manual) as $line) {
                $line = trim($line);
                if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})$/i', $line, $m)) {
                    $current = strtotime($m[1]);
                    $end = strtotime($m[2]);
                    while ($current <= $end) {
                        $blocked[] = date('Y-m-d', $current);
                        $current = strtotime('+1 day', $current);
                    }
                }
            }
        }
        $blocked = array_values(array_unique($blocked));
        sort($blocked);
        return $blocked;
    }

    public static function calculate_total($accommodation_id, $checkin, $checkout) {
        if (!$checkin || !$checkout) {
            return [
                'nights' => 0,
                'subtotal' => 0,
                'cleaning_fee' => 0,
                'discount_amount' => 0,
                'discount_percent' => 0,
                'discount_type' => '',
                'total' => 0,
            ];
        }
        $start = strtotime($checkin);
        $end = strtotime($checkout);
        if ($end <= $start) {
            return [
                'nights' => 0,
                'subtotal' => 0,
                'cleaning_fee' => 0,
                'discount_amount' => 0,
                'discount_percent' => 0,
                'discount_type' => '',
                'total' => 0,
            ];
        }
        $weekday = floatval(get_post_meta($accommodation_id, 'dg_weekday_rate', true));
        $weekend = floatval(get_post_meta($accommodation_id, 'dg_weekend_rate', true));
        if (!$weekend) {
            $weekend = $weekday;
        }
        $peak_start = get_post_meta($accommodation_id, 'dg_peak_season_start', true) ?: '12-15';
        $peak_end = get_post_meta($accommodation_id, 'dg_peak_season_end', true) ?: '01-15';
        $weekday_peak = floatval(get_post_meta($accommodation_id, 'dg_weekday_peak_rate', true)) ?: $weekday;
        $weekend_peak = floatval(get_post_meta($accommodation_id, 'dg_weekend_peak_rate', true)) ?: $weekend;

        $subtotal = 0;
        $nights = 0;
        $current = $start;
        while ($current < $end) {
            $nights++;
            $dow = (int) date('N', $current);
            $is_weekend = ($dow >= 5);
            $md = date('m-d', $current);
            $in_peak = self::is_in_peak_season($md, $peak_start, $peak_end);
            if ($is_weekend) {
                $subtotal += $in_peak ? $weekend_peak : $weekend;
            } else {
                $subtotal += $in_peak ? $weekday_peak : $weekday;
            }
            $current = strtotime('+1 day', $current);
        }
        $cleaning = floatval(get_post_meta($accommodation_id, 'dg_cleaning_fee', true));
        $discount = self::calculate_discount($accommodation_id, $checkin, $subtotal);
        $total = max(0, $subtotal - $discount['amount'] + $cleaning);

        return [
            'nights' => $nights,
            'subtotal' => $subtotal,
            'cleaning_fee' => $cleaning,
            'discount_amount' => $discount['amount'],
            'discount_percent' => $discount['percent'],
            'discount_type' => $discount['type'],
            'total' => $total,
        ];
    }

    /**
     * Last minute (0–3 days) and early bird (3–14 days) discounts from property meta.
     *
     * @return array{amount:float,percent:int,type:string}
     */
    private static function calculate_discount($accommodation_id, $checkin, $subtotal) {
        $days_until = (int) floor((strtotime($checkin) - strtotime(current_time('Y-m-d'))) / 86400);
        $last_minute = (int) get_post_meta($accommodation_id, 'dg_last_minute_discount', true);
        $early_bird = (int) get_post_meta($accommodation_id, 'dg_early_bird_discount', true);

        $percent = 0;
        $type = '';
        if ($days_until >= 0 && $days_until <= 3 && $last_minute > 0) {
            $percent = $last_minute;
            $type = 'Last Minute';
        } elseif ($days_until > 3 && $days_until <= 14 && $early_bird > 0) {
            $percent = $early_bird;
            $type = 'Early Bird';
        }

        $amount = $percent > 0 ? round($subtotal * ($percent / 100), 2) : 0.0;
        return [
            'amount' => $amount,
            'percent' => $percent,
            'type' => $type,
        ];
    }

    private static function is_in_peak_season($md, $start, $end) {
        if ($start <= $end) {
            return $md >= $start && $md <= $end;
        }
        return $md >= $start || $md <= $end;
    }
}
