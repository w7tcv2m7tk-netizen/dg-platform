<?php
/**
 * Creator admin dashboard.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Creator_Admin_Views {

    public static function render_dashboard() {
        if (!DG_Creator_Permissions::can_view()) {
            wp_die('Unauthorized');
        }

        $summary = class_exists('DG_Creator_Reports') ? DG_Creator_Reports::summary() : [];
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>✨ Creator</h1>
            <p class="dg-muted-subtle">Content, projects, and audience for <?php echo esc_html(class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : 'Aetherra'); ?>.</p>

            <div class="dg-stats-grid dg-stats-grid-4">
                <?php self::stat_card('Published posts', $summary['published_posts'] ?? 0, '#A78BFA'); ?>
                <?php self::stat_card('Drafts', $summary['draft_posts'] ?? 0, '#F59E0B'); ?>
                <?php self::stat_card('Pages', $summary['pages'] ?? 0, '#34D399'); ?>
                <?php self::stat_card('Contacts', $summary['contacts'] ?? 0, '#3B82F6'); ?>
            </div>

            <div class="dg-panel" style="margin-top:1.5rem;">
                <h2>Quick actions</h2>
                <p class="dg-actions">
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php')); ?>">New post</a>
                    <a class="button" href="<?php echo esc_url(admin_url('edit.php')); ?>">All posts</a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-contacts')); ?>">Contacts</a>
                </p>
                <p class="dg-muted">Creator CRM features (projects, newsletter, collaborations) will expand here.</p>
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
