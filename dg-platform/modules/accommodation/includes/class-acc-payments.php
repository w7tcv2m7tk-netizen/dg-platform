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
        add_action('admin_post_dg_payid_booking', [__CLASS__, 'handle_payid_booking_post']);
        add_action('admin_post_nopriv_dg_payid_booking', [__CLASS__, 'handle_payid_booking_post']);
        add_action('wp_ajax_dg_confirm_payid_booking', [__CLASS__, 'ajax_confirm_payid_booking']);
        add_action('wp_ajax_nopriv_dg_confirm_payid_booking', [__CLASS__, 'ajax_confirm_payid_booking']);
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
        register_rest_route('dg-stripe/v1', '/confirm-booking', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'confirm_stripe_booking_api'],
            'permission_callback' => '__return_true',
        ]);
    }

    /** @return string */
    private static function stripe_secret_key() {
        return trim((string) get_option('dg_stripe_secret_key', ''));
    }

    /**
     * @param string $endpoint e.g. payment_intents or payment_intents/pi_xxx
     * @param array<string, scalar> $body
     * @param 'GET'|'POST' $method
     * @return array<string, mixed>|WP_Error
     */
    private static function stripe_api($endpoint, $body = [], $method = 'POST') {
        $secret = self::stripe_secret_key();
        if ($secret === '') {
            return new WP_Error('stripe_not_configured', 'Stripe secret key is not configured.');
        }

        $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
            ],
            'timeout' => 30,
        ];

        if ($method === 'GET') {
            $response = wp_remote_get($url, $args);
        } else {
            $args['body'] = $body;
            $response = wp_remote_post($url, $args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($json)) {
            return new WP_Error('stripe_invalid_response', 'Invalid response from Stripe.');
        }
        if ($code >= 400) {
            $message = $json['error']['message'] ?? 'Stripe API error.';
            return new WP_Error('stripe_api_error', $message, ['status' => $code]);
        }

        return $json;
    }

    /** @return array<string, mixed>|null */
    private static function get_temp_booking($booking_ref) {
        $data = get_option('dg_temp_booking_' . $booking_ref, null);
        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    private static function set_temp_booking($booking_ref, array $data) {
        update_option('dg_temp_booking_' . $booking_ref, $data, false);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|WP_Error
     */
    private static function create_booking_from_data($booking_ref, array $data, $payment_method = 'stripe', $paid = 'yes') {
        $existing = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => 1,
            'meta_key' => 'dg_booking_ref',
            'meta_value' => $booking_ref,
            'fields' => 'ids',
        ]);
        if (!empty($existing)) {
            return (int) $existing[0];
        }

        $accommodation_id = intval($data['accommodation_id'] ?? 0);
        $accommodation_name = $accommodation_id ? get_the_title($accommodation_id) : ($data['accommodation_name'] ?? 'Accommodation');
        $checkin = sanitize_text_field($data['checkin'] ?? '');
        $checkout = sanitize_text_field($data['checkout'] ?? '');
        $nights = intval($data['nights'] ?? 0);
        $total = floatval($data['total'] ?? 0);
        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        $guests = intval($data['guests'] ?? 2);
        $message = sanitize_textarea_field($data['message'] ?? '');

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
                'dg_booking_paid' => $paid,
                'dg_booking_payment_method' => $payment_method,
                'dg_booking_status' => $paid === 'yes' ? 'confirmed' : 'pending',
                'dg_booking_source' => 'website',
            ],
        ]);

        if (is_wp_error($booking_id)) {
            return $booking_id;
        }

        if ($email) {
            self::send_booking_confirmation([
                'email' => $email,
                'accommodation' => $accommodation_name,
                'checkin' => $checkin,
                'checkout' => $checkout,
                'nights' => $nights,
                'total' => $total,
                'subtotal' => $total,
                'cleaning_fee' => 0,
                'guests' => $guests,
                'name' => $name,
                'booking_ref' => $booking_ref,
                'payment_method' => $payment_method,
                'is_paid' => $paid === 'yes',
                'source' => 'website',
            ]);
        }

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'type' => 'booking',
                'action' => 'stripe_payment',
                'description' => 'Stripe booking confirmed: ' . $booking_ref,
                'contact_email' => $email,
            ]);
        }

        return (int) $booking_id;
    }

    /** @return int|WP_Error */
    private static function finalize_stripe_booking($booking_ref) {
        $data = self::get_temp_booking($booking_ref);
        if (!$data) {
            return new WP_Error('booking_not_found', 'Booking session expired. Please try again.');
        }
        return self::create_booking_from_data($booking_ref, $data, 'stripe', 'yes');
    }

    public static function send_booking_confirmation($data) {
        $payment_method = $data['payment_method'] ?? 'payid';
        $is_paid = !empty($data['is_paid']) || $payment_method === 'stripe';
        $guest_name = class_exists('DG_Email_Names') ? DG_Email_Names::first_name($data['name'] ?? 'Guest', 'Guest') : ($data['name'] ?? 'Guest');
        $subject = $is_paid
            ? '✅ Booking Confirmed - ' . $data['accommodation']
            : '📋 Booking Confirmation - ' . $data['accommodation'];

        $payid_block = '';
        if (!$is_paid && $payment_method !== 'stripe') {
            $payid_block = '<div style="background:#f0f8ff;padding:20px;border-radius:10px;border:2px dashed #B9A48A;margin:15px 0;">'
                . '<p style="margin:0 0 8px;"><strong>PayID:</strong> payid@currumbinvalleyhideaway.com.au</p>'
                . '<p style="margin:0 0 8px;"><strong>Reference:</strong> ' . esc_html($data['booking_ref']) . '</p>'
                . '<p style="margin:0;"><strong>Amount:</strong> $' . number_format(floatval($data['total']), 2) . '</p>'
                . '</div>'
                . '<p style="margin:0 0 12px;line-height:1.6;">Once payment is confirmed, you\'ll receive check-in instructions by email.</p>';
        } elseif ($is_paid) {
            $payid_block = '<p style="margin:0 0 12px;line-height:1.6;">Your check-in instructions will arrive in a separate email shortly.</p>';
        }

        $highlight = 'background:#f5f2ef;padding:20px;border-radius:10px;margin:15px 0;border-left:4px solid #C9A46C;';
        $source_raw = $data['source'] ?? ($data['booking_source'] ?? 'website');
        $source_label = class_exists('DG_Email_Brand')
            ? DG_Email_Brand::booking_source_label($source_raw)
            : 'Direct';

        $inner = '<h2 style="color:#1C2B2A;border-bottom:2px solid #C9A46C;padding-bottom:10px;margin:0 0 16px;">'
            . ($is_paid ? 'Booking Confirmed!' : 'Thank You for Your Booking!') . '</h2>'
            . '<p style="margin:0 0 12px;line-height:1.6;">Dear <strong>' . esc_html($guest_name) . '</strong>,</p>'
            . '<div style="' . $highlight . '">'
            . '<p style="margin:0 0 8px;"><strong>Accommodation:</strong> ' . esc_html($data['accommodation']) . '</p>'
            . '<p style="margin:0 0 8px;"><strong>Source:</strong> ' . esc_html($source_label) . '</p>'
            . '<p style="margin:0 0 8px;"><strong>Check-in:</strong> ' . date('l, F j, Y', strtotime($data['checkin'])) . '</p>'
            . '<p style="margin:0 0 8px;"><strong>Check-out:</strong> ' . date('l, F j, Y', strtotime($data['checkout'])) . '</p>'
            . '<p style="margin:0 0 8px;"><strong>Nights:</strong> ' . esc_html($data['nights']) . '</p>'
            . '<p style="margin:0 0 8px;"><strong>Total:</strong> $' . number_format(floatval($data['total']), 2) . '</p>'
            . '<p style="margin:0;"><strong>Reference:</strong> ' . esc_html($data['booking_ref']) . '</p>'
            . '</div>'
            . $payid_block
            . '<p style="margin:16px 0 0;line-height:1.6;">Warm regards,<br><strong>Currumbin Valley Hideaway</strong></p>';

        $html = class_exists('DG_Email_Brand')
            ? DG_Email_Brand::wrap($inner, [
                'theme' => 'cvh',
                'footer_note' => 'Currumbin Valley Hideaway — Gold Coast hinterland stays',
            ])
            : '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#2F2F2F;padding:20px;">'
                . '<div style="max-width:600px;margin:0 auto;background:#fff;padding:30px;border-radius:16px;border:1px solid #E0D6CC;">'
                . $inner . '</div></body></html>';

        $headers = class_exists('DG_Email_Brand')
            ? DG_Email_Brand::mail_headers(true)
            : [
                'Content-Type: text/html; charset=UTF-8',
                'From: Currumbin Valley Hideaway <bookings@currumbinvalleyhideaway.com.au>',
            ];

        wp_mail($data['email'], $subject, $html, $headers);
    }

    /** @return array<string, mixed> */
    private static function parse_payid_request() {
        return [
            'accommodation_id' => isset($_POST['accommodation_id']) ? (int) $_POST['accommodation_id'] : 0,
            'checkin' => isset($_POST['booking_checkin']) ? sanitize_text_field(wp_unslash($_POST['booking_checkin'])) : '',
            'checkout' => isset($_POST['booking_checkout']) ? sanitize_text_field(wp_unslash($_POST['booking_checkout'])) : '',
            'name' => isset($_POST['enquiry_name']) ? sanitize_text_field(wp_unslash($_POST['enquiry_name'])) : '',
            'email' => isset($_POST['enquiry_email']) ? sanitize_email(wp_unslash($_POST['enquiry_email'])) : '',
            'phone' => isset($_POST['enquiry_phone']) ? sanitize_text_field(wp_unslash($_POST['enquiry_phone'])) : '',
            'guests' => isset($_POST['enquiry_guests']) ? (int) $_POST['enquiry_guests'] : 2,
            'message' => isset($_POST['enquiry_message']) ? sanitize_textarea_field(wp_unslash($_POST['enquiry_message'])) : '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success:bool,booking_ref?:string,redirect_url?:string,message?:string}
     */
    public static function process_payid_booking(array $data) {
        $accommodation_id = (int) ($data['accommodation_id'] ?? 0);
        $checkin = (string) ($data['checkin'] ?? '');
        $checkout = (string) ($data['checkout'] ?? '');
        $name = (string) ($data['name'] ?? '');
        $email = (string) ($data['email'] ?? '');
        $phone = (string) ($data['phone'] ?? '');
        $guests = (int) ($data['guests'] ?? 2);
        $message = (string) ($data['message'] ?? '');

        if ($name === '' || $email === '') {
            return ['success' => false, 'message' => 'Please fill in your name and email.'];
        }
        if ($checkin === '' || $checkout === '') {
            return ['success' => false, 'message' => 'Please select check-in and check-out dates on the calendar before confirming.'];
        }
        if (class_exists('DG_Acc_Frontend') && !DG_Acc_Frontend::are_booking_dates_valid($checkin, $checkout)) {
            return ['success' => false, 'message' => 'Those dates are not valid for booking (Saturdays cannot be check-in or check-out days).'];
        }
        if (class_exists('DG_Acc_Listing_Status') && !DG_Acc_Listing_Status::is_bookable($accommodation_id)) {
            return ['success' => false, 'message' => 'This accommodation is not yet available for booking.'];
        }

        $accommodation_name = get_the_title($accommodation_id);
        $quote = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::calculate_total($accommodation_id, $checkin, $checkout)
            : ['nights' => 1, 'subtotal' => 0, 'cleaning_fee' => 0, 'total' => 0, 'discount_amount' => 0, 'discount_type' => ''];
        $nights = (int) ($quote['nights'] ?? 0);
        if ($nights < 1) {
            $nights = max(1, (int) round((strtotime($checkout) - strtotime($checkin)) / 86400));
        }
        $total = (float) ($quote['total'] ?? 0);
        $cleaning_fee = (float) ($quote['cleaning_fee'] ?? 0);
        $subtotal = (float) ($quote['subtotal'] ?? 0);
        $discount_amount = (float) ($quote['discount_amount'] ?? 0);
        $discount_type = (string) ($quote['discount_type'] ?? '');
        if ($total <= 0 && $accommodation_id) {
            $rate = (float) get_post_meta($accommodation_id, 'dg_weekday_rate', true);
            $cleaning_fee = (float) get_post_meta($accommodation_id, 'dg_cleaning_fee', true);
            $subtotal = $rate * $nights;
            $total = $subtotal + $cleaning_fee;
        }

        $booking_ref = 'PAYID-' . gmdate('Ymd') . '-' . wp_rand(1000, 9999);
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
                'dg_booking_subtotal' => $subtotal,
                'dg_booking_cleaning_fee' => $cleaning_fee,
                'dg_booking_discount_amount' => $discount_amount,
                'dg_booking_discount_type' => $discount_type,
                'dg_booking_guests' => $guests,
                'dg_booking_name' => $name,
                'dg_booking_email' => $email,
                'dg_booking_phone' => $phone,
                'dg_booking_message' => $message,
                'dg_booking_ref' => $booking_ref,
                'dg_booking_paid' => 'no',
                'dg_booking_payment_method' => 'payid',
                'dg_booking_status' => 'pending',
                'dg_booking_source' => 'website',
            ],
        ], true);

        if (is_wp_error($booking_id)) {
            return ['success' => false, 'message' => 'Could not save your booking. Please try again or email stay@currumbinvalleyhideaway.com.au'];
        }

        self::send_booking_confirmation([
            'email' => $email,
            'accommodation' => $accommodation_name,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'nights' => $nights,
            'total' => $total,
            'subtotal' => $subtotal,
            'cleaning_fee' => $cleaning_fee,
            'discount_amount' => $discount_amount,
            'discount_type' => $discount_type,
            'guests' => $guests,
            'name' => $name,
            'booking_ref' => $booking_ref,
            'payment_method' => 'payid',
            'is_paid' => false,
            'source' => 'website',
        ]);

        do_action('dg_booking_created', (int) $booking_id, $booking_ref);

        return [
            'success' => true,
            'booking_ref' => $booking_ref,
            'redirect_url' => home_url('/booking-confirmed/?ref=' . rawurlencode($booking_ref) . '&payment_method=payid'),
        ];
    }

    public static function handle_payid_booking_post() {
        if (!isset($_POST['dg_enquiry_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dg_enquiry_nonce'])), 'dg_enquiry_action')) {
            wp_die('Security check failed.');
        }

        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
        $result = self::process_payid_booking(self::parse_payid_request());

        if (!empty($result['success'])) {
            wp_safe_redirect($result['redirect_url']);
            exit;
        }

        wp_safe_redirect(add_query_arg('booking_error', rawurlencode($result['message'] ?? 'Booking failed.'), $redirect_to));
        exit;
    }

    public static function ajax_confirm_payid_booking() {
        check_ajax_referer('dg_enquiry_action', 'dg_enquiry_nonce');
        $result = self::process_payid_booking(self::parse_payid_request());
        if (!empty($result['success'])) {
            wp_send_json_success([
                'booking_ref' => $result['booking_ref'],
                'redirect_url' => $result['redirect_url'],
            ]);
        }
        wp_send_json_error($result['message'] ?? 'Booking failed.');
    }

    public static function handle_stripe_redirect() {
        if (!isset($_POST['dg_stripe_redirect'])) {
            return;
        }
    }

    public static function stripe_webhook_handler($request) {
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');
        $webhook_secret = trim((string) get_option('dg_stripe_webhook_secret', ''));

        if ($webhook_secret !== '' && $sig_header) {
            if (!self::verify_webhook_signature($payload, $sig_header, $webhook_secret)) {
                return new WP_REST_Response(['error' => 'Invalid signature'], 400);
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return new WP_REST_Response(['error' => 'Invalid payload'], 400);
        }

        if ($event['type'] === 'payment_intent.succeeded') {
            $pi = $event['data']['object'] ?? [];
            $booking_ref = $pi['metadata']['booking_ref'] ?? '';
            if ($booking_ref) {
                self::finalize_stripe_booking($booking_ref);
            }
        }

        return new WP_REST_Response(['received' => true], 200);
    }

    private static function verify_webhook_signature($payload, $sig_header, $secret) {
        $parts = [];
        foreach (explode(',', $sig_header) as $item) {
            [$key, $value] = array_pad(explode('=', trim($item), 2), 2, null);
            if ($key && $value) {
                $parts[$key] = $value;
            }
        }
        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }
        $signed = hash_hmac('sha256', $parts['t'] . '.' . $payload, $secret);
        return hash_equals($signed, $parts['v1']);
    }

    public static function create_payment_intent_api($request) {
        if (get_option('dg_stripe_enabled', 'no') !== 'yes') {
            return new WP_REST_Response(['error' => 'Stripe is not enabled.'], 400);
        }

        $data = $request->get_json_params();
        if (empty($data['booking_total']) || empty($data['enquiry_email']) || empty($data['enquiry_name'])) {
            return new WP_REST_Response(['error' => 'Missing booking data.'], 400);
        }

        $total = floatval(preg_replace('/[^0-9.]/', '', (string) $data['booking_total']));
        if ($total < 0.5) {
            return new WP_REST_Response(['error' => 'Invalid booking amount.'], 400);
        }

        $booking_ref = 'BOOK-' . gmdate('Ymd') . '-' . wp_rand(1000, 9999);
        $amount_cents = (int) round($total * 100);

        self::set_temp_booking($booking_ref, [
            'accommodation_id' => intval($data['accommodation_id'] ?? 0),
            'checkin' => sanitize_text_field($data['booking_checkin'] ?? ''),
            'checkout' => sanitize_text_field($data['booking_checkout'] ?? ''),
            'nights' => intval($data['booking_nights'] ?? 0),
            'total' => $total,
            'name' => sanitize_text_field($data['enquiry_name'] ?? ''),
            'email' => sanitize_email($data['enquiry_email'] ?? ''),
            'phone' => sanitize_text_field($data['enquiry_phone'] ?? ''),
            'guests' => intval($data['enquiry_guests'] ?? 2),
        ]);

        $intent = self::stripe_api('payment_intents', [
            'amount' => $amount_cents,
            'currency' => 'aud',
            'automatic_payment_methods[enabled]' => 'true',
            'receipt_email' => sanitize_email($data['enquiry_email']),
            'metadata[booking_ref]' => $booking_ref,
            'metadata[site]' => home_url(),
            'description' => 'CVH booking ' . $booking_ref,
        ]);

        if (is_wp_error($intent)) {
            return new WP_REST_Response(['error' => $intent->get_error_message()], 500);
        }

        return new WP_REST_Response([
            'clientSecret' => $intent['client_secret'] ?? '',
            'booking_ref' => $booking_ref,
        ], 200);
    }

    public static function confirm_stripe_booking_api($request) {
        $data = $request->get_json_params();
        $payment_intent_id = sanitize_text_field($data['payment_intent_id'] ?? '');
        if ($payment_intent_id === '') {
            return new WP_REST_Response(['error' => 'Missing payment intent.'], 400);
        }

        $intent = self::stripe_api('payment_intents/' . rawurlencode($payment_intent_id), [], 'GET');
        if (is_wp_error($intent)) {
            return new WP_REST_Response(['error' => $intent->get_error_message()], 500);
        }

        if (($intent['status'] ?? '') !== 'succeeded') {
            return new WP_REST_Response(['error' => 'Payment not completed.'], 400);
        }

        $booking_ref = $intent['metadata']['booking_ref'] ?? '';
        if ($booking_ref === '') {
            return new WP_REST_Response(['error' => 'Booking reference missing.'], 400);
        }

        $result = self::finalize_stripe_booking($booking_ref);
        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message()], 500);
        }

        return new WP_REST_Response(['booking_ref' => $booking_ref, 'booking_id' => $result], 200);
    }

    public static function stripe_status_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Show on Stripe settings and CVH dashboard only — not every accommodation screen.
        $allowed = [
            'dg-platform_page_dg-stripe-settings',
            'dg-platform_page_dg-acc-dashboard',
        ];
        if (!in_array($screen->id, $allowed, true)) {
            return;
        }

        $loaded = class_exists('\Stripe\Stripe');
        $enabled = get_option('dg_stripe_enabled', 'no');
        $publishable = get_option('dg_stripe_publishable_key', '');
        $secret = get_option('dg_stripe_secret_key', '');
        $keys_configured = $publishable && $secret;
        $stripe_url = admin_url('admin.php?page=dg-stripe-settings');

        if ($enabled === 'yes' && $keys_configured) {
            $sdk_note = $loaded ? '' : ' (Stripe.js payments — webhook SDK optional)';
            echo '<div class="notice notice-success"><p>✅ Stripe is configured and enabled' . esc_html($sdk_note) . '.</p></div>';
        } elseif ($keys_configured && $enabled !== 'yes') {
            echo '<div class="notice notice-info"><p>💳 Stripe keys saved but not enabled. <a href="' . esc_url($stripe_url) . '">Enable Stripe</a></p></div>';
        } elseif ($enabled === 'yes' && !$keys_configured) {
            echo '<div class="notice notice-warning"><p>⚠️ Stripe is enabled but API keys are missing. <a href="' . esc_url($stripe_url) . '">Add Stripe keys</a></p></div>';
        }
    }

    public static function ajax_reload_stripe() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        wp_send_json_success(['message' => 'Stripe reloaded']);
    }
}