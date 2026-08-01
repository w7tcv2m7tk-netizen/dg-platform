<?php
/**
 * Admin email notifications for accommodation bookings.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Admin_Notifications {

    const REMINDER_CRON = 'dg_acc_checkin_reminders';

    public static function init() {
        add_action('dg_booking_confirmed', [__CLASS__, 'on_booking_confirmed'], 10, 1);
        add_action('save_post_dg_booking', [__CLASS__, 'maybe_fire_confirmed'], 15, 3);
        add_action('init', [__CLASS__, 'schedule_reminders']);
        add_action(self::REMINDER_CRON, [__CLASS__, 'send_checkin_reminders']);
    }

    public static function admin_email() {
        return apply_filters('dg_acc_admin_email', get_option('admin_email'));
    }

    public static function maybe_fire_confirmed($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        $status = get_post_meta($post_id, 'dg_booking_status', true);
        if (!in_array($status, ['confirmed', 'airbnb', 'bookingcom'], true)) {
            return;
        }
        $prev = get_post_meta($post_id, '_dg_acc_notified_confirmed', true);
        if ($prev === 'yes') {
            return;
        }
        update_post_meta($post_id, '_dg_acc_notified_confirmed', 'yes');
        do_action('dg_booking_confirmed', $post_id);
    }

    public static function on_booking_confirmed($booking_id) {
        $name = get_post_meta($booking_id, 'dg_booking_name', true) ?: 'Guest';
        $accommodation = get_post_meta($booking_id, 'dg_booking_accommodation_name', true) ?: 'Property';
        $checkin = get_post_meta($booking_id, 'dg_booking_checkin', true);
        $checkout = get_post_meta($booking_id, 'dg_booking_checkout', true);
        $total = (float) get_post_meta($booking_id, 'dg_booking_total', true);
        $ref = get_post_meta($booking_id, 'dg_booking_ref', true);
        $email = get_post_meta($booking_id, 'dg_booking_email', true);

        $subject = '✅ Booking confirmed: ' . $name . ' — ' . $accommodation;
        $body = '<html><body style="font-family:Arial,sans-serif;color:#1e293b;">'
            . '<h2>New confirmed booking</h2>'
            . '<p><strong>Guest:</strong> ' . esc_html($name) . '<br>'
            . '<strong>Email:</strong> ' . esc_html($email) . '<br>'
            . '<strong>Property:</strong> ' . esc_html($accommodation) . '<br>'
            . '<strong>Check-in:</strong> ' . esc_html($checkin) . '<br>'
            . '<strong>Check-out:</strong> ' . esc_html($checkout) . '<br>'
            . '<strong>Total:</strong> $' . number_format($total, 2) . '<br>'
            . '<strong>Reference:</strong> ' . esc_html($ref) . '</p>'
            . '<p><a href="' . esc_url(admin_url('post.php?post=' . (int) $booking_id . '&action=edit')) . '">View booking in admin</a></p>'
            . '</body></html>';

        wp_mail(self::admin_email(), $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    public static function schedule_reminders() {
        if (!wp_next_scheduled(self::REMINDER_CRON)) {
            wp_schedule_event(strtotime('tomorrow 8:00'), 'daily', self::REMINDER_CRON);
        }
    }

    public static function send_checkin_reminders() {
        $tomorrow = date('Y-m-d', strtotime('+1 day', current_time('timestamp')));
        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => 'dg_booking_checkin', 'value' => $tomorrow, 'compare' => '=', 'type' => 'DATE'],
                ['key' => 'dg_booking_status', 'value' => ['cancelled', 'completed'], 'compare' => 'NOT IN'],
            ],
        ]);

        foreach ($bookings as $b) {
            if (get_post_meta($b->ID, '_dg_acc_reminder_sent', true) === $tomorrow) {
                continue;
            }
            $name = get_post_meta($b->ID, 'dg_booking_name', true) ?: 'Guest';
            $accommodation = get_post_meta($b->ID, 'dg_booking_accommodation_name', true) ?: 'Property';
            $acc_id = (int) get_post_meta($b->ID, 'dg_booking_accommodation_id', true);
            $checkin_url = class_exists('DG_Acc_Checkin') ? DG_Acc_Checkin::checkin_url_for_property($acc_id) : '';

            $subject = '📅 Check-in tomorrow: ' . $name . ' — ' . $accommodation;
            $body = '<html><body style="font-family:Arial,sans-serif;">'
                . '<h2>Check-in reminder</h2>'
                . '<p><strong>Guest:</strong> ' . esc_html($name) . '<br>'
                . '<strong>Property:</strong> ' . esc_html($accommodation) . '<br>'
                . '<strong>Date:</strong> ' . esc_html($tomorrow) . '</p>';
            if ($checkin_url) {
                $body .= '<p><a href="' . esc_url($checkin_url) . '">Guest check-in link</a></p>';
            }
            $body .= '<p><a href="' . esc_url(admin_url('post.php?post=' . $b->ID . '&action=edit')) . '">View booking</a></p></body></html>';

            wp_mail(self::admin_email(), $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
            update_post_meta($b->ID, '_dg_acc_reminder_sent', $tomorrow);
        }
    }
}

DG_Acc_Admin_Notifications::init();
