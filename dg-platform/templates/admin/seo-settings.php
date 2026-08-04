<?php
if (!defined('ABSPATH')) {
    exit;
}
$tab = $tab ?? 'settings';
?>
<div class="wrap dg-platform-wrap">
    <h1>🔍 SEO Pro</h1>

    <?php if (!empty($_GET['saved'])) : ?>
        <div class="notice notice-success"><p>SEO Pro settings saved.</p></div>
    <?php endif; ?>

    <?php if (DG_SEO_Settings::rank_math_active()) : ?>
        <div class="notice notice-warning">
            <p><strong>Rank Math is still active.</strong> SEO Pro is handling meta tags, schema, and sitemaps. Deactivate Rank Math when ready: <a href="<?php echo esc_url(admin_url('plugins.php')); ?>">Plugins</a>.</p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-seo&tab=pages')); ?>" class="nav-tab <?php echo $tab === 'pages' ? 'nav-tab-active' : ''; ?>">All Pages</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-seo&tab=audit')); ?>" class="nav-tab <?php echo $tab === 'audit' ? 'nav-tab-active' : ''; ?>">Page Audit</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-seo&tab=settings')); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">Global Settings</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-seo&tab=redirects')); ?>" class="nav-tab <?php echo $tab === 'redirects' ? 'nav-tab-active' : ''; ?>">Redirects</a>
    </nav>

    <?php if ($tab === 'pages') : ?>
        <?php include DG_PLATFORM_PATH . 'templates/admin/seo-pages-overview.php'; ?>
    <?php elseif ($tab === 'audit') : ?>
        <?php include DG_PLATFORM_PATH . 'templates/admin/seo-page-audit.php'; ?>
    <?php elseif ($tab === 'redirects') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>301 / 302 Redirects</h2>
            <p class="dg-muted">Paths are matched without trailing slashes, e.g. <code>/old-page</code>.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_seo_redirects'); ?>
                <input type="hidden" name="action" value="dg_save_seo_redirects">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>From path</th>
                            <th>To URL</th>
                            <th>Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = $redirects;
                        $rows[] = ['from' => '', 'to' => '', 'code' => 301];
                        foreach ($rows as $row) :
                            ?>
                            <tr>
                                <td><input type="text" name="redirect_from[]" value="<?php echo esc_attr($row['from']); ?>" class="regular-text" placeholder="/old-url"></td>
                                <td><input type="url" name="redirect_to[]" value="<?php echo esc_attr($row['to']); ?>" class="large-text" placeholder="https://"></td>
                                <td>
                                    <select name="redirect_code[]">
                                        <?php foreach ([301, 302, 307, 308] as $code) : ?>
                                            <option value="<?php echo (int) $code; ?>" <?php selected((int) ($row['code'] ?? 301), $code); ?>><?php echo (int) $code; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="submit" class="button button-primary">Save Redirects</button></p>
            </form>
        </div>
    <?php else : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_seo_settings'); ?>
                <input type="hidden" name="action" value="dg_save_seo_settings">

                <h2>Organization</h2>
                <table class="form-table">
                    <tr>
                        <th>Organization name</th>
                        <td><input type="text" name="organization_name" value="<?php echo esc_attr($settings['organization_name']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Schema type</th>
                        <td>
                            <select name="organization_type">
                                <?php foreach (['Organization', 'RealEstateAgent', 'LodgingBusiness', 'Person', 'LocalBusiness'] as $type) : ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected($settings['organization_type'], $type); ?>><?php echo esc_html($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Website URL</th>
                        <td><input type="url" name="organization_url" value="<?php echo esc_attr($settings['organization_url']); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th>Logo URL</th>
                        <td><input type="url" name="logo_url" value="<?php echo esc_attr($settings['logo_url']); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th>Default social image</th>
                        <td><input type="url" name="default_og_image" value="<?php echo esc_attr($settings['default_og_image']); ?>" class="large-text"></td>
                    </tr>
                </table>

                <h2>Homepage defaults</h2>
                <table class="form-table">
                    <tr>
                        <th>Home title</th>
                        <td><input type="text" name="home_title" value="<?php echo esc_attr($settings['home_title']); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th>Home description</th>
                        <td><textarea name="home_description" rows="3" class="large-text"><?php echo esc_textarea($settings['home_description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th>Title separator</th>
                        <td><input type="text" name="title_separator" value="<?php echo esc_attr($settings['title_separator']); ?>" class="small-text"></td>
                    </tr>
                </table>

                <h2>Social profiles</h2>
                <table class="form-table">
                    <tr><th>Facebook</th><td><input type="url" name="social_facebook" value="<?php echo esc_attr($settings['social_facebook']); ?>" class="large-text"></td></tr>
                    <tr><th>Twitter / X handle</th><td><input type="text" name="social_twitter" value="<?php echo esc_attr($settings['social_twitter']); ?>" class="regular-text" placeholder="@handle"></td></tr>
                    <tr><th>Instagram</th><td><input type="url" name="social_instagram" value="<?php echo esc_attr($settings['social_instagram']); ?>" class="large-text"></td></tr>
                </table>

                <h2>Technical</h2>
                <table class="form-table">
                    <tr>
                        <th>Sitemap</th>
                        <td>
                            <label><input type="checkbox" name="sitemap_enabled" value="1" <?php checked($settings['sitemap_enabled']); ?>> Enable XML sitemap</label>
                            <p class="description">Sitemap index: <a href="<?php echo esc_url(home_url('/sitemap_index.xml')); ?>" target="_blank"><?php echo esc_html(home_url('/sitemap_index.xml')); ?></a></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Noindex</th>
                        <td>
                            <label><input type="checkbox" name="noindex_search" value="1" <?php checked($settings['noindex_search']); ?>> Search results</label><br>
                            <label><input type="checkbox" name="noindex_archives" value="1" <?php checked($settings['noindex_archives']); ?>> Date/author/tag archives</label>
                        </td>
                    </tr>
                    <tr>
                        <th>IndexNow</th>
                        <td>
                            <?php
                            $indexnow_key = class_exists('DG_SEO_IndexNow') ? DG_SEO_IndexNow::ensure_key() : '';
                            $key_url = class_exists('DG_SEO_IndexNow') ? DG_SEO_IndexNow::key_location() : '';
                            ?>
                            <label><input type="checkbox" name="indexnow_auto" value="1" <?php checked(!empty($settings['indexnow_auto'])); ?>> Auto-index on publish &amp; update</label>
                            <p class="description">When enabled, indexable pages are submitted to <a href="https://www.indexnow.org/" target="_blank" rel="noopener">IndexNow</a> (Bing, Yandex, and partners) after you publish or save changes.</p>
                            <?php if ($key_url) : ?>
                                <p class="description">Verification file: <a href="<?php echo esc_url($key_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($key_url); ?></a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <p><button type="submit" class="button button-primary">Save SEO Pro Settings</button></p>
            </form>
        </div>

        <div class="dg-panel">
            <h3>Per-page SEO</h3>
            <p class="dg-muted">Use <strong>Page Audit</strong> above for scores and suggestions, or edit any page/post in WordPress — the <strong>SEO Pro</strong> meta box has title, description, canonical, and robots.</p>
        </div>
    <?php endif; ?>
</div>
