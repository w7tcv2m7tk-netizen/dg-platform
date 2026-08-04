<?php
if (!defined('ABSPATH')) exit;
$automations = DG_Automation::list();
?>
<div class="wrap dg-platform-wrap">
    <h1>⚡ Automations</h1>
    <?php if (!empty($_GET['saved'])) : ?>
        <div class="notice notice-success is-dismissible"><p>Automation saved.</p></div>
    <?php endif; ?>
    <p style="color:#666;">Active automations run when their trigger fires. Delayed sequences (e.g. property report follow-ups) run on daily cron.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Module</th>
                <th>Trigger</th>
                <th>Steps</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($automations) : foreach ($automations as $auto) :
                $steps = json_decode($auto->steps, true) ?: [];
                $settings = json_decode($auto->trigger_settings, true) ?: [];
                ?>
                <tr>
                    <td><?php echo (int) $auto->id; ?></td>
                    <td><strong><?php echo esc_html($auto->name); ?></strong></td>
                    <td><?php echo esc_html($auto->module); ?></td>
                    <td><code><?php echo esc_html($auto->trigger_type); ?></code></td>
                    <td><?php echo count($steps); ?> step(s)
                        <?php if (!empty($settings['delays_days'])) : ?>
                            <br><small>Delays: <?php echo esc_html(implode(', ', $settings['delays_days'])); ?> days</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $auto->is_active ? '<span style="color:#2E7D32;">Active</span>' : '<span style="color:#999;">Inactive</span>'; ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                            <?php wp_nonce_field('dg_toggle_automation'); ?>
                            <input type="hidden" name="action" value="dg_toggle_automation">
                            <input type="hidden" name="automation_id" value="<?php echo (int) $auto->id; ?>">
                            <input type="hidden" name="is_active" value="<?php echo $auto->is_active ? '0' : '1'; ?>">
                            <button type="submit" class="button button-small"><?php echo $auto->is_active ? 'Deactivate' : 'Activate'; ?></button>
                        </form>
                        <?php if (DG_Permissions::current_user_can('dg_manage_automations')) : ?>
                            <?php echo DG_Admin_Delete::link('dg_delete_automation', (int) $auto->id); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="7" style="text-align:center;padding:24px;color:#999;">No automations yet. Property report follow-ups are seeded when the first vendor lead is created.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="dg-panel" style="margin-top:24px;">
        <h3>Create automation</h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('dg_save_automation'); ?>
            <input type="hidden" name="action" value="dg_save_automation">
            <table class="form-table">
                <tr><th>Name</th><td><input type="text" name="name" class="regular-text" required></td></tr>
                <tr><th>Module</th><td><input type="text" name="module" value="real-estate" class="regular-text"></td></tr>
                <tr><th>Trigger type</th><td><input type="text" name="trigger_type" class="regular-text" placeholder="vendor_lead_created" required></td></tr>
                <tr><th>Active</th><td><label><input type="checkbox" name="is_active" value="1" checked> Run when triggered</label></td></tr>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Create automation</button></p>
        </form>
    </div>
</div>
