<?php
/**
 * Extended automation triggers — hooks into platform events.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Automation_Pro_Triggers {

    public static function init() {
        add_action('dg_contact_created', [__CLASS__, 'on_contact_created'], 10, 2);
        add_action('dg_task_completed', [__CLASS__, 'on_task_completed'], 10, 2);
        add_action('dg_booking_confirmed', [__CLASS__, 'on_acc_booking'], 10, 1);
        add_action('dg_audit_completed', [__CLASS__, 'on_audit_completed'], 10, 2);
        add_action('dg_form_submitted', [__CLASS__, 'on_form_submitted'], 10, 2);
        add_action('dg_fin_application_created', [__CLASS__, 'on_fin_application'], 10, 3);
        add_action('dg_svc_job_created', [__CLASS__, 'on_svc_job'], 10, 3);
        add_action('dg_dealer_lead_created', [__CLASS__, 'on_dealer_lead'], 10, 3);
        add_action('dg_com_tenancy_created', [__CLASS__, 'on_com_tenancy'], 10, 3);
    }

    public static function on_contact_created($contact_id, $data = []) {
        $contact = DG_Contacts::get($contact_id);
        if (!$contact) {
            return;
        }
        DG_Automation::trigger('contact_created', DG_Email_Names::normalize_context([
            'contact_id' => $contact_id,
            'email' => $contact->email,
            'name' => DG_Email_Names::first_name($contact),
            'first_name' => $contact->first_name,
            'phone' => $contact->phone,
            'entity_type' => 'contact',
            'entity_id' => $contact_id,
        ]));
    }

    public static function on_task_completed($task_id, $task = null) {
        DG_Automation::trigger('task_completed', [
            'entity_type' => 'task',
            'entity_id' => $task_id,
        ]);
    }

    public static function on_acc_booking($booking_id) {
        DG_Automation::trigger('acc_booking_created', DG_Email_Names::normalize_context([
            'entity_type' => 'dg_booking',
            'entity_id' => $booking_id,
            'email' => get_post_meta($booking_id, 'dg_booking_email', true),
            'name' => get_post_meta($booking_id, 'dg_booking_name', true),
        ]));
    }

    public static function on_audit_completed($company_id, $data = []) {
        DG_Automation::trigger('audit_completed', DG_Email_Names::normalize_context(array_merge([
            'entity_type' => 'company',
            'entity_id' => $company_id,
            'email' => $data['email'] ?? '',
            'name' => $data['name'] ?? '',
        ], $data)));
    }

    public static function on_form_submitted($form_key, $data = []) {
        DG_Automation::trigger('form_submitted', array_merge(['form' => $form_key], $data));
    }

    private static function contact_context($contact_id, $data = []) {
        $contact = DG_Contacts::get($contact_id);
        return array_merge([
            'contact_id' => $contact_id,
            'email' => $contact->email ?? ($data['email'] ?? ''),
            'name' => $contact ? DG_Email_Names::first_name($contact) : DG_Email_Names::first_name($data['name'] ?? ''),
            'first_name' => $contact->first_name ?? '',
            'phone' => $contact->phone ?? ($data['phone'] ?? ''),
        ], $data);
    }

    public static function on_fin_application($id, $contact_id, $data = []) {
        DG_Automation::trigger('fin_application_created', array_merge(self::contact_context($contact_id, $data), [
            'entity_type' => 'fin_application',
            'entity_id' => $id,
        ]));
    }

    public static function on_svc_job($id, $contact_id, $data = []) {
        DG_Automation::trigger('svc_job_created', array_merge(self::contact_context($contact_id, $data), [
            'entity_type' => 'svc_job',
            'entity_id' => $id,
        ]));
    }

    public static function on_dealer_lead($id, $contact_id, $data = []) {
        DG_Automation::trigger('dealer_lead_created', array_merge(self::contact_context($contact_id, $data), [
            'entity_type' => 'dealer_lead',
            'entity_id' => $id,
        ]));
    }

    public static function on_com_tenancy($id, $contact_id, $data = []) {
        DG_Automation::trigger('com_tenancy_created', array_merge(self::contact_context($contact_id, $data), [
            'entity_type' => 'com_tenancy',
            'entity_id' => $id,
        ]));
    }
}
