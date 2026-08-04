<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>⭐ Reviews</h1>
    <p style="color:#64748B;">Import and manage customer reviews from Google Business Profile, Airbnb, Booking.com, REA, Domain, and other platforms.</p>

    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Review saved.</p></div><?php endif; ?>
    <?php if (isset($_GET['deleted'])) : ?><div class="notice notice-success"><p>Review deleted.</p></div><?php endif; ?>
    <?php if (isset($_GET['imported'])) : ?><div class="notice notice-success"><p><?php echo (int) $_GET['imported']; ?> reviews imported from CSV.</p></div><?php endif; ?>
    <?php if (isset($_GET['trustindex_imported'])) : ?><div class="notice notice-success"><p><?php echo (int) $_GET['trustindex_imported']; ?> Airbnb reviews imported from TrustIndex<?php if (!empty($_GET['trustindex_skipped'])) : ?> (<?php echo (int) $_GET['trustindex_skipped']; ?> skipped as duplicates<?php endif; ?>).</p></div><?php endif; ?>

    <div class="dg-panel" style="margin-bottom:1.5rem;">
        <h2>Platform summary</h2>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($platforms as $key => $label) :
                $count = $counts[$key] ?? 0;
                ?>
                <span style="background:#f5f2ef;padding:6px 12px;border-radius:20px;font-size:13px;"><?php echo esc_html($label); ?>: <strong><?php echo (int) $count; ?></strong></span>
            <?php endforeach; ?>
        </div>
        <p class="description" style="margin-top:12px;">Import reviews manually, via CSV, or from the legacy TrustIndex table. Display on the stay page with <code>[dg_airbnb_reviews accommodation_id="123"]</code> or <code>[dg_airbnb_reviews airbnb_id="1654775429707386391"]</code>.</p>
        <?php if (!empty($trustindex_available)) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('dg_import_trustindex_reviews'); ?>
                <input type="hidden" name="action" value="dg_import_trustindex_reviews">
                <label style="display:block;margin-bottom:6px;font-weight:600;">Import legacy TrustIndex Airbnb reviews</label>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="text" name="listing_id" class="regular-text" placeholder="Airbnb listing ID (optional)">
                    <button type="submit" class="button button-primary">Import from TrustIndex</button>
                </div>
                <p class="description">Imports from <code><?php echo esc_html($GLOBALS['wpdb']->prefix); ?>trustindex_airbnb_reviews</code>. Leave listing ID blank to import all, or enter Private Studio / Tiny Home listing ID to tag them.</p>
            </form>
        <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
        <div class="dg-panel">
            <h2>Add review</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_save_review'); ?>
                <input type="hidden" name="action" value="dg_save_review">
                <table class="form-table">
                    <tr><th>Platform</th><td><select name="platform" class="regular-text"><?php foreach ($platforms as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th>Author</th><td><input type="text" name="author_name" class="regular-text" required></td></tr>
                    <tr><th>Rating</th><td><input type="number" name="rating" min="1" max="5" step="0.5" value="5" style="width:80px;"></td></tr>
                    <tr><th>Title</th><td><input type="text" name="title" class="regular-text"></td></tr>
                    <tr><th>Review</th><td><textarea name="content" rows="4" class="large-text" required></textarea></td></tr>
                    <tr><th>Date</th><td><input type="date" name="review_date"></td></tr>
                    <tr><th>Source URL</th><td><input type="url" name="source_url" class="large-text" placeholder="https://..."></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Save review</button></p>
            </form>
        </div>
        <div class="dg-panel">
            <h2>Import CSV</h2>
            <p class="description">Columns: <code>platform, author_name, rating, title, content, review_date, source_url, external_id</code></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('dg_import_reviews_csv'); ?>
                <input type="hidden" name="action" value="dg_import_reviews_csv">
                <p><input type="file" name="csv_file" accept=".csv,text/csv" required></p>
                <p><button type="submit" class="button">Import CSV</button></p>
            </form>
            <hr>
            <h3>Frontend shortcode</h3>
            <p><code>[dg_reviews limit="6" min_rating="4"]</code></p>
            <p class="description">Optional: <code>platform="google_business"</code> · <code>columns="3"</code></p>
        </div>
    </div>

    <div class="dg-panel">
        <h2>All reviews</h2>
        <table class="wp-list-table widefat striped">
            <thead><tr><th>Platform</th><th>Author</th><th>Rating</th><th>Review</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php if (!$reviews) : ?>
                <tr><td colspan="6">No reviews yet. Add one manually or import a CSV.</td></tr>
            <?php else : foreach ($reviews as $review) : ?>
                <tr>
                    <td><?php echo esc_html($platforms[$review->platform] ?? $review->platform); ?></td>
                    <td><?php echo esc_html($review->author_name); ?></td>
                    <td><?php echo esc_html($review->rating); ?>/5</td>
                    <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags($review->content), 18)); ?></td>
                    <td><?php echo esc_html($review->review_date ?: '—'); ?></td>
                    <td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_delete_review&id=' . (int) $review->id), 'dg_delete_review')); ?>">Delete</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
