<?php
/**
 * Stripe Payment Link / Checkout automation for DigitalGate sales.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Stripe_Billing {

    const OPTION_WEBHOOK_SECRET = 'dg_stripe_billing_webhook_secret';
    const TAG_PAYMENT = 'Payment Received';
    const TAG_AWAITING = 'Awaiting Onboarding';

    public static function init() {
        if (!self::enabled()) {
            return;
        }

        add_action('dg_platform_register_rest_routes', [__CLASS__, 'register_routes']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_post_dg_stripe_replay_session', [__CLASS__, 'handle_admin_replay']);
    }

    public static function enabled() {
        return class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate();
    }

    public static function webhook_url() {
        return rest_url(DG_REST_NAMESPACE . '/billing/webhook');
    }

    public static function register_settings() {
        register_setting('dg_stripe_billing', self::OPTION_WEBHOOK_SECRET, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/billing/webhook', [
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'handle_webhook'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'handle_webhook_status'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function handle_webhook_status() {
        $secret = trim((string) get_option(self::OPTION_WEBHOOK_SECRET, ''));
        return new WP_REST_Response([
            'status' => 'ok',
            'endpoint' => self::webhook_url(),
            'methods' => ['POST'],
            'event' => 'checkout.session.completed',
            'signing_secret_configured' => $secret !== '',
        ], 200);
    }

    public static function handle_webhook($request) {
        $payload = $request->get_body();
        if ($payload === '') {
            $payload = (string) file_get_contents('php://input');
        }
        $sig_header = $request->get_header('stripe-signature');
        if ($sig_header === '' && !empty($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $sig_header = (string) $_SERVER['HTTP_STRIPE_SIGNATURE'];
        }
        $secret = trim((string) get_option(self::OPTION_WEBHOOK_SECRET, ''));

        if ($secret !== '' && $sig_header) {
            if (!self::verify_signature($payload, $sig_header, $secret)) {
                self::log_event('signature_failed', 'Invalid Stripe signature — re-copy whsec_ from the Test/Live webhook endpoint that sent this event');
                return new WP_REST_Response(['error' => 'Invalid signature'], 400);
            }
        } elseif ($secret !== '' && !$sig_header) {
            self::log_event('signature_missing', 'No Stripe-Signature header');
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            self::log_event('invalid_payload', 'Could not parse event JSON');
            return new WP_REST_Response(['error' => 'Invalid payload'], 400);
        }

        $type = $event['type'];
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $object = $event['data']['object'] ?? [];
            $result = self::handle_checkout_completed($object);
            if ($result) {
                self::log_event($type, 'Provisioned contact #' . ($result['contact_id'] ?? '?'), $result);
            } else {
                $resolved = self::resolve_checkout_session($object);
                self::log_event($type, 'No contact provisioned', [
                    'session_id' => $resolved['id'] ?? '',
                    'status' => $resolved['status'] ?? '',
                    'payment_status' => $resolved['payment_status'] ?? '',
                    'email' => $resolved['customer_details']['email'] ?? $resolved['customer_email'] ?? '',
                ]);
            }
        } else {
            self::log_event($type, 'Ignored event type');
        }

        return new WP_REST_Response(['received' => true], 200);
    }

    /** @return array<string,mixed>|null */
    public static function recent_logs($limit = 10) {
        $logs = get_option('dg_stripe_billing_webhook_log', []);
        return is_array($logs) ? array_slice($logs, 0, $limit) : [];
    }

    /** @param array<string,mixed>|null $context */
    private static function log_event($type, $message, $context = null) {
        $logs = get_option('dg_stripe_billing_webhook_log', []);
        if (!is_array($logs)) {
            $logs = [];
        }
        array_unshift($logs, [
            'at' => current_time('mysql'),
            'type' => sanitize_text_field($type),
            'message' => sanitize_text_field($message),
            'context' => is_array($context) ? $context : [],
        ]);
        $logs = array_slice($logs, 0, 25);
        update_option('dg_stripe_billing_webhook_log', $logs, false);
    }

    /** @param array<string,mixed> $session */
    public static function handle_checkout_completed(array $session) {
        $session = self::resolve_checkout_session($session);
        if (!$session) {
            self::log_event('provision_skipped', 'Could not resolve checkout session');
            return null;
        }

        $skip = self::session_skip_reason($session);
        if ($skip !== '') {
            self::log_event('provision_skipped', $skip, self::session_debug($session));
            return null;
        }

        $email = self::session_email($session);
        $name = self::session_name($session);
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $business = sanitize_text_field((string) ($metadata['business_name'] ?? $name));

        $purchase = [
            'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
            'stripe_customer_id' => self::session_customer_id($session),
            'amount_total' => (int) ($session['amount_total'] ?? 0),
            'currency' => sanitize_text_field((string) ($session['currency'] ?? 'aud')),
            'dg_category' => sanitize_key((string) ($metadata['dg_category'] ?? '')),
            'dg_plan' => sanitize_key((string) ($metadata['dg_plan'] ?? '')),
            'dg_platform_tier' => sanitize_key((string) ($metadata['dg_platform_tier'] ?? '')),
            'purchase_label' => self::build_purchase_label($metadata),
        ];

        return self::provision_new_client($email, $name, $business, $purchase);
    }

    /** @param array<string,mixed> $session */
    private static function session_skip_reason(array $session) {
        if (($session['status'] ?? '') !== 'complete') {
            return 'Session status is not complete (' . ($session['status'] ?? 'unknown') . ')';
        }

        $payment_status = (string) ($session['payment_status'] ?? '');
        if ($payment_status === 'unpaid' && empty($session['subscription'])) {
            return 'Payment status is unpaid';
        }

        if (self::session_email($session) === '') {
            return 'No customer email on checkout session';
        }

        return '';
    }

    /** @param array<string,mixed> $session */
    private static function session_debug(array $session) {
        return [
            'session_id' => $session['id'] ?? '',
            'status' => $session['status'] ?? '',
            'payment_status' => $session['payment_status'] ?? '',
            'email' => self::session_email($session),
        ];
    }

    /** @param array<string,mixed> $session */
    private static function session_email(array $session) {
        $email = sanitize_email((string) ($session['customer_details']['email'] ?? $session['customer_email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        $email = sanitize_email((string) ($session['metadata']['contact_email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        $customer = $session['customer'] ?? null;
        if (is_array($customer) && !empty($customer['email'])) {
            return sanitize_email((string) $customer['email']);
        }

        return '';
    }

    /** @param array<string,mixed> $session */
    private static function session_name(array $session) {
        $name = sanitize_text_field((string) ($session['customer_details']['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $customer = $session['customer'] ?? null;
        if (is_array($customer) && !empty($customer['name'])) {
            return sanitize_text_field((string) $customer['name']);
        }

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        return sanitize_text_field((string) ($metadata['customer_name'] ?? 'New Client'));
    }

    /** @param array<string,mixed> $session */
    private static function session_customer_id(array $session) {
        $customer = $session['customer'] ?? '';
        if (is_array($customer)) {
            return sanitize_text_field((string) ($customer['id'] ?? ''));
        }
        return sanitize_text_field((string) $customer);
    }

    /**
     * @param array<string,mixed> $purchase
     * @return array<string,mixed>|null
     */
    public static function provision_new_client($email, $name, $business_name, array $purchase) {
        if (!class_exists('DG_Contacts')) {
            return null;
        }

        $org_id = 0;
        if (class_exists('DG_Organisations')) {
            $existing_org = DG_Organisations::get_by_email($email);
            $org_payload = [
                'name' => $business_name !== '' ? $business_name : $name,
                'email' => $email,
                'status' => 'active',
                'source' => 'stripe',
                'notes' => 'Stripe purchase: ' . ($purchase['purchase_label'] ?? 'DigitalGate'),
            ];
            if ($existing_org) {
                DG_Organisations::update($existing_org->id, $org_payload);
                $org_id = (int) $existing_org->id;
            } else {
                $org_id = (int) DG_Organisations::create($org_payload);
            }
        }

        $name_parts = preg_split('/\s+/', trim($name), 2);
        $contact_payload = [
            'organisation_id' => $org_id ?: null,
            'first_name' => $name_parts[0] ?? $name,
            'last_name' => $name_parts[1] ?? '',
            'email' => $email,
            'is_primary' => 1,
            'status' => 'active',
            'source' => 'stripe',
            'tags' => 'DigitalGate Client,' . self::TAG_PAYMENT . ',' . self::TAG_AWAITING,
            'notes' => 'Purchase: ' . ($purchase['purchase_label'] ?? ''),
        ];

        $existing = DG_Contacts::get_by_email($email);
        if ($existing) {
            $contact_payload['tags'] = self::merge_tags($existing->tags ?? '', $contact_payload['tags']);
            DG_Contacts::update($existing->id, $contact_payload);
            $contact_id = (int) $existing->id;
        } else {
            $created = DG_Contacts::create($contact_payload);
            if (is_wp_error($created)) {
                self::log_event('contact_create_failed', $created->get_error_message(), ['email' => $email]);
                return null;
            }
            $contact_id = (int) $created;
        }

        if ($contact_id <= 0) {
            self::log_event('contact_create_failed', 'Contact ID missing after create/update', ['email' => $email]);
            return null;
        }

        if (class_exists('DG_Entity_Meta')) {
            DG_Entity_Meta::set('contact', $contact_id, 'stripe_purchase', $purchase);
            DG_Entity_Meta::set('contact', $contact_id, 'stripe_purchased_at', current_time('mysql'));
            if ($org_id) {
                DG_Entity_Meta::set('organisation', $org_id, 'stripe_purchase', $purchase);
            }
        }

        $user_result = class_exists('DG_Client_Portal')
            ? DG_Client_Portal::ensure_user($email, $name, $contact_id, $org_id)
            : ['user_id' => 0, 'created' => false];

        self::send_purchase_emails($email, $name, $business_name, $purchase, $user_result, $contact_id);

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 'contact',
                'entity_id' => $contact_id,
                'activity_type' => 'payment',
                'subject' => 'Stripe purchase completed',
                'content' => $purchase['purchase_label'] ?? '',
            ]);
        }

        do_action('dg_stripe_checkout_completed', $contact_id, $org_id, $purchase, $user_result);

        return [
            'contact_id' => $contact_id,
            'organisation_id' => $org_id,
            'user_id' => (int) ($user_result['user_id'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $metadata */
    private static function build_purchase_label(array $metadata) {
        $parts = array_filter([
            !empty($metadata['dg_category']) ? ucfirst(str_replace('_', ' ', $metadata['dg_category'])) : '',
            !empty($metadata['dg_plan']) ? ucfirst(str_replace('_', ' ', $metadata['dg_plan'])) : '',
            !empty($metadata['dg_platform_tier']) ? 'Platform ' . ucfirst($metadata['dg_platform_tier']) : '',
        ]);
        return $parts ? implode(' · ', $parts) : 'DigitalGate purchase';
    }

    /** @param array<string,mixed> $purchase */
    private static function send_purchase_emails($email, $name, $business_name, array $purchase, array $user_result, $contact_id) {
        $onboarding_url = class_exists('DG_Client_Portal')
            ? DG_Client_Portal::onboarding_url()
            : home_url('/onboarding/');

        if (!empty($purchase['stripe_session_id'])) {
            $onboarding_url = add_query_arg([
                'session_id' => $purchase['stripe_session_id'],
                'plan' => $purchase['dg_plan'] ?? '',
                'tier' => $purchase['dg_platform_tier'] ?? '',
                'category' => $purchase['dg_category'] ?? '',
            ], $onboarding_url);
        }

        $portal_url = class_exists('DG_Client_Portal') ? DG_Client_Portal::login_url() : home_url('/client-portal/');
        $password_link = $portal_url;

        if (!empty($user_result['user_id']) && !empty($user_result['created'])) {
            $password_link = DG_Client_Portal::password_set_link((int) $user_result['user_id'], $email);
        }

        $subject = 'Welcome to DigitalGate — complete your onboarding';
        $first_name = DG_Email_Names::first_name($name);
        if (class_exists('DG_Marketing_Emails')) {
            $inner = '<h2 style="color:#FFFFFF;font-size:22px;margin:0 0 16px;">Hi ' . esc_html($first_name) . ',</h2>'
                . '<p style="color:#E2E8F0;line-height:1.65;">Thank you for your purchase'
                . (!empty($purchase['purchase_label']) ? ' (' . esc_html($purchase['purchase_label']) . ')' : '')
                . '! Complete your onboarding form so we can configure your platform.</p>'
                . DG_Marketing_Emails::cta($onboarding_url, 'Complete onboarding')
                . '<p style="color:#E2E8F0;line-height:1.65;">Your client portal:</p>'
                . DG_Marketing_Emails::cta($portal_url, 'Open client portal');
            if (!empty($user_result['created'])) {
                $inner .= DG_Marketing_Emails::cta($password_link, 'Set your password');
            }
            $inner .= '<p style="color:#94A3B8;font-size:14px;">We typically complete setup within 2–3 business days after receiving your onboarding details.</p>';
            $message = DG_Marketing_Emails::wrap($inner, ['footer_note' => 'Welcome to DigitalGate.']);
            $headers = DG_Marketing_Emails::mail_headers(true);
        } else {
            $message = "Hi {$first_name},\n\nThank you for your purchase!\n\n{$onboarding_url}\n\n{$portal_url}\n";
            $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: DigitalGate <hello@digitalgate.com.au>'];
        }
        $client_sent = wp_mail($email, $subject, $message, $headers);
        self::log_event('client_email', $client_sent ? 'Sent to ' . $email : 'Failed to send to ' . $email);

        $admin_to = apply_filters('dg_client_onboarding_admin_email', 'onboarding@digitalgate.com.au');
        $admin_subject = 'New purchase — ' . ($business_name !== '' ? $business_name : $name);
        $admin_body = "New Stripe checkout completed.\n\n";
        $admin_body .= "Customer: {$name} <{$email}>\n";
        $admin_body .= "Business: {$business_name}\n";
        $admin_body .= "Product: " . ($purchase['purchase_label'] ?? '') . "\n";
        $admin_body .= "Session: " . ($purchase['stripe_session_id'] ?? '') . "\n";
        $admin_body .= "Onboarding: {$onboarding_url}\n";
        $admin_body .= "Contact ID: {$contact_id}\n";

        if (class_exists('DG_Marketing_Emails')) {
            $admin_body = DG_Marketing_Emails::admin_notification('New Stripe purchase', [
                'Customer' => "{$name} <{$email}>",
                'Business' => $business_name,
                'Product' => $purchase['purchase_label'] ?? '',
                'Onboarding' => $onboarding_url,
                'Contact ID' => (string) $contact_id,
            ], [
                'cta_url' => admin_url('admin.php?page=dg-platform-contacts&action=edit&id=' . $contact_id),
                'cta_label' => 'View contact',
            ]);
            $headers = DG_Marketing_Emails::mail_headers(true);
            wp_mail($admin_to, $admin_subject, $admin_body, $headers);
            return;
        }

        wp_mail($admin_to, $admin_subject, $admin_body, $headers);
    }

    public static function handle_admin_replay() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_stripe_replay_session')) {
            wp_die('Unauthorized');
        }

        $session_id = sanitize_text_field(wp_unslash($_POST['stripe_session_id'] ?? ''));
        if ($session_id === '') {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-api&stripe_replay=missing'));
            exit;
        }

        $session = self::fetch_checkout_session($session_id);
        if (is_wp_error($session)) {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-api&stripe_replay=error&msg=' . rawurlencode($session->get_error_message())));
            exit;
        }

        $result = self::handle_checkout_completed($session);
        if (!$result) {
            $resolved = self::resolve_checkout_session($session);
            $reason = self::session_skip_reason($resolved);
            if ($reason === '') {
                $reason = 'Provisioning returned no result';
            }
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-api&stripe_replay=failed&session_id=' . rawurlencode($session_id) . '&email=' . rawurlencode(self::session_email($resolved)) . '&msg=' . rawurlencode($reason)));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-contacts&action=edit&id=' . (int) $result['contact_id'] . '&stripe_replay=1'));
        exit;
    }

    /**
     * Fetch full checkout session from Stripe (thin webhook payloads omit customer email).
     *
     * @param array<string,mixed> $session
     * @return array<string,mixed>|null
     */
    public static function resolve_checkout_session(array $session) {
        $session_id = sanitize_text_field((string) ($session['id'] ?? ''));
        $email = (string) ($session['customer_details']['email'] ?? $session['customer_email'] ?? '');
        $needs_fetch = $session_id !== '' && (
            $email === ''
            || ($session['status'] ?? '') === ''
            || !array_key_exists('payment_status', $session)
        );

        if ($needs_fetch) {
            $fetched = self::fetch_checkout_session($session_id);
            if (is_wp_error($fetched)) {
                self::log_event('stripe_fetch_failed', $fetched->get_error_message(), ['session_id' => $session_id]);
            } elseif (is_array($fetched)) {
                $session = $fetched;
            }
        }

        if (empty($session['customer_details']['email']) && empty($session['customer_email']) && self::session_email($session) === '' && !empty($session['customer'])) {
            $customer = $session['customer'];
            if (is_array($customer)) {
                if (!empty($customer['email'])) {
                    if (!isset($session['customer_details']) || !is_array($session['customer_details'])) {
                        $session['customer_details'] = [];
                    }
                    $session['customer_details']['email'] = $customer['email'];
                    if (empty($session['customer_details']['name']) && !empty($customer['name'])) {
                        $session['customer_details']['name'] = $customer['name'];
                    }
                }
            } else {
                $fetched = self::fetch_customer((string) $customer);
                if (is_array($fetched) && !empty($fetched['email'])) {
                    if (!isset($session['customer_details']) || !is_array($session['customer_details'])) {
                        $session['customer_details'] = [];
                    }
                    $session['customer_details']['email'] = $fetched['email'];
                    if (empty($session['customer_details']['name']) && !empty($fetched['name'])) {
                        $session['customer_details']['name'] = $fetched['name'];
                    }
                }
            }
        }

        return $session;
    }

    /** @return array<string,mixed>|WP_Error */
    public static function fetch_checkout_session($session_id) {
        $session_id = sanitize_text_field($session_id);
        if ($session_id === '') {
            return new WP_Error('missing_session', 'Checkout session ID is required.');
        }
        return self::stripe_api('checkout/sessions/' . rawurlencode($session_id) . '?expand[]=customer', [], 'GET');
    }

    /** @return array<string,mixed>|WP_Error */
    private static function fetch_customer($customer_id) {
        $customer_id = sanitize_text_field($customer_id);
        if ($customer_id === '') {
            return new WP_Error('missing_customer', 'Customer ID is required.');
        }
        return self::stripe_api('customers/' . rawurlencode($customer_id), [], 'GET');
    }

    /** @return array<string,mixed>|WP_Error */
    private static function stripe_api($endpoint, $body = [], $method = 'POST') {
        if (!class_exists('DG_Integrations')) {
            return new WP_Error('stripe_not_configured', 'Integrations not available.');
        }
        $secret = DG_Integrations::get_api_key('stripe_secret');
        if ($secret === '') {
            return new WP_Error('stripe_not_configured', 'Add your Stripe secret key (sk_test_… or sk_live_…) in API Settings.');
        }

        $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
            ],
            'timeout' => 30,
        ];

        if ($method === 'GET') {
            $response = wp_remote_get($url, $args);
        } else {
            $args['body'] = $body;
            $response = wp_remote_post($url, $args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($json)) {
            return new WP_Error('stripe_invalid_response', 'Invalid response from Stripe.');
        }
        if ($code >= 400) {
            $message = $json['error']['message'] ?? 'Stripe API error.';
            return new WP_Error('stripe_api_error', $message, ['status' => $code]);
        }

        return $json;
    }

    private static function merge_tags($existing, $new) {
        $parts = array_filter(array_map('trim', explode(',', (string) $existing . ',' . (string) $new)));
        return implode(',', array_unique($parts));
    }

    private static function verify_signature($payload, $sig_header, $secret) {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', (string) $sig_header) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $item, 2), 2, null);
            if ($key === 't' && $value !== null) {
                $timestamp = (int) $value;
            }
            if ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        // Reject replays older than 5 minutes (Stripe recommendation).
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}

add_action('plugins_loaded', ['DG_Stripe_Billing', 'init'], 12);
