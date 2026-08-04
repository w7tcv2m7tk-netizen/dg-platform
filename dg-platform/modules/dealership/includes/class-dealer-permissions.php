<?php
if (!defined('ABSPATH')) exit;

class DG_Dealer_Permissions {
    public static function menu_cap() {
        return current_user_can('dg_dealer_manage_inventory') || current_user_can('manage_options') ? 'dg_dealer_manage_inventory' : 'manage_options';
    }
    public static function can_view() {
        return current_user_can('dg_dealer_view_inventory') || current_user_can('dg_dealer_manage_inventory') || current_user_can('manage_options');
    }
    public static function can_manage() {
        return current_user_can('dg_dealer_manage_inventory') || current_user_can('manage_options');
    }
}
