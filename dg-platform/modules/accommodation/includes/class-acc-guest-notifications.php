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
            'checkin_data' => $checkin_data,
        ]);

        $headers = [
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

        ob_start();
        ?>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; color: #2F2F2F; background: #F7F4EE; padding: 24px; margin: 0; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 16px; border: 1px solid #E0D6CC; }
                h2 { color: #1C2B2A; border-bottom: 2px solid #B9A48A; padding-bottom: 10px; margin-top: 0; }
                .highlight { background: #FCF9F5; padding: 18px; border-radius: 12px; margin: 16px 0; border-left: 4px solid #B9A48A; }
                .instructions { line-height: 1.6; margin: 16px 0; }
                .cta { display: inline-block; margin: 16px 0; padding: 12px 22px; background: #B9A48A; color: #fff !important; text-decoration: none; border-radius: 999px; font-weight: 600; }
                .muted { color: #6B7A78; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>🏡 Your check-in details</h2>
                <p>Hi <strong><?php echo $name; ?></strong>,</p>
                <p>Your payment is confirmed — we can't wait to welcome you to <strong><?php echo $accommodation; ?></strong>.</p>

                <div class="highlight">
                    <?php if ($ref) : ?><p><strong>Booking reference:</strong> <?php echo $ref; ?></p><?php endif; ?>
                    <?php if ($checkin_fmt) : ?><p><strong>Check-in:</strong> <?php echo $checkin_fmt; ?><?php echo $checkin_time ? ' from ' . $checkin_time : ''; ?></p><?php endif; ?>
                    <?php if ($checkout_fmt) : ?><p><strong>Check-out:</strong> <?php echo $checkout_fmt; ?><?php echo $checkout_time ? ' by ' . $checkout_time : ''; ?></p><?php endif; ?>
                    <?php if ($guests) : ?><p><strong>Guests:</strong> <?php echo $guests; ?></p><?php endif; ?>
                    <?php if ($address) : ?><p><strong>Address:</strong> <?php echo $address; ?></p><?php endif; ?>
                </div>

                <?php if ($instructions) : ?>
                    <h3 style="color:#1C2B2A;margin-bottom:8px;">Arrival instructions</h3>
                    <div class="instructions"><?php echo $instructions; ?></div>
                <?php endif; ?>

                <?php if ($wifi) : ?>
                    <div class="highlight">
                        <p><strong>Wi‑Fi password:</strong> <?php echo $wifi; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($checkin_url) : ?>
                    <div class="highlight">
                        <p><strong>Your check-in guide:</strong> Everything you need for <?php echo $checkin_label; ?> — directions, access, and Wi‑Fi.</p>
                        <p><a class="cta" href="<?php echo $checkin_url; ?>">Open <?php echo $checkin_label; ?> check-in page</a></p>
                        <p class="muted">Save this link on your phone — <?php echo esc_html(str_replace(home_url(), '', $checkin_url)); ?></p>
                    </div>
                <?php endif; ?>

                <?php
                $portal_url = class_exists('DG_Site_Portal_Guest')
                    ? DG_Site_Portal_Guest::portal_url_for_email($email)
                    : '';
                if ($portal_url) :
                ?>
                    <div class="highlight">
                        <p><strong>Guest Portal:</strong> View all your bookings and check-in details anytime.</p>
                        <p><a class="cta" href="<?php echo esc_url($portal_url); ?>">Open Guest Portal</a></p>
                    </div>
                <?php endif; ?>

                <p>If you have any questions before you arrive, reply to this email or call us on <strong>0415 257 839</strong>.</p>
                <p>Warm regards,<br><strong>Currumbin Valley Hideaway</strong></p>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
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
