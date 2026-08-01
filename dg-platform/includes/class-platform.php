<?php
/**
 * Main platform singleton.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Platform {

    private static $instance = null;
    /** @var DG_Module_Registry */
    private $registry;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->registry = new DG_Module_Registry();

        add_action('init', [$this, 'init'], 5);
        add_action('admin_menu', [$this, 'admin_menu'], 10);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_dg_save_modules', [$this, 'handle_save_modules']);
        add_action('admin_post_dg_save_api_settings', [$this, 'handle_save_api_settings']);
        add_action('admin_post_dg_save_contact', [$this, 'handle_save_contact']);
        add_action('admin_post_dg_save_task', [$this, 'handle_save_task']);
        add_action('admin_post_dg_save_calendar_event', [$this, 'handle_save_calendar_event']);
        add_action('admin_post_dg_complete_task', [$this, 'handle_complete_task']);
        add_action('admin_post_dg_toggle_automation', [$this, 'handle_toggle_automation']);
        add_action('admin_post_dg_save_automation', [$this, 'handle_save_automation']);
        add_action('admin_post_dg_save_custom_fields', [$this, 'handle_save_custom_fields']);
    }

    public function init() {
        DG_Automation::schedule_cron();
        $this->registry->load_active_modules($this);
    }

    public function get_registry() {
        return $this->registry;
    }

    public function register_module($key, $instance) {
        $this->registry->register_instance($key, $instance);
    }

    public function register_module_definition($key, $definition) {
        $this->registry->register_definition($key, $definition);
    }

    public function get_module($key) {
        return $this->registry->get_instance($key);
    }

    public function get_module_definitions() {
        return $this->registry->get_definitions();
    }

    public static function get_api_key($service) {
        return DG_Integrations::get_api_key($service);
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'dg-platform') === false && strpos($hook, 'dg-') === false) {
            return;
        }
        wp_enqueue_style('dg-platform-admin', DG_PLATFORM_URL . 'assets/css/admin.css', [], DG_PLATFORM_VERSION);
        wp_enqueue_style('dg-re-admin', DG_PLATFORM_URL . 'assets/css/re-admin.css', ['dg-platform-admin'], DG_PLATFORM_VERSION);
    }

    public function admin_menu() {
        $cap = DG_Permissions::menu_cap();

        add_menu_page(
            'DG Platform',
            'DG Platform',
            $cap,
            'dg-platform',
            [$this, 'render_dashboard'],
            'dashicons-admin-generic',
            30
        );

        add_submenu_page('dg-platform', 'Dashboard', '📊 Dashboard', $cap, 'dg-platform', [$this, 'render_dashboard']);

        if (DG_Permissions::current_user_can('dg_view_contacts')) {
            add_submenu_page('dg-platform', 'Contacts', '📇 Contacts', 'dg_view_contacts', 'dg-platform-contacts', [$this, 'render_contacts']);
        }
        if (DG_Permissions::current_user_can('dg_view_tasks')) {
            add_submenu_page('dg-platform', 'Tasks', '✅ Tasks', 'dg_view_tasks', 'dg-platform-tasks', [$this, 'render_tasks']);
        }
        if (DG_Permissions::current_user_can('dg_view_calendar')) {
            add_submenu_page('dg-platform', 'Calendar', '📅 Calendar', 'dg_view_calendar', 'dg-platform-calendar', [$this, 'render_calendar']);
        }
        if (DG_Permissions::current_user_can('dg_view_activities')) {
            add_submenu_page('dg-platform', 'Search', '🔍 Search', 'dg_view_contacts', 'dg-platform-search', [$this, 'render_search']);
            add_submenu_page('dg-platform', 'Activity', '🕐 Activity', 'dg_view_activities', 'dg-platform-activity', [$this, 'render_activity']);
        }
        if (DG_Permissions::current_user_can('dg_manage_modules')) {
            add_submenu_page('dg-platform', 'Automations', '⚡ Automations', 'dg_manage_modules', 'dg-platform-automations', [$this, 'render_automations']);
        }
        if (DG_Permissions::current_user_can('dg_manage_contacts')) {
            add_submenu_page('dg-platform', 'Custom Fields', '🏷️ Custom Fields', 'dg_manage_contacts', 'dg-platform-custom-fields', [$this, 'render_custom_fields']);
        }
        if (DG_Permissions::current_user_can('dg_view_reports')) {
            add_submenu_page('dg-platform', 'Reports', '📈 Reports', 'dg_view_reports', 'dg-platform-reports', [$this, 'render_reports']);
        }
        if (DG_Permissions::current_user_can('dg_manage_modules')) {
            add_submenu_page('dg-platform', 'Modules', '🧩 Modules', 'dg_manage_modules', 'dg-platform-modules', [$this, 'render_modules']);
        }
        if (DG_Permissions::current_user_can('dg_manage_roles')) {
            add_submenu_page('dg-platform', 'Roles', '👥 Roles', 'dg_manage_roles', 'dg-platform-roles', [$this, 'render_roles']);
        }
        if (DG_Permissions::current_user_can('dg_manage_api_keys')) {
            add_submenu_page('dg-platform', 'API Settings', '🔑 API Settings', 'dg_manage_api_keys', 'dg-platform-api', [$this, 'render_api_settings']);
        }

        do_action('dg_platform_register_menus');
    }

    public function render_dashboard() {
        $stats = DG_Reports::get_dashboard_stats();
        $widgets = DG_Reports::get_module_widgets();
        $recent = DG_Activities::recent(10);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>📊 DigitalGate Business Platform</h1>
            <div class="dg-stats-grid">
                <?php foreach ($widgets as $widget) : ?>
                    <div class="dg-stat-card" style="border-left-color:<?php echo esc_attr($widget['color']); ?>">
                        <div class="dg-stat-value"><?php echo number_format($widget['value']); ?></div>
                        <div class="dg-stat-label"><?php echo esc_html($widget['label']); ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="dg-stat-card" style="border-left-color:#C9A46C">
                    <div class="dg-stat-value"><?php echo number_format($stats['organisations']); ?></div>
                    <div class="dg-stat-label">Organisations</div>
                </div>
            </div>

            <div class="dg-panel">
                <h3>🚀 Quick Actions</h3>
                <div class="dg-actions">
                    <?php if (DG_Permissions::current_user_can('dg_view_contacts')) : ?>
                        <a href="<?php echo admin_url('admin.php?page=dg-platform-search'); ?>" class="button">🔍 Search</a>
                    <?php endif; ?>
                    <?php if (DG_Permissions::current_user_can('dg_manage_contacts')) : ?>
                        <a href="<?php echo admin_url('admin.php?page=dg-platform-contacts&action=add'); ?>" class="button button-primary">➕ Add Contact</a>
                    <?php endif; ?>
                    <?php if (DG_Permissions::current_user_can('dg_manage_tasks')) : ?>
                        <a href="<?php echo admin_url('admin.php?page=dg-platform-tasks&action=add'); ?>" class="button">✅ Add Task</a>
                    <?php endif; ?>
                    <?php do_action('dg_platform_quick_actions'); ?>
                </div>
            </div>

            <div class="dg-panel">
                <h3>🧩 Active Modules</h3>
                <div class="dg-tags">
                    <?php foreach ($this->registry->get_active_modules() as $module_key) :
                        $def = $this->registry->get_definition($module_key);
                        if (!$def) continue;
                        $class = !empty($def['is_core']) ? 'dg-tag-core' : 'dg-tag-module';
                        ?>
                        <span class="dg-tag <?php echo esc_attr($class); ?>"><?php echo esc_html(($def['icon'] ?? '') . ' ' . $def['name']); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($recent) : ?>
            <div class="dg-panel">
                <h3>🕐 Recent Activity</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Type</th><th>Subject</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $activity) : ?>
                            <tr>
                                <td><?php echo esc_html($activity->activity_type); ?></td>
                                <td><?php echo esc_html($activity->subject); ?></td>
                                <td><?php echo esc_html($activity->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_contacts() {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        if ($action === 'add' || ($action === 'edit' && !empty($_GET['id']))) {
            $this->render_contact_form($action === 'edit' ? (int) $_GET['id'] : 0);
            return;
        }
        $contacts = DG_Contacts::list(['search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : null]);
        include DG_PLATFORM_PATH . 'templates/admin/contacts-list.php';
    }

    private function render_contact_form($id = 0) {
        $contact = $id ? DG_Contacts::get($id) : null;
        $custom_fields = DG_Entity_Meta::get_definitions('contact');
        $custom_values = $id ? DG_Entity_Meta::get('contact', $id) : [];
        include DG_PLATFORM_PATH . 'templates/admin/contact-form.php';
    }

    public function render_tasks() {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        if ($action === 'add') {
            include DG_PLATFORM_PATH . 'templates/admin/task-form.php';
            return;
        }
        $tasks = DG_Tasks::list(['status' => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : null]);
        include DG_PLATFORM_PATH . 'templates/admin/tasks-list.php';
    }

    public function render_calendar() {
        $events = DG_Calendar::list(['start' => date('Y-m-d')]);
        include DG_PLATFORM_PATH . 'templates/admin/calendar.php';
    }

    public function render_activity() {
        $activities = DG_Activities::recent(100);
        include DG_PLATFORM_PATH . 'templates/admin/activity.php';
    }

    public function render_reports() {
        $stats = DG_Reports::get_dashboard_stats();
        $integrations = DG_Integrations::get_integration_status();
        include DG_PLATFORM_PATH . 'templates/admin/reports.php';
    }

    public function render_modules() {
        $active_modules = $this->registry->get_active_modules();
        include DG_PLATFORM_PATH . 'templates/admin/modules.php';
    }

    public function render_roles() {
        $templates = DG_Permissions::get_role_templates();
        include DG_PLATFORM_PATH . 'templates/admin/roles.php';
    }

    public function render_api_settings() {
        if (isset($_POST['save_api_settings']) && check_admin_referer('dg_api_settings')) {
            $this->handle_save_api_settings();
        }
        include DG_PLATFORM_PATH . 'templates/admin/api-settings.php';
    }

    public function render_search() {
        include DG_PLATFORM_PATH . 'templates/admin/search.php';
    }

    public function render_automations() {
        include DG_PLATFORM_PATH . 'templates/admin/automations.php';
    }

    public function render_custom_fields() {
        include DG_PLATFORM_PATH . 'templates/admin/custom-fields.php';
    }

    public function handle_toggle_automation() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_toggle_automation') || !DG_Permissions::current_user_can('dg_manage_modules')) {
            wp_die('Unauthorized');
        }
        global $wpdb;
        $id = (int) ($_POST['automation_id'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $wpdb->update(DG_Automation::table(), ['is_active' => $active], ['id' => $id]);
        wp_redirect(admin_url('admin.php?page=dg-platform-automations&saved=1'));
        exit;
    }

    public function handle_save_automation() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_save_automation') || !DG_Permissions::current_user_can('dg_manage_modules')) {
            wp_die('Unauthorized');
        }
        DG_Automation::create([
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'module' => sanitize_text_field(wp_unslash($_POST['module'] ?? 'core')),
            'trigger_type' => sanitize_text_field(wp_unslash($_POST['trigger_type'] ?? '')),
            'is_active' => !empty($_POST['is_active']),
            'steps' => [],
        ]);
        wp_redirect(admin_url('admin.php?page=dg-platform-automations&saved=1'));
        exit;
    }

    public function handle_save_custom_fields() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_save_custom_fields') || !DG_Permissions::current_user_can('dg_manage_contacts')) {
            wp_die('Unauthorized');
        }
        $entity_type = sanitize_text_field(wp_unslash($_POST['entity_type'] ?? 'contact'));
        $raw = isset($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        $fields = [];
        foreach ($raw as $field) {
            if (empty($field['key']) || empty($field['label'])) {
                continue;
            }
            $fields[] = [
                'key' => sanitize_key($field['key']),
                'label' => sanitize_text_field($field['label']),
                'type' => sanitize_text_field($field['type'] ?? 'text'),
            ];
        }
        DG_Entity_Meta::save_definitions($entity_type, $fields);
        wp_redirect(admin_url('admin.php?page=dg-platform-custom-fields&saved=1'));
        exit;
    }

    public function handle_save_modules() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_save_modules') || !DG_Permissions::current_user_can('dg_manage_modules')) {
            wp_die('Unauthorized');
        }
        $modules = isset($_POST['modules']) ? array_map('sanitize_text_field', wp_unslash($_POST['modules'])) : [];
        $modules[] = 'core';
        update_option('dg_platform_active_modules', array_unique($modules));
        DG_Permissions::log_audit('modules_updated', 'platform', null, null, $modules);
        wp_redirect(admin_url('admin.php?page=dg-platform-modules&saved=1'));
        exit;
    }

    public function handle_save_api_settings() {
        if (!check_admin_referer('dg_api_settings') || !DG_Permissions::current_user_can('dg_manage_api_keys')) {
            wp_die('Unauthorized');
        }
        $keys = ['pagespeed', 'openai', 'gemini', 'twilio_sid', 'twilio_token', 'twilio_from', 'rankmath', 'gsc', 'gbp', 'fluentcrm', 'stripe_secret'];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                DG_Integrations::save_api_key($key, sanitize_text_field(wp_unslash($_POST[$key])));
            }
        }
        wp_redirect(admin_url('admin.php?page=dg-platform-api&saved=1'));
        exit;
    }

    public function handle_save_contact() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_save_contact') || !DG_Permissions::current_user_can('dg_manage_contacts')) {
            wp_die('Unauthorized');
        }
        $data = [
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'source' => sanitize_text_field(wp_unslash($_POST['source'] ?? 'website')),
            'status' => sanitize_text_field(wp_unslash($_POST['status'] ?? 'active')),
            'notes' => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
        ];
        $id = !empty($_POST['contact_id']) ? (int) $_POST['contact_id'] : 0;
        if ($id) {
            DG_Contacts::update($id, $data);
        } else {
            $created = DG_Contacts::create($data);
            if (is_wp_error($created)) {
                wp_die(esc_html($created->get_error_message()));
            }
            $id = (int) $created;
        }
        if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            $definitions = DG_Entity_Meta::get_definitions('contact');
            $textarea_keys = [];
            foreach ($definitions as $def) {
                if (($def['type'] ?? '') === 'textarea') {
                    $textarea_keys[] = $def['key'] ?? '';
                }
            }
            foreach ($_POST['custom_fields'] as $key => $value) {
                $key = sanitize_key($key);
                $value = in_array($key, $textarea_keys, true)
                    ? sanitize_textarea_field(wp_unslash($value))
                    : sanitize_text_field(wp_unslash($value));
                DG_Entity_Meta::set('contact', $id, $key, $value);
            }
        }
        wp_redirect(admin_url('admin.php?page=dg-platform-contacts&saved=1&id=' . $id));
        exit;
    }

    public function handle_save_task() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_save_task') || !DG_Permissions::current_user_can('dg_manage_tasks')) {
            wp_die('Unauthorized');
        }
        DG_Tasks::create([
            'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'assigned_to' => !empty($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null,
            'priority' => sanitize_text_field(wp_unslash($_POST['priority'] ?? 'normal')),
            'due_date' => sanitize_text_field(wp_unslash($_POST['due_date'] ?? '')),
            'contact_id' => !empty($_POST['contact_id']) ? (int) $_POST['contact_id'] : null,
        ]);
        wp_redirect(admin_url('admin.php?page=dg-platform-tasks&saved=1'));
        exit;
    }

    public function handle_save_calendar_event() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_save_calendar_event') || !DG_Permissions::current_user_can('dg_manage_calendar')) {
            wp_die('Unauthorized');
        }
        DG_Calendar::create([
            'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'event_type' => sanitize_text_field(wp_unslash($_POST['event_type'] ?? 'meeting')),
            'start_at' => sanitize_text_field(wp_unslash($_POST['start_at'] ?? '')),
            'end_at' => sanitize_text_field(wp_unslash($_POST['end_at'] ?? '')),
            'contact_id' => !empty($_POST['contact_id']) ? (int) $_POST['contact_id'] : null,
            'location' => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
        ]);
        wp_redirect(admin_url('admin.php?page=dg-platform-calendar&saved=1'));
        exit;
    }

    public function handle_complete_task() {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'dg_complete_task') || !DG_Permissions::current_user_can('dg_manage_tasks')) {
            wp_die('Unauthorized');
        }
        DG_Tasks::complete((int) $_GET['id']);
        wp_redirect(admin_url('admin.php?page=dg-platform-tasks&completed=1'));
        exit;
    }
}
