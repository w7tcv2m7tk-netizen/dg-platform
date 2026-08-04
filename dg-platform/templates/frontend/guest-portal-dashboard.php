<?php
/**
 * Guest portal dashboard — plugin-rendered for Currumbin Valley Hideaway.
 *
 * @var array<string,mixed> $ctx
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

$ctx = isset($ctx) && is_array($ctx) ? $ctx : [];
$guest_name = $ctx['guest_name'] ?? 'Guest';
$upcoming = is_array($ctx['upcoming_bookings'] ?? null) ? $ctx['upcoming_bookings'] : [];
$past = is_array($ctx['past_bookings'] ?? null) ? $ctx['past_bookings'] : [];
$logout_url = $ctx['logout_url'] ?? home_url('/');
$site_label = $ctx['site_label'] ?? 'Currumbin Valley Hideaway';
$support_email = $ctx['support_email'] ?? '';
$is_builder = !empty($ctx['is_builder']);

function dg_guest_format_date($date) {
    if (!$date) {
        return '';
    }
    $ts = strtotime((string) $date);
    return $ts ? date('l, j M Y', $ts) : esc_html((string) $date);
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(sprintf('Guest Portal | %s', $site_label)); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;500;600;700&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { margin: 0; background: #F7F4EE; color: #1C2B2A; font-family: Inter, sans-serif; }
        .dg-guest-portal { max-width: 920px; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }
        .dg-guest-portal * { box-sizing: border-box; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .header h1 { font-family: Sora, sans-serif; font-size: 1.75rem; margin: 0 0 0.35rem; }
        .header p { margin: 0; color: #6B7A78; }
        .header-actions a { color: #1C2B2A; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        .section { margin-bottom: 2rem; }
        .section h2 { font-family: Sora, sans-serif; font-size: 1.1rem; margin: 0 0 1rem; }
        .booking-card { background: #fff; border: 1px solid #E0D6CC; border-radius: 16px; padding: 1.25rem 1.35rem; margin-bottom: 1rem; }
        .booking-card h3 { margin: 0 0 0.5rem; font-size: 1.05rem; }
        .booking-meta { color: #6B7A78; font-size: 0.88rem; line-height: 1.6; }
        .booking-meta strong { color: #1C2B2A; }
        .badge { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; background: #EDE4D8; color: #1C2B2A; }
        .cta { display: inline-block; margin-top: 0.85rem; padding: 0.65rem 1rem; background: #B9A48A; color: #fff !important; text-decoration: none; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
        .empty { background: #FCF9F5; border: 1px dashed #D8CFC4; border-radius: 16px; padding: 1.5rem; color: #6B7A78; }
        .support { margin-top: 2.5rem; text-align: center; color: #6B7A78; font-size: 0.88rem; }
        .support a { color: #1C2B2A; font-weight: 600; }
    </style>
    <?php wp_head(); ?>
</head>
<body class="dg-guest-portal-page">
<div class="dg-guest-portal">

    <div class="header">
        <div>
            <h1><?php echo esc_html(sprintf(__('Welcome back, %s', 'dg-platform'), $guest_name)); ?></h1>
            <p><?php esc_html_e('Your stays and check-in details in one place.', 'dg-platform'); ?></p>
        </div>
        <?php if (!$is_builder) : ?>
        <div class="header-actions">
            <a href="<?php echo esc_url($logout_url); ?>"><i class="fas fa-sign-out-alt"></i> <?php esc_html_e('Sign out', 'dg-platform'); ?></a>
        </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2><?php esc_html_e('Upcoming stays', 'dg-platform'); ?></h2>
        <?php if ($upcoming) : ?>
            <?php foreach ($upcoming as $booking) : ?>
            <div class="booking-card">
                <h3><?php echo esc_html($booking['accommodation'] ?? ''); ?> <span class="badge"><?php echo esc_html($booking['status'] ?? ''); ?></span></h3>
                <div class="booking-meta">
                    <?php if (!empty($booking['ref'])) : ?><div><strong><?php esc_html_e('Reference:', 'dg-platform'); ?></strong> <?php echo esc_html($booking['ref']); ?></div><?php endif; ?>
                    <?php if (!empty($booking['checkin'])) : ?><div><strong><?php esc_html_e('Check-in:', 'dg-platform'); ?></strong> <?php echo esc_html(dg_guest_format_date($booking['checkin'])); ?><?php echo !empty($booking['checkin_time']) ? ' from ' . esc_html($booking['checkin_time']) : ''; ?></div><?php endif; ?>
                    <?php if (!empty($booking['checkout'])) : ?><div><strong><?php esc_html_e('Check-out:', 'dg-platform'); ?></strong> <?php echo esc_html(dg_guest_format_date($booking['checkout'])); ?><?php echo !empty($booking['checkout_time']) ? ' by ' . esc_html($booking['checkout_time']) : ''; ?></div><?php endif; ?>
                    <?php if (!empty($booking['address'])) : ?><div><strong><?php esc_html_e('Address:', 'dg-platform'); ?></strong> <?php echo esc_html($booking['address']); ?></div><?php endif; ?>
                    <?php if (!empty($booking['wifi_password'])) : ?><div><strong><?php esc_html_e('Wi‑Fi:', 'dg-platform'); ?></strong> <?php echo esc_html($booking['wifi_password']); ?></div><?php endif; ?>
                </div>
                <?php if (!empty($booking['checkin_url'])) : ?>
                <a class="cta" href="<?php echo esc_url($booking['checkin_url']); ?>"><?php
                    $label = !empty($booking['checkin_page_label'])
                        ? sprintf(__('Open %s check-in page', 'dg-platform'), $booking['checkin_page_label'])
                        : __('Open check-in page', 'dg-platform');
                    echo esc_html($label);
                ?></a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else : ?>
        <div class="empty"><?php esc_html_e('No upcoming stays yet. When you book with us, your stay will appear here.', 'dg-platform'); ?></div>
        <?php endif; ?>
    </div>

    <?php if ($past) : ?>
    <div class="section">
        <h2><?php esc_html_e('Past stays', 'dg-platform'); ?></h2>
        <?php foreach ($past as $booking) : ?>
        <div class="booking-card">
            <h3><?php echo esc_html($booking['accommodation'] ?? ''); ?></h3>
            <div class="booking-meta">
                <?php if (!empty($booking['checkin'])) : ?><div><strong><?php esc_html_e('Stayed:', 'dg-platform'); ?></strong> <?php echo esc_html(dg_guest_format_date($booking['checkin'])); ?> – <?php echo esc_html(dg_guest_format_date($booking['checkout'] ?? '')); ?></div><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($support_email) : ?>
    <p class="support"><?php esc_html_e('Questions before you arrive?', 'dg-platform'); ?> <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a></p>
    <?php endif; ?>

</div>
<?php wp_footer(); ?>
</body>
</html>
