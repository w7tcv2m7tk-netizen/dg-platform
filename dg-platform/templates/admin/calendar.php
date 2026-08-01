<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>📅 Calendar</h1>
    <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Event created.</p></div><?php endif; ?>
    <div class="dg-panel">
        <h3>Add Event</h3>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="dg_save_calendar_event">
            <?php wp_nonce_field('dg_save_calendar_event'); ?>
            <div class="dg-form-grid">
                <div><label>Title</label><input type="text" name="title" class="regular-text" required></div>
                <div><label>Type</label>
                    <select name="event_type">
                        <option value="meeting">Meeting</option>
                        <option value="appointment">Appointment</option>
                        <option value="appraisal">Appraisal</option>
                        <option value="inspection">Inspection</option>
                        <option value="reminder">Reminder</option>
                    </select>
                </div>
                <div><label>Start</label><input type="datetime-local" name="start_at" required></div>
                <div><label>End</label><input type="datetime-local" name="end_at"></div>
                <div class="full-width"><label>Location</label><input type="text" name="location" class="regular-text"></div>
            </div>
            <p class="submit"><button type="submit" class="button button-primary">Add Event</button></p>
        </form>
    </div>
    <div class="dg-panel">
        <h3>Upcoming Events</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Title</th><th>Type</th><th>Start</th><th>Status</th></tr></thead>
            <tbody>
                <?php if ($events) : foreach ($events as $event) : ?>
                    <tr>
                        <td><?php echo esc_html($event->title); ?></td>
                        <td><?php echo esc_html($event->event_type); ?></td>
                        <td><?php echo esc_html($event->start_at); ?></td>
                        <td><?php echo esc_html($event->status); ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="4">No upcoming events.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
