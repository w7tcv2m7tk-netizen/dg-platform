<?php
/**
 * Activity timeline service.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Activities {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_activities';
    }

    public static function log($data) {
        global $wpdb;
        $defaults = [
            'entity_type' => null,
            'entity_id' => null,
            'contact_id' => null,
            'user_id' => get_current_user_id(),
            'activity_type' => 'note',
            'subject' => '',
            'content' => '',
            'metadata' => null,
        ];
        $data = wp_parse_args($data, $defaults);

        $wpdb->insert(self::table(), [
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'contact_id' => $data['contact_id'],
            'user_id' => $data['user_id'] ?: null,
            'activity_type' => sanitize_text_field($data['activity_type']),
            'subject' => sanitize_text_field($data['subject']),
            'content' => is_string($data['content']) ? $data['content'] : wp_json_encode($data['content']),
            'metadata' => is_array($data['metadata']) ? wp_json_encode($data['metadata']) : $data['metadata'],
        ]);

        return $wpdb->insert_id;
    }

    public static function get_for_entity($entity_type, $entity_id, $limit = 50) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entity_type = %s AND entity_id = %d ORDER BY created_at DESC LIMIT %d',
            $entity_type,
            $entity_id,
            $limit
        ));
    }

    public static function get_for_contact($contact_id, $limit = 50) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE contact_id = %d ORDER BY created_at DESC LIMIT %d',
            $contact_id,
            $limit
        ));
    }

    public static function recent($limit = 20) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC LIMIT %d',
            $limit
        ));
    }

    public static function count() {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table());
    }
}
