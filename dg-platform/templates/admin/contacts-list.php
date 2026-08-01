<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>📇 Contacts</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Contact saved.</p></div><?php endif; ?>
    <p>
        <a href="<?php echo admin_url('admin.php?page=dg-platform-contacts&action=add'); ?>" class="button button-primary">➕ Add Contact</a>
    </p>
    <form method="get">
        <input type="hidden" name="page" value="dg-platform-contacts">
        <p class="search-box">
            <input type="search" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="Search contacts...">
            <input type="submit" class="button" value="Search">
        </p>
    </form>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Phone</th><th>Source</th><th>Status</th><th>Created</th><th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($contacts) : foreach ($contacts as $contact) : ?>
                <tr>
                    <td><strong><?php echo esc_html(DG_Contacts::full_name($contact)); ?></strong></td>
                    <td><?php echo esc_html($contact->email); ?></td>
                    <td><?php echo esc_html($contact->phone); ?></td>
                    <td><?php echo esc_html($contact->source); ?></td>
                    <td><span class="dg-status dg-status-<?php echo esc_attr($contact->status); ?>"><?php echo esc_html($contact->status); ?></span></td>
                    <td><?php echo esc_html($contact->created_at); ?></td>
                    <td><a href="<?php echo admin_url('admin.php?page=dg-platform-contacts&action=edit&id=' . $contact->id); ?>">Edit</a></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="7">No contacts found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
