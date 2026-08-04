<?php
/**
 * SEO Pro post editor meta box (Rank Math-style).
 *
 * @var WP_Post $post
 * @var string  $prefix
 * @var array   $values
 * @var string  $permalink
 * @var string  $site_name
 * @var string  $fallback_title
 * @var string  $fallback_description
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="dg-seo-meta-box" class="dg-seo-meta-box">
    <?php if ($audit_score !== null && $audit_grade) : ?>
        <div class="dg-seo-score-badge" style="--score-color:<?php echo esc_attr($audit_grade['color']); ?>">
            <span class="dg-seo-score-num"><?php echo (int) $audit_score; ?></span>
            <span class="dg-seo-score-text"><?php echo esc_html($audit_grade['label']); ?></span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-seo&tab=audit&post_id=' . (int) $post->ID)); ?>" class="dg-seo-score-link">Full audit →</a>
        </div>
    <?php endif; ?>
    <div class="dg-seo-preview">
        <div class="dg-seo-preview-label"><?php esc_html_e('Google preview', 'dg-platform'); ?></div>
        <div class="dg-seo-preview-snippet">
            <div id="dg-seo-preview-title" class="dg-seo-preview-title"><?php echo esc_html($fallback_title); ?></div>
            <div id="dg-seo-preview-url" class="dg-seo-preview-url"><?php echo esc_html($permalink); ?></div>
            <div id="dg-seo-preview-desc" class="dg-seo-preview-desc"><?php echo esc_html($fallback_description); ?></div>
        </div>
    </div>

    <nav class="dg-seo-tabs" aria-label="<?php esc_attr_e('SEO Pro sections', 'dg-platform'); ?>">
        <button type="button" class="dg-seo-tab is-active" data-tab="general"><?php esc_html_e('General', 'dg-platform'); ?></button>
        <button type="button" class="dg-seo-tab" data-tab="social"><?php esc_html_e('Social', 'dg-platform'); ?></button>
        <button type="button" class="dg-seo-tab" data-tab="advanced"><?php esc_html_e('Advanced', 'dg-platform'); ?></button>
    </nav>

    <div class="dg-seo-panel is-active" data-panel="general">
        <p class="dg-seo-field">
            <label for="dg_seo_focus_keyword"><?php esc_html_e('Focus keyword', 'dg-platform'); ?></label>
            <input type="text" id="dg_seo_focus_keyword" name="dg_seo_focus_keyword" value="<?php echo esc_attr($values['focus_keyword']); ?>" placeholder="<?php esc_attr_e('Primary keyword for this page', 'dg-platform'); ?>">
        </p>
        <p class="dg-seo-field">
            <label for="dg_seo_title">
                <?php esc_html_e('SEO title', 'dg-platform'); ?>
                <span id="dg-seo-title-count" class="dg-seo-char-count dg-seo-count-ok">0 / 60</span>
            </label>
            <input type="text" id="dg_seo_title" name="dg_seo_title" value="<?php echo esc_attr($values['title']); ?>" placeholder="<?php echo esc_attr($fallback_title); ?>">
        </p>
        <p class="dg-seo-field">
            <label for="dg_seo_description">
                <?php esc_html_e('Meta description', 'dg-platform'); ?>
                <span id="dg-seo-desc-count" class="dg-seo-char-count dg-seo-count-ok">0 / 160</span>
            </label>
            <textarea id="dg_seo_description" name="dg_seo_description" rows="4" placeholder="<?php echo esc_attr($fallback_description); ?>"><?php echo esc_textarea($values['description']); ?></textarea>
        </p>
        <p class="dg-seo-field">
            <label for="dg_seo_robots"><?php esc_html_e('Robots', 'dg-platform'); ?></label>
            <select id="dg_seo_robots" name="dg_seo_robots">
                <?php foreach (DG_SEO_Settings::robots_options() as $opt_value => $label) : ?>
                    <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($values['robots'] ?? 'index,follow', $opt_value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php esc_html_e('Controls the meta robots tag for this page.', 'dg-platform'); ?></span>
        </p>
    </div>

    <div class="dg-seo-panel" data-panel="social">
        <p class="dg-seo-field">
            <label for="dg_seo_og_title"><?php esc_html_e('Social title', 'dg-platform'); ?></label>
            <input type="text" id="dg_seo_og_title" name="dg_seo_og_title" value="<?php echo esc_attr($values['og_title']); ?>" placeholder="<?php echo esc_attr($values['title'] !== '' ? $values['title'] : $fallback_title); ?>">
        </p>
        <p class="dg-seo-field">
            <label for="dg_seo_og_description"><?php esc_html_e('Social description', 'dg-platform'); ?></label>
            <textarea id="dg_seo_og_description" name="dg_seo_og_description" rows="3" placeholder="<?php echo esc_attr($values['description'] !== '' ? $values['description'] : $fallback_description); ?>"><?php echo esc_textarea($values['og_description']); ?></textarea>
        </p>
        <p class="dg-seo-field">
            <label for="dg_seo_og_image"><?php esc_html_e('Social image URL', 'dg-platform'); ?></label>
            <input type="url" id="dg_seo_og_image" name="dg_seo_og_image" value="<?php echo esc_attr($values['og_image']); ?>" placeholder="<?php esc_attr_e('https://…', 'dg-platform'); ?>">
            <span class="description"><?php esc_html_e('Recommended 1200×630px. Leave blank to use featured image or site default.', 'dg-platform'); ?></span>
        </p>
    </div>

    <div class="dg-seo-panel" data-panel="advanced">
        <p class="dg-seo-field">
            <label for="dg_seo_canonical"><?php esc_html_e('Canonical URL', 'dg-platform'); ?></label>
            <input type="url" id="dg_seo_canonical" name="dg_seo_canonical" value="<?php echo esc_attr($values['canonical']); ?>" placeholder="<?php echo esc_attr($permalink); ?>">
        </p>
        <?php if (class_exists('DG_SEO_IndexNow')) : ?>
            <p class="dg-seo-field dg-seo-indexnow-meta">
                <?php if (DG_SEO_IndexNow::is_indexable($post->ID)) : ?>
                    <button type="button" class="button button-secondary dg-seo-indexnow-btn" data-post-id="<?php echo (int) $post->ID; ?>">
                        <?php esc_html_e('Index Now', 'dg-platform'); ?>
                    </button>
                    <?php
                    $indexed_label = DG_SEO_IndexNow::last_indexed_label($post->ID);
                    if ($indexed_label !== '') :
                        ?>
                        <span class="description dg-seo-indexnow-last"><?php echo esc_html(sprintf(__('Last indexed %s', 'dg-platform'), $indexed_label)); ?></span>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="description"><?php esc_html_e('Index Now is available when the page is published and set to index.', 'dg-platform'); ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>
