<?php
/**
 * Fatal error logger for DG Platform recovery.
 *
 * Upload to: wp-content/mu-plugins/dg-platform-diagnose.php
 * Reload the site once, then download: wp-content/dg-fatal.log
 * Delete this file when finished.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    $line = sprintf(
        "[%s] %s in %s on line %d\n",
        gmdate('c'),
        $error['message'],
        $error['file'],
        $error['line']
    );

    if (defined('WP_CONTENT_DIR')) {
        @file_put_contents(WP_CONTENT_DIR . '/dg-fatal.log', $line, FILE_APPEND | LOCK_EX);
    }
});
