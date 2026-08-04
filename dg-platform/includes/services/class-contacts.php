<?php
/**
 * Unified contacts service.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Contacts {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_contacts';
    }

    public static function meta_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_entity_meta';
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
    }

    public static function get_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE email = %s LIMIT 1', $email));
    }

    public static function get_by_legacy($legacy_table, $legacy_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE legacy_table = %s AND legacy_id = %d LIMIT 1',
            $legacy_table,
            $legacy_id
        ));
    }

    public static function list($args = []) {
        global $wpdb;
        $defaults = [
            'status' => null,
            'source' => null,
            'owner_id' => null,
            'search' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);
        $where = ['1=1'];
        $values = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ($args['source']) {
            $where[] = 'source = %s';
            $values[] = $args['source'];
        }
        if ($args['owner_id']) {
            $where[] = 'owner_id = %d';
            $values[] = $args['owner_id'];
        }
        if ($args['search']) {
            $where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY ' . sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $sql .= ' LIMIT %d OFFSET %d';
        $values[] = (int) $args['limit'];
        $values[] = (int) $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    public static function count($args = []) {
        global $wpdb;
        $where = ['1=1'];
        $values = [];
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        $sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where);
        if ($values) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, $values));
        }
        return (int) $wpdb->get_var($sql);
    }

    public static function create($data) {
        global $wpdb;
        $defaults = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'status' => 'active',
            'source' => 'website',
        ];
        $data = wp_parse_args($data, $defaults);

        if (empty($data['email'])) {
            return new WP_Error('missing_email', 'Email is required.');
        }

        $existing = self::get_by_email($data['email']);
        if ($existing) {
            return self::update($existing->id, $data);
        }

        $wpdb->insert(self::table(), [
            'organisation_id' => $data['organisation_id'] ?? null,
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'position' => sanitize_text_field($data['position'] ?? ''),
            'is_primary' => !empty($data['is_primary']) ? 1 : 0,
            'status' => sanitize_text_field($data['status']),
            'source' => sanitize_text_field($data['source']),
            'owner_id' => $data['owner_id'] ?? null,
            'tags' => sanitize_text_field($data['tags'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'legacy_table' => $data['legacy_table'] ?? null,
            'legacy_id' => $data['legacy_id'] ?? null,
        ]);

        $id = $wpdb->insert_id;
        DG_Activities::log([
            'entity_type' => 'contact',
            'entity_id' => $id,
            'activity_type' => 'system',
            'subject' => 'Contact created',
            'content' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
        ]);
        DG_Permissions::log_audit('contact_created', 'contact', $id, null, $data);

        do_action('dg_contact_created', $id, $data);

        return $id;
    }

    public static function update($id, $data) {
        global $wpdb;
        $fields = [];
        $allowed = ['organisation_id', 'first_name', 'last_name', 'email', 'phone', 'position', 'is_primary', 'status', 'source', 'owner_id', 'tags', 'notes'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = is_string($data[$field]) ? sanitize_text_field($data[$field]) : $data[$field];
            }
        }
        if (isset($data['notes'])) {
            $fields['notes'] = sanitize_textarea_field($data['notes']);
        }
        if (!$fields) {
            return $id;
        }
        $wpdb->update(self::table(), $fields, ['id' => $id]);
        DG_Permissions::log_audit('contact_updated', 'contact', $id, null, $fields);
        return $id;
    }

    public static function delete($id) {
        global $wpdb;
        $id = (int) $id;

        $wpdb->delete(self::meta_table(), [
            'entity_type' => 'contact',
            'entity_id' => $id,
        ]);

        DG_Permissions::log_audit('contact_deleted', 'contact', $id);
        return $wpdb->delete(self::table(), ['id' => $id]);
    }

    public static function get_meta($contact_id, $key = null) {
        global $wpdb;
        if ($key) {
            return $wpdb->get_var($wpdb->prepare(
                'SELECT meta_value FROM ' . self::meta_table() . ' WHERE entity_type = %s AND entity_id = %d AND meta_key = %s',
                'contact',
                $contact_id,
                $key
            ));
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT meta_key, meta_value FROM ' . self::meta_table() . ' WHERE entity_type = %s AND entity_id = %d',
            'contact',
            $contact_id
        ));
        $meta = [];
        foreach ($rows as $row) {
            $meta[$row->meta_key] = maybe_unserialize($row->meta_value);
        }
        return $meta;
    }

    public static function set_meta($contact_id, $key, $value) {
        global $wpdb;
        $table = self::meta_table();
        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . $table . ' WHERE entity_type = %s AND entity_id = %d AND meta_key = %s',
            'contact',
            $contact_id,
            $key
        ));
        $value = maybe_serialize($value);
        if ($existing) {
            return $wpdb->update($table, ['meta_value' => $value], ['id' => $existing]);
        }
        return $wpdb->insert($table, [
            'entity_type' => 'contact',
            'entity_id' => $contact_id,
            'meta_key' => $key,
            'meta_value' => $value,
        ]);
    }

    /**
     * Backward-compat: write to core and optionally mirror to roe_crm_contacts.
     */
    public static function create_from_legacy($legacy_table, $data) {
        $data['legacy_table'] = $legacy_table;
        $id = self::create($data);
        if (is_wp_error($id)) {
            return $id;
        }

        if ($legacy_table === 'roe_crm_contacts') {
            self::sync_to_roe_contacts($id, $data);
        }

        return $id;
    }

    public static function sync_to_roe_contacts($contact_id, $data = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_crm_contacts';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return false;
        }

        $contact = $data ? (object) $data : self::get($contact_id);
        if (!$contact) {
            return false;
        }

        $legacy_id = $contact->legacy_table === 'roe_crm_contacts' ? $contact->legacy_id : null;
        $row = [
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'phone' => $contact->phone,
            'agent_id' => $contact->owner_id,
            'source' => $contact->source,
            'status' => $contact->status,
        ];
        $property_id = self::get_meta($contact_id, 'property_id');
        if ($property_id) {
            $row['property_id'] = $property_id;
        }

        if ($legacy_id) {
            $wpdb->update($table, $row, ['id' => $legacy_id]);
            return $legacy_id;
        }

        $wpdb->insert($table, $row);
        $legacy_id = $wpdb->insert_id;
        self::update($contact_id, ['legacy_table' => 'roe_crm_contacts', 'legacy_id' => $legacy_id]);
        return $legacy_id;
    }

    public static function full_name($contact) {
        if (is_numeric($contact)) {
            $contact = self::get($contact);
        }
        if (!$contact) {
            return '';
        }
        return trim($contact->first_name . ' ' . ($contact->last_name ?? ''));
    }
}
