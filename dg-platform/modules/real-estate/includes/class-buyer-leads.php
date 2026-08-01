<?php
/**
 * Buyer acquisition pipeline service for Roe Realty.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Buyer_Leads {

    const STAGE = 'inquiry';
    const RECORD_TYPE = 'buyer_acquisition';

    public static function buyers_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_re_buyers';
    }

    public static function pipeline_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_re_pipeline_records';
    }

    public static function stages() {
        return [
            'inquiry' => 'Inquiry',
            'qualified' => 'Qualified',
            'viewing' => 'Viewing',
            'offer' => 'Offer',
            'purchased' => 'Purchased',
        ];
    }

    public static function statuses() {
        return [
            'new' => 'New',
            'contacted' => 'Contacted',
            'active' => 'Active',
            'under_offer' => 'Under Offer',
            'purchased' => 'Purchased',
            'lost' => 'Lost',
        ];
    }

    public static function create($data) {
        global $wpdb;

        $contact_id = DG_RE_Contacts::resolve_contact_id($data);
        if (!$contact_id) {
            return new WP_Error('missing_contact', 'Unable to create or find a contact for this enquiry.');
        }

        $property_address = sanitize_text_field($data['property_address'] ?? '');
        $property_id = (int) ($data['property_id'] ?? 0);
        $source = sanitize_text_field($data['source'] ?? 'property_enquiry');
        $status = sanitize_text_field($data['status'] ?? 'new');
        $message = sanitize_textarea_field($data['message'] ?? '');
        $property_url = esc_url_raw($data['property_url'] ?? '');

        $name_parts = DG_RE_Contacts::split_name($data['full_name'] ?? '');
        $title = trim(($name_parts['first_name'] ?? '') . ' ' . ($name_parts['last_name'] ?? ''));
        if ($property_address !== '') {
            $title = $property_address . ($title !== '' ? ' — ' . $title : '');
        }

        $wpdb->insert(self::pipeline_table(), [
            'record_type' => self::RECORD_TYPE,
            'stage' => self::STAGE,
            'contact_id' => $contact_id,
            'property_id' => $property_id ?: null,
            'title' => $title,
            'status' => 'active',
            'metadata' => wp_json_encode([
                'source' => $source,
                'property_address' => $property_address,
                'property_url' => $property_url,
            ]),
        ]);
        $pipeline_id = (int) $wpdb->insert_id;

        $requirements = $message;
        if ($property_url) {
            $requirements .= ($requirements ? "\n\n" : '') . 'Property URL: ' . $property_url;
        }

        $wpdb->insert(self::buyers_table(), [
            'pipeline_id' => $pipeline_id,
            'contact_id' => $contact_id,
            'requirements' => $requirements,
            'status' => $status === 'new' ? 'active' : $status,
        ]);
        $buyer_id = (int) $wpdb->insert_id;

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 're_buyer',
                'entity_id' => $buyer_id,
                'contact_id' => $contact_id,
                'activity_type' => 'lead',
                'subject' => 'Buyer enquiry received',
                'content' => $property_address,
                'metadata' => [
                    'source' => $source,
                    'property_id' => $property_id,
                    'pipeline_id' => $pipeline_id,
                ],
            ]);
        }

        if (class_exists('DG_Automation')) {
            DG_Automation::trigger('buyer_lead_created', [
                'entity_type' => 're_buyer',
                'entity_id' => $buyer_id,
                'contact_id' => $contact_id,
                'pipeline_id' => $pipeline_id,
                'source' => $source,
                'email' => DG_RE_Contacts::display_email($data['email'] ?? ''),
            ]);
        }

        do_action('dg_re_buyer_lead_created', $buyer_id, $contact_id, $pipeline_id, $data);

        return $buyer_id;
    }

    public static function list($args = []) {
        global $wpdb;

        $defaults = [
            'status' => null,
            'assigned_to' => null,
            'limit' => 100,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $buyers_table = self::buyers_table();
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        $where = ['1=1'];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = 'b.status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['assigned_to'])) {
            $where[] = 'b.assigned_to = %d';
            $params[] = (int) $args['assigned_to'];
        }

        $sql = "SELECT b.*, p.stage, p.property_id, p.metadata AS pipeline_metadata,
                       c.first_name, c.last_name, c.email, c.phone
                FROM $buyers_table b
                LEFT JOIN " . self::pipeline_table() . " p ON b.pipeline_id = p.id
                LEFT JOIN $contacts_table c ON b.contact_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY b.created_at DESC
                LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public static function list_for_kanban() {
        global $wpdb;

        $buyers_table = self::buyers_table();
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        $pipeline_table = self::pipeline_table();

        $rows = $wpdb->get_results(
            "SELECT b.*, p.id AS pipeline_record_id, p.stage,
                    c.first_name, c.last_name, c.email, c.phone, p.metadata AS pipeline_metadata
             FROM $buyers_table b
             INNER JOIN $pipeline_table p ON b.pipeline_id = p.id AND p.status = 'active'
             LEFT JOIN $contacts_table c ON b.contact_id = c.id
             ORDER BY b.created_at DESC"
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

    public static function advance_stage($buyer_id, $stage) {
        global $wpdb;

        if (!isset(self::stages()[$stage])) {
            return false;
        }

        $buyer = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::buyers_table() . ' WHERE id = %d',
            (int) $buyer_id
        ));
        if (!$buyer) {
            return false;
        }

        $updated = $wpdb->update(self::pipeline_table(), [
            'stage' => $stage,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $buyer->pipeline_id]);

        if ($updated && class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 're_buyer',
                'entity_id' => (int) $buyer_id,
                'contact_id' => (int) $buyer->contact_id,
                'activity_type' => 'pipeline',
                'subject' => 'Buyer moved to ' . self::stages()[$stage],
                'content' => '',
                'metadata' => ['stage' => $stage],
            ]);
        }

        return (bool) $updated;
    }

    public static function update_status($buyer_id, $status) {
        global $wpdb;
        if (!isset(self::statuses()[$status])) {
            return false;
        }
        $db_status = $status === 'new' ? 'active' : $status;
        return (bool) $wpdb->update(self::buyers_table(), [
            'status' => $db_status,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $buyer_id]);
    }

    public static function assign($buyer_id, $user_id) {
        global $wpdb;
        $user_id = $user_id ? (int) $user_id : null;
        return (bool) $wpdb->update(self::buyers_table(), [
            'assigned_to' => $user_id,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $buyer_id]);
    }

    public static function get($buyer_id) {
        global $wpdb;
        $buyers_table = self::buyers_table();
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, p.stage, p.id AS pipeline_record_id, p.property_id, p.metadata AS pipeline_metadata,
                    c.first_name, c.last_name, c.email, c.phone, c.id AS dg_contact_id
             FROM $buyers_table b
             LEFT JOIN " . self::pipeline_table() . " p ON b.pipeline_id = p.id
             LEFT JOIN $contacts_table c ON b.contact_id = c.id
             WHERE b.id = %d",
            (int) $buyer_id
        ));
    }

    public static function count($status = null) {
        global $wpdb;
        $table = self::buyers_table();
        if ($status) {
            $db_status = $status === 'new' ? 'active' : $status;
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", $db_status));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
}

function dg_re_process_property_enquiry($data) {
    $name = sanitize_text_field($data['enquiry_name'] ?? $data['full_name'] ?? '');
    $email = sanitize_email($data['enquiry_email'] ?? $data['email'] ?? '');
    $phone = sanitize_text_field($data['enquiry_phone'] ?? $data['phone'] ?? '');
    $message = sanitize_textarea_field($data['enquiry_message'] ?? $data['message'] ?? '');
    $property = sanitize_text_field($data['property_address'] ?? '');
    $property_url = esc_url_raw($data['property_url'] ?? '');
    $property_id = (int) ($data['property_id'] ?? 0);

    if ($name === '' || $email === '') {
        return [
            'success' => false,
            'message' => 'Name and email are required.',
        ];
    }

    $admin_to = apply_filters('dg_re_property_enquiry_admin_email', get_option('admin_email'));
    $subject = 'New Property Enquiry: ' . ($property ?: 'Unknown property');
    $body = "Name: $name\nEmail: $email\nPhone: $phone\nProperty: $property\nProperty URL: $property_url\n\nMessage:\n$message";
    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: Ben Roe | Roe Realty <ben@roerealty.com.au>',
        'Reply-To: ' . $name . ' <' . $email . '>',
    ];
    wp_mail($admin_to, $subject, $body, $headers);

    if (class_exists('DG_RE_Buyer_Leads')) {
        DG_RE_Buyer_Leads::create([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
            'property_address' => $property,
            'property_url' => $property_url,
            'property_id' => $property_id,
            'source' => 'property_enquiry',
            'status' => 'new',
        ]);
    }

    return [
        'success' => true,
        'message' => "Thank you for your enquiry! We'll be in touch shortly.",
    ];
}
