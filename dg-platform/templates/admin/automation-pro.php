<?php
if (!defined('ABSPATH')) {
    exit;
}
$tab = $tab ?? 'dashboard';
?>
<div class="wrap dg-platform-wrap">
    <h1>⚡ Automation Pro</h1>

    <?php if (!empty($_GET['saved'])) : ?><div class="notice notice-success"><p>Workflow saved.</p></div><?php endif; ?>
    <?php if (!empty($_GET['installed'])) : ?><div class="notice notice-success"><p>Template installed.</p></div><?php endif; ?>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-automation-pro')); ?>" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-automation-pro&tab=templates')); ?>" class="nav-tab <?php echo $tab === 'templates' ? 'nav-tab-active' : ''; ?>">Templates</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-automations')); ?>" class="nav-tab">Basic automations</a>
    </nav>

    <?php if ($tab === 'templates') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>Workflow templates</h2>
            <p style="color:#666;">One-click install for your site's industry module.</p>
            <table class="widefat striped">
                <thead><tr><th>Template</th><th>Trigger</th><th>Steps</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($templates as $key => $tpl) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($tpl['label']); ?></strong><br><small><?php echo esc_html($tpl['module']); ?></small></td>
                        <td><code><?php echo esc_html($tpl['trigger_type']); ?></code></td>
                        <td><?php echo count($tpl['steps']); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('dg_install_automation_template'); ?>
                                <input type="hidden" name="action" value="dg_install_automation_template">
                                <input type="hidden" name="template" value="<?php echo esc_attr($key); ?>">
                                <button type="submit" class="button button-primary button-small">Install</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="dg-stats-grid dg-stats-grid-4" style="margin-top:20px;">
            <div class="dg-panel"><div class="dg-stat-value"><?php echo (int) $stats['completed']; ?></div><div class="dg-stat-label">Steps completed (30d)</div></div>
            <div class="dg-panel"><div class="dg-stat-value"><?php echo (int) $stats['failed']; ?></div><div class="dg-stat-label">Failed (30d)</div></div>
            <div class="dg-panel"><div class="dg-stat-value"><?php echo (int) $queue; ?></div><div class="dg-stat-label">Queued delays</div></div>
            <div class="dg-panel"><div class="dg-stat-value"><?php echo count($automations); ?></div><div class="dg-stat-label">Workflows</div></div>
        </div>

        <div class="dg-panel">
            <h2>Active workflows</h2>
            <p>
                <button type="button" class="button button-secondary dg-ai-btn" data-ai-task="automation_suggest" data-ai-trigger="new_lead" data-ai-goal="nurture and convert leads" data-ai-modal="1" data-ai-modal-title="Suggested workflow">✨ Suggest workflow with AI</button>
                <span class="dg-ai-status"></span>
            </p>
            <table class="widefat striped">
                <thead><tr><th>Name</th><th>Module</th><th>Trigger</th><th>Steps</th><th>Status</th></tr></thead>
                <tbody>
                <?php if ($automations) : foreach ($automations as $auto) :
                    $steps = json_decode($auto->steps, true) ?: [];
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($auto->name); ?></strong></td>
                        <td><?php echo esc_html($auto->module); ?></td>
                        <td><code><?php echo esc_html($auto->trigger_type); ?></code></td>
                        <td><?php echo count($steps); ?>
                            <?php if ($steps) : ?><br><small><?php echo esc_html(implode(' → ', array_column($steps, 'action'))); ?></small><?php endif; ?>
                        </td>
                        <td><?php echo $auto->is_active ? 'Active' : 'Inactive'; ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="5" style="text-align:center;padding:24px;color:#64748B;">No workflows yet. Install a template to get started.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="dg-panel">
            <h3>Available triggers</h3>
            <ul style="columns:2;gap:24px;color:#64748B;">
                <?php foreach ($triggers as $key => $label) : ?>
                    <li><code><?php echo esc_html($key); ?></code> — <?php echo esc_html($label); ?></li>
                <?php endforeach; ?>
            </ul>
            <h3>Pro actions</h3>
            <p style="color:#64748B;"><?php echo esc_html(implode(', ', $actions)); ?></p>
        </div>
    <?php endif; ?>
</div>
