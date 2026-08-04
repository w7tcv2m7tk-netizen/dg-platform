<?php
/**
 * Social posts storage and CRUD.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Pro_Posts {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_social_posts';
    }

    public static function ensure_table() {
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            title varchar(255) DEFAULT '',
            content text NOT NULL,
            media_url varchar(500) DEFAULT '',
            link_url varchar(500) DEFAULT '',
            platforms longtext,
            status varchar(20) DEFAULT 'draft',
            scheduled_at datetime DEFAULT NULL,
            published_at datetime DEFAULT NULL,
            results longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY user_id (user_id)
        ) $charset;");
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data) {
        self::ensure_table();
        global $wpdb;

        $platforms = isset($data['platforms']) && is_array($data['platforms'])
            ? array_map('sanitize_key', $data['platforms'])
            : [];

        $wpdb->insert(self::table(), [
            'user_id' => get_current_user_id(),
            'title' => sanitize_text_field($data['title'] ?? ''),
            'content' => wp_kses_post($data['content'] ?? ''),
            'media_url' => esc_url_raw($data['media_url'] ?? ''),
            'link_url' => esc_url_raw($data['link_url'] ?? ''),
            'platforms' => wp_json_encode($platforms),
            'status' => sanitize_key($data['status'] ?? 'draft'),
            'scheduled_at' => !empty($data['scheduled_at']) ? gmdate('Y-m-d H:i:s', strtotime($data['scheduled_at'])) : null,
            'created_at' => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    /** @param array<string,mixed> $data */
    public static function update($id, array $data) {
        self::ensure_table();
        global $wpdb;

        $fields = [];
        if (isset($data['title'])) {
            $fields['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['content'])) {
            $fields['content'] = wp_kses_post($data['content']);
        }
        if (isset($data['media_url'])) {
            $fields['media_url'] = esc_url_raw($data['media_url']);
        }
        if (isset($data['link_url'])) {
            $fields['link_url'] = esc_url_raw($data['link_url']);
        }
        if (isset($data['platforms']) && is_array($data['platforms'])) {
            $fields['platforms'] = wp_json_encode(array_map('sanitize_key', $data['platforms']));
        }
        if (isset($data['status'])) {
            $fields['status'] = sanitize_key($data['status']);
        }
        if (array_key_exists('scheduled_at', $data)) {
            $fields['scheduled_at'] = !empty($data['scheduled_at'])
                ? gmdate('Y-m-d H:i:s', strtotime($data['scheduled_at']))
                : null;
        }
        if (isset($data['results'])) {
            $fields['results'] = wp_json_encode($data['results']);
        }
        if (isset($data['published_at'])) {
            $fields['published_at'] = $data['published_at'];
        }

        if (empty($fields)) {
            return false;
        }

        return (bool) $wpdb->update(self::table(), $fields, ['id' => (int) $id]);
    }

    /** @return object|null */
    public static function get($id) {
        self::ensure_table();
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id));
        return self::hydrate($row);
    }

    /** @return array<int,object> */
    public static function list($args = []) {
        self::ensure_table();
        global $wpdb;

        $status = isset($args['status']) ? sanitize_key($args['status']) : '';
        $limit = min(100, max(1, (int) ($args['limit'] ?? 50)));
        $table = self::table();

        if ($status !== '') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s ORDER BY created_at DESC LIMIT %d",
                $status,
                $limit
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
                $limit
            ));
        }

        return array_map([__CLASS__, 'hydrate'], $rows ?: []);
    }

    /** @return array<int,object> */
    public static function due_scheduled() {
        self::ensure_table();
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'scheduled' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT 10",
            $now
        ));
        return array_map([__CLASS__, 'hydrate'], $rows ?: []);
    }

    /** @param object|null $row */
    private static function hydrate($row) {
        if (!$row) {
            return null;
        }
        $row->platforms = json_decode($row->platforms ?: '[]', true) ?: [];
        $row->results = json_decode($row->results ?: '{}', true) ?: [];
        return $row;
    }
}
