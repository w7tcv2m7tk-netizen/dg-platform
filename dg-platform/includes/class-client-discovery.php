<?php
/**
 * AI Discovery Form — pre-sales qualification, CRM, maturity score, plan recommendation.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Client_Discovery {

    const ACTION = 'dg_submit_discovery';
    const REST_ROUTE = '/discovery';
    const ADMIN_EMAIL = 'hello@digitalgate.com.au';
    const TAGS = 'Discovery Lead,Platform Prospect';

    /** @var array<string> */
    private static $scalar_fields = [
        'full_name', 'business_name', 'email', 'phone',
        'industry', 'business_type', 'team_size', 'website_url',
        'crm', 'accounting', 'marketing_tools', 'website_platform',
        'software_spend', 'ai_adoption', 'timeframe', 'budget_range',
        'goals_message', 'source',
    ];

    /** @var array<string> */
    private static $array_fields = [
        'challenges', 'integrations', 'growth_objectives', 'interested_in',
    ];

    public static function init() {
        if (!self::enabled()) {
            return;
        }

        add_action('admin_post_nopriv_' . self::ACTION, [__CLASS__, 'handle_admin_post']);
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle_admin_post']);
        add_action('dg_platform_register_rest_routes', [__CLASS__, 'register_rest_route']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_form_assets']);
        add_shortcode('dg_discovery_hidden_fields', [__CLASS__, 'shortcode_hidden_fields']);
    }

    public static function enabled() {
        return apply_filters('dg_client_discovery_enabled', class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate());
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

    public static function rest_url() {
        return rest_url(DG_REST_NAMESPACE . self::REST_ROUTE);
    }

    public static function enqueue_form_assets() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post instanceof WP_Post) {
            return;
        }

        $has_form = is_page('discover')
            || strpos($post->post_content, 'dgDiscoveryForm') !== false
            || (function_exists('get_post_meta') && strpos((string) get_post_meta($post->ID, '_oxygen_data', true), 'dgDiscoveryForm') !== false);

        if (!$has_form && !apply_filters('dg_client_discovery_enqueue_assets', false, $post)) {
            return;
        }

        wp_enqueue_script(
            'dg-discovery-form',
            DG_PLATFORM_URL . 'assets/js/discovery-form.js',
            [],
            DG_PLATFORM_VERSION,
            true
        );

        wp_localize_script('dg-discovery-form', 'dgDiscoveryForm', [
            'restUrl' => self::rest_url(),
            'actionUrl' => self::form_action_url(),
            'nonce' => wp_create_nonce(self::ACTION),
            'thankYouUrl' => home_url('/discover/?discovery_sent=1'),
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
        <input type="hidden" name="source" value="discovery">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:0;height:0;opacity:0;pointer-events:none">
        <?php
        return (string) ob_get_clean();
    }

    public static function nonce_field() {
        return wp_nonce_field(self::ACTION, '_wpnonce', true, false);
    }

    public static function handle_rest($request) {
        if (class_exists('DG_Marketing_Form_Security')) {
            $guard = DG_Marketing_Form_Security::guard_rest($request, 'client_discovery');
            if ($guard instanceof WP_REST_Response) {
                return $guard;
            }
        }

        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }

        $result = self::process($params);
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
            wp_safe_redirect(home_url('/discover/?discovery_sent=1'));
            exit;
        }

        if (class_exists('DG_Marketing_Form_Security')) {
            $guard = DG_Marketing_Form_Security::guard_post('client_discovery', $_POST);
            if (is_wp_error($guard)) {
                self::redirect_error($guard->get_error_code());
            }
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::ACTION)) {
            self::redirect_error('invalid-request');
        }

        $result = self::process($_POST);
        if (is_wp_error($result)) {
            self::redirect_error($result->get_error_code());
        }

        wp_safe_redirect(home_url('/discover/?discovery_sent=1'));
        exit;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>|WP_Error
     */
    public static function process(array $input) {
        $data = self::normalize_input($input);

        if ($data['full_name'] === '' || $data['business_name'] === '' || $data['email'] === '') {
            return new WP_Error('missing-fields', 'Full name, business name, and email are required.');
        }
        if (!is_email($data['email'])) {
            return new WP_Error('invalid-email', 'Please provide a valid email address.');
        }

        $maturity = self::calculate_maturity($data);
        $recommendation = self::recommend_plan($data, $maturity);

        $name_parts = preg_split('/\s+/', trim($data['full_name']), 2);
        $first_name = $name_parts[0] ?? $data['full_name'];
        $last_name = $name_parts[1] ?? '';

        $org_id = self::upsert_organisation($data, $maturity, $recommendation);
        $contact_id = self::upsert_contact($data, $org_id, $first_name, $last_name, $maturity, $recommendation);
        if (is_wp_error($contact_id)) {
            return $contact_id;
        }

        self::store_submission_meta((int) $contact_id, (int) $org_id, $data, $maturity, $recommendation);
        self::create_follow_up_task((int) $contact_id, $data, $maturity, $recommendation);
        self::send_prospect_email($data, $maturity, $recommendation);
        self::send_admin_email($data, (int) $contact_id, (int) $org_id, $maturity, $recommendation);

        do_action('dg_discovery_completed', (int) $contact_id, (int) $org_id, $data, $maturity, $recommendation);
        do_action('dg_form_submitted', 'discovery', $data);

        return [
            'contact_id' => (int) $contact_id,
            'organisation_id' => (int) $org_id,
            'maturity_score' => $maturity['score'],
            'maturity_grade' => $maturity['grade'],
            'summary' => $maturity['summary'],
            'priorities' => $maturity['priorities'],
            'recommendation' => $recommendation,
            'redirect_url' => home_url('/discover/?discovery_sent=1'),
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
        if ($data['source'] === '') {
            $data['source'] = 'discovery';
        }
        return $data;
    }

    /** @param array<string,mixed> $data */
    private static function calculate_maturity(array $data) {
        $score = 42;

        if ($data['website_url'] !== '') {
            $score += 8;
        }
        if (count($data['integrations']) >= 2) {
            $score += 6;
        } elseif (count($data['integrations']) === 1) {
            $score += 3;
        }
        if ($data['crm'] !== '' && $data['crm'] !== 'none' && $data['crm'] !== 'spreadsheets') {
            $score += 5;
        }
        if ($data['ai_adoption'] === 'active' || $data['ai_adoption'] === 'implementing') {
            $score += 8;
        } elseif ($data['ai_adoption'] === 'exploring') {
            $score += 4;
        }
        if (in_array('disconnected-systems', $data['challenges'], true)) {
            $score -= 8;
        }
        if (in_array('manual-follow-up', $data['challenges'], true)) {
            $score -= 4;
        }
        if (in_array('reporting-visibility', $data['challenges'], true)) {
            $score -= 3;
        }
        if ($data['software_spend'] === '$2000+/mo') {
            $score -= 5;
        } elseif ($data['software_spend'] === '$1000–2000/mo') {
            $score -= 2;
        }

        $score = max(18, min(96, $score));

        $grade = 'D';
        if ($score >= 80) {
            $grade = 'A';
        } elseif ($score >= 65) {
            $grade = 'B';
        } elseif ($score >= 48) {
            $grade = 'C';
        }

        $priorities = [];
        if (in_array('disconnected-systems', $data['challenges'], true)) {
            $priorities[] = 'Consolidate CRM, website, and operations into one platform';
        }
        if (in_array('manual-follow-up', $data['challenges'], true)) {
            $priorities[] = 'Automate lead follow-up and client communications';
        }
        if (in_array('ai-visibility', $data['challenges'], true) || in_array('online-visibility', $data['challenges'], true)) {
            $priorities[] = 'Improve AI and search visibility for your business';
        }
        if (in_array('reporting-visibility', $data['challenges'], true)) {
            $priorities[] = 'Centralise reporting with a single business dashboard';
        }
        if (empty($priorities)) {
            $priorities[] = 'Activate the Core Platform and your first Industry App';
            $priorities[] = 'Connect existing tools with platform Connectors';
            $priorities[] = 'Enable automation for repetitive workflows';
        }

        return [
            'score' => $score,
            'grade' => $grade,
            'priorities' => array_slice($priorities, 0, 3),
            'summary' => self::maturity_summary($score, $grade),
        ];
    }

    private static function maturity_summary($score, $grade) {
        if ($grade === 'A') {
            return 'Strong digital foundation — ready to unify systems and scale with automation.';
        }
        if ($grade === 'B') {
            return 'Solid base with room to connect systems and reduce manual work.';
        }
        if ($grade === 'C') {
            return 'Fragmented stack — significant gains available from centralising operations.';
        }
        return 'Early stage — a unified platform will deliver the fastest operational improvement.';
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity */
    private static function recommend_plan(array $data, array $maturity) {
        $tier = 'professional';
        $tier_label = 'Growth';
        $team = $data['team_size'];

        if ($team === 'Just me' || $team === '1') {
            $tier = 'starter';
            $tier_label = 'Starter';
        } elseif ($team === '26–50' || $team === '50+') {
            $tier = 'business';
            $tier_label = 'Scale';
        } elseif ($team === '11–25') {
            $tier = 'business';
            $tier_label = 'Scale';
        }

        $industry_map = [
            'Real Estate' => 'real-estate',
            'Accommodation & Hospitality' => 'accommodation',
            'Finance & Mortgage Broking' => 'finance',
            'Professional Services' => 'services',
            'Commercial Property' => 'commercial',
            'Automotive' => 'automotive',
            'Creators & Personal Brands' => 'services',
        ];
        $industry_app = $industry_map[$data['industry']] ?? '';

        $apps = [];
        if ($industry_app !== '') {
            $apps[] = $industry_app;
        }
        if (in_array('ai-visibility', $data['challenges'], true) || in_array('online-visibility', $data['challenges'], true) || in_array('AI Visibility', $data['interested_in'], true)) {
            $apps[] = 'ai_visibility_pro';
        }
        if (in_array('manual-follow-up', $data['challenges'], true) || in_array('Automation', $data['interested_in'], true)) {
            $apps[] = 'automation_pro';
        }
        if (in_array('Voice AI', $data['interested_in'], true)) {
            $apps[] = 'voice_ai';
        }

        $apps = array_values(array_unique($apps));

        return [
            'platform_tier' => $tier,
            'platform_tier_label' => $tier_label,
            'industry_app' => $industry_app,
            'recommended_apps' => $apps,
            'rationale' => sprintf(
                'Based on team size (%s), industry, and operational challenges, we recommend %s with %s.',
                $team !== '' ? $team : 'your profile',
                $tier_label,
                $apps ? implode(', ', $apps) : 'Core Platform features'
            ),
            'maturity_grade' => $maturity['grade'],
            'maturity_score' => $maturity['score'],
        ];
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity @param array<string,mixed> $recommendation */
    private static function upsert_organisation(array $data, array $maturity, array $recommendation) {
        if (!class_exists('DG_Organisations')) {
            return 0;
        }

        $existing = $data['email'] !== '' ? DG_Organisations::get_by_email($data['email']) : null;
        $payload = [
            'name' => $data['business_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'website' => $data['website_url'],
            'industry' => $data['industry'],
            'status' => 'lead',
            'source' => 'discovery',
            'notes' => self::build_summary_text($data, $maturity, $recommendation),
        ];

        if ($existing) {
            DG_Organisations::update($existing->id, $payload);
            return (int) $existing->id;
        }

        return (int) DG_Organisations::create($payload);
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity @param array<string,mixed> $recommendation */
    private static function upsert_contact(array $data, $org_id, $first_name, $last_name, array $maturity, array $recommendation) {
        if (!class_exists('DG_Contacts')) {
            return new WP_Error('contacts_unavailable', 'Contacts service is not available.');
        }

        $payload = [
            'organisation_id' => $org_id ?: null,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'is_primary' => 1,
            'status' => 'lead',
            'source' => 'discovery',
            'tags' => self::TAGS,
            'notes' => self::build_summary_text($data, $maturity, $recommendation),
        ];

        $existing = DG_Contacts::get_by_email($data['email']);
        if ($existing) {
            DG_Contacts::update($existing->id, $payload);
            return (int) $existing->id;
        }

        return (int) DG_Contacts::create($payload);
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity @param array<string,mixed> $recommendation */
    private static function store_submission_meta($contact_id, $org_id, array $data, array $maturity, array $recommendation) {
        if (!class_exists('DG_Entity_Meta')) {
            return;
        }

        $bundle = [
            'submission' => $data,
            'maturity' => $maturity,
            'recommendation' => $recommendation,
        ];

        DG_Entity_Meta::set('contact', $contact_id, 'discovery_submission', $bundle);
        DG_Entity_Meta::set('contact', $contact_id, 'discovery_submitted_at', current_time('mysql'));
        DG_Entity_Meta::set('contact', $contact_id, 'business_health_score', $maturity['score']);
        DG_Entity_Meta::set('contact', $contact_id, 'maturity_grade', $maturity['grade']);

        if ($org_id) {
            DG_Entity_Meta::set('organisation', $org_id, 'discovery_submission', $bundle);
            DG_Entity_Meta::set('organisation', $org_id, 'business_health_score', $maturity['score']);
        }

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 'contact',
                'entity_id' => $contact_id,
                'activity_type' => 'form',
                'subject' => 'AI Discovery submitted',
                'content' => $data['business_name'] . ' — Grade ' . $maturity['grade'] . ' (' . $maturity['score'] . '/100)',
            ]);
        }
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity @param array<string,mixed> $recommendation */
    private static function create_follow_up_task($contact_id, array $data, array $maturity, array $recommendation) {
        if (!class_exists('DG_Tasks')) {
            return;
        }

        DG_Tasks::create([
            'title' => 'Review discovery — ' . $data['full_name'],
            'description' => "Business: {$data['business_name']}\nMaturity: {$maturity['grade']} ({$maturity['score']}/100)\nRecommended: {$recommendation['platform_tier_label']}\n\n" . self::build_summary_text($data, $maturity, $recommendation),
            'contact_id' => $contact_id,
            'entity_type' => 'contact',
            'entity_id' => $contact_id,
            'priority' => 'high',
            'status' => 'pending',
            'due_date' => date('Y-m-d H:i:s', strtotime('+1 weekday')),
        ]);
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity @param array<string,mixed> $recommendation */
    private static function send_prospect_email(array $data, array $maturity, array $recommendation) {
        $first = class_exists('DG_Email_Names') ? DG_Email_Names::first_name($data['full_name']) : $data['full_name'];
        $subject = 'Your DigitalGate Platform Discovery Results';

        $priorities = implode('</li><li>', array_map('esc_html', $maturity['priorities']));
        $contact_url = home_url('/contact/');

        if (class_exists('DG_Marketing_Emails')) {
            $inner = '<h2 style="color:#FFFFFF;font-size:22px;margin:0 0 16px;">Hi ' . esc_html($first) . ',</h2>'
                . '<p style="color:#E2E8F0;line-height:1.65;">Thank you for completing the DigitalGate AI Discovery. Here is your initial Digital Maturity snapshot.</p>'
                . '<p style="color:#93C5FD;font-size:28px;font-weight:800;margin:16px 0;">Grade ' . esc_html($maturity['grade']) . ' · ' . (int) $maturity['score'] . '/100</p>'
                . '<p style="color:#E2E8F0;line-height:1.65;">' . esc_html($maturity['summary']) . '</p>'
                . '<p style="color:#E2E8F0;line-height:1.65;"><strong>Recommended starting point:</strong> ' . esc_html($recommendation['platform_tier_label']) . '</p>'
                . '<ul style="color:#E2E8F0;line-height:1.65;"><li>' . $priorities . '</li></ul>'
                . DG_Marketing_Emails::cta($contact_url, 'Book your free consultation');
            $message = DG_Marketing_Emails::wrap($inner, ['footer_note' => 'DigitalGate — The Gateway to Your Digital World']);
            $headers = DG_Marketing_Emails::mail_headers(true);
        } else {
            $message = "Hi {$first},\n\nGrade {$maturity['grade']} ({$maturity['score']}/100)\n{$maturity['summary']}\n\nRecommended: {$recommendation['platform_tier_label']}\n\nBook a consultation: {$contact_url}\n";
            $headers = ['Content-Type: text/html; charset=UTF-8', 'From: DigitalGate <hello@digitalgate.com.au>'];
        }

        wp_mail($data['email'], $subject, $message, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity @param array<string,mixed> $recommendation */
    private static function send_admin_email(array $data, $contact_id, $org_id, array $maturity, array $recommendation) {
        $to = apply_filters('dg_client_discovery_admin_email', self::ADMIN_EMAIL);
        $subject = 'New AI Discovery — ' . $data['business_name'];

        $rows = [
            'Contact' => $data['full_name'],
            'Business' => $data['business_name'],
            'Email' => $data['email'],
            'Phone' => $data['phone'] ?: 'Not provided',
            'Industry' => $data['industry'],
            'Team size' => $data['team_size'],
            'Software spend' => $data['software_spend'],
            'Maturity' => $maturity['grade'] . ' (' . $maturity['score'] . '/100)',
            'Recommended tier' => $recommendation['platform_tier_label'],
            'Recommended apps' => implode(', ', $recommendation['recommended_apps']),
            'Challenges' => implode(', ', $data['challenges']),
            'Goals' => $data['goals_message'],
        ];

        $body = '<h2>New AI Discovery submission</h2>';
        foreach ($rows as $label => $value) {
            if ($value === '') {
                continue;
            }
            $body .= '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</p>';
        }

        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: DigitalGate <hello@digitalgate.com.au>'];
        wp_mail($to, $subject, $body, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $maturity @param array<string,mixed> $recommendation */
    private static function build_summary_text(array $data, array $maturity, array $recommendation) {
        $lines = [
            'Digital Maturity: ' . $maturity['grade'] . ' (' . $maturity['score'] . '/100)',
            'Recommended tier: ' . $recommendation['platform_tier_label'],
            'Industry: ' . $data['industry'],
            'Team: ' . $data['team_size'],
            'Spend: ' . $data['software_spend'],
            'CRM: ' . $data['crm'],
            'Challenges: ' . implode(', ', $data['challenges']),
            'Goals: ' . $data['goals_message'],
        ];
        return implode("\n", array_filter($lines));
    }

    private static function redirect_error($code) {
        $map = [
            'rate-limit' => 'Too many requests. Please try again later.',
            'invalid-request' => 'Invalid request. Please refresh and try again.',
            'missing-fields' => 'Please complete all required fields.',
            'invalid-email' => 'Please enter a valid email address.',
        ];
        $error = isset($map[$code]) ? $code : 'error';
        wp_safe_redirect(home_url('/discover/?discovery_error=' . rawurlencode($error)));
        exit;
    }
}

add_action('plugins_loaded', ['DG_Client_Discovery', 'init'], 12);
