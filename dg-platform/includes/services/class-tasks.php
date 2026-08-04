<?php
/**
 * Tasks service.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Tasks {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_tasks';
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
    }

    public static function list($args = []) {
        global $wpdb;
        $defaults = [
            'status' => null,
            'assigned_to' => null,
            'contact_id' => null,
            'entity_type' => null,
            'entity_id' => null,
            'limit' => 100,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);
        $where = ['1=1'];
        $values = [];

        foreach (['status', 'assigned_to', 'contact_id', 'entity_type', 'entity_id'] as $field) {
            if ($args[$field]) {
                $where[] = "$field = " . (is_numeric($args[$field]) && $field !== 'status' && $field !== 'entity_type' ? '%d' : '%s');
                $values[] = $args[$field];
            }
        }

        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY due_date ASC, created_at DESC LIMIT %d OFFSET %d';
        $values[] = (int) $args['limit'];
        $values[] = (int) $args['offset'];

        if ($values) {
            return $wpdb->get_results($wpdb->prepare($sql, $values));
        }
        return $wpdb->get_results($sql);
    }

    public static function count($status = null) {
        global $wpdb;
        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s', $status));
        }
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table());
    }

    public static function create($data) {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'title' => sanitize_text_field($data['title'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => $data['created_by'] ?? get_current_user_id(),
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'priority' => sanitize_text_field($data['priority'] ?? 'normal'),
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'due_date' => $data['due_date'] ?? null,
            'recurrence' => sanitize_text_field($data['recurrence'] ?? ''),
        ]);
        $id = $wpdb->insert_id;
        DG_Activities::log([
            'entity_type' => 'task',
            'entity_id' => $id,
            'contact_id' => $data['contact_id'] ?? null,
            'activity_type' => 'task',
            'subject' => 'Task created',
            'content' => $data['title'] ?? '',
        ]);
        return $id;
    }

    public static function update($id, $data) {
        global $wpdb;
        $fields = [];
        foreach (['title', 'description', 'assigned_to', 'priority', 'status', 'due_date', 'recurrence'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }
        if (isset($data['status']) && $data['status'] === 'completed') {
            $fields['completed_at'] = current_time('mysql');
        }
        if ($fields) {
            $wpdb->update(self::table(), $fields, ['id' => $id]);
        }
        if (isset($data['status']) && $data['status'] === 'completed') {
            do_action('dg_task_completed', $id, self::get($id));
        }
        return $id;
    }

    public static function complete($id) {
        return self::update($id, ['status' => 'completed']);
    }

    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete(self::table(), ['id' => $id]);
    }
}
