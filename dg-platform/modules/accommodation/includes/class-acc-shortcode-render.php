<?php
/**
 * Restored CVH accommodation shortcode presentation (from original Fluent Snippets).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Shortcode_Render {

    /** @var bool */
    private static $assets_enqueued = false;

    /** @var bool */
    private static $checkout_script_enqueued = false;

    /** @var array<string,string> */
    private static $feature_icons = [
        'fire_pit' => '🔥',
        'mountain_views' => '⛰️',
        'sauna' => '🧖',
        'outdoor_shower' => '🚿',
        'air_conditioning' => '❄️',
        'pet_friendly' => '🐾',
        'wifi' => '📶',
        'kitchenette' => '🍳',
        'bbq' => '🥩',
        'parking' => '🚗',
        'private_deck' => '🏠',
        'spa' => '💆',
    ];

    /** @var array<string,string> */
    private static $feature_labels = [
        'fire_pit' => 'Fire Pit',
        'mountain_views' => 'Mountain Views',
        'sauna' => 'Sauna',
        'outdoor_shower' => 'Outdoor Shower',
        'air_conditioning' => 'Air Conditioning',
        'pet_friendly' => 'Pet Friendly',
        'wifi' => 'Free WiFi',
        'kitchenette' => 'Kitchenette',
        'bbq' => 'BBQ',
        'parking' => 'Parking',
        'private_deck' => 'Private Deck',
        'spa' => 'Spa',
    ];

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets() {
        if (self::$assets_enqueued) {
            return;
        }
        self::$assets_enqueued = true;
        wp_enqueue_style(
            'dg-acc-frontend',
            (defined('DG_PLATFORM_URL') ? DG_PLATFORM_URL : '') . 'modules/accommodation/assets/css/accommodation-frontend.css',
            [],
            defined('DG_PLATFORM_VERSION') ? DG_PLATFORM_VERSION : '1'
        );
    }

    public static function enqueue_checkout_script() {
        if (self::$checkout_script_enqueued) {
            return;
        }
        self::$checkout_script_enqueued = true;
        self::enqueue_assets();
        $asset_base = DG_PLATFORM_PATH . 'modules/accommodation/';
        wp_enqueue_script(
            'dg-acc-checkout-form',
            plugins_url('assets/js/checkout-form.js', $asset_base . 'accommodation.php'),
            [],
            defined('DG_PLATFORM_VERSION') ? DG_PLATFORM_VERSION : '1',
            true
        );
        wp_localize_script('dg-acc-checkout-form', 'dgCheckoutForm', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => esc_url_raw(rest_url('dg-stripe/v1/')),
            'stripeEnabled' => get_option('dg_stripe_enabled', 'no') === 'yes' && (bool) get_option('dg_stripe_publishable_key', ''),
            'stripeKey' => get_option('dg_stripe_publishable_key', ''),
            'confirmUrl' => home_url('/booking-confirmed/'),
        ]);
        if (get_option('dg_stripe_enabled', 'no') === 'yes' && get_option('dg_stripe_publishable_key', '')) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        }
    }

    public static function field($field_name, $post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        return get_post_meta((int) $post_id, $field_name, true);
    }

    public static function card_price($post_id) {
        $weekday = floatval(get_post_meta($post_id, 'dg_weekday_rate', true));
        $weekend = floatval(get_post_meta($post_id, 'dg_weekend_rate', true));
        if (!$weekday && !$weekend) {
            return 'Contact for Price';
        }
        $base = $weekday > 0 ? $weekday : $weekend;
        return '$' . number_format($base, 0) . '/night';
    }

    /** @return int[] */
    public static function gallery_ids($post_id) {
        $gallery = get_post_meta($post_id, 'dg_gallery', true);
        if (empty($gallery)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $gallery)))));
    }

    /**
     * @param array<string,mixed> $atts
     */
    public static function render_display($atts) {
        self::enqueue_assets();

        $atts = shortcode_atts([
            'posts_per_page' => 6,
            'type' => '',
            'featured' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'columns' => 2,
        ], $atts);

        $args = [
            'post_type' => 'dg_accommodation',
            'posts_per_page' => (int) $atts['posts_per_page'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'post_status' => 'publish',
        ];

        if (!empty($atts['type'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'dg_accommodation_type',
                'field' => 'slug',
                'terms' => explode(',', $atts['type']),
            ]];
        }
        if ($atts['featured'] === 'true') {
            $args['meta_query'] = [[
                'key' => 'dg_featured',
                'value' => 1,
                'type' => 'NUMERIC',
            ]];
        }
        if ($atts['orderby'] === 'price') {
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'dg_weekday_rate';
        }

        $query = new WP_Query($args);
        if (!$query->have_posts()) {
            return '<p style="text-align:center;padding:40px 0;color:#5A6B67;">No accommodation found.</p>';
        }

        $feature_icons = self::$feature_icons;
        $feature_labels = self::$feature_labels;

        ob_start();
        include __DIR__ . '/../templates/shortcode-accommodation-display.php';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function render_details($post_id) {
        self::enqueue_assets();
        if (class_exists('DG_Acc_Frontend')) {
            DG_Acc_Frontend::maybe_enqueue_legacy_cleanup();
        }

        $post_id = (int) $post_id;
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'dg_accommodation') {
            return '<p style="text-align:center;padding:40px 0;color:#5A6B67;">Accommodation not found.</p>';
        }

        $sleeps = self::field('dg_sleeps', $post_id);
        $beds = self::field('dg_bedrooms', $post_id);
        $baths = self::field('dg_bathrooms', $post_id);
        $max_guests = self::field('dg_max_guests', $post_id);
        $min_nights = self::field('dg_min_nights', $post_id);
        $size = self::field('dg_size', $post_id);
        $description = class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::get_description($post_id) : self::field('dg_description', $post_id);
        $address = self::field('dg_address', $post_id);
        $latitude = self::field('dg_latitude', $post_id);
        $longitude = self::field('dg_longitude', $post_id);
        $checkin_time = self::field('dg_checkin_time', $post_id) ?: '3:00 PM';
        $checkout_time = self::field('dg_checkout_time', $post_id) ?: '10:00 AM';
        $security_deposit = self::field('dg_security_deposit', $post_id);
        $cleaning_fee = self::field('dg_cleaning_fee', $post_id);
        $extra_guest_fee = self::field('dg_extra_guest_fee', $post_id);
        $video_url = self::field('dg_video_url', $post_id);
        $virtual_tour = self::field('dg_virtual_tour', $post_id);
        $featured = self::field('dg_featured', $post_id);
        $terms = get_the_terms($post_id, 'dg_accommodation_type');
        $type = ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';

        $weekday_rate = floatval(get_post_meta($post_id, 'dg_weekday_rate', true));
        $weekend_rate = floatval(get_post_meta($post_id, 'dg_weekend_rate', true));
        $weekday_peak = floatval(get_post_meta($post_id, 'dg_weekday_peak_rate', true));
        $weekend_peak = floatval(get_post_meta($post_id, 'dg_weekend_peak_rate', true));
        $last_minute = (int) get_post_meta($post_id, 'dg_last_minute_discount', true);
        $early_bird = (int) get_post_meta($post_id, 'dg_early_bird_discount', true);

        $gallery_ids = self::gallery_ids($post_id);
        $features = get_post_meta($post_id, 'dg_features', true);
        $features = is_array($features) ? $features : [];
        $active_features = array_filter($features);

        $feature_labels = [
            'fire_pit' => '🔥 Fire Pit',
            'mountain_views' => '⛰️ Mountain Views',
            'sauna' => '🧖 Sauna',
            'outdoor_shower' => '🚿 Outdoor Shower',
            'air_conditioning' => '❄️ Air Conditioning',
            'pet_friendly' => '🐾 Pet Friendly',
            'wifi' => '📶 Free WiFi',
            'kitchenette' => '🍳 Kitchenette',
            'bbq' => '🥩 BBQ',
            'parking' => '🚗 Parking',
            'private_deck' => '🏠 Private Deck',
            'spa' => '💆 Spa',
        ];

        if ($weekday_rate > 0) {
            $price_display = 'From $' . number_format($weekday_rate, 0) . ' / night';
        } elseif ($weekend_rate > 0) {
            $price_display = 'From $' . number_format($weekend_rate, 0) . ' / night';
        } else {
            $price_display = 'Contact for Price';
        }

        $bookable = class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::is_bookable($post_id) : true;
        $listing_label = class_exists('DG_Acc_Listing_Status') ? DG_Acc_Listing_Status::public_label($post_id) : '';

        ob_start();
        include __DIR__ . '/../templates/shortcode-accommodation-details.php';
        return ob_get_clean();
    }

    /**
     * @param object $module DG_Module_Accommodation instance
     */
    public static function render_book_now($module, $accommodation_id) {
        self::enqueue_assets();

        $accommodation_id = (int) $accommodation_id;
        $bookable = class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::get_bookable_accommodations() : [];
        list($checkin, $checkout) = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::parse_request_dates()
            : ['', ''];

        if (!$accommodation_id) {
            ob_start();
            ?>
            <div class="dg-book-now-page">
                <div class="dg-book-now-header">
                    <span class="dg-cvh-badge">🌿 BOOK A STAY</span>
                    <h1>Choose Your Retreat</h1>
                    <p>Select a property to check availability and complete your booking.</p>
                </div>
                <?php echo self::render_display(['posts_per_page' => 6, 'columns' => 2]); ?>
            </div>
            <?php
            return ob_get_clean();
        }

        $post = get_post($accommodation_id);
        if (!$post || $post->post_type !== 'dg_accommodation') {
            return '<p>Accommodation not found.</p>';
        }

        $property_title = get_the_title($accommodation_id);
        $booking_page_url = class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::booking_hub_url() : home_url('/');

        ob_start();
        include __DIR__ . '/../templates/shortcode-book-now.php';
        return ob_get_clean();
    }

    public static function render_booking_confirmation($ref) {
        self::enqueue_assets();

        $ref = sanitize_text_field($ref);
        if ($ref === '') {
            return self::confirmation_message('Booking Not Found', 'No booking reference was provided.');
        }

        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => 1,
            'meta_query' => [['key' => 'dg_booking_ref', 'value' => $ref]],
        ]);
        if (empty($bookings)) {
            return self::confirmation_message('Booking Not Found', 'No booking with reference <strong>' . esc_html($ref) . '</strong>.');
        }

        $booking = $bookings[0];
        $booking_id = $booking->ID;
        $name = get_post_meta($booking_id, 'dg_booking_name', true);
        $email = get_post_meta($booking_id, 'dg_booking_email', true);
        $phone = get_post_meta($booking_id, 'dg_booking_phone', true);
        $accommodation = get_post_meta($booking_id, 'dg_booking_accommodation_name', true);
        $accommodation_id = (int) get_post_meta($booking_id, 'dg_booking_accommodation_id', true);
        $checkin = get_post_meta($booking_id, 'dg_booking_checkin', true);
        $checkout = get_post_meta($booking_id, 'dg_booking_checkout', true);
        $guests = get_post_meta($booking_id, 'dg_booking_guests', true);
        $total = get_post_meta($booking_id, 'dg_booking_total', true);
        $status = get_post_meta($booking_id, 'dg_booking_status', true);
        $paid = get_post_meta($booking_id, 'dg_booking_paid', true);
        $payment_method = isset($_GET['payment_method']) ? sanitize_text_field(wp_unslash($_GET['payment_method'])) : get_post_meta($booking_id, 'dg_booking_payment_method', true);
        $is_confirmed = ($status === 'confirmed' || $paid === 'yes');
        $payid = get_option('dg_payid_address', 'accounts@aerroe.com.au');
        $account_name = get_option('dg_payid_account_name', 'Aerroe Holdings Pty Ltd');
        $bsb = get_option('dg_payid_bsb', '814 282');
        $account_number = get_option('dg_payid_account_number', '52057009');
        $stripe_enabled = get_option('dg_stripe_enabled', 'no') === 'yes';
        $checkin_data = ($is_confirmed && $accommodation_id && class_exists('DG_Acc_Checkin'))
            ? DG_Acc_Checkin::get_guest_checkin_details($accommodation_id)
            : [];

        ob_start();
        include __DIR__ . '/../templates/shortcode-booking-confirmation.php';
        return ob_get_clean();
    }

    private static function confirmation_message($title, $message) {
        ob_start();
        ?>
        <div class="dg-booking-confirmed-wrap">
            <div class="dg-booking-confirmed-card" style="text-align:center;">
                <h2><?php echo esc_html($title); ?></h2>
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
