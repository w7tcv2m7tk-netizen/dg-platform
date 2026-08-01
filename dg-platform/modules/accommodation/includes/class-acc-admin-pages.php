<?php
/**
 * Accommodation admin subpages (calendar, settings, OTA sync).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Admin_Pages {

    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_calendar_scripts']);
        add_action('wp_ajax_dg_admin_get_bookings', [__CLASS__, 'admin_get_bookings']);
    }

    public static function booking_settings_page() {
        if (isset($_POST['dg_booking_page_id'])) {
            update_option('dg_booking_page_id', intval($_POST['dg_booking_page_id']));
            echo '<div class="notice notice-success"><p>Booking page updated!</p></div>';
        }
        $selected_page = get_option('dg_booking_page_id', 0);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>Booking Settings</h1>
            <p class="dg-muted-subtle">Configure the front-end booking page for accommodation enquiries.</p>
            <div class="dg-panel">
                <form method="post">
                    <table class="form-table">
                        <tr>
                            <th><label for="dg_booking_page_id">Booking Page</label></th>
                            <td>
                                <select name="dg_booking_page_id" id="dg_booking_page_id">
                                    <option value="0">— Select —</option>
                                    <?php foreach (get_pages(['post_status' => 'publish']) as $page) : ?>
                                        <option value="<?php echo (int) $page->ID; ?>" <?php selected($selected_page, $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public static function stripe_settings_page() {
        if (isset($_POST['submit_stripe_settings'])) {
            update_option('dg_stripe_enabled', isset($_POST['stripe_enabled']) ? 'yes' : 'no');
            update_option('dg_stripe_publishable_key', sanitize_text_field($_POST['stripe_publishable_key'] ?? ''));
            update_option('dg_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key'] ?? ''));
            update_option('dg_stripe_webhook_secret', sanitize_text_field($_POST['stripe_webhook_secret'] ?? ''));
            update_option('dg_stripe_test_mode', isset($_POST['stripe_test_mode']) ? 'yes' : 'no');
            echo '<div class="notice notice-success"><p>✅ Stripe settings saved!</p></div>';
        }
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>💳 Stripe Payment Settings</h1>
            <p class="dg-muted-subtle">Credit card payments for direct bookings.</p>
            <div class="dg-panel">
                <form method="post">
                    <table class="form-table">
                        <tr><th>Enable Stripe</th>
                            <td><label><input type="checkbox" name="stripe_enabled" value="1" <?php checked(get_option('dg_stripe_enabled', 'no'), 'yes'); ?>> Enable credit card payments</label></td></tr>
                        <tr><th>Test Mode</th>
                            <td><label><input type="checkbox" name="stripe_test_mode" value="1" <?php checked(get_option('dg_stripe_test_mode', 'yes'), 'yes'); ?>> Use test mode</label></td></tr>
                        <tr><th>Publishable Key</th>
                            <td><input type="text" name="stripe_publishable_key" class="regular-text" style="width:100%;max-width:500px;" value="<?php echo esc_attr(get_option('dg_stripe_publishable_key', '')); ?>"></td></tr>
                        <tr><th>Secret Key</th>
                            <td><input type="password" name="stripe_secret_key" class="regular-text" style="width:100%;max-width:500px;" value="<?php echo esc_attr(get_option('dg_stripe_secret_key', '')); ?>"></td></tr>
                        <tr><th>Webhook Secret</th>
                            <td><input type="text" name="stripe_webhook_secret" class="regular-text" style="width:100%;max-width:500px;" value="<?php echo esc_attr(get_option('dg_stripe_webhook_secret', '')); ?>"></td></tr>
                    </table>
                    <?php submit_button('Save Stripe Settings', 'primary', 'submit_stripe_settings'); ?>
                </form>
            </div>
            <div class="dg-panel" style="margin-top:1.5rem;">
                <h2>🔗 Webhook URL</h2>
                <code class="dg-code-block"><?php echo esc_html(home_url('/wp-json/dg-stripe/v1/webhook')); ?></code>
                <p class="dg-muted"><strong>Events:</strong> <code>checkout.session.completed</code></p>
            </div>
        </div>
        <?php
    }

    public static function force_sync_all_page() {
        $accommodations = get_posts(['post_type' => 'dg_accommodation', 'posts_per_page' => -1]);
        $has_ota = [];
        foreach ($accommodations as $acc) {
            $airbnb = get_post_meta($acc->ID, 'dg_ical_url', true);
            $bookingcom = get_post_meta($acc->ID, 'dg_bookingcom_ical_url', true);
            if (!empty($airbnb) || !empty($bookingcom)) {
                $has_ota[] = ['id' => $acc->ID, 'title' => $acc->post_title];
            }
        }
        $nonce = wp_create_nonce('dg_calendar_nonce');
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>🔄 Force Sync ALL OTA Bookings</h1>
            <p class="dg-muted-subtle">Sync blocked dates from Airbnb and Booking.com iCal feeds.</p>
            <div class="dg-panel">
                <p><?php echo count($has_ota); ?> propert<?php echo count($has_ota) === 1 ? 'y' : 'ies'; ?> with OTA URLs configured.</p>
                <p class="dg-actions">
                    <button type="button" onclick="forceSyncAll('airbnb')" class="button button-primary">🔄 Sync All Airbnb</button>
                    <button type="button" onclick="forceSyncAll('bookingcom')" class="button button-primary">🔄 Sync All Booking.com</button>
                    <button type="button" onclick="forceSyncAll('all')" class="button button-primary">🔄 Sync ALL</button>
                </p>
                <div id="sync-result" class="dg-muted" style="margin-top:1rem;display:none;"></div>
            </div>
        </div>
        <script>
        var otaAccommodations = <?php echo wp_json_encode(array_column($has_ota, 'id')); ?>;
        function forceSyncAll(source) {
            var result = document.getElementById('sync-result');
            result.style.display = 'block';
            result.innerHTML = '⏳ Syncing...';
            if (otaAccommodations.length === 0) {
                result.innerHTML = '❌ No accommodations with OTA URLs found.';
                return;
            }
            var processed = 0;
            var imported = 0;
            var errors = 0;
            var totalOps = source === 'all' ? otaAccommodations.length * 2 : otaAccommodations.length;
            otaAccommodations.forEach(function(accId) {
                var sourcesToSync = source === 'all' ? ['airbnb', 'bookingcom'] : [source];
                sourcesToSync.forEach(function(src) {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { action: 'dg_ota_sync', accommodation_id: accId, source: src, nonce: '<?php echo esc_js($nonce); ?>' },
                        success: function(response) {
                            processed++;
                            if (response.success) imported++;
                            else errors++;
                            updateProgress();
                        },
                        error: function() { processed++; errors++; updateProgress(); }
                    });
                });
            });
            function updateProgress() {
                var progress = Math.round((processed / totalOps) * 100);
                result.innerHTML = '⏳ Syncing... ' + processed + ' of ' + totalOps + ' (' + progress + '%)';
                if (processed === totalOps) {
                    result.innerHTML = '✅ Complete! Imported: ' + imported + ', Errors: ' + errors;
                }
            }
        }
        </script>
        <?php
    }

    public static function admin_calendar_page() {
        $nonce = wp_create_nonce('dg_calendar_nonce');
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>📅 Booking Calendar</h1>
            <p class="dg-muted-subtle">All properties and booking statuses in one view.</p>
            <div class="dg-panel">
                <div id="dg-admin-calendar" style="min-height:500px;"></div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('dg-admin-calendar');
            if (!calendarEl || typeof FullCalendar === 'undefined') {
                calendarEl.innerHTML = '<p class="dg-muted" style="text-align:center;padding:40px;">Loading calendar...</p>';
                return;
            }
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                events: function(fetchInfo, successCallback) {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'dg_admin_get_bookings',
                            start: fetchInfo.startStr,
                            end: fetchInfo.endStr,
                            nonce: '<?php echo esc_js($nonce); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                successCallback(response.data.map(function(b) {
                                    var color = '#ffc107';
                                    if (b.status === 'confirmed') color = '#28a745';
                                    else if (b.status === 'cancelled') color = '#dc3545';
                                    else if (b.status === 'airbnb') color = '#ff5a5f';
                                    else if (b.status === 'bookingcom') color = '#003580';
                                    return { id: b.id, title: b.guest_name + ' - ' + b.accommodation, start: b.checkin, end: b.checkout, color: color, extendedProps: b };
                                }));
                            }
                        }
                    });
                },
                eventClick: function(info) {
                    var p = info.event.extendedProps;
                    alert('Booking #' + p.id + '\nGuest: ' + p.guest_name + '\nAccommodation: ' + p.accommodation + '\nCheck-in: ' + p.checkin + '\nCheck-out: ' + p.checkout + '\nStatus: ' + p.status);
                }
            });
            calendar.render();
        });
        </script>
        <?php
    }

    public static function admin_calendar_scripts($hook) {
        if ($hook !== 'dg_accommodation_page_dg-admin-calendar') {
            return;
        }
        wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css', [], '5.11.5');
        wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js', ['jquery'], '5.11.5', true);
    }

    public static function admin_get_bookings() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dg_calendar_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => 'dg_booking_checkin', 'value' => sanitize_text_field($_POST['end'] ?? ''), 'compare' => '<', 'type' => 'DATE'],
                ['key' => 'dg_booking_checkout', 'value' => sanitize_text_field($_POST['start'] ?? ''), 'compare' => '>', 'type' => 'DATE'],
            ],
        ]);

        $events = [];
        foreach ($bookings as $b) {
            $events[] = [
                'id' => $b->ID,
                'guest_name' => get_post_meta($b->ID, 'dg_booking_name', true) ?: 'Guest',
                'accommodation' => get_post_meta($b->ID, 'dg_booking_accommodation_name', true) ?: 'Unknown',
                'checkin' => get_post_meta($b->ID, 'dg_booking_checkin', true),
                'checkout' => get_post_meta($b->ID, 'dg_booking_checkout', true),
                'status' => get_post_meta($b->ID, 'dg_booking_status', true) ?: 'pending',
            ];
        }
        wp_send_json_success($events);
    }
}

DG_Acc_Admin_Pages::init();
