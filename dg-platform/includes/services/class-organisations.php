<?php
/**
 * Organisations service.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Organisations {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_organisations';
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
    }

    public static function get_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE email = %s LIMIT 1', $email));
    }

    public static function list($args = []) {
        global $wpdb;
        $defaults = ['limit' => 100, 'offset' => 0, 'status' => null, 'search' => null];
        $args = wp_parse_args($args, $defaults);
        $where = ['1=1'];
        $values = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ($args['search']) {
            $where[] = '(name LIKE %s OR email LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $like;
            $values[] = $like;
        }

        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $values[] = (int) $args['limit'];
        $values[] = (int) $args['offset'];
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    public static function count() {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table());
    }

    public static function create($data) {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'website' => esc_url_raw($data['website'] ?? ''),
            'industry' => sanitize_text_field($data['industry'] ?? ''),
            'suburb' => sanitize_text_field($data['suburb'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'source' => sanitize_text_field($data['source'] ?? 'website'),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ]);
        $id = $wpdb->insert_id;

        // Mirror to legacy companies table for marketing module compatibility
        self::sync_to_legacy_company($id);

        DG_Activities::log([
            'entity_type' => 'organisation',
            'entity_id' => $id,
            'activity_type' => 'system',
            'subject' => 'Organisation created',
            'content' => $data['name'] ?? '',
        ]);

        return $id;
    }

    public static function update($id, $data) {
        global $wpdb;
        $fields = [];
        foreach (['name', 'email', 'phone', 'website', 'industry', 'suburb', 'state', 'status', 'source', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $field === 'notes'
                    ? sanitize_textarea_field($data[$field])
                    : sanitize_text_field($data[$field]);
            }
        }
        if ($fields) {
            $wpdb->update(self::table(), $fields, ['id' => $id]);
            self::sync_to_legacy_company($id);
        }
        return $id;
    }

    public static function sync_to_legacy_company($org_id) {
        global $wpdb;
        $legacy = $wpdb->prefix . 'dg_platform_companies';
        if ($wpdb->get_var("SHOW TABLES LIKE '$legacy'") !== $legacy) {
            return;
        }
        $org = self::get($org_id);
        if (!$org) {
            return;
        }
        $existing = $org->email
            ? $wpdb->get_var($wpdb->prepare("SELECT id FROM $legacy WHERE email = %s", $org->email))
            : null;
        $row = [
            'company_name' => $org->name,
            'email' => $org->email,
            'phone' => $org->phone,
            'website' => $org->website,
            'industry' => $org->industry,
            'suburb' => $org->suburb,
            'state' => $org->state,
            'status' => $org->status,
            'source' => $org->source,
            'notes' => $org->notes,
        ];
        if ($existing) {
            $wpdb->update($legacy, $row, ['id' => $existing]);
            $legacy_id = (int) $existing;
        } else {
            $wpdb->insert($legacy, $row);
            $legacy_id = (int) $wpdb->insert_id;
        }

        if (!empty($legacy_id) && $wpdb->get_var('SHOW COLUMNS FROM ' . self::table() . " LIKE 'legacy_id'")) {
            $wpdb->update(self::table(), [
                'legacy_table' => 'dg_platform_companies',
                'legacy_id' => $legacy_id,
            ], ['id' => (int) $org_id]);
        }
    }

    /** @return int|null Legacy company ID for marketing module */
    public static function get_legacy_company_id($org_id) {
        global $wpdb;
        $org = self::get($org_id);
        if (!$org || !$org->email) {
            return null;
        }
        $legacy = $wpdb->prefix . 'dg_platform_companies';
        return $wpdb->get_var($wpdb->prepare("SELECT id FROM $legacy WHERE email = %s LIMIT 1", $org->email));
    }
}
