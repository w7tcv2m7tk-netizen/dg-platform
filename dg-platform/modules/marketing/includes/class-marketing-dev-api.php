<?php
/**
 * Marketing CRM REST endpoints for Cursor MCP / dev tooling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Dev_API {

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/marketing/summary', [
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

        register_rest_route(DG_REST_NAMESPACE, '/marketing/clients', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_clients'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => self::list_args(),
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/marketing/clients/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_client'],
            'permission_callback' => [__CLASS__, 'can_access'],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/marketing/voice-leads', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_voice_leads'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 25,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/marketing/audits', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_audits'],
            'permission_callback' => [__CLASS__, 'can_access'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 25,
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
        global $wpdb;

        $days = (int) $request->get_param('days');
        $since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $companies = $wpdb->prefix . 'dg_platform_companies';
        $audits = $wpdb->prefix . 'dg_platform_audits';
        $voice = $wpdb->prefix . 'dg_platform_voice_logs';
        $automation = $wpdb->prefix . 'dg_automation_audit_emails';

        $summary = [
            'site' => home_url(),
            'site_profile' => class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'DG Platform',
            'generated_at' => current_time('mysql'),
            'period_days' => $days,
            'clients_total' => 0,
            'clients_leads' => 0,
            'clients_active' => 0,
            'audits_total' => 0,
            'audits_this_period' => 0,
            'voice_leads_total' => 0,
            'voice_leads_this_period' => 0,
            'voice_qualified_this_period' => 0,
            'automation_pending' => 0,
            'automation_sent' => 0,
            'clients_by_source' => [],
            'recent_clients' => [],
        ];

        if ($wpdb->get_var("SHOW TABLES LIKE '$companies'") === $companies) {
            $summary['clients_total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $companies");
            $summary['clients_leads'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $companies WHERE status = 'lead'");
            $summary['clients_active'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $companies WHERE status = 'active'");
            $summary['clients_by_source'] = $wpdb->get_results(
                "SELECT source, COUNT(*) AS total FROM $companies GROUP BY source ORDER BY total DESC LIMIT 10",
                ARRAY_A
            );
            $summary['recent_clients'] = array_map(
                [__CLASS__, 'format_client'],
                $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $companies WHERE created_at >= %s ORDER BY created_at DESC LIMIT 10",
                    $since
                ))
            );
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$audits'") === $audits) {
            $summary['audits_total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $audits");
            $summary['audits_this_period'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $audits WHERE audit_date >= %s",
                $since
            ));
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$voice'") === $voice) {
            $summary['voice_leads_total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $voice");
            $summary['voice_leads_this_period'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $voice WHERE created_at >= %s",
                $since
            ));
            $summary['voice_qualified_this_period'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $voice WHERE created_at >= %s AND is_qualified = 1",
                $since
            ));
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$automation'") === $automation) {
            $summary['automation_pending'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $automation WHERE status = 'pending'");
            $summary['automation_sent'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $automation WHERE status = 'sent'");
        }

        return rest_ensure_response($summary);
    }

    public static function list_clients($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_platform_companies';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return rest_ensure_response(['total_returned' => 0, 'clients' => []]);
        }

        $limit = (int) ($request->get_param('limit') ?: 25);
        $offset = (int) ($request->get_param('offset') ?: 0);
        $status = sanitize_text_field($request->get_param('status') ?: '');
        $source = sanitize_text_field($request->get_param('source') ?: '');

        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ($source !== '') {
            $where[] = 'source = %s';
            $params[] = $source;
        }
        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where)
            . " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        return rest_ensure_response([
            'total_returned' => count($rows),
            'clients' => array_map([__CLASS__, 'format_client'], $rows),
        ]);
    }

    public static function get_client($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $table = $wpdb->prefix . 'dg_platform_companies';
        $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$client) {
            return new WP_Error('not_found', 'Client not found.', ['status' => 404]);
        }

        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dg_platform_contacts WHERE company_id = %d",
            $id
        ));
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dg_platform_notes WHERE company_id = %d ORDER BY created_at DESC LIMIT 20",
            $id
        ));
        $audits = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dg_platform_audits WHERE company_id = %d ORDER BY audit_date DESC LIMIT 10",
            $id
        ));

        $formatted = self::format_client($client, true);
        $formatted['contacts'] = array_map([__CLASS__, 'format_contact'], $contacts);
        $formatted['notes'] = array_map(function ($note) {
            return [
                'id' => (int) $note->id,
                'content' => $note->content,
                'type' => $note->type ?? 'note',
                'created_at' => $note->created_at,
            ];
        }, $notes);
        $formatted['audits'] = array_map([__CLASS__, 'format_audit'], $audits);

        return rest_ensure_response($formatted);
    }

    public static function list_voice_leads($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_platform_voice_logs';
        $companies = $wpdb->prefix . 'dg_platform_companies';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return rest_ensure_response(['total_returned' => 0, 'voice_leads' => []]);
        }

        $limit = (int) $request->get_param('limit');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, c.company_name, c.email AS company_email
             FROM $table l
             LEFT JOIN $companies c ON l.company_id = c.id
             ORDER BY l.created_at DESC
             LIMIT %d",
            $limit
        ));

        $leads = [];
        foreach ($rows as $row) {
            $leads[] = [
                'id' => (int) $row->id,
                'company_id' => (int) $row->company_id,
                'company_name' => $row->company_name ?? '',
                'email' => $row->company_email ?? '',
                'lead_score' => (int) $row->lead_score,
                'is_qualified' => (bool) $row->is_qualified,
                'lead_quality' => $row->lead_quality,
                'summary' => $row->call_summary,
                'created_at' => $row->created_at,
            ];
        }

        return rest_ensure_response([
            'total_returned' => count($leads),
            'voice_leads' => $leads,
        ]);
    }

    public static function list_audits($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_platform_audits';
        $companies = $wpdb->prefix . 'dg_platform_companies';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return rest_ensure_response(['total_returned' => 0, 'audits' => []]);
        }

        $limit = (int) $request->get_param('limit');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, c.company_name
             FROM $table a
             LEFT JOIN $companies c ON a.company_id = c.id
             ORDER BY a.audit_date DESC
             LIMIT %d",
            $limit
        ));

        return rest_ensure_response([
            'total_returned' => count($rows),
            'audits' => array_map([__CLASS__, 'format_audit'], $rows),
        ]);
    }

    private static function list_args() {
        return [
            'status' => ['type' => 'string'],
            'source' => ['type' => 'string'],
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

    private static function format_client($client, $detailed = false) {
        $row = [
            'id' => (int) $client->id,
            'company_name' => $client->company_name,
            'email' => $client->email ?? '',
            'phone' => $client->phone ?? '',
            'website' => $client->website ?? '',
            'suburb' => $client->suburb ?? '',
            'status' => $client->status ?? '',
            'source' => $client->source ?? '',
            'created_at' => $client->created_at ?? '',
        ];
        if ($detailed) {
            $row['industry'] = $client->industry ?? '';
            $row['state'] = $client->state ?? '';
            $row['notes'] = $client->notes ?? '';
        }
        return $row;
    }

    private static function format_contact($contact) {
        return [
            'id' => (int) $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'phone' => $contact->phone ?? '',
            'is_primary' => (bool) $contact->is_primary,
        ];
    }

    private static function format_audit($audit) {
        return [
            'id' => (int) $audit->id,
            'company_id' => (int) $audit->company_id,
            'company_name' => $audit->company_name ?? '',
            'overall_score' => (int) $audit->overall_score,
            'grade' => $audit->grade ?? '',
            'ai_score' => (int) $audit->ai_score,
            'website_score' => (int) $audit->website_score,
            'audit_date' => $audit->audit_date,
            'pdf_path' => $audit->pdf_path ?? '',
        ];
    }
}

add_action('rest_api_init', function () {
    if (class_exists('DG_Marketing_Dev_API')) {
        DG_Marketing_Dev_API::register_routes();
    }
});
