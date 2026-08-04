<?php
/**
 * Services module — jobs, quotes, scheduling.
 *
 * @package DG_Platform
 * @version 10.11.0
 */

if (!defined('ABSPATH')) exit;

class DG_Module_Services {
    private static $instance = null;

    public static function get_instance($platform = null) {
        if (null === self::$instance) self::$instance = new self($platform);
        return self::$instance;
    }

    private function __construct($platform) {
        if ($platform) $platform->register_module('services', $this);
        foreach (['class-svc-permissions.php','class-svc-pipeline.php','class-svc-reports.php','class-svc-admin-views.php'] as $f) {
            require_once __DIR__ . '/includes/' . $f;
        }
        add_action('init', [$this, 'create_tables'], 5);
        add_action('dg_platform_register_menus', [$this, 'register_menus'], 15);
        add_action('admin_post_dg_svc_add_job', [$this, 'handle_add']);
        add_filter('dg_platform_dashboard_widgets', [$this, 'dashboard_widgets']);
        add_filter('dg_platform_capabilities', function ($caps) {
            return array_merge($caps, ['dg_svc_view_jobs', 'dg_svc_manage_jobs']);
        });
        add_filter('dg_analytics_pro/metrics', [$this, 'analytics_metrics']);
    }

    public function create_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_svc_jobs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT, contact_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL, service_type varchar(100) DEFAULT 'General',
            stage varchar(50) DEFAULT 'inquiry', status varchar(20) DEFAULT 'active',
            quoted_amount decimal(15,2) DEFAULT 0, scheduled_at datetime DEFAULT NULL,
            address varchar(255) DEFAULT NULL, notes text, owner_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY stage (stage), KEY contact_id (contact_id)
        ) {$wpdb->get_charset_collate()};");
    }

    public function register_menus() {
        if (!DG_Svc_Permissions::can_view()) return;
        add_submenu_page('dg-platform', 'Services', '🔧 Services', DG_Svc_Permissions::menu_cap(), 'dg-svc-dashboard', ['DG_Svc_Admin_Views', 'render_dashboard']);
        if (DG_Svc_Permissions::can_manage()) {
            add_submenu_page('dg-platform', 'New Job', '➕ Service Job', DG_Svc_Permissions::menu_cap(), 'dg-svc-add', ['DG_Svc_Admin_Views', 'render_add']);
        }
    }

    public function handle_add() {
        if (!DG_Svc_Permissions::can_manage() || !check_admin_referer('dg_svc_add_job')) wp_die('Unauthorized');
        if (!empty($_POST['scheduled_at'])) {
            $_POST['scheduled_at'] = str_replace('T', ' ', sanitize_text_field($_POST['scheduled_at'])) . ':00';
        }
        DG_Svc_Pipeline::create($_POST);
        wp_safe_redirect(admin_url('admin.php?page=dg-svc-dashboard&added=1'));
        exit;
    }

    public function dashboard_widgets($widgets) {
        if (!DG_Svc_Permissions::can_view()) return $widgets;
        $s = DG_Svc_Reports::summary();
        $widgets[] = ['id' => 'svc_jobs', 'label' => 'Service jobs', 'value' => $s['jobs'], 'color' => '#F59E0B'];
        return $widgets;
    }

    public function analytics_metrics($metrics) {
        if (!DG_Svc_Permissions::can_view()) return $metrics;
        $s = DG_Svc_Reports::summary();
        $metrics['svc_jobs'] = ['value' => (float) $s['jobs'], 'module' => 'services'];
        return $metrics;
    }
}
