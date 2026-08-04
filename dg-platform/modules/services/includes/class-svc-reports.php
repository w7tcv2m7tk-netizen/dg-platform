<?php
if (!defined('ABSPATH')) exit;

class DG_Svc_Reports {
    public static function summary() {
        global $wpdb;
        $t = DG_Svc_Pipeline::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t) {
            return ['jobs' => 0, 'scheduled' => 0, 'quoted_value' => 0, 'complete' => 0];
        }
        return [
            'jobs' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE status = 'active'"),
            'scheduled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE stage = 'scheduled'"),
            'quoted_value' => (float) $wpdb->get_var("SELECT COALESCE(SUM(quoted_amount),0) FROM $t WHERE stage NOT IN ('complete','cancelled')"),
            'complete' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE stage = 'complete'"),
        ];
    }
}
