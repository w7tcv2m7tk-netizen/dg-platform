<?php
/**
 * Finance module — loans, lenders, borrowers pipeline.
 *
 * @package DG_Platform
 * @version 10.11.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Module_Finance {

    private static $instance = null;
    private $platform = null;

    public static function get_instance($platform = null) {
        if (null === self::$instance) {
            self::$instance = new self($platform);
        }
        return self::$instance;
    }

    private function __construct($platform) {
        $this->platform = $platform;
        if ($platform) {
            $platform->register_module('finance', $this);
        }
        $this->load_includes();
        add_action('init', [$this, 'create_tables'], 5);
        add_action('dg_platform_register_menus', [$this, 'register_menus'], 15);
        add_action('admin_post_dg_fin_add_application', [$this, 'handle_add_application']);
        add_action('admin_post_dg_fin_update_stage', [$this, 'handle_update_stage']);
        add_action('admin_post_dg_fin_delete_application', [$this, 'handle_delete_application']);
        add_filter('dg_platform_dashboard_widgets', [$this, 'dashboard_widgets']);
        add_filter('dg_platform_capabilities', [$this, 'capabilities']);
        add_filter('dg_analytics_pro/metrics', [$this, 'analytics_metrics']);
    }

    private function load_includes() {
        foreach (['class-fin-permissions.php', 'class-fin-pipeline.php', 'class-fin-reports.php', 'class-fin-admin-views.php'] as $file) {
            require_once __DIR__ . '/includes/' . $file;
        }
    }

    public function create_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_fin_applications';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) NOT NULL,
            loan_type varchar(50) DEFAULT 'Home loan',
            amount decimal(15,2) DEFAULT 0,
            stage varchar(50) DEFAULT 'inquiry',
            status varchar(20) DEFAULT 'active',
            lender varchar(100) DEFAULT NULL,
            notes text,
            owner_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_id (contact_id),
            KEY stage (stage)
        ) {$wpdb->get_charset_collate()};");
    }

    public function register_menus() {
        if (!DG_Fin_Permissions::can_view()) {
            return;
        }
        add_submenu_page('dg-platform', 'Finance', '💰 Finance', DG_Fin_Permissions::menu_cap(), 'dg-fin-dashboard', ['DG_Fin_Admin_Views', 'render_dashboard']);
        if (DG_Fin_Permissions::can_manage()) {
            add_submenu_page('dg-platform', 'New Application', '➕ Finance Application', DG_Fin_Permissions::menu_cap(), 'dg-fin-add', ['DG_Fin_Admin_Views', 'render_add']);
        }
    }

    public function handle_add_application() {
        if (!DG_Fin_Permissions::can_manage() || !check_admin_referer('dg_fin_add_application')) {
            wp_die('Unauthorized');
        }
        DG_Fin_Pipeline::create($_POST);
        wp_safe_redirect(admin_url('admin.php?page=dg-fin-dashboard&added=1'));
        exit;
    }

    public function handle_update_stage() {
        $id = (int) ($_POST['application_id'] ?? 0);
        if ($id <= 0 || !DG_Fin_Permissions::can_manage() || !check_admin_referer('dg_fin_update_stage_' . $id)) {
            wp_die('Unauthorized');
        }
        DG_Fin_Pipeline::update_stage($id, sanitize_text_field(wp_unslash($_POST['stage'] ?? 'inquiry')));
        wp_safe_redirect(admin_url('admin.php?page=dg-fin-dashboard&updated=1'));
        exit;
    }

    public function handle_delete_application() {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0 || !DG_Fin_Permissions::can_manage() || !check_admin_referer('dg_fin_delete_application_' . $id)) {
            wp_die('Unauthorized');
        }
        DG_Fin_Pipeline::delete($id);
        wp_safe_redirect(admin_url('admin.php?page=dg-fin-dashboard&deleted=1'));
        exit;
    }

    public function dashboard_widgets($widgets) {
        if (!DG_Fin_Permissions::can_view()) {
            return $widgets;
        }
        $s = DG_Fin_Reports::summary();
        $widgets[] = ['id' => 'fin_apps', 'label' => 'Finance applications', 'value' => $s['applications'], 'color' => '#059669'];
        return $widgets;
    }

    public function capabilities($caps) {
        return array_merge($caps, ['dg_fin_view_loans', 'dg_fin_manage_loans']);
    }

    public function analytics_metrics($metrics) {
        if (!DG_Fin_Permissions::can_view()) {
            return $metrics;
        }
        $s = DG_Fin_Reports::summary();
        $metrics['fin_applications'] = ['value' => (float) $s['applications'], 'module' => 'finance'];
        $metrics['fin_pipeline_value'] = ['value' => (float) $s['pipeline_value'], 'module' => 'finance'];
        return $metrics;
    }
}
