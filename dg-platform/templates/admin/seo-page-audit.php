<?php
/**
 * SEO Pro — Page Audit tab.
 *
 * @var array<int,array<string,mixed>> $audit_pages
 * @var int|null                         $selected_post
 * @var array<string,mixed>|null         $audit_analysis
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

$analysis = $audit_analysis ?? null;
$fields = $analysis['fields'] ?? [];
$suggestions = $analysis['suggestions'] ?? [];
$grade = $analysis['grade'] ?? ['label' => '—', 'color' => '#646970'];
$score = isset($analysis['score']) ? (int) $analysis['score'] : 0;
?>
<div id="dg-seo-page-audit" class="dg-seo-audit" data-post-id="<?php echo (int) $selected_post; ?>">
    <div class="dg-seo-audit-toolbar">
        <label for="dg-seo-audit-page-select" class="screen-reader-text">Select page</label>
        <select id="dg-seo-audit-page-select" class="dg-seo-audit-select">
            <?php foreach ($audit_pages as $page) : ?>
                <option
                    value="<?php echo (int) $page['id']; ?>"
                    <?php selected((int) $selected_post, (int) $page['id']); ?>
                    data-type="<?php echo esc_attr($page['type_label']); ?>"
                    data-status="<?php echo esc_attr($page['status']); ?>"
                >
                    <?php
                    echo esc_html($page['title']);
                    echo ' (' . esc_html($page['type_label']);
                    if ($page['status'] !== 'publish') {
                        echo ' · ' . esc_html($page['status']);
                    }
                    echo ')';
                    ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($analysis && !empty($analysis['edit_url'])) : ?>
            <a href="<?php echo esc_url($analysis['edit_url']); ?>" class="button" target="_blank" rel="noopener">Edit in WordPress</a>
        <?php endif; ?>
        <?php if ($analysis && !empty($analysis['permalink'])) : ?>
            <a href="<?php echo esc_url($analysis['permalink']); ?>" class="button" target="_blank" rel="noopener">View page</a>
        <?php endif; ?>
        <?php if ($selected_post && class_exists('DG_SEO_IndexNow') && DG_SEO_IndexNow::is_indexable($selected_post)) : ?>
            <button type="button" class="button button-secondary dg-seo-indexnow-btn" data-post-id="<?php echo (int) $selected_post; ?>">
                Index Now
            </button>
            <?php
            $indexed_label = DG_SEO_IndexNow::last_indexed_label($selected_post);
            if ($indexed_label !== '') :
                ?>
                <span class="dg-seo-indexnow-last dg-seo-indexnow-toolbar-last">Indexed <?php echo esc_html($indexed_label); ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <span id="dg-seo-indexnow-status" class="dg-seo-indexnow-status" aria-live="polite"></span>
    </div>

    <?php if (empty($audit_pages)) : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <p>No pages found for SEO audit. Check which post types are enabled in SEO Pro settings.</p>
        </div>
    <?php else : ?>
        <div class="dg-seo-audit-layout">
            <aside class="dg-seo-audit-score-card">
                <div class="dg-seo-audit-score-ring" style="--score-color:<?php echo esc_attr($grade['color']); ?>">
                    <span id="dg-seo-audit-score" class="dg-seo-audit-score-num"><?php echo (int) $score; ?></span>
                    <span class="dg-seo-audit-score-label">/ 100</span>
                </div>
                <div id="dg-seo-audit-grade" class="dg-seo-audit-grade" style="color:<?php echo esc_attr($grade['color']); ?>">
                    <?php echo esc_html($grade['label']); ?>
                </div>
                <p id="dg-seo-audit-post-title" class="dg-seo-audit-post-title">
                    <?php echo esc_html($analysis['post_title'] ?? ''); ?>
                </p>
                <ul id="dg-seo-audit-stats" class="dg-seo-audit-stats">
                    <?php if (!empty($analysis['stats'])) : ?>
                        <li><?php echo (int) $analysis['stats']['word_count']; ?> words</li>
                        <li>Title: <?php echo (int) $analysis['stats']['title_length']; ?> chars</li>
                        <li>Description: <?php echo (int) $analysis['stats']['description_length']; ?> chars</li>
                        <li><?php echo (int) $analysis['stats']['internal_links']; ?> internal links</li>
                    <?php endif; ?>
                </ul>
            </aside>

            <div class="dg-seo-audit-main">
                <div class="dg-panel dg-seo-audit-checklist">
                    <h2>SEO checklist</h2>
                    <p class="description">Fix failed items first — each one lowers your score. Warnings are optional improvements.</p>
                    <ul id="dg-seo-audit-checks" class="dg-seo-audit-checks">
                        <?php if (!empty($analysis['checks'])) : ?>
                            <?php foreach ($analysis['checks'] as $check) : ?>
                                <li class="dg-seo-check dg-seo-check-<?php echo esc_attr($check['status']); ?>">
                                    <span class="dg-seo-check-icon" aria-hidden="true"></span>
                                    <div class="dg-seo-check-body">
                                        <strong><?php echo esc_html($check['label']); ?></strong>
                                        <span class="dg-seo-check-msg"><?php echo esc_html($check['message']); ?></span>
                                        <?php if (!empty($check['suggestion'])) : ?>
                                            <span class="dg-seo-check-tip"><?php echo esc_html($check['suggestion']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="dg-panel dg-seo-audit-fields">
                    <div class="dg-seo-audit-fields-header">
                        <div>
                            <h2>Optimise this page</h2>
                            <p class="description">Edit fields below and save — your score updates automatically. Use suggested values as a starting point.</p>
                        </div>
                        <div class="dg-seo-ai-actions">
                            <?php if (class_exists('DG_SEO_AI_Optimizer') && DG_SEO_AI_Optimizer::available()) : ?>
                                <button type="button" class="button button-secondary" id="dg-seo-ai-optimize">
                                    ✨ Optimise with AI
                                </button>
                                <button type="button" class="button button-secondary dg-ai-btn" data-ai-task="seo_suburb" data-ai-apply-seo="1" data-post-id="<?php echo (int) $selected_post; ?>" data-ai-post-id="<?php echo (int) $selected_post; ?>">
                                    🏘 Suburb page AI
                                </button>
                            <?php else : ?>
                                <button type="button" class="button button-secondary" id="dg-seo-ai-optimize" disabled title="Configure an OpenAI or Gemini API key first">
                                    ✨ Optimise with AI
                                </button>
                                <p class="description dg-seo-ai-key-hint">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-api')); ?>">Add OpenAI or Gemini API key</a> to enable AI optimisation.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form id="dg-seo-audit-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="dg_audit_focus_keyword">Focus keyword</label></th>
                                <td>
                                    <input type="text" id="dg_audit_focus_keyword" name="focus_keyword" class="regular-text"
                                           value="<?php echo esc_attr($fields['focus_keyword'] ?? ''); ?>"
                                           placeholder="<?php echo esc_attr($suggestions['focus_keyword'] ?? ''); ?>">
                                    <?php if (!empty($suggestions['focus_keyword'])) : ?>
                                        <p class="description">
                                            Suggested:
                                            <button type="button" class="button-link dg-seo-use-suggestion" data-target="dg_audit_focus_keyword" data-value="<?php echo esc_attr($suggestions['focus_keyword']); ?>">
                                                <?php echo esc_html($suggestions['focus_keyword']); ?>
                                            </button>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <label for="dg_audit_title">SEO title</label>
                                    <span id="dg-audit-title-count" class="dg-seo-char-count">0 / 60</span>
                                </th>
                                <td>
                                    <input type="text" id="dg_audit_title" name="title" class="large-text"
                                           value="<?php echo esc_attr($fields['title'] ?? ''); ?>"
                                           placeholder="<?php echo esc_attr($analysis['resolved']['title'] ?? ($suggestions['title'] ?? '')); ?>">
                                    <?php if (!empty($suggestions['title'])) : ?>
                                        <p class="description">
                                            Suggested:
                                            <button type="button" class="button-link dg-seo-use-suggestion" data-target="dg_audit_title" data-value="<?php echo esc_attr($suggestions['title']); ?>">
                                                <?php echo esc_html($suggestions['title']); ?>
                                            </button>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <label for="dg_audit_description">Meta description</label>
                                    <span id="dg-audit-desc-count" class="dg-seo-char-count">0 / 160</span>
                                </th>
                                <td>
                                    <textarea id="dg_audit_description" name="description" rows="3" class="large-text"
                                              placeholder="<?php echo esc_attr($analysis['resolved']['description'] ?? ($suggestions['description'] ?? '')); ?>"><?php echo esc_textarea($fields['description'] ?? ''); ?></textarea>
                                    <?php if (!empty($suggestions['description'])) : ?>
                                        <p class="description">
                                            Suggested:
                                            <button type="button" class="button-link dg-seo-use-suggestion" data-target="dg_audit_description" data-value="<?php echo esc_attr($suggestions['description']); ?>">
                                                <?php echo esc_html($suggestions['description']); ?>
                                            </button>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="dg_audit_robots">Robots</label></th>
                                <td>
                                    <select id="dg_audit_robots" name="robots">
                                        <?php foreach (DG_SEO_Settings::robots_options() as $opt_value => $label) : ?>
                                            <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($fields['robots'] ?? 'index,follow', $opt_value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Use <strong>Noindex</strong> for client portal and account pages that should not appear in search results.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="dg_audit_og_title">Social title</label></th>
                                <td>
                                    <input type="text" id="dg_audit_og_title" name="og_title" class="large-text"
                                           value="<?php echo esc_attr($fields['og_title'] ?? ''); ?>"
                                           placeholder="Defaults to SEO title if empty">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="dg_audit_og_description">Social description</label></th>
                                <td>
                                    <textarea id="dg_audit_og_description" name="og_description" rows="2" class="large-text"
                                              placeholder="Defaults to meta description if empty"><?php echo esc_textarea($fields['og_description'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="dg_audit_og_image">Social image URL</label></th>
                                <td>
                                    <input type="url" id="dg_audit_og_image" name="og_image" class="large-text"
                                           value="<?php echo esc_attr($fields['og_image'] ?? ''); ?>"
                                           placeholder="https://… (1200×630 recommended)">
                                </td>
                            </tr>
                        </table>
                        <p>
                            <button type="submit" class="button button-primary" id="dg-seo-audit-save">Save &amp; re-score</button>
                            <span id="dg-seo-audit-status" class="dg-seo-audit-status" aria-live="polite"></span>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
