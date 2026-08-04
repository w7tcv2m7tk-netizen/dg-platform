<?php
/**
 * Workflow templates and CRUD helpers.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Automation_Pro_Workflows {

    /** @return array<string,array<string,mixed>> */
    public static function templates() {
        $site = class_exists('DG_Site_Profile') ? DG_Site_Profile::primary_module() : 'core';

        $all = [
            'welcome_contact' => [
                'label' => 'Welcome new contact',
                'module' => 'core',
                'trigger_type' => 'contact_created',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Thanks for getting in touch', 'message' => "Hi {{name}},\n\nThanks for contacting us. We'll be in touch shortly.\n\n— {{business}}"],
                    ['action' => 'create_task', 'title' => 'Follow up with {{name}}', 'priority' => 'high', 'description' => 'New contact from website'],
                ],
            ],
            'vendor_nurture' => [
                'label' => 'Vendor lead nurture (3-step)',
                'module' => 'real-estate',
                'trigger_type' => 'vendor_lead_created',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Your property appraisal request', 'message' => "Hi {{name}},\n\nThanks for your appraisal request. We'll contact you within 24 hours."],
                    ['action' => 'delay', 'days' => 2],
                    ['action' => 'create_task', 'title' => 'Call vendor lead {{name}}', 'priority' => 'high'],
                    ['action' => 'delay', 'days' => 5],
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Still thinking about selling?', 'message' => "Hi {{name}},\n\nJust checking in — happy to answer any questions about your property appraisal."],
                ],
            ],
            'buyer_followup' => [
                'label' => 'Buyer inquiry follow-up',
                'module' => 'real-estate',
                'trigger_type' => 'buyer_lead_created',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Thanks for your inquiry', 'message' => "Hi {{name}},\n\nWe received your buyer inquiry and will match you with suitable properties."],
                    ['action' => 'create_task', 'title' => 'Qualify buyer {{name}}', 'priority' => 'normal'],
                ],
            ],
            'booking_confirmation' => [
                'label' => 'Accommodation booking confirmation',
                'module' => 'accommodation',
                'trigger_type' => 'acc_booking_created',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Booking confirmed', 'message' => "Hi {{name}},\n\nYour stay is confirmed. We look forward to welcoming you."],
                    ['action' => 'delay', 'days' => 3],
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Pre-arrival information', 'message' => "Hi {{name}},\n\nYour check-in details and directions are on our website."],
                ],
            ],
            'audit_followup' => [
                'label' => 'Agency audit follow-up',
                'module' => 'marketing',
                'trigger_type' => 'audit_completed',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Your visibility audit results', 'message' => "Hi {{name}},\n\nYour audit is ready. Book a strategy call to discuss next steps."],
                    ['action' => 'delay', 'days' => 3],
                    ['action' => 'create_task', 'title' => 'Call audit lead {{name}}', 'priority' => 'high'],
                ],
            ],
            'finance_application' => [
                'label' => 'Finance application follow-up',
                'module' => 'finance',
                'trigger_type' => 'fin_application_created',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Your loan application', 'message' => "Hi {{name}},\n\nThanks for your loan inquiry. We'll review your application and be in touch shortly."],
                    ['action' => 'create_task', 'title' => 'Review finance application — {{name}}', 'priority' => 'high'],
                ],
            ],
            'service_job_quote' => [
                'label' => 'Service job quote follow-up',
                'module' => 'services',
                'trigger_type' => 'svc_job_created',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Your service request', 'message' => "Hi {{name}},\n\nWe received your service request and will send a quote shortly."],
                    ['action' => 'create_task', 'title' => 'Prepare quote for {{name}}', 'priority' => 'normal'],
                ],
            ],
            'dealer_test_drive' => [
                'label' => 'Automotive lead follow-up',
                'module' => 'dealership',
                'trigger_type' => 'dealer_lead_created',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Thanks for your inquiry', 'message' => "Hi {{name}},\n\nThanks for your interest. We'll contact you to arrange a test drive or discuss your options."],
                    ['action' => 'create_task', 'title' => 'Follow up automotive lead {{name}}', 'priority' => 'high'],
                ],
            ],
            'commercial_tenancy' => [
                'label' => 'Commercial tenancy inquiry',
                'module' => 'commercial',
                'trigger_type' => 'com_tenancy_created',
                'steps' => [
                    ['action' => 'send_email', 'to' => '{{email}}', 'subject' => 'Your commercial inquiry', 'message' => "Hi {{name}},\n\nThanks for your interest in our commercial space. We'll arrange an inspection at your convenience."],
                    ['action' => 'create_task', 'title' => 'Schedule inspection — {{name}}', 'priority' => 'high'],
                ],
            ],
        ];

        $filtered = [];
        foreach ($all as $key => $tpl) {
            if ($tpl['module'] === 'core' || $tpl['module'] === $site) {
                $filtered[$key] = $tpl;
            }
        }

        return apply_filters('dg_automation_pro/templates', $filtered);
    }

    public static function install_template($key) {
        $templates = self::templates();
        if (!isset($templates[$key])) {
            return new WP_Error('invalid_template', 'Template not found.');
        }
        $tpl = $templates[$key];
        $existing = DG_Automation::list($tpl['module']);
        foreach ($existing as $row) {
            if ($row->trigger_type === $tpl['trigger_type'] && $row->name === $tpl['label']) {
                return (int) $row->id;
            }
        }

        return DG_Automation::create([
            'name' => $tpl['label'],
            'module' => $tpl['module'],
            'trigger_type' => $tpl['trigger_type'],
            'steps' => $tpl['steps'],
            'is_active' => 1,
        ]);
    }

    public static function update_steps($automation_id, array $steps) {
        global $wpdb;
        return $wpdb->update(
            DG_Automation::table(),
            ['steps' => wp_json_encode(array_values($steps))],
            ['id' => (int) $automation_id]
        );
    }

    /** @return array<string,int> */
    public static function log_stats($days = 30) {
        global $wpdb;
        $table = DG_Automation::logs_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(status = 'completed') AS completed,
                SUM(status = 'failed') AS failed,
                SUM(status = 'pending') AS pending,
                COUNT(*) AS total
             FROM $table WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            (int) $days
        ));
        return [
            'completed' => (int) ($row->completed ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    public static function available_triggers() {
        return apply_filters('dg_automation_pro/triggers', [
            'contact_created' => 'New contact created',
            'vendor_lead_created' => 'Vendor lead submitted',
            'buyer_lead_created' => 'Buyer inquiry submitted',
            'booking_created' => 'Appraisal booking created',
            'acc_booking_created' => 'Accommodation booking created',
            'audit_completed' => 'Agency audit completed',
            'task_completed' => 'Task marked complete',
            'form_submitted' => 'Contact form submitted',
            'fin_application_created' => 'Finance application created',
            'svc_job_created' => 'Service job created',
            'dealer_lead_created' => 'Automotive lead created',
            'com_tenancy_created' => 'Commercial tenancy lead created',
        ]);
    }

    public static function available_actions() {
        return [
            'send_email' => 'Send email',
            'send_sms' => 'Send SMS',
            'create_task' => 'Create task',
            'create_activity' => 'Log activity',
            'notify_manager' => 'Notify admin',
            'assign_user' => 'Assign to user',
            'delay' => 'Wait (delay)',
            'webhook' => 'Webhook POST',
        ];
    }
}
