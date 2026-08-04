<?php
if (!defined('ABSPATH')) {
    exit;
}
$edit = $edit_post ?? null;
$selected_platforms = $edit ? $edit->platforms : [];
?>
<div class="dg-panel dg-social-compose" style="margin-top:20px;">
    <h2><?php echo $edit ? 'Edit post' : 'New post'; ?></h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dg-social-compose-form">
        <?php wp_nonce_field('dg_social_pro_post'); ?>
        <input type="hidden" name="action" value="dg_save_social_pro_post">
        <input type="hidden" name="post_id" value="<?php echo $edit ? (int) $edit->id : 0; ?>">

        <div class="dg-social-compose-grid">
            <div class="dg-social-compose-main">
                <table class="form-table">
                    <tr>
                        <th><label for="dg_social_title">Title / Pin title</label></th>
                        <td><input type="text" id="dg_social_title" name="title" class="regular-text" value="<?php echo esc_attr($edit->title ?? ''); ?>" placeholder="Optional — used for Pinterest pins"></td>
                    </tr>
                    <tr>
                        <th><label for="dg_social_content">Post content</label></th>
                        <td>
                            <textarea id="dg_social_content" name="content" rows="8" class="large-text" placeholder="Write your post…"><?php echo esc_textarea($edit->content ?? ''); ?></textarea>
                            <p class="description">
                                <button type="button" class="button button-secondary dg-ai-btn" data-ai-task="social_compose" data-ai-topic="dg_social_content" data-ai-link="dg_social_link" data-ai-platforms="input[name='platforms[]']" data-ai-target="#dg_social_content" data-ai-target-title="dg_social_title">✨ Generate with AI</button>
                                <span class="dg-ai-status"></span>
                                <span id="dg-social-char-hint">Character limits vary by platform.</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="dg_social_link">Link URL</label></th>
                        <td>
                            <input type="url" id="dg_social_link" name="link_url" class="large-text" value="<?php echo esc_attr($edit->link_url ?? DG_Social_Pro_Settings::get('default_link', home_url('/'))); ?>" placeholder="<?php echo esc_attr(home_url('/')); ?>">
                            <p class="description">Appended to Facebook/LinkedIn/X posts. Required destination for Pinterest pins.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="dg_social_media">Image URL</label></th>
                        <td>
                            <div class="dg-social-media-row">
                                <input type="url" id="dg_social_media" name="media_url" class="large-text" value="<?php echo esc_attr($edit->media_url ?? ''); ?>" placeholder="https://…">
                                <button type="button" class="button" id="dg-social-media-picker">Media library</button>
                            </div>
                            <p class="description">Required for Instagram and Pinterest. Recommended 1200×630px for Facebook/LinkedIn.</p>
                            <?php if (!empty($edit->media_url)) : ?>
                                <img src="<?php echo esc_url($edit->media_url); ?>" alt="" class="dg-social-media-preview">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="dg_social_schedule">Schedule</label></th>
                        <td>
                            <input type="datetime-local" id="dg_social_schedule" name="scheduled_at" value="<?php echo $edit && $edit->scheduled_at ? esc_attr(date('Y-m-d\TH:i', strtotime($edit->scheduled_at))) : ''; ?>">
                            <p class="description">Leave blank to publish immediately. Scheduled posts run every 5 minutes.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <aside class="dg-social-compose-platforms">
                <h3>Publish to</h3>
                <?php foreach ($platforms as $key => $def) :
                    $connected = DG_Social_Pro_Settings::is_connected($key)
                        || ($key === 'instagram' && DG_Social_Pro_Settings::is_connected('facebook'));
                    $conn = $connections[$key] ?? ($key === 'instagram' ? ($connections['facebook'] ?? []) : []);
                    ?>
                    <label class="dg-social-platform-chip <?php echo $connected ? 'is-connected' : 'is-disconnected'; ?>" style="--platform-color:<?php echo esc_attr($def['color']); ?>">
                        <input type="checkbox" name="platforms[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_platforms, true)); ?> <?php disabled(!$connected); ?>>
                        <span class="dg-social-platform-icon"><?php echo esc_html($def['icon']); ?></span>
                        <span class="dg-social-platform-label"><?php echo esc_html($def['label']); ?></span>
                        <span class="dg-social-platform-status">
                            <?php
                            if ($connected) {
                                echo esc_html($conn['account_name'] ?? 'Connected');
                            } else {
                                echo 'Not connected';
                            }
                            ?>
                        </span>
                        <span class="dg-social-platform-limit"><?php echo (int) $def['max_chars']; ?> chars</span>
                    </label>
                <?php endforeach; ?>
                <p class="description"><a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-social-pro&tab=connections')); ?>">Connect platforms →</a></p>
            </aside>
        </div>

        <p class="dg-social-compose-actions">
            <button type="submit" name="post_action" value="publish" class="button button-primary button-hero">Publish now</button>
            <button type="submit" name="post_action" value="schedule" class="button">Schedule</button>
            <button type="submit" name="post_action" value="draft" class="button">Save draft</button>
        </p>
    </form>
</div>

<div class="dg-panel">
    <h3>Quick tips for CVH</h3>
    <ul class="dg-social-tips-list">
        <li><strong>Instagram / Pinterest:</strong> Use high-quality dome or rainforest photos from your media library.</li>
        <li><strong>Facebook:</strong> Great for availability updates, seasonal offers, and linking to booking pages.</li>
        <li><strong>LinkedIn:</strong> Share business updates, sustainability story, or local tourism partnerships.</li>
        <li><strong>X:</strong> Short updates with a link — max 280 characters.</li>
    </ul>
</div>
