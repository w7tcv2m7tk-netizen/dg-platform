<?php
/**
 * Shared contact helpers for Real Estate module.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Contacts {

    public static function split_name($full_name) {
        $parts = preg_split('/\s+/', trim($full_name), 2);
        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }

    public static function resolve_contact_id($data) {
        global $wpdb;

        $email = sanitize_email($data['email'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        $name_parts = self::split_name($data['full_name'] ?? '');
        $source = sanitize_text_field($data['source'] ?? 'website');

        if ($email === '' && $phone !== '') {
            $digits = preg_replace('/[^0-9]/', '', $phone);
            $email = 'phone-' . $digits . '@leads.roerealty.local';
        }

        if ($email === '') {
            return null;
        }

        if (!class_exists('DG_Contacts')) {
            return null;
        }

        $existing = DG_Contacts::get_by_email($email);
        if ($existing) {
            DG_Contacts::update($existing->id, [
                'first_name' => $name_parts['first_name'],
                'last_name' => $name_parts['last_name'],
                'phone' => $phone ?: $existing->phone,
                'source' => $source,
            ]);
            $contact_id = (int) $existing->id;
        } else {
            $created = DG_Contacts::create([
                'first_name' => $name_parts['first_name'],
                'last_name' => $name_parts['last_name'],
                'email' => $email,
                'phone' => $phone,
                'source' => $source,
                'notes' => !empty($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
            ]);
            if (is_wp_error($created)) {
                return null;
            }
            $contact_id = (int) $created;
        }

        $legacy_table = $wpdb->prefix . 'roe_crm_contacts';
        if ($wpdb->get_var("SHOW TABLES LIKE '$legacy_table'") === $legacy_table) {
            $legacy = $wpdb->get_var($wpdb->prepare("SELECT id FROM $legacy_table WHERE email = %s", $email));
            if (!$legacy && strpos($email, '@leads.roerealty.local') === false) {
                $wpdb->insert($legacy_table, [
                    'email' => $email,
                    'first_name' => $name_parts['first_name'],
                    'last_name' => $name_parts['last_name'],
                    'phone' => $phone,
                    'source' => $source,
                    'status' => 'active',
                    'last_activity' => current_time('mysql'),
                ]);
            }
        }

        return $contact_id;
    }

    public static function display_email($email) {
        if (!$email || strpos($email, '@leads.roerealty.local') !== false) {
            return '';
        }
        return $email;
    }
}
