<?php
if (!defined('ABSPATH')) exit;

class DG_Com_Reports {
    public static function summary() {
        global $wpdb;
        $t = DG_Com_Pipeline::table();
        return [
            'listings' => class_exists('DG_Com_Listings') ? DG_Com_Listings::count_active() : 0,
            'tenancies' => ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE status = 'active'") : 0,
            'active_leases' => ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE stage = 'active'") : 0,
            'rent_roll' => ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) ? (float) $wpdb->get_var("SELECT COALESCE(SUM(rent_pcm),0) FROM $t WHERE stage IN ('lease_signed','active')") : 0,
        ];
    }
}
