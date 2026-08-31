<?php
/**
 * Admin: issue Founding 10 offer links and prove Stripe trials.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Founding_Admin {

    public static function init() {
        add_action('admin_post_dg_founding_create_offer', [__CLASS__, 'handle_create']);
        add_action('admin_post_dg_founding_prove_trial', [__CLASS__, 'handle_prove']);
        add_action('admin_post_dg_founding_toggle_setup_ready', [__CLASS__, 'handle_toggle_ready']);
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $offers = class_exists('DG_Founding_Offers') ? array_reverse(DG_Founding_Offers::all()) : [];
        $proof = get_transient('dg_founding_trial_proof');
        $ready = class_exists('DG_Founding_Journey') && DG_Founding_Journey::setup_is_ready();
        include DG_PLATFORM_PATH . 'templates/admin/founding-offers.php';
    }

    public static function handle_create() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_founding_create_offer')) {
            wp_die('Unauthorized');
        }
        $offer = DG_Founding_Offers::create([
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'business_name' => sanitize_text_field(wp_unslash($_POST['business_name'] ?? '')),
            'platform_tier' => sanitize_key(wp_unslash($_POST['platform_tier'] ?? 'starter')),
            'billing_interval' => sanitize_key(wp_unslash($_POST['billing_interval'] ?? 'month')),
            'apps' => isset($_POST['apps']) ? (array) wp_unslash($_POST['apps']) : [],
            'premium' => isset($_POST['premium']) ? (array) wp_unslash($_POST['premium']) : [],
            'addons' => isset($_POST['addons']) ? (array) wp_unslash($_POST['addons']) : [],
        ]);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-founding&created=' . rawurlencode($offer['token'])));
        exit;
    }

    public static function handle_prove() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_founding_prove_trial')) {
            wp_die('Unauthorized');
        }
        $monthly = DG_Founding_Checkout::prove_trial('month');
        $yearly = DG_Founding_Checkout::prove_trial('year');
        set_transient('dg_founding_trial_proof', [
            'month' => is_wp_error($monthly) ? ['ok' => false, 'error' => $monthly->get_error_message()] : $monthly,
            'year' => is_wp_error($yearly) ? ['ok' => false, 'error' => $yearly->get_error_message()] : $yearly,
            'at' => current_time('mysql'),
        ], HOUR_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-founding&proved=1'));
        exit;
    }

    public static function handle_toggle_ready() {
        if (!current_user_can('manage_options') || !check_admin_referer('dg_founding_toggle_setup_ready')) {
            wp_die('Unauthorized');
        }
        $ready = !empty($_POST['setup_ready']);
        DG_Founding_Journey::set_setup_ready($ready);
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-founding&ready=' . ($ready ? '1' : '0')));
        exit;
    }
}
