<?php
/**
 * DG Platform - Accommodation Module
 * Complete integration preserving all functionality from the 10 snippets
 * 
 * @package DG_Platform
 * @subpackage Accommodation
 * @version 10.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// MODULE CLASS
// ============================================================

class DG_Module_Accommodation {
    
    private static $instance = null;
    private $platform = null;
    private $module_key = 'accommodation';
    
    public static function get_instance($platform = null) {
        if (null === self::$instance) {
            self::$instance = new self($platform);
        }
        return self::$instance;
    }
    
    private function __construct($platform) {
        $this->platform = $platform;
        
        if ($platform) {
            $platform->register_module($this->module_key, $this);
        }
        
        $this->init();
    }

    private function load_includes() {
        $dir = __DIR__ . '/includes/';
        foreach ([
            'class-acc-permissions.php',
            'class-acc-guests.php',
            'class-acc-reports.php',
            'class-acc-admin-views.php',
            'class-acc-ota.php',
            'class-acc-payments.php',
            'class-acc-admin-pages.php',
            'class-acc-dev-api.php',
            'class-acc-admin-notifications.php',
            'class-acc-housekeeping.php',
            'class-acc-checkin.php',
            'class-acc-shortcodes.php',
        ] as $file) {
            $path = $dir . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
    
    // ============================================================
    // INITIALIZATION - ALL HOOKS AND FILTERS
    // ============================================================
    
    private function init() {
        $this->load_includes();

        add_action('dg_platform_register_menus', [$this, 'register_platform_menus'], 15);
        add_action('dg_platform_quick_actions', [$this, 'quick_actions']);
        add_filter('dg_platform_dashboard_widgets', [$this, 'dashboard_widgets']);

        // Post Types & Taxonomies
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'prepopulate_types']);
        add_action('init', [$this, 'flush_rewrites']);
        
        // Admin Columns - Accommodation
        add_filter('manage_dg_accommodation_posts_columns', [$this, 'admin_columns_accommodation']);
        add_action('manage_dg_accommodation_posts_custom_column', [$this, 'admin_column_content_accommodation'], 10, 2);
        add_filter('manage_edit-dg_accommodation_sortable_columns', [$this, 'make_accommodation_columns_sortable']);
        add_action('pre_get_posts', [$this, 'accommodation_orderby_meta']);
        
        // Admin Columns - Bookings
        add_filter('manage_dg_booking_posts_columns', [$this, 'admin_columns_booking']);
        add_action('manage_dg_booking_posts_custom_column', [$this, 'admin_column_content_booking'], 10, 2);
        add_filter('manage_edit-dg_booking_sortable_columns', [$this, 'booking_sortable_columns']);
        add_action('pre_get_posts', [$this, 'booking_orderby_meta']);
        
        // Booking Filters
        add_action('restrict_manage_posts', [$this, 'booking_status_filter']);
        add_action('pre_get_posts', [$this, 'booking_filter_by_status']);
        add_action('restrict_manage_posts', [$this, 'booking_date_filter']);
        add_action('pre_get_posts', [$this, 'booking_filter_by_date']);
        
        // Bulk Actions
        add_filter('bulk_actions-edit-dg_booking', [$this, 'booking_bulk_actions']);
        add_action('admin_init', [$this, 'booking_bulk_action_handler']);
        add_action('admin_notices', [$this, 'booking_bulk_action_notice']);
        
        // Quick Edit
        add_action('quick_edit_custom_box', [$this, 'booking_quick_edit_fields'], 10, 2);
        add_action('save_post_dg_booking', [$this, 'booking_save_quick_edit']);
        add_action('admin_footer', [$this, 'booking_quick_edit_scripts']);
        add_action('quick_edit_custom_box', [$this, 'add_quick_edit_fields'], 10, 2);
        add_action('save_post_dg_accommodation', [$this, 'save_quick_edit_fields']);
        add_action('admin_footer', [$this, 'quick_edit_scripts']);
        
        // Bulk Edit
        add_action('bulk_edit_custom_box', [$this, 'add_bulk_edit_fields'], 10, 2);
        
        // Meta Boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_dg_accommodation', [$this, 'save_accommodation_meta']);
        add_action('save_post_dg_booking', [$this, 'save_booking_meta']);
        add_action('save_post_dg_guest', [$this, 'save_guest_meta']);
        add_action('save_post_dg_guest', [$this, 'sync_guest_to_core'], 25);
        
        // Admin Notices
        add_action('admin_notices', [$this, 'saturday_restriction_notice']);
        
        // Admin Menus
        add_action('admin_menu', [$this, 'add_admin_menus'], 20);
        add_action('admin_menu', [$this, 'reorder_accommodation_submenus'], 9999);
        add_action('admin_menu', [$this, 'add_visible_menu_separators'], 9998);
        add_action('admin_head', [$this, 'menu_separator_css']);
        
        // Admin Bar
        add_action('admin_bar_menu', [$this, 'update_admin_bar_menu'], 999);
        
        // AJAX Handlers (enquiry/contact remain on module)
        add_action('wp_ajax_dg_submit_enquiry', [$this, 'handle_enquiry_submission']);
        add_action('wp_ajax_nopriv_dg_submit_enquiry', [$this, 'handle_enquiry_submission']);
        add_action('wp_ajax_dg_submit_contact', [$this, 'handle_contact_submission']);
        add_action('wp_ajax_nopriv_dg_submit_contact', [$this, 'handle_contact_submission']);
        
        // Guest Handling
        add_action('dg_booking_confirmed', [$this, 'upsert_guest_from_booking']);
        add_action('save_post_dg_booking', [$this, 'upsert_guest_from_booking_on_save'], 20);
        
        // Cron Jobs
        add_action('dg_cleanup_expired_bookings', [$this, 'cleanup_expired_bookings']);
        add_action('admin_init', [$this, 'cleanup_orphaned_ota_bookings']);
    }
    
    public function register_platform_menus() {
        if (!DG_Acc_Permissions::can_view_bookings()) {
            return;
        }
        add_submenu_page(
            'dg-platform',
            'Accommodation',
            '🏨 Accommodation',
            DG_Acc_Permissions::menu_cap_bookings(),
            'dg-acc-dashboard',
            ['DG_Acc_Admin_Views', 'render_dashboard']
        );
    }

    public function dashboard_widgets($widgets) {
        if (!class_exists('DG_Acc_Reports') || !DG_Acc_Permissions::can_view_bookings()) {
            return $widgets;
        }
        $summary = DG_Acc_Reports::summary();
        $widgets[] = [
            'id' => 'acc_upcoming',
            'label' => 'Upcoming stays (30d)',
            'value' => $summary['upcoming_30d'],
            'color' => '#34D399',
        ];
        $widgets[] = [
            'id' => 'acc_guests',
            'label' => 'Guests',
            'value' => $summary['guests'],
            'color' => '#8B5CF6',
        ];
        return $widgets;
    }

    public function quick_actions() {
        if (!DG_Acc_Permissions::can_view_bookings()) {
            return;
        }
        echo '<a href="' . esc_url(admin_url('admin.php?page=dg-acc-dashboard')) . '" class="button">🏨 Accommodation</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=dg-admin-calendar')) . '" class="button">📅 Calendar</a>';
    }

    public function sync_guest_to_core($post_id) {
        if (class_exists('DG_Acc_Guests')) {
            DG_Acc_Guests::sync_to_core($post_id);
        }
    }

    // ============================================================
    // POST TYPES - Snippet 1
    // ============================================================
    
    public function register_post_types() {
        // Accommodation Post Type
        register_post_type('dg_accommodation', [
            'labels' => [
                'name' => 'Accommodation',
                'singular_name' => 'Accommodation',
                'menu_name' => 'Accommodation',
                'add_new' => 'Add New Accommodation',
                'add_new_item' => 'Add New Accommodation',
                'edit_item' => 'Edit Accommodation',
                'view_item' => 'View Accommodation',
                'search_items' => 'Search Accommodation',
                'not_found' => 'No accommodation found',
                'not_found_in_trash' => 'No accommodation found in Trash',
                'all_items' => 'All Accommodation',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'accommodation', 'with_front' => false],
            'capability_type' => 'post',
            'has_archive' => 'accommodation',
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-building',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions'],
        ]);
        
        // Booking Post Type (Hidden)
        register_post_type('dg_booking', [
            'labels' => [
                'name' => 'Bookings',
                'singular_name' => 'Booking',
                'menu_name' => 'Bookings',
                'add_new' => 'Add New Booking',
                'add_new_item' => 'Add New Booking',
                'edit_item' => 'Edit Booking',
                'view_item' => 'View Booking',
                'search_items' => 'Search Bookings',
                'not_found' => 'No bookings found',
                'not_found_in_trash' => 'No bookings found in Trash',
                'all_items' => 'All Bookings',
            ],
            'public' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'query_var' => true,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 6,
            'menu_icon' => 'dashicons-money-alt',
            'supports' => ['title', 'author', 'revisions'],
        ]);
        
        // Guest Post Type
        register_post_type('dg_guest', [
            'labels' => [
                'name' => 'Guests',
                'singular_name' => 'Guest',
                'add_new_item' => 'Add New Guest',
                'edit_item' => 'Edit Guest',
                'search_items' => 'Search Guests',
                'not_found' => 'No guests found',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-groups',
        ]);
    }
    
    public function register_taxonomies() {
        register_taxonomy('dg_accommodation_type', 'dg_accommodation', [
            'labels' => [
                'name' => 'Accommodation Types',
                'singular_name' => 'Accommodation Type',
                'search_items' => 'Search Accommodation Types',
                'all_items' => 'All Types',
                'parent_item' => 'Parent Type',
                'parent_item_colon' => 'Parent Type:',
                'edit_item' => 'Edit Type',
                'update_item' => 'Update Type',
                'add_new_item' => 'Add New Type',
                'new_item_name' => 'New Type Name',
                'menu_name' => 'Types',
            ],
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'accommodation-type', 'with_front' => false],
            'show_admin_column' => true,
        ]);
    }
    
    public function prepopulate_types() {
        $types = [
            'Private Studio' => ['slug' => 'private-studio', 'description' => 'Intimate studio accommodation with private entrance'],
            'Tiny Home' => ['slug' => 'tiny-home', 'description' => 'Compact eco-friendly tiny house experience'],
            'Sanctuary Dome' => ['slug' => 'sanctuary-dome', 'description' => 'Premium dome with private sanctuary feel'],
            'Rainforest Dome' => ['slug' => 'rainforest-dome', 'description' => 'Dome accommodation surrounded by rainforest'],
            'Canopy Dome' => ['slug' => 'canopy-dome', 'description' => 'Dome accommodation with canopy views'],
            'Starlight Dome' => ['slug' => 'starlight-dome', 'description' => 'Dome accommodation with stargazing skylights'],
            'The Shed' => ['slug' => 'the-shed', 'description' => 'Rustic converted shed with modern amenities'],
        ];
        
        foreach ($types as $name => $args) {
            if (!term_exists($name, 'dg_accommodation_type')) {
                wp_insert_term($name, 'dg_accommodation_type', $args);
            }
        }
    }
    
    public function flush_rewrites() {
        $this->register_post_types();
        $this->register_taxonomies();
        flush_rewrite_rules();
    }
    
    // ============================================================
    // ADMIN COLUMNS - ACCOMMODATION (Snippet 1 & 2)
    // ============================================================
    
    public function admin_columns_accommodation($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['price'] = 'Price';
                $new_columns['type'] = 'Type';
                $new_columns['sleeps'] = 'Sleeps';
                $new_columns['beds'] = 'Beds';
                $new_columns['baths'] = 'Baths';
                $new_columns['featured'] = 'Featured';
                $new_columns['cleaning_fee'] = '🧹 Cleaning Fee';
                $new_columns['security_deposit'] = '🔒 Security Deposit';
                $new_columns['airbnb_id'] = 'Airbnb ID';
                $new_columns['bookingcom_id'] = 'Booking.com ID';
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }
    
    public function admin_column_content_accommodation($column, $post_id) {
        switch ($column) {
            case 'price':
                $price = get_post_meta($post_id, 'dg_weekday_rate', true);
                echo $price ? '$' . number_format(floatval($price)) . '/night' : '—';
                break;
            case 'type':
                $terms = get_the_terms($post_id, 'dg_accommodation_type');
                echo $terms && !is_wp_error($terms) ? esc_html($terms[0]->name) : '—';
                break;
            case 'sleeps':
                echo get_post_meta($post_id, 'dg_sleeps', true) ?: '—';
                break;
            case 'beds':
                echo get_post_meta($post_id, 'dg_bedrooms', true) ?: '—';
                break;
            case 'baths':
                echo get_post_meta($post_id, 'dg_bathrooms', true) ?: '—';
                break;
            case 'featured':
                echo get_post_meta($post_id, 'dg_featured', true) ? 'Yes' : 'No';
                break;
            case 'cleaning_fee':
                $fee = get_post_meta($post_id, 'dg_cleaning_fee', true);
                echo '<span class="cleaning_fee_column">' . ($fee ? '$' . number_format(floatval($fee), 2) : '—') . '</span>';
                break;
            case 'security_deposit':
                $deposit = get_post_meta($post_id, 'dg_security_deposit', true);
                echo '<span class="security_deposit_column">' . ($deposit ? '$' . number_format(floatval($deposit), 2) : '—') . '</span>';
                break;
            case 'airbnb_id':
                echo get_post_meta($post_id, 'dg_airbnb_id', true) ?: '—';
                break;
            case 'bookingcom_id':
                echo get_post_meta($post_id, 'dg_bookingcom_id', true) ?: '—';
                break;
        }
    }
    
    public function make_accommodation_columns_sortable($columns) {
        $columns['price'] = 'price';
        $columns['sleeps'] = 'sleeps';
        $columns['beds'] = 'beds';
        $columns['baths'] = 'baths';
        $columns['featured'] = 'featured';
        $columns['cleaning_fee'] = 'cleaning_fee';
        $columns['security_deposit'] = 'security_deposit';
        return $columns;
    }
    
    public function accommodation_orderby_meta($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        
        $orderby = $query->get('orderby');
        $meta_keys = [
            'price' => 'dg_weekday_rate',
            'sleeps' => 'dg_sleeps',
            'beds' => 'dg_bedrooms',
            'baths' => 'dg_bathrooms',
            'featured' => 'dg_featured',
            'cleaning_fee' => 'dg_cleaning_fee',
            'security_deposit' => 'dg_security_deposit',
        ];
        
        if (isset($meta_keys[$orderby])) {
            $query->set('meta_key', $meta_keys[$orderby]);
            $query->set('orderby', 'meta_value_num');
        }
    }
    
    // ============================================================
    // ADMIN COLUMNS - BOOKINGS (Snippet 1 & 6)
    // ============================================================
    
    public function admin_columns_booking($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['accommodation'] = 'Accommodation';
                $new_columns['dates'] = 'Dates';
                $new_columns['guests'] = 'Guests';
                $new_columns['total'] = 'Total';
                $new_columns['payment'] = 'Payment';
                $new_columns['status'] = 'Status';
                $new_columns['source'] = 'Source';
                $new_columns['booking_source'] = '📱 Source';
                $new_columns['ota_id'] = '🔑 OTA ID';
                $new_columns['booking_date'] = 'Booked On';
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }
    
    public function admin_column_content_booking($column, $post_id) {
        switch ($column) {
            case 'accommodation':
                echo get_post_meta($post_id, 'dg_booking_accommodation_name', true);
                break;
            case 'dates':
                $checkin = get_post_meta($post_id, 'dg_booking_checkin', true);
                $checkout = get_post_meta($post_id, 'dg_booking_checkout', true);
                echo $checkin && $checkout ? date('d M Y', strtotime($checkin)) . ' - ' . date('d M Y', strtotime($checkout)) : '—';
                break;
            case 'guests':
                echo get_post_meta($post_id, 'dg_booking_guests', true) ?: '2';
                break;
            case 'total':
                echo '$' . number_format(floatval(get_post_meta($post_id, 'dg_booking_total', true)), 2);
                break;
            case 'payment':
                $method = get_post_meta($post_id, 'dg_booking_payment_method', true);
                $paid = get_post_meta($post_id, 'dg_booking_paid', true);
                $paid_text = $paid === 'yes' ? 'Paid' : 'Unpaid';
                if ($method == 'stripe') {
                    echo $paid_text . ' - Credit Card';
                } elseif ($method == 'airbnb') {
                    echo 'Airbnb';
                } elseif ($method == 'bookingcom') {
                    echo 'Booking.com';
                } else {
                    echo $paid_text . ' - PayID';
                }
                break;
            case 'status':
                $status = get_post_meta($post_id, 'dg_booking_status', true);
                $statuses = ['confirmed' => 'Confirmed', 'pending' => 'Pending', 'airbnb' => 'Airbnb', 'bookingcom' => 'Booking.com', 'cancelled' => 'Cancelled', 'completed' => 'Completed'];
                echo isset($statuses[$status]) ? $statuses[$status] : 'Pending';
                break;
            case 'source':
                $source = get_post_meta($post_id, 'dg_booking_source', true);
                echo $source == 'airbnb' ? 'Airbnb' : ($source == 'bookingcom' ? 'Booking.com' : 'Website');
                break;
            case 'booking_source':
                $source = get_post_meta($post_id, 'dg_booking_source', true);
                $source_labels = ['airbnb' => '🏡 Airbnb', 'bookingcom' => '🏨 Booking.com', 'website' => '💻 Website', 'direct' => '📧 Direct'];
                echo isset($source_labels[$source]) ? $source_labels[$source] : $source;
                break;
            case 'ota_id':
                $airbnb_id = get_post_meta($post_id, 'dg_booking_airbnb_id', true);
                $bookingcom_id = get_post_meta($post_id, 'dg_booking_bookingcom_id', true);
                if ($airbnb_id) echo '🏡 ' . esc_html($airbnb_id);
                elseif ($bookingcom_id) echo '🏨 ' . esc_html($bookingcom_id);
                else echo '—';
                break;
            case 'booking_date':
                echo get_the_date('d M Y', $post_id);
                break;
        }
    }
    
    public function booking_sortable_columns($columns) {
        $columns['dates'] = 'dates';
        $columns['total'] = 'total';
        $columns['guests'] = 'guests';
        $columns['booking_date'] = 'booking_date';
        $columns['status'] = 'status';
        return $columns;
    }
    
    public function booking_orderby_meta($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        
        $orderby = $query->get('orderby');
        $meta_keys = [
            'total' => 'dg_booking_total',
            'guests' => 'dg_booking_guests',
            'dates' => 'dg_booking_checkin',
            'status' => 'dg_booking_status',
        ];
        
        if (isset($meta_keys[$orderby])) {
            $query->set('meta_key', $meta_keys[$orderby]);
            $query->set('orderby', $orderby === 'total' || $orderby === 'guests' ? 'meta_value_num' : 'meta_value');
        } elseif ($orderby === 'booking_date') {
            $query->set('orderby', 'date');
        }
    }
    
    // ============================================================
    // BOOKING FILTERS (Snippet 1)
    // ============================================================
    
    public function booking_status_filter() {
        global $typenow;
        if ($typenow !== 'dg_booking') return;
        
        $current_status = isset($_GET['booking_status']) ? $_GET['booking_status'] : '';
        $statuses = ['confirmed' => 'Confirmed', 'pending' => 'Pending', 'airbnb' => 'Airbnb', 'bookingcom' => 'Booking.com', 'cancelled' => 'Cancelled', 'completed' => 'Completed'];
        ?>
        <select name="booking_status">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php selected($current_status, $value); ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    public function booking_filter_by_status($query) {
        global $pagenow, $typenow;
        if ($pagenow !== 'edit.php' || $typenow !== 'dg_booking' || !$query->is_main_query()) return;
        
        if (isset($_GET['booking_status']) && !empty($_GET['booking_status'])) {
            $query->set('meta_key', 'dg_booking_status');
            $query->set('meta_value', $_GET['booking_status']);
            $query->set('meta_compare', '=');
        }
    }
    
    public function booking_date_filter() {
        global $typenow;
        if ($typenow !== 'dg_booking') return;
        
        $from_date = isset($_GET['booking_from']) ? $_GET['booking_from'] : '';
        $to_date = isset($_GET['booking_to']) ? $_GET['booking_to'] : '';
        ?>
        <input type="date" name="booking_from" value="<?php echo $from_date; ?>" placeholder="From" style="width:120px;">
        <input type="date" name="booking_to" value="<?php echo $to_date; ?>" placeholder="To" style="width:120px;">
        <?php
    }
    
    public function booking_filter_by_date($query) {
        global $pagenow, $typenow;
        if ($pagenow !== 'edit.php' || $typenow !== 'dg_booking' || !$query->is_main_query()) return;
        
        $meta_query = [];
        if (isset($_GET['booking_from']) && !empty($_GET['booking_from'])) {
            $meta_query[] = ['key' => 'dg_booking_checkin', 'value' => $_GET['booking_from'], 'compare' => '>=', 'type' => 'DATE'];
        }
        if (isset($_GET['booking_to']) && !empty($_GET['booking_to'])) {
            $meta_query[] = ['key' => 'dg_booking_checkin', 'value' => $_GET['booking_to'], 'compare' => '<=', 'type' => 'DATE'];
        }
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }
    
    // ============================================================
    // BULK ACTIONS - BOOKINGS (Snippet 1)
    // ============================================================
    
    public function booking_bulk_actions($actions) {
        $actions['mark_confirmed'] = 'Mark as Confirmed';
        $actions['mark_pending'] = 'Mark as Pending';
        $actions['mark_cancelled'] = 'Mark as Cancelled';
        return $actions;
    }
    
    public function booking_bulk_action_handler() {
        if (empty($_REQUEST['post']) || empty($_REQUEST['action'])) return;
        
        $action = $_REQUEST['action'];
        $valid_actions = ['mark_confirmed', 'mark_pending', 'mark_cancelled'];
        if (!in_array($action, $valid_actions)) return;
        
        $post_ids = array_map('intval', (array)$_REQUEST['post']);
        $status_map = ['mark_confirmed' => 'confirmed', 'mark_pending' => 'pending', 'mark_cancelled' => 'cancelled'];
        $new_status = $status_map[$action];
        
        foreach ($post_ids as $post_id) {
            update_post_meta($post_id, 'dg_booking_status', $new_status);
            if ($new_status === 'confirmed') {
                delete_post_meta($post_id, '_dg_acc_notified_confirmed');
                do_action('dg_booking_confirmed', $post_id);
                update_post_meta($post_id, '_dg_acc_notified_confirmed', 'yes');
            }
        }
        
        $count = count($post_ids);
        wp_redirect(add_query_arg(['post_type' => 'dg_booking', 'bulk_status_updated' => $count], admin_url('edit.php')));
        exit;
    }
    
    public function booking_bulk_action_notice() {
        if (isset($_GET['bulk_status_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . intval($_GET['bulk_status_updated']) . ' booking(s) status updated successfully.</p></div>';
        }
    }
    
    // ============================================================
    // QUICK EDIT - BOOKINGS (Snippet 1)
    // ============================================================
    
    public function booking_quick_edit_fields($column_name, $post_type) {
        if ($post_type !== 'dg_booking' || $column_name !== 'status') return;
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <h4>Booking Status</h4>
                <label><span class="title">Status</span>
                    <select name="dg_booking_status_quick">
                        <option value="pending">Pending</option><option value="confirmed">Confirmed</option>
                        <option value="airbnb">Airbnb</option><option value="bookingcom">Booking.com</option>
                        <option value="cancelled">Cancelled</option><option value="completed">Completed</option>
                    </select>
                </label>
                <label><span class="title">Payment Status</span>
                    <select name="dg_booking_paid_quick">
                        <option value="no">Unpaid</option><option value="yes">Paid</option>
                    </select>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    public function booking_save_quick_edit($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (get_post_type($post_id) !== 'dg_booking') return;
        
        if (isset($_POST['dg_booking_status_quick'])) {
            update_post_meta($post_id, 'dg_booking_status', $_POST['dg_booking_status_quick']);
        }
        if (isset($_POST['dg_booking_paid_quick'])) {
            update_post_meta($post_id, 'dg_booking_paid', $_POST['dg_booking_paid_quick']);
        }
    }
    
    public function booking_quick_edit_scripts() {
        $screen = get_current_screen();
        if ($screen->id !== 'edit-dg_booking') return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            var wp_inline_edit = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                wp_inline_edit.apply(this, arguments);
                var post_id = 0;
                if (typeof(id) == 'object') post_id = parseInt(this.getId(id));
                if (post_id > 0) {
                    var edit_row = $('#edit-' + post_id);
                    var post_row = $('#post-' + post_id);
                    var status = post_row.find('.column-status').text().trim().toLowerCase();
                    var status_map = {'confirmed':'confirmed','pending':'pending','airbnb':'airbnb','booking.com':'bookingcom','completed':'completed','cancelled':'cancelled'};
                    if (status_map[status]) $('select[name="dg_booking_status_quick"]', edit_row).val(status_map[status]);
                }
            };
        });
        </script>
        <?php
    }
    
    // ============================================================
    // QUICK EDIT - ACCOMMODATION FEES (Snippet 2)
    // ============================================================
    
    public function add_quick_edit_fields($column_name, $post_type) {
        if ($post_type !== 'dg_accommodation') return;
        static $added = false;
        if ($added) return;
        $added = true;
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <h4>💰 Fees</h4>
                <label><span class="title">🧹 Cleaning Fee</span>
                    <span class="input-text-wrap"><input type="text" name="dg_cleaning_fee_quick" value="" placeholder="0.00"></span>
                </label>
                <label><span class="title">🔒 Security Deposit</span>
                    <span class="input-text-wrap"><input type="text" name="dg_security_deposit_quick" value="" placeholder="0.00"></span>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    public function save_quick_edit_fields($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (get_post_type($post_id) !== 'dg_accommodation') return;
        
        if (isset($_POST['dg_cleaning_fee_quick'])) {
            update_post_meta($post_id, 'dg_cleaning_fee', sanitize_text_field($_POST['dg_cleaning_fee_quick']));
        }
        if (isset($_POST['dg_security_deposit_quick'])) {
            update_post_meta($post_id, 'dg_security_deposit', sanitize_text_field($_POST['dg_security_deposit_quick']));
        }
    }
    
    public function quick_edit_scripts() {
        $screen = get_current_screen();
        if ($screen->id !== 'edit-dg_accommodation') return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            var wp_inline_edit = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                wp_inline_edit.apply(this, arguments);
                var post_id = 0;
                if (typeof(id) == 'object') post_id = parseInt(this.getId(id));
                if (post_id > 0) {
                    var edit_row = $('#edit-' + post_id);
                    var post_row = $('#post-' + post_id);
                    var cleaning_fee = $('.cleaning_fee_column', post_row).text().replace('$','').replace(',','').trim();
                    var security_deposit = $('.security_deposit_column', post_row).text().replace('$','').replace(',','').trim();
                    if (cleaning_fee !== '' && cleaning_fee !== '—') $('input[name="dg_cleaning_fee_quick"]', edit_row).val(cleaning_fee);
                    if (security_deposit !== '' && security_deposit !== '—') $('input[name="dg_security_deposit_quick"]', edit_row).val(security_deposit);
                }
            };
        });
        </script>
        <?php
    }
    
    // ============================================================
    // BULK EDIT - ACCOMMODATION (Snippet 2)
    // ============================================================
    
    public function add_bulk_edit_fields($column_name, $post_type) {
        if ($post_type !== 'dg_accommodation') return;
        if ($column_name !== 'title') return;
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <h4>💰 Bulk Edit Fees</h4>
                <label><span class="title">🧹 Cleaning Fee</span>
                    <span class="input-text-wrap"><input type="text" name="dg_cleaning_fee_bulk" value="" placeholder="0.00 or blank to skip"></span>
                </label>
                <label><span class="title">🔒 Security Deposit</span>
                    <span class="input-text-wrap"><input type="text" name="dg_security_deposit_bulk" value="" placeholder="0.00 or blank to skip"></span>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    // ============================================================
    // SATURDAY RESTRICTION NOTICE (Snippet 1)
    // ============================================================
    
    public function saturday_restriction_notice() {
        global $pagenow, $post;
        if (!($pagenow === 'post.php' && isset($post) && get_post_type($post) === 'dg_accommodation')) return;
        
        $next_saturday = date('Y-m-d', strtotime('next Saturday'));
        $next_valid_checkin = $this->next_valid_checkin(date('Y-m-d'));
        ?>
        <div class="notice notice-info is-dismissible" style="border-left-color: #C9A46C; margin-top: 20px;">
            <p style="margin:8px 0;"><strong>Booking Rules</strong></p>
            <ul style="margin:4px 0 8px 20px;color:#555;font-size:13px;line-height:1.6;">
                <li>Saturdays ARE available for overnight stays</li>
                <li>No check-ins on Saturdays</li>
                <li>No check-outs on Saturdays</li>
                <li>Friday check-ins require a minimum 2-night stay</li>
                <li>All other days require a minimum 1-night stay</li>
                <li>Next Saturday: <strong><?php echo date('l, F j, Y', strtotime($next_saturday)); ?></strong>
                    (Next valid check-in: <?php echo date('l, F j, Y', strtotime($next_valid_checkin)); ?>)
                </li>
            </ul>
        </div>
        <?php
    }
    
    private function next_valid_checkin($date) {
        $timestamp = strtotime($date);
        while (date('N', $timestamp) == 6) $timestamp = strtotime('+1 day', $timestamp);
        return date('Y-m-d', $timestamp);
    }
    
    // ============================================================
    // ADMIN MENUS (Snippet 1)
    // ============================================================
    
    public function add_admin_menus() {
        // Bookings submenu
        add_submenu_page('edit.php?post_type=dg_accommodation', 'All Bookings', 'All Bookings', 'manage_options', 'edit.php?post_type=dg_booking');
        add_submenu_page('edit.php?post_type=dg_accommodation', 'Add New Booking', 'Add New Booking', 'manage_options', 'post-new.php?post_type=dg_booking');
        add_submenu_page('edit.php?post_type=dg_accommodation', 'Accommodation Types', 'Types', 'manage_options', 'edit-tags.php?taxonomy=dg_accommodation_type&post_type=dg_accommodation');
        add_submenu_page('edit.php?post_type=dg_accommodation', 'Booking Calendar', '📅 Calendar', 'manage_options', 'dg-admin-calendar', ['DG_Acc_Admin_Pages', 'admin_calendar_page']);
        add_submenu_page('edit.php?post_type=dg_accommodation', 'Booking Settings', 'Booking Settings', 'manage_options', 'dg-booking-settings', ['DG_Acc_Admin_Pages', 'booking_settings_page']);
        add_submenu_page('edit.php?post_type=dg_accommodation', '💳 Stripe Settings', '💳 Stripe Settings', 'manage_options', 'dg-stripe-settings', ['DG_Acc_Admin_Pages', 'stripe_settings_page']);
        add_submenu_page('edit.php?post_type=dg_accommodation', 'Force Sync OTA', '🔄 Sync OTA (All)', 'manage_options', 'dg-force-sync-all', ['DG_Acc_Admin_Pages', 'force_sync_all_page']);
        
        // Guest menu
        add_submenu_page('edit.php?post_type=dg_accommodation', 'Guests', '👥 Guests', 'manage_options', 'edit.php?post_type=dg_guest');
    }
    
    public function reorder_accommodation_submenus() {
        global $submenu;
        if (!isset($submenu['edit.php?post_type=dg_accommodation'])) return;
        
        $menu_items = $submenu['edit.php?post_type=dg_accommodation'];
        $order = ['edit.php?post_type=dg_accommodation', 'post-new.php?post_type=dg_accommodation', 'edit-tags.php?taxonomy=dg_accommodation_type&post_type=dg_accommodation', 'edit.php?post_type=dg_booking', 'post-new.php?post_type=dg_booking', 'dg-admin-calendar', 'dg-booking-settings', 'dg-force-sync-all'];
        
        $reordered = [];
        foreach ($order as $slug) {
            foreach ($menu_items as $item) {
                if ($item[2] == $slug) { $reordered[] = $item; break; }
            }
        }
        foreach ($menu_items as $item) {
            if (!in_array($item[2], $order)) $reordered[] = $item;
        }
        $submenu['edit.php?post_type=dg_accommodation'] = $reordered;
    }
    
    public function add_visible_menu_separators() {
        global $submenu;
        if (!isset($submenu['edit.php?post_type=dg_accommodation'])) return;
        
        $new_submenu = [];
        foreach ($submenu['edit.php?post_type=dg_accommodation'] as $item) {
            $new_submenu[] = $item;
            if ($item[2] == 'post-new.php?post_type=dg_accommodation') {
                $new_submenu[] = ['', 'manage_options', 'dg-separator-1', '──────────────────', ['class' => 'dg-menu-separator']];
            }
            if ($item[2] == 'post-new.php?post_type=dg_booking') {
                $new_submenu[] = ['', 'manage_options', 'dg-separator-2', '──────────────────', ['class' => 'dg-menu-separator']];
            }
            if ($item[2] == 'dg-booking-settings') {
                $new_submenu[] = ['', 'manage_options', 'dg-separator-3', '──────────────────', ['class' => 'dg-menu-separator']];
            }
        }
        $submenu['edit.php?post_type=dg_accommodation'] = $new_submenu;
    }
    
    public function menu_separator_css() {
        ?>
        <style>
            .dg-menu-separator { pointer-events: none; opacity: 0.4; padding-top: 5px !important; padding-bottom: 5px !important; font-size: 11px !important; letter-spacing: 1px; color: #999 !important; }
            .dg-menu-separator:hover { cursor: default; background: transparent !important; }
            .dg-menu-separator .wp-submenu-head { display: none; }
            .dg-menu-separator a { color: #999 !important; cursor: default !important; pointer-events: none; }
            .dg-menu-separator a:hover { color: #999 !important; background: transparent !important; }
        </style>
        <?php
    }
    
    // ============================================================
    // ADMIN BAR MENU (Snippet 1)
    // ============================================================
    
    public function update_admin_bar_menu($wp_admin_bar) {
        $wp_admin_bar->remove_node('new-dg_booking');
        if (current_user_can('edit_posts')) {
            $wp_admin_bar->add_node(['id' => 'new-dg_booking', 'parent' => 'new-content', 'title' => 'Booking', 'href' => admin_url('post-new.php?post_type=dg_booking')]);
        }
        if (current_user_can('manage_options')) {
            $wp_admin_bar->add_node(['id' => 'dg-sync-ota', 'title' => '🔄 Sync OTA', 'href' => admin_url('admin.php?page=dg-force-sync-all')]);
        }
    }
    
    // ============================================================
    // META BOXES (Snippet 2)
    // ============================================================
    
    public function add_meta_boxes() {
        // Accommodation meta boxes
        add_meta_box('dg_accommodation_details', '🏠 Accommodation Details', [$this, 'accommodation_details_meta_box'], 'dg_accommodation', 'normal', 'high');
        add_meta_box('dg_airbnb_meta', '🏠 Airbnb Integration', [$this, 'airbnb_meta_box'], 'dg_accommodation', 'side', 'default');
        add_meta_box('dg_ical_meta', '📅 iCal Sync', [$this, 'ical_meta_box'], 'dg_accommodation', 'side', 'default');
        add_meta_box('dg_airbnb_sync_meta', '🔄 Airbnb Sync', [$this, 'airbnb_sync_meta_box'], 'dg_accommodation', 'side', 'default');
        add_meta_box('dg_booking_settings', '📋 Booking & OTA Settings', [$this, 'booking_settings_meta_box'], 'dg_accommodation', 'side', 'default');
        
        // Booking meta boxes
        add_meta_box('dg_booking_details', '📋 Booking Details', [$this, 'booking_details_meta_box'], 'dg_booking', 'normal', 'high');
        add_meta_box('dg_booking_payment', '💳 Payment Information', [$this, 'booking_payment_meta_box'], 'dg_booking', 'side', 'default');
        add_meta_box('dg_booking_customer', '👤 Customer Information', [$this, 'booking_customer_meta_box'], 'dg_booking', 'side', 'default');
        
        // Guest meta boxes
        add_meta_box('dg_guest_details', 'Guest Details', [$this, 'guest_details_meta_box'], 'dg_guest', 'normal', 'high');
        add_meta_box('dg_guest_history', 'Stay History', [$this, 'guest_history_meta_box'], 'dg_guest', 'normal', 'default');
        add_meta_box('dg_guest_notes', 'Notes & Tags', [$this, 'guest_notes_meta_box'], 'dg_guest', 'side', 'default');
    }
    
    // ============================================================
    // ACCOMMODATION DETAILS META BOX (Snippet 2)
    // ============================================================
    
    public function accommodation_details_meta_box($post) {
        wp_nonce_field('dg_accommodation_details_nonce', 'dg_accommodation_details_nonce');
        ?>
        <style>
            .dg-meta-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top: 10px; }
            .dg-meta-grid .full-width { grid-column: 1 / -1; }
            .dg-meta-field { margin-bottom: 5px; }
            .dg-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; color: #1C2B2A; }
            .dg-meta-field input, .dg-meta-field select, .dg-meta-field textarea { width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; background: #fff; box-sizing: border-box; }
            .dg-meta-field .helper { font-size: 11px; color: #999; margin-top: 2px; }
            .dg-section-title { font-size: 16px; font-weight: 700; color: #1C2B2A; border-bottom: 2px solid #C9A46C; padding-bottom: 8px; margin: 15px 0 10px 0; grid-column: 1 / -1; }
            .dg-gallery-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
            .dg-gallery-preview img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
            .dg-features-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 8px; }
            .dg-features-grid label { display: flex; align-items: center; gap: 6px; font-size: 13px; padding: 4px 0; }
            .dg-features-grid input[type="checkbox"] { width: 16px; height: 16px; }
            .dg-note-box { background: #f0f7ff; border-left: 4px solid #C9A46C; padding: 12px 16px; border-radius: 4px; grid-column: 1 / -1; }
            .dg-note-box strong { color: #1C2B2A; }
            .dg-note-box ul { margin: 6px 0 0 20px; color: #666; font-size: 13px; line-height: 1.6; }
            @media (max-width: 768px) { .dg-meta-grid { grid-template-columns: 1fr 1fr; } .dg-features-grid { grid-template-columns: 1fr 1fr; } }
            @media (max-width: 480px) { .dg-meta-grid { grid-template-columns: 1fr; } .dg-features-grid { grid-template-columns: 1fr; } }
        </style>
        
        <div class="dg-meta-grid">
            <!-- Description -->
            <div class="dg-section-title">📝 Description</div>
            <div class="dg-meta-field full-width">
                <label for="dg_description">Property Description</label>
                <textarea name="dg_description" id="dg_description" rows="6" placeholder="Enter a detailed description of the accommodation. Use line breaks to create paragraphs."><?php echo esc_textarea(get_post_meta($post->ID, 'dg_description', true)); ?></textarea>
                <div class="helper">Press Enter twice for new paragraphs. Formatting will be preserved.</div>
            </div>
            
            <!-- Basic Information -->
            <div class="dg-section-title">📍 Basic Information</div>
            <div class="dg-meta-field">
                <label for="dg_accommodation_type">Accommodation Type</label>
                <select name="dg_accommodation_type">
                    <option value="">— Select Type —</option>
                    <?php
                    $terms = get_terms(['taxonomy' => 'dg_accommodation_type', 'hide_empty' => false]);
                    $selected = wp_get_object_terms($post->ID, 'dg_accommodation_type', ['fields' => 'ids']);
                    $selected_id = !empty($selected) ? $selected[0] : '';
                    foreach ($terms as $term) {
                        echo '<option value="' . esc_attr($term->term_id) . '" ' . selected($selected_id, $term->term_id, false) . '>' . esc_html($term->name) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="dg-meta-field">
                <label for="dg_address">📍 Address</label>
                <input type="text" name="dg_address" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_address', true)); ?>" placeholder="11 Kianga Court, Currumbin Valley QLD 4223">
            </div>
            <div class="dg-meta-field">
                <label for="dg_latitude">🌐 Latitude</label>
                <input type="text" name="dg_latitude" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_latitude', true)); ?>" placeholder="-28.2094">
            </div>
            <div class="dg-meta-field">
                <label for="dg_longitude">🌐 Longitude</label>
                <input type="text" name="dg_longitude" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_longitude', true)); ?>" placeholder="153.3789">
            </div>
            
            <!-- Rates & Discounts -->
            <div class="dg-section-title">💰 Rates & Discounts</div>
            <div class="dg-meta-field">
                <label for="dg_weekday_rate">Weekday Rate ($)</label>
                <input type="number" name="dg_weekday_rate" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_weekday_rate', true)); ?>" placeholder="e.g. 250" step="0.01">
                <div class="helper">Mon-Thu</div>
            </div>
            <div class="dg-meta-field">
                <label for="dg_weekend_rate">Weekend Rate ($)</label>
                <input type="number" name="dg_weekend_rate" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_weekend_rate', true)); ?>" placeholder="e.g. 350" step="0.01">
                <div class="helper">Fri-Sun</div>
            </div>
            <div class="dg-meta-field">
                <label for="dg_weekday_peak_rate">Weekday Peak ($)</label>
                <input type="number" name="dg_weekday_peak_rate" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_weekday_peak_rate', true)); ?>" placeholder="e.g. 300" step="0.01">
            </div>
            <div class="dg-meta-field">
                <label for="dg_weekend_peak_rate">Weekend Peak ($)</label>
                <input type="number" name="dg_weekend_peak_rate" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_weekend_peak_rate', true)); ?>" placeholder="e.g. 400" step="0.01">
            </div>
            <div class="dg-meta-field" style="grid-column:1/-1;">
                <label style="font-weight:600;color:#2D4A2E;">Peak Season Date Range</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px;">
                    <div>
                        <label for="dg_peak_season_start" style="font-weight:normal;">Start (MM-DD)</label>
                        <input type="text" name="dg_peak_season_start" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_peak_season_start', true) ?: '12-15'); ?>" placeholder="12-15">
                        <div class="helper">Default: Dec 15</div>
                    </div>
                    <div>
                        <label for="dg_peak_season_end" style="font-weight:normal;">End (MM-DD)</label>
                        <input type="text" name="dg_peak_season_end" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_peak_season_end', true) ?: '01-15'); ?>" placeholder="01-15">
                        <div class="helper">Default: Jan 15 (next year)</div>
                    </div>
                </div>
            </div>
            <div class="dg-meta-field">
                <label for="dg_last_minute_discount">Last Minute Discount (0-3 days)</label>
                <select name="dg_last_minute_discount">
                    <?php for ($i = 0; $i <= 30; $i += 5): ?>
                        <option value="<?php echo $i; ?>" <?php selected(get_post_meta($post->ID, 'dg_last_minute_discount', true), $i); ?>><?php echo $i; ?>%</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="dg-meta-field">
                <label for="dg_early_bird_discount">Early Bird Discount (3-14 days)</label>
                <select name="dg_early_bird_discount">
                    <?php for ($i = 0; $i <= 30; $i += 5): ?>
                        <option value="<?php echo $i; ?>" <?php selected(get_post_meta($post->ID, 'dg_early_bird_discount', true), $i); ?>><?php echo $i; ?>%</option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <!-- Property Features -->
            <div class="dg-section-title">📐 Property Features</div>
            <div class="dg-meta-field">
                <label for="dg_sleeps">Sleeps</label>
                <input type="number" name="dg_sleeps" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_sleeps', true)); ?>" placeholder="e.g. 4">
            </div>
            <div class="dg-meta-field">
                <label for="dg_bedrooms">Bedrooms</label>
                <input type="number" name="dg_bedrooms" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_bedrooms', true)); ?>" placeholder="e.g. 2">
            </div>
            <div class="dg-meta-field">
                <label for="dg_bathrooms">Bathrooms</label>
                <input type="number" name="dg_bathrooms" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_bathrooms', true)); ?>" placeholder="e.g. 1.5" step="0.5">
            </div>
            <div class="dg-meta-field">
                <label for="dg_max_guests">Max Guests</label>
                <input type="number" name="dg_max_guests" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_max_guests', true)); ?>" placeholder="e.g. 6">
            </div>
            <div class="dg-meta-field">
                <label for="dg_min_nights">Min Nights</label>
                <input type="number" name="dg_min_nights" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_min_nights', true)); ?>" placeholder="e.g. 2">
            </div>
            <div class="dg-meta-field">
                <label for="dg_size">Size (m²)</label>
                <input type="number" name="dg_size" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_size', true)); ?>" placeholder="e.g. 120" step="0.1">
            </div>
            
            <!-- Fees & Deposits -->
            <div class="dg-section-title">💲 Fees & Deposits</div>
            <div class="dg-meta-field">
                <label for="dg_security_deposit">Security Deposit ($)</label>
                <input type="number" name="dg_security_deposit" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_security_deposit', true)); ?>" placeholder="e.g. 500" step="0.01">
            </div>
            <div class="dg-meta-field">
                <label for="dg_cleaning_fee">Cleaning Fee ($)</label>
                <input type="number" name="dg_cleaning_fee" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_cleaning_fee', true)); ?>" placeholder="e.g. 150" step="0.01">
            </div>
            <div class="dg-meta-field">
                <label for="dg_extra_guest_fee">Extra Guest Fee ($)</label>
                <input type="number" name="dg_extra_guest_fee" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_extra_guest_fee', true)); ?>" placeholder="e.g. 50" step="0.01">
            </div>
            
            <!-- Features & Amenities -->
            <div class="dg-section-title">✨ Features & Amenities</div>
            <div class="dg-meta-field full-width">
                <div class="dg-features-grid">
                    <?php
                    $features = ['fire_pit' => '🔥 Fire Pit', 'mountain_views' => '⛰️ Mountain Views', 'sauna' => '🧖 Sauna', 'outdoor_shower' => '🚿 Outdoor Shower', 'air_conditioning' => '❄️ Air Conditioning', 'pet_friendly' => '🐾 Pet Friendly', 'wifi' => '📶 WiFi', 'kitchenette' => '🍳 Kitchenette', 'bbq' => '🥩 BBQ', 'parking' => '🚗 Parking', 'private_deck' => '🏠 Private Deck', 'spa' => '💆 Spa'];
                    $selected_features = get_post_meta($post->ID, 'dg_features', true);
                    $selected_features = is_array($selected_features) ? $selected_features : [];
                    foreach ($features as $key => $label) {
                        $checked = isset($selected_features[$key]) && $selected_features[$key] ? 'checked' : '';
                        echo '<label><input type="checkbox" name="dg_features[' . $key . ']" value="1" ' . $checked . '> ' . $label . '</label>';
                    }
                    ?>
                </div>
                <div class="helper">Select all features that this accommodation offers</div>
            </div>
            
            <!-- Media -->
            <div class="dg-section-title">📸 Media</div>
            <div class="dg-meta-field full-width">
                <label for="dg_gallery">Gallery Images (Image IDs)</label>
                <input type="text" name="dg_gallery" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_gallery', true)); ?>" placeholder="e.g. 123, 456, 789">
                <div class="helper">Enter image IDs separated by commas. Featured image is automatically included.</div>
                <?php
                $gallery_ids = get_post_meta($post->ID, 'dg_gallery', true);
                if (!empty($gallery_ids)) {
                    $ids = array_map('trim', explode(',', $gallery_ids));
                    echo '<div class="dg-gallery-preview">';
                    foreach ($ids as $id) {
                        $img = wp_get_attachment_image_url($id, 'thumbnail');
                        if ($img) echo '<img src="' . esc_url($img) . '" alt="Gallery image">';
                    }
                    echo '</div>';
                }
                ?>
            </div>
            <div class="dg-meta-field full-width">
                <label for="dg_video_url">Video URL</label>
                <input type="url" name="dg_video_url" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_video_url', true)); ?>" placeholder="https://www.youtube.com/watch?v=xxxx">
            </div>
            <div class="dg-meta-field full-width">
                <label for="dg_virtual_tour">Virtual Tour URL</label>
                <input type="url" name="dg_virtual_tour" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_virtual_tour', true)); ?>" placeholder="https://my.matterport.com/show/?m=xxxx">
            </div>
            
            <!-- Availability -->
            <div class="dg-section-title">📅 Availability</div>
            <div class="dg-meta-field">
                <label for="dg_checkin_time">Check-in Time</label>
                <input type="time" name="dg_checkin_time" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_checkin_time', true) ?: '15:00'); ?>">
            </div>
            <div class="dg-meta-field">
                <label for="dg_checkout_time">Check-out Time</label>
                <input type="time" name="dg_checkout_time" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_checkout_time', true) ?: '10:00'); ?>">
            </div>
            <div class="dg-note-box">
                <span class="note-icon">📌</span>
                <strong>Booking Rules:</strong>
                <ul>
                    <li>✅ Saturdays <strong>ARE available</strong> for overnight stays</li>
                    <li>❌ <strong>No check-ins</strong> on Saturdays</li>
                    <li>❌ <strong>No check-outs</strong> on Saturdays</li>
                    <li>📅 Friday check-ins require a <strong>minimum 2-night stay</strong></li>
                    <li>📅 All other days require a <strong>minimum 1-night stay</strong></li>
                </ul>
            </div>
            <div class="dg-meta-field full-width">
                <label for="dg_blocked_dates">Blocked Dates</label>
                <textarea name="dg_blocked_dates" rows="3" placeholder="2024-12-20 to 2025-01-10&#10;2025-04-10 to 2025-04-20"><?php echo esc_textarea(get_post_meta($post->ID, 'dg_blocked_dates', true)); ?></textarea>
                <div class="helper">Enter each blocked date range on a new line. Format: YYYY-MM-DD to YYYY-MM-DD</div>
            </div>
            
            <!-- Featured -->
            <div class="dg-section-title">⭐ Featured</div>
            <div class="dg-meta-field full-width">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="dg_featured" value="1" <?php checked(get_post_meta($post->ID, 'dg_featured', true), 1); ?>>
                    <span style="font-size:16px;">⭐ Featured Accommodation</span>
                </label>
                <div class="helper">Mark this accommodation as featured to display it prominently</div>
            </div>
        </div>
        <?php
    }
    
    // ============================================================
    // AIRBNB META BOX (Snippet 2)
    // ============================================================
    
    public function airbnb_meta_box($post) {
        ?>
        <div class="dg-meta-field">
            <label for="dg_airbnb_id">Airbnb Listing ID</label>
            <input type="text" name="dg_airbnb_id" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_airbnb_id', true)); ?>" placeholder="e.g. 12345678" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
            <div class="helper" style="font-size:11px;color:#999;margin-top:4px;">Find in URL: airbnb.com/rooms/<strong>12345678</strong></div>
        </div>
        <?php
    }
    
    // ============================================================
    // ICAL META BOX (Snippet 2)
    // ============================================================
    
    public function ical_meta_box($post) {
        $ical_url = get_post_meta($post->ID, 'dg_ical_url', true);
        $last_sync = get_post_meta($post->ID, 'dg_ical_last_sync', true);
        ?>
        <div class="dg-meta-field">
            <label for="dg_ical_url">iCal Calendar URL</label>
            <input type="url" name="dg_ical_url" value="<?php echo esc_url($ical_url); ?>" placeholder="https://www.airbnb.com/calendar/ical/..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
            <div class="helper" style="font-size:11px;color:#999;margin-top:4px;">Get from Airbnb → Calendar → Export Calendar</div>
            <?php if ($last_sync): ?>
                <div style="margin-top:8px;font-size:12px;color:#666;">⏱️ Last synced: <strong><?php echo date_i18n('F j, Y g:i A', strtotime($last_sync)); ?></strong></div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // ============================================================
    // AIRBNB SYNC META BOX (Snippet 2)
    // ============================================================
    
    public function airbnb_sync_meta_box($post) {
        ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <p style="margin:0;font-size:13px;color:#666;">Sync Airbnb bookings for this accommodation.</p>
            <button type="button" class="button button-primary" onclick="dgSyncAirbnb(<?php echo $post->ID; ?>)">🔄 Sync Now</button>
            <div id="dg-airbnb-sync-status" style="font-size:12px;color:#666;text-align:center;"></div>
        </div>
        <script>
        function dgSyncAirbnb(postId) {
            var status = document.getElementById('dg-airbnb-sync-status');
            status.textContent = '⏳ Syncing...';
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dg_ota_sync',
                    accommodation_id: postId,
                    source: 'airbnb',
                    nonce: '<?php echo wp_create_nonce('dg_calendar_nonce'); ?>'
                },
                success: function(response) {
                    status.textContent = response.success ? '✅ ' + response.data.message : '❌ ' + response.data.message;
                    status.style.color = response.success ? '#28a745' : '#dc3545';
                },
                error: function() {
                    status.textContent = '❌ Error connecting to server.';
                    status.style.color = '#dc3545';
                }
            });
        }
        </script>
        <?php
    }
    
    // ============================================================
    // BOOKING SETTINGS META BOX (Snippet 8)
    // ============================================================
    
    public function booking_settings_meta_box($post) {
        wp_nonce_field('dg_booking_settings_nonce', 'dg_booking_settings_nonce');
        
        $airbnb_id = get_post_meta($post->ID, 'dg_airbnb_id', true);
        $ical_url = get_post_meta($post->ID, 'dg_ical_url', true);
        $ical_last_sync = get_post_meta($post->ID, 'dg_ical_last_sync', true);
        $bookingcom_id = get_post_meta($post->ID, 'dg_bookingcom_id', true);
        $bookingcom_ical_url = get_post_meta($post->ID, 'dg_bookingcom_ical_url', true);
        $bookingcom_last_sync = get_post_meta($post->ID, 'dg_bookingcom_ical_last_sync', true);
        ?>
        <style>
            .dg-booking-settings-field { margin-bottom: 12px; }
            .dg-booking-settings-field label { display: block; font-weight: 600; margin-bottom: 3px; font-size: 12px; color: #1C2B2A; }
            .dg-booking-settings-field input, .dg-booking-settings-field select { width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; background: #fff; box-sizing: border-box; }
            .dg-booking-settings-field .helper { font-size: 11px; color: #999; margin-top: 2px; }
            .dg-booking-settings-divider { border-top: 2px solid #eee; margin: 15px 0 12px 0; padding-top: 4px; }
            .dg-ota-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
            .dg-ota-badge.airbnb { background: #ff5a5f20; color: #ff5a5f; }
            .dg-ota-badge.bookingcom { background: #00358020; color: #003580; }
            .dg-ota-badge.none { background: #e9ecef; color: #6c757d; }
        </style>
        
        <div class="dg-booking-settings">
            <!-- Airbnb -->
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span style="font-size:16px;">🏡</span>
                <span style="font-weight:600;font-size:13px;">Airbnb</span>
                <span class="dg-ota-badge <?php echo !empty($airbnb_id) ? 'airbnb' : 'none'; ?>"><?php echo !empty($airbnb_id) ? 'Connected' : 'Not Connected'; ?></span>
            </div>
            <div class="dg-booking-settings-field">
                <label for="dg_airbnb_id">Listing ID</label>
                <input type="text" name="dg_airbnb_id" value="<?php echo esc_attr($airbnb_id); ?>" placeholder="e.g. 12345678">
            </div>
            <div class="dg-booking-settings-field">
                <label>iCal URL</label>
                <div style="padding:6px 8px;background:#f5f5f5;border:1px solid #ddd;border-radius:4px;font-size:12px;word-break:break-all;margin-bottom:4px;">
                    <?php echo $ical_url ? esc_url($ical_url) : '<em style="color:#999;">Not set</em>'; ?>
                </div>
                <div style="font-size:11px;color:#666;">🔄 Last synced: <?php echo $ical_last_sync ? date_i18n('M j, Y g:i A', strtotime($ical_last_sync)) : 'Never'; ?></div>
            </div>
            
            <!-- Booking.com -->
            <div class="dg-booking-settings-divider"><span>🏨 Booking.com</span></div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span style="font-size:16px;">🏨</span>
                <span style="font-weight:600;font-size:13px;">Booking.com</span>
                <span class="dg-ota-badge <?php echo !empty($bookingcom_id) ? 'bookingcom' : 'none'; ?>"><?php echo !empty($bookingcom_id) ? 'Connected' : 'Not Connected'; ?></span>
            </div>
            <div class="dg-booking-settings-field">
                <label for="dg_bookingcom_id">Listing ID</label>
                <input type="text" name="dg_bookingcom_id" value="<?php echo esc_attr($bookingcom_id); ?>" placeholder="e.g. 123456789">
            </div>
            <div class="dg-booking-settings-field">
                <label for="dg_bookingcom_ical_url">iCal Import URL</label>
                <input type="url" name="dg_bookingcom_ical_url" value="<?php echo esc_url($bookingcom_ical_url); ?>" placeholder="https://www.booking.com/.../ical">
                <div style="font-size:11px;color:#666;margin-top:4px;">🔄 Last synced: <?php echo $bookingcom_last_sync ? date_i18n('M j, Y g:i A', strtotime($bookingcom_last_sync)) : 'Never'; ?></div>
            </div>
            
            <div style="margin-top:12px;padding:10px;background:#f8f6f2;border-radius:4px;border-left:3px solid #B9A48A;font-size:11px;color:#666;">
                <strong>💡 iCal URL:</strong> Airbnb → Edit → Availability → Export calendar<br>
                <strong>Booking.com:</strong> Property → Calendar → iCal feed
            </div>
        </div>
        <?php
    }
    
    // ============================================================
    // BOOKING DETAILS META BOX (Snippet 9)
    // ============================================================
    
    public function booking_details_meta_box($post) {
        wp_nonce_field('dg_booking_details_nonce', 'dg_booking_details_nonce');
        
        $accommodations = get_posts(['post_type' => 'dg_accommodation', 'posts_per_page' => -1, 'orderby' => 'title']);
        $meta = [
            'accommodation_id' => get_post_meta($post->ID, 'dg_booking_accommodation_id', true),
            'checkin' => get_post_meta($post->ID, 'dg_booking_checkin', true),
            'checkout' => get_post_meta($post->ID, 'dg_booking_checkout', true),
            'nights' => get_post_meta($post->ID, 'dg_booking_nights', true),
            'guests' => get_post_meta($post->ID, 'dg_booking_guests', true),
            'status' => get_post_meta($post->ID, 'dg_booking_status', true) ?: 'pending',
            'source' => get_post_meta($post->ID, 'dg_booking_source', true),
            'ref' => get_post_meta($post->ID, 'dg_booking_ref', true),
            'airbnb_id' => get_post_meta($post->ID, 'dg_booking_airbnb_id', true),
            'bookingcom_id' => get_post_meta($post->ID, 'dg_booking_bookingcom_id', true),
            'message' => get_post_meta($post->ID, 'dg_booking_message', true),
        ];
        ?>
        <style>
            .dg-booking-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
            .dg-booking-meta-grid .full-width { grid-column: 1 / -1; }
            .dg-booking-meta-field { margin-bottom: 5px; }
            .dg-booking-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; color: #1C2B2A; }
            .dg-booking-meta-field input, .dg-booking-meta-field select, .dg-booking-meta-field textarea { width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; background: #fff; box-sizing: border-box; }
            .dg-booking-meta-field .helper { font-size: 11px; color: #999; margin-top: 2px; }
            .dg-booking-meta-field input[readonly] { background: #f5f5f5; color: #666; }
            @media (max-width: 768px) { .dg-booking-meta-grid { grid-template-columns: 1fr; } }
        </style>
        
        <div class="dg-booking-meta-grid">
            <div class="dg-booking-meta-field">
                <label>🏠 Accommodation</label>
                <select name="dg_booking_accommodation_id">
                    <option value="">— Select —</option>
                    <?php foreach ($accommodations as $acc): ?>
                        <option value="<?php echo $acc->ID; ?>" <?php selected($meta['accommodation_id'], $acc->ID); ?>><?php echo esc_html($acc->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="dg-booking-meta-field">
                <label>📋 Booking Ref</label>
                <input type="text" name="dg_booking_ref" value="<?php echo esc_attr($meta['ref']); ?>" readonly>
            </div>
            <div class="dg-booking-meta-field">
                <label>📅 Check-in</label>
                <input type="date" name="dg_booking_checkin" value="<?php echo esc_attr($meta['checkin']); ?>">
            </div>
            <div class="dg-booking-meta-field">
                <label>📅 Check-out</label>
                <input type="date" name="dg_booking_checkout" value="<?php echo esc_attr($meta['checkout']); ?>">
            </div>
            <div class="dg-booking-meta-field">
                <label>🌙 Nights</label>
                <input type="number" name="dg_booking_nights" value="<?php echo esc_attr($meta['nights']); ?>" min="1">
            </div>
            <div class="dg-booking-meta-field">
                <label>👥 Guests</label>
                <input type="number" name="dg_booking_guests" value="<?php echo esc_attr($meta['guests']); ?>" min="1">
            </div>
            <div class="dg-booking-meta-field">
                <label>📌 Status</label>
                <select name="dg_booking_status">
                    <option value="pending" <?php selected($meta['status'], 'pending'); ?>>Pending</option>
                    <option value="confirmed" <?php selected($meta['status'], 'confirmed'); ?>>Confirmed</option>
                    <option value="airbnb" <?php selected($meta['status'], 'airbnb'); ?>>Airbnb</option>
                    <option value="bookingcom" <?php selected($meta['status'], 'bookingcom'); ?>>Booking.com</option>
                    <option value="cancelled" <?php selected($meta['status'], 'cancelled'); ?>>Cancelled</option>
                    <option value="completed" <?php selected($meta['status'], 'completed'); ?>>Completed</option>
                </select>
            </div>
            <div class="dg-booking-meta-field">
                <label>📱 Source</label>
                <select name="dg_booking_source">
                    <option value="website" <?php selected($meta['source'], 'website'); ?>>Website</option>
                    <option value="airbnb" <?php selected($meta['source'], 'airbnb'); ?>>Airbnb</option>
                    <option value="bookingcom" <?php selected($meta['source'], 'bookingcom'); ?>>Booking.com</option>
                    <option value="direct" <?php selected($meta['source'], 'direct'); ?>>Direct</option>
                </select>
            </div>
            <div class="dg-booking-meta-field">
                <label>🏡 Airbnb ID</label>
                <input type="text" name="dg_booking_airbnb_id" value="<?php echo esc_attr($meta['airbnb_id']); ?>" placeholder="e.g. 12345678">
            </div>
            <div class="dg-booking-meta-field">
                <label>🏨 Booking.com ID</label>
                <input type="text" name="dg_booking_bookingcom_id" value="<?php echo esc_attr($meta['bookingcom_id']); ?>" placeholder="e.g. 123456789">
            </div>
            <div class="dg-booking-meta-field full-width">
                <label>💬 Special Requests</label>
                <textarea name="dg_booking_message" rows="3"><?php echo esc_textarea($meta['message']); ?></textarea>
            </div>
        </div>
        <?php
    }
    
    // ============================================================
    // BOOKING PAYMENT META BOX (Snippet 9)
    // ============================================================
    
    public function booking_payment_meta_box($post) {
        $total = get_post_meta($post->ID, 'dg_booking_total', true);
        $subtotal = get_post_meta($post->ID, 'dg_booking_subtotal', true);
        $cleaning_fee = get_post_meta($post->ID, 'dg_booking_cleaning_fee', true);
        $discount_percent = get_post_meta($post->ID, 'dg_booking_discount_percent', true);
        $discount_amount = get_post_meta($post->ID, 'dg_booking_discount_amount', true);
        $discount_type = get_post_meta($post->ID, 'dg_booking_discount_type', true);
        $original_total = get_post_meta($post->ID, 'dg_booking_original_total', true);
        $payment_method = get_post_meta($post->ID, 'dg_booking_payment_method', true);
        $paid = get_post_meta($post->ID, 'dg_booking_paid', true);
        ?>
        <style>
            .dg-payment-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .dg-payment-meta-field { margin-bottom: 3px; }
            .dg-payment-meta-field label { display: block; font-weight: 600; margin-bottom: 3px; font-size: 12px; color: #1C2B2A; }
            .dg-payment-meta-field input, .dg-payment-meta-field select { width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; background: #fff; box-sizing: border-box; }
            .dg-payment-total { font-size: 18px; font-weight: 700; color: #1C2B2A; padding: 10px; background: #f8f5f0; border-radius: 4px; text-align: center; grid-column: 1 / -1; }
        </style>
        
        <div class="dg-payment-meta-grid">
            <div class="dg-payment-total">Total: $<?php echo number_format(floatval($total), 2); ?></div>
            <div class="dg-payment-meta-field">
                <label>💰 Subtotal</label>
                <input type="number" name="dg_booking_subtotal" value="<?php echo esc_attr($subtotal); ?>" step="0.01">
            </div>
            <div class="dg-payment-meta-field">
                <label>🧹 Cleaning Fee</label>
                <input type="number" name="dg_booking_cleaning_fee" value="<?php echo esc_attr($cleaning_fee); ?>" step="0.01">
            </div>
            <div class="dg-payment-meta-field">
                <label>💰 Original Total</label>
                <input type="number" name="dg_booking_original_total" value="<?php echo esc_attr($original_total); ?>" step="0.01">
            </div>
            <div class="dg-payment-meta-field">
                <label>🎉 Discount %</label>
                <input type="number" name="dg_booking_discount_percent" value="<?php echo esc_attr($discount_percent); ?>" step="0.01" min="0" max="100">
            </div>
            <div class="dg-payment-meta-field">
                <label>💰 Discount Amount</label>
                <input type="number" name="dg_booking_discount_amount" value="<?php echo esc_attr($discount_amount); ?>" step="0.01">
            </div>
            <div class="dg-payment-meta-field">
                <label>🏷️ Discount Type</label>
                <select name="dg_booking_discount_type">
                    <option value="">— None —</option>
                    <option value="Last Minute" <?php selected($discount_type, 'Last Minute'); ?>>⚡ Last Minute</option>
                    <option value="Early Bird" <?php selected($discount_type, 'Early Bird'); ?>>🐦 Early Bird</option>
                    <option value="Promotional" <?php selected($discount_type, 'Promotional'); ?>>🎁 Promotional</option>
                </select>
            </div>
            <div class="dg-payment-meta-field">
                <label>💳 Payment Method</label>
                <select name="dg_booking_payment_method">
                    <option value="payid" <?php selected($payment_method, 'payid'); ?>>📱 PayID</option>
                    <option value="stripe" <?php selected($payment_method, 'stripe'); ?>>💳 Credit Card</option>
                    <option value="airbnb" <?php selected($payment_method, 'airbnb'); ?>>🏡 Airbnb</option>
                    <option value="bookingcom" <?php selected($payment_method, 'bookingcom'); ?>>🏨 Booking.com</option>
                </select>
            </div>
            <div class="dg-payment-meta-field">
                <label>✅ Paid Status</label>
                <select name="dg_booking_paid">
                    <option value="no" <?php selected($paid, 'no'); ?>>⏳ Unpaid</option>
                    <option value="yes" <?php selected($paid, 'yes'); ?>>✅ Paid</option>
                </select>
            </div>
        </div>
        <?php
    }
    
    // ============================================================
    // BOOKING CUSTOMER META BOX (Snippet 9)
    // ============================================================
    
    public function booking_customer_meta_box($post) {
        $name = get_post_meta($post->ID, 'dg_booking_name', true);
        $email = get_post_meta($post->ID, 'dg_booking_email', true);
        $phone = get_post_meta($post->ID, 'dg_booking_phone', true);
        $street = get_post_meta($post->ID, 'dg_booking_street_address', true);
        $suburb = get_post_meta($post->ID, 'dg_booking_suburb', true);
        $state = get_post_meta($post->ID, 'dg_booking_state', true);
        $postcode = get_post_meta($post->ID, 'dg_booking_postcode', true);
        $country = get_post_meta($post->ID, 'dg_booking_country', true);
        ?>
        <style>
            .dg-customer-meta-field { margin-bottom: 10px; }
            .dg-customer-meta-field label { display: block; font-weight: 600; margin-bottom: 3px; font-size: 12px; color: #1C2B2A; }
            .dg-customer-meta-field input, .dg-customer-meta-field select { width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; background: #fff; box-sizing: border-box; }
            .dg-customer-address-divider { border-top: 2px solid #eee; margin: 12px 0 10px 0; padding-top: 4px; }
            .dg-customer-address-divider span { font-size: 11px; font-weight: 600; color: #999; text-transform: uppercase; letter-spacing: 1px; }
        </style>
        
        <div class="dg-customer-meta-field">
            <label>👤 Guest Name</label>
            <input type="text" name="dg_booking_name" value="<?php echo esc_attr($name); ?>" placeholder="Full name">
        </div>
        <div class="dg-customer-meta-field">
            <label>📧 Email</label>
            <input type="email" name="dg_booking_email" value="<?php echo esc_attr($email); ?>" placeholder="guest@example.com">
        </div>
        <div class="dg-customer-meta-field">
            <label>📱 Phone</label>
            <input type="text" name="dg_booking_phone" value="<?php echo esc_attr($phone); ?>" placeholder="0400 000 000">
        </div>
        
        <div class="dg-customer-address-divider"><span>📍 Address</span></div>
        <div class="dg-customer-meta-field">
            <label>Street Address</label>
            <input type="text" name="dg_booking_street_address" value="<?php echo esc_attr($street); ?>" placeholder="123 Main Street">
        </div>
        <div class="dg-customer-meta-field">
            <label>Suburb / City</label>
            <input type="text" name="dg_booking_suburb" value="<?php echo esc_attr($suburb); ?>" placeholder="Suburb">
        </div>
        <div class="dg-customer-meta-field">
            <label>State</label>
            <select name="dg_booking_state">
                <option value="">— Select —</option>
                <?php foreach (['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php selected($state, $s); ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="dg-customer-meta-field">
            <label>Postcode</label>
            <input type="text" name="dg_booking_postcode" value="<?php echo esc_attr($postcode); ?>" placeholder="4000">
        </div>
        <div class="dg-customer-meta-field">
            <label>Country</label>
            <select name="dg_booking_country">
                <option value="">— Select —</option>
                <option value="AU" <?php selected($country, 'AU'); ?>>Australia</option>
                <option value="NZ" <?php selected($country, 'NZ'); ?>>New Zealand</option>
                <option value="US" <?php selected($country, 'US'); ?>>United States</option>
                <option value="UK" <?php selected($country, 'UK'); ?>>United Kingdom</option>
            </select>
        </div>
        <?php
    }
    
    // ============================================================
    // GUEST META BOXES (Snippet 10)
    // ============================================================
    
    public function guest_details_meta_box($post) {
        wp_nonce_field('dg_guest_save', 'dg_guest_nonce');
        ?>
        <table class="form-table">
            <tr><th style="width:120px;"><label>Email</label></th>
                <td><input type="email" name="dg_guest_email" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_guest_email', true)); ?>" style="width:100%;max-width:400px;"></td></tr>
            <tr><th><label>Phone</label></th>
                <td><input type="text" name="dg_guest_phone" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_guest_phone', true)); ?>" style="width:100%;max-width:400px;"></td></tr>
            <tr><th><label>Address</label></th>
                <td><input type="text" name="dg_guest_address" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_guest_address', true)); ?>" style="width:100%;max-width:400px;"></td></tr>
            <tr><th><label>Source</label></th>
                <td><input type="text" name="dg_guest_source" value="<?php echo esc_attr(get_post_meta($post->ID, 'dg_guest_source', true)); ?>" style="width:100%;max-width:400px;"></td></tr>
        </table>
        <?php
        $total_stays = intval(get_post_meta($post->ID, 'dg_guest_total_stays', true));
        $total_nights = intval(get_post_meta($post->ID, 'dg_guest_total_nights', true));
        $total_spent = floatval(get_post_meta($post->ID, 'dg_guest_total_spent', true));
        $last_stay = get_post_meta($post->ID, 'dg_guest_last_stay', true);
        ?>
        <div style="margin-top:16px;padding:12px;background:#f0f4f0;border-radius:6px;display:flex;gap:24px;flex-wrap:wrap;">
            <div><strong><?php echo $total_stays; ?></strong><br><span style="color:#666;font-size:12px;">Stays</span></div>
            <div><strong><?php echo $total_nights; ?></strong><br><span style="color:#666;font-size:12px;">Nights</span></div>
            <div><strong>$<?php echo number_format($total_spent, 2); ?></strong><br><span style="color:#666;font-size:12px;">Spent</span></div>
            <div><strong><?php echo $last_stay ?: 'Never'; ?></strong><br><span style="color:#666;font-size:12px;">Last Stay</span></div>
        </div>
        <?php
    }
    
    public function guest_history_meta_box($post) {
        $email = get_post_meta($post->ID, 'dg_guest_email', true);
        if (!$email) { echo '<p style="color:#666;">Save a guest email to see booking history.</p>'; return; }
        
        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'meta_query' => [['key' => 'dg_booking_email', 'value' => $email]],
            'orderby' => 'meta_value', 'meta_key' => 'dg_booking_checkin', 'order' => 'DESC'
        ]);
        if (empty($bookings)) { echo '<p style="color:#666;">No bookings found.</p>'; return; }
        ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#2D4A2E;color:#fff;">
                <th style="padding:8px;text-align:left;">Ref</th><th style="padding:8px;text-align:left;">Accommodation</th>
                <th style="padding:8px;text-align:left;">Check-in</th><th style="padding:8px;text-align:left;">Check-out</th>
                <th style="padding:8px;text-align:left;">Total</th><th style="padding:8px;text-align:left;">Status</th>
            </tr></thead><tbody>
            <?php foreach ($bookings as $i => $b):
                $bg = $i % 2 === 0 ? '#fff' : '#f9f9f9'; ?>
                <tr style="background:<?php echo $bg; ?>;">
                    <td style="padding:8px;"><a href="<?php echo get_edit_post_link($b->ID); ?>"><?php echo get_post_meta($b->ID, 'dg_booking_ref', true); ?></a></td>
                    <td style="padding:8px;"><?php echo get_post_meta($b->ID, 'dg_booking_accommodation_name', true); ?></td>
                    <td style="padding:8px;"><?php echo get_post_meta($b->ID, 'dg_booking_checkin', true); ?></td>
                    <td style="padding:8px;"><?php echo get_post_meta($b->ID, 'dg_booking_checkout', true); ?></td>
                    <td style="padding:8px;">$<?php echo number_format(floatval(get_post_meta($b->ID, 'dg_booking_total', true)), 2); ?></td>
                    <td style="padding:8px;"><?php echo get_post_meta($b->ID, 'dg_booking_status', true); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    public function guest_notes_meta_box($post) {
        $notes = get_post_meta($post->ID, 'dg_guest_notes', true);
        $tags = get_post_meta($post->ID, 'dg_guest_tags', true);
        $vip = get_post_meta($post->ID, 'dg_guest_vip', true);
        ?>
        <p><label><input type="checkbox" name="dg_guest_vip" value="1" <?php checked($vip, '1'); ?>> ⭐ VIP Guest</label></p>
        <p><label>Tags (comma-separated)<br><input type="text" name="dg_guest_tags" value="<?php echo esc_attr($tags); ?>" style="width:100%;" placeholder="repeat, family, honeymoon"></label></p>
        <p><label>Notes<br><textarea name="dg_guest_notes" rows="6" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea></label></p>
        <?php
    }
    
    // ============================================================
    // SAVE META DATA
    // ============================================================
    
    public function save_accommodation_meta($post_id) {
        if (!isset($_POST['dg_accommodation_details_nonce']) || !wp_verify_nonce($_POST['dg_accommodation_details_nonce'], 'dg_accommodation_details_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        if (isset($_POST['dg_accommodation_type'])) {
            wp_set_object_terms($post_id, intval($_POST['dg_accommodation_type']), 'dg_accommodation_type');
        }
        
        $fields = ['dg_description', 'dg_weekday_rate', 'dg_weekend_rate', 'dg_weekday_peak_rate', 'dg_weekend_peak_rate',
            'dg_peak_season_start', 'dg_peak_season_end', 'dg_last_minute_discount', 'dg_early_bird_discount',
            'dg_sleeps', 'dg_bedrooms', 'dg_bathrooms', 'dg_max_guests', 'dg_min_nights', 'dg_size',
            'dg_security_deposit', 'dg_cleaning_fee', 'dg_extra_guest_fee', 'dg_gallery', 'dg_video_url',
            'dg_virtual_tour', 'dg_address', 'dg_latitude', 'dg_longitude', 'dg_checkin_time', 'dg_checkout_time',
            'dg_blocked_dates', 'dg_featured', 'dg_airbnb_id', 'dg_ical_url'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, $field === 'dg_description' || $field === 'dg_blocked_dates' ? $_POST[$field] : sanitize_text_field($_POST[$field]));
            }
        }
        
        if (isset($_POST['dg_features']) && is_array($_POST['dg_features'])) {
            update_post_meta($post_id, 'dg_features', array_map(function($v) { return $v ? 1 : 0; }, $_POST['dg_features']));
        } else {
            update_post_meta($post_id, 'dg_features', []);
        }
    }
    
    public function save_booking_meta($post_id) {
        if (!isset($_POST['dg_booking_details_nonce']) || !wp_verify_nonce($_POST['dg_booking_details_nonce'], 'dg_booking_details_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $fields = ['dg_booking_accommodation_id', 'dg_booking_accommodation_name', 'dg_booking_checkin', 'dg_booking_checkout',
            'dg_booking_nights', 'dg_booking_guests', 'dg_booking_status', 'dg_booking_source',
            'dg_booking_airbnb_id', 'dg_booking_bookingcom_id', 'dg_booking_message',
            'dg_booking_total', 'dg_booking_subtotal', 'dg_booking_cleaning_fee',
            'dg_booking_discount_percent', 'dg_booking_discount_amount', 'dg_booking_discount_type',
            'dg_booking_original_total', 'dg_booking_payment_method', 'dg_booking_paid',
            'dg_booking_name', 'dg_booking_email', 'dg_booking_phone', 'dg_booking_ref',
            'dg_booking_street_address', 'dg_booking_suburb', 'dg_booking_state', 'dg_booking_postcode', 'dg_booking_country'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                if (in_array($field, ['dg_booking_accommodation_id', 'dg_booking_nights', 'dg_booking_guests'])) {
                    $value = intval($value);
                } elseif (in_array($field, ['dg_booking_total', 'dg_booking_subtotal', 'dg_booking_cleaning_fee', 'dg_booking_discount_percent', 'dg_booking_discount_amount', 'dg_booking_original_total'])) {
                    $value = floatval($value);
                } elseif ($field === 'dg_booking_email') {
                    $value = sanitize_email($value);
                } elseif ($field === 'dg_booking_message') {
                    $value = sanitize_textarea_field($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                update_post_meta($post_id, $field, $value);
            }
        }
        
        if (isset($_POST['dg_booking_accommodation_id'])) {
            $acc_id = intval($_POST['dg_booking_accommodation_id']);
            if ($acc_id) {
                $acc = get_post($acc_id);
                if ($acc) update_post_meta($post_id, 'dg_booking_accommodation_name', $acc->post_title);
            }
        }
        
        if (isset($_POST['dg_booking_status']) && $_POST['dg_booking_status'] === 'confirmed') {
            if (get_post_meta($post_id, 'dg_booking_paid', true) !== 'yes') {
                update_post_meta($post_id, 'dg_booking_paid', 'yes');
            }
        }
    }
    
    public function save_guest_meta($post_id) {
        if (!isset($_POST['dg_guest_nonce']) || !wp_verify_nonce($_POST['dg_guest_nonce'], 'dg_guest_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $fields = ['dg_guest_email', 'dg_guest_phone', 'dg_guest_address', 'dg_guest_source', 'dg_guest_tags', 'dg_guest_notes'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
        update_post_meta($post_id, 'dg_guest_vip', isset($_POST['dg_guest_vip']) ? '1' : '0');
    }
    
    // ============================================================
    // GUEST CRM - UPSERT FROM BOOKING (Snippet 10)
    // ============================================================
    
    public function upsert_guest_from_booking($booking_id) {
        if (class_exists('DG_Acc_Guests')) {
            DG_Acc_Guests::sync_from_booking($booking_id);
            return;
        }

        $email = get_post_meta($booking_id, 'dg_booking_email', true);
        if (!$email) return;
        
        $name = get_post_meta($booking_id, 'dg_booking_name', true);
        $phone = get_post_meta($booking_id, 'dg_booking_phone', true);
        $total = floatval(get_post_meta($booking_id, 'dg_booking_total', true));
        $checkin = get_post_meta($booking_id, 'dg_booking_checkin', true);
        $checkout = get_post_meta($booking_id, 'dg_booking_checkout', true);
        $source = get_post_meta($booking_id, 'dg_booking_source', true) ?: 'direct';
        
        $existing = get_posts(['post_type' => 'dg_guest', 'posts_per_page' => 1, 'meta_query' => [['key' => 'dg_guest_email', 'value' => $email]]]);
        
        if ($existing) {
            $guest_id = $existing[0]->ID;
        } else {
            $guest_id = wp_insert_post(['post_type' => 'dg_guest', 'post_title' => $name ?: $email, 'post_status' => 'publish']);
            if (is_wp_error($guest_id)) return;
            update_post_meta($guest_id, 'dg_guest_email', sanitize_email($email));
            update_post_meta($guest_id, 'dg_guest_source', $source);
        }
        
        if ($phone) update_post_meta($guest_id, 'dg_guest_phone', sanitize_text_field($phone));
        
        $nights = $checkin && $checkout ? round((strtotime($checkout) - strtotime($checkin)) / 86400) : 0;
        update_post_meta($guest_id, 'dg_guest_total_stays', intval(get_post_meta($guest_id, 'dg_guest_total_stays', true)) + 1);
        update_post_meta($guest_id, 'dg_guest_total_nights', intval(get_post_meta($guest_id, 'dg_guest_total_nights', true)) + $nights);
        update_post_meta($guest_id, 'dg_guest_total_spent', floatval(get_post_meta($guest_id, 'dg_guest_total_spent', true)) + $total);
        update_post_meta($guest_id, 'dg_guest_last_stay', $checkin);
        update_post_meta($booking_id, 'dg_booking_guest_id', $guest_id);
    }
    
    public function upsert_guest_from_booking_on_save($post_id) {
        $status = get_post_meta($post_id, 'dg_booking_status', true);
        if (in_array($status, ['confirmed', 'airbnb', 'bookingcom'])) {
            $this->upsert_guest_from_booking($post_id);
        }
    }
    
    
    // ============================================================
    // SHORTCODES (Snippet 4)
    // ============================================================
    
    public function accommodation_display_shortcode($atts) {
        $atts = shortcode_atts(['posts_per_page' => 6, 'columns' => 2, 'type' => '', 'featured' => ''], $atts);
        
        $args = ['post_type' => 'dg_accommodation', 'posts_per_page' => intval($atts['posts_per_page']), 'post_status' => 'publish'];
        if (!empty($atts['type'])) {
            $args['tax_query'] = [['taxonomy' => 'dg_accommodation_type', 'field' => 'slug', 'terms' => explode(',', $atts['type'])]];
        }
        if ($atts['featured'] === 'true') {
            $args['meta_query'] = [['key' => 'dg_featured', 'value' => 1, 'type' => 'NUMERIC']];
        }
        
        $query = new WP_Query($args);
        if (!$query->have_posts()) return '<p style="text-align:center;padding:40px 0;color:#5A6B67;">No accommodation found.</p>';
        
        ob_start();
        ?>
        <div class="dg-accommodation-grid" style="display:grid;grid-template-columns:repeat(<?php echo intval($atts['columns']); ?>,1fr);gap:2rem;max-width:1200px;margin:0 auto;padding:2rem 0;">
            <?php while ($query->have_posts()) : $query->the_post();
                $price = floatval(get_post_meta(get_the_ID(), 'dg_weekday_rate', true));
                $price_display = $price ? '$' . number_format($price, 0) . '/night' : 'Contact for Price';
                $image = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'medium_large') : '';
                $featured = get_post_meta(get_the_ID(), 'dg_featured', true);
            ?>
            <div class="dg-accommodation-card" style="background:#fff;border-radius:24px;overflow:hidden;border:1px solid #E0D6CC;transition:all 0.3s ease;">
                <div style="height:200px;overflow:hidden;position:relative;">
                    <?php if ($image): ?>
                        <img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <div style="background:#E0D6CC;height:100%;display:flex;align-items:center;justify-content:center;color:#6B7A78;">No Image</div>
                    <?php endif; ?>
                    <?php if ($featured): ?>
                        <span style="position:absolute;top:1rem;right:1rem;background:#B9A48A;color:#fff;padding:0.3rem 0.8rem;border-radius:40px;font-size:0.7rem;">⭐ Featured</span>
                    <?php endif; ?>
                    <span style="position:absolute;bottom:1rem;left:1rem;background:rgba(44,62,80,0.9);color:#fff;padding:0.4rem 1rem;border-radius:40px;"><?php echo $price_display; ?></span>
                </div>
                <div style="padding:1.5rem;text-align:center;">
                    <h3 style="font-size:1.2rem;font-weight:600;color:#2F2F2F;margin:0 0 0.5rem 0;">
                        <a href="<?php the_permalink(); ?>" style="color:#2F2F2F;text-decoration:none;"><?php the_title(); ?></a>
                    </h3>
                    <a href="<?php the_permalink(); ?>" style="display:inline-block;margin-top:0.5rem;background:#B9A48A;color:#fff;padding:0.6rem 1.5rem;border-radius:40px;text-decoration:none;font-weight:600;">View Details →</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <style>
            .dg-accommodation-card:hover { transform: translateY(-5px); box-shadow: 0 20px 30px -12px rgba(0,0,0,0.1); }
            @media (max-width: 768px) { .dg-accommodation-grid { grid-template-columns: 1fr !important; } }
        </style>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    public function accommodation_details_shortcode($atts) {
        $atts = shortcode_atts(['id' => get_the_ID()], $atts);
        $post_id = intval($atts['id']);
        if (!$post_id) return '<p>No accommodation specified.</p>';
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'dg_accommodation') return '<p>Accommodation not found.</p>';
        
        $price = floatval(get_post_meta($post_id, 'dg_weekday_rate', true));
        $price_display = $price ? '$' . number_format($price, 0) . '/night' : 'Contact for Price';
        $sleeps = get_post_meta($post_id, 'dg_sleeps', true);
        $beds = get_post_meta($post_id, 'dg_bedrooms', true);
        $baths = get_post_meta($post_id, 'dg_bathrooms', true);
        $description = get_post_meta($post_id, 'dg_description', true);
        $image = has_post_thumbnail($post_id) ? get_the_post_thumbnail_url($post_id, 'large') : '';
        
        ob_start();
        ?>
        <div style="max-width:1200px;margin:0 auto;padding:20px;">
            <?php if ($image): ?>
                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($post->post_title); ?>" style="width:100%;max-height:400px;object-fit:cover;border-radius:12px;">
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:2rem;padding:2rem 0;">
                <div>
                    <h1 style="font-size:2rem;color:#1C2B2A;"><?php echo esc_html($post->post_title); ?></h1>
                    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin:1rem 0;">
                        <?php if ($sleeps): ?><span style="background:#f5f2ef;padding:0.3rem 0.8rem;border-radius:20px;">🛏️ Sleeps <?php echo esc_html($sleeps); ?></span><?php endif; ?>
                        <?php if ($beds): ?><span style="background:#f5f2ef;padding:0.3rem 0.8rem;border-radius:20px;">🚪 <?php echo esc_html($beds); ?> beds</span><?php endif; ?>
                        <?php if ($baths): ?><span style="background:#f5f2ef;padding:0.3rem 0.8rem;border-radius:20px;">🛁 <?php echo esc_html($baths); ?> baths</span><?php endif; ?>
                    </div>
                    <?php if ($description): ?>
                        <div style="line-height:1.8;color:#4A5B59;"><?php echo wp_kses_post(nl2br($description)); ?></div>
                    <?php endif; ?>
                </div>
                <div style="background:#fff;padding:1.5rem;border-radius:12px;border:1px solid #E0D6CC;height:fit-content;">
                    <h3 style="text-align:center;color:#1C2B2A;">💰 <?php echo $price_display; ?></h3>
                    <a href="<?php echo home_url('/book-now/?accommodation=' . $post_id); ?>" style="display:block;width:100%;padding:0.8rem;background:#B9A48A;color:#fff;border:none;border-radius:40px;font-size:1rem;font-weight:600;text-align:center;text-decoration:none;cursor:pointer;">📅 Check Availability</a>
                </div>
            </div>
            <div style="padding:1rem 0;font-size:0.85rem;color:#999;border-top:1px solid #E0D6CC;text-align:center;">
                📌 No check-ins or check-outs on Saturdays. Friday check-ins require 2-night minimum.
            </div>
        </div>
        <style>
            @media (max-width: 768px) { .dg-single-wrapper > div:first-child { grid-template-columns: 1fr !important; } }
        </style>
        <?php
        return ob_get_clean();
    }
    
    public function accommodation_enquiry_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => get_the_ID(), 'button_text' => 'Confirm Booking'], $atts);
        $accommodation_id = intval($atts['accommodation_id']);
        $checkin = isset($_GET['checkin']) ? sanitize_text_field($_GET['checkin']) : '';
        $checkout = isset($_GET['checkout']) ? sanitize_text_field($_GET['checkout']) : '';
        $accommodation = $accommodation_id ? get_the_title($accommodation_id) : '';
        
        ob_start();
        ?>
        <div style="max-width:600px;margin:20px auto;background:#fff;padding:30px;border-radius:12px;border:1px solid #E0D6CC;">
            <h3 style="color:#1C2B2A;margin:0 0 20px 0;">📧 Complete Your Booking</h3>
            <?php if ($checkin && $checkout): ?>
            <div style="background:#f5f2ef;padding:12px;border-radius:8px;margin-bottom:20px;">
                <p style="margin:0;font-size:0.9rem;">📅 <strong><?php echo date('d M Y', strtotime($checkin)); ?></strong> → <strong><?php echo date('d M Y', strtotime($checkout)); ?></strong></p>
            </div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="accommodation_id" value="<?php echo $accommodation_id; ?>">
                <input type="hidden" name="booking_checkin" value="<?php echo esc_attr($checkin); ?>">
                <input type="hidden" name="booking_checkout" value="<?php echo esc_attr($checkout); ?>">
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div><label style="display:block;font-weight:600;margin-bottom:4px;">Name *</label>
                        <input type="text" name="enquiry_name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;"></div>
                    <div><label style="display:block;font-weight:600;margin-bottom:4px;">Email *</label>
                        <input type="email" name="enquiry_email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;"></div>
                </div>
                <div style="margin-top:10px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Phone</label>
                    <input type="tel" name="enquiry_phone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;"></div>
                <div style="margin-top:10px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Guests</label>
                    <select name="enquiry_guests" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Guest<?php echo $i > 1 ? 's' : ''; ?></option>
                        <?php endfor; ?>
                    </select></div>
                <div style="margin-top:10px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Special Requests</label>
                    <textarea name="enquiry_message" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;resize:vertical;"></textarea></div>
                <button type="submit" name="dg_payid_submit" value="1" style="width:100%;margin-top:15px;padding:12px;background:#B9A48A;color:#fff;border:none;border-radius:40px;font-size:16px;font-weight:600;cursor:pointer;">📱 Confirm Booking</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function booking_confirmation_shortcode($atts) {
        $ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
        if (empty($ref)) return '<div style="text-align:center;padding:60px 20px;"><h2>Booking Not Found</h2></div>';
        
        $bookings = get_posts(['post_type' => 'dg_booking', 'posts_per_page' => 1, 'meta_query' => [['key' => 'dg_booking_ref', 'value' => $ref]]]);
        if (empty($bookings)) return '<div style="text-align:center;padding:60px 20px;"><h2>Booking Not Found</h2><p>No booking with reference <strong>' . esc_html($ref) . '</strong>.</p></div>';
        
        $booking = $bookings[0];
        $name = get_post_meta($booking->ID, 'dg_booking_name', true);
        $accommodation = get_post_meta($booking->ID, 'dg_booking_accommodation_name', true);
        $checkin = get_post_meta($booking->ID, 'dg_booking_checkin', true);
        $checkout = get_post_meta($booking->ID, 'dg_booking_checkout', true);
        $total = get_post_meta($booking->ID, 'dg_booking_total', true);
        $status = get_post_meta($booking->ID, 'dg_booking_status', true);
        $paid = get_post_meta($booking->ID, 'dg_booking_paid', true);
        $is_confirmed = ($status == 'confirmed' || $paid == 'yes');
        
        ob_start();
        ?>
        <div style="max-width:500px;margin:40px auto;background:#fff;border-radius:16px;padding:30px;border:1px solid #E0D6CC;text-align:center;">
            <div style="font-size:64px;margin-bottom:16px;"><?php echo $is_confirmed ? '✅' : '📋'; ?></div>
            <h2 style="color:#1C2B2A;">Booking <?php echo $is_confirmed ? 'Confirmed!' : 'Received'; ?></h2>
            <p>Reference: <strong><?php echo esc_html($ref); ?></strong></p>
            <p>Status: <strong><?php echo $is_confirmed ? '✅ Confirmed' : '⏳ Pending Payment'; ?></strong></p>
            <div style="text-align:left;margin:20px 0;padding:15px;background:#f5f2ef;border-radius:8px;">
                <p style="margin:5px 0;"><strong>Guest:</strong> <?php echo esc_html($name); ?></p>
                <p style="margin:5px 0;"><strong>Accommodation:</strong> <?php echo esc_html($accommodation); ?></p>
                <?php if ($checkin && $checkout): ?>
                <p style="margin:5px 0;"><strong>Dates:</strong> <?php echo date('d M Y', strtotime($checkin)); ?> → <?php echo date('d M Y', strtotime($checkout)); ?></p>
                <?php endif; ?>
                <?php if ($total): ?>
                <p style="margin:5px 0;"><strong>Total:</strong> $<?php echo number_format(floatval($total), 2); ?></p>
                <?php endif; ?>
            </div>
            <a href="/" style="display:inline-block;margin-top:10px;background:#B9A48A;color:#fff;padding:10px 30px;border-radius:40px;text-decoration:none;font-weight:600;">Return Home</a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function booking_calendar_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => get_the_ID()], $atts);
        $accommodation_id = intval($atts['accommodation_id']);
        if (!$accommodation_id) return '<p>Accommodation not found.</p>';
        
        $blocked_dates = [];
        $bookings = get_posts(['post_type' => 'dg_booking', 'posts_per_page' => -1, 'meta_query' => [['key' => 'dg_booking_accommodation_id', 'value' => $accommodation_id, 'compare' => '=']]]);
        foreach ($bookings as $b) {
            $checkin = get_post_meta($b->ID, 'dg_booking_checkin', true);
            $checkout = get_post_meta($b->ID, 'dg_booking_checkout', true);
            $status = get_post_meta($b->ID, 'dg_booking_status', true);
            if ($status !== 'cancelled' && $checkin && $checkout) {
                $current = strtotime($checkin);
                $end = strtotime($checkout);
                while ($current < $end) { $blocked_dates[] = date('Y-m-d', $current); $current = strtotime('+1 day', $current); }
            }
        }
        $blocked_dates = array_unique($blocked_dates);
        sort($blocked_dates);
        ?>
        <div style="background:#f8f5f0;padding:25px;border-radius:12px;max-width:1200px;margin:0 auto;">
            <h3 style="margin:0 0 20px 0;color:#1C2B2A;">📅 Check Availability</h3>
            <div id="dg-calendar-<?php echo $accommodation_id; ?>" style="background:#fff;padding:15px;border-radius:8px;min-height:400px;"></div>
            <div style="margin-top:15px;font-size:13px;color:#666;">
                <span style="display:inline-block;width:16px;height:16px;background:#dc3545;border-radius:3px;vertical-align:middle;margin-right:5px;"></span> Unavailable
                <span style="display:inline-block;width:16px;height:16px;background:#28a745;border-radius:3px;vertical-align:middle;margin:0 5px 0 15px;"></span> Available
                <span style="display:inline-block;width:16px;height:16px;background:#ffc107;border-radius:3px;vertical-align:middle;margin:0 5px 0 15px;"></span> Saturday
            </div>
            <div style="margin-top:10px;padding:10px 16px;background:#fef8e7;border-left:4px solid #f39c12;border-radius:4px;font-size:13px;color:#666;">📌 No check-ins or check-outs on Saturdays. Friday check-ins require 2-night minimum.</div>
        </div>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css">
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('dg-calendar-<?php echo $accommodation_id; ?>');
            if (!calendarEl || typeof FullCalendar === 'undefined') return;
            
            var blockedDates = <?php echo json_encode($blocked_dates); ?>;
            var today = new Date(); today.setHours(0,0,0,0);
            
            function isSaturday(date) { return date.getDay() === 6; }
            function isDateBlocked(date) { return blockedDates.indexOf(date.toISOString().split('T')[0]) !== -1; }
            
            var events = [];
            blockedDates.forEach(function(d) { events.push({ start: d, display: 'background', color: '#dc3545' }); });
            
            var d = new Date(today);
            var end = new Date(today); end.setFullYear(end.getFullYear() + 1);
            while (d <= end) { if (isSaturday(d)) { events.push({ start: new Date(d), display: 'background', color: '#ffc107' }); } d.setDate(d.getDate() + 1); }
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                selectable: true,
                events: events,
                selectAllow: function(info) {
                    if (info.start < today) return false;
                    if (isSaturday(info.start)) return false;
                    if (isSaturday(info.end)) return false;
                    return true;
                },
                select: function(info) {
                    var start = info.start, end = info.end;
                    if (isSaturday(start) || isSaturday(end)) {
                        alert('❌ Check-in/out not available on Saturdays.');
                        calendar.unselect(); return;
                    }
                    var hasBlocked = false;
                    var d = new Date(start);
                    while (d < end) { if (isDateBlocked(d)) { hasBlocked = true; break; } d.setDate(d.getDate() + 1); }
                    if (hasBlocked) { alert('❌ Some dates are unavailable.'); calendar.unselect(); return; }
                    
                    var nights = Math.round((end - start) / (1000 * 60 * 60 * 24));
                    var url = '<?php echo home_url('/book-now/'); ?>?accommodation=<?php echo $accommodation_id; ?>&checkin=' + start.toISOString().split('T')[0] + '&checkout=' + end.toISOString().split('T')[0];
                    if (confirm('📅 Book ' + nights + ' nights?\n\nCheck-in: ' + start.toISOString().split('T')[0] + '\nCheck-out: ' + end.toISOString().split('T')[0])) {
                        window.location.href = url;
                    } else { calendar.unselect(); }
                }
            });
            calendar.render();
        });
        </script>
        <?php
        return '';
    }
    
    // ============================================================
    // AIRBNB SHORTCODE (Snippet 7)
    // ============================================================
    
    public function airbnb_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => get_the_ID(), 'airbnb_id' => ''], $atts);
        $airbnb_id = $atts['airbnb_id'] ?: get_post_meta(intval($atts['accommodation_id']), 'dg_airbnb_id', true);
        if (empty($airbnb_id)) return '<p>No Airbnb listing ID found.</p>';
        
        return '<div style="background:#f8f5f0;border-radius:12px;padding:25px;text-align:center;"><h3>🏡 Book on Airbnb</h3><a href="https://www.airbnb.com/rooms/' . esc_attr($airbnb_id) . '" target="_blank" style="display:inline-block;padding:12px 25px;background:#ff5a5f;color:#fff;border-radius:30px;text-decoration:none;font-weight:600;">View on Airbnb</a></div>';
    }
    
    // ============================================================
    // BOOKING.COM SHORTCODE (Snippet 7)
    // ============================================================
    
    public function bookingcom_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => get_the_ID(), 'bookingcom_id' => ''], $atts);
        $bookingcom_id = $atts['bookingcom_id'] ?: get_post_meta(intval($atts['accommodation_id']), 'dg_bookingcom_id', true);
        if (empty($bookingcom_id)) return '<p>No Booking.com listing ID found.</p>';
        
        return '<div style="background:#f8f5f0;border-radius:12px;padding:25px;text-align:center;"><h3>🏨 Book on Booking.com</h3><a href="https://www.booking.com/hotel/' . esc_attr($bookingcom_id) . '" target="_blank" style="display:inline-block;padding:12px 25px;background:#003580;color:#fff;border-radius:30px;text-decoration:none;font-weight:600;">View on Booking.com</a></div>';
    }
    
    // ============================================================
    // ENQUIRY FORM SHORTCODE (Snippet 10)
    // ============================================================
    
    public function enquiry_form_shortcode($atts) {
        $atts = shortcode_atts(['accommodation' => ''], $atts);
        ob_start();
        ?>
        <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
            <h3 style="font-family:'Cormorant Garamond',serif;color:#2D4A2E;font-size:1.6rem;margin-bottom:8px;">Make an Enquiry</h3>
            <div id="dg-enquiry-success" style="display:none;padding:16px;background:#d4edda;color:#155724;border-radius:8px;margin-bottom:16px;">✅ Thanks! We\'ll be in touch shortly.</div>
            <div id="dg-enquiry-error" style="display:none;padding:16px;background:#f8d7da;color:#721c24;border-radius:8px;margin-bottom:16px;"></div>
            <div style="display:grid;gap:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div><label style="display:block;font-size:13px;font-weight:600;">First Name *</label>
                        <input type="text" id="dg-enq-fname" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;"></div>
                    <div><label style="display:block;font-size:13px;font-weight:600;">Last Name *</label>
                        <input type="text" id="dg-enq-lname" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;"></div>
                </div>
                <div><label style="display:block;font-size:13px;font-weight:600;">Email *</label>
                    <input type="email" id="dg-enq-email" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;"></div>
                <div><label style="display:block;font-size:13px;font-weight:600;">Phone</label>
                    <input type="tel" id="dg-enq-phone" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;"></div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;">Accommodation</label>
                    <select id="dg-enq-accom" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;background:#fff;">
                        <option value="">— Select —</option>
                        <?php foreach (get_posts(['post_type' => 'dg_accommodation', 'posts_per_page' => -1]) as $a): ?>
                            <option value="<?php echo $a->ID; ?>"><?php echo esc_html($a->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div><label style="display:block;font-size:13px;font-weight:600;">Check-in</label>
                        <input type="date" id="dg-enq-checkin" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;"></div>
                    <div><label style="display:block;font-size:13px;font-weight:600;">Check-out</label>
                        <input type="date" id="dg-enq-checkout" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;"></div>
                </div>
                <div><label style="display:block;font-size:13px;font-weight:600;">Guests</label>
                    <select id="dg-enq-guests" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;">
                        <?php for ($i = 1; $i <= 8; $i++) echo "<option value='$i'>$i guest" . ($i > 1 ? 's' : '') . "</option>"; ?>
                    </select></div>
                <div><label style="display:block;font-size:13px;font-weight:600;">Message</label>
                    <textarea id="dg-enq-message" rows="4" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;resize:vertical;"></textarea></div>
                <button onclick="dgSubmitEnquiry()" id="dg-enq-submit" style="width:100%;padding:14px;background:#2D4A2E;color:#fff;border:none;border-radius:40px;font-size:16px;font-weight:600;cursor:pointer;">Send Enquiry</button>
            </div>
        </div>
        <script>
        function dgSubmitEnquiry() {
            var btn = document.getElementById('dg-enq-submit');
            var data = new FormData();
            data.append('action', 'dg_submit_enquiry');
            data.append('nonce', '<?php echo wp_create_nonce('dg_enquiry_nonce'); ?>');
            data.append('fname', document.getElementById('dg-enq-fname').value.trim());
            data.append('lname', document.getElementById('dg-enq-lname').value.trim());
            data.append('email', document.getElementById('dg-enq-email').value.trim());
            data.append('phone', document.getElementById('dg-enq-phone').value.trim());
            data.append('accom_id', document.getElementById('dg-enq-accom').value);
            data.append('checkin', document.getElementById('dg-enq-checkin').value);
            data.append('checkout', document.getElementById('dg-enq-checkout').value);
            data.append('guests', document.getElementById('dg-enq-guests').value);
            data.append('message', document.getElementById('dg-enq-message').value.trim());
            
            btn.textContent = 'Sending...'; btn.disabled = true;
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: data })
                .then(r => r.json()).then(res => {
                    if (res.success) {
                        document.getElementById('dg-enquiry-success').style.display = 'block';
                        document.querySelector('.dg-enquiry-form-wrap div:last-child').style.display = 'none';
                    } else {
                        document.getElementById('dg-enquiry-error').textContent = res.data || 'Error. Please try again.';
                        document.getElementById('dg-enquiry-error').style.display = 'block';
                        btn.textContent = 'Send Enquiry'; btn.disabled = false;
                    }
                });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    // ============================================================
    // CONTACT FORM SHORTCODE (Snippet 10)
    // ============================================================
    
    public function contact_form_shortcode($atts) {
        ob_start();
        ?>
        <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
            <h3 style="font-family:'Cormorant Garamond',serif;color:#2D4A2E;font-size:1.6rem;">Get in Touch</h3>
            <div id="dg-contact-success" style="display:none;padding:16px;background:#d4edda;color:#155724;border-radius:8px;">✅ Message sent!</div>
            <div id="dg-contact-error" style="display:none;padding:16px;background:#f8d7da;color:#721c24;border-radius:8px;"></div>
            <div style="display:grid;gap:14px;">
                <div><label style="display:block;font-size:13px;font-weight:600;">Name *</label>
                    <input type="text" id="dg-con-name" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;"></div>
                <div><label style="display:block;font-size:13px;font-weight:600;">Email *</label>
                    <input type="email" id="dg-con-email" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;"></div>
                <div><label style="display:block;font-size:13px;font-weight:600;">Message *</label>
                    <textarea id="dg-con-message" rows="5" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;resize:vertical;"></textarea></div>
                <button onclick="dgSubmitContact()" id="dg-con-submit" style="width:100%;padding:14px;background:#2D4A2E;color:#fff;border:none;border-radius:40px;font-size:16px;font-weight:600;cursor:pointer;">Send Message</button>
            </div>
        </div>
        <script>
        function dgSubmitContact() {
            var btn = document.getElementById('dg-con-submit');
            var data = new FormData();
            data.append('action','dg_submit_contact'); data.append('nonce','<?php echo wp_create_nonce('dg_contact_nonce'); ?>');
            data.append('name', document.getElementById('dg-con-name').value.trim());
            data.append('email', document.getElementById('dg-con-email').value.trim());
            data.append('message', document.getElementById('dg-con-message').value.trim());
            btn.textContent = 'Sending...'; btn.disabled = true;
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: data })
                .then(r => r.json()).then(res => {
                    if (res.success) {
                        document.getElementById('dg-contact-success').style.display = 'block';
                        document.querySelector('.dg-contact-form-wrap div:last-child').style.display = 'none';
                    } else {
                        document.getElementById('dg-contact-error').textContent = res.data || 'Error.';
                        document.getElementById('dg-contact-error').style.display = 'block';
                        btn.textContent = 'Send Message'; btn.disabled = false;
                    }
                });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    // ============================================================
    // STRIPE ELEMENTS SHORTCODE (Snippet 5)
    // ============================================================
    
    public function stripe_elements_shortcode($atts) {
        if (get_option('dg_stripe_enabled', 'no') !== 'yes') return '<p style="color:#dc3545;">Stripe payments are not enabled.</p>';
        $publishable_key = get_option('dg_stripe_publishable_key');
        if (!$publishable_key) return '<p style="color:#dc3545;">Stripe is not configured.</p>';
        
        ob_start();
        ?>
        <div id="dg-stripe-elements" style="max-width:500px;margin:0 auto;">
            <div id="dg-stripe-card-element" style="padding:12px 14px;border:2px solid #E0D6CC;border-radius:10px;background:#fff;min-height:44px;"></div>
            <div id="dg-stripe-card-errors" style="color:#dc3545;font-size:0.85rem;margin-top:8px;"></div>
            <button id="dg-stripe-submit" style="width:100%;margin-top:16px;padding:14px;background:#B9A48A;color:#fff;border:none;border-radius:40px;font-size:1rem;font-weight:600;cursor:pointer;">💳 Pay Now</button>
        </div>
        <script src="https://js.stripe.com/v3/"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var stripe = Stripe('<?php echo $publishable_key; ?>');
            var elements = stripe.elements();
            var cardElement = elements.create('card', {
                style: { base: { fontSize: '16px', color: '#2F2F2F', '::placeholder': { color: '#999' } } }
            });
            cardElement.mount('#dg-stripe-card-element');
            
            document.getElementById('dg-stripe-submit').addEventListener('click', function(e) {
                var name = document.getElementById('enquiry_name')?.value.trim();
                var email = document.getElementById('enquiry_email')?.value.trim();
                if (!name || !email) { alert('Please fill in your name and email.'); return; }
                
                this.disabled = true; this.textContent = '⏳ Processing...';
                var bookingData = {
                    accommodation_id: document.getElementById('dg-accommodation-id')?.value || 0,
                    booking_total: document.getElementById('dg-booking-total')?.value || '0',
                    enquiry_name: name, enquiry_email: email,
                    enquiry_phone: document.getElementById('enquiry_phone')?.value || '',
                    booking_checkin: document.getElementById('dg-booking-checkin')?.value || '',
                    booking_checkout: document.getElementById('dg-booking-checkout')?.value || '',
                    booking_nights: document.getElementById('dg-booking-nights')?.value || 0,
                    enquiry_guests: document.getElementById('enquiry_guests')?.value || 2
                };
                
                fetch('/wp-json/dg-stripe/v1/create-payment-intent', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(bookingData)
                })
                .then(r => r.json())
                .then(data => stripe.confirmCardPayment(data.clientSecret, {
                    payment_method: { card: cardElement, billing_details: { name: name, email: email } }
                }))
                .then(result => {
                    if (result.error) throw new Error(result.error.message);
                    window.location.href = '/booking-confirmed/?ref=' + result.paymentIntent.metadata.booking_ref;
                })
                .catch(err => { alert('Payment error: ' + err.message); this.disabled = false; this.textContent = '💳 Pay Now'; });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    
    // ============================================================
    // AJAX HANDLERS (Snippet 10)
    // ============================================================
    
    public function handle_enquiry_submission() {
        if (!check_ajax_referer('dg_enquiry_nonce', 'nonce', false)) wp_send_json_error('Security check failed.');
        
        $fname = sanitize_text_field($_POST['fname'] ?? '');
        $lname = sanitize_text_field($_POST['lname'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $accom_id = intval($_POST['accom_id'] ?? 0);
        $checkin = sanitize_text_field($_POST['checkin'] ?? '');
        $checkout = sanitize_text_field($_POST['checkout'] ?? '');
        $guests = intval($_POST['guests'] ?? 1);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (!$fname || !$lname || !$email) wp_send_json_error('Name and email required.');
        
        $name = $fname . ' ' . $lname;
        $accom = $accom_id ? get_the_title($accom_id) : 'Not specified';
        
        $enquiry_id = wp_insert_post([
            'post_type' => 'dg_booking',
            'post_title' => $name . ' — Enquiry (' . date('d M Y') . ')',
            'post_status' => 'publish',
            'meta_input' => [
                'dg_booking_name' => $name,
                'dg_booking_email' => $email,
                'dg_booking_phone' => $phone,
                'dg_booking_accommodation_id' => $accom_id,
                'dg_booking_accommodation_name' => $accom,
                'dg_booking_checkin' => $checkin,
                'dg_booking_checkout' => $checkout,
                'dg_booking_guests' => $guests,
                'dg_booking_message' => $message,
                'dg_booking_status' => 'enquiry',
                'dg_booking_source' => 'website',
            ]
        ]);
        
        wp_mail(get_option('admin_email'), '📬 New Enquiry from ' . $name,
            "Name: $name\nEmail: $email\nPhone: $phone\nAccommodation: $accom\n\nMessage:\n$message");
        
        wp_send_json_success(['ref' => 'ENQ-' . time()]);
    }
    
    public function handle_contact_submission() {
        if (!check_ajax_referer('dg_contact_nonce', 'nonce', false)) wp_send_json_error('Security check failed.');
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (!$name || !$email || !$message) wp_send_json_error('All fields required.');
        
        wp_mail(get_option('admin_email'), '📩 Contact form: ' . $name,
            "Name: $name\nEmail: $email\n\nMessage:\n$message");
        
        wp_send_json_success();
    }
    
    
    public function cleanup_expired_bookings() {
        $bookings = get_posts([
            'post_type' => 'dg_booking',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => 'dg_booking_checkout', 'value' => date('Y-m-d', strtotime('-30 days')), 'compare' => '<', 'type' => 'DATE'],
                ['key' => 'dg_booking_status', 'value' => ['pending', 'confirmed'], 'compare' => 'IN']
            ]
        ]);
        foreach ($bookings as $b) {
            update_post_meta($b->ID, 'dg_booking_status', 'completed');
        }
    }
    
    public function cleanup_orphaned_ota_bookings() {
        // Optional cleanup function
    }
    
}

// ============================================================
// INITIALIZE MODULE
// ============================================================

add_action('dg_platform_modules_loaded', function() {
    $platform = DG_Platform::get_instance();
    DG_Module_Accommodation::get_instance($platform);
});

// ============================================================
// END OF MODULE
// ============================================================