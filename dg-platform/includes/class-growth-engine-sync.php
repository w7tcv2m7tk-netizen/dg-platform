<?php
/**
 * Sync discovery and sales events to Gen 2 Growth Engine.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Growth_Engine_Sync {

    public static function init() {
        if (!self::enabled()) {
            return;
        }

        add_action('dg_discovery_completed', [__CLASS__, 'on_discovery_completed'], 10, 5);
        add_action('dg_client_onboarding_completed', [__CLASS__, 'on_onboarding_completed'], 10, 4);
    }

    public static function enabled() {
        return class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate();
    }

    public static function app_url() {
        if (class_exists('DG_Address_Resolver')) {
            return DG_Address_Resolver::app_url();
        }
        return defined('DG_APP_URL') && DG_APP_URL !== ''
            ? untrailingslashit(DG_APP_URL)
            : 'https://app.digitalgate.com.au';
    }

    public static function webhook_secret() {
        if (defined('DG_DISCOVERY_WEBHOOK_SECRET') && DG_DISCOVERY_WEBHOOK_SECRET !== '') {
            return DG_DISCOVERY_WEBHOOK_SECRET;
        }
        $stored = (string) get_option('dg_discovery_webhook_secret', '');
        if ($stored !== '') {
            return $stored;
        }
        return (string) get_option('dg_dev_api_key', '');
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity @param array<string,mixed> $recommendation */
    public static function on_discovery_completed($contact_id, $org_id, $data, $maturity, $recommendation) {
        $audit = is_array($maturity['audit'] ?? null) ? $maturity['audit'] : null;
        self::push_discovery([
            'wpContactId' => (int) $contact_id,
            'wpOrganisationId' => (int) $org_id,
            'businessName' => $data['business_name'] ?? '',
            'contactName' => $data['full_name'] ?? '',
            'contactEmail' => $data['email'] ?? '',
            'contactPhone' => $data['phone'] ?? '',
            'industry' => $data['industry'] ?? '',
            'websiteUrl' => $data['website_url'] ?? '',
            'maturity' => [
                'score' => (int) ($maturity['score'] ?? 0),
                'grade' => (string) ($maturity['grade'] ?? ''),
                'summary' => (string) ($maturity['summary'] ?? ''),
                'priorities' => $maturity['priorities'] ?? [],
            ],
            'recommendation' => $recommendation,
            'audit' => $audit ? [
                'businessHealth' => (int) ($audit['overall_score'] ?? 0),
                'aiVisibility' => (int) ($audit['ai_score'] ?? 0),
                'websiteHealth' => (int) ($audit['website_score'] ?? 0),
                'seoScore' => (int) ($audit['website_score'] ?? 0),
                'findings' => [
                    'googleScore' => (int) ($audit['google_score'] ?? 0),
                    'recommendations' => $audit['recommendations'] ?? [],
                    'reportUrl' => $audit['report_url'] ?? '',
                    'aiDetails' => $audit['ai_details'] ?? [],
                ],
            ] : null,
        ]);
    }

    /** @param array<string,mixed> $data */
    public static function on_onboarding_completed($contact_id, $org_id, $data, $uploads) {
        $email = $data['contact_email'] ?? '';
        if ($email === '') {
            return;
        }
        self::push_onboarding_sync(['email' => $email]);
    }

    /** @param array<string,mixed> $payload */
    private static function push_discovery(array $payload) {
        self::post_json('/api/webhooks/dg-discovery', $payload);
    }

    /** @param array<string,mixed> $payload */
    private static function push_onboarding_sync(array $payload) {
        self::post_json('/api/webhooks/dg-onboarding-sync', $payload);
    }

    /** @param array<string,mixed> $payload */
    private static function post_json($path, array $payload) {
        $secret = self::webhook_secret();
        if ($secret === '') {
            return;
        }

        $url = self::app_url() . $path;
        $response = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-DG-Webhook-Secret' => $secret,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            error_log('DG Growth Engine sync failed: ' . $response->get_error_message());
            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            error_log('DG Growth Engine sync HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
        }
    }
}

add_action('plugins_loaded', ['DG_Growth_Engine_Sync', 'init'], 13);
