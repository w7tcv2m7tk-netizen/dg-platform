<?php
if (!defined('ABSPATH')) {
    exit;
}
$tab = $tab ?? 'compose';
?>
<div class="wrap dg-platform-wrap dg-social-pro-wrap">
    <h1>📱 Social Pro</h1>
    <p class="description">Create and publish posts to Facebook, Instagram, LinkedIn, X, and Pinterest — all from one place.</p>

    <?php if (!empty($_GET['saved'])) : ?>
        <div class="notice notice-success"><p>Saved.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['connected'])) : ?>
        <div class="notice notice-success"><p><?php echo esc_html(ucfirst(sanitize_key($_GET['connected']))); ?> connected successfully.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['disconnected'])) : ?>
        <div class="notice notice-success"><p>Platform disconnected.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['oauth_error'])) : ?>
        <div class="notice notice-error"><p>OAuth failed: <?php echo esc_html(urldecode(sanitize_text_field(wp_unslash($_GET['msg'] ?? 'Unknown error')))); ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-social-pro&tab=compose')); ?>" class="nav-tab <?php echo $tab === 'compose' ? 'nav-tab-active' : ''; ?>">Compose</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-social-pro&tab=history')); ?>" class="nav-tab <?php echo $tab === 'history' ? 'nav-tab-active' : ''; ?>">History</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-social-pro&tab=connections')); ?>" class="nav-tab <?php echo $tab === 'connections' ? 'nav-tab-active' : ''; ?>">Connections</a>
    </nav>

    <?php if ($tab === 'connections') : ?>
        <?php include DG_PLATFORM_PATH . 'templates/admin/social-pro-connections.php'; ?>
    <?php elseif ($tab === 'history') : ?>
        <?php include DG_PLATFORM_PATH . 'templates/admin/social-pro-history.php'; ?>
    <?php else : ?>
        <?php include DG_PLATFORM_PATH . 'templates/admin/social-pro-compose.php'; ?>
    <?php endif; ?>
</div>
