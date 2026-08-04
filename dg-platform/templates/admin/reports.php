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
    <?php
    $integration_rows = class_exists('DG_Integrations') ? DG_Integrations::get_hub_rows() : [];
    include DG_PLATFORM_PATH . 'templates/admin/integrations-panel.php';
    ?>
</div>
