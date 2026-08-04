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
        add_action('admin_post_dg_save_plan', [$this, 'handle_save_plan']);
        add_action('admin_post_dg_save_api_settings', [$this, 'handle_save_api_settings']);
        add_action('admin_post_dg_save_contact', [$this, 'handle_save_contact']);
        add_action('admin_post_dg_import_contacts_vcard', [$this, 'handle_import_contacts_vcard']);
        add_action('admin_post_dg_save_task', [$this, 'handle_save_task']);
        add_action('admin_post_dg_save_calendar_event', [$this, 'handle_save_calendar_event']);
        add_action('admin_post_dg_save_document', [$this, 'handle_save_document']);
        add_action('wp_ajax_dg_test_integration', [$this, 'ajax_test_integration']);
        add_action('admin_post_dg_complete_task', [$this, 'handle_complete_task']);
        add_action('admin_post_dg_toggle_automation', [$this, 'handle_toggle_automation']);
        add_action('admin_post_dg_save_automation', [$this, 'handle_save_automation']);
        add_action('admin_post_dg_save_custom_fields', [$this, 'handle_save_custom_fields']);
        add_action('admin_notices', [$this, 'module_refresh_notice']);
    }

    public function module_refresh_notice() {
        if (!get_option('dg_platform_show_module_refresh_notice') || !current_user_can('manage_options')) {
            return;
        }
        delete_option('dg_platform_show_module_refresh_notice');
        $label = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'site module';
        echo '<div class="notice notice-success is-dismissible"><p><strong>DG Platform:</strong> '
            . esc_html($label) . ' module is now active. If menus are missing, refresh this page once.</p></div>';
    }

    public function init() {
        DG_Automation::schedule_cron();
        if (class_exists('DG_Admin_Dark_Mode')) {
            DG_Admin_Dark_Mode::get_instance();
        }
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
        if ($this->registry->is_active('real-estate')) {
            wp_enqueue_style('dg-re-admin', DG_PLATFORM_URL . 'assets/css/re-admin.css', ['dg-platform-admin'], DG_PLATFORM_VERSION);
        }
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
        if (DG_Permissions::current_user_can('dg_view_calendar') && !self::accommodation_owns_calendar()) {
            add_submenu_page('dg-platform', 'Calendar', '📅 Calendar', 'dg_view_calendar', 'dg-platform-calendar', [$this, 'render_calendar']);
        }
        if (DG_Permissions::current_user_can('dg_view_activities')) {
            add_submenu_page('dg-platform', 'Search', '🔍 Search', 'dg_view_contacts', 'dg-platform-search', [$this, 'render_search']);
            add_submenu_page('dg-platform', 'Activity', '🕐 Activity', 'dg_view_activities', 'dg-platform-activity', [$this, 'render_activity']);
        }
        if (DG_Permissions::current_user_can('dg_manage_modules') && DG_Plan_Registry::has_feature('automation')) {
            add_submenu_page('dg-platform', 'Automations', '⚡ Core Automations', 'dg_manage_modules', 'dg-platform-automations', [$this, 'render_automations']);
        }
        if (DG_Permissions::current_user_can('dg_manage_contacts')) {
            add_submenu_page('dg-platform', 'Custom Fields', '🏷️ Custom Fields', 'dg_manage_contacts', 'dg-platform-custom-fields', [$this, 'render_custom_fields']);
        }
        if (DG_Permissions::current_user_can('dg_view_reports') && DG_Plan_Registry::has_feature('reports')) {
            add_submenu_page('dg-platform', 'Reports', '📈 Growth Intelligence', 'dg_view_reports', 'dg-platform-reports', [$this, 'render_reports']);
        }
        if (DG_Permissions::current_user_can('dg_manage_platform')) {
            add_submenu_page('dg-platform', 'Documents', '📎 Documents', 'dg_manage_platform', 'dg-platform-documents', [$this, 'render_documents']);
        }
        if (DG_Permissions::current_user_can('dg_manage_modules')) {
            add_submenu_page('dg-platform', 'Modules & Plan', '🧩 Modules & Plan', 'dg_manage_modules', 'dg-platform-modules', [$this, 'render_modules']);
        }
        if (DG_Permissions::current_user_can('dg_manage_roles')) {
            add_submenu_page('dg-platform', 'Roles', '👥 Roles', 'dg_manage_roles', 'dg-platform-roles', [$this, 'render_roles']);
        }
        if (DG_Permissions::current_user_can('dg_manage_platform') && DG_Plan_Registry::has_feature('audit_log')) {
            add_submenu_page('dg-platform', 'Audit Log', '📋 Audit Log', 'dg_manage_platform', 'dg-platform-audit-log', [$this, 'render_audit_log']);
        }
        if (DG_Permissions::current_user_can('dg_manage_api_keys') && DG_Plan_Registry::has_feature('api')) {
            add_submenu_page('dg-platform', 'API Settings', '🔑 API Settings', 'dg_manage_api_keys', 'dg-platform-api', [$this, 'render_api_settings']);
        }

        do_action('dg_platform_register_menus');
    }

    public function render_dashboard() {
        self::admin_debug_log('render_dashboard start', DG_PLATFORM_VERSION);

        if (!current_user_can(DG_Permissions::menu_cap())) {
            wp_die(__('Sorry, you are not allowed to access this page.'));
        }

        try {
            $apps = self::collect_launcher_apps();
            self::admin_debug_log('render_dashboard apps', count($apps));
            $this->render_dashboard_inner($apps);
            self::admin_debug_log('render_dashboard done');
        } catch (Throwable $e) {
            self::admin_debug_log('render_dashboard FAIL', $e->getMessage(), $e->getFile() . ':' . $e->getLine());
            echo '<div class="wrap dg-platform-wrap"><h1>🧩 DG Platform</h1>';
            echo '<div class="notice notice-error"><p><strong>Dashboard error:</strong> '
                . esc_html($e->getMessage()) . '</p>';
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=dg-acc-dashboard')) . '">Open CVH Dashboard</a> ';
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=dg-platform-modules')) . '">Modules &amp; Plan</a></p></div></div>';
        }
    }

    /** @param array<int,array{title:string,url:string,group?:string}> $apps */
    private function render_dashboard_inner($apps) {

        $stats = ['organisations' => 0, 'contacts' => 0];
        $recent = [];
        try {
            $stats = DG_Reports::get_dashboard_stats();
            $recent = DG_Activities::recent(5);
        } catch (Throwable $e) {
            echo '<div class="notice notice-error"><p><strong>DG Platform:</strong> '
                . esc_html($e->getMessage()) . '</p></div>';
        }

        $group_labels = [
            'core' => 'Core',
            'industry' => 'Industry',
            'premium' => 'Premium',
            'addons' => 'Add-ons',
            'platform' => 'Platform',
        ];
        $grouped = [];
        foreach ($apps as $app) {
            $group = $app['group'] ?? 'core';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $app;
        }
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>🧩 DG Platform</h1>
            <p class="description">All apps and tools for <?php echo esc_html(get_bloginfo('name')); ?>. <span class="dg-muted-subtle">v<?php echo esc_html(DG_PLATFORM_VERSION); ?></span></p>

            <?php if (current_user_can('manage_options') && class_exists('DG_Onboarding')) :
                $beta = null;
                try {
                    $beta = DG_Onboarding::cached_summary(false);
                } catch (Throwable $e) {
                    $beta = null;
                }
                if ($beta && (int) ($beta['percent'] ?? 100) < 100) : ?>
                <div class="notice notice-info">
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-onboarding')); ?>" class="button button-primary">🚀 Complete Beta Setup (<?php echo (int) $beta['percent']; ?>%)</a></p>
                </div>
            <?php endif; endif; ?>

            <?php if (current_user_can('manage_options') && class_exists('DG_Integrations')) :
                $integration_rows = DG_Integrations::get_hub_rows();
                include DG_PLATFORM_PATH . 'templates/admin/integrations-panel.php';
            endif; ?>

            <?php if ($apps) : ?>
                <?php foreach ($group_labels as $group_key => $group_label) :
                    if (empty($grouped[$group_key])) {
                        continue;
                    }
                    ?>
                    <h2 class="dg-launcher-group-title"><?php echo esc_html($group_label); ?></h2>
                    <div class="dg-app-launcher">
                        <?php foreach ($grouped[$group_key] as $app) : ?>
                            <a href="<?php echo esc_url($app['url']); ?>" class="dg-app-card">
                                <span class="dg-app-card-title"><?php echo esc_html($app['title']); ?></span>
                                <span class="dg-app-card-arrow">→</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="dg-panel">
                    <p>No apps available for your role. Try <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-modules')); ?>">Modules &amp; Plan</a> or refresh this page.</p>
                </div>
            <?php endif; ?>

            <details class="dg-panel dg-dashboard-details" style="margin-top:24px;">
                <summary style="cursor:pointer;font-weight:600;">📊 Stats &amp; recent activity</summary>
                <div class="dg-stats-grid" style="margin-top:16px;">
                    <div class="dg-stat-card" style="border-left-color:#1565C0">
                        <div class="dg-stat-value"><?php echo number_format((int) ($stats['contacts'] ?? 0)); ?></div>
                        <div class="dg-stat-label">Contacts</div>
                    </div>
                    <div class="dg-stat-card" style="border-left-color:#C9A46C">
                        <div class="dg-stat-value"><?php echo number_format((int) ($stats['organisations'] ?? 0)); ?></div>
                        <div class="dg-stat-label">Organisations</div>
                    </div>
                </div>
                <?php if ($recent) : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
                    <thead><tr><th>Type</th><th>Subject</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $activity) : ?>
                            <tr>
                                <td><?php echo esc_html($activity->activity_type ?? ''); ?></td>
                                <td><?php echo esc_html($activity->subject ?? ''); ?></td>
                                <td><?php echo esc_html($activity->created_at ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </details>
        </div>
        <?php
    }

    /** @return array<int,array{title:string,url:string,group?:string}> */
    private static function collect_launcher_apps() {
        if (class_exists('DG_Admin_Menu') && method_exists('DG_Admin_Menu', 'launcher_apps')) {
            try {
                return DG_Admin_Menu::launcher_apps();
            } catch (Throwable $e) {
                return self::collect_launcher_apps_fallback();
            }
        }

        return self::collect_launcher_apps_fallback();
    }

    /** @return array<int,array{title:string,url:string,group?:string}> */
    private static function collect_launcher_apps_fallback() {
        global $submenu;
        $apps = [];

        if (empty($submenu['dg-platform']) || !is_array($submenu['dg-platform'])) {
            return $apps;
        }

        foreach ($submenu['dg-platform'] as $item) {
            if (!is_array($item) || empty($item[2]) || $item[2] === 'dg-platform') {
                continue;
            }
            if (strpos((string) $item[2], 'dg-sep-') === 0) {
                continue;
            }
            $cap = isset($item[1]) ? (string) $item[1] : 'read';
            if ($cap !== '' && !current_user_can($cap)) {
                continue;
            }
            $slug = (string) $item[2];
            $apps[] = [
                'title' => wp_strip_all_tags((string) ($item[0] ?? 'App')),
                'url' => (strpos($slug, '.php') !== false || strpos($slug, '?') !== false)
                    ? admin_url($slug)
                    : admin_url('admin.php?page=' . $slug),
                'group' => 'core',
            ];
        }

        return $apps;
    }

    /** @param mixed ...$parts */
    private static function admin_debug_log(...$parts) {
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }

        $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ';
        foreach ($parts as $part) {
            if (is_string($part) || is_numeric($part)) {
                $line .= $part;
            } else {
                $line .= wp_json_encode($part, JSON_UNESCAPED_SLASHES);
            }
            $line .= ' ';
        }

        @file_put_contents(WP_CONTENT_DIR . '/dg-admin-debug.log', rtrim($line) . "\n", FILE_APPEND | LOCK_EX);
    }

    public function render_contacts() {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        if ($action === 'import') {
            if (!DG_Permissions::current_user_can('dg_manage_contacts')) {
                wp_die(__('Sorry, you are not allowed to import contacts.'));
            }
            include DG_PLATFORM_PATH . 'templates/admin/contacts-import.php';
            return;
        }
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
        $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $edit_event = $edit_id > 0 ? DG_Calendar::get($edit_id) : null;
        $events = DG_Calendar::list(['start' => date('Y-m-d', strtotime('-30 days'))]);
        include DG_PLATFORM_PATH . 'templates/admin/calendar.php';
    }

    public function render_documents() {
        if (!DG_Permissions::current_user_can('dg_manage_platform')) {
            wp_die('Unauthorized');
        }
        $documents = DG_Documents::list_recent(200);
        include DG_PLATFORM_PATH . 'templates/admin/documents.php';
    }

    public function ajax_test_integration() {
        if (!current_user_can('manage_options') || !check_ajax_referer('dg_test_integration', '_wpnonce', false)) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        $service = sanitize_key(wp_unslash($_POST['service'] ?? ''));
        if ($service === 'openai' && class_exists('DG_Integrations')) {
            $result = DG_Integrations::test_openai();
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
            wp_send_json_success($result);
        }
        wp_send_json_error(['message' => 'Unknown service']);
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

    public function render_audit_log() {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_audit_log';
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");
        include DG_PLATFORM_PATH . 'templates/admin/audit-log.php';
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
        $current = get_option('dg_platform_active_modules', ['core']);
        $result = ['core'];
        foreach (array_unique($modules) as $key) {
            if ($key === 'core') {
                continue;
            }
            $was_active = in_array($key, $current, true);
            if (!class_exists('DG_Plan_Registry') || $was_active || DG_Plan_Registry::module_allowed($key)) {
                $result[] = $key;
            }
        }
        update_option('dg_platform_active_modules', array_values(array_unique($result)));
        DG_Permissions::log_audit('modules_updated', 'platform', null, null, $result);
        wp_redirect(admin_url('admin.php?page=dg-platform-modules&saved=1'));
        exit;
    }

    public function handle_save_plan() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_save_plan') || !DG_Permissions::current_user_can('dg_manage_modules')) {
            wp_die('Unauthorized');
        }
        $plan = sanitize_text_field(wp_unslash($_POST['plan'] ?? ''));
        if (class_exists('DG_Plan_Registry') && DG_Plan_Registry::set_plan($plan)) {
            $addons = isset($_POST['addons']) ? array_map('sanitize_text_field', wp_unslash($_POST['addons'])) : [];
            DG_Plan_Registry::set_addons($addons);
            DG_Permissions::log_audit('plan_updated', 'platform', null, null, ['plan' => $plan, 'addons' => $addons]);
        }
        wp_redirect(admin_url('admin.php?page=dg-platform-modules&plan_saved=1'));
        exit;
    }

    public function handle_save_api_settings() {
        if (!check_admin_referer('dg_api_settings') || !DG_Permissions::current_user_can('dg_manage_api_keys')) {
            wp_die('Unauthorized');
        }
        $keys = ['pagespeed', 'openai', 'gemini', 'twilio_sid', 'twilio_token', 'twilio_from', 'gsc', 'gbp', 'stripe_secret'];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                DG_Integrations::save_api_key($key, sanitize_text_field(wp_unslash($_POST[$key])));
            }
        }
        if (isset($_POST['dg_admin_dark_default'])) {
            $mode = sanitize_text_field(wp_unslash($_POST['dg_admin_dark_default']));
            if (in_array($mode, ['off', 'on', 'system'], true)) {
                update_option('dg_admin_dark_default', $mode);
            }
        }
        if (isset($_POST['dg_stripe_billing_webhook_secret']) && class_exists('DG_Stripe_Billing') && DG_Stripe_Billing::enabled()) {
            update_option('dg_stripe_billing_webhook_secret', trim(sanitize_text_field(wp_unslash($_POST['dg_stripe_billing_webhook_secret']))));
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

    public function handle_import_contacts_vcard() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_import_contacts_vcard') || !DG_Permissions::current_user_can('dg_manage_contacts')) {
            wp_die('Unauthorized');
        }

        if (empty($_FILES['vcf_files'])) {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-contacts&action=import&error=' . rawurlencode('No file uploaded.')));
            exit;
        }

        $files = $_FILES['vcf_files'];
        $total = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $count = is_array($files['name']) ? count($files['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $total['skipped']++;
                $total['errors'][] = basename((string) ($files['name'][$i] ?? 'file')) . ': upload failed';
                continue;
            }

            $tmp = $files['tmp_name'][$i] ?? '';
            $parsed = DG_Contacts_Vcard::parse_file($tmp);
            if (is_wp_error($parsed)) {
                $total['skipped']++;
                $total['errors'][] = basename((string) ($files['name'][$i] ?? 'file')) . ': ' . $parsed->get_error_message();
                continue;
            }

            if (!$parsed) {
                $total['skipped']++;
                $total['errors'][] = basename((string) ($files['name'][$i] ?? 'file')) . ': no contacts found';
                continue;
            }

            $result = DG_Contacts_Vcard::import_vcards($parsed);
            $total['imported'] += (int) $result['imported'];
            $total['updated'] += (int) $result['updated'];
            $total['skipped'] += (int) $result['skipped'];
            if (!empty($result['errors'])) {
                $total['errors'] = array_merge($total['errors'], $result['errors']);
            }
        }

        if ($total['imported'] === 0 && $total['updated'] === 0 && $total['skipped'] > 0 && empty($total['errors'])) {
            wp_safe_redirect(admin_url('admin.php?page=dg-platform-contacts&action=import&error=' . rawurlencode('No contacts could be imported.')));
            exit;
        }

        if (!empty($total['errors'])) {
            set_transient('dg_contacts_vcard_import_errors_' . get_current_user_id(), $total['errors'], 300);
        }

        $url = add_query_arg([
            'page' => 'dg-platform-contacts',
            'action' => 'import',
            'imported' => 1,
            'new' => $total['imported'],
            'updated' => $total['updated'],
            'skipped' => $total['skipped'],
        ], admin_url('admin.php'));

        wp_safe_redirect($url);
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
        $payload = [
            'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'event_type' => sanitize_text_field(wp_unslash($_POST['event_type'] ?? 'meeting')),
            'start_at' => sanitize_text_field(wp_unslash($_POST['start_at'] ?? '')),
            'end_at' => sanitize_text_field(wp_unslash($_POST['end_at'] ?? '')),
            'contact_id' => !empty($_POST['contact_id']) ? (int) $_POST['contact_id'] : null,
            'location' => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
            'status' => sanitize_text_field(wp_unslash($_POST['status'] ?? 'scheduled')),
        ];
        $event_id = (int) ($_POST['event_id'] ?? 0);
        if ($event_id > 0 && DG_Calendar::get($event_id)) {
            DG_Calendar::update($event_id, $payload);
            wp_redirect(admin_url('admin.php?page=dg-platform-calendar&updated=1'));
            exit;
        }
        DG_Calendar::create($payload);
        wp_redirect(admin_url('admin.php?page=dg-platform-calendar&saved=1'));
        exit;
    }

    public function handle_save_document() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_save_document') || !DG_Permissions::current_user_can('dg_manage_platform')) {
            wp_die('Unauthorized');
        }
        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        if ($attachment_id <= 0 || !get_post($attachment_id)) {
            wp_redirect(admin_url('admin.php?page=dg-platform-documents&error=invalid_attachment'));
            exit;
        }
        DG_Documents::attach(
            $attachment_id,
            sanitize_text_field(wp_unslash($_POST['entity_type'] ?? 'general')),
            (int) ($_POST['entity_id'] ?? 0),
            sanitize_text_field(wp_unslash($_POST['title'] ?? ''))
        );
        wp_redirect(admin_url('admin.php?page=dg-platform-documents&uploaded=1'));
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

    private static function accommodation_owns_calendar() {
        $active = get_option('dg_platform_active_modules', ['core']);
        return in_array('accommodation', (array) $active, true);
    }
}
