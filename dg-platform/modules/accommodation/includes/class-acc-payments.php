<?php
/**
 * PayID, Stripe REST, and booking confirmation emails.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Payments {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'handle_payid_booking'], 1);
        add_action('init', [__CLASS__, 'handle_stripe_redirect']);
        add_action('admin_notices', [__CLASS__, 'stripe_status_notice']);
        add_action('wp_ajax_dg_reload_stripe', [__CLASS__, 'ajax_reload_stripe']);
    }

    public static function register_routes() {
        register_rest_route('dg-stripe/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'stripe_webhook_handler'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('dg-stripe/v1', '/create-payment-intent', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_payment_intent_api'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function send_booking_confirmation($data) {
        $subject = '📋 Booking Confirmation - ' . $data['accommodation'];
        $html = '
        <html><head><style>body{font-family:Arial,sans-serif;color:#2F2F2F;padding:20px;}.container{max-width:600px;margin:0 auto;background:#fff;padding:30px;border-radius:16px;border:1px solid #E0D6CC;}h2{color:#1C2B2A;border-bottom:2px solid #C9A46C;padding-bottom:10px;}.highlight{background:#f5f2ef;padding:20px;border-radius:10px;margin:15px 0;border-left:4px solid #C9A46C;}.payid-box{background:#f0f8ff;padding:20px;border-radius:10px;border:2px dashed #B9A48A;}</style></head>
        <body><div class="container">
            <h2>🏡 Thank You for Your Booking!</h2>
            <p>Dear <strong>' . esc_html($data['name']) . '</strong>,</p>
            <div class="highlight">
                <p><strong>Accommodation:</strong> ' . esc_html($data['accommodation']) . '</p>
                <p><strong>Check-in:</strong> ' . date('l, F j, Y', strtotime($data['checkin'])) . '</p>
                <p><strong>Check-out:</strong> ' . date('l, F j, Y', strtotime($data['checkout'])) . '</p>
                <p><strong>Nights:</strong> ' . esc_html($data['nights']) . '</p>
                <p><strong>Total:</strong> $' . number_format(floatval($data['total']), 2) . '</p>
                <p><strong>Reference:</strong> ' . esc_html($data['booking_ref']) . '</p>
            </div>
            <div class="payid-box">
                <p><strong>PayID:</strong> payid@currumbinvalleyhideaway.com.au</p>
                <p><strong>Reference:</strong> ' . esc_html($data['booking_ref']) . '</p>
                <p><strong>Amount:</strong> $' . number_format(floatval($data['total']), 2) . '</p>
            </div>
            <p>Once payment is confirmed, you\'ll receive check-in instructions.</p>
            <p>Warm regards,<br><strong>Currumbin Valley Hideaway</strong></p>
        </div></body></html>';

        wp_mail(
            $data['email'],
            $subject,
            $html,
            ['Content-Type: text/html; charset=UTF-8', 'From: Currumbin Valley Hideaway <bookings@currumbinvalleyhideaway.com.au>']
        );
    }

    public static function handle_payid_booking() {
        if (!isset($_POST['dg_payid_submit'])) {
            return;
        }
        if (!isset($_POST['dg_enquiry_nonce']) || !wp_verify_nonce($_POST['dg_enquiry_nonce'], 'dg_enquiry_action')) {
            wp_die('Security check failed.');
        }

        $accommodation_id = isset($_POST['accommodation_id']) ? intval($_POST['accommodation_id']) : 0;
        $checkin = isset($_POST['booking_checkin']) ? sanitize_text_field($_POST['booking_checkin']) : '';
        $checkout = isset($_POST['booking_checkout']) ? sanitize_text_field($_POST['booking_checkout']) : '';
        $name = isset($_POST['enquiry_name']) ? sanitize_text_field($_POST['enquiry_name']) : '';
        $email = isset($_POST['enquiry_email']) ? sanitize_email($_POST['enquiry_email']) : '';
        $phone = isset($_POST['enquiry_phone']) ? sanitize_text_field($_POST['enquiry_phone']) : '';
        $guests = isset($_POST['enquiry_guests']) ? intval($_POST['enquiry_guests']) : 2;
        $message = isset($_POST['enquiry_message']) ? sanitize_textarea_field($_POST['enquiry_message']) : '';

        if (empty($name) || empty($email)) {
            wp_die('Please fill in all required fields.');
        }

        $accommodation_name = get_the_title($accommodation_id);
        $nights = $checkin && $checkout ? round((strtotime($checkout) - strtotime($checkin)) / (60 * 60 * 24)) : 1;
        $rate = floatval(get_post_meta($accommodation_id, 'dg_weekday_rate', true));
        $cleaning_fee = floatval(get_post_meta($accommodation_id, 'dg_cleaning_fee', true));
        $total = ($rate * $nights) + $cleaning_fee;
        $booking_ref = 'PAYID-' . date('Ymd') . '-' . rand(1000, 9999);

        $booking_id = wp_insert_post([
            'post_type' => 'dg_booking',
            'post_title' => $booking_ref . ' - ' . $name,
            'post_status' => 'publish',
            'meta_input' => [
                'dg_booking_accommodation_id' => $accommodation_id,
                'dg_booking_accommodation_name' => $accommodation_name,
                'dg_booking_checkin' => $checkin,
                'dg_booking_checkout' => $checkout,
                'dg_booking_nights' => $nights,
                'dg_booking_total' => $total,
                'dg_booking_guests' => $guests,
                'dg_booking_name' => $name,
                'dg_booking_email' => $email,
                'dg_booking_phone' => $phone,
                'dg_booking_message' => $message,
                'dg_booking_ref' => $booking_ref,
                'dg_booking_paid' => 'no',
                'dg_booking_payment_method' => 'payid',
                'dg_booking_status' => 'pending',
            ],
        ]);

        if (is_wp_error($booking_id)) {
            wp_die('Error creating booking.');
        }

        self::send_booking_confirmation([
            'email' => $email,
            'accommodation' => $accommodation_name,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'nights' => $nights,
            'total' => $total,
            'subtotal' => $total - $cleaning_fee,
            'cleaning_fee' => $cleaning_fee,
            'guests' => $guests,
            'name' => $name,
            'booking_ref' => $booking_ref,
        ]);

        wp_redirect(home_url('/booking-confirmed/?ref=' . $booking_ref . '&payment_method=payid'));
        exit;
    }

    public static function handle_stripe_redirect() {
        if (!isset($_POST['dg_stripe_redirect'])) {
            return;
        }
    }

    public static function stripe_webhook_handler($request) {
        error_log('Stripe webhook received');
        return new WP_REST_Response('Webhook received', 200);
    }

    public static function create_payment_intent_api($request) {
        $data = $request->get_json_params();
        if (empty($data['booking_total'])) {
            return ['error' => 'Missing booking data'];
        }

        $total = floatval(str_replace('$', '', $data['booking_total']));
        $booking_ref = 'BOOK-' . time() . '-' . rand(1000, 9999);

        update_option('dg_temp_booking_' . $booking_ref, [
            'accommodation_id' => $data['accommodation_id'] ?? 0,
            'checkin' => $data['booking_checkin'] ?? '',
            'checkout' => $data['booking_checkout'] ?? '',
            'nights' => $data['booking_nights'] ?? 0,
            'total' => $total,
            'name' => $data['enquiry_name'] ?? '',
            'email' => $data['enquiry_email'] ?? '',
            'phone' => $data['enquiry_phone'] ?? '',
            'guests' => $data['enquiry_guests'] ?? 2,
        ]);

        return [
            'clientSecret' => 'pi_' . time() . '_secret_' . rand(1000, 9999),
            'booking_ref' => $booking_ref,
        ];
    }

    public static function stripe_status_notice() {
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'dg_accommodation') === false && strpos($screen->id, 'dg-stripe') === false)) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }

        $loaded = class_exists('\Stripe\Stripe');
        $enabled = get_option('dg_stripe_enabled', 'no');

        if ($loaded && $enabled === 'yes') {
            echo '<div class="notice notice-success"><p>✅ Stripe is loaded and enabled.</p></div>';
        } elseif ($loaded && $enabled === 'no') {
            echo '<div class="notice notice-info"><p>💳 Stripe is loaded but not enabled. <a href="' . esc_url(admin_url('edit.php?post_type=dg_accommodation&page=dg-stripe-settings')) . '">Enable Stripe</a></p></div>';
        } elseif (!$loaded) {
            echo '<div class="notice notice-warning"><p>⚠️ Stripe is not loaded. <a href="' . esc_url(admin_url('edit.php?post_type=dg_accommodation&page=dg-stripe-settings')) . '">Check Stripe settings</a></p></div>';
        }
    }

    public static function ajax_reload_stripe() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        wp_send_json_success(['message' => 'Stripe reloaded']);
    }
}

DG_Acc_Payments::init();
