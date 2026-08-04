<?php
/**
 * AI assist prompts for SEO, Social, CRM, RE, Accommodation, etc.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Assist {

    const SYSTEM = 'You are an expert marketing copywriter and SEO specialist for Australian businesses. Use Australian English. Be concise and actionable.';

    /** @return bool */
    public static function available() {
        return class_exists('DG_AI_Client') && DG_AI_Client::available();
    }

    /** @return array<string,mixed>|WP_Error */
    public static function seo_optimize($post_id) {
        if (!class_exists('DG_SEO_Analyzer')) {
            return new WP_Error('missing', 'SEO analyzer unavailable.');
        }
        $analysis = DG_SEO_Analyzer::analyze($post_id);
        if (!empty($analysis['error'])) {
            return new WP_Error('analyze_failed', (string) $analysis['error']);
        }

        $post = get_post($post_id);
        $ctx = DG_AI_Client::site_context();
        $content = $post ? wp_strip_all_tags($post->post_content) : '';
        $content = substr(preg_replace('/\s+/', ' ', trim($content)), 0, 2500);

        $issues = [];
        foreach ($analysis['checks'] ?? [] as $check) {
            if (($check['status'] ?? '') !== 'pass') {
                $issues[] = ($check['label'] ?? '') . ': ' . ($check['message'] ?? '');
            }
        }

        $fields = $analysis['fields'] ?? [];
        $robots_opts = implode(', ', array_keys(DG_SEO_Settings::robots_options()));

        $prompt = implode("\n", [
            'Optimise SEO metadata for this page.',
            'Site: ' . $ctx['site_name'] . ' (' . $ctx['host'] . ')',
            'Page: ' . ($analysis['post_title'] ?? '') . ' | slug: ' . ($post->post_name ?? ''),
            'URL: ' . ($analysis['permalink'] ?? ''),
            'Current keyword: ' . ($fields['focus_keyword'] ?? ''),
            'Content excerpt: ' . ($content ?: '(none)'),
            'Issues: ' . ($issues ? implode('; ', $issues) : 'general polish'),
            'Rules: title max 60 chars, description max 160, robots one of: ' . $robots_opts,
            'Client portal/login/onboarding thank-you → noindex,nofollow. Public marketing → index,follow.',
            'JSON: {"focus_keyword":"","title":"","description":"","og_title":"","og_description":"","robots":"index,follow","rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 700);
        if (is_wp_error($result)) {
            return $result;
        }

        return self::normalize_seo_fields($result, $post_id);
    }

    /** @return array<string,mixed>|WP_Error */
    public static function suburb_page($post_id) {
        $base = self::seo_optimize($post_id);
        if (is_wp_error($base)) {
            return $base;
        }

        $post = get_post($post_id);
        $ctx = DG_AI_Client::site_context();
        $prompt = implode("\n", [
            'Rewrite this as a local suburb landing page for a real estate agency.',
            'Suburb/page: ' . ($post->post_title ?? ''),
            'Site: ' . $ctx['site_name'],
            'Emphasise local expertise, appraisals, and vendor leads.',
            'JSON same fields as before.',
            'Current: ' . wp_json_encode([
                'focus_keyword' => $base['focus_keyword'] ?? '',
                'title' => $base['title'] ?? '',
                'description' => $base['description'] ?? '',
            ]),
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 700);
        if (is_wp_error($result)) {
            return $base;
        }

        return self::normalize_seo_fields($result, $post_id);
    }

    /** @param array<string,mixed> $json */
    private static function normalize_seo_fields(array $json, $post_id) {
        $title = sanitize_text_field($json['title'] ?? '');
        $description = sanitize_textarea_field($json['description'] ?? '');
        $focus = sanitize_text_field($json['focus_keyword'] ?? '');
        $robots = sanitize_text_field($json['robots'] ?? 'index,follow');
        if (!in_array($robots, array_keys(DG_SEO_Settings::robots_options()), true)) {
            $robots = 'index,follow';
        }
        if (strlen($title) > 60) {
            $title = substr($title, 0, 60);
        }
        if (strlen($description) > 160) {
            $description = substr($description, 0, 160);
        }
        if ($title === '' || $description === '' || $focus === '') {
            return new WP_Error('ai_incomplete', 'AI response was incomplete.');
        }

        return [
            'focus_keyword' => $focus,
            'title' => $title,
            'description' => $description,
            'og_title' => sanitize_text_field($json['og_title'] ?? $title),
            'og_description' => sanitize_textarea_field($json['og_description'] ?? $description),
            'robots' => $robots,
            'rationale' => sanitize_text_field($json['rationale'] ?? ''),
            'provider' => $json['provider'] ?? '',
            'post_id' => (int) $post_id,
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function social_compose(array $args) {
        $ctx = DG_AI_Client::site_context();
        $topic = sanitize_text_field($args['topic'] ?? '');
        $link = esc_url_raw($args['link_url'] ?? home_url('/'));
        $platforms = isset($args['platforms']) && is_array($args['platforms'])
            ? array_map('sanitize_key', $args['platforms'])
            : ['linkedin', 'facebook', 'instagram'];

        $prompt = implode("\n", [
            'Write a social media post for ' . $ctx['site_name'] . '.',
            'Topic or brief: ' . ($topic ?: 'General brand awareness'),
            'Link to include context for: ' . $link,
            'Platforms: ' . implode(', ', $platforms),
            'Australian English. Include emojis sparingly.',
            'JSON: {"title":"optional pin title","content":"main post text","hashtags":"space-separated tags","rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 600);
        if (is_wp_error($result)) {
            return $result;
        }

        $content = sanitize_textarea_field($result['content'] ?? '');
        if ($content === '') {
            return new WP_Error('ai_incomplete', 'AI did not return post content.');
        }

        $hashtags = sanitize_text_field($result['hashtags'] ?? '');
        if ($hashtags && stripos($content, '#') === false) {
            $content .= "\n\n" . $hashtags;
        }

        return [
            'title' => sanitize_text_field($result['title'] ?? ''),
            'content' => $content,
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function property_description($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'property') {
            return new WP_Error('invalid', 'Invalid property.');
        }

        $meta = [];
        foreach ([
            'roe_property_title', 'roe_property_address', 'roe_property_suburb', 'roe_property_state',
            'roe_property_price', 'roe_property_type', 'roe_property_bedrooms', 'roe_property_bathrooms',
            'roe_property_car_spaces', 'roe_property_land_size', 'roe_property_features',
        ] as $key) {
            $meta[$key] = get_post_meta($post_id, $key, true);
        }

        $ctx = DG_AI_Client::site_context();
        $prompt = implode("\n", [
            'Write a compelling real estate listing for an Australian agency website.',
            'Agency/site: ' . $ctx['site_name'],
            'Property data: ' . wp_json_encode($meta),
            'Use HTML paragraphs (<p>) only. 2-4 paragraphs. Highlight lifestyle and location.',
            'JSON: {"title":"listing headline","description":"HTML description","excerpt":"plain text 155 chars for SEO","rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 900);
        if (is_wp_error($result)) {
            return $result;
        }

        $description = wp_kses_post($result['description'] ?? '');
        if ($description === '') {
            return new WP_Error('ai_incomplete', 'AI did not return a description.');
        }

        return [
            'title' => sanitize_text_field($result['title'] ?? $post->post_title),
            'description' => $description,
            'excerpt' => sanitize_text_field($result['excerpt'] ?? ''),
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function accommodation_description($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'dg_accommodation') {
            return new WP_Error('invalid', 'Invalid accommodation.');
        }

        $meta = [
            'name' => $post->post_title,
            'description' => get_post_meta($post_id, 'dg_description', true),
            'weekday_rate' => get_post_meta($post_id, 'dg_weekday_rate', true),
            'weekend_rate' => get_post_meta($post_id, 'dg_weekend_rate', true),
            'features' => get_post_meta($post_id, 'dg_features', true),
        ];

        $ctx = DG_AI_Client::site_context();
        $prompt = implode("\n", [
            'Write accommodation stay copy for a boutique Australian retreat website.',
            'Property: ' . $ctx['site_name'],
            'Unit data: ' . wp_json_encode($meta),
            'Tone: luxury eco, warm, sensory (rainforest, valley, privacy). Plain text with line breaks.',
            'JSON: {"description":"full stay description","tagline":"short hero line under 80 chars","rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 800);
        if (is_wp_error($result)) {
            return $result;
        }

        $description = sanitize_textarea_field($result['description'] ?? '');
        if ($description === '') {
            return new WP_Error('ai_incomplete', 'AI did not return a description.');
        }

        return [
            'description' => $description,
            'tagline' => sanitize_text_field($result['tagline'] ?? ''),
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function contact_draft($contact_id, $channel = 'email', $purpose = 'follow_up') {
        $contact = class_exists('DG_Contacts') ? DG_Contacts::get((int) $contact_id) : null;
        if (!$contact) {
            return new WP_Error('invalid', 'Contact not found.');
        }

        $ctx = DG_AI_Client::site_context();
        $name = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
        $channel = $channel === 'sms' ? 'sms' : 'email';

        $prompt = implode("\n", [
            'Draft a ' . $channel . ' for ' . $ctx['site_name'] . ' to send to a CRM contact.',
            'Purpose: ' . sanitize_text_field($purpose),
            'Contact: ' . $name . ', email: ' . ($contact->email ?? '') . ', source: ' . ($contact->source ?? ''),
            'Notes: ' . substr((string) ($contact->notes ?? ''), 0, 500),
            $channel === 'sms' ? 'Max 320 characters for SMS.' : 'Professional but warm email with subject line.',
            'JSON: {"subject":"email subject or empty for sms","body":"message body","rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 600);
        if (is_wp_error($result)) {
            return $result;
        }

        $body = sanitize_textarea_field($result['body'] ?? '');
        if ($body === '') {
            return new WP_Error('ai_incomplete', 'AI did not return message content.');
        }

        if ($channel === 'sms' && strlen($body) > 320) {
            $body = substr($body, 0, 317) . '…';
        }

        return [
            'channel' => $channel,
            'subject' => sanitize_text_field($result['subject'] ?? ''),
            'body' => $body,
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function visibility_fix($recommendation, array $context = []) {
        $ctx = DG_AI_Client::site_context();
        $settings = class_exists('DG_AI_Visibility_Settings') ? DG_AI_Visibility_Settings::all() : [];

        $prompt = implode("\n", [
            'Provide an actionable fix for this AI visibility recommendation.',
            'Business: ' . ($settings['business_name'] ?? $ctx['site_name']),
            'Industry: ' . ($settings['industry'] ?? ''),
            'Location: ' . ($settings['location'] ?? ''),
            'Recommendation: ' . sanitize_text_field($recommendation),
            'Latest scores: ' . wp_json_encode($context),
            'JSON: {"action_type":"content|meta|llms|schema|other","title":"short action title","content":"ready-to-use copy or step-by-step fix","apply_target":"where to paste e.g. SEO Pro, llms.txt, new page section","rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 900);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'action_type' => sanitize_key($result['action_type'] ?? 'other'),
            'title' => sanitize_text_field($result['title'] ?? 'Suggested fix'),
            'content' => sanitize_textarea_field($result['content'] ?? ''),
            'apply_target' => sanitize_text_field($result['apply_target'] ?? ''),
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function onboarding_summary(array $data) {
        $ctx = DG_AI_Client::site_context();
        unset($data['website_login_password'], $data['website_password']);
        $payload = wp_json_encode($data);

        $prompt = implode("\n", [
            'Summarise this new client onboarding submission for the DigitalGate team.',
            'Site: ' . $ctx['site_name'],
            'Submission: ' . substr($payload, 0, 6000),
            'JSON: {"summary":"2-3 paragraph admin brief","priorities":["top 3 setup tasks"],"modules_to_enable":["list"],"red_flags":["any concerns or empty"],"rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 800);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'summary' => sanitize_textarea_field($result['summary'] ?? ''),
            'priorities' => array_map('sanitize_text_field', (array) ($result['priorities'] ?? [])),
            'modules_to_enable' => array_map('sanitize_text_field', (array) ($result['modules_to_enable'] ?? [])),
            'red_flags' => array_map('sanitize_text_field', (array) ($result['red_flags'] ?? [])),
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function automation_suggest($trigger = '', $goal = '') {
        $ctx = DG_AI_Client::site_context();
        $triggers = class_exists('DG_Automation_Pro_Workflows') ? DG_Automation_Pro_Workflows::available_triggers() : [];
        $actions = class_exists('DG_Automation_Pro_Workflows') ? DG_Automation_Pro_Workflows::available_actions() : [];

        $prompt = implode("\n", [
            'Suggest an automation workflow for ' . $ctx['site_name'] . '.',
            'Trigger hint: ' . sanitize_text_field($trigger ?: 'new lead'),
            'Goal: ' . sanitize_text_field($goal ?: 'nurture and convert'),
            'Available triggers: ' . wp_json_encode(array_keys($triggers)),
            'Available actions: ' . wp_json_encode(array_keys($actions)),
            'JSON: {"name":"workflow name","description":"one sentence","steps":[{"action":"action_key","delay_hours":0,"config_note":"what to configure"}],"rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 800);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'name' => sanitize_text_field($result['name'] ?? 'Suggested workflow'),
            'description' => sanitize_text_field($result['description'] ?? ''),
            'steps' => is_array($result['steps'] ?? null) ? $result['steps'] : [],
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function blog_draft($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid', 'Post not found.');
        }

        $ctx = DG_AI_Client::site_context();
        $existing = wp_strip_all_tags($post->post_content);
        $prompt = implode("\n", [
            'Draft or improve a blog/insights article for ' . $ctx['site_name'] . '.',
            'Title: ' . $post->post_title,
            'Existing content: ' . substr($existing, 0, 1500),
            'Include SEO-friendly structure with H2 sections. HTML: p, h2, ul, li only.',
            'JSON: {"title":"article title","excerpt":"meta description 155 chars","content":"HTML body","focus_keyword":"","rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 1500);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'title' => sanitize_text_field($result['title'] ?? $post->post_title),
            'excerpt' => sanitize_text_field($result['excerpt'] ?? ''),
            'content' => wp_kses_post($result['content'] ?? ''),
            'focus_keyword' => sanitize_text_field($result['focus_keyword'] ?? ''),
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function audit_executive_summary($audit_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_platform_audits';
        $audit = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, c.company_name FROM $table a
             LEFT JOIN {$wpdb->prefix}dg_platform_companies c ON c.id = a.company_id
             WHERE a.id = %d",
            (int) $audit_id
        ));
        if (!$audit) {
            return new WP_Error('invalid', 'Audit not found.');
        }

        $prompt = implode("\n", [
            'Write an executive summary for a real estate agency visibility audit report.',
            'Company: ' . ($audit->company_name ?? ''),
            'Overall score: ' . ($audit->overall_score ?? '') . ' Grade: ' . ($audit->grade ?? ''),
            'AI score: ' . ($audit->ai_score ?? ''),
            'Google score: ' . ($audit->google_score ?? ''),
            'Website score: ' . ($audit->website_score ?? ''),
            'JSON: {"executive_summary":"3-4 sentences for PDF cover","key_wins":["2-3 bullets"],"priority_actions":["3 urgent actions"],"rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 700);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'executive_summary' => sanitize_textarea_field($result['executive_summary'] ?? ''),
            'key_wins' => array_map('sanitize_text_field', (array) ($result['key_wins'] ?? [])),
            'priority_actions' => array_map('sanitize_text_field', (array) ($result['priority_actions'] ?? [])),
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
            'audit_id' => (int) $audit_id,
        ];
    }

    /** @return array<string,mixed>|WP_Error */
    public static function reports_narrative(array $context) {
        $ctx = DG_AI_Client::site_context();
        $prompt = implode("\n", [
            'Write a brief client-facing progress narrative for a DigitalGate platform report.',
            'Client site: ' . $ctx['site_name'],
            'Metrics snapshot: ' . wp_json_encode($context),
            'Tone: professional, encouraging, specific numbers where provided. 2 short paragraphs.',
            'JSON: {"headline":"one line","narrative":"2 paragraphs plain text","highlights":["3 bullet highlights"],"rationale":""}',
        ]);

        $result = DG_AI_Client::chat_json(self::SYSTEM, $prompt, 700);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'headline' => sanitize_text_field($result['headline'] ?? ''),
            'narrative' => sanitize_textarea_field($result['narrative'] ?? ''),
            'highlights' => array_map('sanitize_text_field', (array) ($result['highlights'] ?? [])),
            'rationale' => sanitize_text_field($result['rationale'] ?? ''),
            'provider' => $result['provider'] ?? '',
        ];
    }

    /** Save onboarding AI summary to contact notes + activity. */
    public static function persist_onboarding_summary($contact_id, array $data, $summary) {
        if (!class_exists('DG_Contacts') || empty($summary['summary'])) {
            return;
        }

        $contact = DG_Contacts::get((int) $contact_id);
        if (!$contact) {
            return;
        }

        $block = "--- AI onboarding brief (" . wp_date('Y-m-d') . ") ---\n"
            . $summary['summary'] . "\n\nPriorities: " . implode('; ', $summary['priorities'] ?? [])
            . "\nModules: " . implode(', ', $summary['modules_to_enable'] ?? []);

        $notes = trim((string) ($contact->notes ?? ''));
        $notes = $notes !== '' ? $notes . "\n\n" . $block : $block;
        DG_Contacts::update((int) $contact_id, ['notes' => $notes]);

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'contact_id' => (int) $contact_id,
                'activity_type' => 'note',
                'subject' => 'AI onboarding summary',
                'content' => $summary['summary'],
            ]);
        }
    }
}

