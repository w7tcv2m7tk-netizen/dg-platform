<?php
/**
 * Agency client pipeline stages (status-based CRM).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Client_Pipeline {

    public static function stages() {
        return [
            'lead' => 'New Lead',
            'audit_sent' => 'Audit Sent',
            'engaged' => 'Engaged',
            'client' => 'Active Client',
            'past_client' => 'Past Client',
            'lost' => 'Lost',
        ];
    }

    public static function list_for_kanban() {
        global $wpdb;
        $table = DG_Marketing_Clients::companies_table();
        $columns = [];
        foreach (self::stages() as $key => $label) {
            $columns[$key] = ['label' => $label, 'clients' => []];
        }
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return $columns;
        }
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY updated_at DESC, created_at DESC LIMIT 200");
        foreach ($rows as $row) {
            $status = $row->status ?: 'lead';
            if (!isset($columns[$status])) {
                $columns[$status] = ['label' => ucwords(str_replace('_', ' ', $status)), 'clients' => []];
            }
            $columns[$status]['clients'][] = $row;
        }
        return $columns;
    }

    public static function advance_stage($company_id, $new_status) {
        global $wpdb;
        $stages = array_keys(self::stages());
        if (!in_array($new_status, $stages, true)) {
            return false;
        }
        $table = DG_Marketing_Clients::companies_table();
        $updated = $wpdb->update($table, [
            'status' => $new_status,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $company_id]);
        if ($updated !== false) {
            DG_Marketing_Clients::sync_company((int) $company_id);
            if (class_exists('DG_Permissions')) {
                DG_Permissions::log_audit('marketing_client_stage', 'organisation', DG_Marketing_Clients::get_org_id($company_id), null, $new_status);
            }
        }
        return $updated !== false;
    }
}
