<?php
/**
 * Cleaning report forms linked to accommodations and housekeeping.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Acc_Cleaning {

    const ACTION = 'dg_submit_cleaning_report';
    const CPT = 'dg_cleaning_report';

    /** @var array<int, array{name:string,tasks:array<int,string>}> */
    private static $task_categories = [
        ['name' => 'General', 'tasks' => [
            'Open windows and air property if required',
            'Remove all rubbish from inside property',
            'Replace bin liners',
            'Check for guest belongings left behind',
            'Dust all surfaces',
            'Vacuum entire property',
            'Mop all hard floors',
            'Clean internal glass and mirrors',
            'Wipe down light switches and door handles',
            'Check for maintenance issues and report immediately',
        ]],
        ['name' => 'Kitchen', 'tasks' => [
            'Wash and put away all dishes',
            'Clean sink and tapware',
            'Clean benchtops',
            'Wipe cupboard fronts',
            'Clean microwave inside and out',
            'Clean fridge inside and out',
            'Check fridge is empty',
            'Clean stovetop',
            'Clean rangehood if required',
            'Empty and clean rubbish bin',
            'Refill tea, coffee, sugar and supplies',
            'Check all appliances are clean and working',
        ]],
        ['name' => 'Bathroom', 'tasks' => [
            'Clean toilet thoroughly',
            'Clean basin and vanity',
            'Clean shower screens',
            'Clean shower walls and floor',
            'Polish mirrors',
            'Empty bathroom bin',
            'Replenish toilet paper',
            'Replenish soap, shampoo and conditioner',
            'Check drains are clear',
            'Mop bathroom floor',
        ]],
        ['name' => 'Bedroom', 'tasks' => [
            'Strip used bedding',
            'Check mattress protector',
            'Make bed with fresh linen',
            'Replace pillowcases',
            'Replace towels',
            'Check under bed for items',
            'Dust bedside tables and lamps',
        ]],
        ['name' => 'Living Area', 'tasks' => [
            'Dust all furniture',
            'Clean coffee table',
            'Vacuum lounge and cushions',
            'Arrange cushions and décor',
            'Clean TV screen if required',
            'Check remote controls working',
        ]],
        ['name' => 'Outdoor Area', 'tasks' => [
            'Sweep deck and entry areas',
            'Wipe outdoor furniture',
            'Clean BBQ (if used)',
            'Empty outdoor bins',
            'Remove cobwebs',
            'Check pathways are clear',
            'Tidy firepit area',
            'Restack firewood neatly',
            'Check outdoor lighting',
        ]],
        ['name' => 'Final Guest Presentation', 'tasks' => [
            'Wi-Fi information visible',
            'Welcome guide present',
            'All lights working',
            'Air conditioning functioning',
            'Property smells fresh',
            'Curtains/blinds neatly arranged',
            'Front door glass clean',
            'Exterior entry tidy and welcoming',
        ]],
        ['name' => 'Before Leaving', 'tasks' => [
            'Take arrival-ready photos',
            'Lock all windows and doors',
            'Turn off unnecessary lights',
            'Confirm property is guest-ready',
            'Report any damage, missing items or maintenance issues',
        ]],
    ];

    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_rewrite']);
        add_action('init', [__CLASS__, 'maybe_flag_rewrite_flush'], 100);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'render_cleaning_page']);
        add_shortcode('dg_cleaning_form', [__CLASS__, 'shortcode']);
        add_action('admin_post_nopriv_' . self::ACTION, [__CLASS__, 'handle_submit']);
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle_submit']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('add_meta_boxes', [__CLASS__, 'add_report_meta_box']);
        add_action('add_meta_boxes', [__CLASS__, 'add_accommodation_meta_box']);
        add_filter('manage_' . self::CPT . '_posts_columns', [__CLASS__, 'report_columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [__CLASS__, 'report_column_content'], 10, 2);
        add_action('pre_get_posts', [__CLASS__, 'filter_reports_by_accommodation']);
    }

    public static function maybe_flag_rewrite_flush() {
        if (!get_option('dg_acc_cleaning_rewrite_v1')) {
            update_option('dg_acc_needs_rewrite_flush', 1);
            update_option('dg_acc_cleaning_rewrite_v1', 1);
        }
    }

    public static function filter_reports_by_accommodation($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'edit.php' || ($query->get('post_type') !== self::CPT)) {
            return;
        }

        $accommodation_id = isset($_GET['accommodation_id']) ? (int) $_GET['accommodation_id'] : 0;
        if ($accommodation_id <= 0) {
            return;
        }

        $query->set('meta_key', 'dg_cleaning_accommodation_id');
        $query->set('meta_value', $accommodation_id);
    }

    /** @return array<int, array{name:string,tasks:array<int,string>}> */
    public static function task_categories() {
        return apply_filters('dg_acc_cleaning_task_categories', self::$task_categories);
    }

    public static function register_post_type() {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'Cleaning Reports',
                'singular_name' => 'Cleaning Report',
                'menu_name' => 'Cleaning Reports',
                'add_new' => 'Add Report',
                'add_new_item' => 'Add Cleaning Report',
                'edit_item' => 'View Cleaning Report',
                'view_item' => 'View Cleaning Report',
                'search_items' => 'Search Cleaning Reports',
                'not_found' => 'No cleaning reports found',
                'all_items' => 'All Cleaning Reports',
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title'],
        ]);
    }

    public static function register_rewrite() {
        add_rewrite_rule('^cleaning/([^/]+)/?$', 'index.php?dg_cleaning_slug=$matches[1]', 'top');
        add_rewrite_tag('%dg_cleaning_slug%', '([^&]+)');
    }

    public static function query_vars($vars) {
        $vars[] = 'dg_cleaning_slug';
        return $vars;
    }

    public static function property_by_slug($slug) {
        if (class_exists('DG_Acc_Checkin')) {
            return DG_Acc_Checkin::property_by_slug($slug);
        }

        $posts = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => 1,
            'name' => sanitize_title($slug),
            'post_status' => 'publish',
        ]);

        return $posts ? $posts[0] : null;
    }

    public static function cleaning_hub_url() {
        $page_id = (int) get_option('dg_cleaning_page_id', 0);
        if ($page_id > 0 && get_post_status($page_id) === 'publish') {
            return get_permalink($page_id);
        }

        $page = get_page_by_path('cleaning');
        if ($page && $page->post_status === 'publish') {
            return get_permalink($page);
        }

        return home_url('/cleaning/');
    }

    public static function cleaning_url_for_property($property_id) {
        $property_id = (int) $property_id;
        $base = self::cleaning_hub_url();
        if ($property_id <= 0) {
            return $base;
        }

        return add_query_arg('accommodation', $property_id, $base);
    }

    public static function access_code_required() {
        $code = self::access_code();
        return $code !== '';
    }

    public static function access_code() {
        return (string) apply_filters('dg_acc_cleaning_access_code', get_option('dg_cleaning_access_code', ''));
    }

    public static function register_rest_routes() {
        register_rest_route(DG_REST_NAMESPACE, '/accommodation/cleaning-report', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_rest_submit'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function shortcode($atts) {
        $atts = shortcode_atts([
            'accommodation' => 0,
            'slug' => '',
            'lock' => '',
        ], $atts, 'dg_cleaning_form');

        $property = null;
        if ($atts['accommodation']) {
            $property = get_post((int) $atts['accommodation']);
        } elseif ($atts['slug']) {
            $property = self::property_by_slug($atts['slug']);
        } elseif (!empty($_GET['accommodation'])) {
            $property = get_post((int) $_GET['accommodation']);
        }

        $lock = filter_var($atts['lock'], FILTER_VALIDATE_BOOLEAN) || (bool) $property;

        return self::render_form([
            'property' => ($property && $property->post_type === 'dg_accommodation') ? $property : null,
            'lock_property' => $lock,
        ]);
    }

    public static function render_cleaning_page() {
        $slug = get_query_var('dg_cleaning_slug');
        if (!$slug) {
            return;
        }

        $property = self::property_by_slug($slug);
        if (!$property) {
            status_header(404);
            wp_die('Cleaning form not found for this property.', 'Not found', ['response' => 404]);
        }

        status_header(200);
        nocache_headers();
        echo self::render_form([
            'property' => $property,
            'lock_property' => true,
            'standalone' => true,
        ]);
        exit;
    }

    /**
     * @param array{property?:WP_Post|null,lock_property?:bool,standalone?:bool} $args
     */
    public static function render_form(array $args = []) {
        $properties = get_posts([
            'post_type' => 'dg_accommodation',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        ob_start();
        include __DIR__ . '/../templates/cleaning-form.php';
        return ob_get_clean();
    }

    public static function handle_submit() {
        $result = self::process_submission($_POST);
        if (is_wp_error($result)) {
            self::redirect_with_message($result->get_error_message(), 'error');
        }

        self::redirect_with_message('Cleaning report submitted successfully. Property marked clean.', 'success', (int) $result);
    }

    public static function handle_rest_submit($request) {
        if (class_exists('DG_Marketing_Form_Security')) {
            $guard = DG_Marketing_Form_Security::guard_rest($request, 'cleaning_report');
            if ($guard !== true) {
                return $guard;
            }
        }

        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }

        $result = self::process_submission($params);
        if (is_wp_error($result)) {
            return new WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 400);
        }

        return rest_ensure_response([
            'success' => true,
            'report_id' => (int) $result,
            'message' => 'Cleaning report saved.',
        ]);
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error Report post ID
     */
    public static function process_submission(array $data) {
        if (class_exists('DG_Marketing_Form_Security')) {
            $guard = DG_Marketing_Form_Security::guard_post('cleaning_report', $data);
            if (is_wp_error($guard)) {
                return $guard;
            }
        }

        if (!empty($data['website'])) {
            return 0;
        }

        if (!wp_verify_nonce($data['_wpnonce'] ?? '', self::ACTION)) {
            return new WP_Error('invalid_nonce', 'Security check failed. Please refresh and try again.');
        }

        if (self::access_code_required()) {
            $submitted = sanitize_text_field($data['access_code'] ?? '');
            if (!hash_equals(self::access_code(), $submitted)) {
                return new WP_Error('invalid_code', 'Invalid access code.');
            }
        }

        $accommodation_id = (int) ($data['accommodation_id'] ?? 0);
        $property = get_post($accommodation_id);
        if (!$property || $property->post_type !== 'dg_accommodation' || $property->post_status !== 'publish') {
            return new WP_Error('invalid_property', 'Please select a valid accommodation.');
        }

        $cleaner = sanitize_text_field($data['cleaner'] ?? '');
        if ($cleaner === '') {
            return new WP_Error('missing_cleaner', 'Cleaner name is required.');
        }

        $signature = sanitize_text_field($data['signature'] ?? '');
        if ($signature === '') {
            return new WP_Error('missing_signature', 'Signature is required.');
        }

        $tasks = self::normalize_tasks($data['tasks'] ?? $data['tasks_json'] ?? '');
        $completed = count(array_filter($tasks, static function ($task) {
            return !empty($task['completed']);
        }));
        $total = count($tasks);

        if ($total > 0 && $completed < $total) {
            return new WP_Error('incomplete_checklist', 'Please complete every checklist item before submitting.');
        }

        $report_date = sanitize_text_field($data['report_date'] ?? $data['date'] ?? current_time('Y-m-d'));
        $departure_time = sanitize_text_field($data['departure_time'] ?? $data['departureTime'] ?? '');
        $notes = sanitize_textarea_field($data['notes'] ?? '');

        $title = sprintf(
            '%s — %s (%s)',
            $property->post_title,
            $cleaner,
            $report_date
        );

        $report_id = wp_insert_post([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'post_title' => $title,
        ], true);

        if (is_wp_error($report_id)) {
            return $report_id;
        }

        update_post_meta($report_id, 'dg_cleaning_accommodation_id', $accommodation_id);
        update_post_meta($report_id, 'dg_cleaning_accommodation_name', $property->post_title);
        update_post_meta($report_id, 'dg_cleaning_date', $report_date);
        update_post_meta($report_id, 'dg_cleaning_cleaner', $cleaner);
        update_post_meta($report_id, 'dg_cleaning_departure_time', $departure_time);
        update_post_meta($report_id, 'dg_cleaning_notes', $notes);
        update_post_meta($report_id, 'dg_cleaning_signature', $signature);
        update_post_meta($report_id, 'dg_cleaning_tasks', wp_json_encode($tasks));
        update_post_meta($report_id, 'dg_cleaning_completed_count', $completed);
        update_post_meta($report_id, 'dg_cleaning_total_count', $total);
        update_post_meta($report_id, 'dg_cleaning_submitted_at', current_time('mysql'));

        self::apply_to_housekeeping($accommodation_id, $report_id, $notes, $cleaner, $report_date);

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'entity_type' => 'accommodation',
                'entity_id' => $accommodation_id,
                'activity_type' => 'cleaning_report',
                'subject' => 'Cleaning report submitted',
                'content' => sprintf('%s completed cleaning on %s (%d/%d tasks).', $cleaner, $report_date, $completed, $total),
                'metadata' => [
                    'report_id' => $report_id,
                    'notes' => $notes,
                ],
            ]);
        }

        self::notify_admin($report_id, $property, $cleaner, $report_date, $notes, $completed, $total);

        do_action('dg_acc_cleaning_report_submitted', $report_id, $accommodation_id, $data);

        return (int) $report_id;
    }

    /**
     * @param mixed $raw
     * @return array<int, array{text:string,completed:bool}>
     */
    private static function normalize_tasks($raw) {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode(wp_unslash($raw), true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $tasks = [];
        foreach ($raw as $task) {
            if (!is_array($task)) {
                continue;
            }
            $text = sanitize_text_field($task['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $tasks[] = [
                'text' => $text,
                'completed' => !empty($task['completed']),
            ];
        }

        return $tasks;
    }

    private static function apply_to_housekeeping($accommodation_id, $report_id, $notes, $cleaner, $report_date) {
        update_post_meta($accommodation_id, 'dg_housekeeping_status', 'clean');
        update_post_meta($accommodation_id, 'dg_housekeeping_last_cleaned', current_time('mysql'));
        update_post_meta($accommodation_id, 'dg_housekeeping_last_report_id', (int) $report_id);

        if ($notes !== '') {
            $existing = trim((string) get_post_meta($accommodation_id, 'dg_housekeeping_notes', true));
            $entry = sprintf("[%s · %s] %s", $report_date, $cleaner, $notes);
            update_post_meta($accommodation_id, 'dg_housekeeping_notes', $existing === '' ? $entry : $existing . "\n" . $entry);
        }
    }

    private static function notify_admin($report_id, $property, $cleaner, $report_date, $notes, $completed, $total) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        $edit_link = admin_url('post.php?post=' . (int) $report_id . '&action=edit');
        $subject = sprintf('[CVH] Cleaning report — %s (%s)', $property->post_title, $report_date);
        $body = "A cleaning report was submitted.\n\n"
            . "Property: {$property->post_title}\n"
            . "Date: {$report_date}\n"
            . "Cleaner: {$cleaner}\n"
            . "Tasks: {$completed}/{$total} complete\n";

        if ($notes !== '') {
            $body .= "\nNotes:\n{$notes}\n";
        }

        $body .= "\nView in admin: {$edit_link}\n";

        wp_mail($admin_email, $subject, $body);
    }

    private static function redirect_with_message($message, $type = 'success', $report_id = 0) {
        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = home_url('/cleaning/');
        }

        $args = [
            'cleaning_message' => rawurlencode($message),
            'cleaning_status' => $type,
        ];
        if ($report_id > 0) {
            $args['cleaning_report'] = $report_id;
        }

        wp_safe_redirect(add_query_arg($args, $redirect));
        exit;
    }

    public static function get_reports_for_property($property_id, $limit = 10) {
        return get_posts([
            'post_type' => self::CPT,
            'posts_per_page' => (int) $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => 'dg_cleaning_accommodation_id',
            'meta_value' => (int) $property_id,
        ]);
    }

    public static function add_report_meta_box() {
        add_meta_box(
            'dg_cleaning_report_details',
            'Report details',
            [__CLASS__, 'render_report_meta_box'],
            self::CPT,
            'normal',
            'high'
        );
    }

    public static function render_report_meta_box($post) {
        $fields = [
            'Accommodation' => get_post_meta($post->ID, 'dg_cleaning_accommodation_name', true),
            'Date' => get_post_meta($post->ID, 'dg_cleaning_date', true),
            'Cleaner' => get_post_meta($post->ID, 'dg_cleaning_cleaner', true),
            'Guest departure' => get_post_meta($post->ID, 'dg_cleaning_departure_time', true),
            'Signature' => get_post_meta($post->ID, 'dg_cleaning_signature', true),
            'Submitted' => get_post_meta($post->ID, 'dg_cleaning_submitted_at', true),
            'Completed tasks' => get_post_meta($post->ID, 'dg_cleaning_completed_count', true) . ' / ' . get_post_meta($post->ID, 'dg_cleaning_total_count', true),
        ];

        echo '<table class="form-table"><tbody>';
        foreach ($fields as $label => $value) {
            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }
        echo '</tbody></table>';

        $notes = get_post_meta($post->ID, 'dg_cleaning_notes', true);
        if ($notes) {
            echo '<p><strong>Notes / maintenance</strong></p><pre style="white-space:pre-wrap;">' . esc_html($notes) . '</pre>';
        }

        $tasks_json = get_post_meta($post->ID, 'dg_cleaning_tasks', true);
        $tasks = json_decode((string) $tasks_json, true);
        if (is_array($tasks) && $tasks) {
            echo '<p><strong>Checklist</strong></p><ul style="margin-left:1.2em;">';
            foreach ($tasks as $task) {
                $mark = !empty($task['completed']) ? '✓' : '☐';
                echo '<li>' . esc_html($mark . ' ' . ($task['text'] ?? '')) . '</li>';
            }
            echo '</ul>';
        }

        $acc_id = (int) get_post_meta($post->ID, 'dg_cleaning_accommodation_id', true);
        if ($acc_id) {
            echo '<p><a class="button" href="' . esc_url(get_edit_post_link($acc_id)) . '">View accommodation</a></p>';
        }
    }

    public static function add_accommodation_meta_box() {
        add_meta_box(
            'dg_acc_cleaning_reports',
            '🧹 Cleaning reports',
            [__CLASS__, 'render_accommodation_meta_box'],
            'dg_accommodation',
            'normal',
            'default'
        );
    }

    public static function render_accommodation_meta_box($post) {
        $form_url = self::cleaning_url_for_property($post->ID);
        $reports = self::get_reports_for_property($post->ID, 5);
        ?>
        <p>
            <strong>Cleaning form URL:</strong><br>
            <code style="word-break:break-all;"><?php echo esc_html($form_url); ?></code>
        </p>
        <p class="description">Share this link with cleaners — the accommodation is pre-selected.</p>
        <?php if ($reports) : ?>
            <table class="widefat striped" style="margin-top:12px;">
                <thead>
                    <tr><th>Date</th><th>Cleaner</th><th>Tasks</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $report) : ?>
                    <tr>
                        <td><?php echo esc_html(get_post_meta($report->ID, 'dg_cleaning_date', true)); ?></td>
                        <td><?php echo esc_html(get_post_meta($report->ID, 'dg_cleaning_cleaner', true)); ?></td>
                        <td><?php echo esc_html(get_post_meta($report->ID, 'dg_cleaning_completed_count', true) . '/' . get_post_meta($report->ID, 'dg_cleaning_total_count', true)); ?></td>
                        <td><a href="<?php echo esc_url(get_edit_post_link($report->ID)); ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . self::CPT . '&accommodation_id=' . (int) $post->ID)); ?>">All reports for this property</a></p>
        <?php else : ?>
            <p class="description">No cleaning reports yet.</p>
        <?php endif;
    }

    public static function report_columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['accommodation'] = 'Accommodation';
                $new['cleaner'] = 'Cleaner';
                $new['report_date'] = 'Date';
                $new['tasks'] = 'Tasks';
            }
        }
        return $new;
    }

    public static function report_column_content($column, $post_id) {
        switch ($column) {
            case 'accommodation':
                echo esc_html(get_post_meta($post_id, 'dg_cleaning_accommodation_name', true));
                break;
            case 'cleaner':
                echo esc_html(get_post_meta($post_id, 'dg_cleaning_cleaner', true));
                break;
            case 'report_date':
                echo esc_html(get_post_meta($post_id, 'dg_cleaning_date', true));
                break;
            case 'tasks':
                echo esc_html(get_post_meta($post_id, 'dg_cleaning_completed_count', true) . '/' . get_post_meta($post_id, 'dg_cleaning_total_count', true));
                break;
        }
    }
}
