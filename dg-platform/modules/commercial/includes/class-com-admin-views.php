<?php
if (!defined('ABSPATH')) exit;

class DG_Com_Admin_Views {
    public static function render_dashboard() {
        if (!DG_Com_Permissions::can_view()) wp_die('Unauthorized');
        $summary = DG_Com_Reports::summary();
        $stages = DG_Com_Pipeline::stage_counts();
        $records = DG_Com_Pipeline::list(50);
        $listings = DG_Com_Listings::list(12);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>🏢 Commercial</h1>
            <?php if (!empty($_GET['added'])) : ?><div class="notice notice-success"><p>Tenancy lead created.</p></div><?php endif; ?>
            <div class="dg-stats-grid dg-stats-grid-4">
                <div class="dg-stat-card" style="border-left-color:#3B82F6"><div class="dg-stat-value"><?php echo (int) $summary['listings']; ?></div><div class="dg-stat-label">Active listings</div></div>
                <div class="dg-stat-card" style="border-left-color:#F59E0B"><div class="dg-stat-value"><?php echo (int) $summary['tenancies']; ?></div><div class="dg-stat-label">Tenancy leads</div></div>
                <div class="dg-stat-card" style="border-left-color:#059669"><div class="dg-stat-value"><?php echo (int) $summary['active_leases']; ?></div><div class="dg-stat-label">Active leases</div></div>
                <div class="dg-stat-card" style="border-left-color:#A78BFA"><div class="dg-stat-value">$<?php echo number_format($summary['rent_roll']); ?></div><div class="dg-stat-label">Rent roll / month</div></div>
            </div>
            <p style="margin:16px 0;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-com-add')); ?>" class="button button-primary">+ Tenancy lead</a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=dg_commercial')); ?>" class="button">+ Add listing</a>
            </p>
            <div class="dg-panel"><h2>Listings</h2>
                <?php if ($listings) : ?><ul><?php foreach ($listings as $l) : ?>
                    <li><a href="<?php echo esc_url(get_edit_post_link($l->ID)); ?>"><?php echo esc_html($l->post_title); ?></a>
                        — <?php echo esc_html(get_post_meta($l->ID, 'dg_com_type', true)); ?>,
                        <?php echo (int) get_post_meta($l->ID, 'dg_com_sqm', true); ?> sqm,
                        $<?php echo number_format((float) get_post_meta($l->ID, 'dg_com_rent_pcm', true)); ?>/mo</li>
                <?php endforeach; ?></ul><?php else : ?><p style="color:#64748B;">No listings yet.</p><?php endif; ?>
            </div>
            <div class="dg-panel"><h2>Tenancy pipeline</h2>
                <table class="wp-list-table widefat striped">
                    <thead><tr><th>Contact</th><th>Business</th><th>Listing</th><th>Rent/mo</th><th>Stage</th></tr></thead>
                    <tbody>
                    <?php if ($records) : foreach ($records as $r) : ?>
                        <tr>
                            <td><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?></td>
                            <td><?php echo esc_html($r->business_name ?: '—'); ?></td>
                            <td><?php echo esc_html($r->listing_name ?: '—'); ?></td>
                            <td>$<?php echo number_format((float) $r->rent_pcm); ?></td>
                            <td><?php echo esc_html(DG_Com_Pipeline::stages()[$r->stage] ?? $r->stage); ?></td>
                        </tr>
                    <?php endforeach; else : ?><tr><td colspan="5" style="text-align:center;padding:24px;color:#64748B;">No tenancy leads yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function render_add() {
        if (!DG_Com_Permissions::can_manage()) wp_die('Unauthorized');
        $listings = DG_Com_Listings::list(100);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>New tenancy lead</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dg-panel">
                <?php wp_nonce_field('dg_com_add_tenancy'); ?>
                <input type="hidden" name="action" value="dg_com_add_tenancy">
                <table class="form-table">
                    <tr><th>Contact name</th><td><input type="text" name="name" class="regular-text" required></td></tr>
                    <tr><th>Email</th><td><input type="email" name="email" class="regular-text" required></td></tr>
                    <tr><th>Business name</th><td><input type="text" name="business_name" class="regular-text"></td></tr>
                    <tr><th>Listing</th><td><select name="listing_id"><option value="">— Any —</option><?php foreach ($listings as $l) : ?><option value="<?php echo (int) $l->ID; ?>"><?php echo esc_html($l->post_title); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th>Rent ($/month)</th><td><input type="number" name="rent_pcm" step="0.01" min="0"></td></tr>
                    <tr><th>Area (sqm)</th><td><input type="number" name="sqm" step="0.1" min="0"></td></tr>
                    <tr><th>Lease start</th><td><input type="date" name="lease_start"></td></tr>
                    <tr><th>Lease end</th><td><input type="date" name="lease_end"></td></tr>
                    <tr><th>Notes</th><td><textarea name="notes" rows="3" class="large-text"></textarea></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Create lead</button></p>
            </form>
        </div>
        <?php
    }
}
