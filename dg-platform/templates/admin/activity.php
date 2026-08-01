<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>🕐 Activity Timeline</h1>
    <table class="wp-list-table widefat fixed striped">
        <thead><tr><th>Type</th><th>Subject</th><th>Content</th><th>Date</th></tr></thead>
        <tbody>
            <?php if ($activities) : foreach ($activities as $activity) : ?>
                <tr>
                    <td><?php echo esc_html($activity->activity_type); ?></td>
                    <td><?php echo esc_html($activity->subject); ?></td>
                    <td><?php echo esc_html(wp_trim_words($activity->content, 15)); ?></td>
                    <td><?php echo esc_html($activity->created_at); ?></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="4">No activity yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
