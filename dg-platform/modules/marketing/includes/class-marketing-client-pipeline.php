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
        $columns = [];
        foreach (self::stages() as $key => $label) {
            $columns[$key] = ['label' => $label, 'clients' => []];
        }
        $rows = DG_Marketing_Clients::list_clients(['limit' => 200]);
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
        $stages = array_keys(self::stages());
        if (!in_array($new_status, $stages, true)) {
            return false;
        }
        $updated = DG_Marketing_Clients::update_status((int) $company_id, $new_status);
        if ($updated) {
            if (class_exists('DG_Permissions')) {
                DG_Permissions::log_audit('marketing_client_stage', 'organisation', DG_Marketing_Clients::get_org_id($company_id), null, $new_status);
            }
        }
        return (bool) $updated;
    }
}
