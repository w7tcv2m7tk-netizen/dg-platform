<?php if (!defined('ABSPATH')) exit;
$definitions = dg_platform()->get_module_definitions();
?>
<div class="wrap dg-platform-wrap">
    <h1>🧩 Module Manager</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Module settings saved.</p></div><?php endif; ?>
    <div class="dg-panel">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="dg_save_modules">
            <?php wp_nonce_field('dg_save_modules'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Enable</th><th>Module</th><th>Description</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($definitions as $key => $module) : ?>
                        <tr>
                            <td>
                                <?php if (!empty($module['required'])) : ?>
                                    <input type="checkbox" checked disabled>
                                <?php else : ?>
                                    <input type="checkbox" name="modules[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $active_modules, true)); ?>>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html(($module['icon'] ?? '') . ' ' . $module['name']); ?></strong></td>
                            <td><?php echo esc_html($module['description'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($module['is_core'])) : ?>
                                    <span class="dg-tag dg-tag-core">Core</span>
                                <?php elseif (in_array($key, $active_modules, true)) : ?>
                                    <span class="dg-tag dg-tag-module">Active</span>
                                <?php else : ?>
                                    <span class="dg-tag">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Save Modules</button></p>
        </form>
    </div>
</div>
