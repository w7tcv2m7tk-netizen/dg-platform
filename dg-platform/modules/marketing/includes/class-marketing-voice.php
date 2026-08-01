<?php
/**
 * Vapi voice agent webhook (replaces legacy dg-webhook plugin).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Voice {

    public static function register_routes() {
        register_rest_route('digitalgate/v1', '/voice-agent', [
            'methods' => ['GET', 'POST'],
            'callback' => [__CLASS__, 'handle_request'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_request($request) {
        if ($request->get_method() === 'GET') {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'DG Platform voice agent webhook is active.',
                'site' => class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'DG Platform',
            ], 200);
        }

        $data = $request->get_json_params();
        if (!$data) {
            $data = json_decode($request->get_body(), true);
        }
        if (!is_array($data)) {
            $data = [];
        }

        $email = sanitize_email($data['email'] ?? '');
        if ($email === '') {
            return new WP_REST_Response(['success' => false, 'message' => 'Email required'], 400);
        }

        $company_id = DG_Marketing_Clients::upsert_lead_company([
            'email' => $email,
            'name' => sanitize_text_field($data['name'] ?? ''),
            'business_name' => sanitize_text_field($data['business_name'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'website_url' => esc_url_raw($data['website_url'] ?? ''),
            'agency_location' => sanitize_text_field($data['agency_location'] ?? ''),
            'source' => 'voice_agent',
            'status' => 'lead',
        ]);

        self::save_company_meta($company_id, $data);

        $score = self::calculate_lead_score($data);
        $qualified = self::is_qualified($data, $score);
        $quality = $score >= 70 ? 'hot' : ($score >= 50 ? 'warm' : 'cold');

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'dg_platform_voice_logs', [
            'company_id' => $company_id,
            'call_summary' => sanitize_textarea_field($data['ai_call_summary'] ?? ''),
            'call_transcript' => sanitize_textarea_field($data['ai_transcript'] ?? ''),
            'lead_score' => $score,
            'is_qualified' => $qualified ? 1 : 0,
            'lead_quality' => $quality,
            'call_data' => wp_json_encode($data),
            'created_at' => current_time('mysql'),
        ]);

        do_action('dg_marketing_voice_lead_created', $company_id, $data, $score, $qualified);

        if (class_exists('DG_Permissions')) {
            DG_Permissions::log_audit('marketing_voice_lead', 'organisation', DG_Marketing_Clients::get_org_id($company_id), null, [
                'score' => $score,
                'qualified' => $qualified,
            ]);
        }

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 'organisation',
                'entity_id' => $company_id,
                'activity_type' => 'voice_lead',
                'subject' => 'AI voice lead captured',
                'content' => sanitize_text_field($data['name'] ?? $email),
                'metadata' => [
                    'lead_score' => $score,
                    'qualified' => $qualified,
                    'service_interest' => sanitize_text_field($data['service_interest'] ?? ''),
                ],
            ]);
        }

        return new WP_REST_Response([
            'success' => true,
            'company_id' => $company_id,
            'lead_score' => $score,
            'is_qualified' => $qualified,
            'lead_quality' => $quality,
            'booking_url' => self::booking_url($data),
        ], 200);
    }

    private static function save_company_meta($company_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_platform_company_meta';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }

        $fields = [
            'service_interest', 'budget_range', 'existing_agency', 'preferred_contact_time',
            'agency_location', 'agency_size', 'lead_sources', 'appraisal_volume', 'listing_volume',
            'current_marketing', 'website_performance', 'seo_performance', 'ai_visibility', 'growth_goals',
            'website_url', 'business_name',
        ];

        foreach ($fields as $key) {
            if (empty($data[$key])) {
                continue;
            }
            $wpdb->replace($table, [
                'company_id' => (int) $company_id,
                'meta_key' => $key,
                'meta_value' => sanitize_text_field(is_string($data[$key]) ? $data[$key] : wp_json_encode($data[$key])),
            ]);
        }
    }

    public static function calculate_lead_score($data) {
        $score = 0;
        if (!empty($data['business_name'])) {
            $score += 10;
        }
        if (!empty($data['website_url'])) {
            $score += 10;
        }
        if (!empty($data['service_interest'])) {
            $score += 15;
        }
        if (!empty($data['budget_range'])) {
            $score += 15;
        }
        if (!empty($data['agency_size'])) {
            $score += 10;
        }

        $summary = strtolower($data['ai_call_summary'] ?? '');
        foreach (['growth', 'increase', 'more', 'better', 'expand', 'scale'] as $keyword) {
            if (strpos($summary, $keyword) !== false) {
                $score += 20;
                break;
            }
        }

        if (!empty($data['ai_visibility']) && strtolower($data['ai_visibility']) !== 'no') {
            $score += 10;
        }
        if (!empty($data['current_marketing'])) {
            $score += 10;
        }

        return min($score, 100);
    }

    public static function is_qualified($data, $score = null) {
        if ($score === null) {
            $score = self::calculate_lead_score($data);
        }
        return $score >= 50;
    }

    public static function booking_url($data) {
        $service = strtolower($data['service_interest'] ?? '');
        $base = 'https://digitalgate.com.au/book/';

        if (strpos($service, 'seo') !== false || strpos($service, 'visibility') !== false) {
            return $base . 'seo-audit';
        }
        if (strpos($service, 'marketing') !== false || strpos($service, 'lead') !== false) {
            return $base . 'marketing-consultation';
        }
        if (strpos($service, 'appraisal') !== false || strpos($service, 'listing') !== false) {
            return $base . 'appraisal-optimization';
        }
        return $base . 'agency-growth-audit';
    }
}
