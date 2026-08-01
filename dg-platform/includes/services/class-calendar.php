<?php
/**
 * Calendar events service.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Calendar {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_calendar_events';
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
    }

    public static function list($args = []) {
        global $wpdb;
        $defaults = [
            'start' => null,
            'end' => null,
            'assigned_to' => null,
            'event_type' => null,
            'contact_id' => null,
            'limit' => 200,
        ];
        $args = wp_parse_args($args, $defaults);
        $where = ['1=1'];
        $values = [];

        if ($args['start']) {
            $where[] = 'start_at >= %s';
            $values[] = $args['start'];
        }
        if ($args['end']) {
            $where[] = 'start_at <= %s';
            $values[] = $args['end'];
        }
        if ($args['assigned_to']) {
            $where[] = 'assigned_to = %d';
            $values[] = $args['assigned_to'];
        }
        if ($args['event_type']) {
            $where[] = 'event_type = %s';
            $values[] = $args['event_type'];
        }
        if ($args['contact_id']) {
            $where[] = 'contact_id = %d';
            $values[] = $args['contact_id'];
        }

        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY start_at ASC LIMIT %d';
        $values[] = (int) $args['limit'];

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    public static function count_upcoming() {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE start_at >= %s AND status != %s',
            current_time('mysql'),
            'cancelled'
        ));
    }

    public static function create($data) {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'title' => sanitize_text_field($data['title'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'event_type' => sanitize_text_field($data['event_type'] ?? 'meeting'),
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'] ?? null,
            'all_day' => !empty($data['all_day']) ? 1 : 0,
            'assigned_to' => $data['assigned_to'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'status' => sanitize_text_field($data['status'] ?? 'scheduled'),
            'location' => sanitize_text_field($data['location'] ?? ''),
            'metadata' => is_array($data['metadata'] ?? null) ? wp_json_encode($data['metadata']) : ($data['metadata'] ?? null),
        ]);
        $id = $wpdb->insert_id;
        DG_Activities::log([
            'entity_type' => 'calendar_event',
            'entity_id' => $id,
            'contact_id' => $data['contact_id'] ?? null,
            'activity_type' => 'meeting',
            'subject' => 'Event scheduled',
            'content' => $data['title'] ?? '',
        ]);
        return $id;
    }

    public static function update($id, $data) {
        global $wpdb;
        $fields = [];
        foreach (['title', 'description', 'event_type', 'start_at', 'end_at', 'assigned_to', 'contact_id', 'status', 'location'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }
        if ($fields) {
            $wpdb->update(self::table(), $fields, ['id' => $id]);
        }
        return $id;
    }

    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete(self::table(), ['id' => $id]);
    }

    /**
     * Create calendar event from Real Estate booking.
     */
    public static function create_from_booking($booking, $contact_id) {
        $start = $booking->booking_date . ' ' . $booking->booking_time;
        $duration = $booking->duration ?? 30;
        $end = date('Y-m-d H:i:s', strtotime($start) + ($duration * 60));

        return self::create([
            'title' => $booking->service_name,
            'event_type' => $booking->booking_type ?? 'appraisal',
            'start_at' => $start,
            'end_at' => $end,
            'contact_id' => $contact_id,
            'entity_type' => 'booking',
            'entity_id' => $booking->id,
            'status' => $booking->status === 'confirmed' ? 'confirmed' : 'scheduled',
            'metadata' => ['service_name' => $booking->service_name, 'notes' => $booking->notes ?? ''],
        ]);
    }
}
