<?php
/**
 * Plugin Name: DG Platform
 * Plugin URI: https://digitalgate.com.au
 * Description: DigitalGate Business Platform Core - Modular CRM with Industry Modules
 * Version: 10.45.0
 * Author: DigitalGate
 * Author URI: https://digitalgate.com.au
 * Text Domain: dg-platform
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Kill switch: create empty wp-content/.dg-platform-off via FTP to disable without renaming the plugin folder.
if (defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR . '/.dg-platform-off')) {
    return;
}

define('DG_PLATFORM_VERSION', '10.45.0');
define('DG_PLATFORM_PATH', plugin_dir_path(__FILE__));
define('DG_PLATFORM_URL', plugin_dir_url(__FILE__));
define('DG_MODULES_PATH', DG_PLATFORM_PATH . 'modules/');
define('DG_INCLUDES_PATH', DG_PLATFORM_PATH . 'includes/');
define('DG_REST_NAMESPACE', 'digitalgate/v1');

require_once DG_INCLUDES_PATH . 'class-activator.php';
require_once DG_INCLUDES_PATH . 'class-site-profile.php';
require_once DG_INCLUDES_PATH . 'class-site-portal-config.php';
require_once DG_INCLUDES_PATH . 'class-module-registry.php';
require_once DG_INCLUDES_PATH . 'class-plan-registry.php';
require_once DG_INCLUDES_PATH . 'class-permissions.php';
require_once DG_INCLUDES_PATH . 'services/class-email-names.php';
require_once DG_INCLUDES_PATH . 'services/class-email-brand.php';
require_once DG_INCLUDES_PATH . 'services/class-contacts.php';
require_once DG_INCLUDES_PATH . 'services/class-contacts-vcard.php';
require_once DG_INCLUDES_PATH . 'services/class-organisations.php';
require_once DG_INCLUDES_PATH . 'services/class-activities.php';
require_once DG_INCLUDES_PATH . 'services/class-tasks.php';
require_once DG_INCLUDES_PATH . 'services/class-calendar.php';
require_once DG_INCLUDES_PATH . 'services/class-documents.php';
require_once DG_INCLUDES_PATH . 'services/class-automation.php';
require_once DG_INCLUDES_PATH . 'services/class-reports.php';
require_once DG_INCLUDES_PATH . 'services/class-integrations.php';
require_once DG_INCLUDES_PATH . 'services/class-ai-client.php';
require_once DG_INCLUDES_PATH . 'services/class-ai-assist.php';
require_once DG_INCLUDES_PATH . 'class-ai-admin.php';
require_once DG_INCLUDES_PATH . 'services/class-search.php';
require_once DG_INCLUDES_PATH . 'services/class-entity-meta.php';
require_once DG_INCLUDES_PATH . 'class-legacy-notices.php';
require_once DG_INCLUDES_PATH . 'class-frontend-cleanup.php';
require_once DG_INCLUDES_PATH . 'reviews/class-reviews.php';
require_once DG_INCLUDES_PATH . 'reviews/class-reviews-airbnb.php';
require_once DG_INCLUDES_PATH . 'reviews/class-reviews-admin.php';
require_once DG_INCLUDES_PATH . 'seo/class-seo.php';
require_once DG_INCLUDES_PATH . 'ai-visibility/class-ai-visibility.php';
require_once DG_INCLUDES_PATH . 'automation-pro/class-automation-pro.php';
require_once DG_INCLUDES_PATH . 'analytics-pro/class-analytics-pro.php';
require_once DG_INCLUDES_PATH . 'social-pro/class-social-pro.php';
require_once DG_INCLUDES_PATH . 'site-tools/class-site-tools.php';
require_once DG_INCLUDES_PATH . 'class-admin-dark-mode.php';
require_once DG_INCLUDES_PATH . 'class-dev-api.php';
require_once DG_INCLUDES_PATH . 'api/class-rest-controller.php';
require_once DG_INCLUDES_PATH . 'class-admin-menu.php';
require_once DG_INCLUDES_PATH . 'class-admin-delete.php';
require_once DG_INCLUDES_PATH . 'class-platform.php';
require_once DG_INCLUDES_PATH . 'class-onboarding.php';
require_once DG_INCLUDES_PATH . 'class-client-portal.php';
require_once DG_INCLUDES_PATH . 'class-site-portal-guest.php';
require_once DG_INCLUDES_PATH . 'class-client-reports.php';
require_once DG_INCLUDES_PATH . 'class-client-support.php';
require_once DG_INCLUDES_PATH . 'class-stripe-billing.php';
require_once DG_INCLUDES_PATH . 'class-client-onboarding.php';

register_activation_hook(__FILE__, ['DG_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['DG_Activator', 'deactivate']);

function dg_platform() {
    return DG_Platform::get_instance();
}

add_action('admin_init', function () {
    if (class_exists('DG_Permissions')) {
        DG_Permissions::register_capabilities();
    }
    if (class_exists('DG_Activator')) {
        DG_Activator::maybe_enable_deferred_modules();
        DG_Activator::maybe_upgrade_schema();
    }
}, 1);

add_action('plugins_loaded', function () {
    if (class_exists('DG_Plan_Registry')) {
        DG_Plan_Registry::init();
    }
    if (class_exists('DG_Reviews')) {
        DG_Reviews::init();
    }
    if (class_exists('DG_Reviews_Airbnb')) {
        DG_Reviews_Airbnb::init();
        add_action('init', ['DG_Reviews_Airbnb', 'maybe_set_default_listing_ids'], 20);
    }
    if (is_admin() && class_exists('DG_Reviews_Admin')) {
        DG_Reviews_Admin::init();
    }
    if (class_exists('DG_AI_Admin')) {
        DG_AI_Admin::init();
    }
}, 0);

add_action('plugins_loaded', 'dg_platform', 1);
