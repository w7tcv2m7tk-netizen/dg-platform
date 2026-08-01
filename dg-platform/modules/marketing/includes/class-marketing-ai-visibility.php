<?php
/**
 * AI Visibility tracking and history for DigitalGate clients.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_AI_Visibility {

    public static function history_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_ai_visibility_history';
    }

    public static function ensure_table() {
        global $wpdb;
        $table = self::history_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_id bigint(20) NOT NULL,
            organisation_id bigint(20) DEFAULT NULL,
            ai_score int(11) DEFAULT 0,
            google_score int(11) DEFAULT 0,
            website_score int(11) DEFAULT 0,
            overall_score int(11) DEFAULT 0,
            grade varchar(5) DEFAULT NULL,
            scan_source varchar(50) DEFAULT 'audit',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY organisation_id (organisation_id),
            KEY created_at (created_at)
        ) $charset;");
    }

    public static function record_scan($company_id, $scores, $source = 'audit') {
        self::ensure_table();
        global $wpdb;
        $org_id = class_exists('DG_Marketing_Clients') ? DG_Marketing_Clients::get_org_id($company_id) : null;
        $wpdb->insert(self::history_table(), [
            'company_id' => (int) $company_id,
            'organisation_id' => $org_id ? (int) $org_id : null,
            'ai_score' => (int) ($scores['ai_score'] ?? 0),
            'google_score' => (int) ($scores['google_score'] ?? 0),
            'website_score' => (int) ($scores['website_score'] ?? 0),
            'overall_score' => (int) ($scores['overall_score'] ?? 0),
            'grade' => sanitize_text_field($scores['grade'] ?? ''),
            'scan_source' => sanitize_text_field($source),
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function recent_scans($limit = 20) {
        self::ensure_table();
        global $wpdb;
        $history = self::history_table();
        $companies = DG_Marketing_Clients::companies_table();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, c.company_name, c.website
             FROM $history h
             LEFT JOIN $companies c ON c.id = h.company_id
             ORDER BY h.created_at DESC
             LIMIT %d",
            (int) $limit
        ));
    }

    public static function client_history($company_id, $limit = 12) {
        self::ensure_table();
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::history_table() . ' WHERE company_id = %d ORDER BY created_at DESC LIMIT %d',
            (int) $company_id,
            (int) $limit
        ));
    }

    public static function platform_averages() {
        self::ensure_table();
        global $wpdb;
        $table = self::history_table();
        $row = $wpdb->get_row(
            "SELECT AVG(ai_score) AS ai_avg, AVG(google_score) AS google_avg, AVG(website_score) AS web_avg, AVG(overall_score) AS overall_avg, COUNT(*) AS total
             FROM $table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        return [
            'ai_avg' => $row ? round((float) $row->ai_avg, 1) : 0,
            'google_avg' => $row ? round((float) $row->google_avg, 1) : 0,
            'web_avg' => $row ? round((float) $row->web_avg, 1) : 0,
            'overall_avg' => $row ? round((float) $row->overall_avg, 1) : 0,
            'scans' => $row ? (int) $row->total : 0,
        ];
    }
}

DG_Marketing_AI_Visibility::ensure_table();
