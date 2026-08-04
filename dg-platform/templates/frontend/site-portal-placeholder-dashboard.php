<?php
/**
 * Placeholder dashboard for portals not yet on Oxygen templates (owner, creator).
 *
 * @var array<string,mixed> $ctx
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

$ctx = isset($ctx) && is_array($ctx) ? $ctx : [];
$user_name = $ctx['user_name'] ?? 'there';
$portal_label = $ctx['portal_label'] ?? 'Portal';
$site_label = $ctx['site_label'] ?? '';
$logout_url = $ctx['logout_url'] ?? home_url('/');
$message = $ctx['message'] ?? __('Your dashboard is being prepared. Check back soon.', 'dg-platform');
$theme = $ctx['theme'] ?? 'digitalgate';

$palettes = [
    'roe' => ['bg' => '#F5F7F6', 'card' => '#fff', 'text' => '#1C2B2A', 'muted' => '#5C6B68', 'accent' => '#1C2B2A'],
    'aetherra' => ['bg' => '#0B0B12', 'card' => '#151522', 'text' => '#F5F3FF', 'muted' => '#A5A3C2', 'accent' => '#8B5CF6'],
    'digitalgate' => ['bg' => '#0A0E17', 'card' => '#1E293B', 'text' => '#fff', 'muted' => '#94A3B8', 'accent' => '#3B82F6'],
];
$p = $palettes[$theme] ?? $palettes['digitalgate'];
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(sprintf('%s | %s', $portal_label, $site_label)); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Sora:wght@600&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; font-family: Inter, sans-serif; background: <?php echo esc_attr($p['bg']); ?>; color: <?php echo esc_attr($p['text']); ?>; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 3rem 1.25rem; }
        .card { background: <?php echo esc_attr($p['card']); ?>; border-radius: 20px; padding: 2rem; }
        h1 { font-family: Sora, sans-serif; margin: 0 0 0.5rem; font-size: 1.6rem; }
        p { color: <?php echo esc_attr($p['muted']); ?>; line-height: 1.6; }
        a { color: <?php echo esc_attr($p['accent']); ?>; font-weight: 600; text-decoration: none; }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    </style>
    <?php wp_head(); ?>
</head>
<body>
<div class="wrap">
    <div class="top">
        <strong><?php echo esc_html($site_label); ?></strong>
        <a href="<?php echo esc_url($logout_url); ?>"><?php esc_html_e('Sign out', 'dg-platform'); ?></a>
    </div>
    <div class="card">
        <h1><?php echo esc_html(sprintf(__('Welcome, %s', 'dg-platform'), $user_name)); ?></h1>
        <p><?php echo esc_html($message); ?></p>
    </div>
</div>
<?php wp_footer(); ?>
</body>
</html>
