<?php
if (!defined('ABSPATH')) exit;

class DG_Svc_Pipeline {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_svc_jobs';
    }

    public static function stages() {
        return [
            'inquiry' => 'Inquiry',
            'quote' => 'Quote sent',
            'scheduled' => 'Scheduled',
            'in_progress' => 'In progress',
            'invoiced' => 'Invoiced',
            'complete' => 'Complete',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function create($data) {
        global $wpdb;
        $contact_id = self::resolve_contact($data);
        if (!$contact_id) {
            return new WP_Error('missing_contact', 'Name and email are required.');
        }
        $wpdb->insert(self::table(), [
            'contact_id' => $contact_id,
            'title' => sanitize_text_field($data['title'] ?? 'Service job'),
            'service_type' => sanitize_text_field($data['service_type'] ?? 'General'),
            'stage' => sanitize_text_field($data['stage'] ?? 'inquiry'),
            'status' => 'active',
            'quoted_amount' => (float) ($data['quoted_amount'] ?? 0),
            'scheduled_at' => !empty($data['scheduled_at']) ? sanitize_text_field($data['scheduled_at']) : null,
            'address' => sanitize_text_field($data['address'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'owner_id' => (int) ($data['owner_id'] ?? get_current_user_id()),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        $id = (int) $wpdb->insert_id;
        DG_Activities::log([
            'entity_type' => 'svc_job', 'entity_id' => $id, 'contact_id' => $contact_id,
            'activity_type' => 'task', 'subject' => 'Service job created', 'content' => $data['title'] ?? '',
        ]);
        do_action('dg_svc_job_created', $id, $contact_id, $data);
        return $id;
    }

    public static function list($limit = 100) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT j.*, c.first_name, c.last_name, c.email FROM ' . self::table() . ' j
             LEFT JOIN ' . $wpdb->prefix . 'dg_contacts c ON c.id = j.contact_id
             ORDER BY j.updated_at DESC LIMIT %d', (int) $limit
        ));
    }

    public static function stage_counts() {
        global $wpdb;
        $counts = [];
        foreach (self::stages() as $key => $label) {
            $counts[$key] = ['label' => $label, 'count' => 0];
        }
        foreach ($wpdb->get_results('SELECT stage, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY stage') as $row) {
            if (isset($counts[$row->stage])) {
                $counts[$row->stage]['count'] = (int) $row->total;
            }
        }
        return $counts;
    }

    private static function resolve_contact($data) {
        $email = sanitize_email($data['email'] ?? '');
        if (!$email) return 0;
        $existing = DG_Contacts::get_by_email($email);
        if ($existing) return (int) $existing->id;
        $parts = preg_split('/\s+/', trim($data['name'] ?? ''), 2);
        $id = DG_Contacts::create([
            'first_name' => $parts[0] ?? 'Customer', 'last_name' => $parts[1] ?? '',
            'email' => $email, 'phone' => sanitize_text_field($data['phone'] ?? ''), 'source' => 'services',
        ]);
        return is_wp_error($id) ? 0 : (int) $id;
    }
}
