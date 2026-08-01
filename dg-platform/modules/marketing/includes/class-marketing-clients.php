<?php
/**
 * Agency clients — core organisations table is primary; legacy companies mirror for FKs.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Clients {

    public static function primary_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_organisations';
    }

    /** Legacy table — audits/voice logs FK here. */
    public static function companies_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_platform_companies';
    }

    public static function contacts_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_platform_contacts';
    }

    public static function marketing_sources() {
        return apply_filters('dg_marketing_client_sources', [
            'marketing', 'manual', 'voice_agent', 'webhook_audit', 'audit', 'csv_import',
            'import', 'website', 'agency_audit', 'free_audit',
        ]);
    }

    public static function shape_client($org) {
        if (!$org) {
            return null;
        }
        $company_id = self::get_company_id((int) $org->id);
        return (object) [
            'id' => $company_id ?: (int) $org->id,
            'org_id' => (int) $org->id,
            'company_name' => $org->name,
            'email' => $org->email ?? '',
            'phone' => $org->phone ?? '',
            'website' => $org->website ?? '',
            'industry' => $org->industry ?? '',
            'suburb' => $org->suburb ?? '',
            'state' => $org->state ?? '',
            'status' => $org->status ?: 'lead',
            'source' => $org->source ?? 'marketing',
            'notes' => $org->notes ?? '',
            'created_at' => $org->created_at ?? '',
            'updated_at' => $org->updated_at ?? ($org->created_at ?? ''),
        ];
    }

    public static function get_org_row($client_id) {
        global $wpdb;
        $orgs = self::primary_table();
        $org = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orgs WHERE id = %d", (int) $client_id));
        if ($org) {
            return $org;
        }
        if ($wpdb->get_var("SHOW COLUMNS FROM $orgs LIKE 'legacy_id'")) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $orgs WHERE legacy_table = 'dg_platform_companies' AND legacy_id = %d LIMIT 1",
                (int) $client_id
            ));
        }
        $company = self::get_legacy_company($client_id);
        if ($company && !empty($company->email)) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $orgs WHERE email = %s LIMIT 1", $company->email));
        }
        return null;
    }

    public static function get_legacy_company($company_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::companies_table() . ' WHERE id = %d',
            (int) $company_id
        ));
    }

    public static function get($client_id) {
        $org = self::get_org_row($client_id);
        if ($org) {
            return self::shape_client($org);
        }
        $legacy = self::get_legacy_company($client_id);
        return $legacy ?: null;
    }

    public static function get_org_id($company_id) {
        $org = self::get_org_row($company_id);
        return $org ? (int) $org->id : 0;
    }

    public static function get_company_id($org_id) {
        global $wpdb;
        $orgs = self::primary_table();
        $org = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orgs WHERE id = %d", (int) $org_id));
        if (!$org) {
            return 0;
        }
        if ($wpdb->get_var("SHOW COLUMNS FROM $orgs LIKE 'legacy_id'") && !empty($org->legacy_id) && ($org->legacy_table ?? '') === 'dg_platform_companies') {
            return (int) $org->legacy_id;
        }
        if (!empty($org->email)) {
            $id = $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . self::companies_table() . ' WHERE email = %s LIMIT 1',
                $org->email
            ));
            if ($id) {
                return (int) $id;
            }
        }
        if (class_exists('DG_Organisations')) {
            DG_Organisations::sync_to_legacy_company((int) $org_id);
            return (int) (DG_Organisations::get_legacy_company_id((int) $org_id) ?: 0);
        }
        return 0;
    }

    public static function list_clients($args = []) {
        global $wpdb;
        $orgs = self::primary_table();
        $limit = isset($args['limit']) ? (int) $args['limit'] : 100;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        if ($wpdb->get_var("SHOW TABLES LIKE '$orgs'") !== $orgs) {
            return self::list_legacy_clients($args);
        }

        $where = ['1=1'];
        $params = [];
        $sources = self::marketing_sources();
        $placeholders = implode(',', array_fill(0, count($sources), '%s'));
        $where[] = "(source IN ($placeholders) OR legacy_table = 'dg_platform_companies')";
        foreach ($sources as $source) {
            $params[] = $source;
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['source'])) {
            $where[] = 'source = %s';
            $params[] = $args['source'];
        }

        $params[] = $limit;
        $params[] = $offset;
        $sql = "SELECT * FROM $orgs WHERE " . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        if (!$rows) {
            return self::list_legacy_clients($args);
        }

        return array_map([__CLASS__, 'shape_client'], $rows);
    }

    private static function list_legacy_clients($args = []) {
        global $wpdb;
        $table = self::companies_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        $limit = isset($args['limit']) ? (int) $args['limit'] : 100;
        $where = '1=1';
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(' AND status = %s', $args['status']);
        }
        return $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT $limit");
    }

    public static function create($data) {
        if (!class_exists('DG_Organisations')) {
            return self::create_legacy_only($data);
        }

        $org_id = DG_Organisations::create([
            'name' => sanitize_text_field($data['company_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'website' => esc_url_raw($data['website'] ?? ''),
            'industry' => sanitize_text_field($data['industry'] ?? ''),
            'suburb' => sanitize_text_field($data['suburb'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'lead'),
            'source' => sanitize_text_field($data['source'] ?? 'manual'),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ]);

        $company_id = self::get_company_id($org_id);
        if ($company_id) {
            self::link_org_legacy($org_id, $company_id);
        }
        return $company_id ?: $org_id;
    }

    public static function update($client_id, $data) {
        $org_id = self::get_org_id($client_id) ?: (int) $client_id;
        $fields = [
            'name' => sanitize_text_field($data['company_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'website' => esc_url_raw($data['website'] ?? ''),
            'suburb' => sanitize_text_field($data['suburb'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ];

        if (class_exists('DG_Organisations')) {
            DG_Organisations::update($org_id, $fields);
            return self::get_company_id($org_id) ?: $client_id;
        }

        global $wpdb;
        $legacy = [
            'company_name' => $fields['name'],
            'email' => $fields['email'],
            'phone' => $fields['phone'],
            'website' => $fields['website'],
            'suburb' => $fields['suburb'],
            'state' => $fields['state'],
            'status' => $fields['status'],
            'notes' => $fields['notes'],
        ];
        $wpdb->update(self::companies_table(), $legacy, ['id' => (int) $client_id]);
        self::sync_company((int) $client_id);
        return (int) $client_id;
    }

    public static function update_status($client_id, $status) {
        $client = self::get($client_id);
        if (!$client) {
            return false;
        }
        self::update($client_id, [
            'company_name' => $client->company_name,
            'email' => $client->email,
            'phone' => $client->phone ?? '',
            'website' => $client->website ?? '',
            'suburb' => $client->suburb ?? '',
            'state' => $client->state ?? '',
            'status' => $status,
            'notes' => $client->notes ?? '',
        ]);
        return true;
    }

    public static function delete($client_id) {
        global $wpdb;
        $company_id = self::get_company_id(self::get_org_id($client_id) ?: $client_id) ?: (int) $client_id;
        $org_id = self::get_org_id($company_id);

        if ($org_id && class_exists('DG_Organisations')) {
            $wpdb->delete(self::primary_table(), ['id' => $org_id]);
        }
        $wpdb->delete(self::companies_table(), ['id' => $company_id]);
        $wpdb->delete(self::contacts_table(), ['company_id' => $company_id]);
        $wpdb->delete($wpdb->prefix . 'dg_platform_notes', ['company_id' => $company_id]);
        return true;
    }

    private static function create_legacy_only($data) {
        global $wpdb;
        $wpdb->insert(self::companies_table(), [
            'company_name' => sanitize_text_field($data['company_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'website' => esc_url_raw($data['website'] ?? ''),
            'suburb' => sanitize_text_field($data['suburb'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'lead'),
            'source' => sanitize_text_field($data['source'] ?? 'manual'),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_at' => current_time('mysql'),
        ]);
        $id = (int) $wpdb->insert_id;
        self::sync_company($id);
        return $id;
    }

    private static function link_org_legacy($org_id, $company_id) {
        global $wpdb;
        $orgs = self::primary_table();
        if ($wpdb->get_var("SHOW COLUMNS FROM $orgs LIKE 'legacy_id'")) {
            $wpdb->update($orgs, [
                'legacy_table' => 'dg_platform_companies',
                'legacy_id' => (int) $company_id,
            ], ['id' => (int) $org_id]);
        }
    }

    public static function sync_company($company_id) {
        global $wpdb;

        $company = self::get_legacy_company($company_id);
        if (!$company) {
            return false;
        }

        $orgs_table = self::primary_table();
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        $org_id = null;

        if ($wpdb->get_var("SHOW COLUMNS FROM $orgs_table LIKE 'legacy_id'")) {
            $org_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $orgs_table WHERE legacy_table = 'dg_platform_companies' AND legacy_id = %d LIMIT 1",
                (int) $company_id
            ));
        }
        if (!$org_id && !empty($company->email)) {
            $org_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $orgs_table WHERE email = %s LIMIT 1",
                $company->email
            ));
        }

        $org_data = [
            'name' => $company->company_name,
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $company->website,
            'industry' => $company->industry,
            'suburb' => $company->suburb,
            'state' => $company->state,
            'status' => $company->status ?: 'active',
            'source' => $company->source ?: 'marketing',
            'notes' => $company->notes,
            'legacy_table' => 'dg_platform_companies',
            'legacy_id' => (int) $company_id,
        ];

        if ($org_id) {
            $wpdb->update($orgs_table, $org_data, ['id' => (int) $org_id]);
        } else {
            unset($org_data['legacy_table'], $org_data['legacy_id']);
            $wpdb->insert($orgs_table, $org_data);
            $org_id = (int) $wpdb->insert_id;
            self::link_org_legacy($org_id, (int) $company_id);
        }

        $platform_contacts = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::contacts_table() . ' WHERE company_id = %d',
            (int) $company_id
        ));

        if (!$platform_contacts && !empty($company->email)) {
            $parts = self::split_name($company->company_name);
            $wpdb->insert(self::contacts_table(), [
                'company_id' => (int) $company_id,
                'first_name' => $parts['first_name'],
                'last_name' => $parts['last_name'],
                'email' => $company->email,
                'phone' => $company->phone,
                'is_primary' => 1,
                'status' => 'active',
                'source' => $company->source ?: 'marketing',
            ]);
            $platform_contacts = $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . self::contacts_table() . ' WHERE company_id = %d',
                (int) $company_id
            ));
        }

        foreach ($platform_contacts as $contact) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $contacts_table WHERE email = %s AND legacy_table = 'dg_platform_contacts' AND legacy_id = %d",
                $contact->email,
                (int) $contact->id
            ));

            $row = [
                'organisation_id' => $org_id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'position' => $contact->position,
                'is_primary' => (int) $contact->is_primary,
                'status' => $contact->status ?: 'active',
                'source' => $contact->source ?: 'marketing',
                'notes' => $contact->notes,
                'legacy_table' => 'dg_platform_contacts',
                'legacy_id' => (int) $contact->id,
            ];

            if ($existing) {
                $wpdb->update($contacts_table, $row, ['id' => (int) $existing]);
            } else {
                $wpdb->insert($contacts_table, $row);
            }
        }

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 'organisation',
                'entity_id' => (int) $org_id,
                'activity_type' => 'sync',
                'subject' => 'Agency client synced to core CRM',
                'content' => $company->company_name,
                'metadata' => ['company_id' => (int) $company_id],
            ]);
        }

        return (int) $org_id;
    }

    public static function upsert_lead_company($data) {
        global $wpdb;
        $email = sanitize_email($data['email'] ?? '');
        if ($email === '') {
            return 0;
        }

        $existing_org = class_exists('DG_Organisations') ? DG_Organisations::get_by_email($email) : null;
        $org_payload = [
            'name' => sanitize_text_field($data['company_name'] ?? $data['business_name'] ?? ''),
            'email' => $email,
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'website' => esc_url_raw($data['website'] ?? $data['website_url'] ?? ''),
            'suburb' => sanitize_text_field($data['suburb'] ?? $data['agency_location'] ?? ''),
            'source' => sanitize_text_field($data['source'] ?? 'voice_agent'),
            'status' => sanitize_text_field($data['status'] ?? 'lead'),
        ];
        if ($org_payload['name'] === '' && !empty($data['name'])) {
            $org_payload['name'] = sanitize_text_field($data['name']) . ' Agency';
        }
        if ($org_payload['name'] === '') {
            $org_payload['name'] = 'Unknown Agency';
        }

        if ($existing_org) {
            if (class_exists('DG_Organisations')) {
                DG_Organisations::update((int) $existing_org->id, $org_payload);
            }
            $company_id = self::get_company_id((int) $existing_org->id);
        } else {
            $company_id = self::create(array_merge($org_payload, ['company_name' => $org_payload['name']]));
        }

        if (!empty($data['name'])) {
            $parts = self::split_name($data['name']);
            $contacts = self::contacts_table();
            $has = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $contacts WHERE company_id = %d AND email = %s",
                $company_id,
                $email
            ));
            if (!$has) {
                $wpdb->insert($contacts, [
                    'company_id' => $company_id,
                    'first_name' => $parts['first_name'],
                    'last_name' => $parts['last_name'],
                    'email' => $email,
                    'phone' => sanitize_text_field($data['phone'] ?? ''),
                    'is_primary' => 1,
                    'status' => 'active',
                    'source' => sanitize_text_field($data['source'] ?? 'voice_agent'),
                ]);
            }
        }

        self::sync_company($company_id);
        return (int) $company_id;
    }

    public static function split_name($full_name) {
        $parts = preg_split('/\s+/', trim((string) $full_name), 2);
        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }
}
