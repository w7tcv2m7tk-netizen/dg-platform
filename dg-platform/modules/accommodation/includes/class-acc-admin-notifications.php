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
        $source = class_exists('DG_Email_Brand')
            ? DG_Email_Brand::booking_source_label_for($booking_id)
            : (string) get_post_meta($booking_id, 'dg_booking_source', true);

        $acc_id = (int) get_post_meta($booking_id, 'dg_booking_accommodation_id', true);
        $checkin_url = class_exists('DG_Acc_Checkin') ? DG_Acc_Checkin::checkin_url_for_property($acc_id) : '';
        $cleaning_url = class_exists('DG_Acc_Cleaning') ? DG_Acc_Cleaning::cleaning_url_for_property($acc_id) : '';

        $subject = '✅ Booking confirmed: ' . $name . ' — ' . $accommodation;
        $rows = [
            'Guest' => $name,
            'Email' => $email,
            'Property' => $accommodation,
            'Source' => $source,
            'Check-in' => $checkin,
            'Check-out' => $checkout,
            'Total' => '$' . number_format($total, 2),
            'Reference' => $ref,
        ];
        $body = self::mail_body('New confirmed booking', $rows, admin_url('post.php?post=' . (int) $booking_id . '&action=edit'), 'View booking in admin');
        if ($checkin_url) {
            $body = str_replace('</table>', '</table><p><a href="' . esc_url($checkin_url) . '">Guest check-in page</a></p>', $body);
        }
        if ($cleaning_url) {
            $body = str_replace('</table>', '</table><p><a href="' . esc_url($cleaning_url) . '">Cleaning checklist</a></p>', $body);
        }

        wp_mail(self::admin_email(), $subject, $body, self::mail_headers());
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
            $source = class_exists('DG_Email_Brand')
                ? DG_Email_Brand::booking_source_label_for($b->ID)
                : (string) get_post_meta($b->ID, 'dg_booking_source', true);
            $acc_id = (int) get_post_meta($b->ID, 'dg_booking_accommodation_id', true);
            $checkin_url = class_exists('DG_Acc_Checkin') ? DG_Acc_Checkin::checkin_url_for_property($acc_id) : '';
            $cleaning_url = class_exists('DG_Acc_Cleaning') ? DG_Acc_Cleaning::cleaning_url_for_property($acc_id) : '';

            $subject = '📅 Check-in tomorrow: ' . $name . ' — ' . $accommodation;
            $rows = [
                'Guest' => $name,
                'Property' => $accommodation,
                'Source' => $source,
                'Date' => $tomorrow,
            ];
            $body = self::mail_body('Check-in reminder', $rows, admin_url('post.php?post=' . $b->ID . '&action=edit'), 'View booking');
            if ($checkin_url) {
                $body = str_replace('</table>', '</table><p><a href="' . esc_url($checkin_url) . '">Guest check-in page</a></p>', $body);
            }
            if ($cleaning_url) {
                $body = str_replace('</table>', '</table><p><a href="' . esc_url($cleaning_url) . '">Cleaning checklist</a></p>', $body);
            }

            wp_mail(self::admin_email(), $subject, $body, self::mail_headers());
            update_post_meta($b->ID, '_dg_acc_reminder_sent', $tomorrow);
        }
    }

    private static function mail_body($title, array $rows, $cta_url = '', $cta_label = '') {
        $inner = '<h2 style="color:#1C2B2A;margin:0 0 16px;">' . esc_html($title) . '</h2><table style="width:100%;border-collapse:collapse;">';
        foreach ($rows as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $inner .= '<tr><td style="padding:6px 0;color:#6B7A78;width:140px;vertical-align:top;">'
                . esc_html((string) $label) . '</td><td style="padding:6px 0;color:#2F2F2F;">'
                . esc_html((string) $value) . '</td></tr>';
        }
        $inner .= '</table>';
        if ($cta_url && $cta_label && class_exists('DG_Email_Brand')) {
            $inner .= DG_Email_Brand::cta($cta_url, $cta_label, 'cvh');
        } elseif ($cta_url && $cta_label) {
            $inner .= '<p><a href="' . esc_url($cta_url) . '">' . esc_html($cta_label) . '</a></p>';
        }
        return class_exists('DG_Email_Brand')
            ? DG_Email_Brand::wrap($inner, ['theme' => 'cvh', 'site_label' => 'Currumbin Valley Hideaway'])
            : $inner;
    }

    private static function mail_headers() {
        return class_exists('DG_Email_Brand')
            ? DG_Email_Brand::mail_headers(true)
            : ['Content-Type: text/html; charset=UTF-8'];
    }
}