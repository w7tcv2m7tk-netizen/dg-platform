<?php
/**
 * AI Visibility scan history storage.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Visibility_History {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_ai_visibility_scans';
    }

    public static function ensure_table() {
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_host varchar(191) NOT NULL,
            business_name varchar(200) NOT NULL,
            openai_score int(11) DEFAULT 0,
            gemini_score int(11) DEFAULT 0,
            technical_score int(11) DEFAULT 0,
            combined_score int(11) DEFAULT 0,
            grade varchar(5) DEFAULT NULL,
            openai_summary text,
            gemini_summary text,
            recommendations longtext,
            scan_source varchar(50) DEFAULT 'manual',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_host (site_host),
            KEY created_at (created_at)
        ) $charset;");
    }

    /** @param array<string,mixed> $data */
    public static function record(array $data) {
        self::ensure_table();
        global $wpdb;

        $combined = (int) round(
            ((int) ($data['openai_score'] ?? 0) + (int) ($data['gemini_score'] ?? 0) + (int) ($data['technical_score'] ?? 0)) / 3
        );

        $wpdb->insert(self::table(), [
            'site_host' => sanitize_text_field($data['site_host'] ?? ''),
            'business_name' => sanitize_text_field($data['business_name'] ?? ''),
            'openai_score' => (int) ($data['openai_score'] ?? 0),
            'gemini_score' => (int) ($data['gemini_score'] ?? 0),
            'technical_score' => (int) ($data['technical_score'] ?? 0),
            'combined_score' => $combined,
            'grade' => self::grade_for_score($combined),
            'openai_summary' => sanitize_textarea_field($data['openai_summary'] ?? ''),
            'gemini_summary' => sanitize_textarea_field($data['gemini_summary'] ?? ''),
            'recommendations' => wp_json_encode($data['recommendations'] ?? []),
            'scan_source' => sanitize_text_field($data['scan_source'] ?? 'manual'),
            'created_at' => current_time('mysql'),
        ]);

        $id = (int) $wpdb->insert_id;
        delete_transient('dg_onboarding_summary_v' . (defined('DG_PLATFORM_VERSION') ? DG_PLATFORM_VERSION : '1'));

        return $id;
    }

    public static function grade_for_score($score) {
        if ($score >= 80) {
            return 'A';
        }
        if ($score >= 65) {
            return 'B';
        }
        if ($score >= 50) {
            return 'C';
        }
        if ($score >= 35) {
            return 'D';
        }
        return 'F';
    }

    public static function recent($limit = 20) {
        self::ensure_table();
        global $wpdb;
        $host = class_exists('DG_Site_Profile') ? DG_Site_Profile::hostname() : parse_url(home_url(), PHP_URL_HOST);
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE site_host = %s ORDER BY created_at DESC LIMIT %d',
            $host,
            (int) $limit
        ));
    }

    public static function latest() {
        $rows = self::recent(1);
        return $rows ? $rows[0] : null;
    }

    /** @return array<string,float|int> */
    public static function averages($days = 90) {
        self::ensure_table();
        global $wpdb;
        $host = class_exists('DG_Site_Profile') ? DG_Site_Profile::hostname() : parse_url(home_url(), PHP_URL_HOST);
        $table = self::table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(openai_score) AS openai_avg, AVG(gemini_score) AS gemini_avg,
                    AVG(technical_score) AS technical_avg, AVG(combined_score) AS combined_avg, COUNT(*) AS total
             FROM $table WHERE site_host = %s AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $host,
            (int) $days
        ));
        return [
            'openai_avg' => $row ? round((float) $row->openai_avg, 1) : 0,
            'gemini_avg' => $row ? round((float) $row->gemini_avg, 1) : 0,
            'technical_avg' => $row ? round((float) $row->technical_avg, 1) : 0,
            'combined_avg' => $row ? round((float) $row->combined_avg, 1) : 0,
            'scans' => $row ? (int) $row->total : 0,
        ];
    }
}
