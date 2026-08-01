<?php
/**
 * Shared contact helpers for Marketing module.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Contacts {

    public static function split_name($full_name) {
        return DG_Marketing_Clients::split_name($full_name);
    }

    public static function display_email($email) {
        if ($email === '' || strpos($email, '@leads.') !== false) {
            return '';
        }
        return $email;
    }

    public static function resolve_contact_id($data) {
        global $wpdb;
        $email = sanitize_email($data['email'] ?? '');
        if ($email !== '' && class_exists('DG_Contacts')) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}dg_contacts WHERE email = %s ORDER BY id DESC LIMIT 1",
                $email
            ));
            if ($row) {
                return (int) $row->id;
            }
        }
        return 0;
    }
}
