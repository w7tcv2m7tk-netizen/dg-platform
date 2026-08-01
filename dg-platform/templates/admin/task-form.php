<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>➕ Add Task</h1>
    <div class="dg-panel">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="dg_save_task">
            <?php wp_nonce_field('dg_save_task'); ?>
            <table class="form-table">
                <tr><th>Title</th><td><input type="text" name="title" class="regular-text" required></td></tr>
                <tr><th>Description</th><td><textarea name="description" rows="3" class="large-text"></textarea></td></tr>
                <tr><th>Priority</th><td>
                    <select name="priority">
                        <option value="low">Low</option>
                        <option value="normal" selected>Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </td></tr>
                <tr><th>Due Date</th><td><input type="datetime-local" name="due_date"></td></tr>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Create Task</button></p>
        </form>
    </div>
</div>
