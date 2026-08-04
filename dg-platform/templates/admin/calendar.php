<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>📅 Calendar</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Event created.</p></div><?php endif; ?>
    <?php if (isset($_GET['updated'])) : ?><div class="notice notice-success"><p>Event updated.</p></div><?php endif; ?>
    <div class="dg-panel">
        <h3><?php echo !empty($edit_event) ? 'Edit Event' : 'Add Event'; ?></h3>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="dg_save_calendar_event">
            <?php wp_nonce_field('dg_save_calendar_event'); ?>
            <?php if (!empty($edit_event)) : ?>
                <input type="hidden" name="event_id" value="<?php echo (int) $edit_event->id; ?>">
            <?php endif; ?>
            <div class="dg-form-grid">
                <div><label>Title</label><input type="text" name="title" class="regular-text" required value="<?php echo esc_attr($edit_event->title ?? ''); ?>"></div>
                <div><label>Type</label>
                    <select name="event_type">
                        <?php foreach (['meeting', 'appointment', 'appraisal', 'inspection', 'reminder'] as $type) : ?>
                            <option value="<?php echo esc_attr($type); ?>" <?php selected($edit_event->event_type ?? 'meeting', $type); ?>><?php echo esc_html(ucfirst($type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Start</label><input type="datetime-local" name="start_at" required value="<?php echo esc_attr(!empty($edit_event->start_at) ? date('Y-m-d\TH:i', strtotime($edit_event->start_at)) : ''); ?>"></div>
                <div><label>End</label><input type="datetime-local" name="end_at" value="<?php echo esc_attr(!empty($edit_event->end_at) ? date('Y-m-d\TH:i', strtotime($edit_event->end_at)) : ''); ?>"></div>
                <div><label>Status</label>
                    <select name="status">
                        <?php foreach (['scheduled', 'confirmed', 'cancelled', 'completed'] as $status) : ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($edit_event->status ?? 'scheduled', $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="full-width"><label>Location</label><input type="text" name="location" class="regular-text" value="<?php echo esc_attr($edit_event->location ?? ''); ?>"></div>
            </div>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php echo !empty($edit_event) ? 'Update Event' : 'Add Event'; ?></button>
                <?php if (!empty($edit_event)) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-calendar')); ?>" class="button">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    <div class="dg-panel">
        <h3>Events</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Title</th><th>Type</th><th>Start</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($events) : foreach ($events as $event) : ?>
                    <tr>
                        <td><?php echo esc_html($event->title); ?></td>
                        <td><?php echo esc_html($event->event_type); ?></td>
                        <td><?php echo esc_html($event->start_at); ?></td>
                        <td><?php echo esc_html($event->status); ?></td>
                        <td>
                            <?php if (DG_Permissions::current_user_can('dg_manage_calendar')) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-calendar&edit=' . (int) $event->id)); ?>">Edit</a>
                                · <?php echo DG_Admin_Delete::link('dg_delete_calendar_event', (int) $event->id); ?>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="5">No events yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
