<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>📋 Audit Log</h1>
    <p style="color:#64748B;">Platform actions including marketing clients, audits, voice leads, and documents.</p>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:160px;">When</th>
                <th style="width:120px;">User</th>
                <th>Action</th>
                <th style="width:120px;">Entity</th>
                <th style="width:80px;">ID</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($logs)) : foreach ($logs as $log) :
            $user = $log->user_id ? get_userdata((int) $log->user_id) : null;
        ?>
            <tr>
                <td><?php echo esc_html($log->created_at); ?></td>
                <td><?php echo esc_html($user ? $user->display_name : 'System'); ?></td>
                <td><?php echo esc_html($log->action); ?></td>
                <td><?php echo esc_html($log->entity_type ?: '—'); ?></td>
                <td><?php echo $log->entity_id ? (int) $log->entity_id : '—'; ?></td>
            </tr>
        <?php endforeach; else : ?>
            <tr><td colspan="5" style="text-align:center;padding:24px;color:#64748B;">No audit log entries yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
