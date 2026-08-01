<?php
/**
 * Generic entity meta + custom field definitions.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Entity_Meta {

    const DEFINITIONS_OPTION = 'dg_custom_field_definitions';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_entity_meta';
    }

    public static function get_definitions($entity_type = 'contact') {
        $all = get_option(self::DEFINITIONS_OPTION, []);
        return $all[$entity_type] ?? [];
    }

    public static function save_definitions($entity_type, $fields) {
        $all = get_option(self::DEFINITIONS_OPTION, []);
        $all[$entity_type] = array_values($fields);
        update_option(self::DEFINITIONS_OPTION, $all);
    }

    public static function get($entity_type, $entity_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT meta_key, meta_value FROM ' . self::table() . ' WHERE entity_type = %s AND entity_id = %d',
            $entity_type,
            $entity_id
        ));
        $meta = [];
        foreach ($rows as $row) {
            $meta[$row->meta_key] = maybe_unserialize($row->meta_value);
        }
        return $meta;
    }

    public static function set($entity_type, $entity_id, $key, $value) {
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE entity_type = %s AND entity_id = %d AND meta_key = %s',
            $entity_type,
            $entity_id,
            $key
        ));
        $value = maybe_serialize($value);
        if ($existing) {
            return $wpdb->update(self::table(), ['meta_value' => $value], ['id' => $existing]);
        }
        return $wpdb->insert(self::table(), [
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'meta_key' => $key,
            'meta_value' => $value,
        ]);
    }
}
