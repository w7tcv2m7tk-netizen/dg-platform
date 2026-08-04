<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>✅ Tasks</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Task created.</p></div><?php endif; ?>
    <?php if (isset($_GET['completed'])) : ?><div class="notice notice-success"><p>Task completed.</p></div><?php endif; ?>
    <p><a href="<?php echo admin_url('admin.php?page=dg-platform-tasks&action=add'); ?>" class="button button-primary">➕ Add Task</a></p>
    <table class="wp-list-table widefat fixed striped">
        <thead><tr><th>Title</th><th>Priority</th><th>Status</th><th>Due Date</th><th>Actions</th></tr></thead>
        <tbody>
            <?php if ($tasks) : foreach ($tasks as $task) : ?>
                <tr>
                    <td><strong><?php echo esc_html($task->title); ?></strong></td>
                    <td><?php echo esc_html($task->priority); ?></td>
                    <td><span class="dg-status dg-status-<?php echo esc_attr($task->status); ?>"><?php echo esc_html($task->status); ?></span></td>
                    <td><?php echo esc_html($task->due_date ?: '—'); ?></td>
                    <td>
                        <?php if ($task->status !== 'completed') : ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=dg_complete_task&id=' . $task->id), 'dg_complete_task'); ?>">Complete</a>
                        <?php endif; ?>
                        <?php if (DG_Permissions::current_user_can('dg_manage_tasks')) : ?>
                            <?php if ($task->status !== 'completed') : ?> | <?php endif; ?>
                            <?php echo DG_Admin_Delete::link('dg_delete_task', (int) $task->id); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="5">No tasks found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
