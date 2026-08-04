<?php
/**
 * Emergency fix: DG Platform sidebar click → app launcher dashboard.
 *
 * Upload to: wp-content/mu-plugins/dg-platform-menu-fix.php
 * Works even if the main plugin zip is only partially deployed.
 * Remove when dg-platform v10.23.3+ is confirmed working.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', function () {
    if (empty($_GET['page'])) {
        return;
    }

    $page = sanitize_key(wp_unslash($_GET['page']));
    if (strpos($page, 'dg-sep-') !== 0) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=dg-platform'));
    exit;
}, 1);

add_action('admin_menu', function () {
    global $submenu;

    if (empty($submenu['dg-platform']) || !is_array($submenu['dg-platform'])) {
        return;
    }

    $dashboard = null;
    $others = [];

    foreach ($submenu['dg-platform'] as $item) {
        if (!is_array($item)) {
            $others[] = $item;
            continue;
        }
        $slug = isset($item[2]) ? (string) $item[2] : '';
        if ($slug === 'dg-platform') {
            $dashboard = $item;
        } else {
            $others[] = $item;
        }
    }

    if ($dashboard) {
        $submenu['dg-platform'] = array_merge([$dashboard], $others);
    }
}, 99999);
