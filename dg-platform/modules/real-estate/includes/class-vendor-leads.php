<?php
/**
 * Vendor acquisition pipeline service for Roe Realty.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Vendor_Leads {

    const STAGE = 'vendor_lead';
    const RECORD_TYPE = 'vendor_acquisition';

    public static function leads_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_re_leads';
    }

    public static function pipeline_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_re_pipeline_records';
    }

    public static function stages() {
        return [
            'vendor_lead' => 'Vendor Lead',
            'appraisal' => 'Appraisal',
            'listing' => 'Listing',
            'sale' => 'Sale',
            'settlement' => 'Settlement',
            'past_client' => 'Past Client',
        ];
    }

    public static function statuses() {
        return [
            'new' => 'New',
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'appointment_booked' => 'Appointment Booked',
            'converted' => 'Converted',
            'lost' => 'Lost',
        ];
    }

    public static function create($data) {
        global $wpdb;

        $contact_id = DG_RE_Contacts::resolve_contact_id($data);
        if (!$contact_id) {
            return new WP_Error('missing_contact', 'Unable to create or find a contact for this lead.');
        }

        $property_address = sanitize_text_field($data['property_address'] ?? '');
        if (class_exists('DG_Address_Resolver') && $property_address !== '') {
            $resolved = DG_Address_Resolver::resolve($property_address);
            if (!is_wp_error($resolved)) {
                $property_address = $resolved['formatted'];
                $data['property_suburb'] = $resolved['suburb'];
                $data['property_state'] = $resolved['state'];
                $data['property_postcode'] = $resolved['postcode'];
                $data['property_address_metadata'] = $resolved['metadata'];
            }
        }
        $source = sanitize_text_field($data['source'] ?? 'website');
        $status = sanitize_text_field($data['status'] ?? 'new');
        $notes = sanitize_textarea_field($data['notes'] ?? '');

        $existing = self::find_recent_duplicate($contact_id, $property_address);
        if ($existing) {
            return (int) $existing;
        }

        $name_parts = DG_RE_Contacts::split_name($data['full_name'] ?? '');
        $title = trim(($name_parts['first_name'] ?? '') . ' ' . ($name_parts['last_name'] ?? ''));
        if ($property_address !== '') {
            $title = $property_address . ($title !== '' ? ' — ' . $title : '');
        }

        $wpdb->insert(self::pipeline_table(), [
            'record_type' => self::RECORD_TYPE,
            'stage' => self::STAGE,
            'contact_id' => $contact_id,
            'title' => $title,
            'status' => 'active',
            'metadata' => wp_json_encode(array_filter([
                'source' => $source,
                'property_address' => $property_address,
                'property_suburb' => $data['property_suburb'] ?? null,
                'property_state' => $data['property_state'] ?? null,
                'property_postcode' => $data['property_postcode'] ?? null,
                'property_address_metadata' => $data['property_address_metadata'] ?? null,
            ])),
        ]);
        $pipeline_id = (int) $wpdb->insert_id;

        $wpdb->insert(self::leads_table(), [
            'pipeline_id' => $pipeline_id,
            'contact_id' => $contact_id,
            'source' => $source,
            'status' => $status,
            'property_address' => $property_address,
            'notes' => $notes,
        ]);
        $lead_id = (int) $wpdb->insert_id;

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 're_lead',
                'entity_id' => $lead_id,
                'contact_id' => $contact_id,
                'activity_type' => 'lead',
                'subject' => 'Vendor lead created',
                'content' => $property_address,
                'metadata' => [
                    'source' => $source,
                    'status' => $status,
                    'pipeline_id' => $pipeline_id,
                ],
            ]);
        }

        if (class_exists('DG_Automation')) {
            DG_Automation::trigger('vendor_lead_created', [
                'entity_type' => 're_lead',
                'entity_id' => $lead_id,
                'contact_id' => $contact_id,
                'pipeline_id' => $pipeline_id,
                'source' => $source,
            ]);
        }

        do_action('dg_re_vendor_lead_created', $lead_id, $contact_id, $pipeline_id, $data);

        return $lead_id;
    }

    public static function list($args = []) {
        global $wpdb;

        $defaults = [
            'status' => null,
            'source' => null,
            'stage' => null,
            'assigned_to' => null,
            'limit' => 100,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $leads_table = self::leads_table();
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        $where = ['1=1'];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = 'l.status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['source'])) {
            $where[] = 'l.source = %s';
            $params[] = $args['source'];
        }
        if (!empty($args['stage'])) {
            $where[] = 'p.stage = %s';
            $params[] = $args['stage'];
        }
        if (!empty($args['assigned_to'])) {
            $where[] = 'l.assigned_to = %d';
            $params[] = (int) $args['assigned_to'];
        }

        $sql = "SELECT l.*, p.stage, p.id AS pipeline_record_id, c.first_name, c.last_name, c.email, c.phone
                FROM $leads_table l
                LEFT JOIN " . self::pipeline_table() . " p ON l.pipeline_id = p.id
                LEFT JOIN $contacts_table c ON l.contact_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY l.created_at DESC
                LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public static function list_for_kanban() {
        global $wpdb;

        $leads_table = self::leads_table();
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        $pipeline_table = self::pipeline_table();

        $rows = $wpdb->get_results(
            "SELECT l.*, p.stage, p.id AS pipeline_record_id,
                    c.first_name, c.last_name, c.email, c.phone
             FROM $leads_table l
             INNER JOIN $pipeline_table p ON l.pipeline_id = p.id AND p.status = 'active'
             LEFT JOIN $contacts_table c ON l.contact_id = c.id
             ORDER BY l.created_at DESC"
        );

        $grouped = [];
        foreach (self::stages() as $key => $label) {
            $grouped[$key] = [];
        }
        foreach ($rows as $row) {
            $stage = $row->stage ?? self::STAGE;
            if (!isset($grouped[$stage])) {
                $grouped[$stage] = [];
            }
            $grouped[$stage][] = $row;
        }
        return $grouped;
    }

    public static function advance_stage($lead_id, $stage) {
        global $wpdb;

        if (!isset(self::stages()[$stage])) {
            return false;
        }

        $lead = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::leads_table() . ' WHERE id = %d',
            (int) $lead_id
        ));
        if (!$lead) {
            return false;
        }

        $updated = $wpdb->update(self::pipeline_table(), [
            'stage' => $stage,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $lead->pipeline_id]);

        if ($updated && class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 're_lead',
                'entity_id' => (int) $lead_id,
                'contact_id' => (int) $lead->contact_id,
                'activity_type' => 'pipeline',
                'subject' => 'Vendor moved to ' . self::stages()[$stage],
                'content' => $lead->property_address,
                'metadata' => ['stage' => $stage],
            ]);
        }

        if ($stage === 'past_client') {
            $wpdb->update(self::pipeline_table(), ['status' => 'closed'], ['id' => (int) $lead->pipeline_id]);
        }

        return (bool) $updated;
    }

    public static function count($status = null) {
        global $wpdb;
        $table = self::leads_table();
        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", $status));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    public static function update_status($lead_id, $status) {
        global $wpdb;
        if (!isset(self::statuses()[$status])) {
            return false;
        }
        return (bool) $wpdb->update(self::leads_table(), [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $lead_id]);
    }

    public static function assign($lead_id, $user_id) {
        global $wpdb;
        $user_id = $user_id ? (int) $user_id : null;
        return (bool) $wpdb->update(self::leads_table(), [
            'assigned_to' => $user_id,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $lead_id]);
    }

    public static function update_notes($lead_id, $notes) {
        global $wpdb;
        return (bool) $wpdb->update(self::leads_table(), [
            'notes' => sanitize_textarea_field($notes),
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $lead_id]);
    }

    public static function get($lead_id) {
        global $wpdb;
        $leads_table = self::leads_table();
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, p.stage, p.id AS pipeline_record_id, p.metadata AS pipeline_metadata,
                    c.first_name, c.last_name, c.email, c.phone, c.id AS dg_contact_id
             FROM $leads_table l
             LEFT JOIN " . self::pipeline_table() . " p ON l.pipeline_id = p.id
             LEFT JOIN $contacts_table c ON l.contact_id = c.id
             WHERE l.id = %d",
            (int) $lead_id
        ));
    }

    public static function delete($lead_id) {
        global $wpdb;
        $lead_id = (int) $lead_id;
        $lead = self::get($lead_id);
        if (!$lead) {
            return false;
        }
        if (!empty($lead->pipeline_id)) {
            $wpdb->delete(self::pipeline_table(), ['id' => (int) $lead->pipeline_id], ['%d']);
        }
        return (bool) $wpdb->delete(self::leads_table(), ['id' => $lead_id], ['%d']);
    }

    private static function find_recent_duplicate($contact_id, $property_address) {
        global $wpdb;
        if ($property_address === '') {
            return null;
        }

        return $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::leads_table() . '
             WHERE contact_id = %d AND property_address = %s
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY id DESC LIMIT 1',
            $contact_id,
            $property_address
        ));
    }
}
