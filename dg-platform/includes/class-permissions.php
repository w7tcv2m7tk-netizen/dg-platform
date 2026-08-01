<?php
/**
 * Permission engine — capabilities and role templates.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Permissions {

    const CAP_PREFIX = 'dg_';

    public static function register_capabilities() {
        $caps = self::get_all_capabilities();
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($caps as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    public static function get_all_capabilities() {
        $core = [
            'dg_access_platform',
            'dg_manage_platform',
            'dg_view_contacts',
            'dg_manage_contacts',
            'dg_view_organisations',
            'dg_manage_organisations',
            'dg_view_tasks',
            'dg_manage_tasks',
            'dg_view_calendar',
            'dg_manage_calendar',
            'dg_view_activities',
            'dg_manage_activities',
            'dg_view_documents',
            'dg_manage_documents',
            'dg_view_reports',
            'dg_manage_automations',
            'dg_manage_modules',
            'dg_manage_roles',
            'dg_manage_api_keys',
        ];

        $marketing = [
            'dg_marketing_view_clients',
            'dg_marketing_manage_clients',
            'dg_marketing_view_audits',
            'dg_marketing_manage_audits',
            'dg_marketing_view_ai',
            'dg_marketing_manage_ai',
            'dg_marketing_view_voice',
            'dg_marketing_manage_voice',
            'dg_marketing_import_contacts',
        ];

        $real_estate = [
            'dg_re_view_leads',
            'dg_re_manage_leads',
            'dg_re_view_appraisals',
            'dg_re_manage_appraisals',
            'dg_re_view_listings',
            'dg_re_manage_listings',
            'dg_re_view_buyers',
            'dg_re_manage_buyers',
            'dg_re_view_sales',
            'dg_re_manage_sales',
            'dg_re_view_agents',
            'dg_re_manage_agents',
            'dg_re_import_properties',
        ];

        $accommodation = [
            'dg_acc_view_bookings',
            'dg_acc_manage_bookings',
            'dg_acc_view_guests',
            'dg_acc_manage_guests',
        ];

        return array_merge($core, $marketing, $real_estate, $accommodation, apply_filters('dg_platform_capabilities', []));
    }

    public static function install_role_templates() {
        self::ensure_role('dg_sales_agent', 'DG Sales Agent', [
            'read' => true,
            'dg_access_platform' => true,
            'dg_view_contacts' => true,
            'dg_manage_contacts' => true,
            'dg_view_tasks' => true,
            'dg_manage_tasks' => true,
            'dg_view_calendar' => true,
            'dg_manage_calendar' => true,
            'dg_view_activities' => true,
            'dg_manage_activities' => true,
            'dg_re_view_leads' => true,
            'dg_re_manage_leads' => true,
            'dg_re_view_appraisals' => true,
            'dg_re_manage_appraisals' => true,
            'dg_re_view_listings' => true,
            'dg_re_manage_listings' => true,
            'dg_re_view_buyers' => true,
            'dg_re_manage_buyers' => true,
            'dg_re_view_sales' => true,
            'dg_re_manage_sales' => true,
        ]);

        self::ensure_role('dg_reception', 'DG Reception', [
            'read' => true,
            'dg_access_platform' => true,
            'dg_view_contacts' => true,
            'dg_manage_contacts' => true,
            'dg_view_tasks' => true,
            'dg_manage_tasks' => true,
            'dg_view_calendar' => true,
            'dg_manage_calendar' => true,
            'dg_view_activities' => true,
        ]);

        self::ensure_role('dg_marketing', 'DG Marketing', [
            'read' => true,
            'dg_access_platform' => true,
            'dg_view_contacts' => true,
            'dg_view_reports' => true,
            'dg_marketing_view_clients' => true,
            'dg_marketing_manage_clients' => true,
            'dg_marketing_view_audits' => true,
            'dg_marketing_manage_audits' => true,
            'dg_marketing_view_ai' => true,
            'dg_marketing_manage_ai' => true,
            'dg_marketing_view_voice' => true,
            'dg_marketing_manage_voice' => true,
            'dg_marketing_import_contacts' => true,
        ]);
    }

    private static function ensure_role($role_key, $display_name, $caps) {
        $role = get_role($role_key);
        if (!$role) {
            add_role($role_key, $display_name, $caps);
            return;
        }
        foreach ($caps as $cap => $grant) {
            if ($grant) {
                $role->add_cap($cap);
            }
        }
    }

    public static function current_user_can($capability) {
        if (current_user_can('manage_options')) {
            return true;
        }
        return current_user_can($capability);
    }

    public static function menu_cap($default = 'dg_access_platform') {
        return apply_filters('dg_platform_menu_capability', $default);
    }

    public static function get_role_templates() {
        return [
            'dg_sales_agent' => [
                'label' => 'Sales Agent',
                'description' => 'Vendor leads, appraisals, listings, buyers, and sales.',
            ],
            'dg_reception' => [
                'label' => 'Reception',
                'description' => 'Contacts, calendar, and tasks.',
            ],
            'dg_marketing' => [
                'label' => 'Marketing',
                'description' => 'Campaigns, audits, and AI visibility.',
            ],
        ];
    }

    public static function log_audit($action, $entity_type = null, $entity_id = null, $old_value = null, $new_value = null) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'dg_audit_log', [
            'user_id' => get_current_user_id(),
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'old_value' => is_array($old_value) ? wp_json_encode($old_value) : $old_value,
            'new_value' => is_array($new_value) ? wp_json_encode($new_value) : $new_value,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null,
        ]);
    }
}
