<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>📈 Reports</h1>
    <div class="dg-stats-grid">
        <?php foreach ($stats as $label => $value) : ?>
            <div class="dg-stat-card">
                <div class="dg-stat-value"><?php echo number_format($value); ?></div>
                <div class="dg-stat-label"><?php echo esc_html(ucwords(str_replace('_', ' ', $label))); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="dg-panel">
        <h3>Integration Status</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Integration</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($integrations as $name => $connected) : ?>
                    <tr>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $name))); ?></td>
                        <td><?php echo $connected ? '✅ Connected' : '⚪ Not configured'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
