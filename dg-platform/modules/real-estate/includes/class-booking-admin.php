<?php
/**
 * Booking services, availability admin, and booking status management.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Booking_Admin {

    public static function init() {
        add_action('admin_post_dg_re_save_availability', [__CLASS__, 'save_availability']);
        add_action('admin_post_dg_re_update_booking_status', [__CLASS__, 'update_booking_status']);
    }

    public static function day_labels() {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }

    public static function get_availability_rows() {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_crm_availability';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        return $wpdb->get_results("SELECT * FROM $table ORDER BY day_of_week ASC");
    }

    public static function get_services() {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_crm_services';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    public static function save_availability() {
        if (!check_admin_referer('dg_re_save_availability') || !DG_RE_Permissions::can_view_appraisals()) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'roe_crm_availability';
        $days = self::day_labels();

        foreach ($days as $dow => $label) {
            $active = isset($_POST['active'][$dow]) ? 1 : 0;
            $start = sanitize_text_field(wp_unslash($_POST['start'][$dow] ?? '09:00'));
            $end = sanitize_text_field(wp_unslash($_POST['end'][$dow] ?? '17:00'));
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE day_of_week = %d", $dow));
            $row = [
                'day_of_week' => $dow,
                'start_time' => $start . ':00',
                'end_time' => $end . ':00',
                'is_active' => $active,
            ];
            if ($existing) {
                $wpdb->update($table, $row, ['id' => (int) $existing]);
            } else {
                $wpdb->insert($table, $row);
            }
        }

        wp_redirect(admin_url('admin.php?page=dg-re-booking-settings&saved=1'));
        exit;
    }

    public static function update_booking_status() {
        if (!check_admin_referer('dg_re_booking_status') || !DG_RE_Permissions::can_view_appraisals()) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $id = (int) ($_POST['booking_id'] ?? 0);
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
        $allowed = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (!$id || !in_array($status, $allowed, true)) {
            wp_redirect(admin_url('admin.php?page=dg-re-bookings'));
            exit;
        }

        $wpdb->update($wpdb->prefix . 'roe_crm_bookings', [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);

        wp_redirect(admin_url('admin.php?page=dg-re-bookings&updated=1'));
        exit;
    }
}

DG_RE_Booking_Admin::init();
