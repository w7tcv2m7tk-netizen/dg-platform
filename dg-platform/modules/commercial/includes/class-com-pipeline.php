<?php
if (!defined('ABSPATH')) exit;

class DG_Com_Pipeline {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_com_tenancies';
    }

    public static function stages() {
        return [
            'inquiry' => 'Inquiry',
            'inspection' => 'Inspection',
            'offer' => 'Offer',
            'lease_signed' => 'Lease signed',
            'active' => 'Active tenancy',
            'expired' => 'Expired',
        ];
    }

    public static function property_types() {
        return ['Office', 'Retail', 'Industrial', 'Warehouse', 'Mixed use'];
    }

    public static function create($data) {
        global $wpdb;
        $contact_id = self::resolve_contact($data);
        if (!$contact_id) return new WP_Error('missing_contact', 'Name and email required.');
        $wpdb->insert(self::table(), [
            'contact_id' => $contact_id,
            'listing_id' => (int) ($data['listing_id'] ?? 0) ?: null,
            'business_name' => sanitize_text_field($data['business_name'] ?? ''),
            'stage' => sanitize_text_field($data['stage'] ?? 'inquiry'),
            'status' => 'active',
            'rent_pcm' => (float) ($data['rent_pcm'] ?? 0),
            'sqm' => (float) ($data['sqm'] ?? 0),
            'lease_start' => !empty($data['lease_start']) ? sanitize_text_field($data['lease_start']) : null,
            'lease_end' => !empty($data['lease_end']) ? sanitize_text_field($data['lease_end']) : null,
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'owner_id' => (int) get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        $id = (int) $wpdb->insert_id;
        DG_Activities::log(['entity_type' => 'com_tenancy', 'entity_id' => $id, 'contact_id' => $contact_id, 'activity_type' => 'lead', 'subject' => 'Commercial tenancy lead', 'content' => $data['business_name'] ?? '']);
        do_action('dg_com_tenancy_created', $id, $contact_id, $data);
        return $id;
    }

    public static function list($limit = 100) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT t.*, c.first_name, c.last_name, c.email, p.post_title AS listing_name
             FROM ' . self::table() . ' t
             LEFT JOIN ' . $wpdb->prefix . 'dg_contacts c ON c.id = t.contact_id
             LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = t.listing_id
             ORDER BY t.updated_at DESC LIMIT %d', (int) $limit
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
        $id = DG_Contacts::create(['first_name' => $parts[0] ?? 'Tenant', 'last_name' => $parts[1] ?? '', 'email' => $email, 'phone' => sanitize_text_field($data['phone'] ?? ''), 'source' => 'commercial']);
        return is_wp_error($id) ? 0 : (int) $id;
    }
}
