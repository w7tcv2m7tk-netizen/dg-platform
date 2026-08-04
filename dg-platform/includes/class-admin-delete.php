<?php
/**
 * Unified delete / trash actions for DG Platform admin.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Admin_Delete {

    /** @var array<string,string> */
    private static $post_types = [
        'property' => 'Property',
        'agent' => 'Agent',
        'dg_accommodation' => 'Accommodation',
        'dg_booking' => 'Booking',
        'dg_guest' => 'Guest',
        'dg_vehicle' => 'Vehicle',
        'dg_commercial' => 'Commercial listing',
    ];

    public static function init() {
        add_action('admin_post_dg_delete_contact', [__CLASS__, 'handle_delete_contact']);
        add_action('admin_post_dg_delete_task', [__CLASS__, 'handle_delete_task']);
        add_action('admin_post_dg_delete_calendar_event', [__CLASS__, 'handle_delete_calendar_event']);
        add_action('admin_post_dg_delete_automation', [__CLASS__, 'handle_delete_automation']);
        add_action('admin_post_dg_trash_post', [__CLASS__, 'handle_trash_post']);
        add_action('admin_post_dg_delete_document', [__CLASS__, 'handle_delete_document']);

        add_filter('post_row_actions', [__CLASS__, 'post_row_actions'], 20, 2);
        add_action('admin_notices', [__CLASS__, 'deleted_notice']);
    }

    /** @return array<string,string> */
    public static function post_types() {
        return apply_filters('dg_platform_deletable_post_types', self::$post_types);
    }

    public static function is_dg_post_type($post_type) {
        return isset(self::post_types()[$post_type]);
    }

    /**
     * @param string $action admin_post action
     * @param int    $id
     * @param string $label
     */
    public static function link($action, $id, $label = 'Delete') {
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=' . $action . '&id=' . (int) $id),
            $action
        );

        return '<a href="' . esc_url($url) . '" class="submitdelete dg-delete-link" onclick="return confirm('
            . esc_js('Delete this item permanently? This cannot be undone.')
            . ');">' . esc_html($label) . '</a>';
    }

    public static function trash_post_link($post_id, $label = 'Trash') {
        $post_id = (int) $post_id;
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=dg_trash_post&id=' . $post_id . '&redirect_to=' . rawurlencode(self::current_list_url())),
            'dg_trash_post_' . $post_id
        );

        return '<a href="' . esc_url($url) . '" class="submitdelete dg-trash-link" onclick="return confirm('
            . esc_js('Move this item to trash?')
            . ');">' . esc_html($label) . '</a>';
    }

    public static function delete_post_link($post_id, $label = 'Delete permanently') {
        $post_id = (int) $post_id;
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=dg_delete_post&id=' . $post_id . '&redirect_to=' . rawurlencode(self::current_list_url())),
            'dg_delete_post_' . $post_id
        );

        return '<a href="' . esc_url($url) . '" class="submitdelete dg-delete-link" onclick="return confirm('
            . esc_js('Delete permanently? This cannot be undone.')
            . ');">' . esc_html($label) . '</a>';
    }

    public static function post_row_actions($actions, $post) {
        if (!$post instanceof WP_Post || !self::is_dg_post_type($post->post_type)) {
            return $actions;
        }

        if (!current_user_can('delete_post', $post->ID)) {
            return $actions;
        }

        if ($post->post_status === 'trash') {
            unset($actions['untrash'], $actions['delete']);
            $actions['dg_delete_permanent'] = self::delete_post_link($post->ID);
        } else {
            unset($actions['trash']);
            $actions['dg_trash'] = self::trash_post_link($post->ID);
        }

        return $actions;
    }

    public static function deleted_notice() {
        if (empty($_GET['dg_deleted'])) {
            return;
        }

        $type = sanitize_key(wp_unslash($_GET['dg_deleted_type'] ?? 'item'));
        if ($type === 'trashed') {
            echo '<div class="notice notice-success is-dismissible"><p>Item moved to trash.</p></div>';
            return;
        }

        $labels = array_merge([
            'contact' => 'Contact',
            'task' => 'Task',
            'calendar_event' => 'Calendar event',
            'automation' => 'Automation',
            'document' => 'Document',
        ], self::post_types());

        $label = $labels[$type] ?? 'Item';
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html($label . ' deleted.')
            . '</p></div>';
    }

    public static function handle_delete_contact() {
        self::authorize('dg_delete_contact', 'dg_manage_contacts');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !DG_Contacts::get($id)) {
            wp_die('Contact not found.');
        }

        DG_Contacts::delete($id);
        self::redirect('dg-platform-contacts', 'contact');
    }

    public static function handle_delete_task() {
        self::authorize('dg_delete_task', 'dg_manage_tasks');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !DG_Tasks::get($id)) {
            wp_die('Task not found.');
        }

        DG_Tasks::delete($id);
        self::redirect('dg-platform-tasks', 'task');
    }

    public static function handle_delete_calendar_event() {
        self::authorize('dg_delete_calendar_event', 'dg_manage_calendar');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !DG_Calendar::get($id)) {
            wp_die('Event not found.');
        }

        DG_Calendar::delete($id);
        self::redirect('dg-platform-calendar', 'calendar_event');
    }

    public static function handle_delete_automation() {
        self::authorize('dg_delete_automation', 'dg_manage_automations');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !DG_Automation::get($id)) {
            wp_die('Automation not found.');
        }

        DG_Automation::delete($id);
        self::redirect('dg-platform-automations', 'automation');
    }

    public static function handle_delete_document() {
        self::authorize('dg_delete_document', 'dg_manage_platform');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !DG_Documents::get($id)) {
            wp_die('Document not found.');
        }

        DG_Documents::delete($id);
        self::redirect('dg-platform-documents', 'document');
    }

    public static function handle_trash_post() {
        $id = (int) ($_GET['id'] ?? 0);
        $post = get_post($id);

        if (!$post || !self::is_dg_post_type($post->post_type)) {
            wp_die('Invalid item.');
        }

        if (!current_user_can('delete_post', $id)) {
            wp_die('Unauthorized');
        }

        check_admin_referer('dg_trash_post_' . $id);

        wp_trash_post($id);
        self::redirect_post_list($post->post_type, 'trashed');
    }

    public static function handle_delete_post() {
        $id = (int) ($_GET['id'] ?? 0);
        $post = get_post($id);

        if (!$post || !self::is_dg_post_type($post->post_type)) {
            wp_die('Invalid item.');
        }

        if (!current_user_can('delete_post', $id)) {
            wp_die('Unauthorized');
        }

        check_admin_referer('dg_delete_post_' . $id);

        wp_delete_post($id, true);
        self::redirect_post_list($post->post_type, $post->post_type);
    }

    private static function authorize($nonce_action, $capability) {
        if (!DG_Permissions::current_user_can($capability)) {
            wp_die('Unauthorized');
        }
        check_admin_referer($nonce_action);
    }

    private static function redirect($page, $type) {
        wp_safe_redirect(add_query_arg([
            'page' => $page,
            'dg_deleted' => 1,
            'dg_deleted_type' => $type,
        ], admin_url('admin.php')));
        exit;
    }

    private static function redirect_post_list($post_type, $type) {
        $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        if ($redirect === '') {
            $redirect = admin_url('edit.php?post_type=' . $post_type);
        }

        wp_safe_redirect(add_query_arg([
            'dg_deleted' => 1,
            'dg_deleted_type' => $type,
        ], $redirect));
        exit;
    }

    private static function current_list_url() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($uri === '') {
            return admin_url();
        }

        return home_url($uri);
    }
}

DG_Admin_Delete::init();
