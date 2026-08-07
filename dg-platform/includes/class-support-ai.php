<?php
/**
 * First-line AI auto-replies for Live Support chat.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Support_AI {

    const OPTION_ENABLED = 'dg_support_ai_auto_reply';

    public static function init() {
        add_action('dg_support_client_message_created', [__CLASS__, 'queue_auto_reply'], 10, 3);
        add_action('dg_support_ai_auto_reply', [__CLASS__, 'run_auto_reply'], 10, 2);
        add_action('admin_post_dg_support_resume_ai', [__CLASS__, 'handle_resume_ai']);
        add_action('admin_post_dg_support_pause_ai', [__CLASS__, 'handle_pause_ai']);
    }

    /** Global kill-switch (default on when AI keys exist). */
    public static function enabled() {
        $default = class_exists('DG_AI_Client') && DG_AI_Client::available() ? '1' : '0';
        $opt = get_option(self::OPTION_ENABLED, $default);
        return (bool) apply_filters('dg_support_ai_auto_reply_enabled', $opt === '1' || $opt === 1 || $opt === true);
    }

    /**
     * @param object $conversation
     * @param int    $message_id
     * @param string $body
     */
    public static function queue_auto_reply($conversation, $message_id, $body) {
        if (!self::enabled() || !class_exists('DG_AI_Client') || !DG_AI_Client::available()) {
            return;
        }

        $conversation_id = (int) ($conversation->id ?? 0);
        $message_id = (int) $message_id;
        if ($conversation_id <= 0 || $message_id <= 0) {
            return;
        }

        if (!empty($conversation->ai_paused)) {
            return;
        }

        $lock = 'dg_support_ai_lock_' . $conversation_id . '_' . $message_id;
        if (get_transient($lock)) {
            return;
        }
        set_transient($lock, 1, 180);

        register_shutdown_function(static function () use ($conversation_id, $message_id) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            // Ignore user abort so the reply still lands after the client disconnects.
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }
            self::run_auto_reply($conversation_id, $message_id);
        });
    }

    /**
     * @param int $conversation_id
     * @param int $trigger_message_id Client message that prompted this reply.
     */
    public static function run_auto_reply($conversation_id, $trigger_message_id) {
        $conversation_id = (int) $conversation_id;
        $trigger_message_id = (int) $trigger_message_id;
        if ($conversation_id <= 0 || !class_exists('DG_Client_Support')) {
            return;
        }

        if (!self::enabled() || !class_exists('DG_AI_Client') || !DG_AI_Client::available()) {
            return;
        }

        global $wpdb;
        $c_table = DG_Client_Support::conversations_table();
        $m_table = DG_Client_Support::messages_table();

        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$c_table} WHERE id = %d LIMIT 1",
            $conversation_id
        ));
        if (!$conversation || !empty($conversation->ai_paused)) {
            return;
        }

        // Only reply if the trigger is still the latest client message (no newer client turn).
        $latest_client = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$m_table}
             WHERE conversation_id = %d AND sender_role = 'client'
             ORDER BY id DESC LIMIT 1",
            $conversation_id
        ));
        if ($latest_client !== $trigger_message_id) {
            return;
        }

        // Already answered this turn (AI or staff after the client message).
        $after = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$m_table}
             WHERE conversation_id = %d AND id > %d AND sender_role IN ('ai','staff')
             ORDER BY id ASC LIMIT 1",
            $conversation_id,
            $trigger_message_id
        ));
        if ($after > 0) {
            return;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT sender_role, body, created_at FROM {$m_table}
             WHERE conversation_id = %d ORDER BY id DESC LIMIT 12",
            $conversation_id
        ));
        if (!$rows) {
            return;
        }
        $rows = array_reverse($rows);

        $user = get_userdata((int) $conversation->user_id);
        $client_name = $user ? ($user->display_name ?: $user->user_email) : 'Client';
        $client_email = $user ? $user->user_email : '';

        $transcript = '';
        foreach ($rows as $row) {
            $who = $row->sender_role === 'client'
                ? 'Client'
                : ($row->sender_role === 'ai' ? 'Assist' : 'Staff');
            $transcript .= $who . ': ' . trim((string) $row->body) . "\n";
        }

        $system = self::system_prompt();
        $user_prompt = "Client name: {$client_name}\nClient email: {$client_email}\n\nRecent thread:\n{$transcript}\n\nWrite the next Assist reply only (no role prefix).";

        $result = DG_AI_Client::chat($system, $user_prompt, 450);
        if (is_wp_error($result)) {
            error_log('[DG Support AI] ' . $result->get_error_message());
            return;
        }

        $text = trim((string) ($result['text'] ?? ''));
        $text = self::sanitize_reply($text);
        if ($text === '') {
            return;
        }

        // Re-check pause / race before insert.
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$c_table} WHERE id = %d LIMIT 1",
            $conversation_id
        ));
        if (!$conversation || !empty($conversation->ai_paused)) {
            return;
        }

        $after = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$m_table}
             WHERE conversation_id = %d AND id > %d AND sender_role IN ('ai','staff')
             ORDER BY id ASC LIMIT 1",
            $conversation_id,
            $trigger_message_id
        ));
        if ($after > 0) {
            return;
        }

        DG_Client_Support::insert_message_public($conversation_id, 'ai', 0, $text);
        // No client email for AI — chat poll only. Staff already got the client-message email.
    }

    public static function system_prompt() {
        $prompt = <<<'PROMPT'
You are DigitalGate Assist, first-line support for DigitalGate (Australian digital platform: websites, marketing, real estate tools, accommodation apps, and the client portal at app.digitalgate.com.au).

Voice: warm, concise, Australian English. You are an assistant, not Ben.

You can: explain portal/onboarding, point people to dashboards and apps, clarify how Live Support works, set expectations (business-hours human follow-up), and suggest emailing support@digitalgate.com.au when needed.

You cannot: change billing, issue refunds, access private data beyond this thread, promise SLAs, invent features, or claim a human is online right now.

If the client asks for a person, disputes money, reports an outage, or anything high-stakes/legal, say a DigitalGate team member will follow up and keep the reply short.

Keep replies under ~120 words. Prefer 1–3 short paragraphs or bullets. End with one clear next step when useful.
PROMPT;
        return (string) apply_filters('dg_support_ai_system_prompt', $prompt);
    }

    private static function sanitize_reply($text) {
        $text = preg_replace('/^\s*(Assist|DigitalGate Assist|Support)\s*:\s*/i', '', $text);
        $text = wp_strip_all_tags((string) $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        if (strlen($text) > 2000) {
            $text = substr($text, 0, 2000) . '…';
        }
        return trim($text);
    }

    public static function pause_conversation($conversation_id) {
        global $wpdb;
        if (!class_exists('DG_Client_Support')) {
            return;
        }
        $wpdb->update(
            DG_Client_Support::conversations_table(),
            ['ai_paused' => 1],
            ['id' => (int) $conversation_id],
            ['%d'],
            ['%d']
        );
    }

    public static function resume_conversation($conversation_id) {
        global $wpdb;
        if (!class_exists('DG_Client_Support')) {
            return;
        }
        $wpdb->update(
            DG_Client_Support::conversations_table(),
            ['ai_paused' => 0],
            ['id' => (int) $conversation_id],
            ['%d'],
            ['%d']
        );
    }

    public static function handle_pause_ai() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_support_pause_ai')) {
            wp_die('Unauthorized');
        }
        $id = (int) ($_POST['conversation_id'] ?? 0);
        if ($id > 0) {
            self::pause_conversation($id);
        }
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-support&conversation_id=' . $id . '&ai=paused'));
        exit;
    }

    public static function handle_resume_ai() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_support_resume_ai')) {
            wp_die('Unauthorized');
        }
        $id = (int) ($_POST['conversation_id'] ?? 0);
        if ($id > 0) {
            self::resume_conversation($id);
        }
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-support&conversation_id=' . $id . '&ai=resumed'));
        exit;
    }
}
