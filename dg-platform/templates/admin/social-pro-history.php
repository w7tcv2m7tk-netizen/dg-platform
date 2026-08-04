<?php
if (!defined('ABSPATH')) {
    exit;
}
$status_labels = [
    'draft' => 'Draft',
    'scheduled' => 'Scheduled',
    'published' => 'Published',
    'partial' => 'Partial',
    'failed' => 'Failed',
    'publishing' => 'Publishing',
];
?>
<div class="dg-panel" style="margin-top:20px;">
    <h2>Post history</h2>

    <?php if (empty($posts)) : ?>
        <p>No posts yet. <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-social-pro&tab=compose')); ?>">Create your first post →</a></p>
    <?php else : ?>
        <table class="widefat striped dg-social-history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Content</th>
                    <th>Platforms</th>
                    <th>Status</th>
                    <th>Results</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post) : ?>
                    <tr>
                        <td>
                            <?php echo esc_html(date_i18n('j M Y, g:i a', strtotime($post->created_at))); ?>
                            <?php if ($post->scheduled_at) : ?>
                                <br><small>Scheduled: <?php echo esc_html(date_i18n('j M, g:i a', strtotime($post->scheduled_at))); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(wp_trim_words(strip_tags($post->content), 12, '…')); ?></td>
                        <td>
                            <?php
                            foreach ($post->platforms as $p) {
                                $def = $platforms[$p] ?? null;
                                echo $def ? esc_html($def['icon']) . ' ' : '';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="dg-social-status dg-social-status-<?php echo esc_attr($post->status); ?>">
                                <?php echo esc_html($status_labels[$post->status] ?? $post->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($post->results)) : ?>
                                <ul class="dg-social-results-list">
                                    <?php foreach ($post->results as $platform => $result) :
                                        if ($platform === '_error') {
                                            continue;
                                        }
                                        $def = $platforms[$platform] ?? ['label' => $platform];
                                        ?>
                                        <li class="<?php echo !empty($result['success']) ? 'is-ok' : 'is-fail'; ?>">
                                            <?php echo esc_html($def['label'] ?? $platform); ?>:
                                            <?php echo esc_html($result['message'] ?? ''); ?>
                                            <?php if (!empty($result['url'])) : ?>
                                                <a href="<?php echo esc_url($result['url']); ?>" target="_blank" rel="noopener">View</a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-social-pro&tab=compose&edit=' . (int) $post->id)); ?>" class="button button-small">Edit</a>
                            <?php if (in_array($post->status, ['draft', 'failed', 'partial'], true)) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('dg_publish_social_post'); ?>
                                    <input type="hidden" name="action" value="dg_publish_social_post">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post->id; ?>">
                                    <button type="submit" class="button button-small">Retry</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
