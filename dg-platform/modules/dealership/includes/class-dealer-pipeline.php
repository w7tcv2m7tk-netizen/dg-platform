<?php
if (!defined('ABSPATH')) exit;

class DG_Dealer_Pipeline {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_dealer_leads';
    }

    public static function stages() {
        return [
            'inquiry' => 'Inquiry',
            'test_drive' => 'Test drive booked',
            'negotiation' => 'Negotiation',
            'finance' => 'Finance',
            'sold' => 'Sold',
            'lost' => 'Lost',
        ];
    }

    public static function interest_types() {
        return ['Test drive', 'Purchase', 'Trade-in', 'Finance'];
    }

    public static function create($data) {
        global $wpdb;
        $contact_id = self::resolve_contact($data);
        if (!$contact_id) return new WP_Error('missing_contact', 'Name and email required.');
        $wpdb->insert(self::table(), [
            'contact_id' => $contact_id,
            'vehicle_id' => (int) ($data['vehicle_id'] ?? 0) ?: null,
            'interest_type' => sanitize_text_field($data['interest_type'] ?? 'Test drive'),
            'stage' => sanitize_text_field($data['stage'] ?? 'inquiry'),
            'status' => 'active',
            'scheduled_at' => !empty($data['scheduled_at']) ? sanitize_text_field($data['scheduled_at']) : null,
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'owner_id' => (int) get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        $id = (int) $wpdb->insert_id;
        DG_Activities::log(['entity_type' => 'dealer_lead', 'entity_id' => $id, 'contact_id' => $contact_id, 'activity_type' => 'lead', 'subject' => 'Automotive lead', 'content' => $data['interest_type'] ?? '']);
        do_action('dg_dealer_lead_created', $id, $contact_id, $data);
        return $id;
    }

    public static function list($limit = 100) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT l.*, c.first_name, c.last_name, c.email, p.post_title AS vehicle_name
             FROM ' . self::table() . ' l
             LEFT JOIN ' . $wpdb->prefix . 'dg_contacts c ON c.id = l.contact_id
             LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = l.vehicle_id
             ORDER BY l.updated_at DESC LIMIT %d', (int) $limit
        ));
    }

    public static function stage_counts() {
        global $wpdb;
        $counts = [];
        foreach (self::stages() as $key => $label) $counts[$key] = ['label' => $label, 'count' => 0];
        foreach ($wpdb->get_results('SELECT stage, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY stage') as $row) {
            if (isset($counts[$row->stage])) $counts[$row->stage]['count'] = (int) $row->total;
        }
        return $counts;
    }

    private static function resolve_contact($data) {
        $email = sanitize_email($data['email'] ?? '');
        if (!$email) return 0;
        $existing = DG_Contacts::get_by_email($email);
        if ($existing) return (int) $existing->id;
        $parts = preg_split('/\s+/', trim($data['name'] ?? ''), 2);
        $id = DG_Contacts::create(['first_name' => $parts[0] ?? 'Customer', 'last_name' => $parts[1] ?? '', 'email' => $email, 'phone' => sanitize_text_field($data['phone'] ?? ''), 'source' => 'automotive']);
        return is_wp_error($id) ? 0 : (int) $id;
    }
}
