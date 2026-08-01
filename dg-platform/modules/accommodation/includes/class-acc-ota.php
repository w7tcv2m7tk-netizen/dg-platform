<?php
/**
 * OTA sync, blocked dates, and calendar AJAX.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Ota {

    public static function init() {
        add_action('wp_ajax_dg_ota_sync', [__CLASS__, 'ajax_ota_sync']);
        add_action('wp_ajax_dg_airbnb_sync', [__CLASS__, 'ajax_ota_sync']);
        add_action('wp_ajax_dg_refresh_calendar', [__CLASS__, 'ajax_refresh_calendar']);
        add_action('wp_ajax_nopriv_dg_refresh_calendar', [__CLASS__, 'ajax_refresh_calendar']);
        add_action('wp', [__CLASS__, 'schedule_ota_sync']);
        add_action('dg_hourly_ota_sync', [__CLASS__, 'run_hourly_ota_sync']);
        add_action('admin_init', [__CLASS__, 'auto_sync_on_admin_load']);
        add_action('admin_init', [__CLASS__, 'add_force_sync_buttons']);
    }

    public static function rebuild_blocked_dates($accommodation_id) {
        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'meta_query' => [['key' => 'dg_booking_accommodation_id', 'value' => $accommodation_id, 'compare' => '=']],
        ]);

        $blocked_ranges = [];
        foreach ($bookings as $b) {
            $checkin = get_post_meta($b->ID, 'dg_booking_checkin', true);
            $checkout = get_post_meta($b->ID, 'dg_booking_checkout', true);
            $status = get_post_meta($b->ID, 'dg_booking_status', true);
            if ($status !== 'cancelled' && $checkin && $checkout) {
                $blocked_ranges[] = $checkin . ' to ' . $checkout;
            }
        }
        $blocked_ranges = array_unique($blocked_ranges);
        sort($blocked_ranges);
        update_post_meta($accommodation_id, 'dg_blocked_dates', implode("\n", $blocked_ranges));

        return $blocked_ranges;
    }

    public static function ajax_ota_sync() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dg_calendar_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $accommodation_id = isset($_POST['accommodation_id']) ? intval($_POST['accommodation_id']) : 0;
        if (!$accommodation_id) {
            wp_send_json_error('Invalid accommodation ID');
        }

        self::rebuild_blocked_dates($accommodation_id);
        wp_send_json_success(['message' => 'Calendar updated successfully']);
    }

    public static function ajax_refresh_calendar() {
        if (!isset($_POST['accommodation_id']) || !isset($_POST['nonce']) ||
            !wp_verify_nonce($_POST['nonce'], 'dg_calendar_nonce')) {
            wp_send_json_error('Invalid request');
        }

        $blocked = self::rebuild_blocked_dates(intval($_POST['accommodation_id']));
        wp_send_json_success(['blocked_dates' => $blocked]);
    }

    public static function schedule_ota_sync() {
        if (!wp_next_scheduled('dg_hourly_ota_sync')) {
            wp_schedule_event(time(), 'hourly', 'dg_hourly_ota_sync');
        }
    }

    public static function run_hourly_ota_sync() {
        $accommodations = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'meta_query' => [['key' => 'dg_ical_url', 'value' => '', 'compare' => '!=']],
        ]);
        foreach ($accommodations as $acc) {
            self::rebuild_blocked_dates($acc->ID);
        }
    }

    public static function auto_sync_on_admin_load() {
        global $pagenow;
        if ($pagenow !== 'post.php' || !isset($_GET['post']) || get_post_type((int) $_GET['post']) !== 'dg_accommodation') {
            return;
        }

        $post_id = intval($_GET['post']);
        $last_sync = get_post_meta($post_id, 'dg_ical_last_sync', true);
        $url = get_post_meta($post_id, 'dg_ical_url', true);

        if ($url && (!$last_sync || (time() - strtotime($last_sync)) > 21600)) {
            self::rebuild_blocked_dates($post_id);
            update_post_meta($post_id, 'dg_ical_last_sync', current_time('mysql'));
        }
    }

    public static function add_force_sync_buttons() {
        global $pagenow;
        if ($pagenow !== 'post.php' || !isset($_GET['post']) || get_post_type((int) $_GET['post']) !== 'dg_accommodation') {
            return;
        }
        $post_id = intval($_GET['post']);
        $nonce = wp_create_nonce('dg_calendar_nonce');
        ?>
        <script>
        jQuery(document).ready(function($) {
            var notice = $('<div class="notice notice-info"><p><button class="button button-primary" onclick="dgForceSync()">🔄 Sync OTA Now</button> <span id="dg-sync-status" style="margin-left:10px;"></span></p></div>');
            $('.wrap h1').after(notice);
            window.dgForceSync = function() {
                $('#dg-sync-status').text('⏳ Syncing...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dg_ota_sync',
                        accommodation_id: <?php echo $post_id; ?>,
                        source: 'airbnb',
                        nonce: '<?php echo esc_js($nonce); ?>'
                    },
                    success: function(r) {
                        $('#dg-sync-status').text(r.success ? '✅ ' + r.data.message : '❌ ' + r.data.message);
                    }
                });
            };
        });
        </script>
        <?php
    }
}

DG_Acc_Ota::init();
