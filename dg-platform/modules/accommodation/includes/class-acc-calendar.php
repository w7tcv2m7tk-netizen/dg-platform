<?php
/**
 * Front-end booking calendar (FullCalendar) with CVH styling.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Calendar {

    private static $assets_needed = false;

    public static function init() {
        add_action('wp_footer', [__CLASS__, 'enqueue_assets'], 5);
    }

    public static function enqueue_assets() {
        if (!self::$assets_needed) {
            return;
        }

        wp_enqueue_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css',
            [],
            '5.11.5'
        );
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js',
            [],
            '5.11.5',
            true
        );
        $asset_base = DG_PLATFORM_PATH . 'modules/accommodation/';
        wp_enqueue_style(
            'dg-booking-calendar',
            plugins_url('assets/css/booking-calendar.css', $asset_base . 'accommodation.php'),
            [],
            DG_PLATFORM_VERSION
        );
        wp_enqueue_script(
            'dg-booking-calendar',
            plugins_url('assets/js/booking-calendar.js', $asset_base . 'accommodation.php'),
            ['fullcalendar'],
            DG_PLATFORM_VERSION,
            true
        );
    }

    /**
     * @param int $accommodation_id
     * @param array<string, mixed> $args mode: inline|redirect, checkin, checkout
     */
    public static function render($accommodation_id, $args = []) {
        $accommodation_id = (int) $accommodation_id;
        if (!$accommodation_id || get_post_type($accommodation_id) !== 'dg_accommodation') {
            return '';
        }

        if (class_exists('DG_Acc_Listing_Status') && !DG_Acc_Listing_Status::is_bookable($accommodation_id)) {
            return '';
        }

        self::$assets_needed = true;

        $defaults = [
            'mode' => 'redirect',
            'checkin' => '',
            'checkout' => '',
            'show_price' => true,
        ];
        $args = wp_parse_args($args, $defaults);

        $weekday = floatval(get_post_meta($accommodation_id, 'dg_weekday_rate', true));
        $weekend = floatval(get_post_meta($accommodation_id, 'dg_weekend_rate', true)) ?: $weekday;
        $weekday_peak = floatval(get_post_meta($accommodation_id, 'dg_weekday_peak_rate', true)) ?: $weekday;
        $weekend_peak = floatval(get_post_meta($accommodation_id, 'dg_weekend_peak_rate', true)) ?: $weekend;
        $cleaning = floatval(get_post_meta($accommodation_id, 'dg_cleaning_fee', true));
        $blocked = class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::get_blocked_dates($accommodation_id) : [];
        $book_url = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::booking_page_url($accommodation_id)
            : home_url('/');

        if (!empty($_GET['checkin']) && empty($args['checkin']) && class_exists('DG_Acc_Frontend')) {
            list($ci,) = DG_Acc_Frontend::parse_request_dates();
            if ($ci) {
                $args['checkin'] = $ci;
            }
        }
        if (!empty($_GET['checkout']) && empty($args['checkout']) && class_exists('DG_Acc_Frontend')) {
            list(, $co) = DG_Acc_Frontend::parse_request_dates();
            if ($co) {
                $args['checkout'] = $co;
            }
        }

        $config = [
            'accommodationId' => $accommodation_id,
            'propertyName' => get_the_title($accommodation_id),
            'mode' => $args['mode'] === 'inline' ? 'inline' : 'redirect',
            'bookUrl' => $book_url,
            'blockedDates' => $blocked,
            'weekdayRate' => $weekday,
            'weekendRate' => $weekend,
            'weekdayPeak' => $weekday_peak,
            'weekendPeak' => $weekend_peak,
            'cleaningFee' => $cleaning,
            'peakStart' => get_post_meta($accommodation_id, 'dg_peak_season_start', true) ?: '12-15',
            'peakEnd' => get_post_meta($accommodation_id, 'dg_peak_season_end', true) ?: '01-15',
            'checkin' => $args['checkin'],
            'checkout' => $args['checkout'],
        ];

        ob_start();
        ?>
        <div class="dg-booking-calendar-wrap" data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">
            <div class="dg-calendar-header">
                <h3>📅 Check Availability</h3>
                <?php if ($args['show_price'] && $weekday) : ?>
                    <div class="dg-calendar-price">
                        From <span class="dg-price">$<?php echo number_format($weekday, 0); ?></span> / night
                    </div>
                <?php endif; ?>
            </div>
            <div id="dg-calendar-<?php echo (int) $accommodation_id; ?>" class="dg-booking-calendar"></div>
            <p class="dg-calendar-hint" data-dg-cal-hint>Tap your check-in date, then your check-out date</p>
            <p class="dg-calendar-status" data-dg-cal-status></p>
            <div class="dg-booking-summary" style="display:none;">
                <div class="dg-summary-details">
                    <span>Check-in: <strong data-dg-checkin></strong></span>
                    <span>Check-out: <strong data-dg-checkout></strong></span>
                    <span>Nights: <strong data-dg-nights>0</strong></span>
                    <span>Total: <strong data-dg-total>$0</strong></span>
                </div>
                <div class="dg-booking-actions">
                    <?php if ($args['mode'] === 'inline') : ?>
                        <button type="button" class="dg-calendar-cta">Continue to payment →</button>
                        <button type="button" class="dg-calendar-clear" data-dg-cal-clear>Clear dates</button>
                    <?php else : ?>
                        <a href="<?php echo esc_url($book_url); ?>" class="dg-calendar-cta">📧 Book These Dates →</a>
                        <button type="button" class="dg-calendar-clear" data-dg-cal-clear>Clear dates</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dg-saturday-notice">
                <span class="dg-notice-icon">📌</span>
                <div class="dg-notice-content">
                    <strong>Booking rules</strong>
                    <ul class="dg-booking-rules-list">
                        <li>Saturdays are available for overnight stays</li>
                        <li>No check-ins on Saturdays</li>
                        <li>No check-outs on Saturdays</li>
                        <li>Friday check-ins require a minimum 2-night stay</li>
                        <li>All other days require a minimum 1-night stay</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
