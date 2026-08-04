<?php
if (!defined('ABSPATH')) exit;

class DG_Svc_Admin_Views {
    public static function render_dashboard() {
        if (!DG_Svc_Permissions::can_view()) wp_die('Unauthorized');
        $summary = DG_Svc_Reports::summary();
        $stages = DG_Svc_Pipeline::stage_counts();
        $records = DG_Svc_Pipeline::list(50);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>🔧 Services</h1>
            <?php if (!empty($_GET['added'])) : ?><div class="notice notice-success"><p>Job created.</p></div><?php endif; ?>
            <div class="dg-stats-grid dg-stats-grid-4">
                <div class="dg-stat-card" style="border-left-color:#F59E0B"><div class="dg-stat-value"><?php echo (int) $summary['jobs']; ?></div><div class="dg-stat-label">Active jobs</div></div>
                <div class="dg-stat-card" style="border-left-color:#3B82F6"><div class="dg-stat-value"><?php echo (int) $summary['scheduled']; ?></div><div class="dg-stat-label">Scheduled</div></div>
                <div class="dg-stat-card" style="border-left-color:#059669"><div class="dg-stat-value">$<?php echo number_format($summary['quoted_value']); ?></div><div class="dg-stat-label">Quoted pipeline</div></div>
                <div class="dg-stat-card" style="border-left-color:#A78BFA"><div class="dg-stat-value"><?php echo (int) $summary['complete']; ?></div><div class="dg-stat-label">Completed</div></div>
            </div>
            <p style="margin:16px 0;"><a href="<?php echo esc_url(admin_url('admin.php?page=dg-svc-add')); ?>" class="button button-primary">+ New job</a></p>
            <div class="dg-panel">
                <h2>Jobs pipeline</h2>
                <div class="dg-tags" style="margin-bottom:16px;"><?php foreach ($stages as $row) : ?><span class="dg-tag dg-tag-module"><?php echo esc_html($row['label']); ?>: <?php echo (int) $row['count']; ?></span><?php endforeach; ?></div>
                <table class="wp-list-table widefat striped">
                    <thead><tr><th>Customer</th><th>Job</th><th>Type</th><th>Quote</th><th>Stage</th><th>Scheduled</th></tr></thead>
                    <tbody>
                    <?php if ($records) : foreach ($records as $r) : ?>
                        <tr>
                            <td><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?></td>
                            <td><?php echo esc_html($r->title); ?></td>
                            <td><?php echo esc_html($r->service_type); ?></td>
                            <td>$<?php echo number_format((float) $r->quoted_amount); ?></td>
                            <td><?php echo esc_html(DG_Svc_Pipeline::stages()[$r->stage] ?? $r->stage); ?></td>
                            <td><?php echo esc_html($r->scheduled_at ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; else : ?><tr><td colspan="6" style="text-align:center;padding:24px;color:#64748B;">No jobs yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function render_add() {
        if (!DG_Svc_Permissions::can_manage()) wp_die('Unauthorized');
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>New service job</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dg-panel">
                <?php wp_nonce_field('dg_svc_add_job'); ?>
                <input type="hidden" name="action" value="dg_svc_add_job">
                <table class="form-table">
                    <tr><th>Customer name</th><td><input type="text" name="name" class="regular-text" required></td></tr>
                    <tr><th>Email</th><td><input type="email" name="email" class="regular-text" required></td></tr>
                    <tr><th>Phone</th><td><input type="text" name="phone" class="regular-text"></td></tr>
                    <tr><th>Job title</th><td><input type="text" name="title" class="large-text" required></td></tr>
                    <tr><th>Service type</th><td><input type="text" name="service_type" class="regular-text" placeholder="Plumbing, Electrical, etc."></td></tr>
                    <tr><th>Quote ($)</th><td><input type="number" name="quoted_amount" step="0.01" min="0"></td></tr>
                    <tr><th>Address</th><td><input type="text" name="address" class="large-text"></td></tr>
                    <tr><th>Scheduled</th><td><input type="datetime-local" name="scheduled_at"></td></tr>
                    <tr><th>Notes</th><td><textarea name="notes" rows="3" class="large-text"></textarea></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Create job</button></p>
            </form>
        </div>
        <?php
    }
}
