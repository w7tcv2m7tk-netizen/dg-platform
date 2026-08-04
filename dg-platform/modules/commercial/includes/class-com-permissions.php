<?php
if (!defined('ABSPATH')) exit;

class DG_Com_Permissions {
    public static function menu_cap() {
        return current_user_can('dg_com_manage_listings') || current_user_can('manage_options') ? 'dg_com_manage_listings' : 'manage_options';
    }
    public static function can_view() {
        return current_user_can('dg_com_view_listings') || current_user_can('dg_com_manage_listings') || current_user_can('manage_options');
    }
    public static function can_manage() {
        return current_user_can('dg_com_manage_listings') || current_user_can('manage_options');
    }
}
