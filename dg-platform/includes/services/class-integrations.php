<?php
/**
 * Third-party integration stubs.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Integrations {

    /** @return array<string,string> */
    private static function option_map() {
        return [
            'pagespeed' => 'dg_pagespeed_api_key',
            'openai' => 'dg_openai_api_key',
            'gemini' => 'dg_gemini_api_key',
            'twilio_sid' => 'dg_twilio_sid',
            'twilio_token' => 'dg_twilio_token',
            'twilio_from' => 'dg_twilio_from',
            'stripe_secret' => 'dg_stripe_secret_key',
            'gsc' => 'dg_gsc_credentials',
            'gbp' => 'dg_gbp_credentials',
        ];
    }

    public static function get_api_key($service) {
        $keys = self::option_map();
        $option = $keys[$service] ?? 'dg_' . $service . '_api_key';
        return get_option($option, '');
    }

    public static function save_api_key($service, $value) {
        $keys = self::option_map();
        $option = $keys[$service] ?? 'dg_' . $service . '_api_key';
        update_option($option, sanitize_text_field($value));

        // Migrate legacy misnamed option from v10.30.5 and earlier.
        if ($service === 'stripe_secret' && $value !== '') {
            delete_option('dg_stripe_secret_api_key');
        }
    }

    /** @return bool|WP_Error */
    public static function send_sms($to, $message) {
        $sid = self::get_api_key('twilio_sid');
        $token = self::get_api_key('twilio_token');
        $from = self::get_api_key('twilio_from');

        if (!$sid || !$token || !$from) {
            return new WP_Error('twilio_not_configured', 'Twilio is not configured.');
        }

        $response = wp_remote_post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token),
            ],
            'body' => [
                'From' => $from,
                'To' => $to,
                'Body' => $message,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        DG_Activities::log([
            'activity_type' => 'sms',
            'subject' => 'SMS sent',
            'content' => $message,
            'metadata' => ['to' => $to],
        ]);

        return true;
    }

    public static function get_gsc_data($site_url) {
        if (!self::get_api_key('gsc')) {
            return ['available' => false, 'message' => 'Google Search Console not configured.'];
        }
        return apply_filters('dg_integrations_gsc_data', ['available' => true, 'site_url' => $site_url]);
    }

    public static function get_gbp_data($location_id) {
        if (!self::get_api_key('gbp')) {
            return ['available' => false, 'message' => 'Google Business Profile not configured.'];
        }
        return apply_filters('dg_integrations_gbp_data', ['available' => true, 'location_id' => $location_id]);
    }

    public static function get_integration_status() {
        return [
            'pagespeed' => (bool) self::get_api_key('pagespeed'),
            'openai' => (bool) self::get_api_key('openai'),
            'gemini' => (bool) self::get_api_key('gemini'),
            'twilio' => (bool) (self::get_api_key('twilio_sid') && self::get_api_key('twilio_token')),
            'gsc' => (bool) (self::get_api_key('gsc') && apply_filters('dg_integrations_gsc_active', false)),
            'gbp' => (bool) (self::get_api_key('gbp') && apply_filters('dg_integrations_gbp_active', false)),
            'stripe' => (bool) self::get_api_key('stripe_secret'),
        ];
    }

    /**
     * Rich integration hub rows for admin dashboards.
     *
     * @return array<int,array{key:string,label:string,configured:bool,status:string,detail:string,testable:bool}>
     */
    public static function get_hub_rows() {
        $status = self::get_integration_status();
        $smtp_ok = class_exists('DG_Site_Tools_Settings')
            && DG_Site_Tools_Settings::get('smtp_enabled')
            && DG_Site_Tools_Settings::get('smtp_host');

        $indexnow_last = class_exists('DG_SEO_IndexNow') ? DG_SEO_IndexNow::last_site_submit_at() : '';

        $rows = [
            [
                'key' => 'stripe',
                'label' => 'Stripe',
                'configured' => $status['stripe'],
                'status' => $status['stripe'] ? 'connected' : 'not_configured',
                'detail' => $status['stripe'] ? 'Secret key saved' : 'Add secret in API Settings',
                'testable' => false,
            ],
            [
                'key' => 'openai',
                'label' => 'OpenAI',
                'configured' => $status['openai'],
                'status' => $status['openai'] ? 'connected' : 'not_configured',
                'detail' => $status['openai'] ? 'API key saved' : 'Add key in API Settings',
                'testable' => true,
            ],
            [
                'key' => 'gemini',
                'label' => 'Gemini',
                'configured' => $status['gemini'],
                'status' => $status['gemini'] ? 'connected' : 'not_configured',
                'detail' => $status['gemini'] ? 'API key saved' : 'Add key in API Settings',
                'testable' => false,
            ],
            [
                'key' => 'pagespeed',
                'label' => 'PageSpeed',
                'configured' => $status['pagespeed'],
                'status' => $status['pagespeed'] ? 'connected' : 'not_configured',
                'detail' => $status['pagespeed'] ? 'API key saved' : 'Add key in API Settings',
                'testable' => false,
            ],
            [
                'key' => 'twilio',
                'label' => 'Twilio SMS',
                'configured' => $status['twilio'],
                'status' => $status['twilio'] ? 'connected' : 'not_configured',
                'detail' => $status['twilio'] ? 'SID + token saved' : 'Add credentials in API Settings',
                'testable' => false,
            ],
            [
                'key' => 'gsc',
                'label' => 'Google Search Console',
                'configured' => $status['gsc'],
                'status' => $status['gsc'] ? 'connected' : 'not_configured',
                'detail' => $status['gsc'] ? 'Credentials active' : 'Configure in API Settings',
                'testable' => false,
            ],
            [
                'key' => 'gbp',
                'label' => 'Google Business Profile',
                'configured' => $status['gbp'],
                'status' => $status['gbp'] ? 'connected' : 'not_configured',
                'detail' => $status['gbp'] ? 'Credentials active' : 'Configure in API Settings',
                'testable' => false,
            ],
            [
                'key' => 'smtp',
                'label' => 'SMTP / Email',
                'configured' => (bool) $smtp_ok,
                'status' => $smtp_ok ? 'connected' : 'not_configured',
                'detail' => $smtp_ok ? 'SMTP configured in Site Tools' : 'Configure Site Tools → Email',
                'testable' => true,
            ],
            [
                'key' => 'indexnow',
                'label' => 'IndexNow',
                'configured' => class_exists('DG_SEO_IndexNow'),
                'status' => $indexnow_last !== '' ? 'connected' : (class_exists('DG_SEO_IndexNow') ? 'ready' : 'not_configured'),
                'detail' => $indexnow_last !== '' ? 'Last submit: ' . $indexnow_last : 'No URLs submitted yet',
                'testable' => false,
            ],
        ];

        return apply_filters('dg_integrations_hub_rows', $rows);
    }

    public static function test_openai() {
        if (!self::get_api_key('openai')) {
            return new WP_Error('missing_key', 'OpenAI API key not configured.');
        }
        if (!class_exists('DG_AI_Client')) {
            return new WP_Error('missing_client', 'AI client not available.');
        }
        $result = DG_AI_Client::chat('You are a test assistant.', 'Reply with exactly: OK', 10);
        if (is_wp_error($result)) {
            return $result;
        }
        return ['ok' => true, 'message' => 'OpenAI responded successfully.'];
    }
}
