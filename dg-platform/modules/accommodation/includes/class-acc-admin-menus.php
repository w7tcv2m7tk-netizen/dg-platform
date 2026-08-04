<?php
/**
 * Accommodation admin menus under DG Platform (hide CPT top-level menus).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Admin_Menus {

    public static function init() {
        add_action('dg_platform_register_menus', [__CLASS__, 'register'], 15);
        add_action('admin_menu', [__CLASS__, 'remove_default_menus'], 999);
        add_action('admin_init', [__CLASS__, 'admin_screen_titles']);
    }

    public static function cap() {
        return DG_Acc_Permissions::menu_cap_bookings();
    }

    public static function register() {
        if (!DG_Acc_Permissions::can_view_bookings()) {
            return;
        }

        $cap = self::cap();
        $parent = 'dg-platform';

        add_submenu_page($parent, 'Currumbin Valley Hideaway', '🏨 CVH', $cap, 'dg-acc-dashboard', ['DG_Acc_Admin_Views', 'render_dashboard']);
        add_submenu_page($parent, 'Accommodation', '🏡 Accommodation', $cap, 'edit.php?post_type=dg_accommodation');
        add_submenu_page($parent, 'Bookings', '📋 Bookings', $cap, 'edit.php?post_type=dg_booking');
        add_submenu_page($parent, 'Booking Calendar', '📅 Calendar', $cap, 'dg-admin-calendar', ['DG_Acc_Admin_Pages', 'admin_calendar_page']);
        add_submenu_page($parent, 'Guests', '👥 Guests', $cap, 'edit.php?post_type=dg_guest');
        add_submenu_page($parent, 'Accommodation Types', '🏷️ Types', $cap, 'edit-tags.php?taxonomy=dg_accommodation_type&post_type=dg_accommodation');
        add_submenu_page($parent, 'Housekeeping', '🧹 Housekeeping', $cap, 'dg-acc-housekeeping', ['DG_Acc_Housekeeping', 'render_board']);
        add_submenu_page($parent, 'Cleaning Reports', '📋 Cleaning Reports', $cap, 'edit.php?post_type=dg_cleaning_report');
        add_submenu_page($parent, 'Booking Settings', '⚙️ Booking Settings', $cap, 'dg-booking-settings', ['DG_Acc_Admin_Pages', 'booking_settings_page']);
        add_submenu_page($parent, 'Stripe Settings', '💳 Stripe', $cap, 'dg-stripe-settings', ['DG_Acc_Admin_Pages', 'stripe_settings_page']);
        add_submenu_page($parent, 'Sync OTA', '🔄 Sync OTA', $cap, 'dg-force-sync-all', ['DG_Acc_Admin_Pages', 'force_sync_all_page']);
    }

    public static function remove_default_menus() {
        remove_menu_page('edit.php?post_type=dg_accommodation');
        remove_menu_page('edit.php?post_type=dg_booking');
        remove_menu_page('edit.php?post_type=dg_guest');
    }

    /** Keep admin screen titles consistent (Accommodation, not Property). */
    public static function admin_screen_titles() {
        add_filter('admin_title', [__CLASS__, 'filter_admin_title'], 10, 2);
        add_action('admin_head', [__CLASS__, 'fix_accommodation_list_heading']);
    }

    public static function filter_admin_title($admin_title, $title) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'dg_accommodation') {
            return $admin_title;
        }
        return str_replace(['Property', 'Properties'], 'Accommodation', $admin_title);
    }

    public static function fix_accommodation_list_heading() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'dg_accommodation' || $screen->base !== 'edit') {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var h1 = document.querySelector('.wrap > h1');
            if (h1 && /property/i.test(h1.textContent) && !/accommodation/i.test(h1.textContent)) {
                h1.textContent = h1.textContent.replace(/properties?/gi, 'Accommodation');
            }
        });
        </script>
        <?php
    }
}
