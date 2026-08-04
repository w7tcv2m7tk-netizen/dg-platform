<?php
/**
 * Emergency: disable DG Platform without renaming the plugin folder.
 *
 * Upload to: wp-content/mu-plugins/dg-platform-emergency-disable.php
 * Remove this file once the site is stable again.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('option_active_plugins', function ($plugins) {
    if (!is_array($plugins)) {
        return $plugins;
    }
    return array_values(array_filter($plugins, function ($plugin) {
        return strpos($plugin, 'dg-platform/') !== 0;
    }));
});

add_filter('site_option_active_sitewide_plugins', function ($plugins) {
    if (!is_array($plugins)) {
        return $plugins;
    }
    unset($plugins['dg-platform/dg-platform.php']);
    return $plugins;
});
