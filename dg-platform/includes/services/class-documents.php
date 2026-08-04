<?php
/**
 * Documents service — WP Media Library wrapper.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Documents {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_documents';
    }

    public static function attach($attachment_id, $entity_type, $entity_id, $title = '') {
        global $wpdb;
        $file = get_attached_file($attachment_id);
        $wpdb->insert(self::table(), [
            'attachment_id' => $attachment_id,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'title' => $title ?: get_the_title($attachment_id),
            'file_path' => $file,
            'mime_type' => get_post_mime_type($attachment_id),
            'uploaded_by' => get_current_user_id(),
        ]);
        return $wpdb->insert_id;
    }

    public static function get_for_entity($entity_type, $entity_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entity_type = %s AND entity_id = %d ORDER BY created_at DESC',
            $entity_type,
            $entity_id
        ));
    }

    public static function count() {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table());
    }

    public static function list_recent($limit = 200) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC LIMIT %d',
            (int) $limit
        ));
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id));
    }

    public static function delete($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::table(), ['id' => (int) $id], ['%d']);
    }
}
