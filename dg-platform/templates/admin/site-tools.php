<?php
if (!defined('ABSPATH')) {
    exit;
}
$tab = $tab ?? 'overview';
?>
<div class="wrap dg-platform-wrap">
    <h1>🛠 Site Tools</h1>
    <p style="color:#64748B;max-width:820px;">Cache, images, email delivery, snippets, and Cloudflare/Google status — built into DG Platform so you can retire Smush, Fluent SMTP, Fluent Snippets, Site Kit, and Super Page Cache admin workflows.</p>

    <?php if (!empty($_GET['saved'])) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>
    <?php if (!empty($_GET['cf_ok'])) : ?><div class="notice notice-success"><p>Cloudflare connected successfully.</p></div><?php endif; ?>
    <?php if (!empty($_GET['cf_imported'])) : ?><div class="notice notice-success"><p>Cloudflare credentials imported from Super Page Cache.</p></div><?php endif; ?>
    <?php if (!empty($_GET['cf_import'])) : ?><div class="notice notice-warning"><p>Cloudflare credentials were not saved in Site Tools. If they are already in Super Page Cache, use <strong>Import from Super Page Cache</strong> below.</p></div><?php endif; ?>
    <?php if (!empty($_GET['cf_error'])) : ?><div class="notice notice-error"><p><?php echo esc_html(wp_unslash($_GET['cf_error'])); ?></p></div><?php endif; ?>
    <?php if (!empty($_GET['purged'])) : ?><div class="notice notice-success"><p>Cache purged successfully.</p></div><?php endif; ?>
    <?php if (!empty($_GET['purge_error'])) : ?><div class="notice notice-error"><p>Cache purge failed — check Cloudflare credentials or install a cache plugin.</p></div><?php endif; ?>
    <?php if (!empty($_GET['smtp_ok'])) : ?><div class="notice notice-success"><p>SMTP test email sent.</p></div><?php endif; ?>
    <?php if (!empty($_GET['smtp_error'])) : ?><div class="notice notice-error"><p>SMTP test failed — check host, port, and credentials.</p></div><?php endif; ?>
    <?php if (!empty($_GET['bulk'])) : ?><div class="notice notice-success"><p>Optimized <?php echo (int) $_GET['bulk']; ?> images<?php echo !empty($_GET['saved_bytes']) ? ' — saved ' . esc_html(size_format((int) $_GET['saved_bytes'])) : ''; ?>. <?php echo !empty($_GET['remaining']) ? (int) $_GET['remaining'] . ' remaining.' : ''; ?></p></div><?php endif; ?>
    <?php if (!empty($_GET['pagespeed'])) : ?><div class="notice notice-success"><p>PageSpeed scores refreshed.</p></div><?php endif; ?>
    <?php if (!empty($_GET['deleted'])) : ?><div class="notice notice-success"><p>Snippet deleted.</p></div><?php endif; ?>
    <?php if (!empty($_GET['delete_error'])) : ?><div class="notice notice-error"><p>Could not delete snippet — try again or remove via hosting file manager (option <code>dg_site_tools_snippets</code> in wp_options).</p></div><?php endif; ?>

    <?php if ($legacy) : ?>
        <div class="notice notice-warning">
            <p><strong>Legacy plugins still active:</strong>
                <?php foreach ($legacy as $i => $item) : ?>
                    <?php echo $i ? ' · ' : ''; ?>
                    <?php echo esc_html($item['label']); ?> → use <?php echo esc_html($item['replacement']); ?>
                <?php endforeach; ?>
                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>">Manage plugins</a>
            </p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <?php
        $tabs = [
            'overview' => 'Overview',
            'health' => 'Platform Health',
            'cache' => 'Cache & CDN',
            'images' => 'Images',
            'email' => 'Email (SMTP)',
            'snippets' => 'Snippets',
            'analytics' => 'Analytics',
        ];
        foreach ($tabs as $key => $label) :
            ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-site-tools&tab=' . $key)); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'overview') : ?>
        <div class="dg-stats-grid dg-stats-grid-4" style="margin-top:20px;">
            <div class="dg-stat-card" style="border-left-color:#3B82F6">
                <div class="dg-stat-value"><?php echo !empty($cache_status['cloudflare_api']) ? '✓' : '—'; ?></div>
                <div class="dg-stat-label">Cloudflare API</div>
            </div>
            <div class="dg-stat-card" style="border-left-color:#059669">
                <div class="dg-stat-value"><?php echo $settings['smtp_enabled'] ? '✓' : '—'; ?></div>
                <div class="dg-stat-label">SMTP configured</div>
            </div>
            <div class="dg-stat-card" style="border-left-color:#F59E0B">
                <div class="dg-stat-value"><?php echo (int) $unoptimized; ?></div>
                <div class="dg-stat-label">Images to optimize</div>
            </div>
            <div class="dg-stat-card" style="border-left-color:#A78BFA">
                <div class="dg-stat-value"><?php echo $settings['pagespeed_mobile'] !== null ? (int) $settings['pagespeed_mobile'] : '—'; ?></div>
                <div class="dg-stat-label">PageSpeed mobile</div>
            </div>
        </div>

        <div class="dg-panel" style="margin-top:20px;">
            <h2>Quick actions</h2>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-site-tools&tab=health')); ?>" class="button">Platform health check</a>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_purge_site_cache'), 'dg_purge_site_cache')); ?>" class="button button-primary">Purge all cache</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-site-tools&tab=images')); ?>" class="button">Optimize images</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-site-tools&tab=email')); ?>" class="button">Configure SMTP</a>
            </p>
        </div>

        <div class="dg-panel">
            <h2>What replaces what</h2>
            <table class="widefat striped">
                <thead><tr><th>Plugin</th><th>DG Platform replacement</th><th>Can deactivate?</th></tr></thead>
                <tbody>
                    <tr><td>Super Page Cache</td><td>Cloudflare API purge + optional keep plugin for edge caching only</td><td>Partial — purge UI yes; edge cache layer may stay</td></tr>
                    <tr><td>Smush</td><td>Compress on upload + bulk optimize</td><td>Yes, once bulk run complete</td></tr>
                    <tr><td>Fluent SMTP</td><td>Site Tools → Email</td><td>Yes, after SMTP test passes</td></tr>
                    <tr><td>Fluent Snippets</td><td>Site Tools → Snippets + DG modules</td><td>Yes, after migrating snippets</td></tr>
                    <tr><td>Google Site Kit</td><td>SEO + Analytics Pro + Site Tools analytics</td><td>Yes — GSC deep data needs API key in API Settings</td></tr>
                </tbody>
            </table>
        </div>

    <?php elseif ($tab === 'health') : ?>
        <?php if (!$health) : ?>
            <div class="notice notice-error" style="margin-top:20px;"><p>Platform Health checks could not run. Re-upload the full DG Platform plugin zip.</p></div>
        <?php else : ?>
        <?php
        $score = (int) ($health['score'] ?? 0);
        $score_color = $score >= 85 ? '#059669' : ($score >= 60 ? '#F59E0B' : '#DC2626');
        ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>Platform Health</h2>
            <p style="color:#64748B;">Run before beta launch or after plugin updates. Fix <strong>fail</strong> items first, then warnings.</p>
            <div class="dg-stats-grid dg-stats-grid-4" style="margin:16px 0;">
                <div class="dg-stat-card" style="border-left-color:<?php echo esc_attr($score_color); ?>">
                    <div class="dg-stat-value"><?php echo (int) $score; ?>%</div>
                    <div class="dg-stat-label">Health score</div>
                </div>
                <div class="dg-stat-card" style="border-left-color:#059669">
                    <div class="dg-stat-value"><?php echo (int) ($health['pass'] ?? 0); ?></div>
                    <div class="dg-stat-label">Passing</div>
                </div>
                <div class="dg-stat-card" style="border-left-color:#F59E0B">
                    <div class="dg-stat-value"><?php echo (int) ($health['warn'] ?? 0); ?></div>
                    <div class="dg-stat-label">Warnings</div>
                </div>
                <div class="dg-stat-card" style="border-left-color:#DC2626">
                    <div class="dg-stat-value"><?php echo (int) ($health['fail'] ?? 0); ?></div>
                    <div class="dg-stat-label">Failures</div>
                </div>
            </div>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-site-tools&tab=health')); ?>" class="button">Re-run checks</a>
                <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" class="button">Permalinks</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-modules')); ?>" class="button">Modules & Plan</a>
            </p>
        </div>
        <div class="dg-panel">
            <table class="widefat striped">
                <thead><tr><th style="width:100px;">Status</th><th>Check</th><th>Detail</th></tr></thead>
                <tbody>
                <?php foreach ($health['checks'] ?? [] as $check) :
                    $status = $check['status'] ?? 'warn';
                    $badge = $status === 'pass' ? '✓ Pass' : ($status === 'fail' ? '✗ Fail' : '⚠ Warn');
                    $color = $status === 'pass' ? '#059669' : ($status === 'fail' ? '#DC2626' : '#B45309');
                    ?>
                    <tr>
                        <td><strong style="color:<?php echo esc_attr($color); ?>"><?php echo esc_html($badge); ?></strong></td>
                        <td><?php echo esc_html($check['label'] ?? ''); ?></td>
                        <td style="color:#64748B;"><?php echo esc_html($check['detail'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php elseif ($tab === 'cache') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>Purge cache</h2>
            <p style="color:#666;">Purges Cloudflare (via API), Super Page Cache / WP Rocket / LiteSpeed if installed, and WordPress object cache.</p>
            <p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_purge_site_cache'), 'dg_purge_site_cache')); ?>" class="button button-primary button-hero">Purge all cache now</a>
            </p>
            <ul style="color:#64748B;">
                <li>Cloudflare API: <?php echo !empty($cache_status['cloudflare_api']) ? '<strong style="color:#059669">Configured</strong>' : 'Not configured — add token below'; ?></li>
                <li>Super Page Cache plugin: <?php echo !empty($cache_status['super_page_cache']) ? 'Detected' : 'Not active'; ?></li>
                <li>WP Rocket: <?php echo !empty($cache_status['wp_rocket']) ? 'Detected' : 'Not active'; ?></li>
                <li>LiteSpeed: <?php echo !empty($cache_status['litespeed']) ? 'Detected' : 'Not active'; ?></li>
            </ul>
        </div>

        <div class="dg-panel">
            <h2>Cloudflare credentials</h2>
            <p style="color:#64748B;">Enter credentials here — <strong>not</strong> in DG Platform → API Settings. API Settings is for PageSpeed/OpenAI only.</p>
            <?php if (!empty($cf_credentials['source']) && $cf_credentials['source'] !== 'none' && $cf_credentials['source'] !== 'site_tools') : ?>
                <div class="notice notice-info inline"><p>Cloudflare credentials detected via <strong><?php echo esc_html(str_replace('_', ' ', $cf_credentials['source'])); ?></strong>. Import them into Site Tools for the health check and toolbar purge to recognize them.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_site_tools_settings'); ?>
                <input type="hidden" name="action" value="dg_save_site_tools">
                <input type="hidden" name="tab" value="cache">
                <table class="form-table">
                    <tr><th>API token</th><td><input type="password" name="cf_api_token" value="" class="large-text" autocomplete="new-password" placeholder="<?php echo !empty($settings['cf_api_token']) ? 'Leave blank to keep saved token' : 'Paste Cloudflare API token'; ?>"><?php if (!empty($settings['cf_api_token'])) : ?><p class="description" style="color:#059669;">Token saved<?php echo !empty($cf_zone['zone_name']) ? ' and verified' : ''; ?>.</p><?php endif; ?><p class="description">Create at Cloudflare → My Profile → API Tokens. Needs <code>Zone.Cache Purge</code> and <code>Zone.Read</code>.</p></td></tr>
                    <tr><th>Zone ID</th><td><input type="text" name="cf_zone_id" value="<?php echo esc_attr($settings['cf_zone_id']); ?>" class="regular-text" placeholder="32-character zone ID from Cloudflare overview"></td></tr>
                    <tr><th>Account ID</th><td><input type="text" name="cf_account_id" value="<?php echo esc_attr($settings['cf_account_id']); ?>" class="regular-text"><p class="description">Optional — shown on Cloudflare zone overview.</p></td></tr>
                </table>
                <?php if (!empty($cf_zone['zone_name'])) : ?>
                    <p style="color:#059669;">Connected zone: <strong><?php echo esc_html($cf_zone['zone_name']); ?></strong> (<?php echo esc_html($cf_zone['plan'] ?? ''); ?>)<?php if (!empty($cf_zone['source']) && $cf_zone['source'] !== 'site_tools') : ?> · source: <?php echo esc_html(str_replace('_', ' ', $cf_zone['source'])); ?><?php endif; ?></p>
                <?php elseif (!empty($cf_zone['message'])) : ?>
                    <p style="color:#B45309;"><?php echo esc_html($cf_zone['message']); ?></p>
                <?php endif; ?>
                <?php if (!empty($cf_analytics['success'])) : ?>
                    <p>Last 7 days: <strong><?php echo number_format((int) $cf_analytics['requests']); ?></strong> requests · <strong><?php echo esc_html(size_format((int) $cf_analytics['bandwidth'])); ?></strong> bandwidth</p>
                <?php endif; ?>
                <p>
                    <button type="submit" class="button button-primary">Save Cloudflare settings</button>
                    <?php if (!empty($cf_legacy['source']) && $cf_legacy['source'] !== 'none' && $cf_legacy['source'] !== 'site_tools') : ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_site_tools_import_cloudflare'), 'dg_site_tools_import_cloudflare')); ?>" class="button">Import from Super Page Cache</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

    <?php elseif ($tab === 'images') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>Image optimization</h2>
            <p style="color:#666;">Compresses JPEG/PNG on upload and can bulk-process the media library. Replaces Smush for standard compression.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_site_tools_settings'); ?>
                <input type="hidden" name="action" value="dg_save_site_tools">
                <input type="hidden" name="tab" value="images">
                <table class="form-table">
                    <tr><th>Compress on upload</th><td><label><input type="checkbox" name="compress_on_upload" value="1" <?php checked($settings['compress_on_upload']); ?>> Enable automatic compression</label></td></tr>
                    <tr><th>JPEG quality</th><td><input type="number" name="jpeg_quality" min="50" max="95" value="<?php echo (int) $settings['jpeg_quality']; ?>"> <span class="description">Default 82 — lower = smaller files</span></td></tr>
                    <tr><th>Max width (px)</th><td><input type="number" name="max_image_width" min="0" value="<?php echo (int) $settings['max_image_width']; ?>"> <span class="description">0 = no resize. Large uploads scaled down.</span></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Save image settings</button></p>
            </form>
            <hr>
            <p><strong><?php echo (int) $unoptimized; ?></strong> images not yet optimized.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_site_tools_bulk_images'); ?>
                <input type="hidden" name="action" value="dg_site_tools_bulk_images">
                <button type="submit" class="button">Bulk optimize next 25 images</button>
            </form>
        </div>

    <?php elseif ($tab === 'email') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>SMTP (replaces Fluent SMTP)</h2>
            <p style="color:#666;">All DG Platform emails (forms, automations, booking confirmations) use these settings when enabled.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_site_tools_settings'); ?>
                <input type="hidden" name="action" value="dg_save_site_tools">
                <input type="hidden" name="tab" value="email">
                <table class="form-table">
                    <tr><th>Enable SMTP</th><td><label><input type="checkbox" name="smtp_enabled" value="1" <?php checked($settings['smtp_enabled']); ?>> Route wp_mail via SMTP</label></td></tr>
                    <tr><th>SMTP host</th><td><input type="text" name="smtp_host" value="<?php echo esc_attr($settings['smtp_host']); ?>" class="regular-text" placeholder="smtp.example.com"></td></tr>
                    <tr><th>Port</th><td><input type="number" name="smtp_port" value="<?php echo (int) $settings['smtp_port']; ?>"></td></tr>
                    <tr><th>Encryption</th><td><select name="smtp_encryption"><option value="tls" <?php selected($settings['smtp_encryption'], 'tls'); ?>>TLS</option><option value="ssl" <?php selected($settings['smtp_encryption'], 'ssl'); ?>>SSL</option><option value="none" <?php selected($settings['smtp_encryption'], 'none'); ?>>None</option></select></td></tr>
                    <tr><th>Username</th><td><input type="text" name="smtp_user" value="<?php echo esc_attr($settings['smtp_user']); ?>" class="regular-text"></td></tr>
                    <tr><th>Password</th><td><input type="password" name="smtp_pass" value="<?php echo $settings['smtp_pass'] ? '********' : ''; ?>" class="regular-text" autocomplete="new-password"></td></tr>
                    <tr><th>From email</th><td><input type="email" name="smtp_from_email" value="<?php echo esc_attr($settings['smtp_from_email']); ?>" class="regular-text"></td></tr>
                    <tr><th>From name</th><td><input type="text" name="smtp_from_name" value="<?php echo esc_attr($settings['smtp_from_name']); ?>" class="regular-text"></td></tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary">Save SMTP settings</button>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_site_tools_test_smtp'), 'dg_site_tools_test_smtp')); ?>" class="button">Send test email</a>
                </p>
            </form>
        </div>

    <?php elseif ($tab === 'snippets') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>Code snippets</h2>
            <p style="color:#666;">For small custom hooks not covered by DG modules. Migrate remaining Fluent Snippets here, then deactivate Fluent Snippets.</p>
            <?php if ($snippets) : ?>
                <table class="widefat striped">
                    <thead><tr><th>Name</th><th>Hook</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($snippets as $s) : ?>
                        <tr>
                            <td><?php echo esc_html($s['name'] ?? ''); ?></td>
                            <td><code><?php echo esc_html($s['hook'] ?? ''); ?></code></td>
                            <td><?php echo !empty($s['active']) ? 'Yes' : 'No'; ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('dg_delete_site_snippet'); ?>
                                    <input type="hidden" name="action" value="dg_delete_site_snippet">
                                    <input type="hidden" name="snippet_id" value="<?php echo esc_attr($s['id'] ?? ''); ?>">
                                    <button type="submit" class="button button-small" onclick="return confirm('Delete snippet?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="color:#64748B;">No snippets yet.</p>
            <?php endif; ?>
        </div>
        <div class="dg-panel">
            <h2>Add / edit snippet</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_save_site_snippet'); ?>
                <input type="hidden" name="action" value="dg_save_site_snippet">
                <table class="form-table">
                    <tr><th>Name</th><td><input type="text" name="snippet_name" class="regular-text" required></td></tr>
                    <tr><th>WordPress hook</th><td><select name="snippet_hook"><?php foreach ($snippet_hooks as $hook => $label) : ?><option value="<?php echo esc_attr($hook); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th>Priority</th><td><input type="number" name="snippet_priority" value="10"></td></tr>
                    <tr><th>PHP code</th><td><textarea name="snippet_code" rows="12" class="large-text code" placeholder="// Example: add_filter('body_class', function($c) { $c[] = 'my-class'; return $c; });"></textarea><p class="description">Runs on the selected hook. Only add trusted code — equivalent to Fluent Snippets.</p></td></tr>
                    <tr><th>Active</th><td><label><input type="checkbox" name="snippet_active" value="1" checked> Run on site load</label></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Save snippet</button></p>
            </form>
        </div>

    <?php elseif ($tab === 'analytics') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <h2>Performance & traffic</h2>
            <div class="dg-stats-grid dg-stats-grid-3">
                <div class="dg-stat-card" style="border-left-color:#059669">
                    <div class="dg-stat-value"><?php echo $settings['pagespeed_mobile'] !== null ? (int) $settings['pagespeed_mobile'] : '—'; ?></div>
                    <div class="dg-stat-label">PageSpeed mobile</div>
                </div>
                <div class="dg-stat-card" style="border-left-color:#3B82F6">
                    <div class="dg-stat-value"><?php echo $settings['pagespeed_desktop'] !== null ? (int) $settings['pagespeed_desktop'] : '—'; ?></div>
                    <div class="dg-stat-label">PageSpeed desktop</div>
                </div>
                <div class="dg-stat-card" style="border-left-color:#F59E0B">
                    <div class="dg-stat-value"><?php echo !empty($cf_analytics['success']) ? number_format((int) $cf_analytics['requests']) : '—'; ?></div>
                    <div class="dg-stat-label">CF requests (7d)</div>
                </div>
            </div>
            <?php if ($settings['pagespeed_checked_at']) : ?>
                <p style="color:#64748B;">PageSpeed last checked: <?php echo esc_html($settings['pagespeed_checked_at']); ?></p>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_site_tools_refresh_pagespeed'), 'dg_site_tools_refresh_pagespeed')); ?>" class="button button-primary">Refresh PageSpeed scores</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-analytics-pro')); ?>" class="button">Analytics Pro (CRM)</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-seo')); ?>" class="button">SEO Pro</a>
            </p>
        </div>
        <div class="dg-panel">
            <h2>Google Search Console</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_site_tools_settings'); ?>
                <input type="hidden" name="action" value="dg_save_site_tools">
                <input type="hidden" name="tab" value="analytics">
                <table class="form-table">
                    <tr><th>Property URL</th><td><input type="url" name="gsc_property" value="<?php echo esc_attr($settings['gsc_property'] ?: home_url('/')); ?>" class="large-text"><p class="description">For reference. Full GSC API data: add credentials in <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-api')); ?>">API Settings</a>. Site Kit can be deactivated once SEO + Analytics Pro cover your needs.</p></td></tr>
                </table>
                <p><button type="submit" class="button">Save</button></p>
            </form>
            <?php if (!empty($google['site_kit_active'])) : ?>
                <div class="notice notice-info inline"><p>Google Site Kit is still active. Deactivate after confirming PageSpeed and SEO modules meet your needs.</p></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
