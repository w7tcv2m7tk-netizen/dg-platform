<?php
/**
 * Housekeeping status per accommodation property.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Housekeeping {

    const STATUSES = [
        'clean' => 'Clean & ready',
        'dirty' => 'Needs cleaning',
        'in_progress' => 'Cleaning in progress',
        'inspection' => 'Awaiting inspection',
    ];

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_dg_accommodation', [__CLASS__, 'save_meta'], 20);
        add_action('dg_platform_register_menus', [__CLASS__, 'register_menu'], 16);
        add_filter('dg_platform_dashboard_widgets', [__CLASS__, 'dashboard_widgets']);
    }

    public static function register_menu() {
        if (!class_exists('DG_Acc_Permissions') || !DG_Acc_Permissions::can_view_bookings()) {
            return;
        }
        add_submenu_page(
            'dg-platform',
            'Housekeeping',
            '🧹 Housekeeping',
            DG_Acc_Permissions::menu_cap_bookings(),
            'dg-acc-housekeeping',
            [__CLASS__, 'render_board']
        );
    }

    public static function dashboard_widgets($widgets) {
        if (!class_exists('DG_Acc_Permissions') || !DG_Acc_Permissions::can_view_bookings()) {
            return $widgets;
        }
        $summary = self::status_summary();
        $needs = ($summary['dirty'] ?? 0) + ($summary['in_progress'] ?? 0);
        if ($needs > 0) {
            $widgets[] = [
                'id' => 'acc_housekeeping',
                'label' => 'Properties need cleaning',
                'value' => $needs,
                'color' => '#F59E0B',
            ];
        }
        return $widgets;
    }

    public static function status_summary() {
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        $counts['unknown'] = 0;
        foreach (get_posts(['post_type' => 'dg_accommodation', 'posts_per_page' => -1, 'post_status' => 'publish']) as $p) {
            $status = get_post_meta($p->ID, 'dg_housekeeping_status', true) ?: 'unknown';
            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;
        }
        return $counts;
    }

    public static function add_meta_box() {
        add_meta_box(
            'dg_acc_housekeeping',
            '🧹 Housekeeping',
            [__CLASS__, 'render_meta_box'],
            'dg_accommodation',
            'side',
            'default'
        );
    }

    public static function render_meta_box($post) {
        wp_nonce_field('dg_acc_housekeeping_save', 'dg_acc_housekeeping_nonce');
        $status = get_post_meta($post->ID, 'dg_housekeeping_status', true) ?: 'clean';
        $notes = get_post_meta($post->ID, 'dg_housekeeping_notes', true);
        $last = get_post_meta($post->ID, 'dg_housekeeping_last_cleaned', true);
        ?>
        <p>
            <label for="dg_housekeeping_status"><strong>Status</strong></label><br>
            <select name="dg_housekeeping_status" id="dg_housekeeping_status" style="width:100%;">
                <?php foreach (self::STATUSES as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="dg_housekeeping_notes"><strong>Notes</strong></label><br>
            <textarea name="dg_housekeeping_notes" id="dg_housekeeping_notes" rows="3" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
        </p>
        <?php if ($last) : ?>
            <p class="description">Last cleaned: <?php echo esc_html($last); ?></p>
        <?php endif; ?>
        <?php
    }

    public static function save_meta($post_id) {
        if (!isset($_POST['dg_acc_housekeeping_nonce']) || !wp_verify_nonce($_POST['dg_acc_housekeeping_nonce'], 'dg_acc_housekeeping_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $status = sanitize_text_field($_POST['dg_housekeeping_status'] ?? 'clean');
        if (!isset(self::STATUSES[$status])) {
            $status = 'clean';
        }
        $prev = get_post_meta($post_id, 'dg_housekeeping_status', true);
        update_post_meta($post_id, 'dg_housekeeping_status', $status);
        update_post_meta($post_id, 'dg_housekeeping_notes', sanitize_textarea_field($_POST['dg_housekeeping_notes'] ?? ''));
        if ($status === 'clean' && $prev !== 'clean') {
            update_post_meta($post_id, 'dg_housekeeping_last_cleaned', current_time('mysql'));
        }
    }

    public static function render_board() {
        if (!DG_Acc_Permissions::can_view_bookings()) {
            wp_die('Unauthorized');
        }

        if (isset($_POST['dg_housekeeping_bulk']) && check_admin_referer('dg_housekeeping_board')) {
            foreach ((array) ($_POST['property_status'] ?? []) as $id => $status) {
                $id = (int) $id;
                $status = sanitize_text_field($status);
                if ($id && isset(self::STATUSES[$status])) {
                    update_post_meta($id, 'dg_housekeeping_status', $status);
                    if ($status === 'clean') {
                        update_post_meta($id, 'dg_housekeeping_last_cleaned', current_time('mysql'));
                    }
                }
            }
            echo '<div class="notice notice-success"><p>Housekeeping statuses updated.</p></div>';
        }

        $properties = get_posts(['post_type' => 'dg_accommodation', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>🧹 Housekeeping</h1>
            <p class="dg-muted-subtle">Cleaning status for each property. Update after turnovers.</p>
            <form method="post">
                <?php wp_nonce_field('dg_housekeeping_board'); ?>
                <input type="hidden" name="dg_housekeeping_bulk" value="1">
                <div class="dg-panel">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Status</th>
                                <th>Last cleaned</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($properties as $p) :
                            $status = get_post_meta($p->ID, 'dg_housekeeping_status', true) ?: 'unknown';
                            $last = get_post_meta($p->ID, 'dg_housekeeping_last_cleaned', true);
                            $notes = get_post_meta($p->ID, 'dg_housekeeping_notes', true);
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>"><?php echo esc_html($p->post_title); ?></a></td>
                                <td>
                                    <select name="property_status[<?php echo (int) $p->ID; ?>]">
                                        <?php foreach (self::STATUSES as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><?php echo $last ? esc_html($last) : '—'; ?></td>
                                <td><?php echo $notes ? esc_html($notes) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php submit_button('Save all statuses'); ?>
                </div>
            </form>
        </div>
        <?php
    }
}

DG_Acc_Housekeeping::init();
