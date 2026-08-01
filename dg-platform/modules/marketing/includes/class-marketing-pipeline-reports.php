<?php
/**
 * Pipeline reporting for DigitalGate agency client CRM.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Pipeline_Reports {

    public static function companies_table() {
        return DG_Marketing_Clients::companies_table();
    }

    public static function status_counts() {
        global $wpdb;
        $table = self::companies_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        $counts = [];
        foreach (DG_Marketing_Client_Pipeline::stages() as $key => $label) {
            $counts[$key] = ['label' => $label, 'count' => 0];
        }
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM $table GROUP BY status");
        foreach ($rows as $row) {
            $status = $row->status ?: 'lead';
            if (!isset($counts[$status])) {
                $counts[$status] = ['label' => ucwords(str_replace('_', ' ', $status)), 'count' => 0];
            }
            $counts[$status]['count'] = (int) $row->total;
        }
        return $counts;
    }

    public static function source_counts() {
        global $wpdb;
        $table = self::companies_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        return $wpdb->get_results(
            "SELECT source, COUNT(*) AS total FROM $table GROUP BY source ORDER BY total DESC"
        );
    }

    public static function audits_this_month() {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_platform_audits';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE MONTH(audit_date) = MONTH(CURRENT_DATE()) AND YEAR(audit_date) = YEAR(CURRENT_DATE())"
        );
    }

    public static function voice_leads_this_month() {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_platform_voice_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        );
    }

    public static function client_conversion_summary() {
        global $wpdb;
        $table = self::companies_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['total' => 0, 'engaged_plus' => 0, 'rate' => 0];
        }
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $engaged = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status IN ('engaged','client','active')"
        );
        return [
            'total' => $total,
            'engaged_plus' => $engaged,
            'rate' => $total > 0 ? round(($engaged / $total) * 100, 1) : 0,
        ];
    }

    public static function recent_activity_summary($days = 30) {
        global $wpdb;
        $since = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));
        $companies = self::companies_table();
        $audits = $wpdb->prefix . 'dg_platform_audits';
        $voice = $wpdb->prefix . 'dg_platform_voice_logs';
        $automation = $wpdb->prefix . 'dg_automation_audit_emails';

        $out = [
            'new_clients' => 0,
            'audits' => 0,
            'voice_leads' => 0,
            'automation_sent' => 0,
        ];

        if ($wpdb->get_var("SHOW TABLES LIKE '$companies'") === $companies) {
            $out['new_clients'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $companies WHERE created_at >= %s",
                $since
            ));
        }
        if ($wpdb->get_var("SHOW TABLES LIKE '$audits'") === $audits) {
            $out['audits'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $audits WHERE audit_date >= %s",
                $since
            ));
        }
        if ($wpdb->get_var("SHOW TABLES LIKE '$voice'") === $voice) {
            $out['voice_leads'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $voice WHERE created_at >= %s",
                $since
            ));
        }
        if ($wpdb->get_var("SHOW TABLES LIKE '$automation'") === $automation) {
            $out['automation_sent'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $automation WHERE status = 'sent' AND created_at >= %s",
                $since
            ));
        }
        return $out;
    }

    public static function summary($days = 30) {
        $activity = self::recent_activity_summary($days);
        $conversion = self::client_conversion_summary();
        return [
            'site' => home_url(),
            'site_profile' => class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'DG Platform',
            'generated_at' => current_time('mysql'),
            'period_days' => $days,
            'clients_total' => $conversion['total'],
            'clients_leads' => self::count_by_status('lead'),
            'clients_active' => self::count_by_status('active') + self::count_by_status('client'),
            'audits_total' => self::table_count('dg_platform_audits'),
            'audits_this_period' => $activity['audits'],
            'voice_leads_total' => self::table_count('dg_platform_voice_logs'),
            'voice_leads_this_period' => $activity['voice_leads'],
            'voice_qualified_this_period' => self::qualified_voice_count($days),
            'automation_pending' => self::automation_count('pending'),
            'automation_sent' => $activity['automation_sent'],
            'clients_by_source' => array_map(function ($row) {
                return ['source' => $row->source, 'total' => (string) $row->total];
            }, self::source_counts()),
            'status_pipeline' => self::status_counts(),
            'conversion_rate' => $conversion['rate'],
        ];
    }

    private static function count_by_status($status) {
        global $wpdb;
        $table = self::companies_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", $status));
    }

    private static function table_count($suffix) {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    private static function qualified_voice_count($days) {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_platform_voice_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        $since = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE is_qualified = 1 AND created_at >= %s",
            $since
        ));
    }

    private static function automation_count($status) {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_automation_audit_emails';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", $status));
    }
}
