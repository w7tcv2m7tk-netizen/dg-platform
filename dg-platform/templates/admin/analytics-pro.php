<?php
if (!defined('ABSPATH')) {
    exit;
}
$tab = $tab ?? 'dashboard';
?>
<div class="wrap dg-platform-wrap">
    <h1>📊 Analytics Pro</h1>

    <?php if (!empty($_GET['saved'])) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>
    <?php if (!empty($_GET['snapshot'])) : ?><div class="notice notice-success"><p>Snapshot recorded.</p></div><?php endif; ?>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-analytics-pro')); ?>" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-analytics-pro&tab=settings')); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-reports')); ?>" class="nav-tab">Basic reports</a>
    </nav>

    <?php if ($tab === 'settings') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_analytics_pro_settings'); ?>
                <input type="hidden" name="action" value="dg_save_analytics_pro_settings">
                <table class="form-table">
                    <tr>
                        <th>Daily snapshots</th>
                        <td><label><input type="checkbox" name="daily_snapshots" value="1" <?php checked($settings['daily_snapshots']); ?>> Record KPIs daily for trend charts</label></td>
                    </tr>
                    <tr>
                        <th>Weekly email report</th>
                        <td><label><input type="checkbox" name="weekly_email" value="1" <?php checked($settings['weekly_email']); ?>> Email weekly summary</label></td>
                    </tr>
                    <tr>
                        <th>Report recipient</th>
                        <td><input type="email" name="email_recipient" value="<?php echo esc_attr($settings['email_recipient']); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Save Settings</button></p>
            </form>
        </div>
    <?php else : ?>
        <div style="margin:16px 0;display:flex;gap:12px;flex-wrap:wrap;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_analytics_pro_snapshot'); ?>
                <input type="hidden" name="action" value="dg_analytics_pro_snapshot">
                <button type="submit" class="button button-primary">Snapshot now</button>
            </form>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_analytics_pro_export'), 'dg_analytics_pro_export')); ?>" class="button">Export CSV</a>
        </div>

        <div class="dg-stats-grid" style="margin-top:8px;">
            <?php foreach ($metrics as $key => $row) :
                $trend = $trends[$key] ?? null;
                $delta = $trend ? $trend['delta'] : 0;
                ?>
                <div class="dg-panel">
                    <div class="dg-stat-value"><?php echo is_float($row['value']) && floor($row['value']) != $row['value'] ? number_format($row['value'], 1) : number_format($row['value']); ?></div>
                    <div class="dg-stat-label"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></div>
                    <?php if ($trend) : ?>
                        <small style="color:<?php echo $delta >= 0 ? '#059669' : '#DC2626'; ?>;">
                            <?php echo $delta >= 0 ? '▲' : '▼'; ?> <?php echo esc_html(round(abs($delta), 1)); ?> vs 30d
                        </small>
                    <?php endif; ?>
                    <br><small style="color:#94A3B8;"><?php echo esc_html($row['module']); ?></small>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($trends) : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>30-day trends</h2>
            <table class="widefat striped">
                <thead><tr><th>Metric</th><th>Current</th><th>30d ago</th><th>Change</th><th>Module</th></tr></thead>
                <tbody>
                <?php foreach ($trends as $key => $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['label']); ?></td>
                        <td><?php echo esc_html($row['current']); ?></td>
                        <td><?php echo esc_html($row['previous']); ?></td>
                        <td style="color:<?php echo $row['delta'] >= 0 ? '#059669' : '#DC2626'; ?>;"><?php echo ($row['delta'] >= 0 ? '+' : '') . esc_html(round($row['delta'], 1)); ?></td>
                        <td><?php echo esc_html($row['module']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else : ?>
        <div class="dg-panel"><p style="color:#64748B;">Run a snapshot to start building trend data.</p></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
