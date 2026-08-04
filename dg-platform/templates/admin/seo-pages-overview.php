<?php
/**
 * SEO Pro — All Pages overview (stored meta from database).
 *
 * @var array<int,array{post:WP_Post,depth:int,score:int|null}> $overview_pages
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

$prefix = DG_SEO_Settings::META_PREFIX;
?>
<div id="dg-seo-pages-overview" class="dg-seo-pages-overview">
    <div class="dg-panel" style="margin-top:20px;">
        <h2>All Pages</h2>
        <p class="description">
            Shows <strong>saved</strong> SEO values from the database (same fields as <em>Pages → All Pages</em>).
            Edits auto-save when you tab out or change Robots. A green border means saved.
            <span id="dg-seo-inline-status" class="dg-seo-inline-status" aria-live="polite"></span>
        </p>
        <?php if (!empty($overview_pages) && class_exists('DG_SEO_IndexNow')) : ?>
            <p class="dg-seo-indexnow-toolbar">
                <button type="button" class="button button-secondary dg-seo-indexnow-btn" data-bulk="all">
                    Index all indexable pages
                </button>
                <?php if (DG_SEO_IndexNow::auto_enabled()) : ?>
                    <span class="description">Auto-index is on — published pages are submitted to IndexNow when updated.</span>
                <?php else : ?>
                    <span class="description">Auto-index is off — use <strong>Index Now</strong> per page or enable it in Global Settings.</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if (empty($overview_pages)) : ?>
            <p>No pages found.</p>
        <?php else : ?>
            <table class="widefat striped dg-seo-pages-table">
                <thead>
                    <tr>
                        <th style="width:18%;">Page</th>
                        <th style="width:14%;">Keyword</th>
                        <th style="width:18%;">SEO Title</th>
                        <th style="width:22%;">SEO Description</th>
                        <th style="width:12%;">Robots</th>
                        <th style="width:10%;">Index</th>
                        <th style="width:6%;">Score</th>
                        <th style="width:10%;">Audit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overview_pages as $row) :
                        $post = $row['post'];
                        $depth = (int) $row['depth'];
                        $post_id = (int) $post->ID;
                        $stored = DG_SEO_Settings::get_post_seo_stored($post_id);
                        $score = $row['score'];
                        $pad = str_repeat('— ', max(0, $depth));
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_html($pad . $post->post_title); ?>
                                <?php if ($post->post_status !== 'publish') : ?>
                                    <span class="dg-muted" style="font-size:11px;">(<?php echo esc_html($post->post_status); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="text" class="dg-seo-inline" data-post-id="<?php echo $post_id; ?>" data-field="focus_keyword"
                                       value="<?php echo esc_attr($stored['focus_keyword']); ?>" placeholder="Focus keyword"
                                       style="width:100%;font-size:12px;">
                            </td>
                            <td>
                                <input type="text" class="dg-seo-inline" data-post-id="<?php echo $post_id; ?>" data-field="title"
                                       value="<?php echo esc_attr($stored['title']); ?>" placeholder="SEO title"
                                       style="width:100%;font-size:12px;">
                            </td>
                            <td>
                                <textarea class="dg-seo-inline" data-post-id="<?php echo $post_id; ?>" data-field="description" rows="2"
                                          placeholder="Meta description" style="width:100%;font-size:12px;"><?php echo esc_textarea($stored['description']); ?></textarea>
                            </td>
                            <td>
                                <select class="dg-seo-inline dg-seo-inline-robots" data-post-id="<?php echo $post_id; ?>" data-field="robots" style="width:100%;font-size:12px;">
                                    <?php foreach (DG_SEO_Settings::robots_options() as $opt_value => $label) : ?>
                                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($stored['robots'], $opt_value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php
                                if (class_exists('DG_SEO_IndexNow')) {
                                    DG_SEO_IndexNow::render_index_cell($post_id);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo $score !== null ? (int) $score : '—'; ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-seo&tab=audit&post_id=' . $post_id)); ?>" class="button button-small">Audit</a>
                                <?php if (class_exists('DG_AI_Assist') && DG_AI_Assist::available()) : ?>
                                    <button type="button" class="button button-small dg-ai-btn" data-ai-task="seo_optimize" data-ai-post-id="<?php echo $post_id; ?>" data-ai-modal="1" data-ai-modal-title="AI SEO">✨</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
