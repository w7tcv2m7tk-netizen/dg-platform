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

    public static function get_api_key($service) {
        $keys = [
            'pagespeed' => 'dg_pagespeed_api_key',
            'openai' => 'dg_openai_api_key',
            'gemini' => 'dg_gemini_api_key',
            'twilio_sid' => 'dg_twilio_sid',
            'twilio_token' => 'dg_twilio_token',
            'twilio_from' => 'dg_twilio_from',
            'stripe_secret' => 'dg_stripe_secret_key',
            'rankmath' => 'dg_rankmath_api_key',
            'gsc' => 'dg_gsc_credentials',
            'gbp' => 'dg_gbp_credentials',
            'fluentcrm' => 'dg_fluentcrm_api_key',
        ];
        $option = $keys[$service] ?? 'dg_' . $service . '_api_key';
        return get_option($option, '');
    }

    public static function save_api_key($service, $value) {
        $option = 'dg_' . $service . '_api_key';
        if ($service === 'twilio_sid') $option = 'dg_twilio_sid';
        if ($service === 'twilio_token') $option = 'dg_twilio_token';
        if ($service === 'twilio_from') $option = 'dg_twilio_from';
        update_option($option, sanitize_text_field($value));
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

    public static function get_seo_score($url) {
        $key = self::get_api_key('rankmath');
        if (!$key) {
            return ['available' => false, 'message' => 'Rank Math integration not configured.'];
        }
        return apply_filters('dg_integrations_seo_score', ['available' => true, 'score' => null, 'url' => $url]);
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

    public static function sync_fluentcrm_contact($contact_id) {
        if (!self::get_api_key('fluentcrm') || !function_exists('FluentCrmApi')) {
            return false;
        }
        $contact = DG_Contacts::get($contact_id);
        if (!$contact) {
            return false;
        }
        return apply_filters('dg_integrations_fluentcrm_sync', true, $contact);
    }

    public static function get_integration_status() {
        return [
            'pagespeed' => (bool) self::get_api_key('pagespeed'),
            'openai' => (bool) self::get_api_key('openai'),
            'gemini' => (bool) self::get_api_key('gemini'),
            'twilio' => (bool) (self::get_api_key('twilio_sid') && self::get_api_key('twilio_token')),
            'rankmath' => (bool) self::get_api_key('rankmath'),
            'gsc' => (bool) self::get_api_key('gsc'),
            'gbp' => (bool) self::get_api_key('gbp'),
            'fluentcrm' => (bool) self::get_api_key('fluentcrm'),
            'stripe' => (bool) self::get_api_key('stripe_secret'),
        ];
    }
}
