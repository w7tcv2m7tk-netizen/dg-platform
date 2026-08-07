<?php
/**
 * Client portal live support chat (DigitalGate).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Client_Support {

    public static function init() {
        if (!self::enabled()) {
            return;
        }

        add_action('dg_platform_register_rest_routes', [__CLASS__, 'register_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 25);
        add_action('admin_post_dg_support_reply', [__CLASS__, 'handle_admin_reply']);

        if (class_exists('DG_Support_AI')) {
            DG_Support_AI::init();
        }
    }

    public static function enabled() {
        return class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate();
    }

    public static function conversations_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_support_conversations';
    }

    public static function messages_table() {
        global $wpdb;
        return $wpdb->prefix . 'dg_support_messages';
    }

    public static function register_admin_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }
        add_submenu_page(
            'dg-platform',
            'Support Inbox',
            '💬 Support Inbox',
            'manage_options',
            'dg-platform-support',
            [__CLASS__, 'render_admin_inbox']
        );
    }

    public static function register_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/support/conversation', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_conversation'],
            'permission_callback' => [__CLASS__, 'rest_can_chat'],
        ]);
        register_rest_route(DG_REST_NAMESPACE, '/support/messages', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'rest_get_messages'],
                'permission_callback' => [__CLASS__, 'rest_can_chat'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_post_message'],
                'permission_callback' => [__CLASS__, 'rest_can_chat'],
            ],
        ]);

        register_rest_route(DG_REST_NAMESPACE, '/support/platform/conversation', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_platform_get_conversation'],
            'permission_callback' => [__CLASS__, 'rest_platform_can_access'],
        ]);
        register_rest_route(DG_REST_NAMESPACE, '/support/platform/messages', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'rest_platform_get_messages'],
                'permission_callback' => [__CLASS__, 'rest_platform_can_access'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_platform_post_message'],
                'permission_callback' => [__CLASS__, 'rest_platform_can_access'],
            ],
        ]);
    }

    public static function rest_platform_can_access($request) {
        return class_exists('DG_Dev_API') && DG_Dev_API::verify_request($request);
    }

    /** Resolve WP user for Gen 2 platform chat (portal email + API key). */
    public static function resolve_user_id_from_portal_request($request) {
        $email = sanitize_email($request->get_header('X-Portal-Email') ?: $request->get_param('email'));
        if ($email === '' || !class_exists('DG_Client_Portal')) {
            return 0;
        }

        $clerk_user_id = sanitize_text_field($request->get_header('X-Clerk-User-Id') ?: '');
        $profile = DG_Client_Portal::profile_for_portal_email($email, $clerk_user_id);
        if (empty($profile['linked'])) {
            return 0;
        }

        $user = get_user_by('email', $email);
        return $user ? (int) $user->ID : 0;
    }

    public static function rest_platform_get_conversation($request) {
        $user_id = self::resolve_user_id_from_portal_request($request);
        if ($user_id <= 0) {
            return new WP_Error('not_linked', 'Complete onboarding with this email to use live chat.', ['status' => 403]);
        }

        $conversation = self::get_or_create_conversation($user_id);
        if (!$conversation) {
            return new WP_Error('support_unavailable', 'Could not start conversation.', ['status' => 500]);
        }

        return new WP_REST_Response([
            'conversation_id' => (int) $conversation->id,
            'messages' => self::get_messages((int) $conversation->id),
        ], 200);
    }

    public static function rest_platform_get_messages($request) {
        $user_id = self::resolve_user_id_from_portal_request($request);
        if ($user_id <= 0) {
            return new WP_Error('not_linked', 'Complete onboarding with this email to use live chat.', ['status' => 403]);
        }

        $conversation = self::get_or_create_conversation($user_id);
        if (!$conversation) {
            return new WP_Error('support_unavailable', 'Could not load messages.', ['status' => 500]);
        }

        $after = (int) $request->get_param('after');
        return new WP_REST_Response([
            'messages' => self::get_messages((int) $conversation->id, $after),
        ], 200);
    }

    public static function rest_platform_post_message($request) {
        $user_id = self::resolve_user_id_from_portal_request($request);
        if ($user_id <= 0) {
            return new WP_Error('not_linked', 'Complete onboarding with this email to use live chat.', ['status' => 403]);
        }

        $body = sanitize_textarea_field((string) ($request->get_param('message') ?? ''));
        if ($body === '') {
            return new WP_Error('empty_message', 'Message is required.', ['status' => 400]);
        }

        $conversation = self::get_or_create_conversation($user_id);
        if (!$conversation) {
            return new WP_Error('support_unavailable', 'Could not send message.', ['status' => 500]);
        }

        $message_id = self::insert_message((int) $conversation->id, 'client', $user_id, $body);
        if (!$message_id) {
            return new WP_Error('send_failed', 'Could not save message.', ['status' => 500]);
        }

        self::notify_staff_new_message($conversation, $body);
        do_action('dg_support_client_message_created', $conversation, $message_id, $body);

        return new WP_REST_Response([
            'message_id' => $message_id,
            'messages' => self::get_messages((int) $conversation->id),
        ], 201);
    }

    public static function rest_can_chat() {
        return is_user_logged_in() && self::user_can_chat();
    }

    public static function user_can_chat($user = null) {
        if (!is_user_logged_in() && !$user) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        return class_exists('DG_Client_Portal') && DG_Client_Portal::is_client_user($user);
    }

    public static function enqueue_assets() {
        if (!is_user_logged_in() || !self::user_can_chat()) {
            return;
        }
        if (!self::is_portal_context() && !self::is_admin_portal_context()) {
            return;
        }

        wp_enqueue_style(
            'dg-client-support-chat',
            DG_PLATFORM_URL . 'assets/css/client-support-chat.css',
            [],
            DG_PLATFORM_VERSION
        );
        wp_enqueue_script(
            'dg-client-support-chat',
            DG_PLATFORM_URL . 'assets/js/client-support-chat.js',
            [],
            DG_PLATFORM_VERSION,
            true
        );
        wp_enqueue_style(
            'font-awesome-6',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
            [],
            '6.0.0'
        );

        $user = wp_get_current_user();
        wp_localize_script('dg-client-support-chat', 'dgSupportChat', [
            'restUrl' => rest_url(DG_REST_NAMESPACE . '/support/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userName' => $user->display_name ?: $user->user_email,
            'isStaff' => current_user_can('manage_options'),
            'pollMs' => 4000,
        ]);
    }

    public static function is_portal_context() {
        if (!is_page()) {
            return false;
        }
        $post = get_queried_object();
        if (!$post instanceof WP_Post) {
            return false;
        }
        $slugs = ['client-dashboard', 'client-account', 'client-reports', 'customer-account', 'client-portal', 'onboarding'];
        if (in_array($post->post_name, $slugs, true)) {
            return true;
        }
        $parent = (int) $post->post_parent;
        return $parent && get_post_field('post_name', $parent) === 'system-pages';
    }

    public static function is_admin_portal_context() {
        if (!is_admin()) {
            return false;
        }
        if (class_exists('DG_Client_Portal') && method_exists('DG_Client_Portal', 'is_app_admin_context')) {
            return DG_Client_Portal::is_app_admin_context();
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        return $page === '' || strpos($page, 'dg-') === 0;
    }

    public static function rest_get_conversation($request) {
        $user_id = get_current_user_id();
        $conversation = self::get_or_create_conversation($user_id);
        if (!$conversation) {
            return new WP_Error('support_unavailable', 'Could not start conversation.', ['status' => 500]);
        }

        return new WP_REST_Response([
            'conversation_id' => (int) $conversation->id,
            'messages' => self::get_messages((int) $conversation->id),
        ], 200);
    }

    public static function rest_get_messages($request) {
        $user_id = get_current_user_id();
        $conversation = self::get_or_create_conversation($user_id);
        if (!$conversation) {
            return new WP_Error('support_unavailable', 'Could not load messages.', ['status' => 500]);
        }

        $after = (int) $request->get_param('after');
        return new WP_REST_Response([
            'messages' => self::get_messages((int) $conversation->id, $after),
        ], 200);
    }

    public static function rest_post_message($request) {
        $body = sanitize_textarea_field((string) ($request->get_param('message') ?? ''));
        if ($body === '') {
            return new WP_Error('empty_message', 'Message is required.', ['status' => 400]);
        }

        $user_id = get_current_user_id();
        $conversation = self::get_or_create_conversation($user_id);
        if (!$conversation) {
            return new WP_Error('support_unavailable', 'Could not send message.', ['status' => 500]);
        }

        $role = current_user_can('manage_options') ? 'staff' : 'client';
        $message_id = self::insert_message((int) $conversation->id, $role, $user_id, $body);
        if (!$message_id) {
            return new WP_Error('send_failed', 'Could not save message.', ['status' => 500]);
        }

        if ($role === 'client') {
            self::notify_staff_new_message($conversation, $body);
            do_action('dg_support_client_message_created', $conversation, $message_id, $body);
        } else {
            if (class_exists('DG_Support_AI')) {
                DG_Support_AI::pause_conversation((int) $conversation->id);
            }
            self::notify_client_reply($conversation, $body);
        }

        return new WP_REST_Response([
            'message_id' => $message_id,
            'messages' => self::get_messages((int) $conversation->id),
        ], 201);
    }

    /** @return object|null */
    public static function get_or_create_conversation($user_id) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return null;
        }

        $table = self::conversations_table();
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
        if ($existing) {
            return $existing;
        }

        $contact_id = (int) get_user_meta($user_id, 'dg_contact_id', true);
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'contact_id' => $contact_id ?: null,
            'status' => 'open',
            'last_message_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ]);

        $id = (int) $wpdb->insert_id;
        if ($id <= 0) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /** @return array<int,array<string,mixed>> */
    public static function get_messages($conversation_id, $after_id = 0) {
        global $wpdb;
        $conversation_id = (int) $conversation_id;
        $after_id = (int) $after_id;
        $table = self::messages_table();

        if ($after_id > 0) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE conversation_id = %d AND id > %d ORDER BY id ASC LIMIT 100",
                $conversation_id,
                $after_id
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC LIMIT 200",
                $conversation_id
            ));
        }

        $messages = [];
        foreach ($rows as $row) {
            $messages[] = self::format_message($row);
        }
        return $messages;
    }

    /** @return array<string,mixed> */
    private static function format_message($row) {
        $role = (string) $row->sender_role;
        $sender_name = 'Support';
        if ($role === 'client') {
            $user = get_userdata((int) $row->sender_user_id);
            $sender_name = $user ? ($user->display_name ?: $user->user_email) : 'Client';
        } elseif ($role === 'ai') {
            $sender_name = 'DigitalGate Assist';
        }

        return [
            'id' => (int) $row->id,
            'role' => $role,
            'sender' => $sender_name,
            'body' => (string) $row->body,
            'at' => (string) $row->created_at,
        ];
    }

    /** Public wrapper for AI / integrations. */
    public static function insert_message_public($conversation_id, $role, $user_id, $body) {
        return self::insert_message($conversation_id, $role, $user_id, $body);
    }

    private static function insert_message($conversation_id, $role, $user_id, $body) {
        global $wpdb;
        $allowed = ['client', 'staff', 'ai'];
        $role = in_array($role, $allowed, true) ? $role : 'client';
        $wpdb->insert(self::messages_table(), [
            'conversation_id' => (int) $conversation_id,
            'sender_role' => $role,
            'sender_user_id' => (int) $user_id,
            'body' => $body,
            'created_at' => current_time('mysql'),
        ]);
        $id = (int) $wpdb->insert_id;
        if ($id > 0) {
            $wpdb->update(self::conversations_table(), [
                'last_message_at' => current_time('mysql'),
                'status' => 'open',
            ], ['id' => (int) $conversation_id]);
        }
        return $id;
    }

    /** @param object $conversation */
    private static function notify_staff_new_message($conversation, $body) {
        $user = get_userdata((int) $conversation->user_id);
        $to = apply_filters('dg_client_support_admin_email', 'support@digitalgate.com.au');
        $subject = 'Client support message — ' . ($user ? $user->display_name : 'Client');
        $inbox = admin_url('admin.php?page=dg-platform-support&conversation_id=' . (int) $conversation->id);
        $rows = [
            'From' => $user ? $user->display_name : 'Client',
            'Email' => $user ? $user->user_email : '',
            'Message' => $body,
        ];
        if (
            class_exists('DG_Support_AI')
            && DG_Support_AI::enabled()
            && empty($conversation->ai_paused)
        ) {
            $rows['Note'] = 'DigitalGate Assist may send a first-line reply in chat.';
        }

        if (class_exists('DG_Email_Brand')) {
            $html = DG_Email_Brand::admin_notification('Client support message', $rows, [
                'theme' => 'digitalgate',
                'footer_note' => 'Internal notification from DigitalGate Support.',
                'cta_url' => $inbox,
                'cta_label' => 'Open conversation',
            ]);
            wp_mail($to, $subject, $html, DG_Email_Brand::mail_headers(true));
            return;
        }

        $message = "New message in the client portal:\n\n" . $body . "\n\nFrom: "
            . ($user ? $user->user_email : '') . "\n" . $inbox;
        wp_mail($to, $subject, $message, ['Content-Type: text/plain; charset=UTF-8']);
    }

    /** @param object $conversation */
    private static function notify_client_reply($conversation, $body) {
        $user = get_userdata((int) $conversation->user_id);
        if (!$user || !is_email($user->user_email)) {
            return;
        }
        $subject = 'Reply from DigitalGate Support';
        $dashboard = class_exists('DG_Client_Portal')
            ? DG_Client_Portal::dashboard_url()
            : admin_url('admin.php?page=dg-platform');
        $first = DG_Email_Names::first_name($user);

        if (class_exists('DG_Email_Brand')) {
            $inner = '<p style="margin:0 0 14px;line-height:1.65;color:#E2E8F0;">Hi ' . esc_html($first) . ',</p>'
                . '<p style="margin:0 0 14px;line-height:1.65;color:#E2E8F0;">Ben from DigitalGate replied to your support message:</p>'
                . '<div style="margin:0 0 16px;padding:16px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:#E2E8F0;line-height:1.65;">'
                . nl2br(esc_html($body)) . '</div>'
                . (class_exists('DG_Marketing_Emails')
                    ? DG_Marketing_Emails::cta($dashboard, 'Open your dashboard')
                    : DG_Email_Brand::cta($dashboard, 'Open your dashboard', 'digitalgate'))
                . '<p style="margin:16px 0 0;line-height:1.65;color:#94A3B8;">— DigitalGate Support</p>';
            $html = DG_Email_Brand::wrap($inner, [
                'theme' => 'digitalgate',
                'footer_note' => 'DigitalGate Support — reply in your client portal.',
            ]);
            wp_mail($user->user_email, $subject, $html, [
                'Content-Type: text/html; charset=UTF-8',
                'From: DigitalGate Support <support@digitalgate.com.au>',
            ]);
            return;
        }

        $message = 'Hi ' . $first . ",\n\nBen from DigitalGate replied to your support message:\n\n"
            . $body . "\n\nYou can continue the conversation in your platform dashboard:\n"
            . $dashboard . "\n\n— DigitalGate Support";
        wp_mail($user->user_email, $subject, $message, [
            'Content-Type: text/plain; charset=UTF-8',
            'From: DigitalGate Support <support@digitalgate.com.au>',
        ]);
    }

    public static function handle_admin_reply() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_support_reply')) {
            wp_die('Unauthorized');
        }

        $conversation_id = (int) ($_POST['conversation_id'] ?? 0);
        $body = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        if ($conversation_id <= 0 || $body === '') {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-support&error=1'));
            exit;
        }

        global $wpdb;
        $conversation = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::conversations_table() . ' WHERE id = %d',
            $conversation_id
        ));
        if (!$conversation) {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-support&error=1'));
            exit;
        }

        self::insert_message($conversation_id, 'staff', get_current_user_id(), $body);
        if (class_exists('DG_Support_AI')) {
            DG_Support_AI::pause_conversation($conversation_id);
        }
        self::notify_client_reply($conversation, $body);

        wp_safe_redirect(admin_url('admin.php?page=dg-platform-support&conversation_id=' . $conversation_id . '&sent=1'));
        exit;
    }

    /** @return array<int,object> */
    public static function list_conversations($limit = 50) {
        global $wpdb;
        $c = self::conversations_table();
        $m = self::messages_table();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name, u.user_email,
                (SELECT body FROM {$m} WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_body
             FROM {$c} c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id
             ORDER BY c.last_message_at DESC
             LIMIT %d",
            $limit
        ));
    }

    public static function render_admin_inbox() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $conversation_id = (int) ($_GET['conversation_id'] ?? 0);
        $conversations = self::list_conversations();
        $active = null;
        $messages = [];

        if ($conversation_id > 0) {
            global $wpdb;
            $active = $wpdb->get_row($wpdb->prepare(
                'SELECT c.*, u.display_name, u.user_email FROM ' . self::conversations_table() . ' c
                 LEFT JOIN ' . $wpdb->users . ' u ON u.ID = c.user_id WHERE c.id = %d',
                $conversation_id
            ));
            if ($active) {
                $messages = self::get_messages($conversation_id);
            }
        }

        include DG_PLATFORM_PATH . 'templates/admin/support-inbox.php';
    }
}

add_action('plugins_loaded', ['DG_Client_Support', 'init'], 12);
