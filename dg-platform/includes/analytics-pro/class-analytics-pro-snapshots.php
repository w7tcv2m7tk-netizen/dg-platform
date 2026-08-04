<?php
/**
 * Daily metric snapshots for trend analysis.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Analytics_Pro_Snapshots {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_analytics_snapshots';
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
            snapshot_date date NOT NULL,
            metric_key varchar(100) NOT NULL,
            metric_value double NOT NULL DEFAULT 0,
            module varchar(50) DEFAULT 'core',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date_metric (snapshot_date, metric_key),
            KEY snapshot_date (snapshot_date),
            KEY metric_key (metric_key)
        ) $charset;");
    }

    public static function record_today(array $metrics) {
        self::ensure_table();
        global $wpdb;
        $date = current_time('Y-m-d');
        $table = self::table();

        foreach ($metrics as $key => $row) {
            $value = is_array($row) ? ($row['value'] ?? 0) : $row;
            $module = is_array($row) ? ($row['module'] ?? 'core') : 'core';
            $wpdb->replace($table, [
                'snapshot_date' => $date,
                'metric_key' => sanitize_key($key),
                'metric_value' => (float) $value,
                'module' => sanitize_text_field($module),
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    /** @return array<string,array{current:float,previous:float,delta:float}> */
    public static function trends($days = 30) {
        self::ensure_table();
        global $wpdb;
        $table = self::table();
        $today = current_time('Y-m-d');
        $past = date('Y-m-d', strtotime('-' . (int) $days . ' days'));

        $current = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_key, metric_value, module FROM $table WHERE snapshot_date = %s",
            $today
        ), OBJECT_K);

        if (!$current) {
            $latest_date = $wpdb->get_var("SELECT MAX(snapshot_date) FROM $table");
            if ($latest_date) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT metric_key, metric_value, module FROM $table WHERE snapshot_date = %s",
                    $latest_date
                ));
                $current = [];
                foreach ($rows as $row) {
                    $current[$row->metric_key] = $row;
                }
            }
        }

        $previous_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_key, metric_value FROM $table WHERE snapshot_date <= %s ORDER BY snapshot_date DESC",
            $past
        ));
        $previous = [];
        foreach ($previous_rows as $row) {
            if (!isset($previous[$row->metric_key])) {
                $previous[$row->metric_key] = (float) $row->metric_value;
            }
        }

        $trends = [];
        foreach ($current as $key => $row) {
            $cur = (float) $row->metric_value;
            $prev = $previous[$key] ?? $cur;
            $trends[$key] = [
                'label' => self::label_for($key),
                'module' => $row->module,
                'current' => $cur,
                'previous' => $prev,
                'delta' => $cur - $prev,
            ];
        }

        return $trends;
    }

    /** @return array<int,object> */
    public static function history($metric_key, $days = 30) {
        self::ensure_table();
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT snapshot_date, metric_value FROM ' . self::table() . '
             WHERE metric_key = %s AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             ORDER BY snapshot_date ASC',
            sanitize_key($metric_key),
            (int) $days
        ));
    }

    private static function label_for($key) {
        return ucwords(str_replace('_', ' ', $key));
    }
}
