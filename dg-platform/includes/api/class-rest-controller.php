<?php
/**
 * Unified REST API controller — digitalgate/v1
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_REST_Controller {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/contacts', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'list_contacts'], 'permission_callback' => [__CLASS__, 'can_view_contacts']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_contact'], 'permission_callback' => [__CLASS__, 'can_manage_contacts']],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/contacts/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'get_contact'], 'permission_callback' => [__CLASS__, 'can_view_contacts']],
            ['methods' => 'PUT,PATCH', 'callback' => [__CLASS__, 'update_contact'], 'permission_callback' => [__CLASS__, 'can_manage_contacts']],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'delete_contact'], 'permission_callback' => [__CLASS__, 'can_manage_contacts']],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/tasks', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'list_tasks'], 'permission_callback' => [__CLASS__, 'can_view_tasks']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_task'], 'permission_callback' => [__CLASS__, 'can_manage_tasks']],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/calendar', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'list_calendar'], 'permission_callback' => [__CLASS__, 'can_view_calendar']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_calendar_event'], 'permission_callback' => [__CLASS__, 'can_manage_calendar']],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/activities', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'list_activities'], 'permission_callback' => [__CLASS__, 'can_view_activities']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_activity'], 'permission_callback' => [__CLASS__, 'can_manage_activities']],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_stats'],
            'permission_callback' => [__CLASS__, 'can_access_platform'],
        ]);

        // Legacy route aliases
        register_rest_route('dg/v1', '/audit-webhook', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'legacy_audit_webhook'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/audit-webhook', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'legacy_audit_webhook'],
            'permission_callback' => '__return_true',
        ]);

        do_action('dg_platform_register_rest_routes');
    }

    public static function can_access_platform() {
        return DG_Permissions::current_user_can('dg_access_platform') || current_user_can('manage_options');
    }

    public static function can_view_contacts() {
        return DG_Permissions::current_user_can('dg_view_contacts');
    }

    public static function can_manage_contacts() {
        return DG_Permissions::current_user_can('dg_manage_contacts');
    }

    public static function can_view_tasks() {
        return DG_Permissions::current_user_can('dg_view_tasks');
    }

    public static function can_manage_tasks() {
        return DG_Permissions::current_user_can('dg_manage_tasks');
    }

    public static function can_view_calendar() {
        return DG_Permissions::current_user_can('dg_view_calendar');
    }

    public static function can_manage_calendar() {
        return DG_Permissions::current_user_can('dg_manage_calendar');
    }

    public static function can_view_activities() {
        return DG_Permissions::current_user_can('dg_view_activities');
    }

    public static function can_manage_activities() {
        return DG_Permissions::current_user_can('dg_manage_activities');
    }

    public static function list_contacts($request) {
        $contacts = DG_Contacts::list([
            'search' => $request->get_param('search'),
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('limit') ?: 100,
        ]);
        return rest_ensure_response($contacts);
    }

    public static function get_contact($request) {
        $contact = DG_Contacts::get((int) $request['id']);
        if (!$contact) {
            return new WP_Error('not_found', 'Contact not found.', ['status' => 404]);
        }
        return rest_ensure_response($contact);
    }

    public static function create_contact($request) {
        $id = DG_Contacts::create($request->get_json_params() ?: $request->get_params());
        if (is_wp_error($id)) {
            return $id;
        }
        return rest_ensure_response(['id' => $id, 'contact' => DG_Contacts::get($id)]);
    }

    public static function update_contact($request) {
        $id = (int) $request['id'];
        DG_Contacts::update($id, $request->get_json_params() ?: $request->get_params());
        return rest_ensure_response(DG_Contacts::get($id));
    }

    public static function delete_contact($request) {
        DG_Contacts::delete((int) $request['id']);
        return rest_ensure_response(['deleted' => true]);
    }

    public static function list_tasks($request) {
        return rest_ensure_response(DG_Tasks::list([
            'status' => $request->get_param('status'),
            'assigned_to' => $request->get_param('assigned_to'),
        ]));
    }

    public static function create_task($request) {
        $id = DG_Tasks::create($request->get_json_params() ?: $request->get_params());
        return rest_ensure_response(['id' => $id, 'task' => DG_Tasks::get($id)]);
    }

    public static function list_calendar($request) {
        return rest_ensure_response(DG_Calendar::list([
            'start' => $request->get_param('start'),
            'end' => $request->get_param('end'),
            'event_type' => $request->get_param('event_type'),
        ]));
    }

    public static function create_calendar_event($request) {
        $id = DG_Calendar::create($request->get_json_params() ?: $request->get_params());
        return rest_ensure_response(['id' => $id, 'event' => DG_Calendar::get($id)]);
    }

    public static function list_activities($request) {
        if ($request->get_param('contact_id')) {
            return rest_ensure_response(DG_Activities::get_for_contact((int) $request->get_param('contact_id')));
        }
        return rest_ensure_response(DG_Activities::recent((int) ($request->get_param('limit') ?: 20)));
    }

    public static function create_activity($request) {
        $id = DG_Activities::log($request->get_json_params() ?: $request->get_params());
        return rest_ensure_response(['id' => $id]);
    }

    public static function get_stats() {
        return rest_ensure_response(DG_Reports::get_dashboard_stats());
    }

    public static function legacy_audit_webhook($request) {
        $core = class_exists('DG_Platform') ? DG_Platform::get_instance() : null;
        $marketing = $core ? $core->get_module('marketing') : null;
        if ($marketing && method_exists($marketing, 'handle_audit_webhook')) {
            return $marketing->handle_audit_webhook($request);
        }

        return apply_filters('dg_legacy_audit_webhook', new WP_REST_Response([
            'success' => false,
            'message' => 'Marketing module is not active',
        ], 503), $request);
    }
}

add_action('rest_api_init', ['DG_REST_Controller', 'register_routes']);
