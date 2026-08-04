<?php
/**
 * Finance reporting.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Fin_Reports {

    public static function summary() {
        global $wpdb;
        $table = DG_Fin_Pipeline::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['applications' => 0, 'pipeline_value' => 0, 'approved' => 0, 'settled' => 0];
        }
        return [
            'applications' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'"),
            'pipeline_value' => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM $table WHERE stage NOT IN ('settled','declined')"),
            'approved' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE stage = 'approved'"),
            'settled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE stage = 'settled'"),
        ];
    }
}
