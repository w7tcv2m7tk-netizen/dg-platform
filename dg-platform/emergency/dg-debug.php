<?php
/**
 * Standalone WordPress load test — shows the real PHP error on screen.
 *
 * 1. Upload this file to your WordPress root (same folder as wp-load.php)
 * 2. Visit https://yoursite.com/dg-debug.php
 * 3. DELETE this file immediately after use (security)
 *
 * @package DG_Platform
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

header('Content-Type: text/plain; charset=UTF-8');

echo "DG Platform debug loader\n";
echo "PHP: " . PHP_VERSION . "\n\n";

$wp_load = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found next to this script.\n");
}

echo "Loading wp-load.php ...\n";

require $wp_load;

echo "OK — WordPress loaded successfully.\n\n";

if (function_exists('get_option')) {
    $plugins = get_option('active_plugins', []);
    echo "Active plugins (" . count($plugins) . "):\n";
    foreach ($plugins as $plugin) {
        echo "  - {$plugin}\n";
    }
    echo "\n";
}

if (defined('DG_PLATFORM_VERSION')) {
    echo "DG Platform version: " . DG_PLATFORM_VERSION . "\n";
    echo "DG Platform path: " . DG_PLATFORM_PATH . "\n";
} else {
    echo "DG Platform: not loaded (inactive or kill switch active).\n";
}

echo "\nDelete dg-debug.php from the server when done.\n";
