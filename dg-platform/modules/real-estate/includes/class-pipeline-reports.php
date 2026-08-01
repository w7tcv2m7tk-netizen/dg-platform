<?php
/**
 * Pipeline reporting for Roe Realty vendor/buyer funnels.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Pipeline_Reports {

    public static function vendor_stage_counts() {
        global $wpdb;
        if (!class_exists('DG_RE_Vendor_Leads')) {
            return [];
        }
        $pipeline = DG_RE_Vendor_Leads::pipeline_table();
        $leads = DG_RE_Vendor_Leads::leads_table();
        $rows = $wpdb->get_results(
            "SELECT p.stage, COUNT(l.id) AS total
             FROM $leads l
             INNER JOIN $pipeline p ON l.pipeline_id = p.id AND p.status = 'active'
             GROUP BY p.stage"
        );
        $counts = [];
        foreach (DG_RE_Vendor_Leads::stages() as $key => $label) {
            $counts[$key] = ['label' => $label, 'count' => 0];
        }
        foreach ($rows as $row) {
            if (isset($counts[$row->stage])) {
                $counts[$row->stage]['count'] = (int) $row->total;
            }
        }
        return $counts;
    }

    public static function vendor_source_counts() {
        global $wpdb;
        if (!class_exists('DG_RE_Vendor_Leads')) {
            return [];
        }
        $table = DG_RE_Vendor_Leads::leads_table();
        return $wpdb->get_results(
            "SELECT source, COUNT(*) AS total FROM $table GROUP BY source ORDER BY total DESC"
        );
    }

    public static function buyer_stage_counts() {
        global $wpdb;
        if (!class_exists('DG_RE_Buyer_Leads')) {
            return [];
        }
        $pipeline = DG_RE_Buyer_Leads::pipeline_table();
        $buyers = DG_RE_Buyer_Leads::buyers_table();
        $rows = $wpdb->get_results(
            "SELECT p.stage, COUNT(b.id) AS total
             FROM $buyers b
             INNER JOIN $pipeline p ON b.pipeline_id = p.id AND p.status = 'active'
             GROUP BY p.stage"
        );
        $counts = [];
        foreach (DG_RE_Buyer_Leads::stages() as $key => $label) {
            $counts[$key] = ['label' => $label, 'count' => 0];
        }
        foreach ($rows as $row) {
            if (isset($counts[$row->stage])) {
                $counts[$row->stage]['count'] = (int) $row->total;
            }
        }
        return $counts;
    }

    public static function bookings_this_month() {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_crm_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE MONTH(booking_date) = MONTH(CURRENT_DATE()) AND YEAR(booking_date) = YEAR(CURRENT_DATE())"
        );
    }

    public static function property_reports_this_month() {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_realty_leads';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE MONTH(submitted_at) = MONTH(CURRENT_DATE()) AND YEAR(submitted_at) = YEAR(CURRENT_DATE())"
        );
    }

    public static function vendor_conversion_summary() {
        global $wpdb;
        if (!class_exists('DG_RE_Vendor_Leads')) {
            return ['total' => 0, 'appraisal_plus' => 0, 'rate' => 0];
        }
        $pipeline = DG_RE_Vendor_Leads::pipeline_table();
        $leads = DG_RE_Vendor_Leads::leads_table();
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $leads");
        $advanced = (int) $wpdb->get_var(
            "SELECT COUNT(l.id) FROM $leads l
             INNER JOIN $pipeline p ON l.pipeline_id = p.id
             WHERE p.stage IN ('appraisal','listing','sale','settlement','past_client')"
        );
        return [
            'total' => $total,
            'appraisal_plus' => $advanced,
            'rate' => $total > 0 ? round(($advanced / $total) * 100, 1) : 0,
        ];
    }

    public static function recent_activity_summary($days = 30) {
        global $wpdb;
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $out = [
            'vendor_leads' => 0,
            'buyer_leads' => 0,
            'bookings' => 0,
            'property_reports' => 0,
        ];
        if (class_exists('DG_RE_Vendor_Leads')) {
            $out['vendor_leads'] = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DG_RE_Vendor_Leads::leads_table() . ' WHERE created_at >= %s',
                $since
            ));
        }
        if (class_exists('DG_RE_Buyer_Leads')) {
            $out['buyer_leads'] = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DG_RE_Buyer_Leads::buyers_table() . ' WHERE created_at >= %s',
                $since
            ));
        }
        $bookings = $wpdb->prefix . 'roe_crm_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings'") === $bookings) {
            $out['bookings'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings WHERE created_at >= %s",
                $since
            ));
        }
        $reports = $wpdb->prefix . 'roe_realty_leads';
        if ($wpdb->get_var("SHOW TABLES LIKE '$reports'") === $reports) {
            $out['property_reports'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $reports WHERE submitted_at >= %s",
                $since
            ));
        }
        return $out;
    }
}
