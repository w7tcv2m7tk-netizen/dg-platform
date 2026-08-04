<?php
if (!defined('ABSPATH')) {
    exit;
}
$tab = $tab ?? 'dashboard';
?>
<div class="wrap dg-platform-wrap">
    <h1>🤖 AI Visibility Pro</h1>

    <?php if (!empty($_GET['saved'])) : ?>
        <div class="notice notice-success"><p>Settings saved.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['scanned'])) : ?>
        <div class="notice notice-success"><p>AI visibility scan completed.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['error']))); ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-ai-visibility&tab=dashboard')); ?>" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-ai-visibility&tab=settings')); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
    </nav>

    <?php if ($tab === 'settings') : ?>
        <div class="dg-panel" style="margin-top:20px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_ai_visibility_settings'); ?>
                <input type="hidden" name="action" value="dg_save_ai_visibility_settings">

                <h2>Business profile</h2>
                <table class="form-table">
                    <tr><th>Business name</th><td><input type="text" name="business_name" value="<?php echo esc_attr($settings['business_name']); ?>" class="regular-text"></td></tr>
                    <tr><th>Industry</th><td><input type="text" name="industry" value="<?php echo esc_attr($settings['industry']); ?>" class="large-text"></td></tr>
                    <tr><th>Location</th><td><input type="text" name="location" value="<?php echo esc_attr($settings['location']); ?>" class="large-text"></td></tr>
                    <tr><th>Website</th><td><input type="url" name="website" value="<?php echo esc_attr($settings['website']); ?>" class="large-text"></td></tr>
                    <tr><th>Target AI queries</th><td><textarea name="target_queries" rows="3" class="large-text"><?php echo esc_textarea($settings['target_queries']); ?></textarea><p class="description">What would someone ask ChatGPT/Gemini to find you?</p></td></tr>
                    <tr><th>Competitors</th><td><input type="text" name="competitors" value="<?php echo esc_attr($settings['competitors']); ?>" class="large-text" placeholder="Comma-separated"></td></tr>
                </table>

                <h2>Monitoring</h2>
                <table class="form-table">
                    <tr>
                        <th>Scheduled scans</th>
                        <td>
                            <select name="schedule">
                                <option value="off" <?php selected($settings['schedule'], 'off'); ?>>Off</option>
                                <option value="weekly" <?php selected($settings['schedule'], 'weekly'); ?>>Weekly</option>
                                <option value="monthly" <?php selected($settings['schedule'], 'monthly'); ?>>Monthly</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2>llms.txt (AI crawlers)</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable llms.txt</th>
                        <td>
                            <label><input type="checkbox" name="llms_txt_enabled" value="1" <?php checked($settings['llms_txt_enabled']); ?>> Serve at <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank"><?php echo esc_html(home_url('/llms.txt')); ?></a></label>
                        </td>
                    </tr>
                    <tr>
                        <th>Extra llms.txt content</th>
                        <td><textarea name="llms_txt_extra" rows="5" class="large-text"><?php echo esc_textarea($settings['llms_txt_extra']); ?></textarea></td>
                    </tr>
                </table>

                <p><button type="submit" class="button button-primary">Save Settings</button></p>
            </form>
        </div>
    <?php else : ?>
        <div class="dg-stats-grid dg-stats-grid-4" style="margin-top:20px;">
            <div class="dg-panel">
                <div class="dg-stat-value"><?php echo $latest ? (int) $latest->combined_score : '—'; ?><?php echo $latest ? '%' : ''; ?></div>
                <div class="dg-stat-label">Latest score <?php echo $latest ? '(' . esc_html($latest->grade) . ')' : ''; ?></div>
            </div>
            <div class="dg-panel"><div class="dg-stat-value"><?php echo esc_html($averages['openai_avg']); ?>%</div><div class="dg-stat-label">ChatGPT avg (90d)</div></div>
            <div class="dg-panel"><div class="dg-stat-value"><?php echo esc_html($averages['gemini_avg']); ?>%</div><div class="dg-stat-label">Gemini avg (90d)</div></div>
            <div class="dg-panel"><div class="dg-stat-value"><?php echo (int) $averages['scans']; ?></div><div class="dg-stat-label">Scans recorded</div></div>
        </div>

        <div style="margin:16px 0;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_run_ai_visibility_scan'); ?>
                <input type="hidden" name="action" value="dg_run_ai_visibility_scan">
                <button type="submit" class="button button-primary">Run scan now</button>
            </form>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-api')); ?>" class="button">API settings</a>
            <?php if (!$integrations['openai'] || !$integrations['gemini']) : ?>
                <span style="color:#B45309;">Configure OpenAI and/or Gemini keys for live AI scoring.</span>
            <?php endif; ?>
        </div>

        <?php if ($latest) : ?>
        <div class="dg-panel">
            <h2>Latest scan — <?php echo esc_html($latest->created_at); ?></h2>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                <div><strong>ChatGPT:</strong> <?php echo (int) $latest->openai_score; ?>%<br><span style="color:#64748B;font-size:13px;"><?php echo esc_html($latest->openai_summary); ?></span></div>
                <div><strong>Gemini:</strong> <?php echo (int) $latest->gemini_score; ?>%<br><span style="color:#64748B;font-size:13px;"><?php echo esc_html($latest->gemini_summary); ?></span></div>
                <div><strong>Technical:</strong> <?php echo (int) $latest->technical_score; ?>%<br><span style="color:#64748B;font-size:13px;">llms.txt, schema, sitemap, content freshness</span></div>
            </div>
            <?php if ($recommendations) : ?>
                <h3>Recommendations</h3>
                <ul style="margin:0 0 0 1.2em;line-height:1.7;">
                    <?php foreach ($recommendations as $tip) : ?>
                        <li>
                            <?php echo esc_html($tip); ?>
                            <?php if (class_exists('DG_AI_Assist') && DG_AI_Assist::available()) : ?>
                                <button type="button" class="button-link dg-ai-btn" data-ai-task="visibility_fix" data-ai-modal="1" data-ai-modal-title="AI fix" data-ai-recommendation="<?php echo esc_attr($tip); ?>" data-ai-openai="<?php echo (int) ($latest->openai_score ?? 0); ?>" data-ai-gemini="<?php echo (int) ($latest->gemini_score ?? 0); ?>" data-ai-technical="<?php echo (int) ($latest->technical_score ?? 0); ?>">✨ Get fix</button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="dg-panel">
            <h2>Scan history</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Date</th><th>Combined</th><th>Grade</th><th>ChatGPT</th><th>Gemini</th><th>Technical</th><th>Source</th></tr></thead>
                <tbody>
                <?php if ($history) : foreach ($history as $scan) : ?>
                    <tr>
                        <td><?php echo esc_html($scan->created_at); ?></td>
                        <td><?php echo (int) $scan->combined_score; ?>%</td>
                        <td><?php echo esc_html($scan->grade); ?></td>
                        <td><?php echo (int) $scan->openai_score; ?>%</td>
                        <td><?php echo (int) $scan->gemini_score; ?>%</td>
                        <td><?php echo (int) $scan->technical_score; ?>%</td>
                        <td><?php echo esc_html($scan->scan_source); ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="7" style="text-align:center;padding:24px;color:#64748B;">No scans yet. Run your first scan above.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate()) : ?>
        <div class="dg-panel">
            <p style="color:#64748B;">Agency client audits and pipeline history: <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-ai')); ?>">AI Visibility (Marketing)</a></p>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
