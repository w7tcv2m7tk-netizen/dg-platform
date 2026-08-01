<?php
/**
 * Emergency safe mode for DG Platform.
 *
 * Upload this file to: wp-content/mu-plugins/dg-platform-safe-mode.php
 * That loads only CRM Core and disables industry modules until the site is stable.
 *
 * Remove this file once DG Platform is working again.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DG_PLATFORM_SAFE_MODE')) {
    define('DG_PLATFORM_SAFE_MODE', true);
}
