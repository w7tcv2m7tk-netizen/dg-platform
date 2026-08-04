<?php
/**
 * Platform automation engine.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Automation {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_automations';
    }

    public static function logs_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_automation_logs';
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
        if ($row) {
            $row->trigger_settings = json_decode($row->trigger_settings, true);
            $row->steps = json_decode($row->steps, true);
        }
        return $row;
    }

    public static function list($module = null) {
        global $wpdb;
        if ($module) {
            return $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE module = %s ORDER BY created_at DESC',
                $module
            ));
        }
        return $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC');
    }

    public static function create($data) {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'module' => sanitize_text_field($data['module'] ?? 'core'),
            'trigger_type' => sanitize_text_field($data['trigger_type'] ?? ''),
            'trigger_settings' => wp_json_encode($data['trigger_settings'] ?? []),
            'steps' => wp_json_encode($data['steps'] ?? []),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return $wpdb->insert_id;
    }

    public static function trigger($trigger_type, $context = []) {
        global $wpdb;
        $automations = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE trigger_type = %s AND is_active = 1',
            $trigger_type
        ));

        foreach ($automations as $automation) {
            self::run($automation, $context);
        }

        do_action('dg_automation_triggered', $trigger_type, $context);
    }

    public static function run($automation, $context = []) {
        $context = class_exists('DG_Email_Names') ? DG_Email_Names::normalize_context($context) : $context;
        if (class_exists('DG_Automation_Pro_Runner') && class_exists('DG_Automation_Pro_Settings') && DG_Automation_Pro_Settings::is_enabled()) {
            $full = is_object($automation) && !empty($automation->id) ? self::get($automation->id) : $automation;
            if ($full) {
                return DG_Automation_Pro_Runner::run_workflow($full, $context);
            }
        }

        global $wpdb;
        $steps = json_decode($automation->steps, true) ?: [];

        foreach ($steps as $index => $step) {
            $log_id = $wpdb->insert(self::logs_table(), [
                'automation_id' => $automation->id,
                'entity_type' => $context['entity_type'] ?? null,
                'entity_id' => $context['entity_id'] ?? null,
                'contact_id' => $context['contact_id'] ?? null,
                'step_index' => $index,
                'status' => 'pending',
            ]);

            $result = self::execute_step($step, $context);

            $wpdb->update(self::logs_table(), [
                'status' => is_wp_error($result) ? 'failed' : 'completed',
                'error_message' => is_wp_error($result) ? $result->get_error_message() : null,
                'processed_at' => current_time('mysql'),
            ], ['id' => $log_id]);
        }
    }

    private static function execute_step($step, $context) {
        $action = $step['action'] ?? '';

        switch ($action) {
            case 'send_email':
                return self::action_send_email($step, $context);
            case 'send_sms':
                return DG_Integrations::send_sms($step['to'] ?? '', $step['message'] ?? '');
            case 'create_task':
                return DG_Tasks::create(array_merge($step, $context));
            case 'assign_user':
                if (!empty($context['entity_type']) && !empty($context['entity_id'])) {
                    DG_Contacts::update($context['contact_id'] ?? $context['entity_id'], ['owner_id' => $step['user_id'] ?? null]);
                }
                return true;
            case 'notify_manager':
                $admin_email = get_option('admin_email');
                wp_mail($admin_email, $step['subject'] ?? 'DG Platform Notification', $step['message'] ?? '');
                return true;
            case 'update_pipeline':
                return apply_filters('dg_automation_update_pipeline', true, $step, $context);
            default:
                return apply_filters('dg_automation_execute_step', true, $action, $step, $context);
        }
    }

    private static function action_send_email($step, $context) {
        $context = class_exists('DG_Email_Names') ? DG_Email_Names::normalize_context($context) : $context;
        $to = $step['to'] ?? ($context['email'] ?? '');
        if (!$to) {
            return new WP_Error('no_recipient', 'No email recipient.');
        }
        $subject = $step['subject'] ?? 'Notification';
        $message = $step['message'] ?? '';
        if (class_exists('DG_Email_Names')) {
            $map = [
                '{{email}}' => $context['email'] ?? '',
                '{{name}}' => DG_Email_Names::first_name($context['name'] ?? ($context['first_name'] ?? '')),
                '{{phone}}' => $context['phone'] ?? '',
            ];
            $subject = str_replace(array_keys($map), array_values($map), $subject);
            $message = str_replace(array_keys($map), array_values($map), $message);
        }
        $headers = $step['headers'] ?? [];
        wp_mail($to, $subject, $message, $headers);
        DG_Activities::log([
            'contact_id' => $context['contact_id'] ?? null,
            'activity_type' => 'email',
            'subject' => $subject,
            'content' => $message,
        ]);
        return true;
    }

    public static function schedule_cron() {
        if (!wp_next_scheduled('dg_process_automations')) {
            wp_schedule_event(time(), 'every_minute', 'dg_process_automations');
        }
    }

    public static function delete($id) {
        global $wpdb;
        $id = (int) $id;
        $wpdb->delete(self::logs_table(), ['automation_id' => $id]);
        return $wpdb->delete(self::table(), ['id' => $id]);
    }
}

add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['every_minute'])) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => 'Every Minute',
        ];
    }
    return $schedules;
});

add_action('dg_process_automations', function () {
    do_action('dg_automation_cron');
});
