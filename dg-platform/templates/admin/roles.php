<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>👥 Roles & Permissions</h1>
    <div class="dg-panel">
        <p>Role templates are installed on plugin activation. Assign these roles to users under <a href="<?php echo admin_url('users.php'); ?>">Users</a>.</p>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Role</th><th>Description</th><th>Users</th></tr></thead>
            <tbody>
                <?php foreach ($templates as $role_key => $template) :
                    $users = count(get_users(['role' => $role_key]));
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($template['label']); ?></strong><br><code><?php echo esc_html($role_key); ?></code></td>
                        <td><?php echo esc_html($template['description']); ?></td>
                        <td><?php echo esc_html($users); ?> user(s)</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="dg-panel">
        <h3>Permission Levels</h3>
        <p><strong>Level 1 — Business Licence:</strong> Controlled via Module Manager (which modules are enabled).</p>
        <p><strong>Level 2 — User Permissions:</strong> Controlled via WordPress roles above.</p>
    </div>
</div>
