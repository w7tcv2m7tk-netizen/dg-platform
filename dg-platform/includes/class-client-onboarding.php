<?php
/**
 * Client onboarding form — REST + admin-post handler.
 * Replaces standalone save-onboarding.php (FluentCRM-free).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Client_Onboarding {

    const ACTION = 'dg_submit_onboarding';
    const REST_ROUTE = '/onboarding';
    const ADMIN_EMAIL = 'onboarding@digitalgate.com.au';
    const TAGS = 'DigitalGate Client,Onboarding Complete';

    /** @var array<string,string> */
    private static $scalar_fields = [
        'business_name', 'street_address', 'city', 'state', 'postcode', 'country',
        'phone', 'business_email', 'business_hours',
        'contact_name', 'position', 'contact_phone', 'contact_email', 'contact_method',
        'services', 'packages', 'service_areas', 'about_business', 'ideal_customer',
        'referral_source', 'special_requests', 'priority', 'source',
        'brand_colours', 'website_url', 'current_website_url', 'website_platform', 'hosting_company',
        'website_login_url', 'website_username', 'google_account_email', 'gmb_email', 'gbp_link', 'gmb_link',
        'facebook', 'instagram', 'linkedin', 'tiktok', 'youtube',
        'additional_info', 'hear_about_us',
        'industry_vertical', 'platform_tier', 'growth_tier', 'domain_registrar', 'desired_domain',
        'about_business', 'team_members', 'stripe_session_id', 'purchase_summary',
    ];

    /** @var array<string> */
    private static $array_fields = [
        'platforms', 'goals', 'google_assets', 'business_systems', 'systems',
        'deliverables', 'competitors', 'competitor_urls', 'priorities', 'provide_items',
        'purchased_apps', 'purchased_premium', 'purchased_addons', 'purchased_addons',
    ];

    public static function init() {
        if (!self::enabled()) {
            return;
        }

        add_action('admin_post_nopriv_' . self::ACTION, [__CLASS__, 'handle_admin_post']);
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle_admin_post']);
        add_action('dg_platform_register_rest_routes', [__CLASS__, 'register_rest_route']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_form_assets']);
        add_shortcode('dg_onboarding_hidden_fields', [__CLASS__, 'shortcode_hidden_fields']);
    }

    public static function enabled() {
        return apply_filters('dg_client_onboarding_enabled', class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate());
    }

    public static function register_rest_route() {
        register_rest_route(DG_REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_rest'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dg/v1', self::REST_ROUTE, [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_rest'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function form_action_url() {
        return admin_url('admin-post.php');
    }

    public static function enqueue_form_assets() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post instanceof WP_Post) {
            return;
        }

        $has_form = is_page('onboarding')
            || strpos($post->post_content, 'onboardingForm') !== false
            || (function_exists('get_post_meta') && strpos((string) get_post_meta($post->ID, '_oxygen_data', true), 'onboardingForm') !== false);

        if (!$has_form && !apply_filters('dg_client_onboarding_enqueue_assets', false, $post)) {
            return;
        }

        wp_enqueue_script(
            'dg-onboarding-form',
            DG_PLATFORM_URL . 'assets/js/onboarding-form.js',
            [],
            DG_PLATFORM_VERSION,
            true
        );

        wp_localize_script('dg-onboarding-form', 'dgOnboardingForm', [
            'actionUrl' => self::form_action_url(),
            'nonce' => wp_create_nonce(self::ACTION),
        ]);
    }

    public static function shortcode_hidden_fields() {
        if (!self::enabled()) {
            return '';
        }

        ob_start();
        ?>
        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
        <?php echo self::nonce_field(); ?>
        <input type="hidden" name="source" value="onboarding">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:0;height:0;opacity:0;pointer-events:none">
        <?php
        return (string) ob_get_clean();
    }

    public static function rest_url() {
        return rest_url(DG_REST_NAMESPACE . self::REST_ROUTE);
    }

    public static function handle_rest($request) {
        if (class_exists('DG_Marketing_Form_Security')) {
            $guard = DG_Marketing_Form_Security::guard_rest($request, 'client_onboarding');
            if ($guard instanceof WP_REST_Response) {
                return $guard;
            }
        }

        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }

        $result = self::process($params, []);
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ], 400);
        }

        return new WP_REST_Response(array_merge(['success' => true], $result), 200);
    }

    public static function handle_admin_post() {
        if (!empty($_POST['website'])) {
            wp_safe_redirect(home_url('/onboarding-thank-you/'));
            exit;
        }

        if (class_exists('DG_Marketing_Form_Security')) {
            $guard = DG_Marketing_Form_Security::guard_post('client_onboarding', $_POST);
            if (is_wp_error($guard)) {
                self::redirect_error($guard->get_error_code());
            }
        }

        $legacy = !empty($_POST['dg_onboarding_legacy']);
        if (!$legacy && (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::ACTION))) {
            self::redirect_error('invalid-request');
        }

        $data = self::normalize_input($_POST);
        $result = self::process($data, $_FILES);
        if (is_wp_error($result)) {
            self::redirect_error($result->get_error_code());
        }

        wp_safe_redirect(home_url('/onboarding-thank-you/'));
        exit;
    }

    /**
     * Legacy entry point for root save-onboarding.php (no WordPress nonce).
     */
    public static function handle_legacy_root_post() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $_POST['dg_onboarding_legacy'] = '1';
        self::handle_admin_post();
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $files
     * @return array<string,mixed>|WP_Error
     */
    public static function process(array $input, array $files = []) {
        $data = self::normalize_input($input);

        if ($data['business_name'] === '' || $data['contact_name'] === '' || $data['contact_email'] === '') {
            return new WP_Error('missing-fields', 'Business name, contact name, and email are required.');
        }
        if (!is_email($data['contact_email'])) {
            return new WP_Error('invalid-email', 'Please provide a valid email address.');
        }

        $name_parts = preg_split('/\s+/', trim($data['contact_name']), 2);
        $first_name = $name_parts[0] ?? $data['contact_name'];
        $last_name = $name_parts[1] ?? '';

        $org_id = self::upsert_organisation($data);
        $contact_id = self::upsert_contact($data, $org_id, $first_name, $last_name);
        if (is_wp_error($contact_id)) {
            return $contact_id;
        }

        self::store_submission_meta((int) $contact_id, (int) $org_id, $data);
        $uploads = self::handle_uploads($files, (int) $contact_id);
        $user_result = class_exists('DG_Client_Portal')
            ? DG_Client_Portal::ensure_user($data['contact_email'], $data['contact_name'], (int) $contact_id, (int) $org_id)
            : self::ensure_portal_user($data['contact_email'], $data['contact_name']);
        self::send_welcome_email($data, $user_result);
        self::send_admin_email($data, (int) $contact_id, (int) $org_id, $user_result, $uploads);

        do_action('dg_client_onboarding_completed', (int) $contact_id, (int) $org_id, $data, $uploads);

        return [
            'contact_id' => (int) $contact_id,
            'organisation_id' => (int) $org_id,
            'redirect_url' => home_url('/onboarding-thank-you/'),
            'uploads' => $uploads,
            'user_created' => !empty($user_result['created']),
        ];
    }

    /** @param array<string,mixed> $raw */
    private static function normalize_input(array $raw) {
        $data = [];
        foreach (self::$scalar_fields as $field) {
            $data[$field] = isset($raw[$field]) ? sanitize_text_field(wp_unslash((string) $raw[$field])) : '';
        }
        foreach (self::$array_fields as $field) {
            $value = $raw[$field] ?? [];
            if (!is_array($value)) {
                $value = $value !== '' ? [(string) $value] : [];
            }
            $data[$field] = array_values(array_filter(array_map(function ($item) {
                return sanitize_text_field(wp_unslash((string) $item));
            }, $value)));
        }

        if ($data['current_website_url'] === '' && $data['website_url'] !== '') {
            $data['current_website_url'] = $data['website_url'];
        }
        if ($data['google_account_email'] === '' && $data['gmb_email'] !== '') {
            $data['google_account_email'] = $data['gmb_email'];
        }
        if ($data['gbp_link'] === '' && $data['gmb_link'] !== '') {
            $data['gbp_link'] = $data['gmb_link'];
        }
        if (empty($data['business_systems']) && !empty($data['systems'])) {
            $data['business_systems'] = $data['systems'];
        }

        return $data;
    }

    /** @param array<string,mixed> $data */
    private static function upsert_organisation(array $data) {
        if (!class_exists('DG_Organisations')) {
            return 0;
        }

        $email = $data['business_email'] !== '' ? $data['business_email'] : $data['contact_email'];
        $existing = $email !== '' ? DG_Organisations::get_by_email($email) : null;
        $notes = self::build_summary_text($data);

        $payload = [
            'name' => $data['business_name'],
            'email' => $email,
            'phone' => $data['phone'] !== '' ? $data['phone'] : $data['contact_phone'],
            'website' => $data['current_website_url'] !== '' ? $data['current_website_url'] : $data['website_url'],
            'suburb' => $data['city'],
            'state' => $data['state'],
            'status' => 'active',
            'source' => 'onboarding',
            'notes' => $notes,
        ];

        if ($existing) {
            DG_Organisations::update($existing->id, $payload);
            return (int) $existing->id;
        }

        return (int) DG_Organisations::create($payload);
    }

    /** @param array<string,mixed> $data */
    private static function upsert_contact(array $data, $org_id, $first_name, $last_name) {
        if (!class_exists('DG_Contacts')) {
            return new WP_Error('contacts_unavailable', 'Contacts service is not available.');
        }

        $phone = $data['contact_phone'] !== '' ? $data['contact_phone'] : $data['phone'];
        $payload = [
            'organisation_id' => $org_id ?: null,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $data['contact_email'],
            'phone' => $phone,
            'position' => $data['position'],
            'is_primary' => 1,
            'status' => 'active',
            'source' => 'onboarding',
            'tags' => self::TAGS,
            'notes' => self::build_summary_text($data),
        ];

        $existing = DG_Contacts::get_by_email($data['contact_email']);
        if ($existing) {
            DG_Contacts::update($existing->id, $payload);
            return (int) $existing->id;
        }

        return (int) DG_Contacts::create($payload);
    }

    /** @param array<string,mixed> $data */
    private static function store_submission_meta($contact_id, $org_id, array $data) {
        if (!class_exists('DG_Entity_Meta')) {
            return;
        }

        DG_Entity_Meta::set('contact', $contact_id, 'onboarding_submission', $data);
        DG_Entity_Meta::set('contact', $contact_id, 'onboarding_submitted_at', current_time('mysql'));

        if ($org_id) {
            DG_Entity_Meta::set('organisation', $org_id, 'onboarding_submission', $data);
            DG_Entity_Meta::set('organisation', $org_id, 'onboarding_submitted_at', current_time('mysql'));
        }

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 'contact',
                'entity_id' => $contact_id,
                'activity_type' => 'form',
                'subject' => 'Client onboarding submitted',
                'content' => $data['business_name'],
            ]);
        }
    }

    /** @param array<string,mixed> $files */
    private static function handle_uploads(array $files, $contact_id) {
        if (empty($files)) {
            return [];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $saved = [];
        $single_fields = ['logo' => 'Logo'];
        foreach ($single_fields as $field => $label) {
            if (empty($files[$field]['tmp_name']) || (int) ($files[$field]['error'] ?? 1) !== UPLOAD_ERR_OK) {
                continue;
            }
            $attachment_id = self::upload_file($files[$field], $label);
            if ($attachment_id) {
                $saved[] = ['field' => $field, 'attachment_id' => $attachment_id];
                if (class_exists('DG_Documents')) {
                    DG_Documents::attach($attachment_id, 'contact', $contact_id, $label);
                }
            }
        }

        if (!empty($files['photos']['tmp_name']) && is_array($files['photos']['tmp_name'])) {
            foreach ($files['photos']['tmp_name'] as $i => $tmp) {
                if (empty($tmp) || (int) ($files['photos']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $file = [
                    'name' => $files['photos']['name'][$i] ?? 'photo.jpg',
                    'type' => $files['photos']['type'][$i] ?? '',
                    'tmp_name' => $tmp,
                    'error' => $files['photos']['error'][$i],
                    'size' => $files['photos']['size'][$i] ?? 0,
                ];
                $attachment_id = self::upload_file($file, 'Onboarding photo ' . ($i + 1));
                if ($attachment_id) {
                    $saved[] = ['field' => 'photos', 'attachment_id' => $attachment_id];
                    if (class_exists('DG_Documents')) {
                        DG_Documents::attach($attachment_id, 'contact', $contact_id, 'Onboarding photo ' . ($i + 1));
                    }
                }
            }
        }

        return $saved;
    }

    /** @param array<string,mixed> $file */
    private static function upload_file(array $file, $title) {
        $overrides = ['test_form' => false];
        $upload = wp_handle_upload($file, $overrides);
        if (!empty($upload['error'])) {
            return 0;
        }

        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_text_field($title),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        if (!$attachment_id) {
            return 0;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);
        return (int) $attachment_id;
    }

    private static function ensure_portal_user($email, $display_name) {
        $existing = email_exists($email);
        if ($existing) {
            return ['user_id' => (int) $existing, 'created' => false];
        }

        $password = wp_generate_password(12, true);
        $user_id = wp_create_user($email, $password, $email);
        if (is_wp_error($user_id)) {
            return ['user_id' => 0, 'created' => false, 'error' => $user_id->get_error_message()];
        }

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'first_name' => $display_name,
            'role' => 'subscriber',
        ]);

        return ['user_id' => (int) $user_id, 'created' => true];
    }

    /** @param array<string,mixed> $data */
    private static function send_welcome_email(array $data, array $user_result) {
        $portal_url = class_exists('DG_Client_Portal') ? DG_Client_Portal::login_url() : home_url('/client-portal/');
        $reset_link = $portal_url;

        if (!empty($user_result['created']) && !empty($user_result['user_id'])) {
            if (class_exists('DG_Client_Portal')) {
                $reset_link = DG_Client_Portal::password_set_link((int) $user_result['user_id'], $data['contact_email']);
            } else {
                $reset_key = get_password_reset_key(get_userdata($user_result['user_id']));
                if (!is_wp_error($reset_key)) {
                    $reset_link = network_site_url(
                        'wp-login.php?action=rp&key=' . rawurlencode($reset_key) . '&login=' . rawurlencode($data['contact_email'])
                    );
                }
            }
        }

        $subject = 'Welcome to DigitalGate';
        $first = DG_Email_Names::first_name($data['contact_name']);
        if (class_exists('DG_Marketing_Emails')) {
            $inner = '<h2 style="color:#FFFFFF;font-size:22px;margin:0 0 16px;">Hi ' . esc_html($first) . ',</h2>'
                . '<p style="color:#E2E8F0;line-height:1.65;">Welcome to DigitalGate! Your onboarding information has been received and we\'re preparing your project.</p>'
                . DG_Marketing_Emails::cta($portal_url, 'Open client portal')
                . DG_Marketing_Emails::cta($reset_link, 'Set your password')
                . '<p style="color:#E2E8F0;line-height:1.65;">In your portal you can track setup progress, upload assets, book strategy calls, and submit support requests.</p>';
            $message = DG_Marketing_Emails::wrap($inner, ['footer_note' => 'Welcome to DigitalGate.']);
            $headers = DG_Marketing_Emails::mail_headers(true);
        } else {
            $message = 'Hi ' . $first . ",\n\nWelcome to DigitalGate!\n\n{$portal_url}\n\n{$reset_link}\n";
            $headers = self::mail_headers($data['contact_email']);
        }
        wp_mail($data['contact_email'], $subject, $message, $headers);
    }

    /** @param array<string,mixed> $data */
    private static function send_admin_email(array $data, $contact_id, $org_id, array $user_result, array $uploads) {
        $to = apply_filters('dg_client_onboarding_admin_email', self::ADMIN_EMAIL);
        $subject = 'New Client Onboarding — ' . $data['business_name'];

        $rows = [
            'Business' => $data['business_name'],
            'Contact' => $data['contact_name'],
            'Position' => $data['position'],
            'Email' => $data['contact_email'],
            'Phone' => $data['contact_phone'] !== '' ? $data['contact_phone'] : $data['phone'],
            'Address' => trim($data['street_address'] . ', ' . $data['city'] . ' ' . $data['state'] . ' ' . $data['postcode']),
            'Country' => $data['country'],
            'Services' => $data['services'],
            'Service areas' => $data['service_areas'],
            'Ideal customer' => $data['ideal_customer'],
            'Goals' => implode(', ', $data['goals']),
            'Industry' => $data['industry_vertical'],
            'Platform tier' => $data['platform_tier'],
            'Growth tier' => $data['growth_tier'],
            'Purchased apps' => implode(', ', $data['purchased_apps']),
            'Premium apps' => implode(', ', $data['purchased_premium']),
            'Purchase summary' => $data['purchase_summary'],
            'Platforms / systems' => implode(', ', array_unique(array_merge($data['platforms'], $data['business_systems'], $data['systems']))),
            'Deliverables' => implode(', ', $data['deliverables']),
            'Competitors' => implode(', ', array_filter($data['competitors'])),
            'Referral source' => $data['referral_source'] !== '' ? $data['referral_source'] : $data['hear_about_us'],
            'Additional info' => $data['special_requests'] !== '' ? $data['special_requests'] : $data['additional_info'],
            'Contact ID' => (string) $contact_id,
            'Organisation ID' => $org_id ? (string) $org_id : '',
            'Portal user' => !empty($user_result['created']) ? 'Created' : (!empty($user_result['user_id']) ? 'Already existed' : 'Not created'),
            'Uploads' => $uploads ? count($uploads) . ' file(s)' : 'None',
            'Submitted' => current_time('mysql'),
            'IP' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        ];

        if (class_exists('DG_Marketing_Emails')) {
            $body = DG_Marketing_Emails::admin_notification('New client onboarding', $rows, [
                'cta_url' => admin_url('admin.php?page=dg-platform-contacts&action=edit&id=' . $contact_id),
                'cta_label' => 'View contact in DG Platform',
                'footer_note' => 'Client onboarding notification from DigitalGate.',
            ]);
            $headers = DG_Marketing_Emails::mail_headers(true);
            $headers[] = 'Reply-To: ' . $data['contact_name'] . ' <' . $data['contact_email'] . '>';
            wp_mail($to, $subject, $body, $headers);
            return;
        }

        $text = self::build_summary_text($data);
        $text .= "\nContact ID: {$contact_id}\nOrganisation ID: {$org_id}\n";
        wp_mail($to, $subject, $text, self::mail_headers($data['contact_email']));
    }

    /** @param array<string,mixed> $data */
    private static function build_summary_text(array $data) {
        $lines = [];
        $labels = [
            'business_name' => 'Business name',
            'street_address' => 'Street address',
            'city' => 'City',
            'state' => 'State',
            'postcode' => 'Postcode',
            'country' => 'Country',
            'phone' => 'Phone',
            'business_email' => 'Business email',
            'contact_name' => 'Contact name',
            'position' => 'Position',
            'contact_phone' => 'Contact phone',
            'contact_email' => 'Contact email',
            'contact_method' => 'Preferred contact',
            'services' => 'Services',
            'service_areas' => 'Service areas',
            'ideal_customer' => 'Ideal customer',
            'referral_source' => 'Referral source',
            'special_requests' => 'Special requests',
        ];
        foreach ($labels as $key => $label) {
            if (!empty($data[$key])) {
                $lines[] = $label . ': ' . $data[$key];
            }
        }
        if (!empty($data['goals'])) {
            $lines[] = 'Goals: ' . implode(', ', $data['goals']);
        }
        if (!empty($data['platforms'])) {
            $lines[] = 'Platforms: ' . implode(', ', $data['platforms']);
        }
        if (!empty($data['business_systems'])) {
            $lines[] = 'Business systems: ' . implode(', ', $data['business_systems']);
        }
        return implode("\n", $lines);
    }

    private static function mail_headers($reply_email = '') {
        if (class_exists('DG_Marketing_Emails')) {
            $headers = DG_Marketing_Emails::mail_headers(false);
            if ($reply_email !== '') {
                $headers[] = 'Reply-To: support@digitalgate.com.au';
            }
            return $headers;
        }

        return [
            'Content-Type: text/plain; charset=UTF-8',
            'From: DigitalGate Onboarding <hello@digitalgate.com.au>',
            'Reply-To: support@digitalgate.com.au',
        ];
    }

    private static function redirect_error($code) {
        $map = [
            'missing-fields' => 'missing-fields',
            'invalid-email' => 'invalid-email',
            'rate-limit' => 'rate-limit',
        ];
        $error = $map[$code] ?? 'submission-failed';
        wp_safe_redirect(home_url('/onboarding/?error=' . rawurlencode($error)));
        exit;
    }

    public static function nonce_field() {
        return wp_nonce_field(self::ACTION, '_wpnonce', true, false)
            . '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
    }
}

add_action('plugins_loaded', ['DG_Client_Onboarding', 'init'], 12);
