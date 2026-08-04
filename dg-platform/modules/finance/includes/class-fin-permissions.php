<?php
/**
 * Finance module permissions.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Fin_Permissions {

    public static function menu_cap() {
        return current_user_can('dg_fin_manage_loans') || current_user_can('manage_options')
            ? 'dg_fin_manage_loans'
            : 'manage_options';
    }

    public static function can_view() {
        return current_user_can('dg_fin_view_loans')
            || current_user_can('dg_fin_manage_loans')
            || current_user_can('manage_options');
    }

    public static function can_manage() {
        return current_user_can('dg_fin_manage_loans') || current_user_can('manage_options');
    }
}
