<?php
/**
 * Guest-facing booking emails (check-in instructions after payment confirmed).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Guest_Notifications {

    const CHECKIN_EMAIL_META = '_dg_acc_checkin_email_sent';

    public static function init() {
        add_action('dg_booking_confirmed', [__CLASS__, 'on_booking_confirmed'], 20, 1);
        add_action('save_post_dg_booking', [__CLASS__, 'maybe_send_on_paid'], 25, 3);
        add_action('admin_post_dg_resend_checkin_email', [__CLASS__, 'handle_resend_admin']);
    }

    public static function guest_from_email() {
        return apply_filters(
            'dg_acc_guest_from_email',
            'Currumbin Valley Hideaway <bookings@currumbinvalleyhideaway.com.au>'
        );
    }

    public static function on_booking_confirmed($booking_id) {
        self::maybe_send_checkin_email((int) $booking_id);
    }

    public static function maybe_send_on_paid($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        if (get_post_meta($post_id, 'dg_booking_paid', true) !== 'yes') {
            return;
        }

        $status = get_post_meta($post_id, 'dg_booking_status', true);
        if ($status === 'cancelled') {
            return;
        }

        self::maybe_send_checkin_email((int) $post_id);
    }

    public static function maybe_send_checkin_email($booking_id) {
        if ($booking_id <= 0) {
            return false;
        }

        if (get_post_meta($booking_id, self::CHECKIN_EMAIL_META, true) === 'yes') {
            return false;
        }

        $email = sanitize_email(get_post_meta($booking_id, 'dg_booking_email', true));
        if (!$email) {
            return false;
        }

        $status = get_post_meta($booking_id, 'dg_booking_status', true);
        $paid = get_post_meta($booking_id, 'dg_booking_paid', true);
        if ($status === 'cancelled') {
            return false;
        }
        if ($paid !== 'yes' && !in_array($status, ['confirmed', 'airbnb', 'bookingcom'], true)) {
            return false;
        }

        $sent = self::send_checkin_instructions($booking_id, $email);
        if ($sent) {
            update_post_meta($booking_id, self::CHECKIN_EMAIL_META, 'yes');
            update_post_meta($booking_id, '_dg_acc_checkin_email_sent_at', current_time('mysql'));
        }

        return $sent;
    }

    public static function send_checkin_instructions($booking_id, $email = '') {
        $booking_id = (int) $booking_id;
        if ($email === '') {
            $email = sanitize_email(get_post_meta($booking_id, 'dg_booking_email', true));
        }
        if (!$email) {
            return false;
        }

        $name = get_post_meta($booking_id, 'dg_booking_name', true) ?: 'Guest';
        if (class_exists('DG_Email_Names')) {
            $name = DG_Email_Names::first_name($name, 'Guest');
        }
        $accommodation_name = get_post_meta($booking_id, 'dg_booking_accommodation_name', true) ?: 'your accommodation';
        $accommodation_id = (int) get_post_meta($booking_id, 'dg_booking_accommodation_id', true);
        $checkin = get_post_meta($booking_id, 'dg_booking_checkin', true);
        $checkout = get_post_meta($booking_id, 'dg_booking_checkout', true);
        $ref = get_post_meta($booking_id, 'dg_booking_ref', true);
        $guests = get_post_meta($booking_id, 'dg_booking_guests', true);
        $source = class_exists('DG_Email_Brand')
            ? DG_Email_Brand::booking_source_label_for($booking_id)
            : '';

        $checkin_data = class_exists('DG_Acc_Checkin')
            ? DG_Acc_Checkin::get_guest_checkin_details($accommodation_id)
            : [];

        $subject = apply_filters(
            'dg_acc_checkin_email_subject',
            '🏡 Check-in instructions — ' . $accommodation_name,
            $booking_id,
            $accommodation_id
        );

        $html = self::build_checkin_email_html([
            'name' => $name,
            'email' => $email,
            'accommodation' => $accommodation_name,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $guests,
            'ref' => $ref,
            'source' => $source,
            'checkin_data' => $checkin_data,
        ]);

        $headers = class_exists('DG_Email_Brand')
            ? array_merge(DG_Email_Brand::mail_headers(true), ['From: ' . self::guest_from_email()])
            : [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . self::guest_from_email(),
            ];

        $sent = wp_mail($email, $subject, $html, $headers);

        if ($sent && class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 'booking',
                'entity_id' => $booking_id,
                'activity_type' => 'email',
                'subject' => 'Check-in instructions sent',
                'content' => 'Sent to ' . $email,
            ]);
        }

        do_action('dg_acc_checkin_email_sent', $booking_id, $email, $sent);

        return $sent;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function build_checkin_email_html(array $data) {
        $name = esc_html(class_exists('DG_Email_Names') ? DG_Email_Names::first_name($data['name'] ?? 'Guest', 'Guest') : ($data['name'] ?? 'Guest'));
        $accommodation = esc_html($data['accommodation'] ?? '');
        $checkin = $data['checkin'] ?? '';
        $checkout = $data['checkout'] ?? '';
        $guests = esc_html($data['guests'] ?? '');
        $ref = esc_html($data['ref'] ?? '');
        $source = esc_html($data['source'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $details = is_array($data['checkin_data'] ?? null) ? $data['checkin_data'] : [];

        $checkin_fmt = $checkin ? esc_html(date('l, j F Y', strtotime($checkin))) : '';
        $checkout_fmt = $checkout ? esc_html(date('l, j F Y', strtotime($checkout))) : '';
        $checkin_url = !empty($details['checkin_url']) ? esc_url($details['checkin_url']) : '';
        $checkin_label = !empty($details['checkin_page_label'])
            ? esc_html($details['checkin_page_label'])
            : $accommodation;
        $instructions = !empty($details['instructions']) ? wp_kses_post($details['instructions']) : '';
        $wifi = !empty($details['wifi_password']) ? esc_html($details['wifi_password']) : '';
        $checkin_time = !empty($details['checkin_time']) ? esc_html($details['checkin_time']) : '';
        $checkout_time = !empty($details['checkout_time']) ? esc_html($details['checkout_time']) : '';
        $address = !empty($details['address']) ? esc_html($details['address']) : '';

        $highlight = 'background:#FCF9F5;padding:18px;border-radius:12px;margin:16px 0;border-left:4px solid #B9A48A;';
        $cta = 'display:inline-block;margin:16px 0;padding:12px 22px;background:#B9A48A;color:#fff;text-decoration:none;border-radius:999px;font-weight:600;';

        $inner = '<h2 style="color:#1C2B2A;border-bottom:2px solid #B9A48A;padding-bottom:10px;margin:0 0 16px;">Your check-in details</h2>'
            . '<p style="margin:0 0 12px;line-height:1.6;">Hi <strong>' . $name . '</strong>,</p>'
            . '<p style="margin:0 0 12px;line-height:1.6;">Your payment is confirmed — we can\'t wait to welcome you to <strong>' . $accommodation . '</strong>.</p>'
            . '<div style="' . $highlight . '">';
        if ($ref) {
            $inner .= '<p style="margin:0 0 8px;"><strong>Booking reference:</strong> ' . $ref . '</p>';
        }
        if ($source) {
            $inner .= '<p style="margin:0 0 8px;"><strong>Source:</strong> ' . $source . '</p>';
        }
        if ($checkin_fmt) {
            $inner .= '<p style="margin:0 0 8px;"><strong>Check-in:</strong> ' . $checkin_fmt . ($checkin_time ? ' from ' . $checkin_time : '') . '</p>';
        }
        if ($checkout_fmt) {
            $inner .= '<p style="margin:0 0 8px;"><strong>Check-out:</strong> ' . $checkout_fmt . ($checkout_time ? ' by ' . $checkout_time : '') . '</p>';
        }
        if ($guests) {
            $inner .= '<p style="margin:0 0 8px;"><strong>Guests:</strong> ' . $guests . '</p>';
        }
        if ($address) {
            $inner .= '<p style="margin:0;"><strong>Address:</strong> ' . $address . '</p>';
        }
        $inner .= '</div>';

        if ($instructions) {
            $inner .= '<h3 style="color:#1C2B2A;margin:16px 0 8px;">Arrival instructions</h3>'
                . '<div style="line-height:1.6;margin:0 0 16px;">' . $instructions . '</div>';
        }

        if ($wifi) {
            $inner .= '<div style="' . $highlight . '"><p style="margin:0;"><strong>Wi‑Fi password:</strong> ' . $wifi . '</p></div>';
        }

        if ($checkin_url) {
            $inner .= '<div style="' . $highlight . '">'
                . '<p style="margin:0 0 8px;"><strong>Your check-in guide:</strong> Everything you need for ' . $checkin_label . ' — directions, access, and Wi‑Fi.</p>'
                . '<p style="margin:0 0 8px;"><a href="' . $checkin_url . '" style="' . $cta . '">Open ' . $checkin_label . ' check-in page</a></p>'
                . '<p style="margin:0;color:#6B7A78;font-size:14px;">Save this link on your phone — ' . esc_html(str_replace(home_url(), '', $checkin_url)) . '</p>'
                . '</div>';
        }

        $portal_url = class_exists('DG_Site_Portal_Guest')
            ? DG_Site_Portal_Guest::portal_url_for_email($email)
            : '';
        if ($portal_url) {
            $inner .= '<div style="' . $highlight . '">'
                . '<p style="margin:0 0 8px;"><strong>Guest Portal:</strong> View all your bookings and check-in details anytime.</p>'
                . '<p style="margin:0;"><a href="' . esc_url($portal_url) . '" style="' . $cta . '">Open Guest Portal</a></p>'
                . '</div>';
        }

        $inner .= '<p style="margin:16px 0 12px;line-height:1.6;">If you have any questions before you arrive, reply to this email or call us on <strong>0415 257 839</strong>.</p>'
            . '<p style="margin:0;line-height:1.6;">Warm regards,<br><strong>Currumbin Valley Hideaway</strong></p>';

        if (class_exists('DG_Email_Brand')) {
            return DG_Email_Brand::wrap($inner, [
                'theme' => 'cvh',
                'footer_note' => 'Currumbin Valley Hideaway — Gold Coast hinterland stays',
            ]);
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;color:#2F2F2F;background:#F7F4EE;padding:24px;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;padding:28px;border-radius:16px;border:1px solid #E0D6CC;">'
            . $inner . '</div></body></html>';
    }

    public static function handle_resend_admin() {
        $booking_id = (int) ($_GET['booking_id'] ?? 0);
        if ($booking_id <= 0 || !current_user_can('edit_post', $booking_id)) {
            wp_die('Unauthorized');
        }
        check_admin_referer('dg_resend_checkin_email_' . $booking_id);

        delete_post_meta($booking_id, self::CHECKIN_EMAIL_META);
        delete_post_meta($booking_id, '_dg_acc_checkin_email_sent_at');

        $sent = self::maybe_send_checkin_email($booking_id);

        $redirect = wp_get_referer() ?: admin_url('post.php?post=' . $booking_id . '&action=edit');
        wp_safe_redirect(add_query_arg([
            'dg_checkin_resent' => $sent ? '1' : '0',
        ], $redirect));
        exit;
    }
}
