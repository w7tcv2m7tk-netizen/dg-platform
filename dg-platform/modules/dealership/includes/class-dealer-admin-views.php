<?php
if (!defined('ABSPATH')) exit;

class DG_Dealer_Admin_Views {
    public static function render_dashboard() {
        if (!DG_Dealer_Permissions::can_view()) wp_die('Unauthorized');
        $summary = DG_Dealer_Reports::summary();
        $stages = DG_Dealer_Pipeline::stage_counts();
        $records = DG_Dealer_Pipeline::list(50);
        $vehicles = DG_Dealer_Inventory::list(12);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>🚗 Automotive</h1>
            <?php if (!empty($_GET['added'])) : ?><div class="notice notice-success"><p>Lead created.</p></div><?php endif; ?>
            <div class="dg-stats-grid dg-stats-grid-4">
                <div class="dg-stat-card" style="border-left-color:#3B82F6"><div class="dg-stat-value"><?php echo (int) $summary['vehicles']; ?></div><div class="dg-stat-label">Available stock</div></div>
                <div class="dg-stat-card" style="border-left-color:#F59E0B"><div class="dg-stat-value"><?php echo (int) $summary['leads']; ?></div><div class="dg-stat-label">Active leads</div></div>
                <div class="dg-stat-card" style="border-left-color:#059669"><div class="dg-stat-value"><?php echo (int) $summary['test_drives']; ?></div><div class="dg-stat-label">Test drives</div></div>
                <div class="dg-stat-card" style="border-left-color:#A78BFA"><div class="dg-stat-value"><?php echo (int) $summary['sold']; ?></div><div class="dg-stat-label">Sold</div></div>
            </div>
            <p style="margin:16px 0;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-dealer-add')); ?>" class="button button-primary">+ New lead</a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=dg_vehicle')); ?>" class="button">+ Add vehicle</a>
            </p>
            <div class="dg-panel"><h2>Inventory</h2>
                <?php if ($vehicles) : ?><ul><?php foreach ($vehicles as $v) : ?>
                    <li><a href="<?php echo esc_url(get_edit_post_link($v->ID)); ?>"><?php echo esc_html($v->post_title); ?></a>
                        — $<?php echo number_format((float) get_post_meta($v->ID, 'dg_vehicle_price', true)); ?>
                        (<?php echo esc_html(get_post_meta($v->ID, 'dg_vehicle_status', true) ?: 'available'); ?>)</li>
                <?php endforeach; ?></ul><?php else : ?><p style="color:#64748B;">No vehicles in stock.</p><?php endif; ?>
            </div>
            <div class="dg-panel"><h2>Leads pipeline</h2>
                <table class="wp-list-table widefat striped">
                    <thead><tr><th>Customer</th><th>Vehicle</th><th>Interest</th><th>Stage</th><th>Scheduled</th></tr></thead>
                    <tbody>
                    <?php if ($records) : foreach ($records as $r) : ?>
                        <tr>
                            <td><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?></td>
                            <td><?php echo esc_html($r->vehicle_name ?: '—'); ?></td>
                            <td><?php echo esc_html($r->interest_type); ?></td>
                            <td><?php echo esc_html(DG_Dealer_Pipeline::stages()[$r->stage] ?? $r->stage); ?></td>
                            <td><?php echo esc_html($r->scheduled_at ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; else : ?><tr><td colspan="5" style="text-align:center;padding:24px;color:#64748B;">No leads yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function render_add() {
        if (!DG_Dealer_Permissions::can_manage()) wp_die('Unauthorized');
        $vehicles = DG_Dealer_Inventory::list(100);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>New automotive lead</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dg-panel">
                <?php wp_nonce_field('dg_dealer_add_lead'); ?>
                <input type="hidden" name="action" value="dg_dealer_add_lead">
                <table class="form-table">
                    <tr><th>Name</th><td><input type="text" name="name" class="regular-text" required></td></tr>
                    <tr><th>Email</th><td><input type="email" name="email" class="regular-text" required></td></tr>
                    <tr><th>Phone</th><td><input type="text" name="phone" class="regular-text"></td></tr>
                    <tr><th>Vehicle</th><td><select name="vehicle_id"><option value="">— Any —</option><?php foreach ($vehicles as $v) : ?><option value="<?php echo (int) $v->ID; ?>"><?php echo esc_html($v->post_title); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th>Interest</th><td><select name="interest_type"><?php foreach (DG_Dealer_Pipeline::interest_types() as $t) : ?><option><?php echo esc_html($t); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th>Test drive / visit</th><td><input type="datetime-local" name="scheduled_at"></td></tr>
                    <tr><th>Notes</th><td><textarea name="notes" rows="3" class="large-text"></textarea></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Create lead</button></p>
            </form>
        </div>
        <?php
    }
}
