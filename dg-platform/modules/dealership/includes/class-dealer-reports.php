<?php
if (!defined('ABSPATH')) exit;

class DG_Dealer_Reports {
    public static function summary() {
        global $wpdb;
        $t = DG_Dealer_Pipeline::table();
        $leads = ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE status = 'active'") : 0;
        return [
            'vehicles' => class_exists('DG_Dealer_Inventory') ? DG_Dealer_Inventory::count_available() : 0,
            'leads' => $leads,
            'test_drives' => ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE stage = 'test_drive'") : 0,
            'sold' => ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE stage = 'sold'") : 0,
        ];
    }
}
