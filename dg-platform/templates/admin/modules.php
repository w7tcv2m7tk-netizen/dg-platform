<?php if (!defined('ABSPATH')) exit;
$definitions = dg_platform()->get_module_definitions();
$site = class_exists('DG_Site_Profile') ? DG_Site_Profile::matched_site() : null;
$needs_sync = class_exists('DG_Site_Profile') && DG_Site_Profile::modules_need_sync();
$current_plan = class_exists('DG_Plan_Registry') ? DG_Plan_Registry::current() : 'business';
$plan_tiers = class_exists('DG_Plan_Registry') ? DG_Plan_Registry::tiers() : [];
$plan_info = $plan_tiers[$current_plan] ?? null;
$premium_addons = class_exists('DG_Plan_Registry') ? DG_Plan_Registry::premium_addons() : [];
$optional_addons = class_exists('DG_Plan_Registry') ? DG_Plan_Registry::optional_addons() : [];
$active_addons = class_exists('DG_Plan_Registry') ? DG_Plan_Registry::active_addons() : [];
?>
<div class="wrap dg-platform-wrap">
    <h1>🧩 Modules & Plan</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Module settings saved.</p></div><?php endif; ?>
    <?php if (isset($_GET['plan_saved'])) : ?><div class="notice notice-success"><p>Plan settings saved.</p></div><?php endif; ?>
    <?php if ($site) : ?>
        <p class="dg-muted-subtle">This site: <strong><?php echo esc_html($site['label']); ?></strong> (<code><?php echo esc_html($site['domain']); ?></code>) — recommended: <strong><?php echo esc_html(implode(' + ', $site['modules'])); ?></strong></p>
    <?php endif; ?>
    <?php if ($needs_sync) : ?>
        <div class="notice notice-warning"><p>Active modules don't match this hostname's recommended set. Save with the recommended modules checked, or re-activate the plugin to sync automatically.</p></div>
    <?php endif; ?>

    <?php if ($plan_info) : ?>
    <div class="dg-panel" style="margin-bottom:1.5rem;">
        <h2>Platform Plan</h2>
        <p class="dg-muted-subtle">Level 1 — Business Licence. Controls which platform features and industry apps this site can use.</p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="dg_save_plan">
            <?php wp_nonce_field('dg_save_plan'); ?>
            <table class="form-table">
                <tr>
                    <th>Tier</th>
                    <td>
                        <select name="plan">
                            <?php foreach ($plan_tiers as $key => $tier) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_plan, $key); ?>>
                                    <?php echo esc_html($tier['label'] . ' — ' . ($tier['price_label'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($plan_info) : ?>
                            <p class="description"><?php echo esc_html($plan_info['tagline'] ?? ''); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($premium_addons || $optional_addons) : ?>
                <tr>
                    <th>Add-ons</th>
                    <td>
                        <?php foreach (array_merge($premium_addons, $optional_addons) as $addon_key => $addon) : ?>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="addons[]" value="<?php echo esc_attr($addon_key); ?>" <?php checked(in_array($addon_key, $active_addons, true)); ?>>
                                <?php echo esc_html($addon['label']); ?> (<?php echo esc_html(class_exists('DG_Plan_Registry') ? DG_Plan_Registry::addon_price_label($addon) : ''); ?>)
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Save Plan</button></p>
        </form>
    </div>
    <?php endif; ?>

    <div class="dg-panel">
        <h2>Industry Modules</h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="dg_save_modules">
            <?php wp_nonce_field('dg_save_modules'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Enable</th><th>Module</th><th>Min tier</th><th>Site</th><th>Description</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($definitions as $key => $module) :
                        $is_recommended = $site && in_array($key, $site['modules'], true);
                        $min_tier = $module['min_tier'] ?? 'professional';
                        $allowed = !isset($module['plan_allowed']) || $module['plan_allowed'] || in_array($key, $active_modules, true);
                        ?>
                        <tr<?php echo $is_recommended ? ' style="background:rgba(52,211,153,0.06);"' : ''; ?>>
                            <td>
                                <?php if (!empty($module['required'])) : ?>
                                    <input type="checkbox" checked disabled>
                                <?php else : ?>
                                    <input type="checkbox" name="modules[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $active_modules, true)); ?> <?php disabled(!$allowed); ?>>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html(($module['icon'] ?? '') . ' ' . ($module['name'] ?? $key)); ?></strong></td>
                            <td><code><?php echo esc_html(ucfirst($min_tier)); ?></code></td>
                            <td><?php echo !empty($module['site_host']) ? '<code>' . esc_html($module['site_host']) . '</code>' : '—'; ?></td>
                            <td><?php echo esc_html($module['description'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($module['is_core'])) : ?>
                                    <span class="dg-tag dg-tag-core">Core</span>
                                <?php elseif (in_array($key, $active_modules, true)) : ?>
                                    <span class="dg-tag dg-tag-module">Active</span>
                                <?php elseif (!$allowed) : ?>
                                    <span class="dg-tag">Plan locked</span>
                                <?php else : ?>
                                    <span class="dg-tag">Inactive</span>
                                <?php endif; ?>
                                <?php if ($is_recommended) : ?><span class="dg-tag" style="margin-left:4px;">Recommended</span><?php endif; ?>
                                <?php if (($module['beta_status'] ?? '') === 'preview') : ?><span class="dg-tag" style="margin-left:4px;background:#FEF3C7;color:#92400E;">Preview</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Save Modules</button></p>
        </form>
    </div>
</div>
