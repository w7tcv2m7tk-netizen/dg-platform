<?php
/**
 * Finance loan application pipeline.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Fin_Pipeline {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_fin_applications';
    }

    public static function stages() {
        return [
            'inquiry' => 'Inquiry',
            'pre_approval' => 'Pre-approval',
            'application' => 'Application',
            'assessment' => 'Assessment',
            'approved' => 'Approved',
            'settled' => 'Settled',
            'declined' => 'Declined',
        ];
    }

    public static function loan_types() {
        return ['Home loan', 'Investment', 'Refinance', 'Commercial', 'Asset finance', 'Other'];
    }

    public static function create($data) {
        global $wpdb;

        $contact_id = self::resolve_contact($data);
        if (!$contact_id) {
            return new WP_Error('missing_contact', 'Name and email are required.');
        }

        $wpdb->insert(self::table(), [
            'contact_id' => $contact_id,
            'loan_type' => sanitize_text_field($data['loan_type'] ?? 'Home loan'),
            'amount' => (float) ($data['amount'] ?? 0),
            'stage' => sanitize_text_field($data['stage'] ?? 'inquiry'),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'lender' => sanitize_text_field($data['lender'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'owner_id' => (int) ($data['owner_id'] ?? get_current_user_id()),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        $id = (int) $wpdb->insert_id;

        DG_Activities::log([
            'entity_type' => 'fin_application',
            'entity_id' => $id,
            'contact_id' => $contact_id,
            'activity_type' => 'lead',
            'subject' => 'Finance application created',
            'content' => ($data['loan_type'] ?? 'Home loan') . ' — $' . number_format((float) ($data['amount'] ?? 0)),
        ]);

        do_action('dg_fin_application_created', $id, $contact_id, $data);

        return $id;
    }

    public static function list($args = []) {
        global $wpdb;
        $limit = (int) ($args['limit'] ?? 100);
        $stage = isset($args['stage']) ? sanitize_text_field($args['stage']) : '';
        $sql = 'SELECT a.*, c.first_name, c.last_name, c.email, c.phone
                FROM ' . self::table() . ' a
                LEFT JOIN ' . $wpdb->prefix . 'dg_contacts c ON c.id = a.contact_id';
        if ($stage) {
            return $wpdb->get_results($wpdb->prepare($sql . ' WHERE a.stage = %s ORDER BY a.updated_at DESC LIMIT %d', $stage, $limit));
        }
        return $wpdb->get_results($sql . ' ORDER BY a.updated_at DESC LIMIT ' . $limit);
    }

    public static function stage_counts() {
        global $wpdb;
        $counts = [];
        foreach (self::stages() as $key => $label) {
            $counts[$key] = ['label' => $label, 'count' => 0];
        }
        $rows = $wpdb->get_results('SELECT stage, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY stage');
        foreach ($rows as $row) {
            if (isset($counts[$row->stage])) {
                $counts[$row->stage]['count'] = (int) $row->total;
            }
        }
        return $counts;
    }

    public static function update_stage($id, $stage) {
        global $wpdb;
        if (!isset(self::stages()[$stage])) {
            return false;
        }
        return $wpdb->update(self::table(), [
            'stage' => $stage,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int) $id]);
    }

    public static function get($id) {
        global $wpdb;
        $sql = 'SELECT a.*, c.first_name, c.last_name, c.email, c.phone
                FROM ' . self::table() . ' a
                LEFT JOIN ' . $wpdb->prefix . 'dg_contacts c ON c.id = a.contact_id
                WHERE a.id = %d';
        return $wpdb->get_row($wpdb->prepare($sql, (int) $id));
    }

    public static function delete($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::table(), ['id' => (int) $id], ['%d']);
    }

    private static function resolve_contact($data) {
        $email = sanitize_email($data['email'] ?? '');
        if (!$email) {
            return 0;
        }
        $existing = DG_Contacts::get_by_email($email);
        if ($existing) {
            return (int) $existing->id;
        }
        $parts = preg_split('/\s+/', trim($data['name'] ?? ''), 2);
        $id = DG_Contacts::create([
            'first_name' => $parts[0] ?? 'Borrower',
            'last_name' => $parts[1] ?? '',
            'email' => $email,
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'source' => 'finance',
        ]);
        return is_wp_error($id) ? 0 : (int) $id;
    }
}
