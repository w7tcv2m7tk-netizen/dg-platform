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
        if (current_user_can('manage_options')) {
            return 'manage_options';
        }
        if (current_user_can('dg_acc_manage_bookings')) {
            return 'dg_acc_manage_bookings';
        }
        return 'dg_acc_view_bookings';
    }

    public static function menu_cap_guests() {
        if (current_user_can('manage_options')) {
            return 'manage_options';
        }
        if (current_user_can('dg_acc_manage_guests')) {
            return 'dg_acc_manage_guests';
        }
        return 'dg_acc_view_guests';
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
