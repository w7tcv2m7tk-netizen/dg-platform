<?php
/**
 * One-off DG Platform admin probe — run from browser without mu-plugin.
 *
 * 1. Upload next to wp-load.php (WordPress root)
 * 2. Visit https://yoursite.com/dg-admin-debug-probe.php
 * 3. DELETE this file immediately after use
 *
 * @package DG_Platform
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=UTF-8');

echo "DG Platform Admin Probe\n";
echo "=======================\n\n";

$wp_load = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: Place this file in your WordPress root (next to wp-load.php).\n");
}

require $wp_load;

if (!current_user_can('manage_options')) {
    echo "Not logged in as admin.\n";
    echo "1. Log into WP Admin in another tab\n";
    echo "2. Reload this page\n\n";
    echo "Or run while logged in as administrator.\n";
    exit;
}

$log = WP_CONTENT_DIR . '/dg-admin-debug.log';

function probe_log($msg) {
    global $log;
    $line = '[' . gmdate('c') . '] ' . $msg . "\n";
    echo $line;
    @file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
}

probe_log('=== probe script start ===');
probe_log('PHP ' . PHP_VERSION);
probe_log('WP ' . get_bloginfo('version'));
probe_log('DG Platform ' . (defined('DG_PLATFORM_VERSION') ? DG_PLATFORM_VERSION : 'NOT LOADED'));
probe_log('Modules ' . wp_json_encode(get_option('dg_platform_active_modules', [])));

require_once ABSPATH . 'wp-admin/includes/admin.php';

$checks = [
    'DG_Platform' => function () {
        return class_exists('DG_Platform') ? 'ok' : 'missing';
    },
    'DG_Admin_Menu::launcher_apps' => function () {
        if (!class_exists('DG_Admin_Menu')) {
            return 'DG_Admin_Menu missing';
        }
        $apps = DG_Admin_Menu::launcher_apps();
        return 'ok (' . count($apps) . ' apps)';
    },
    'DG_Reports::get_dashboard_stats' => function () {
        return wp_json_encode(DG_Reports::get_dashboard_stats());
    },
    'DG_Activities::recent' => function () {
        return 'ok (' . count(DG_Activities::recent(3)) . ' rows)';
    },
    'DG_Onboarding::cached_summary' => function () {
        return wp_json_encode(DG_Onboarding::cached_summary(false));
    },
    'DG_Acc_Reports::summary' => function () {
        if (!class_exists('DG_Acc_Reports')) {
            return 'skipped (no accommodation module)';
        }
        return wp_json_encode(DG_Acc_Reports::summary());
    },
];

foreach ($checks as $label => $fn) {
    try {
        probe_log($label . ' => ' . call_user_func($fn));
    } catch (Throwable $e) {
        probe_log($label . ' => FAIL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}

probe_log('=== probe script end ===');
echo "\nFull log also written to: wp-content/dg-admin-debug.log\n";
echo "Delete dg-admin-debug-probe.php from the server when done.\n";
