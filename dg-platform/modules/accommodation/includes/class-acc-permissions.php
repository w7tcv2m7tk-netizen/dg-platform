<?php
/**
 * Accommodation module permissions.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Permissions {

    public static function menu_cap_bookings() {
        return current_user_can('dg_acc_manage_bookings') || current_user_can('manage_options')
            ? 'dg_acc_manage_bookings'
            : 'manage_options';
    }

    public static function menu_cap_guests() {
        return current_user_can('dg_acc_manage_guests') || current_user_can('manage_options')
            ? 'dg_acc_manage_guests'
            : 'manage_options';
    }

    public static function can_view_bookings() {
        return current_user_can('dg_acc_view_bookings') || current_user_can('dg_acc_manage_bookings') || current_user_can('manage_options');
    }

    public static function can_manage_bookings() {
        return current_user_can('dg_acc_manage_bookings') || current_user_can('manage_options');
    }

    public static function can_view_guests() {
        return current_user_can('dg_acc_view_guests') || current_user_can('dg_acc_manage_guests') || current_user_can('manage_options');
    }
}
