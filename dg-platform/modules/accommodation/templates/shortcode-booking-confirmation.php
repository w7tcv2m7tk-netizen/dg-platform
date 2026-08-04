<?php
/**
 * Booking confirmed — restored CVH payment presentation.
 *
 * @var string $ref @var string $name @var string $email @var string $phone
 * @var string $accommodation @var string $checkin @var string $checkout
 * @var string $guests @var string $total @var bool $is_confirmed
 * @var string $payment_method @var string $payid @var string $account_name
 * @var string $bsb @var string $account_number @var bool $stripe_enabled
 * @var int $accommodation_id @var array $checkin_data
 */
if (!defined('ABSPATH')) { exit; }
?>
<div class="dg-booking-confirmed-wrap">
    <div class="dg-booking-confirmed-header">
        <span class="dg-cvh-badge"><?php echo $is_confirmed ? '✅ BOOKING CONFIRMED' : '🌿 RESERVATION RECEIVED'; ?></span>
        <h1><?php echo $is_confirmed ? 'Thank You — You\'re All Set' : 'Complete Your Payment'; ?></h1>
        <p><?php echo $is_confirmed
            ? 'We look forward to welcoming you to Currumbin Valley Hideaway.'
            : 'Your booking is reserved. Please complete payment to secure your stay.'; ?></p>
    </div>

    <div class="dg-booking-confirmed-card dg-booking-details-card">
        <h3>Booking Details</h3>
        <div class="dg-booking-details-list">
            <p><strong>Reference</strong> <span><?php echo esc_html($ref); ?></span></p>
            <p><strong>Status</strong> <span><?php echo $is_confirmed ? '✅ Confirmed' : '⏳ Pending Payment'; ?></span></p>
            <?php if ($name) : ?><p><strong>Guest</strong> <span><?php echo esc_html($name); ?></span></p><?php endif; ?>
            <?php if ($email) : ?><p><strong>Email</strong> <span><?php echo esc_html($email); ?></span></p><?php endif; ?>
            <?php if ($phone) : ?><p><strong>Phone</strong> <span><?php echo esc_html($phone); ?></span></p><?php endif; ?>
            <?php if ($accommodation) : ?><p><strong>Accommodation</strong> <span><?php echo esc_html($accommodation); ?></span></p><?php endif; ?>
            <?php if ($checkin && $checkout) : ?>
                <p><strong>Dates</strong> <span><?php echo esc_html(date('j M Y', strtotime($checkin))); ?> → <?php echo esc_html(date('j M Y', strtotime($checkout))); ?></span></p>
            <?php endif; ?>
            <?php if ($guests) : ?><p><strong>Guests</strong> <span><?php echo esc_html($guests); ?></span></p><?php endif; ?>
            <?php if ($total) : ?>
                <p class="dg-booking-total-row"><strong>Total</strong> <span class="dg-booking-total">$<?php echo number_format((float) $total, 2); ?></span></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_confirmed && !empty($checkin_data)) : ?>
    <div class="dg-booking-confirmed-card dg-checkin-details-card">
        <h3>Check-in Instructions</h3>
        <?php if (!empty($checkin_data['checkin_time']) || !empty($checkin_data['checkout_time'])) : ?>
            <p class="dg-checkin-times">
                <?php if (!empty($checkin_data['checkin_time'])) : ?>
                    <strong>Check-in from:</strong> <?php echo esc_html($checkin_data['checkin_time']); ?>
                <?php endif; ?>
                <?php if (!empty($checkin_data['checkout_time'])) : ?>
                    <?php if (!empty($checkin_data['checkin_time'])) : ?> · <?php endif; ?>
                    <strong>Check-out by:</strong> <?php echo esc_html($checkin_data['checkout_time']); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($checkin_data['address'])) : ?>
            <p><strong>Address:</strong> <?php echo esc_html($checkin_data['address']); ?></p>
        <?php endif; ?>
        <?php if (!empty($checkin_data['instructions'])) : ?>
            <div class="dg-checkin-instructions"><?php echo wp_kses_post($checkin_data['instructions']); ?></div>
        <?php endif; ?>
        <?php if (!empty($checkin_data['wifi_password'])) : ?>
            <p class="dg-checkin-wifi"><strong>Wi‑Fi password:</strong> <?php echo esc_html($checkin_data['wifi_password']); ?></p>
        <?php endif; ?>
        <?php if (!empty($checkin_data['checkin_url'])) : ?>
            <p><a href="<?php echo esc_url($checkin_data['checkin_url']); ?>" class="dg-btn-secondary">Open <?php echo esc_html($checkin_data['checkin_page_label'] ?? $accommodation); ?> check-in page</a></p>
        <?php endif; ?>
        <p class="dg-checkin-email-note">📧 A copy of these instructions has been sent to <?php echo esc_html($email ?: 'your email'); ?>.</p>
    </div>
    <?php elseif ($is_confirmed) : ?>
    <div class="dg-booking-confirmed-card">
        <p>Check-in instructions will be emailed to you shortly.</p>
    </div>
    <?php endif; ?>

    <?php if (!$is_confirmed) : ?>
    <div class="dg-payid-card">
        <span class="dg-payid-preferred">⭐ PREFERRED</span>
        <div class="dg-payid-head">
            <div class="dg-payid-icon">🏦</div>
            <div>
                <strong>Bank Transfer (PayID)</strong>
                <p>No fees · Instant · Our preferred method</p>
            </div>
        </div>
        <div class="dg-payid-body">
            <div class="dg-payid-highlight">
                <div><span>PAYID</span><strong><?php echo esc_html($payid); ?></strong></div>
                <div><span>ACCOUNT NAME</span><strong><?php echo esc_html($account_name); ?></strong></div>
            </div>
            <div class="dg-payid-row">
                <div><span>BSB</span><strong><?php echo esc_html($bsb); ?></strong></div>
                <div><span>ACCOUNT NUMBER</span><strong><?php echo esc_html($account_number); ?></strong></div>
            </div>
            <div class="dg-payid-ref">
                <span>📝 REFERENCE</span>
                <strong>Use booking ref: <?php echo esc_html($ref); ?></strong>
            </div>
        </div>
        <button type="button" class="dg-copy-payid" data-payid="<?php echo esc_attr($payid); ?>">📋 Copy PayID</button>
    </div>

    <?php if ($stripe_enabled) : ?>
    <div class="dg-stripe-card">
        <div class="dg-stripe-head">
            <span>💳</span>
            <strong>Credit / Debit Card (Stripe)</strong>
            <span class="dg-stripe-alt">Alternative</span>
        </div>
        <p class="dg-stripe-note">Return to the booking page to pay securely by card if you did not complete Stripe checkout.</p>
    </div>
    <?php endif; ?>

    <div class="dg-booking-info-note">
        <p>✓ PayID transfers are instant with no fees. ✓ Your booking confirms once payment is received.</p>
    </div>

    <div class="dg-booking-footer-bar">
        <div>⏰ <span>Complete payment to secure your dates</span></div>
        <div>📧 <span>Confirmation sent to your email</span></div>
        <p>📞 0415 257 839 · ✉️ stay@currumbinvalleyhideaway.com.au</p>
    </div>
    <?php endif; ?>

    <div class="dg-booking-confirmed-actions">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="dg-btn-home">Return Home</a>
        <?php if (!$is_confirmed) :
            $back_url = class_exists('DG_Acc_Frontend')
                ? DG_Acc_Frontend::stays_back_url($accommodation_id)
                : home_url('/');
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="dg-btn-secondary">Back to Stays</a>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.querySelector('.dg-copy-payid');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var payid = btn.getAttribute('data-payid') || '';
        if (navigator.clipboard && payid) {
            navigator.clipboard.writeText(payid).then(function () {
                alert('PayID copied: ' + payid);
            }, function () {
                alert('Please copy manually: ' + payid);
            });
        }
    });
});
</script>
