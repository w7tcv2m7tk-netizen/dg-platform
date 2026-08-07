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
            'class-acc-admin-menus.php',
            'class-acc-ota.php',
            'class-acc-ical-import.php',
            'class-acc-payments.php',
            'class-acc-admin-pages.php',
            'class-acc-dev-api.php',
            'class-acc-admin-notifications.php',
            'class-acc-guest-notifications.php',
            'class-acc-housekeeping.php',
            'class-acc-checkin.php',
            'class-acc-cleaning.php',
            'class-acc-frontend.php',
            'class-acc-calendar.php',
            'class-acc-gallery.php',
            'class-acc-ical-export.php',
            'class-acc-shortcodes.php',
            'class-acc-listing-status.php',
            'class-acc-shortcode-render.php',
        ] as $file) {
            $path = $dir . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        $this->bootstrap_includes();
    }

    private function bootstrap_includes() {
        foreach ([
            'DG_Acc_Ota',
            'DG_Acc_Payments',
            'DG_Acc_Admin_Pages',
            'DG_Acc_Admin_Menus',
            'DG_Acc_Admin_Notifications',
            'DG_Acc_Guest_Notifications',
            'DG_Acc_Housekeeping',
            'DG_Acc_Checkin',
            'DG_Acc_Cleaning',
            'DG_Acc_Calendar',
            'DG_Acc_Gallery',
            'DG_Acc_Ical_Export',
            'DG_Acc_Shortcodes',
            'DG_Acc_Listing_Status',
            'DG_Acc_Shortcode_Render',
        ] as $class) {
            if (class_exists($class) && method_exists($class, 'init')) {
                $class::init();
            }
        }
        if (class_exists('DG_Acc_Frontend') && method_exists('DG_Acc_Frontend', 'init')) {
            DG_Acc_Frontend::init();
        }
        if (class_exists('DG_Acc_Dev_API')) {
            add_action('rest_api_init', ['DG_Acc_Dev_API', 'register_routes']);
        }
    }
    
    // ============================================================
    // INITIALIZATION - ALL HOOKS AND FILTERS
    // ============================================================
    
    private function init() {
        $this->load_includes();

        add_action('dg_platform_quick_actions', [$this, 'quick_actions']);
        add_filter('dg_platform_dashboard_widgets', [$this, 'dashboard_widgets']);

        // Post Types & Taxonomies
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'prepopulate_types']);
        add_action('init', [$this, 'maybe_flush_rewrites'], 99);
        
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
        
        // Booking page — strip legacy summary blocks and ensure calendar works
        add_filter('the_content', [$this, 'filter_booking_page_content'], 20);
        add_action('save_post_dg_accommodation', [$this, 'save_accommodation_meta']);
        add_action('save_post_dg_accommodation', [$this, 'save_booking_settings_meta']);
        add_action('save_post_dg_booking', [$this, 'save_booking_meta']);
        add_action('save_post_dg_guest', [$this, 'save_guest_meta']);
        add_action('save_post_dg_guest', [$this, 'sync_guest_to_core'], 25);
        
        // Admin Notices
        add_action('admin_notices', [$this, 'saturday_restriction_notice']);
        
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
    
    public function dashboard_widgets($widgets) {
        if (!class_exists('DG_Acc_Reports') || !DG_Acc_Permissions::can_view_bookings()) {
            return $widgets;
        }
        try {
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
        } catch (Throwable $e) {
            // ignore widget errors on main dashboard
        }
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
            'show_in_menu' => false,
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
            'Sanctuary Dome' => ['slug' => 'sanctuary-dome', 'description' => 'Premium glass dome — coming soon'],
            'Rainforest Dome' => ['slug' => 'rainforest-dome', 'description' => 'Rainforest glass dome — coming soon'],
            'Canopy Dome' => ['slug' => 'canopy-dome', 'description' => 'Canopy glass dome — coming soon'],
            'Starlight Dome' => ['slug' => 'starlight-dome', 'description' => 'Stargazing glass dome — coming soon'],
            'The Shed' => ['slug' => 'the-shed', 'description' => 'Functions and events venue — booking opening in future'],
        ];
        
        foreach ($types as $name => $args) {
            if (!term_exists($name, 'dg_accommodation_type')) {
                wp_insert_term($name, 'dg_accommodation_type', $args);
            }
        }
    }
    
    public function maybe_flush_rewrites() {
        if (!get_option('dg_acc_needs_rewrite_flush')) {
            return;
        }
        flush_rewrite_rules();
        delete_option('dg_acc_needs_rewrite_flush');
    }

    /** Schedule rewrite flush (e.g. on plugin activation). */
    public static function flag_rewrite_flush() {
        update_option('dg_acc_needs_rewrite_flush', 1);
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
                <?php if (class_exists('DG_AI_Assist')) : ?>
                    <p style="margin-top:6px;">
                        <button type="button" class="button button-secondary dg-ai-btn" data-ai-task="accommodation_description" data-ai-post-id="<?php echo (int) $post->ID; ?>" data-ai-target="#dg_description">✨ Write stay description with AI</button>
                        <span class="dg-ai-status"></span>
                    </p>
                <?php endif; ?>
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
            <?php
            $listing_status = class_exists('DG_Acc_Listing_Status')
                ? get_post_meta($post->ID, DG_Acc_Listing_Status::META, true) ?: DG_Acc_Listing_Status::infer_from_type($post->ID)
                : 'bookable';
            ?>
            <div class="dg-meta-field full-width">
                <label for="dg_listing_status">Listing status</label>
                <select name="dg_listing_status" id="dg_listing_status" style="width:100%;max-width:400px;">
                    <?php if (class_exists('DG_Acc_Listing_Status')) : ?>
                        <?php foreach (DG_Acc_Listing_Status::labels() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($listing_status, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <option value="bookable">Open for bookings</option>
                    <?php endif; ?>
                </select>
                <div class="helper">Domes, Tiny Home, and Private Retreat default to <strong>Coming soon</strong>. The Shed defaults to <strong>Events & functions</strong>. Private Studio stays open for bookings.</div>
            </div>
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
                <label for="dg_blocked_dates">Manual blocked dates</label>
                <textarea name="dg_blocked_dates" rows="3" placeholder="2024-12-20 to 2025-01-10&#10;2025-04-10 to 2025-04-20"><?php echo esc_textarea(get_post_meta($post->ID, 'dg_blocked_dates', true)); ?></textarea>
                <p class="description">Operator-only ranges. Airbnb / Booking.com stays block via calendar sync and are not written here.</p>
                <div class="helper">Enter each blocked date range on a new line. Format: YYYY-MM-DD to YYYY-MM-DD</div>
            </div>
            
            <!-- Landing Page -->
            <div class="dg-section-title">🌐 Public Page</div>
            <div class="dg-meta-field full-width">
                <label for="dg_landing_page_id">WordPress Booking Page</label>
                <?php
                $landing_page_id = (int) get_post_meta($post->ID, 'dg_landing_page_id', true);
                wp_dropdown_pages([
                    'name' => 'dg_landing_page_id',
                    'id' => 'dg_landing_page_id',
                    'selected' => $landing_page_id,
                    'show_option_none' => '— Auto-match by slug —',
                    'option_none_value' => '0',
                ]);
                ?>
                <div class="helper">Link to the Oxygen page for this stay (e.g. /tiny-home/). Place <code>[dg_accommodation_page]</code> on that page.</div>
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
        $export_url = class_exists('DG_Acc_Ical_Export') ? DG_Acc_Ical_Export::url_for($post->ID) : '';
        $sync_nonce = wp_create_nonce('dg_calendar_nonce');
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
                <span class="dg-ota-badge <?php echo !empty($ical_url) ? 'airbnb' : 'none'; ?>"><?php echo !empty($ical_url) ? 'iCal connected' : 'Add iCal URL'; ?></span>
            </div>
            <div class="dg-booking-settings-field">
                <label for="dg_airbnb_id">Listing ID</label>
                <input type="text" name="dg_airbnb_id" value="<?php echo esc_attr($airbnb_id); ?>" placeholder="e.g. 12345678">
            </div>
            <div class="dg-booking-settings-field">
                <label for="dg_ical_url">Airbnb iCal Import URL</label>
                <input type="url" id="dg_ical_url" name="dg_ical_url" value="<?php echo esc_url($ical_url); ?>" placeholder="https://www.airbnb.com/calendar/ical/...">
                <div class="helper">Airbnb → Calendar → Availability → Export calendar</div>
                <div style="font-size:11px;color:#666;margin-top:4px;">🔄 Last synced: <?php echo $ical_last_sync ? date_i18n('M j, Y g:i A', strtotime($ical_last_sync)) : 'Never'; ?></div>
            </div>
            
            <!-- Booking.com -->
            <div class="dg-booking-settings-divider"><span>🏨 Booking.com</span></div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span style="font-size:16px;">🏨</span>
                <span style="font-weight:600;font-size:13px;">Booking.com</span>
                <span class="dg-ota-badge <?php echo !empty($bookingcom_ical_url) ? 'bookingcom' : 'none'; ?>"><?php echo !empty($bookingcom_ical_url) ? 'iCal connected' : 'Add iCal URL'; ?></span>
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

            <!-- iCal Export (for Booking.com / Airbnb) -->
            <div class="dg-booking-settings-divider"><span>📤 iCal Export</span></div>
            <?php if ($export_url) :
                $fallback_url = class_exists('DG_Acc_Ical_Export') ? DG_Acc_Ical_Export::fallback_url_for($post->ID) : '';
                ?>
            <div class="dg-booking-settings-field">
                <label>Export URL (paste into Booking.com / Airbnb)</label>
                <input type="text" readonly value="<?php echo esc_attr($export_url); ?>" id="dg-ical-export-url" onclick="this.select();" style="font-size:11px;background:#f5f5f5;">
                <div class="helper">Must return calendar data — open the link in a new tab to verify before pasting into an OTA.</div>
                <div class="helper"><a href="<?php echo esc_url($export_url); ?>" target="_blank" rel="noopener">Test export link ↗</a><?php if ($fallback_url && $fallback_url !== $export_url) : ?> · <a href="<?php echo esc_url($fallback_url); ?>" target="_blank" rel="noopener">Alternate link ↗</a><?php endif; ?></div>
                <div class="helper">Booking.com → Calendar → Import calendar · Airbnb → Availability → Connect calendars → Import</div>
            </div>
            <?php endif; ?>

            <div style="margin-top:12px;">
                <button type="button" class="button button-secondary" id="dg-ota-sync-btn" style="width:100%;">🔄 Sync OTA Now</button>
                <div id="dg-ota-sync-status" style="font-size:11px;color:#666;margin-top:6px;text-align:center;"></div>
            </div>
            
            <div style="margin-top:12px;padding:10px;background:#f8f6f2;border-radius:4px;border-left:3px solid #B9A48A;font-size:11px;color:#666;">
                <strong>💡 Import:</strong> Airbnb → Calendar → Export calendar<br>
                <strong>Booking.com:</strong> Property → Calendar → iCal import link
            </div>
        </div>
        <script>
        jQuery(function($) {
            $('#dg-ota-sync-btn').on('click', function() {
                var $status = $('#dg-ota-sync-status');
                $status.text('⏳ Syncing...');
                $.post(ajaxurl, {
                    action: 'dg_ota_sync',
                    accommodation_id: <?php echo (int) $post->ID; ?>,
                    source: 'all',
                    nonce: '<?php echo esc_js($sync_nonce); ?>'
                }).done(function(r) {
                    if (r.success) {
                        $status.text('✅ ' + (r.data.message || 'Synced'));
                        setTimeout(function() { window.location.reload(); }, 1200);
                    } else {
                        $status.text('❌ ' + (r.data || 'Sync failed'));
                    }
                }).fail(function() {
                    $status.text('❌ Error connecting to server.');
                });
            });
        });
        </script>
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
        $checkin_sent = get_post_meta($post->ID, '_dg_acc_checkin_email_sent', true) === 'yes';
        $checkin_sent_at = get_post_meta($post->ID, '_dg_acc_checkin_email_sent_at', true);
        ?>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee;">
            <p style="margin:0 0 8px;font-size:12px;">
                <strong>Check-in email:</strong>
                <?php echo $checkin_sent ? '✅ Sent' . ($checkin_sent_at ? ' (' . esc_html($checkin_sent_at) . ')' : '') : '— Not sent yet'; ?>
            </p>
            <?php if (class_exists('DG_Acc_Guest_Notifications')) : ?>
                <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_resend_checkin_email&booking_id=' . (int) $post->ID), 'dg_resend_checkin_email_' . (int) $post->ID)); ?>">Resend check-in email</a>
            <?php endif; ?>
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
            'dg_blocked_dates', 'dg_featured'];
        
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

        if (isset($_POST['dg_listing_status']) && class_exists('DG_Acc_Listing_Status')) {
            $status = sanitize_text_field($_POST['dg_listing_status']);
            if (isset(DG_Acc_Listing_Status::labels()[$status])) {
                update_post_meta($post_id, DG_Acc_Listing_Status::META, $status);
            }
        }

        if (isset($_POST['dg_landing_page_id'])) {
            $old_page = (int) get_post_meta($post_id, 'dg_landing_page_id', true);
            $new_page = (int) $_POST['dg_landing_page_id'];
            update_post_meta($post_id, 'dg_landing_page_id', $new_page);
            if ($old_page && $old_page !== $new_page) {
                delete_post_meta($old_page, 'dg_linked_accommodation_id');
            }
            if ($new_page) {
                update_post_meta($new_page, 'dg_linked_accommodation_id', $post_id);
            }
        }
    }

    public function save_booking_settings_meta($post_id) {
        if (!isset($_POST['dg_booking_settings_nonce']) || !wp_verify_nonce($_POST['dg_booking_settings_nonce'], 'dg_booking_settings_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'dg_airbnb_id' => 'sanitize_text_field',
            'dg_ical_url' => 'esc_url_raw',
            'dg_bookingcom_id' => 'sanitize_text_field',
            'dg_bookingcom_ical_url' => 'esc_url_raw',
        ];
        foreach ($fields as $field => $sanitize) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, call_user_func($sanitize, wp_unslash($_POST[$field])));
            }
        }

        if (class_exists('DG_Acc_Ical_Export')) {
            DG_Acc_Ical_Export::token_for($post_id);
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
        if (class_exists('DG_Acc_Shortcode_Render')) {
            return DG_Acc_Shortcode_Render::render_display($atts);
        }
        return '';
    }
    
    public function accommodation_gallery_shortcode($atts) {
        $atts = shortcode_atts(['id' => 0], $atts);
        $post_id = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::resolve_accommodation_id($atts['id'])
            : intval($atts['id']);
        if (!$post_id || !class_exists('DG_Acc_Gallery')) {
            return '';
        }
        return DG_Acc_Gallery::render($post_id);
    }

    public function accommodation_description_shortcode($atts) {
        $atts = shortcode_atts(['id' => 0], $atts);
        $post_id = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::resolve_accommodation_id($atts['id'])
            : intval($atts['id']);
        if (!$post_id || !class_exists('DG_Acc_Frontend')) {
            return '';
        }
        return DG_Acc_Frontend::render_description($post_id);
    }

    public function booking_summary_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => 0], $atts);
        $accommodation_id = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::resolve_accommodation_id($atts['accommodation_id'])
            : intval($atts['accommodation_id']);
        if (!class_exists('DG_Acc_Frontend')) {
            return '';
        }
        return DG_Acc_Frontend::render_booking_summary($accommodation_id);
    }

    public function accommodation_details_shortcode($atts) {
        $atts = shortcode_atts(['id' => 0], $atts);
        $post_id = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::resolve_accommodation_id($atts['id'])
            : intval($atts['id']);
        if (!$post_id) {
            return '<p style="text-align:center;padding:40px 0;color:#5A6B67;">No accommodation specified.</p>';
        }
        if (class_exists('DG_Acc_Shortcode_Render')) {
            return DG_Acc_Shortcode_Render::render_details($post_id);
        }
        return '';
    }

    public function accommodation_page_shortcode($atts) {
        return $this->accommodation_details_shortcode($atts);
    }
    
    public function accommodation_enquiry_shortcode($atts) {
        $atts = shortcode_atts([
            'accommodation_id' => 0,
            'button_text' => 'Confirm Booking',
            'layout' => 'default',
        ], $atts);
        $compact = ($atts['layout'] === 'compact');
        $accommodation_id = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::resolve_accommodation_id($atts['accommodation_id'])
            : intval($atts['accommodation_id']);
        if (!$accommodation_id) {
            return '<p>Please select an accommodation to book.</p>';
        }
        list($checkin, $checkout) = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::parse_request_dates()
            : ['', ''];
        $accommodation = get_the_title($accommodation_id);
        $quote = class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::calculate_total($accommodation_id, $checkin, $checkout) : ['nights' => 0, 'total' => 0, 'subtotal' => 0, 'cleaning_fee' => 0, 'discount_amount' => 0, 'discount_type' => ''];
        $has_dates = ($checkin && $checkout && $quote['nights'] > 0);
        $wrap_style = $compact
            ? 'background:#fff;border:1px solid #E8DFD3;border-radius:16px;padding:1.25rem;margin:0;'
            : 'max-width:600px;margin:20px auto;background:#fff;padding:30px;border-radius:12px;border:1px solid #E0D6CC;';

        $form_action = is_singular()
            ? get_permalink(get_queried_object_id())
            : (class_exists('DG_Acc_Frontend')
                ? DG_Acc_Frontend::booking_page_url($accommodation_id)
                : home_url('/'));
        $booking_error = isset($_GET['booking_error']) ? sanitize_text_field(wp_unslash($_GET['booking_error'])) : '';

        $stripe_enabled = get_option('dg_stripe_enabled', 'no') === 'yes' && (bool) get_option('dg_stripe_publishable_key', '');

        if (class_exists('DG_Acc_Shortcode_Render')) {
            DG_Acc_Shortcode_Render::enqueue_checkout_script();
        }

        ob_start();
        ?>
        <div id="dg-booking-form" class="dg-booking-form<?php echo $compact ? ' dg-booking-form-compact' : ''; ?>" style="<?php echo esc_attr($wrap_style); ?>">
            <?php if (!$compact) : ?>
            <h3 style="color:#1C2B2A;margin:0 0 8px 0;">Book Your Stay</h3>
            <p style="margin:0 0 20px;color:#4A5B59;font-size:0.95rem;"><?php echo esc_html($accommodation); ?></p>
            <?php else : ?>
            <h3 class="dg-checkout-title" style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;color:#1C2B2A;margin:0 0 1rem;">Book Your Stay</h3>
            <?php endif; ?>
            <?php if ($booking_error) : ?>
            <div id="dg-checkout-error" style="display:block;margin-bottom:12px;padding:12px;background:#fdecea;color:#9b1c1c;border-radius:8px;font-size:0.9rem;"><?php echo esc_html($booking_error); ?></div>
            <?php else : ?>
            <div id="dg-checkout-error" style="display:none;margin-bottom:12px;padding:12px;background:#fdecea;color:#9b1c1c;border-radius:8px;font-size:0.9rem;"></div>
            <?php endif; ?>
            <div id="dg-enquiry-date-summary" style="<?php echo $has_dates ? '' : 'display:none;'; ?>background:#f5f2ef;padding:12px;border-radius:8px;margin-bottom:16px;">
                <?php if ($has_dates) : ?>
                <p style="margin:0 0 8px;font-size:0.9rem;">📅 <strong><?php echo esc_html(date('d M Y', strtotime($checkin))); ?></strong> → <strong><?php echo esc_html(date('d M Y', strtotime($checkout))); ?></strong></p>
                <p style="margin:0;font-size:0.9rem;"><?php echo (int) $quote['nights']; ?> night<?php echo $quote['nights'] > 1 ? 's' : ''; ?> · Subtotal $<?php echo number_format($quote['subtotal'], 2); ?>
                <?php if (!empty($quote['discount_amount'])) : ?> · <?php echo esc_html($quote['discount_type']); ?> -$<?php echo number_format($quote['discount_amount'], 2); ?><?php endif; ?>
                <?php if ($quote['cleaning_fee'] > 0) : ?> · Cleaning $<?php echo number_format($quote['cleaning_fee'], 2); ?><?php endif; ?>
                · <strong>Total $<?php echo number_format($quote['total'], 2); ?></strong></p>
                <?php else : ?>
                <p style="margin:0;font-size:0.9rem;color:#6B7A78;">Select your dates on the calendar to unlock checkout.</p>
                <?php endif; ?>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dg-checkout-form<?php echo $has_dates ? '' : ' is-disabled'; ?>">
                <?php wp_nonce_field('dg_enquiry_action', 'dg_enquiry_nonce'); ?>
                <input type="hidden" name="action" value="dg_payid_booking">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($form_action); ?>">
                <input type="hidden" id="dg-accommodation-id" name="accommodation_id" value="<?php echo (int) $accommodation_id; ?>">
                <input type="hidden" id="dg-booking-checkin" name="booking_checkin" value="<?php echo esc_attr($checkin); ?>">
                <input type="hidden" id="dg-booking-checkout" name="booking_checkout" value="<?php echo esc_attr($checkout); ?>">
                <input type="hidden" id="dg-booking-nights" value="<?php echo (int) $quote['nights']; ?>">
                <input type="hidden" id="dg-booking-total" value="<?php echo esc_attr(number_format($quote['total'], 2, '.', '')); ?>">
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div><label style="display:block;font-weight:600;margin-bottom:4px;">Name *</label>
                        <input type="text" id="enquiry_name" name="enquiry_name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;"></div>
                    <div><label style="display:block;font-weight:600;margin-bottom:4px;">Email *</label>
                        <input type="email" id="enquiry_email" name="enquiry_email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;"></div>
                </div>
                <div style="margin-top:10px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Phone</label>
                    <input type="tel" id="enquiry_phone" name="enquiry_phone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;"></div>
                <div style="margin-top:10px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Guests</label>
                    <select id="enquiry_guests" name="enquiry_guests" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Guest<?php echo $i > 1 ? 's' : ''; ?></option>
                        <?php endfor; ?>
                    </select></div>
                <div style="margin-top:10px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Special Requests</label>
                    <textarea id="enquiry_message" name="enquiry_message" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;resize:vertical;"></textarea></div>

                <div class="dg-payment-section" style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid #E8DFD3;">
                    <p class="dg-payment-label" style="margin:0 0 0.75rem;font-size:0.85rem;color:#5A6B67;text-align:center;">Choose how to pay and confirm</p>
                    <div class="dg-payment-buttons">
                        <button type="button" id="dg-payid-submit" class="dg-btn-payid">📱 Pay with PayID</button>
                        <?php if ($stripe_enabled) : ?>
                        <button type="button" id="dg-stripe-submit" class="dg-btn-card">💳 Pay with Card</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($stripe_enabled) : ?>
                    <div id="dg-stripe-panel" class="dg-stripe-panel" hidden>
                        <p class="dg-stripe-panel-label">Card details</p>
                        <div id="dg-stripe-card-element" class="dg-stripe-card-mount"></div>
                        <div id="dg-stripe-card-errors" class="dg-stripe-errors" role="alert"></div>
                        <p class="dg-stripe-panel-note">Secure payment via Stripe · Visa, Mastercard, Amex</p>
                    </div>
                    <?php else : ?>
                    <p style="margin:10px 0 0;font-size:0.75rem;color:#6B7A78;text-align:center;">PayID preferred — bank transfer details on the next page</p>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function book_now_calendar_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => 0], $atts);
        $accommodation_id = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::resolve_accommodation_id($atts['accommodation_id'])
            : (int) $atts['accommodation_id'];
        if (!$accommodation_id || !class_exists('DG_Acc_Calendar')) {
            return '';
        }
        list($checkin, $checkout) = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::parse_request_dates()
            : ['', ''];
        if (class_exists('DG_Acc_Shortcode_Render')) {
            DG_Acc_Shortcode_Render::enqueue_assets();
        }
        return DG_Acc_Calendar::render($accommodation_id, [
            'mode' => 'inline',
            'checkin' => $checkin,
            'checkout' => $checkout,
        ]);
    }

    public function book_now_checkout_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => 0, 'button_text' => 'Confirm Booking'], $atts);
        if (class_exists('DG_Acc_Shortcode_Render')) {
            DG_Acc_Shortcode_Render::enqueue_assets();
        }
        return $this->accommodation_enquiry_shortcode(array_merge($atts, ['layout' => 'compact']));
    }

    public function book_now_sidebar_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => 0], $atts);
        $accommodation_id = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::resolve_accommodation_id($atts['accommodation_id'])
            : (int) $atts['accommodation_id'];
        if (!$accommodation_id) {
            return '';
        }
        if (class_exists('DG_Acc_Shortcode_Render')) {
            DG_Acc_Shortcode_Render::enqueue_assets();
        }
        ob_start();
        ?>
        <aside class="dg-book-now-sidebar">
            <?php echo class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::render_rates($accommodation_id) : ''; ?>
            <?php echo class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::render_features($accommodation_id) : ''; ?>
            <?php echo class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::render_booking_summary($accommodation_id) : ''; ?>
            <div id="dg-book-now-checkout" class="dg-book-now-checkout">
                <?php echo $this->accommodation_enquiry_shortcode(['accommodation_id' => $accommodation_id, 'layout' => 'compact']); ?>
            </div>
            <?php echo class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::render_booking_rules() : ''; ?>
        </aside>
        <?php
        return ob_get_clean();
    }
    
    public function booking_confirmation_shortcode($atts) {
        try {
            $ref = isset($_GET['ref']) ? sanitize_text_field(wp_unslash($_GET['ref'])) : '';
            if (class_exists('DG_Acc_Shortcode_Render')) {
                return DG_Acc_Shortcode_Render::render_booking_confirmation($ref);
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DG booking confirmation shortcode: ' . $e->getMessage());
            }
            return '<div class="dg-booking-confirmed-wrap"><div class="dg-booking-confirmed-card"><p>We received your booking but could not display the confirmation page. Please check your email or contact us.</p><p><a href="' . esc_url(home_url('/')) . '">Return home</a></p></div></div>';
        }
        return '';
    }
    
    public function booking_calendar_shortcode($atts) {
        $atts = shortcode_atts(['accommodation_id' => 0, 'mode' => 'redirect'], $atts);
        $accommodation_id = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::resolve_accommodation_id($atts['accommodation_id'])
            : intval($atts['accommodation_id']);
        if (!$accommodation_id || !class_exists('DG_Acc_Calendar')) {
            return '<p>Please select an accommodation to view availability.</p>';
        }
        $booking_page_id = (int) get_option('dg_booking_page_id', 0);
        if ($atts['mode'] === 'redirect') {
            $resolved = class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::resolve_accommodation_id((int) $atts['accommodation_id']) : 0;
            if ($resolved && class_exists('DG_Acc_Frontend') && (
                DG_Acc_Frontend::is_accommodation_landing_page()
                || is_singular('dg_accommodation')
                || (int) $atts['accommodation_id'] === $resolved
            )) {
                $atts['mode'] = 'inline';
            }
        }
        if ($atts['mode'] === 'redirect' && $booking_page_id && is_page($booking_page_id)) {
            $atts['mode'] = 'inline';
        }
        list($checkin, $checkout) = class_exists('DG_Acc_Frontend')
            ? DG_Acc_Frontend::parse_request_dates()
            : ['', ''];
        return DG_Acc_Calendar::render($accommodation_id, [
            'mode' => $atts['mode'],
            'checkin' => $checkin,
            'checkout' => $checkout,
        ]);
    }

    /**
     * Strip legacy booking blocks on accommodation landing pages when needed.
     */
    public function filter_booking_page_content($content) {
        if (!is_singular() || is_admin()) {
            return $content;
        }

        try {
            return $this->filter_booking_page_content_inner($content);
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DG Accommodation booking page filter: ' . $e->getMessage());
            }
            return $content;
        }
    }

    private function filter_booking_page_content_inner($content) {
        $has_acc_shortcode = has_shortcode($content, 'dg_accommodation_page')
            || has_shortcode($content, 'dg_accommodation_details')
            || has_shortcode($content, 'dg_book_now')
            || has_shortcode($content, 'dg_calendar')
            || has_shortcode($content, 'dg_accommodation_enquiry');

        $hub_id = class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::get_hub_page_id() : 0;
        $is_booking_page = $has_acc_shortcode
            || (class_exists('DG_Acc_Frontend') && DG_Acc_Frontend::is_accommodation_landing_page())
            || (class_exists('DG_Acc_Frontend') && DG_Acc_Frontend::resolve_accommodation_id(0))
            || ($hub_id && is_page($hub_id))
            || is_page('accommodation');

        if (!$is_booking_page) {
            return $content;
        }

        if (class_exists('DG_Acc_Frontend')) {
            DG_Acc_Frontend::maybe_enqueue_legacy_cleanup();
        }

        $content = $this->strip_legacy_booking_blocks($content);

        if (has_shortcode($content, 'dg_book_now')) {
            return $content;
        }

        if (strpos($content, 'dg-booking-calendar-wrap') !== false) {
            return $content;
        }

        $accommodation_id = class_exists('DG_Acc_Frontend') ? DG_Acc_Frontend::resolve_accommodation_id(0) : 0;
        if (!$accommodation_id && class_exists('DG_Acc_Frontend')) {
            $accommodation_id = DG_Acc_Frontend::default_accommodation_id();
        }
        if ($accommodation_id && class_exists('DG_Acc_Calendar')) {
            list($checkin, $checkout) = class_exists('DG_Acc_Frontend')
                ? DG_Acc_Frontend::parse_request_dates()
                : ['', ''];
            $calendar = DG_Acc_Calendar::render($accommodation_id, [
                'mode' => 'inline',
                'checkin' => $checkin,
                'checkout' => $checkout,
            ]);
            $content = $calendar . $content;
        }

        return $content;
    }

    private function strip_legacy_booking_blocks($content) {
        if (!is_string($content) || $content === '') {
            return $content;
        }

        $needles = [
            'No Booking Details Found',
            'No booking details found',
            'check-in or check-out on a Saturday',
        ];

        foreach ($needles as $needle) {
            if (stripos($content, $needle) === false) {
                continue;
            }
            $content = $this->remove_html_blocks_containing($content, $needle);
        }

        if (stripos($content, 'dg-legacy-booking-summary') !== false) {
            $previous = null;
            while ($previous !== $content) {
                $previous = $content;
                $content = preg_replace('/<div[^>]*class="[^"]*dg-legacy-booking-summary[^"]*"[^>]*>.*?<\/div>/is', '', $content, 1);
            }
        }

        return $content;
    }

    /** Remove outer HTML blocks containing text without catastrophic backtracking. */
    private function remove_html_blocks_containing($content, $needle) {
        $tags = ['div', 'section', 'article', 'aside', 'p'];
        foreach ($tags as $tag) {
            $pattern = '/<' . $tag . '\\b[^>]*>/i';
            if (!preg_match_all($pattern, $content, $open_matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            for ($i = count($open_matches[0]) - 1; $i >= 0; $i--) {
                $start = $open_matches[0][$i][1];
                $close = stripos($content, '</' . $tag . '>', $start);
                if ($close === false) {
                    continue;
                }
                $close += strlen('</' . $tag . '>');
                $block = substr($content, $start, $close - $start);
                if (stripos($block, $needle) !== false) {
                    $content = substr($content, 0, $start) . substr($content, $close);
                }
            }
        }

        return $content;
    }

    public function book_now_shortcode($atts) {
        try {
            $atts = shortcode_atts(['accommodation_id' => 0], $atts);
            $preferred = (int) $atts['accommodation_id'];
            $accommodation_id = class_exists('DG_Acc_Frontend')
                ? DG_Acc_Frontend::resolve_accommodation_id($preferred)
                : $preferred;

            if (!$accommodation_id && class_exists('DG_Acc_Frontend')) {
                $accommodation_id = DG_Acc_Frontend::default_accommodation_id();
            }

            if (class_exists('DG_Acc_Shortcode_Render')) {
                return DG_Acc_Shortcode_Render::render_book_now($this, $accommodation_id);
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DG book_now shortcode: ' . $e->getMessage());
            }
            return '<p class="dg-muted">Booking is temporarily unavailable. Please call 0415 257 839.</p>';
        }
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
        $atts = shortcode_atts([
            'title' => 'Send Us a Message',
            'subtitle' => "We'll get back to you as soon as possible.",
            'recipient' => 'stay@currumbinvalleyhideaway.com.au',
        ], $atts);

        ob_start();
        ?>
        <div class="dg-contact-form-wrap" style="max-width:560px;margin:0 auto;">
            <h3 style="font-family:'Cormorant Garamond',serif;color:#1C2B2A;font-size:1.6rem;margin:0 0 0.35rem;"><?php echo esc_html($atts['title']); ?></h3>
            <?php if ($atts['subtitle']) : ?>
                <p style="color:#4A5B59;margin:0 0 1.25rem;font-size:0.95rem;"><?php echo esc_html($atts['subtitle']); ?></p>
            <?php endif; ?>
            <div id="dg-contact-success" style="display:none;padding:16px;background:#d4edda;color:#155724;border-radius:8px;margin-bottom:1rem;">✅ Thank you — your message has been sent. We'll reply within 24 hours.</div>
            <div id="dg-contact-error" style="display:none;padding:16px;background:#f8d7da;color:#721c24;border-radius:8px;margin-bottom:1rem;"></div>
            <div id="dg-contact-fields" style="display:grid;gap:14px;">
                <div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Full Name *</label>
                    <input type="text" id="dg-con-name" placeholder="Your full name" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;"></div>
                <div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Email Address *</label>
                    <input type="email" id="dg-con-email" placeholder="Your email address" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;"></div>
                <div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Phone Number</label>
                    <input type="tel" id="dg-con-phone" placeholder="Your phone number (optional)" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;"></div>
                <div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Message *</label>
                    <textarea id="dg-con-message" rows="5" placeholder="Tell us about your enquiry..." style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;resize:vertical;box-sizing:border-box;"></textarea></div>
                <button type="button" onclick="dgSubmitContact()" id="dg-con-submit" style="width:100%;padding:14px;background:#B9A48A;color:#fff;border:none;border-radius:40px;font-size:16px;font-weight:600;cursor:pointer;">Send Message</button>
            </div>
        </div>
        <script>
        function dgSubmitContact() {
            var btn = document.getElementById('dg-con-submit');
            var name = document.getElementById('dg-con-name').value.trim();
            var email = document.getElementById('dg-con-email').value.trim();
            var message = document.getElementById('dg-con-message').value.trim();
            if (!name || !email || !message) {
                document.getElementById('dg-contact-error').textContent = 'Please fill in name, email, and message.';
                document.getElementById('dg-contact-error').style.display = 'block';
                return;
            }
            var data = new FormData();
            data.append('action', 'dg_submit_contact');
            data.append('nonce', '<?php echo esc_js(wp_create_nonce('dg_contact_nonce')); ?>');
            data.append('name', name);
            data.append('email', email);
            data.append('phone', document.getElementById('dg-con-phone').value.trim());
            data.append('message', message);
            data.append('recipient', '<?php echo esc_js($atts['recipient']); ?>');
            btn.textContent = 'Sending...';
            btn.disabled = true;
            document.getElementById('dg-contact-error').style.display = 'none';
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        document.getElementById('dg-contact-success').style.display = 'block';
                        document.getElementById('dg-contact-fields').style.display = 'none';
                    } else {
                        document.getElementById('dg-contact-error').textContent = (res.data || 'Something went wrong. Please email us directly.');
                        document.getElementById('dg-contact-error').style.display = 'block';
                        btn.textContent = 'Send Message';
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    document.getElementById('dg-contact-error').textContent = 'Network error — please try again or email stay@currumbinvalleyhideaway.com.au';
                    document.getElementById('dg-contact-error').style.display = 'block';
                    btn.textContent = 'Send Message';
                    btn.disabled = false;
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
                var btn = this;
                var name = document.getElementById('enquiry_name')?.value.trim();
                var email = document.getElementById('enquiry_email')?.value.trim();
                if (!name || !email) { alert('Please fill in your name and email.'); return; }
                
                btn.disabled = true; btn.textContent = '⏳ Processing...';
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
                .then(data => {
                    if (data.error || !data.clientSecret) throw new Error(data.error || 'Could not start payment.');
                    return stripe.confirmCardPayment(data.clientSecret, {
                        payment_method: { card: cardElement, billing_details: { name: name, email: email } }
                    }).then(result => ({ result: result, booking_ref: data.booking_ref }));
                })
                .then(({ result, booking_ref }) => {
                    if (result.error) throw new Error(result.error.message);
                    return fetch('/wp-json/dg-stripe/v1/confirm-booking', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ payment_intent_id: result.paymentIntent.id })
                    }).then(r => r.json()).then(data => {
                        if (data.error) throw new Error(data.error);
                        return data.booking_ref || booking_ref;
                    });
                })
                .then(ref => {
                    window.location.href = '/booking-confirmed/?ref=' + encodeURIComponent(ref) + '&payment_method=stripe';
                })
                .catch(err => { alert('Payment error: ' + err.message); btn.disabled = false; btn.textContent = '💳 Pay Now'; });
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
        
        wp_mail(
            get_option('admin_email'),
            'New Enquiry from ' . $name,
            class_exists('DG_Email_Brand')
                ? DG_Email_Brand::admin_notification('New accommodation enquiry', [
                    'Name' => $name,
                    'Email' => $email,
                    'Phone' => $phone,
                    'Accommodation' => $accom,
                    'Source' => class_exists('DG_Email_Brand') ? DG_Email_Brand::booking_source_label('website') : 'Direct',
                    'Message' => $message,
                ], [
                    'theme' => 'cvh',
                    'footer_note' => 'Website enquiry — Currumbin Valley Hideaway',
                ])
                : "Name: $name\nEmail: $email\nPhone: $phone\nAccommodation: $accom\n\nMessage:\n$message",
            class_exists('DG_Email_Brand')
                ? DG_Email_Brand::mail_headers(true)
                : ['Content-Type: text/plain; charset=UTF-8']
        );
        
        wp_send_json_success(['ref' => 'ENQ-' . time()]);
    }
    
    public function handle_contact_submission() {
        if (!check_ajax_referer('dg_contact_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed.');
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $recipient = sanitize_email($_POST['recipient'] ?? '') ?: get_option('admin_email');

        if (!$name || !$email || !$message) {
            wp_send_json_error('All required fields must be filled in.');
        }

        $site_name = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : get_bloginfo('name');
        $admin_body = "Name: $name\nEmail: $email\nPhone: " . ($phone ?: 'Not provided') . "\n\nMessage:\n$message";

        if (class_exists('DG_Email_Brand')) {
            $admin_html = DG_Email_Brand::admin_notification('Contact form enquiry', [
                'Name' => $name,
                'Email' => $email,
                'Phone' => $phone ?: 'Not provided',
                'Message' => $message,
            ], [
                'theme' => 'cvh',
                'footer_note' => 'Website contact form — Currumbin Valley Hideaway',
            ]);
            $headers = array_merge(DG_Email_Brand::mail_headers(true), [
                'Reply-To: ' . $name . ' <' . $email . '>',
            ]);
            $sent = wp_mail($recipient, 'Contact form: ' . $name, $admin_html, $headers);

            $first_name = class_exists('DG_Email_Names') ? DG_Email_Names::first_name($name) : $name;
            $guest_inner = '<p style="margin:0 0 14px;line-height:1.6;">Dear ' . esc_html($first_name) . ',</p>'
                . '<p style="margin:0 0 14px;line-height:1.6;">Thank you for contacting ' . esc_html($site_name) . '.</p>'
                . '<p style="margin:0 0 14px;line-height:1.6;">We have received your message and will respond within 24 hours.</p>'
                . '<p style="margin:0 0 8px;color:#6B7A78;"><strong>Your message:</strong></p>'
                . '<p style="margin:0 0 14px;line-height:1.6;">' . nl2br(esc_html($message)) . '</p>'
                . '<p style="margin:0;line-height:1.6;">Warm regards,<br>' . esc_html($site_name) . ' Team</p>';
            $guest_html = DG_Email_Brand::wrap($guest_inner, [
                'theme' => 'cvh',
                'footer_note' => 'Currumbin Valley Hideaway — Gold Coast hinterland stays',
            ]);
            wp_mail($email, 'Thank you for your message — ' . $site_name, $guest_html, array_merge(
                DG_Email_Brand::mail_headers(true),
                ['From: ' . $site_name . ' <' . $recipient . '>']
            ));
        } else {
            $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>'];
            $sent = wp_mail($recipient, 'Contact form: ' . $name, $admin_body, $headers);
            $first_name = class_exists('DG_Email_Names') ? DG_Email_Names::first_name($name) : $name;
            $guest_body = "Dear $first_name,\n\nThank you for contacting $site_name.\n\nWe have received your message and will respond within 24 hours.\n\nYour message:\n$message\n\nWarm regards,\n$site_name Team";
            wp_mail($email, 'Thank you for your message — ' . $site_name, $guest_body, [
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . $site_name . ' <' . $recipient . '>',
            ]);
        }

        if (class_exists('DG_Activities')) {
            DG_Activities::log([
                'activity_type' => 'note',
                'subject' => 'Website contact form',
                'content' => $admin_body,
                'metadata' => ['source' => 'contact_form', 'email' => $email],
            ]);
        }

        if (!$sent) {
            wp_send_json_error('Could not send email. Please contact us directly.');
        }

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
