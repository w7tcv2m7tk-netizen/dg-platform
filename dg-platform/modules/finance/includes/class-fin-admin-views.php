<?php
/**
 * Finance admin views.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Fin_Admin_Views {

    public static function render_dashboard() {
        if (!DG_Fin_Permissions::can_view()) {
            wp_die('Unauthorized');
        }
        $summary = DG_Fin_Reports::summary();
        $stages = DG_Fin_Pipeline::stage_counts();
        $records = DG_Fin_Pipeline::list(['limit' => 50]);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>💰 Finance</h1>
            <?php if (!empty($_GET['added'])) : ?><div class="notice notice-success"><p>Application added.</p></div><?php endif; ?>
            <div class="dg-stats-grid dg-stats-grid-4">
                <div class="dg-stat-card" style="border-left-color:#059669"><div class="dg-stat-value"><?php echo (int) $summary['applications']; ?></div><div class="dg-stat-label">Active applications</div></div>
                <div class="dg-stat-card" style="border-left-color:#3B82F6"><div class="dg-stat-value">$<?php echo number_format($summary['pipeline_value']); ?></div><div class="dg-stat-label">Pipeline value</div></div>
                <div class="dg-stat-card" style="border-left-color:#F59E0B"><div class="dg-stat-value"><?php echo (int) $summary['approved']; ?></div><div class="dg-stat-label">Approved</div></div>
                <div class="dg-stat-card" style="border-left-color:#A78BFA"><div class="dg-stat-value"><?php echo (int) $summary['settled']; ?></div><div class="dg-stat-label">Settled</div></div>
            </div>
            <p style="margin:16px 0;"><a href="<?php echo esc_url(admin_url('admin.php?page=dg-fin-add')); ?>" class="button button-primary">+ New application</a></p>
            <div class="dg-panel">
                <h2>Pipeline</h2>
                <div class="dg-tags" style="margin-bottom:16px;">
                    <?php foreach ($stages as $key => $row) : ?>
                        <span class="dg-tag dg-tag-module"><?php echo esc_html($row['label']); ?>: <?php echo (int) $row['count']; ?></span>
                    <?php endforeach; ?>
                </div>
                <table class="wp-list-table widefat striped">
                    <thead><tr><th>Borrower</th><th>Loan type</th><th>Amount</th><th>Stage</th><th>Lender</th><th>Updated</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($records) : foreach ($records as $r) : ?>
                        <tr>
                            <td><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?><br><small><?php echo esc_html($r->email); ?></small></td>
                            <td><?php echo esc_html($r->loan_type); ?></td>
                            <td>$<?php echo number_format((float) $r->amount); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:6px;align-items:center;">
                                    <?php wp_nonce_field('dg_fin_update_stage_' . (int) $r->id); ?>
                                    <input type="hidden" name="action" value="dg_fin_update_stage">
                                    <input type="hidden" name="application_id" value="<?php echo (int) $r->id; ?>">
                                    <select name="stage">
                                        <?php foreach (DG_Fin_Pipeline::stages() as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($r->stage, $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="button button-small">Save</button>
                                </form>
                            </td>
                            <td><?php echo esc_html($r->lender ?: '—'); ?></td>
                            <td><?php echo esc_html($r->updated_at); ?></td>
                            <td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_fin_delete_application&id=' . (int) $r->id), 'dg_fin_delete_application_' . (int) $r->id)); ?>" onclick="return confirm('Delete this application?');" style="color:#C62828;">Delete</a></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="7" style="text-align:center;padding:24px;color:#64748B;">No applications yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function render_add() {
        if (!DG_Fin_Permissions::can_manage()) {
            wp_die('Unauthorized');
        }
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>New finance application</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dg-panel">
                <?php wp_nonce_field('dg_fin_add_application'); ?>
                <input type="hidden" name="action" value="dg_fin_add_application">
                <table class="form-table">
                    <tr><th>Name</th><td><input type="text" name="name" class="regular-text" required></td></tr>
                    <tr><th>Email</th><td><input type="email" name="email" class="regular-text" required></td></tr>
                    <tr><th>Phone</th><td><input type="text" name="phone" class="regular-text"></td></tr>
                    <tr><th>Loan type</th><td><select name="loan_type"><?php foreach (DG_Fin_Pipeline::loan_types() as $t) : ?><option><?php echo esc_html($t); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th>Amount ($)</th><td><input type="number" name="amount" step="1000" min="0" class="regular-text"></td></tr>
                    <tr><th>Lender</th><td><input type="text" name="lender" class="regular-text"></td></tr>
                    <tr><th>Notes</th><td><textarea name="notes" rows="4" class="large-text"></textarea></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Create application</button></p>
            </form>
        </div>
        <?php
    }
}
