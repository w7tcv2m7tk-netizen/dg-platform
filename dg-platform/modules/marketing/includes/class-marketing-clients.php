<?php
/**
 * Agency client sync — legacy companies table ↔ core CRM.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Clients {

    public static function companies_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_platform_companies';
    }

    public static function contacts_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_platform_contacts';
    }

    public static function get($company_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::companies_table() . ' WHERE id = %d',
            (int) $company_id
        ));
    }

    public static function get_org_id($company_id) {
        global $wpdb;
        $company = self::get($company_id);
        if (!$company) {
            return 0;
        }
        $orgs = $wpdb->prefix . 'dg_organisations';
        if ($wpdb->get_var("SHOW COLUMNS FROM $orgs LIKE 'legacy_id'")) {
            $org_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $orgs WHERE legacy_table = 'dg_platform_companies' AND legacy_id = %d LIMIT 1",
                (int) $company_id
            ));
            if ($org_id) {
                return (int) $org_id;
            }
        }
        if (!empty($company->email)) {
            $org_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $orgs WHERE email = %s LIMIT 1",
                $company->email
            ));
            if ($org_id) {
                return (int) $org_id;
            }
        }
        return (int) self::sync_company($company_id);
    }

    public static function get_company_id($org_id) {
        global $wpdb;
        $orgs = $wpdb->prefix . 'dg_organisations';
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
        return 0;
    }

    public static function list_clients($args = []) {
        global $wpdb;
        $table = self::companies_table();
        $limit = isset($args['limit']) ? (int) $args['limit'] : 100;
        $where = '1=1';
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(' AND status = %s', $args['status']);
        }
        return $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT $limit");
    }

    public static function sync_company($company_id) {
        global $wpdb;

        $company = self::get($company_id);
        if (!$company) {
            return false;
        }

        $orgs_table = $wpdb->prefix . 'dg_organisations';
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        $org_id = null;

        if (!empty($company->email)) {
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
        ];

        $legacy_link = [
            'legacy_table' => 'dg_platform_companies',
            'legacy_id' => (int) $company_id,
        ];
        if ($wpdb->get_var("SHOW COLUMNS FROM $orgs_table LIKE 'legacy_id'")) {
            $org_data = array_merge($org_data, $legacy_link);
        }

        if ($org_id) {
            $wpdb->update($orgs_table, $org_data, ['id' => (int) $org_id]);
        } else {
            $wpdb->insert($orgs_table, $org_data);
            $org_id = (int) $wpdb->insert_id;
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
        $table = self::companies_table();
        $email = sanitize_email($data['email'] ?? '');
        if ($email === '') {
            return 0;
        }

        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));
        if ($existing) {
            $company_id = (int) $existing->id;
            $wpdb->update($table, [
                'company_name' => sanitize_text_field($data['company_name'] ?? $data['business_name'] ?? ''),
                'phone' => sanitize_text_field($data['phone'] ?? ''),
                'website' => esc_url_raw($data['website'] ?? $data['website_url'] ?? ''),
                'suburb' => sanitize_text_field($data['suburb'] ?? $data['agency_location'] ?? ''),
                'source' => sanitize_text_field($data['source'] ?? 'voice_agent'),
                'status' => sanitize_text_field($data['status'] ?? 'lead'),
            ], ['id' => $company_id]);
        } else {
            $name = sanitize_text_field($data['company_name'] ?? $data['business_name'] ?? '');
            if ($name === '' && !empty($data['name'])) {
                $name = sanitize_text_field($data['name']) . ' Agency';
            }
            $wpdb->insert($table, [
                'company_name' => $name ?: 'Unknown Agency',
                'email' => $email,
                'phone' => sanitize_text_field($data['phone'] ?? ''),
                'website' => esc_url_raw($data['website'] ?? $data['website_url'] ?? ''),
                'suburb' => sanitize_text_field($data['suburb'] ?? $data['agency_location'] ?? ''),
                'source' => sanitize_text_field($data['source'] ?? 'voice_agent'),
                'status' => sanitize_text_field($data['status'] ?? 'lead'),
                'created_at' => current_time('mysql'),
            ]);
            $company_id = (int) $wpdb->insert_id;
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
        return $company_id;
    }

    public static function split_name($full_name) {
        $parts = preg_split('/\s+/', trim((string) $full_name), 2);
        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }
}
