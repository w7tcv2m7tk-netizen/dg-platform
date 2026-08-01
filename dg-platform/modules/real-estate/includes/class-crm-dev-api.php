<?php
/**
 * Authenticated CRM REST endpoints for Cursor MCP / dev tooling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_CRM_Dev_API {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/leads/summary', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_summary'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 30,
                    'minimum' => 1,
                    'maximum' => 365,
                ],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/leads/vendor', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_vendor_leads'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => self::list_args(),
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/leads/vendor/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_vendor_lead'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/leads/buyer', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_buyer_leads'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => self::list_args(),
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/bookings/recent', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_recent_bookings'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);
    }

    public static function can_access($request) {
        if (!class_exists('DG_Dev_API')) {
            return false;
        }
        return DG_Dev_API::verify_request($request);
    }

    public static function get_summary($request) {
        if (!class_exists('DG_RE_Pipeline_Reports')) {
            return new WP_Error('unavailable', 'Pipeline reports unavailable.', ['status' => 503]);
        }

        $days = (int) $request->get_param('days');

        return rest_ensure_response([
            'site' => home_url(),
            'generated_at' => current_time('mysql'),
            'period_days' => $days,
            'property_reports_this_month' => DG_RE_Pipeline_Reports::property_reports_this_month(),
            'bookings_this_month' => DG_RE_Pipeline_Reports::bookings_this_month(),
            'vendor_conversion' => DG_RE_Pipeline_Reports::vendor_conversion_summary(),
            'recent_activity' => DG_RE_Pipeline_Reports::recent_activity_summary($days),
            'vendor_pipeline' => DG_RE_Pipeline_Reports::vendor_stage_counts(),
            'vendor_sources' => self::format_source_rows(DG_RE_Pipeline_Reports::vendor_source_counts()),
            'buyer_pipeline' => DG_RE_Pipeline_Reports::buyer_stage_counts(),
        ]);
    }

    public static function list_vendor_leads($request) {
        if (!class_exists('DG_RE_Vendor_Leads')) {
            return new WP_Error('unavailable', 'Vendor leads unavailable.', ['status' => 503]);
        }

        $leads = DG_RE_Vendor_Leads::list([
            'status' => $request->get_param('status') ?: null,
            'source' => $request->get_param('source') ?: null,
            'stage' => $request->get_param('stage') ?: null,
            'assigned_to' => $request->get_param('assigned_to') ? (int) $request->get_param('assigned_to') : null,
            'limit' => (int) ($request->get_param('limit') ?: 25),
            'offset' => (int) ($request->get_param('offset') ?: 0),
        ]);

        return rest_ensure_response([
            'total_returned' => count($leads),
            'leads' => array_map([__CLASS__, 'format_vendor_lead'], $leads),
        ]);
    }

    public static function get_vendor_lead($request) {
        if (!class_exists('DG_RE_Vendor_Leads')) {
            return new WP_Error('unavailable', 'Vendor leads unavailable.', ['status' => 503]);
        }

        $lead = DG_RE_Vendor_Leads::get((int) $request['id']);
        if (!$lead) {
            return new WP_Error('not_found', 'Vendor lead not found.', ['status' => 404]);
        }

        return rest_ensure_response(self::format_vendor_lead($lead, true));
    }

    public static function list_buyer_leads($request) {
        if (!class_exists('DG_RE_Buyer_Leads')) {
            return new WP_Error('unavailable', 'Buyer leads unavailable.', ['status' => 503]);
        }

        $leads = DG_RE_Buyer_Leads::list([
            'assigned_to' => $request->get_param('assigned_to') ? (int) $request->get_param('assigned_to') : null,
            'limit' => (int) ($request->get_param('limit') ?: 25),
            'offset' => (int) ($request->get_param('offset') ?: 0),
        ]);

        return rest_ensure_response([
            'total_returned' => count($leads),
            'leads' => array_map([__CLASS__, 'format_buyer_lead'], $leads),
        ]);
    }

    public static function list_recent_bookings($request) {
        global $wpdb;

        $table = $wpdb->prefix . 'roe_crm_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return rest_ensure_response(['total_returned' => 0, 'bookings' => []]);
        }

        $limit = (int) $request->get_param('limit');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.email, c.phone
             FROM $table b
             LEFT JOIN {$wpdb->prefix}roe_crm_contacts c ON b.contact_id = c.id
             ORDER BY b.booking_date DESC, b.booking_time DESC
             LIMIT %d",
            $limit
        ));

        $bookings = [];
        foreach ($rows as $row) {
            $bookings[] = [
                'id' => (int) $row->id,
                'contact' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'email' => self::format_email($row->email ?? ''),
                'phone' => $row->phone ?? '',
                'service' => $row->service_name,
                'type' => $row->booking_type,
                'date' => $row->booking_date,
                'time' => $row->booking_time,
                'status' => $row->status,
                'created_at' => $row->created_at,
            ];
        }

        return rest_ensure_response([
            'total_returned' => count($bookings),
            'bookings' => $bookings,
        ]);
    }

    private static function list_args() {
        return [
            'status' => ['type' => 'string'],
            'source' => ['type' => 'string'],
            'stage' => ['type' => 'string'],
            'assigned_to' => ['type' => 'integer'],
            'limit' => [
                'type' => 'integer',
                'default' => 25,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'offset' => [
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0,
            ],
        ];
    }

    private static function format_source_rows($rows) {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'source' => $row->source,
                'count' => (int) $row->total,
            ];
        }
        return $out;
    }

    private static function format_vendor_lead($lead, $detailed = false) {
        $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
        $formatted = [
            'id' => (int) $lead->id,
            'name' => $name !== '' ? $name : 'Unknown',
            'email' => self::format_email($lead->email ?? ''),
            'phone' => $lead->phone ?? '',
            'property_address' => $lead->property_address ?? '',
            'source' => $lead->source ?? '',
            'stage' => $lead->stage ?? 'vendor_lead',
            'status' => $lead->status ?? '',
            'assigned_to' => isset($lead->assigned_to) ? (int) $lead->assigned_to : null,
            'created_at' => $lead->created_at ?? '',
        ];

        if ($detailed) {
            $formatted['notes'] = $lead->notes ?? '';
            $formatted['pipeline_id'] = isset($lead->pipeline_record_id) ? (int) $lead->pipeline_record_id : null;
            $formatted['contact_id'] = isset($lead->dg_contact_id) ? (int) $lead->dg_contact_id : null;
        }

        return $formatted;
    }

    private static function format_buyer_lead($lead) {
        $meta = json_decode($lead->pipeline_metadata ?? '{}', true);
        $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));

        return [
            'id' => (int) $lead->id,
            'name' => $name !== '' ? $name : 'Unknown',
            'email' => self::format_email($lead->email ?? ''),
            'phone' => $lead->phone ?? '',
            'property_address' => $meta['property_address'] ?? '',
            'property_url' => $meta['property_url'] ?? '',
            'requirements' => $lead->requirements ?? '',
            'stage' => $lead->stage ?? 'inquiry',
            'status' => $lead->status ?? '',
            'assigned_to' => isset($lead->assigned_to) ? (int) $lead->assigned_to : null,
            'created_at' => $lead->created_at ?? '',
        ];
    }

    private static function format_email($email) {
        if (class_exists('DG_RE_Contacts')) {
            return DG_RE_Contacts::display_email($email);
        }
        if (strpos($email, '@leads.roerealty.local') !== false) {
            return '';
        }
        return $email;
    }
}

add_action('rest_api_init', function () {
    if (class_exists('DG_RE_CRM_Dev_API')) {
        DG_RE_CRM_Dev_API::register_routes();
    }
});
