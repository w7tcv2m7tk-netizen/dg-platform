<?php
/**
 * Agency audit 5-email follow-up sequence (DG_Automation integration).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Audit_Followups {

    const AUTOMATION_OPTION = 'dg_marketing_audit_automation_id';

    public static function init() {
        add_action('dg_automation_cron', [__CLASS__, 'process']);
        add_action('init', [__CLASS__, 'ensure_table']);
        add_action('init', [__CLASS__, 'maybe_seed_automation'], 20);
        add_action('dg_marketing_audit_created', [__CLASS__, 'on_audit_created'], 20, 6);
        add_action('init', [__CLASS__, 'clear_legacy_cron'], 99);
    }

    public static function clear_legacy_cron() {
        if (wp_next_scheduled('dg_process_audit_emails')) {
            wp_clear_scheduled_hook('dg_process_audit_emails');
        }
    }

    public static function queue_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_automation_audit_emails';
    }

    public static function ensure_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = self::queue_table();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            company_id bigint(20) NOT NULL,
            contact_id bigint(20) DEFAULT NULL,
            email varchar(255) NOT NULL,
            email_number int(11) NOT NULL,
            email_subject varchar(255) NOT NULL,
            email_content longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY email (email)
        ) $charset_collate;");
    }

    public static function maybe_seed_automation() {
        if (get_option(self::AUTOMATION_OPTION) || !class_exists('DG_Automation')) {
            return;
        }

        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . DG_Automation::table() . ' WHERE trigger_type = %s LIMIT 1',
            'marketing_audit_followup_sequence'
        ));
        if ($existing) {
            update_option(self::AUTOMATION_OPTION, (int) $existing);
            return;
        }

        $id = DG_Automation::create([
            'name' => 'Agency Audit 5-Email Follow-up',
            'module' => 'marketing',
            'trigger_type' => 'marketing_audit_followup_sequence',
            'trigger_settings' => [
                'description' => 'Sends emails 1–5 after agency audit submission (0h, 24h, 48h, 72h, 96h).',
                'schedule' => 'every_minute',
            ],
            'steps' => [
                ['action' => 'send_email', 'delay_hours' => 0, 'label' => 'Email 1 — Audit results'],
                ['action' => 'send_email', 'delay_hours' => 24, 'label' => 'Email 2 — AI visibility'],
                ['action' => 'send_email', 'delay_hours' => 48, 'label' => 'Email 3 — Website performance'],
                ['action' => 'send_email', 'delay_hours' => 72, 'label' => 'Email 4 — Action plan'],
                ['action' => 'send_email', 'delay_hours' => 96, 'label' => 'Email 5 — Strategy session'],
            ],
            'is_active' => 1,
        ]);
        update_option(self::AUTOMATION_OPTION, (int) $id);
    }

    public static function on_audit_created($company_id, $full_name, $email, $phone, $company_name, $meta = []) {
        self::maybe_seed_automation();
        self::schedule(
            (int) $company_id,
            (int) ($meta['contact_id'] ?? 0),
            $email,
            $full_name,
            $company_name,
            $meta['audit_data'] ?? [],
            $meta['audit_url'] ?? ''
        );
    }

    public static function schedule($company_id, $contact_id, $email, $full_name, $company_name, $audit_data, $audit_url) {
        global $wpdb;
        self::ensure_table();
        $table = self::queue_table();

        $templates = self::email_templates($company_name, $full_name, $audit_data, $audit_url);
        $schedule = [0, 24, 48, 72, 96];

        foreach ($schedule as $index => $hours) {
            $email_number = $index + 1;
            $template = $templates[$email_number];
            $sent_at = $hours === 0
                ? current_time('mysql')
                : date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours'));

            $wpdb->insert($table, [
                'company_id' => (int) $company_id,
                'contact_id' => $contact_id ?: null,
                'email' => sanitize_email($email),
                'email_number' => $email_number,
                'email_subject' => $template['subject'],
                'email_content' => $template['content'],
                'status' => 'pending',
                'sent_at' => $sent_at,
                'created_at' => current_time('mysql'),
            ]);
        }

        $notes = $wpdb->prefix . 'dg_platform_notes';
        if ($wpdb->get_var("SHOW TABLES LIKE '$notes'") === $notes) {
            $wpdb->insert($notes, [
                'company_id' => (int) $company_id,
                'content' => "📧 5-email automation sequence scheduled for {$full_name} ({$email})",
                'type' => 'automation',
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    public static function process() {
        global $wpdb;
        $table = self::queue_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }

        self::maybe_seed_automation();

        $pending = $wpdb->get_results(
            "SELECT * FROM $table
             WHERE status = 'pending'
             AND sent_at <= NOW()
             ORDER BY email_number ASC
             LIMIT 10"
        );

        foreach ($pending as $record) {
            self::send($record);
        }
    }

    private static function send($record) {
        global $wpdb;
        $table = self::queue_table();

        $message = class_exists('DG_Marketing_Emails')
            ? DG_Marketing_Emails::wrap($record->email_content)
            : self::wrap_html($record->email_content);

        $headers = class_exists('DG_Marketing_Emails')
            ? DG_Marketing_Emails::mail_headers()
            : [
                'Content-Type: text/html; charset=UTF-8',
                'From: Ben Roe | DigitalGate <hello@digitalgate.com.au>',
                'Reply-To: hello@digitalgate.com.au',
            ];

        $sent = wp_mail($record->email, $record->email_subject, $message, $headers);
        $wpdb->update($table, ['status' => $sent ? 'sent' : 'failed'], ['id' => (int) $record->id]);

        if ($sent) {
            $notes = $wpdb->prefix . 'dg_platform_notes';
            if ($wpdb->get_var("SHOW TABLES LIKE '$notes'") === $notes) {
                $wpdb->insert($notes, [
                    'company_id' => (int) $record->company_id,
                    'content' => "📧 Email #{$record->email_number} sent: {$record->email_subject}",
                    'type' => 'automation',
                    'created_at' => current_time('mysql'),
                ]);
            }
        }
    }

    private static function wrap_html($content) {
        return '<html><body><div style="max-width:600px;margin:0 auto;padding:20px;">' . $content . '</div></body></html>';
    }

    public static function queue_stats() {
        global $wpdb;
        $table = self::queue_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['total' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0];
        }
        return [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'sent' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'sent'"),
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'"),
            'failed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'"),
        ];
    }

    public static function recent_queue($limit = 20) {
        global $wpdb;
        $table = self::queue_table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
            (int) $limit
        ));
    }

    private static function email_templates($company_name, $full_name, $audit_data, $audit_url) {
        $h2 = 'color:#FFFFFF;font-size:22px;font-weight:700;margin:0 0 16px;letter-spacing:-0.02em;';
        $p = 'color:#E2E8F0;font-size:16px;line-height:1.65;margin:0 0 16px;';
        $ul = 'color:#E2E8F0;font-size:15px;line-height:1.8;padding-left:20px;margin:0 0 16px;';
        $link = 'color:#60A5FA;text-decoration:none;';
        $cta = 'background:#3B82F6;color:#fff;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:600;display:inline-block;';
        $ai = (int) ($audit_data['ai_score'] ?? 0);
        $web = (int) ($audit_data['website_score'] ?? 0);
        $google = (int) ($audit_data['google_score'] ?? 0);
        $vendor = (int) ($audit_data['vendor_lead_score'] ?? 0);
        $recs = $audit_data['recommendations'] ?? [];

        return [
            1 => [
                'subject' => 'Your Agency Visibility Audit Results Are In',
                'content' => '
                <h2 style="' . $h2 . '">Hi ' . esc_html($full_name) . ',</h2>
                <p style="' . $p . '">Your Agency Visibility Audit for <strong style="color:#60A5FA;">' . esc_html($company_name) . '</strong> is ready.</p>
                <p style="' . $p . '">Here are your key results:</p>
                ' . (class_exists('DG_Marketing_Emails') ? DG_Marketing_Emails::score_table([
                    'Overall Score' => ($audit_data['overall_score'] ?? 0) . '%',
                    'Grade' => $audit_data['grade'] ?? '',
                    'AI Visibility' => $ai . '%',
                    'Website Performance' => $web . '%',
                ]) : '') . '
                <p style="margin:30px 0 20px 0;text-align:center;"><a href="' . esc_url($audit_url) . '" style="' . $cta . '">View Full Report</a></p>
                <p style="' . $p . '">I\'ll send you a breakdown of your top growth opportunities in my next email.</p>
                ',
            ],
            2 => [
                'subject' => 'Your AI Visibility Breakdown & What It Means',
                'content' => '
                <h2 style="' . $h2 . '">Hi ' . esc_html($full_name) . ',</h2>
                <p style="' . $p . '">Let\'s break down your AI Visibility score for <strong style="color:#60A5FA;">' . esc_html($company_name) . '</strong>.</p>
                <p style="' . $p . '">Your AI Visibility score is <strong style="color:#FFFFFF;">' . $ai . '%</strong> ' . ($ai < 50 ? '— this means AI systems like ChatGPT and Google AI Mode are not currently recommending your agency.' : '— this is a solid foundation, but there\'s room to grow.') . '</p>
                <p style="' . $p . '">Here\'s how AI visibility works:</p>
                <ul style="' . $ul . '">
                    <li>AI systems scan the web for consistent, trusted information</li>
                    <li>They look for authority signals, reviews, and local citations</li>
                    <li>The more consistent your presence, the higher your AI visibility</li>
                </ul>
                <p style="' . $p . '">Want to see how this compares to other agencies in your area? <a href="' . esc_url(admin_url('admin.php?page=dg-platform-ai')) . '" style="' . $link . '">View the AI Visibility Dashboard →</a></p>
                ',
            ],
            3 => [
                'subject' => 'Your Website Performance & Lead Generation Potential',
                'content' => '
                <h2 style="' . $h2 . '">Hi ' . esc_html($full_name) . ',</h2>
                <p style="' . $p . '">Let\'s talk about your website performance and lead potential for <strong style="color:#60A5FA;">' . esc_html($company_name) . '</strong>.</p>
                <p style="' . $p . '">Your website scored <strong style="color:#FFFFFF;">' . $web . '%</strong> on Google PageSpeed ' . ($web < 50 ? '— which is below average. This means potential vendors are likely leaving your site before enquiring.' : '— which is above average, giving you a good foundation.') . '</p>
                <p style="' . $p . '"><strong style="color:#FFFFFF;">Your Lead Potential Score:</strong> ' . $vendor . '%</p>
                <ul style="' . $ul . '">
                    <li>' . ($web < 50 ? '❌ Slow loading times are hurting conversions' : '✅ Your website speed is good') . '</li>
                    <li>' . ($vendor < 50 ? '❌ Limited content targeting vendors' : '✅ You have good vendor-focused content') . '</li>
                    <li>' . ($google < 50 ? '❌ Google visibility needs improvement' : '✅ Your Google presence is strong') . '</li>
                </ul>
                <p style="margin:30px 0 20px 0;text-align:center;"><a href="' . esc_url($audit_url) . '" style="' . $cta . '">See Your Full Website Analysis →</a></p>
                ',
            ],
            4 => [
                'subject' => 'Action Plan: 3 Steps to Improve Your Agency Visibility',
                'content' => '
                <h2 style="' . $h2 . '">Hi ' . esc_html($full_name) . ',</h2>
                <p style="' . $p . '">Based on your audit for <strong style="color:#60A5FA;">' . esc_html($company_name) . '</strong>, here are the <strong style="color:#FFFFFF;">3 most impactful actions</strong> you can take right now:</p>
                <ol style="' . $ul . '">
                    <li><strong style="color:#FFFFFF;">Build local authority content</strong> — Create suburb-specific landing pages with detailed market insights</li>
                    <li><strong style="color:#FFFFFF;">Improve your Google Business Profile</strong> — Add photos, posts, and respond to all reviews</li>
                    <li><strong style="color:#FFFFFF;">Optimize for AI search</strong> — Structure your content to answer common vendor questions</li>
                </ol>
                <ul style="' . $ul . '">'
                . implode('', array_map(function ($rec) {
                    return '<li>✓ ' . esc_html($rec) . '</li>';
                }, array_slice($recs, 0, 3))) .
                '</ul>
                <p style="margin:30px 0 20px 0;text-align:center;"><a href="https://digitalgate.com.au/strategy-session" style="' . $cta . '">Book Your Free Strategy Session →</a></p>
                ',
            ],
            5 => [
                'subject' => 'Final Step: Let\'s Build Your Growth Plan',
                'content' => '
                <h2 style="' . $h2 . '">Hi ' . esc_html($full_name) . ',</h2>
                <p style="' . $p . '">This is the final email in your Agency Visibility Audit series for <strong style="color:#60A5FA;">' . esc_html($company_name) . '</strong>.</p>
                <ul style="' . $ul . '">
                    <li>✅ Your AI visibility score and breakdown</li>
                    <li>✅ Your website performance and lead potential</li>
                    <li>✅ 3 key actions to improve your visibility</li>
                </ul>
                <p style="' . $p . '"><strong style="color:#FFFFFF;">Now it\'s time to take the next step.</strong></p>
                <p style="margin:30px 0 20px 0;text-align:center;"><a href="https://digitalgate.com.au/strategy-session" style="' . $cta . ' font-size:16px;padding:14px 36px;">📅 Book Your Free Strategy Session</a></p>
                <p style="color:#E2E8F0;font-size:16px;line-height:1.65;margin:16px 0 0;">Looking forward to helping you grow,</p>
                <p style="color:#E2E8F0;font-size:16px;line-height:1.65;margin:8px 0 0;"><strong style="color:#FFFFFF;">Ben Roe</strong><br><span style="color:#94A3B8;font-size:14px;">DigitalGate · Licensed QLD Real Estate Agent</span></p>
                ',
            ],
        ];
    }
}

DG_Marketing_Audit_Followups::init();
