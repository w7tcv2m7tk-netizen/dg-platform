<?php
/**
 * Plugin Name: DG Platform
 * Plugin URI: https://digitalgate.com.au
 * Description: DigitalGate Business Platform Core - Modular CRM with Industry Modules
 * Version: 10.0.0
 * Author: DigitalGate
 * Author URI: https://digitalgate.com.au
 * Text Domain: dg-platform
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DG_PLATFORM_VERSION', '10.0.0');
define('DG_PLATFORM_PATH', plugin_dir_path(__FILE__));
define('DG_PLATFORM_URL', plugin_dir_url(__FILE__));
define('DG_MODULES_PATH', DG_PLATFORM_PATH . 'modules/');
define('DG_INCLUDES_PATH', DG_PLATFORM_PATH . 'includes/');
define('DG_REST_NAMESPACE', 'digitalgate/v1');

require_once DG_INCLUDES_PATH . 'class-activator.php';
require_once DG_INCLUDES_PATH . 'class-module-registry.php';
require_once DG_INCLUDES_PATH . 'class-permissions.php';
require_once DG_INCLUDES_PATH . 'services/class-contacts.php';
require_once DG_INCLUDES_PATH . 'services/class-organisations.php';
require_once DG_INCLUDES_PATH . 'services/class-activities.php';
require_once DG_INCLUDES_PATH . 'services/class-tasks.php';
require_once DG_INCLUDES_PATH . 'services/class-calendar.php';
require_once DG_INCLUDES_PATH . 'services/class-documents.php';
require_once DG_INCLUDES_PATH . 'services/class-automation.php';
require_once DG_INCLUDES_PATH . 'services/class-reports.php';
require_once DG_INCLUDES_PATH . 'services/class-integrations.php';
require_once DG_INCLUDES_PATH . 'api/class-rest-controller.php';
require_once DG_INCLUDES_PATH . 'class-platform.php';

register_activation_hook(__FILE__, ['DG_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['DG_Activator', 'deactivate']);

function dg_platform() {
    return DG_Platform::get_instance();
}

add_action('plugins_loaded', 'dg_platform', 1);
