<?php
/**
 * Multi-step workflow runner with delays, webhooks, and template variables.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Automation_Pro_Runner {

    public static function run_workflow($automation, array $context = []) {
        if (!$automation || empty($automation->steps)) {
            return;
        }
        $context = class_exists('DG_Email_Names') ? DG_Email_Names::normalize_context($context) : $context;
        return self::run_from_step($automation, 0, $context);
    }

    public static function run_from_step($automation, $start_index, array $context) {
        $steps = is_array($automation->steps) ? $automation->steps : [];

        for ($i = $start_index; $i < count($steps); $i++) {
            $step = $steps[$i];
            $action = $step['action'] ?? '';

            if ($action === 'delay') {
                $days = max(0, (int) ($step['days'] ?? 0));
                $hours = max(0, (int) ($step['hours'] ?? 0));
                $seconds = ($days * DAY_IN_SECONDS) + ($hours * HOUR_IN_SECONDS);
                if ($seconds <= 0) {
                    continue;
                }
                $max_days = (int) DG_Automation_Pro_Settings::all()['max_delay_days'];
                if ($days > $max_days) {
                    $days = $max_days;
                    $seconds = $days * DAY_IN_SECONDS;
                }
                DG_Automation_Pro_Queue::enqueue(
                    $automation->id,
                    $i + 1,
                    $context,
                    date('Y-m-d H:i:s', time() + $seconds)
                );
                return true;
            }

            $result = self::execute_step($step, $context, $automation);
            self::log_step($automation, $i, $context, $result);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $step @param array<string,mixed> $context */
    public static function execute_step($step, array $context, $automation = null) {
        $action = $step['action'] ?? '';
        $step = self::replace_tokens($step, $context);

        switch ($action) {
            case 'send_email':
                $to = $step['to'] ?? ($context['email'] ?? '');
                if (!$to) {
                    return new WP_Error('no_recipient', 'No email recipient.');
                }
                wp_mail($to, $step['subject'] ?? 'Notification', $step['message'] ?? '');
                DG_Activities::log([
                    'contact_id' => $context['contact_id'] ?? null,
                    'activity_type' => 'email',
                    'subject' => $step['subject'] ?? 'Automation email',
                    'content' => $step['message'] ?? '',
                    'metadata' => ['automation_id' => $automation->id ?? null],
                ]);
                return true;

            case 'send_sms':
                return DG_Integrations::send_sms($step['to'] ?? '', $step['message'] ?? '');

            case 'create_task':
                return DG_Tasks::create(array_merge([
                    'title' => $step['title'] ?? 'Automation task',
                    'description' => $step['description'] ?? '',
                    'assigned_to' => $step['assigned_to'] ?? null,
                    'priority' => $step['priority'] ?? 'normal',
                ], $context));

            case 'create_activity':
                DG_Activities::log([
                    'contact_id' => $context['contact_id'] ?? null,
                    'entity_type' => $context['entity_type'] ?? null,
                    'entity_id' => $context['entity_id'] ?? null,
                    'activity_type' => $step['activity_type'] ?? 'note',
                    'subject' => $step['subject'] ?? 'Automation note',
                    'content' => $step['content'] ?? '',
                ]);
                return true;

            case 'notify_manager':
                wp_mail(get_option('admin_email'), $step['subject'] ?? 'DG Automation', $step['message'] ?? '');
                return true;

            case 'webhook':
                if (!DG_Automation_Pro_Settings::all()['webhooks_enabled']) {
                    return new WP_Error('webhooks_disabled', 'Webhooks are disabled.');
                }
                $url = $step['url'] ?? '';
                if (!$url) {
                    return new WP_Error('no_webhook_url', 'Webhook URL missing.');
                }
                $response = wp_remote_post($url, [
                    'timeout' => 20,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => wp_json_encode(array_merge($context, ['step' => $step])),
                ]);
                return is_wp_error($response) ? $response : true;

            case 'assign_user':
                if (!empty($context['contact_id'])) {
                    DG_Contacts::update((int) $context['contact_id'], ['owner_id' => (int) ($step['user_id'] ?? 0)]);
                }
                return true;

            default:
                return apply_filters('dg_automation_pro_execute_step', true, $action, $step, $context, $automation);
        }
    }

    /** @param array<string,mixed> $step @param array<string,mixed> $context @return array<string,mixed> */
    private static function replace_tokens($step, $context) {
        $map = [
            '{{email}}' => $context['email'] ?? '',
            '{{name}}' => class_exists('DG_Email_Names')
                ? DG_Email_Names::first_name($context['name'] ?? ($context['first_name'] ?? ''))
                : ($context['name'] ?? ($context['first_name'] ?? '')),
            '{{phone}}' => $context['phone'] ?? '',
            '{{business}}' => $context['business_name'] ?? get_bloginfo('name'),
            '{{site}}' => home_url('/'),
        ];
        array_walk_recursive($step, function (&$value) use ($map) {
            if (is_string($value)) {
                $value = str_replace(array_keys($map), array_values($map), $value);
            }
        });
        return $step;
    }

    private static function log_step($automation, $step_index, array $context, $result) {
        global $wpdb;
        if (!$automation || empty($automation->id)) {
            return;
        }
        $wpdb->insert(DG_Automation::logs_table(), [
            'automation_id' => $automation->id,
            'entity_type' => $context['entity_type'] ?? null,
            'entity_id' => $context['entity_id'] ?? null,
            'contact_id' => $context['contact_id'] ?? null,
            'step_index' => $step_index,
            'status' => is_wp_error($result) ? 'failed' : 'completed',
            'error_message' => is_wp_error($result) ? $result->get_error_message() : null,
            'processed_at' => current_time('mysql'),
        ]);
    }
}
