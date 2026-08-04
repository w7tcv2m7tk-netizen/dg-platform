<?php
/**
 * Delayed automation step queue.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Automation_Pro_Queue {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_automation_queue';
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
            automation_id bigint(20) NOT NULL,
            step_index int(11) NOT NULL DEFAULT 0,
            context longtext,
            run_at datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status_run_at (status, run_at),
            KEY automation_id (automation_id)
        ) $charset;");
    }

    /** @param array<string,mixed> $context */
    public static function enqueue($automation_id, $step_index, array $context, $run_at) {
        self::ensure_table();
        global $wpdb;
        $wpdb->insert(self::table(), [
            'automation_id' => (int) $automation_id,
            'step_index' => (int) $step_index,
            'context' => wp_json_encode($context),
            'run_at' => gmdate('Y-m-d H:i:s', strtotime($run_at)),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function process_due() {
        self::ensure_table();
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'pending' AND run_at <= %s ORDER BY run_at ASC LIMIT 20",
            $now
        ));

        foreach ($rows as $row) {
            $automation = DG_Automation::get($row->automation_id);
            $context = json_decode($row->context, true) ?: [];
            if (!$automation || empty($automation->steps[$row->step_index])) {
                $wpdb->update($table, [
                    'status' => 'failed',
                    'error_message' => 'Automation or step missing',
                    'processed_at' => current_time('mysql'),
                ], ['id' => $row->id]);
                continue;
            }

            $result = DG_Automation_Pro_Runner::execute_step($automation->steps[$row->step_index], $context, $automation);
            $wpdb->update($table, [
                'status' => is_wp_error($result) ? 'failed' : 'completed',
                'error_message' => is_wp_error($result) ? $result->get_error_message() : null,
                'processed_at' => current_time('mysql'),
            ], ['id' => $row->id]);

            if (!is_wp_error($result)) {
                DG_Automation_Pro_Runner::run_from_step($automation, (int) $row->step_index + 1, $context);
            }
        }
    }

    public static function pending_count() {
        self::ensure_table();
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table() . " WHERE status = 'pending'");
    }
}
