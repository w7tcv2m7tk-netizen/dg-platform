<?php
/**
 * OTA sync, blocked dates, and calendar AJAX.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Ota {

    public static function init() {
        add_action('wp_ajax_dg_ota_sync', [__CLASS__, 'ajax_ota_sync']);
        add_action('wp_ajax_dg_airbnb_sync', [__CLASS__, 'ajax_ota_sync']);
        add_action('wp_ajax_dg_refresh_calendar', [__CLASS__, 'ajax_refresh_calendar']);
        add_action('wp_ajax_nopriv_dg_refresh_calendar', [__CLASS__, 'ajax_refresh_calendar']);
        add_action('wp', [__CLASS__, 'schedule_ota_sync']);
        add_action('dg_hourly_ota_sync', [__CLASS__, 'run_hourly_ota_sync']);
        add_action('admin_init', [__CLASS__, 'auto_sync_on_admin_load']);
        add_action('admin_init', [__CLASS__, 'add_force_sync_buttons']);
    }

    public static function rebuild_blocked_dates($accommodation_id) {
        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'meta_query' => [['key' => 'dg_booking_accommodation_id', 'value' => $accommodation_id, 'compare' => '=']],
        ]);

        $blocked_ranges = [];
        foreach ($bookings as $b) {
            $checkin = get_post_meta($b->ID, 'dg_booking_checkin', true);
            $checkout = get_post_meta($b->ID, 'dg_booking_checkout', true);
            $status = get_post_meta($b->ID, 'dg_booking_status', true);
            if ($status !== 'cancelled' && $checkin && $checkout) {
                $blocked_ranges[] = $checkin . ' to ' . $checkout;
            }
        }
        $blocked_ranges = array_unique($blocked_ranges);
        sort($blocked_ranges);
        update_post_meta($accommodation_id, 'dg_blocked_dates', implode("\n", $blocked_ranges));

        return $blocked_ranges;
    }

    public static function ajax_ota_sync() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dg_calendar_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $accommodation_id = isset($_POST['accommodation_id']) ? intval($_POST['accommodation_id']) : 0;
        if (!$accommodation_id || get_post_type($accommodation_id) !== 'dg_accommodation') {
            wp_send_json_error('Invalid accommodation ID');
        }

        if (!current_user_can('edit_post', $accommodation_id)) {
            wp_send_json_error('Permission denied');
        }

        $source = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'all';
        if (!class_exists('DG_Acc_Ical_Import')) {
            self::rebuild_blocked_dates($accommodation_id);
            wp_send_json_success(['message' => 'Calendar updated from local bookings only.']);
        }

        $results = DG_Acc_Ical_Import::sync_accommodation($accommodation_id, $source);
        if (!empty($results['errors']) && $results['imported'] === 0 && $results['updated'] === 0 && empty($results['sources'])) {
            wp_send_json_error($results['message']);
        }

        wp_send_json_success($results);
    }

    public static function ajax_refresh_calendar() {
        if (!isset($_POST['accommodation_id']) || !isset($_POST['nonce']) ||
            !wp_verify_nonce($_POST['nonce'], 'dg_calendar_nonce')) {
            wp_send_json_error('Invalid request');
        }

        $accommodation_id = intval($_POST['accommodation_id']);
        if (class_exists('DG_Acc_Ical_Import') && current_user_can('edit_post', $accommodation_id)) {
            $airbnb = get_post_meta($accommodation_id, 'dg_ical_url', true);
            $bookingcom = get_post_meta($accommodation_id, 'dg_bookingcom_ical_url', true);
            if ($airbnb || $bookingcom) {
                DG_Acc_Ical_Import::sync_accommodation($accommodation_id, 'all');
            }
        }

        $blocked = self::rebuild_blocked_dates($accommodation_id);
        wp_send_json_success(['blocked_dates' => $blocked]);
    }

    public static function schedule_ota_sync() {
        if (!wp_next_scheduled('dg_hourly_ota_sync')) {
            wp_schedule_event(time(), 'hourly', 'dg_hourly_ota_sync');
        }
    }

    public static function run_hourly_ota_sync() {
        if (!class_exists('DG_Acc_Ical_Import')) {
            return;
        }

        $accommodations = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
        ]);

        foreach ($accommodations as $acc) {
            $airbnb = get_post_meta($acc->ID, 'dg_ical_url', true);
            $bookingcom = get_post_meta($acc->ID, 'dg_bookingcom_ical_url', true);
            if (!$airbnb && !$bookingcom) {
                continue;
            }
            DG_Acc_Ical_Import::sync_accommodation($acc->ID, 'all');
        }
    }

    public static function auto_sync_on_admin_load() {
        global $pagenow;
        if ($pagenow !== 'post.php' || !isset($_GET['post']) || get_post_type((int) $_GET['post']) !== 'dg_accommodation') {
            return;
        }

        if (!class_exists('DG_Acc_Ical_Import')) {
            return;
        }

        $post_id = intval($_GET['post']);
        $airbnb_url = get_post_meta($post_id, 'dg_ical_url', true);
        $bookingcom_url = get_post_meta($post_id, 'dg_bookingcom_ical_url', true);
        if (!$airbnb_url && !$bookingcom_url) {
            return;
        }

        $last_airbnb = get_post_meta($post_id, 'dg_ical_last_sync', true);
        $last_bookingcom = get_post_meta($post_id, 'dg_bookingcom_ical_last_sync', true);
        $stale_after = 6 * HOUR_IN_SECONDS;
        $needs_sync = false;

        if ($airbnb_url && (!$last_airbnb || (time() - strtotime($last_airbnb)) > $stale_after)) {
            $needs_sync = true;
        }
        if ($bookingcom_url && (!$last_bookingcom || (time() - strtotime($last_bookingcom)) > $stale_after)) {
            $needs_sync = true;
        }

        if ($needs_sync) {
            DG_Acc_Ical_Import::sync_accommodation($post_id, 'all');
        }
    }

    public static function add_force_sync_buttons() {
        // Sync button is in Booking & OTA Settings meta box — avoid duplicate UI on edit screen.
        return;
    }
}
