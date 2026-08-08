<?php
/**
 * Dual-write Roe vendor/buyer leads to Gen 2 (WP-D-103).
 *
 * Public property-report / enquiry forms still create on WordPress first.
 * After create, push to Platform so Neon Lead is ops SoT without waiting for pull-sync.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Platform_Sync {

    public static function init() {
        add_action('dg_re_vendor_lead_created', [__CLASS__, 'on_vendor_lead_created'], 50, 4);
        add_action('dg_re_buyer_lead_created', [__CLASS__, 'on_buyer_lead_created'], 50, 4);
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
        if (defined('DG_LEADS_WEBHOOK_SECRET') && DG_LEADS_WEBHOOK_SECRET !== '') {
            return (string) DG_LEADS_WEBHOOK_SECRET;
        }
        $leads = (string) get_option('dg_leads_webhook_secret', '');
        if ($leads !== '') {
            return $leads;
        }
        if (defined('DG_DISCOVERY_WEBHOOK_SECRET') && DG_DISCOVERY_WEBHOOK_SECRET !== '') {
            return (string) DG_DISCOVERY_WEBHOOK_SECRET;
        }
        $discovery = (string) get_option('dg_discovery_webhook_secret', '');
        if ($discovery !== '') {
            return $discovery;
        }
        return (string) get_option('dg_dev_api_key', '');
    }

    public static function organisation_id() {
        if (defined('DG_RE_ORGANISATION_ID') && DG_RE_ORGANISATION_ID !== '') {
            return (string) DG_RE_ORGANISATION_ID;
        }
        if (defined('DG_ROE_ORGANISATION_ID') && DG_ROE_ORGANISATION_ID !== '') {
            return (string) DG_ROE_ORGANISATION_ID;
        }
        return (string) get_option('dg_platform_organisation_id', '');
    }

    /**
     * @param int $lead_id
     * @param int $contact_id
     * @param int $pipeline_id
     * @param array $data
     */
    public static function on_vendor_lead_created($lead_id, $contact_id, $pipeline_id, $data = []) {
        self::push_lead('vendor', (int) $lead_id, is_array($data) ? $data : []);
    }

    /**
     * @param int $buyer_id
     * @param int $contact_id
     * @param int $pipeline_id
     * @param array $data
     */
    public static function on_buyer_lead_created($buyer_id, $contact_id, $pipeline_id, $data = []) {
        self::push_lead('buyer', (int) $buyer_id, is_array($data) ? $data : []);
    }

    /**
     * @param string $lead_type vendor|buyer
     * @param int $wp_lead_id
     * @param array $data
     */
    public static function push_lead($lead_type, $wp_lead_id, $data = []) {
        $wp_lead_id = (int) $wp_lead_id;
        if ($wp_lead_id <= 0) {
            return;
        }

        $secret = self::webhook_secret();
        if ($secret === '') {
            return;
        }

        $name = sanitize_text_field($data['full_name'] ?? $data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        $address = sanitize_text_field($data['property_address'] ?? '');
        $property_url = esc_url_raw($data['property_url'] ?? '');
        $source = sanitize_text_field($data['source'] ?? ($lead_type === 'buyer' ? 'property_enquiry' : 'property_report'));
        $notes = sanitize_textarea_field($data['notes'] ?? $data['message'] ?? '');

        $payload = [
            'lead_type' => $lead_type === 'buyer' ? 'buyer' : 'vendor',
            'wp_lead_id' => $wp_lead_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'property_address' => $address,
            'property_url' => $property_url,
            'source' => $source,
            'stage' => $lead_type === 'buyer' ? 'inquiry' : 'vendor_lead',
            'status' => sanitize_text_field($data['status'] ?? 'new'),
            'notes' => $notes,
            'site_url' => home_url('/'),
            'source_path' => 'wordpress_dual_write',
        ];

        $org = self::organisation_id();
        if ($org !== '') {
            $payload['organisation_id'] = $org;
        }

        $url = self::app_url() . '/api/webhooks/dg-leads';
        $response = wp_remote_post($url, [
            'timeout' => 15,
            'blocking' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-DG-Webhook-Secret' => $secret,
                'X-API-Key' => $secret,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            error_log('DG RE Platform sync failed: ' . $response->get_error_message());
        }
    }
}
