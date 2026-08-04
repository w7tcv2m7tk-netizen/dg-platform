<?php
/**
 * Central AI AJAX handlers and admin assets.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_AI_Admin {

    public static function init() {
        if (!is_admin()) {
            return;
        }

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_dg_ai_assist', [__CLASS__, 'ajax_assist']);
        add_action('dg_client_onboarding_completed', [__CLASS__, 'on_onboarding_completed'], 30, 4);
        add_action('admin_footer', [__CLASS__, 'render_post_editor_ai']);
    }

    public static function enqueue($hook) {
        if (!self::should_enqueue($hook)) {
            return;
        }

        wp_enqueue_script(
            'dg-ai-assist',
            DG_PLATFORM_URL . 'assets/js/dg-ai-assist.js',
            ['jquery'],
            DG_PLATFORM_VERSION,
            true
        );
        wp_enqueue_script(
            'dg-ai-assist-bindings',
            DG_PLATFORM_URL . 'assets/js/dg-ai-assist-bindings.js',
            ['jquery', 'dg-ai-assist'],
            DG_PLATFORM_VERSION,
            true
        );

        wp_enqueue_style(
            'dg-seo-admin',
            DG_PLATFORM_URL . 'assets/css/seo-admin.css',
            [],
            DG_PLATFORM_VERSION
        );

        wp_localize_script('dg-ai-assist', 'dgAiAssist', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dg_ai_assist'),
            'hasAi' => class_exists('DG_AI_Assist') && DG_AI_Assist::available(),
            'apiSettingsUrl' => admin_url('admin.php?page=dg-platform-api'),
        ]);
    }

    private static function should_enqueue($hook) {
        if (!class_exists('DG_AI_Assist')) {
            return false;
        }

        $screens = [
            'post.php', 'post-new.php',
            'dg-platform_page_dg-platform-seo',
            'dg-platform_page_dg-platform-social-pro',
            'dg-platform_page_dg-platform-ai-visibility',
            'dg-platform_page_dg-platform-automation-pro',
            'dg-platform_page_dg-platform-contacts',
            'toplevel_page_dg-platform',
        ];

        if (in_array($hook, $screens, true)) {
            return true;
        }

        if (strpos($hook, 'dg-platform') !== false) {
            return true;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && in_array($screen->post_type ?? '', ['property', 'dg_accommodation', 'post', 'page'], true)) {
            return in_array($hook, ['post.php', 'post-new.php'], true);
        }

        return false;
    }

    public static function ajax_assist() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'dg_ai_assist')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!class_exists('DG_AI_Assist') || !DG_AI_Assist::available()) {
            wp_send_json_error(['message' => 'Configure OpenAI or Gemini in API Settings first.']);
        }

        $task = isset($_POST['task']) ? sanitize_key($_POST['task']) : '';
        $result = null;

        switch ($task) {
            case 'seo_optimize':
            case 'seo_suburb':
                $post_id = (int) ($_POST['post_id'] ?? 0);
                if (!$post_id || !current_user_can('edit_post', $post_id)) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $result = $task === 'seo_suburb'
                    ? DG_AI_Assist::suburb_page($post_id)
                    : DG_AI_Assist::seo_optimize($post_id);
                break;

            case 'social_compose':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $platforms = isset($_POST['platforms']) ? (array) wp_unslash($_POST['platforms']) : [];
                $result = DG_AI_Assist::social_compose([
                    'topic' => sanitize_text_field(wp_unslash($_POST['topic'] ?? '')),
                    'link_url' => esc_url_raw(wp_unslash($_POST['link_url'] ?? '')),
                    'platforms' => array_map('sanitize_key', $platforms),
                ]);
                break;

            case 'property_description':
                $post_id = (int) ($_POST['post_id'] ?? 0);
                if (!$post_id || !current_user_can('edit_post', $post_id)) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $result = DG_AI_Assist::property_description($post_id);
                break;

            case 'accommodation_description':
                $post_id = (int) ($_POST['post_id'] ?? 0);
                if (!$post_id || !current_user_can('edit_post', $post_id)) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $result = DG_AI_Assist::accommodation_description($post_id);
                break;

            case 'contact_draft':
                $contact_id = (int) ($_POST['contact_id'] ?? 0);
                if (!$contact_id || !current_user_can('dg_manage_contacts')) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $result = DG_AI_Assist::contact_draft(
                    $contact_id,
                    sanitize_key($_POST['channel'] ?? 'email'),
                    sanitize_text_field(wp_unslash($_POST['purpose'] ?? 'follow_up'))
                );
                break;

            case 'visibility_fix':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $result = DG_AI_Assist::visibility_fix(
                    sanitize_text_field(wp_unslash($_POST['recommendation'] ?? '')),
                    [
                        'openai' => (int) ($_POST['openai_score'] ?? 0),
                        'gemini' => (int) ($_POST['gemini_score'] ?? 0),
                        'technical' => (int) ($_POST['technical_score'] ?? 0),
                    ]
                );
                break;

            case 'automation_suggest':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $result = DG_AI_Assist::automation_suggest(
                    sanitize_text_field(wp_unslash($_POST['trigger'] ?? '')),
                    sanitize_text_field(wp_unslash($_POST['goal'] ?? ''))
                );
                break;

            case 'blog_draft':
                $post_id = (int) ($_POST['post_id'] ?? 0);
                if (!$post_id || !current_user_can('edit_post', $post_id)) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $result = DG_AI_Assist::blog_draft($post_id);
                break;

            case 'audit_executive_summary':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $result = DG_AI_Assist::audit_executive_summary((int) ($_POST['audit_id'] ?? 0));
                break;

            case 'reports_narrative':
                if (!current_user_can('manage_options') && !current_user_can('dg_client_portal')) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $context = isset($_POST['context']) ? json_decode(wp_unslash($_POST['context']), true) : [];
                if (!is_array($context)) {
                    $context = [];
                }
                $result = DG_AI_Assist::reports_narrative($context);
                break;

            case 'onboarding_summary':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(['message' => 'Unauthorized']);
                }
                $contact_id = (int) ($_POST['contact_id'] ?? 0);
                $data = isset($_POST['data']) ? json_decode(wp_unslash($_POST['data']), true) : [];
                if (!is_array($data)) {
                    $data = [];
                }
                $result = DG_AI_Assist::onboarding_summary($data);
                if (!is_wp_error($result) && $contact_id) {
                    DG_AI_Assist::persist_onboarding_summary($contact_id, $data, $result);
                }
                break;

            default:
                wp_send_json_error(['message' => 'Unknown AI task.']);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public static function on_onboarding_completed($contact_id, $org_id, $data, $uploads) {
        if (!class_exists('DG_AI_Assist') || !DG_AI_Assist::available()) {
            return;
        }

        $summary = DG_AI_Assist::onboarding_summary(is_array($data) ? $data : []);
        if (!is_wp_error($summary)) {
            DG_AI_Assist::persist_onboarding_summary((int) $contact_id, is_array($data) ? $data : [], $summary);
        }
    }

    public static function render_post_editor_ai() {
        if (!class_exists('DG_AI_Assist') || !DG_AI_Assist::available()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'post' || !in_array($screen->post_type, ['post', 'page'], true)) {
            return;
        }

        global $post;
        if (!$post instanceof WP_Post) {
            return;
        }

        $task = $screen->post_type === 'post' ? 'blog_draft' : 'seo_optimize';
        $label = $screen->post_type === 'post' ? '✨ Draft article with AI' : '✨ Optimise SEO with AI';
        $extra = $screen->post_type === 'post'
            ? 'data-ai-target="#content" data-ai-target-title="title" data-ai-target-excerpt="excerpt"'
            : 'data-ai-modal="1" data-ai-modal-title="SEO suggestions" data-ai-apply-seo="1"';
        ?>
        <script>
        jQuery(function ($) {
            var $box = $('#titlewrap');
            if (!$box.length || $('#dg-post-ai-btn').length) {
                return;
            }
            $box.after(
                '<p id="dg-post-ai-wrap" style="margin:8px 0;">' +
                '<button type="button" id="dg-post-ai-btn" class="button button-secondary dg-ai-btn" ' +
                'data-ai-task="<?php echo esc_js($task); ?>" data-ai-post-id="<?php echo (int) $post->ID; ?>" ' +
                '<?php echo $extra; ?>><?php echo esc_js($label); ?></button> ' +
                '<span class="dg-ai-status"></span> ' +
                '<a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-seo&tab=audit&post_id=' . (int) $post->ID)); ?>">Open in SEO Pro</a></p>'
            );
        });
        </script>
        <?php
    }
}
