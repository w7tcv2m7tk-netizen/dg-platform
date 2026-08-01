<?php
/**
 * Accommodation admin dashboard (DG Platform integration).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Admin_Views {

    public static function render_dashboard() {
        if (!DG_Acc_Permissions::can_view_bookings()) {
            wp_die('Unauthorized');
        }

        $summary = DG_Acc_Reports::summary();
        $status = $summary['status_counts'];
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>🏨 Accommodation</h1>
            <p class="dg-muted-subtle">Properties, bookings, guests, OTA sync, and payments for Currumbin Valley Hideaway.</p>

            <div class="dg-stats-grid dg-stats-grid-4">
                <?php self::stat_card('Properties', $summary['properties'], '#3B82F6'); ?>
                <?php self::stat_card('Guests', $summary['guests'], '#8B5CF6'); ?>
                <?php self::stat_card('Upcoming (30d)', $summary['upcoming_30d'], '#34D399'); ?>
                <?php self::stat_card('Revenue (month)', '$' . number_format($summary['revenue_month'], 0), '#F59E0B'); ?>
            </div>

            <div class="dg-two-col-grid">
                <div class="dg-panel" style="margin-top:0;">
                    <h2>Quick actions</h2>
                    <p class="dg-actions">
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=dg_accommodation')); ?>">All properties</a>
                        <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=dg_booking')); ?>">All bookings</a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dg-admin-calendar')); ?>">📅 Calendar</a>
                        <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=dg_guest')); ?>">👥 Guests</a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dg-force-sync-all')); ?>">🔄 Sync OTA</a>
                    </p>
                </div>
                <div class="dg-panel" style="margin-top:0;">
                    <h2>Booking status</h2>
                    <?php if ($status) : ?>
                        <table class="widefat striped"><tbody>
                        <?php foreach ($status as $key => $count) : ?>
                            <tr><td><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></td><td><?php echo (int) $count; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    <?php else : ?>
                        <p class="dg-muted">No bookings yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function stat_card($label, $value, $color) {
        echo '<div class="dg-stat-card" style="border-left-color:' . esc_attr($color) . ';">';
        echo '<div class="dg-stat-value">' . esc_html((string) $value) . '</div>';
        echo '<div class="dg-stat-label">' . esc_html($label) . '</div></div>';
    }
}
