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
            if (isset($_POST['dg_cleaning_access_code'])) {
                update_option('dg_cleaning_access_code', sanitize_text_field(wp_unslash($_POST['dg_cleaning_access_code'])));
            }
            echo '<div class="notice notice-success"><p>Booking settings updated!</p></div>';
        }
        $selected_page = get_option('dg_booking_page_id', 0);
        $cleaning_access_code = get_option('dg_cleaning_access_code', '');
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>Booking Settings</h1>
            <p class="dg-muted-subtle">Hub page: <code>/accommodation/</code> with <code>[dg_accommodation_display]</code> or links to property pages. Set below if using a different slug.</p>
            <div class="dg-panel">
                <form method="post">
                    <table class="form-table">
                        <tr>
                            <th><label for="dg_booking_page_id">Accommodation hub page</label></th>
                            <td>
                                <select name="dg_booking_page_id" id="dg_booking_page_id">
                                    <option value="0">— Auto: /accommodation/ —</option>
                                    <?php foreach (get_pages(['post_status' => 'publish']) as $page) : ?>
                                        <option value="<?php echo (int) $page->ID; ?>" <?php selected($selected_page, $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="dg_cleaning_access_code">Cleaning form access code</label></th>
                            <td>
                                <input type="text" name="dg_cleaning_access_code" id="dg_cleaning_access_code" value="<?php echo esc_attr($cleaning_access_code); ?>" class="regular-text" autocomplete="off">
                                <p class="description">Optional. If set, cleaners must enter this code when submitting a report. Leave blank to require only the checklist + signature.</p>
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
            <p class="dg-muted-subtle">Credit card payments for direct bookings. Platform billing keys (DigitalGate sales) live in <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-api')); ?>">API Settings</a>.</p>
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
                <h2>🧪 Test mode</h2>
                <p class="dg-muted">Use Stripe <strong>test</strong> keys (<code>pk_test_…</code> / <code>sk_test_…</code>) with Test Mode checked. Test card: <code>4242 4242 4242 4242</code>, any future expiry, any CVC.</p>
                <p class="dg-muted">Webhook secret is optional for dev — bookings confirm via the payment success callback. Add a webhook in Stripe Dashboard for production (<code>payment_intent.succeeded</code>).</p>
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
        $accommodations = get_posts(['post_type' => 'dg_accommodation', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $ota_count = 0;
        foreach ($accommodations as $acc) {
            if (get_post_meta($acc->ID, 'dg_ical_url', true) || get_post_meta($acc->ID, 'dg_bookingcom_ical_url', true)) {
                $ota_count++;
            }
        }
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>📅 Booking Calendar</h1>
            <p class="dg-muted-subtle">All properties and booking statuses in one view.</p>
            <div class="dg-panel" style="margin-bottom:1rem;">
                <p style="margin:0 0 0.75rem;">
                    <strong>Calendar sync</strong> — refresh blocked dates from OTA iCal feeds and local bookings.
                    <?php if ($ota_count) : ?>
                        <span class="dg-muted">(<?php echo (int) $ota_count; ?> propert<?php echo $ota_count === 1 ? 'y' : 'ies'; ?> with iCal URLs)</span>
                    <?php else : ?>
                        <span class="dg-muted">(Add iCal URLs on each property to sync Airbnb / Booking.com)</span>
                    <?php endif; ?>
                </p>
                <p class="dg-actions" style="margin:0;">
                    <button type="button" class="button button-primary" id="dg-cal-sync-all">🔄 Sync all calendars</button>
                    <button type="button" class="button" id="dg-cal-refresh">↻ Refresh view</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dg-force-sync-all')); ?>" class="button">OTA sync settings</a>
                    <span id="dg-cal-sync-status" class="dg-muted" style="margin-left:10px;"></span>
                </p>
            </div>
            <div class="dg-panel">
                <div id="dg-admin-calendar" style="min-height:500px;"></div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('dg-admin-calendar');
            var calendar = null;
            var otaIds = <?php echo wp_json_encode(array_map('intval', wp_list_pluck($accommodations, 'ID'))); ?>;
            var nonce = '<?php echo esc_js($nonce); ?>';

            function renderCalendar() {
                if (!calendarEl || typeof FullCalendar === 'undefined') {
                    if (calendarEl) {
                        calendarEl.innerHTML = '<p class="dg-muted" style="text-align:center;padding:40px;">Loading calendar...</p>';
                    }
                    return;
                }
                if (calendar) {
                    calendar.refetchEvents();
                    return;
                }
                calendar = new FullCalendar.Calendar(calendarEl, {
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
                                nonce: nonce
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
            }

            renderCalendar();

            document.getElementById('dg-cal-refresh').addEventListener('click', function () {
                renderCalendar();
                document.getElementById('dg-cal-sync-status').textContent = 'View refreshed';
            });

            document.getElementById('dg-cal-sync-all').addEventListener('click', function () {
                var status = document.getElementById('dg-cal-sync-status');
                if (!otaIds.length) {
                    status.textContent = 'No properties to sync';
                    return;
                }
                status.textContent = '⏳ Syncing...';
                var processed = 0;
                var ok = 0;
                var fail = 0;
                var total = otaIds.length * 2;
                otaIds.forEach(function(accId) {
                    ['airbnb', 'bookingcom'].forEach(function(source) {
                        jQuery.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: { action: 'dg_ota_sync', accommodation_id: accId, source: source, nonce: nonce },
                            complete: function(xhr) {
                                processed++;
                                try {
                                    var r = JSON.parse(xhr.responseText);
                                    if (r.success) ok++; else fail++;
                                } catch (e) { fail++; }
                                if (processed === total) {
                                    status.textContent = '✅ Sync complete — ' + ok + ' OK, ' + fail + ' skipped/errors';
                                    if (calendar) calendar.refetchEvents();
                                } else {
                                    status.textContent = '⏳ Syncing... ' + processed + '/' + total;
                                }
                            }
                        });
                    });
                });
            });
        });
        </script>
        <?php
    }

    public static function admin_calendar_scripts($hook) {
        if ($hook !== 'dg-platform_page_dg-admin-calendar') {
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