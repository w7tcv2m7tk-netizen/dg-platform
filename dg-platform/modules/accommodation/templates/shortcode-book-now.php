<?php
/** @var int $accommodation_id @var string $property_title @var string $booking_page_url @var WP_Post[] $bookable @var string $checkin @var string $checkout @var object $module */
if (!defined('ABSPATH')) { exit; }
?>
<div class="dg-book-now-page">
    <div class="dg-book-now-header">
        <span class="dg-cvh-badge">🌿 RESERVE YOUR STAY</span>
        <h1>Book Your Getaway</h1>
        <p>Select your check-in date, then your check-out date on the calendar.</p>
    </div>

    <?php if (count($bookable) > 1) : ?>
        <div class="dg-property-tabs">
            <?php foreach ($bookable as $item) :
                $active = ((int) $item->ID === (int) $accommodation_id);
                $tab_url = add_query_arg([
                    'accommodation' => $item->ID,
                    'checkin' => $checkin ?: null,
                    'checkout' => $checkout ?: null,
                ], $booking_page_url);
                ?>
                <a href="<?php echo esc_url($tab_url); ?>" class="dg-property-tab<?php echo $active ? ' is-active' : ''; ?>">
                    <?php echo esc_html($item->post_title); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="dg-book-now-layout">
        <div class="dg-book-now-main">
            <div class="dg-book-now-property">
                <h2><?php echo esc_html($property_title); ?></h2>
            </div>
            <?php echo class_exists('DG_Acc_Calendar')
                ? DG_Acc_Calendar::render($accommodation_id, ['mode' => 'inline', 'checkin' => $checkin, 'checkout' => $checkout])
                : ''; ?>
        </div>
        <aside class="dg-book-now-sidebar">
            <?php echo class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::render_rates($accommodation_id) : ''; ?>
            <?php echo class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::render_features($accommodation_id) : ''; ?>
            <?php echo class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::render_booking_summary($accommodation_id) : ''; ?>
            <div id="dg-book-now-checkout" class="dg-book-now-checkout">
                <?php echo $module->accommodation_enquiry_shortcode(['accommodation_id' => $accommodation_id, 'layout' => 'compact']); ?>
            </div>
            <?php echo class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::render_booking_rules() : ''; ?>
        </aside>
    </div>
</div>
<style>.dg-legacy-booking-summary,[data-legacy-booking-summary],.booking-summary-legacy{display:none!important;}</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.dg-legacy-booking-summary,[data-legacy-booking-summary],.booking-summary-legacy').forEach(function (el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('*').forEach(function (el) {
        if (!el.children.length && el.textContent && el.textContent.indexOf('No Booking Details Found') !== -1) {
            var block = el.closest('div,section,article');
            if (block && !block.closest('#dg-booking-summary-panel')) {
                block.style.display = 'none';
            }
        }
    });
});
</script>
