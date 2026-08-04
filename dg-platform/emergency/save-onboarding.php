<?php
/**
 * Drop-in replacement for public_html/save-onboarding.php
 *
 * Upload to WordPress root (same folder as wp-load.php).
 * Delegates to DG Platform — no FluentCRM required.
 *
 * @package DG_Platform
 */

require_once __DIR__ . '/wp-load.php';

if (!class_exists('DG_Client_Onboarding')) {
    status_header(503);
    wp_die('DG Platform client onboarding is not available. Activate the DG Platform plugin.');
}

DG_Client_Onboarding::handle_legacy_root_post();
