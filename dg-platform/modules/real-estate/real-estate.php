<?php
/**
 * Real Estate Module - Complete Agency CRM
 * Features: Vendor Leads, Appraisals, Listings, Buyers, Sales, Agents, Properties
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

class DG_Module_RealEstate {
    
    private static $instance = null;
    private $core;
    private $wpdb;
    private $includes_loaded = false;
    
    public static function get_instance($core = null) {
        if (null === self::$instance) {
            self::$instance = new self($core);
        }
        return self::$instance;
    }
    
    private function __construct($core) {
        global $wpdb;
        $this->core = $core;
        $this->wpdb = $wpdb;
        $this->load_includes();
        
        // Register hooks
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'create_tables']);
        add_action('init', [$this, 'maybe_upgrade_data'], 5);
        add_action('init', [$this, 'schedule_cron']);
        add_action('dg_platform_register_menus', [$this, 'register_menus'], 15);
        add_action('dg_platform_quick_actions', [$this, 'quick_actions']);
        
        // ============================================================
        // REMOVE DEFAULT ADMIN MENUS - KEEP ONLY IN DG PLATFORM
        // ============================================================
        add_action('admin_menu', [$this, 'remove_default_admin_menus'], 999);
        
        // Meta boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_property', [$this, 'save_property_meta']);
        add_action('save_post_agent', [$this, 'save_agent_meta']);
        
        add_action('admin_post_dg_re_save_email_templates', [$this, 'handle_save_email_templates']);
        
        // AJAX handlers
        add_action('wp_ajax_roe_realty_save_lead', [$this, 'save_lead_callback']);
        add_action('wp_ajax_nopriv_roe_realty_save_lead', [$this, 'save_lead_callback']);
        add_action('wp_ajax_roe_crm_get_available_slots', [$this, 'get_available_slots_callback']);
        add_action('wp_ajax_nopriv_roe_crm_get_available_slots', [$this, 'get_available_slots_callback']);
        add_action('wp_ajax_roe_crm_create_booking', [$this, 'create_booking_callback']);
        add_action('wp_ajax_nopriv_roe_crm_create_booking', [$this, 'create_booking_callback']);
        
        // Shortcodes
        add_shortcode('roe_properties', [$this, 'properties_shortcode']);
        add_shortcode('roe_property_display', [$this, 'property_display_shortcode']);
        add_shortcode('roe_agents', [$this, 'agents_shortcode']);
        add_shortcode('roe_agent_profile', [$this, 'agent_profile_shortcode']);
        add_shortcode('roe_crm_property_report_form', [$this, 'property_report_form_shortcode']);
        add_shortcode('roe_crm_booking_form', [$this, 'booking_form_shortcode']);
        
        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Admin columns
        add_filter('manage_property_posts_columns', [$this, 'property_admin_columns']);
        add_action('manage_property_posts_custom_column', [$this, 'property_admin_column_content'], 10, 2);
    }

    private function load_includes() {
        if ($this->includes_loaded) {
            return true;
        }

        $includes = __DIR__ . '/includes/';
        $files = [
            'class-re-contacts.php',
            'class-re-permissions.php',
            'class-lead-assignment.php',
            'class-email-templates.php',
            'class-vendor-leads.php',
            'class-buyer-leads.php',
            'class-property-report-followups.php',
            'class-pipeline-reports.php',
            'class-crm-dev-api.php',
            'booking-handler.php',
            'booking-shortcode.php',
            'properties-shortcodes.php',
            'property-display-shortcode.php',
            'agent-shortcodes.php',
            'property-report-leads.php',
            'rest-api.php',
        ];

        foreach ($files as $file) {
            $path = $includes . $file;
            if (!file_exists($path)) {
                continue;
            }
            require_once $path;
        }

        $this->includes_loaded = true;
        return true;
    }

    private function ensure_frontend_loaded() {
        return $this->load_includes();
    }
    
    // ============================================================
    // REMOVE DEFAULT ADMIN MENUS
    // ============================================================
    
    public function remove_default_admin_menus() {
        // Remove default Properties menu
        remove_menu_page('edit.php?post_type=property');
        // Remove default Agents menu
        remove_menu_page('edit.php?post_type=agent');
    }
    
    // ============================================================
    // POST TYPES
    // ============================================================
    
    public function register_post_types() {
        // Agent Post Type - Hidden from admin menu
        $labels = [
            'name'               => 'Agents',
            'singular_name'      => 'Agent',
            'menu_name'          => 'Agents',
            'add_new'            => 'Add New Agent',
            'add_new_item'       => 'Add New Agent',
            'edit_item'          => 'Edit Agent',
            'view_item'          => 'View Agent',
            'search_items'       => 'Search Agents',
            'not_found'          => 'No agents found',
            'all_items'          => 'All Agents',
        ];

        register_post_type('agent', [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // Hide from default menu
            'show_in_rest'        => true,
            'query_var'           => true,
            'rewrite'             => ['slug' => 'agent', 'with_front' => false],
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
            'menu_icon'           => 'dashicons-groups',
            'supports'            => ['title', 'editor', 'thumbnail', 'revisions', 'page-attributes'],
        ]);
        
        // Property Post Type - Hidden from admin menu
        $prop_labels = [
            'name'               => 'Properties',
            'singular_name'      => 'Property',
            'menu_name'          => 'Properties',
            'add_new'            => 'Add New Property',
            'add_new_item'       => 'Add New Property',
            'edit_item'          => 'Edit Property',
            'view_item'          => 'View Property',
            'search_items'       => 'Search Properties',
            'not_found'          => 'No properties found',
            'all_items'          => 'All Properties',
        ];

        register_post_type('property', [
            'labels'              => $prop_labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // Hide from default menu
            'show_in_rest'        => true,
            'query_var'           => true,
            'rewrite'             => ['slug' => 'property', 'with_front' => false],
            'capability_type'     => 'post',
            'has_archive'         => 'properties',
            'hierarchical'        => false,
            'menu_position'       => null,
            'menu_icon'           => 'dashicons-building',
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions'],
        ]);
    }
    
    public function maybe_upgrade_data() {
        if (class_exists('DG_RE_Email_Templates')) {
            DG_RE_Email_Templates::maybe_upgrade();
        }
    }

    // ============================================================
    // ADMIN MENUS - Everything under DG Platform
    // ============================================================
    
    public function register_menus() {
        $view_leads = DG_RE_Permissions::cap_view_leads();
        $manage_leads = DG_RE_Permissions::cap_manage_leads();
        $view_buyers = DG_RE_Permissions::cap_view_buyers();
        $view_listings = DG_RE_Permissions::cap_view_listings();
        $view_agents = DG_RE_Permissions::cap_view_agents();
        $view_appraisals = DG_RE_Permissions::cap_view_appraisals();
        $import_cap = DG_RE_Permissions::cap_import();

        add_submenu_page('dg-platform', 'Real Estate', '🏠 Real Estate', $view_leads, 'dg-re-dashboard', [$this, 'render_dashboard']);
        add_submenu_page('dg-platform', 'Properties', '🏷️ Properties', $view_listings, 'edit.php?post_type=property');
        add_submenu_page('dg-platform', 'Agents', '👤 Agents', $view_agents, 'edit.php?post_type=agent');
        add_submenu_page('dg-platform', 'Contacts', '📇 Contacts', $view_leads, 'dg-re-contacts', [$this, 'render_contacts']);
        add_submenu_page('dg-platform', 'Vendor Leads', '🎯 Vendor Leads', $view_leads, 'dg-re-vendor-leads', [$this, 'render_vendor_leads']);
        add_submenu_page(null, 'Vendor Lead', 'Vendor Lead', $view_leads, 'dg-re-vendor-lead', [$this, 'render_vendor_lead_detail']);
        add_submenu_page('dg-platform', 'Vendor Pipeline', '📋 Vendor Pipeline', $view_leads, 'dg-re-vendor-pipeline', [$this, 'render_vendor_pipeline']);
        add_submenu_page('dg-platform', 'Buyer Leads', '🛒 Buyer Leads', $view_buyers, 'dg-re-buyer-leads', [$this, 'render_buyer_leads']);
        add_submenu_page(null, 'Buyer Lead', 'Buyer Lead', $view_buyers, 'dg-re-buyer-lead', [$this, 'render_buyer_lead_detail']);
        add_submenu_page('dg-platform', 'Buyer Pipeline', '📋 Buyer Pipeline', $view_buyers, 'dg-re-buyer-pipeline', [$this, 'render_buyer_pipeline']);
        add_submenu_page('dg-platform', 'Pipeline Reports', '📊 Pipeline Reports', $view_leads, 'dg-re-pipeline-reports', [$this, 'render_pipeline_reports']);
        add_submenu_page('dg-platform', 'Bookings', '📅 Bookings', $view_appraisals, 'dg-re-bookings', [$this, 'render_bookings']);
        add_submenu_page('dg-platform', 'Email Templates', '✉️ Email Templates', $manage_leads, 'dg-re-email-templates', [$this, 'render_email_templates']);
        add_submenu_page('dg-platform', 'Import', '📥 Import', $import_cap, 'dg-re-import', [$this, 'render_import']);
    }
    
    // ============================================================
    // QUICK ACTIONS
    // ============================================================
    
    public function quick_actions() {
        echo '<a href="' . admin_url('admin.php?page=dg-re-dashboard') . '" class="button">🏠 Real Estate</a>';
        echo '<a href="' . admin_url('post-new.php?post_type=property') . '" class="button">➕ Add Property</a>';
        echo '<a href="' . admin_url('post-new.php?post_type=agent') . '" class="button">👤 Add Agent</a>';
    }
    
    // ============================================================
    // DATABASE TABLES
    // ============================================================
    
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $tables = [
            $wpdb->prefix . 'roe_crm_contacts' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                email varchar(100) NOT NULL,
                first_name varchar(50) DEFAULT NULL,
                last_name varchar(50) DEFAULT NULL,
                phone varchar(20) DEFAULT NULL,
                agent_id bigint(20) DEFAULT NULL,
                property_id bigint(20) DEFAULT NULL,
                source varchar(50) DEFAULT 'website',
                status varchar(20) DEFAULT 'active',
                last_activity datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY email (email),
                KEY agent_id (agent_id),
                KEY property_id (property_id)
            ",
            $wpdb->prefix . 'roe_crm_contact_meta' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                contact_id bigint(20) NOT NULL,
                meta_key varchar(191) NOT NULL,
                meta_value longtext,
                PRIMARY KEY (id),
                UNIQUE KEY contact_meta (contact_id, meta_key),
                KEY meta_key (meta_key)
            ",
            $wpdb->prefix . 'roe_crm_automations' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                trigger_type varchar(50) NOT NULL,
                trigger_settings longtext,
                steps longtext,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY is_active (is_active)
            ",
            $wpdb->prefix . 'roe_crm_automation_logs' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                automation_id bigint(20) NOT NULL,
                contact_id bigint(20) NOT NULL,
                property_id bigint(20) DEFAULT NULL,
                step_index int(11) NOT NULL,
                status varchar(20) DEFAULT 'pending',
                error_message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                processed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY automation_id (automation_id),
                KEY contact_id (contact_id),
                KEY status (status)
            ",
            $wpdb->prefix . 'roe_crm_bookings' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                contact_id bigint(20) NOT NULL,
                booking_type varchar(50) NOT NULL,
                service_name varchar(100) NOT NULL,
                booking_date date NOT NULL,
                booking_time time NOT NULL,
                duration int(11) DEFAULT 30,
                status varchar(20) DEFAULT 'pending',
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY contact_id (contact_id),
                KEY booking_date (booking_date),
                KEY booking_type (booking_type),
                KEY status (status)
            ",
            $wpdb->prefix . 'roe_crm_services' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                slug varchar(100) NOT NULL,
                description text,
                duration int(11) DEFAULT 30,
                price decimal(10,2) DEFAULT 0.00,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ",
            $wpdb->prefix . 'roe_crm_availability' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                day_of_week int(1) NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY day_of_week (day_of_week)
            ",
            $wpdb->prefix . 'roe_crm_holidays' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                date date NOT NULL,
                reason varchar(255) DEFAULT NULL,
                is_recurring tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY date (date)
            ",
            $wpdb->prefix . 'roe_property_sync' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                property_id bigint(20) NOT NULL,
                external_id varchar(255) NOT NULL,
                source varchar(100) NOT NULL,
                last_synced datetime DEFAULT NULL,
                sync_status varchar(20) DEFAULT 'active',
                sync_data longtext,
                PRIMARY KEY (id),
                UNIQUE KEY external_source (external_id, source)
            ",
            $wpdb->prefix . 'roe_import_logs' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                source varchar(100) NOT NULL,
                type varchar(50) NOT NULL,
                status varchar(20) NOT NULL,
                items_processed int(11) DEFAULT 0,
                items_success int(11) DEFAULT 0,
                items_failed int(11) DEFAULT 0,
                log_message text,
                started_at datetime DEFAULT NULL,
                completed_at datetime DEFAULT NULL,
                PRIMARY KEY (id)
            ",
            $wpdb->prefix . 'roe_realty_leads' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                full_name varchar(100) NOT NULL,
                first_name varchar(50) NOT NULL,
                email varchar(100) DEFAULT NULL,
                phone varchar(20) DEFAULT NULL,
                property_address text NOT NULL,
                submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
                email_2_sent tinyint(1) DEFAULT 0,
                email_3_sent tinyint(1) DEFAULT 0,
                email_4_sent tinyint(1) DEFAULT 0,
                email_5_sent tinyint(1) DEFAULT 0,
                PRIMARY KEY (id),
                KEY email (email),
                KEY submitted_at (submitted_at)
            "
        ];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($tables as $table_name => $table_sql) {
            $sql = "CREATE TABLE IF NOT EXISTS $table_name ($table_sql) $charset_collate;";
            dbDelta($sql);
        }
        
        // Add default data
        $this->add_default_services();
        $this->add_default_availability();
    }
    
    private function add_default_services() {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_crm_services';
        if ($wpdb->get_var("SELECT COUNT(*) FROM $table") > 0) return;
        
        $services = [
            ['name' => 'Property Appraisal', 'slug' => 'property-appraisal', 'description' => 'Free property valuation consultation - 30 minutes', 'duration' => 30, 'price' => 0.00],
            ['name' => 'Buyer Consultation', 'slug' => 'buyer-consultation', 'description' => 'Free buyer consultation - 30 minutes', 'duration' => 30, 'price' => 0.00],
            ['name' => 'Strategy Call', 'slug' => 'strategy-call', 'description' => '45-minute strategy session', 'duration' => 45, 'price' => 0.00],
            ['name' => 'Property Valuation', 'slug' => 'property-valuation', 'description' => 'Full property valuation consultation - 60 minutes', 'duration' => 60, 'price' => 0.00],
        ];
        foreach ($services as $service) {
            $wpdb->insert($table, $service);
        }
    }
    
    private function add_default_availability() {
        global $wpdb;
        $table = $wpdb->prefix . 'roe_crm_availability';
        if ($wpdb->get_var("SELECT COUNT(*) FROM $table") > 0) return;
        
        $availability = [
            ['day_of_week' => 0, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => 0],
            ['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => 1],
            ['day_of_week' => 2, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => 1],
            ['day_of_week' => 3, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => 1],
            ['day_of_week' => 4, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => 1],
            ['day_of_week' => 5, 'start_time' => '09:00:00', 'end_time' => '16:00:00', 'is_active' => 1],
            ['day_of_week' => 6, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_active' => 0],
        ];
        foreach ($availability as $slot) {
            $wpdb->insert($table, $slot);
        }
    }
    
    // ============================================================
    // CRON JOBS
    // ============================================================
    
    public function schedule_cron() {
        if (!wp_next_scheduled('roe_crm_process_automations')) {
            wp_schedule_event(time(), 'every_minute', 'roe_crm_process_automations');
        }
    }
    
    // ... (rest of the module code remains the same - all the methods below here are unchanged)
    
    // ============================================================
    // META BOXES
    // ============================================================
    
    public function add_meta_boxes() {
        add_meta_box('roe_agent_details', '👤 Agent Details', [$this, 'agent_details_meta_box'], 'agent', 'normal', 'high');
        add_meta_box('roe_property_details', '🏠 Property Details', [$this, 'property_details_meta_box'], 'property', 'normal', 'high');
    }
    
    public function agent_details_meta_box($post) {
        wp_nonce_field('roe_agent_details_nonce', 'roe_agent_details_nonce');
        $fields = [
            'roe_agent_title' => 'Job Title',
            'roe_agent_position' => 'Position / Role',
            'roe_agent_phone' => 'Phone',
            'roe_agent_email' => 'Email',
            'roe_agent_bio' => 'Bio / Description',
            'roe_agent_facebook' => 'Facebook',
            'roe_agent_instagram' => 'Instagram',
            'roe_agent_linkedin' => 'LinkedIn',
            'roe_agent_twitter' => 'Twitter / X',
            'roe_agent_youtube' => 'YouTube'
        ];
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:10px;">
            <?php foreach ($fields as $key => $label) : ?>
                <div style="<?php echo in_array($key, ['roe_agent_bio', 'roe_agent_youtube']) ? 'grid-column:1/-1;' : ''; ?>">
                    <label style="display:block;font-weight:600;margin-bottom:4px;font-size:13px;color:#1C2B2A;"><?php echo $label; ?></label>
                    <?php if ($key === 'roe_agent_bio') : ?>
                        <textarea name="<?php echo $key; ?>" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;min-height:80px;resize:vertical;"><?php echo esc_textarea(get_post_meta($post->ID, $key, true)); ?></textarea>
                    <?php else : ?>
                        <input type="<?php echo strpos($key, 'email') !== false ? 'email' : 'text'; ?>" name="<?php echo $key; ?>" value="<?php echo esc_attr(get_post_meta($post->ID, $key, true)); ?>" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    public function property_details_meta_box($post) {
        wp_nonce_field('roe_property_details_nonce', 'roe_property_details_nonce');
        $fields = [
            'roe_property_status' => ['label' => 'Property Status', 'type' => 'select', 'options' => ['For Sale', 'Under Contract', 'Sold', 'Withdrawn']],
            'roe_property_price' => ['label' => 'Price ($)', 'type' => 'number', 'placeholder' => 'e.g. 1950000'],
            'roe_property_type' => ['label' => 'Property Type', 'type' => 'select', 'options' => ['House', 'Apartment', 'Acreage', 'Townhouse', 'Land', 'Unit']],
            'roe_property_address' => ['label' => 'Street Address', 'type' => 'text', 'placeholder' => 'e.g. 12 Smith Street'],
            'roe_property_suburb' => ['label' => 'Suburb', 'type' => 'text', 'placeholder' => 'e.g. Currumbin'],
            'roe_property_state' => ['label' => 'State', 'type' => 'select', 'options' => ['QLD', 'NSW', 'VIC', 'ACT', 'SA', 'WA', 'TAS', 'NT']],
            'roe_property_postcode' => ['label' => 'Postcode', 'type' => 'text', 'placeholder' => 'e.g. 4223'],
            'roe_property_bedrooms' => ['label' => 'Bedrooms', 'type' => 'number', 'placeholder' => 'e.g. 4'],
            'roe_property_bathrooms' => ['label' => 'Bathrooms', 'type' => 'number', 'placeholder' => 'e.g. 3'],
            'roe_property_car_spaces' => ['label' => 'Car Spaces', 'type' => 'number', 'placeholder' => 'e.g. 2'],
            'roe_property_land_size' => ['label' => 'Land Size (m²)', 'type' => 'number', 'placeholder' => 'e.g. 2400'],
            'roe_property_building_size' => ['label' => 'Building Size (m²)', 'type' => 'number', 'placeholder' => 'e.g. 320'],
            'roe_property_year_built' => ['label' => 'Year Built', 'type' => 'number', 'placeholder' => 'e.g. 2020'],
            'roe_property_title' => ['label' => 'Property Title', 'type' => 'text', 'placeholder' => 'e.g. Modern Family Home with Ocean Views'],
            'roe_property_description' => ['label' => 'Description', 'type' => 'textarea'],
            'roe_property_features' => ['label' => 'Features / Highlights', 'type' => 'textarea'],
            'roe_property_gallery' => ['label' => 'Gallery Images (IDs)', 'type' => 'text', 'placeholder' => 'e.g. 123, 456, 789'],
            'roe_property_floorplans' => ['label' => 'Floorplans (IDs)', 'type' => 'text', 'placeholder' => 'e.g. 123, 456'],
            'roe_property_videos' => ['label' => 'Video URL', 'type' => 'text', 'placeholder' => 'https://www.youtube.com/watch?v=xxxx'],
            'roe_property_virtual_tour' => ['label' => 'Virtual Tour URL', 'type' => 'text', 'placeholder' => 'https://my.matterport.com/show/?m=xxxx'],
            'roe_property_inspection_times' => ['label' => 'Inspection Times', 'type' => 'text', 'placeholder' => 'e.g. Saturday 10:00-10:30am'],
            'roe_property_external_id' => ['label' => 'External Listing ID', 'type' => 'text', 'placeholder' => 'e.g. REA123456'],
        ];
        
        // Agent dropdown
        $agents = get_posts(['post_type' => 'agent', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish']);
        $selected_agent = get_post_meta($post->ID, 'roe_property_agent_id', true);
        ?>
        <style>
            .roe-meta-grid {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 15px;
                margin-top: 10px;
            }
            .roe-meta-grid .full-width { grid-column: 1 / -1; }
            .roe-meta-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
                font-size: 13px;
                color: #1C2B2A;
            }
            .roe-meta-field input,
            .roe-meta-field select,
            .roe-meta-field textarea {
                width: 100%;
                padding: 8px 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 13px;
                background: #fff;
            }
            .roe-meta-field .helper {
                font-size: 11px;
                color: #999;
                margin-top: 2px;
            }
            .roe-section-title {
                font-size: 16px;
                font-weight: 700;
                color: #1C2B2A;
                border-bottom: 2px solid #C9A46C;
                padding-bottom: 8px;
                margin: 15px 0 10px 0;
                grid-column: 1 / -1;
            }
            .roe-meta-field textarea {
                resize: vertical;
                min-height: 100px;
                line-height: 1.8;
                font-size: 14px !important;
            }
        </style>
        <div class="roe-meta-grid">
            <div class="roe-section-title">📍 Basic Information</div>
            
            <?php foreach ($fields as $key => $field) : 
                $value = get_post_meta($post->ID, $key, true);
                $full = in_array($key, ['roe_property_description', 'roe_property_features', 'roe_property_address', 'roe_property_gallery', 'roe_property_floorplans', 'roe_property_videos', 'roe_property_virtual_tour', 'roe_property_inspection_times']) ? 'full-width' : '';
            ?>
                <div class="roe-meta-field <?php echo $full; ?>">
                    <label for="<?php echo $key; ?>"><?php echo $field['label']; ?></label>
                    <?php if ($field['type'] === 'select') : ?>
                        <select name="<?php echo $key; ?>" id="<?php echo $key; ?>">
                            <option value="">Select...</option>
                            <?php foreach ($field['options'] as $option) : ?>
                                <option value="<?php echo $option; ?>" <?php selected($value, $option); ?>><?php echo $option; ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($field['type'] === 'textarea') : ?>
                        <textarea name="<?php echo $key; ?>" id="<?php echo $key; ?>" rows="6" placeholder="<?php echo $field['placeholder'] ?? ''; ?>"><?php echo esc_textarea($value); ?></textarea>
                    <?php else : ?>
                        <input type="<?php echo $field['type']; ?>" name="<?php echo $key; ?>" id="<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo $field['placeholder'] ?? ''; ?>">
                    <?php endif; ?>
                    <?php if (isset($field['helper'])) : ?>
                        <div class="helper"><?php echo $field['helper']; ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <div class="roe-section-title">👤 Agent Assignment</div>
            
            <div class="roe-meta-field full-width">
                <label for="roe_property_agent_id">Select Agent</label>
                <select name="roe_property_agent_id" id="roe_property_agent_id" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                    <option value="">— Select an Agent —</option>
                    <?php foreach ($agents as $agent) : 
                        $title = get_post_meta($agent->ID, 'roe_agent_title', true);
                        $display = $agent->post_title . ($title ? ' - ' . $title : '');
                    ?>
                        <option value="<?php echo $agent->ID; ?>" <?php selected($selected_agent, $agent->ID); ?>><?php echo esc_html($display); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="helper">Select the agent assigned to this property. Their details will be displayed automatically.</div>
            </div>
            
            <input type="hidden" name="roe_property_agent_name" value="<?php echo esc_attr(get_post_meta($post->ID, 'roe_property_agent_name', true)); ?>">
            <input type="hidden" name="roe_property_agent_phone" value="<?php echo esc_attr(get_post_meta($post->ID, 'roe_property_agent_phone', true)); ?>">
            <input type="hidden" name="roe_property_agent_email" value="<?php echo esc_attr(get_post_meta($post->ID, 'roe_property_agent_email', true)); ?>">
            <input type="hidden" name="roe_property_agent_photo" value="<?php echo esc_attr(get_post_meta($post->ID, 'roe_property_agent_photo', true)); ?>">
        </div>
        <?php
    }
    
    // ============================================================
    // SAVE META
    // ============================================================
    
    public function save_agent_meta($post_id) {
        if (!isset($_POST['roe_agent_details_nonce']) || !wp_verify_nonce($_POST['roe_agent_details_nonce'], 'roe_agent_details_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $fields = ['roe_agent_title', 'roe_agent_position', 'roe_agent_phone', 'roe_agent_email', 'roe_agent_bio', 
                   'roe_agent_facebook', 'roe_agent_instagram', 'roe_agent_linkedin', 'roe_agent_twitter', 'roe_agent_youtube'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, $field === 'roe_agent_bio' ? wp_kses_post($_POST[$field]) : sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    public function save_property_meta($post_id) {
        if (!isset($_POST['roe_property_details_nonce']) || !wp_verify_nonce($_POST['roe_property_details_nonce'], 'roe_property_details_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $fields = ['roe_property_status', 'roe_property_price', 'roe_property_type', 'roe_property_address', 'roe_property_suburb',
                   'roe_property_state', 'roe_property_postcode', 'roe_property_bedrooms', 'roe_property_bathrooms', 'roe_property_car_spaces',
                   'roe_property_land_size', 'roe_property_building_size', 'roe_property_year_built', 'roe_property_title',
                   'roe_property_description', 'roe_property_features', 'roe_property_gallery', 'roe_property_floorplans',
                   'roe_property_videos', 'roe_property_virtual_tour', 'roe_property_inspection_times', 'roe_property_external_id',
                   'roe_property_agent_id', 'roe_property_agent_name', 'roe_property_agent_phone', 'roe_property_agent_email',
                   'roe_property_agent_photo'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, in_array($field, ['roe_property_description', 'roe_property_features']) ? $_POST[$field] : sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    // ============================================================
    // ADMIN COLUMNS
    // ============================================================
    
    public function property_admin_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['price'] = 'Price';
                $new_columns['status'] = 'Status';
                $new_columns['beds'] = 'Beds';
                $new_columns['baths'] = 'Baths';
                $new_columns['cars'] = 'Cars';
                $new_columns['external_id'] = 'External ID';
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }
    
    public function property_admin_column_content($column, $post_id) {
        switch ($column) {
            case 'price':
                $this->ensure_frontend_loaded();
                $price = get_post_meta($post_id, 'roe_property_price', true);
                echo function_exists('roe_format_price') ? roe_format_price($price) : ($price ? esc_html($price) : '—');
                break;
            case 'status':
                $status = get_post_meta($post_id, 'roe_property_status', true);
                $colors = ['For Sale' => '#2E7D32', 'Under Contract' => '#F57C00', 'Sold' => '#C62828', 'Withdrawn' => '#666'];
                $color = isset($colors[$status]) ? $colors[$status] : '#666';
                echo '<span style="background:' . $color . ';color:#fff;padding:2px 12px;border-radius:12px;font-size:11px;display:inline-block;">' . esc_html($status) . '</span>';
                break;
            case 'beds': echo get_post_meta($post_id, 'roe_property_bedrooms', true) ?: '—'; break;
            case 'baths': echo get_post_meta($post_id, 'roe_property_bathrooms', true) ?: '—'; break;
            case 'cars': echo get_post_meta($post_id, 'roe_property_car_spaces', true) ?: '—'; break;
            case 'external_id': echo get_post_meta($post_id, 'roe_property_external_id', true) ?: '—'; break;
        }
    }
    
    public function register_rest_routes() {
        $this->ensure_frontend_loaded();
        if (function_exists('dg_re_register_rest_routes')) {
            dg_re_register_rest_routes();
        }
    }

    public function properties_shortcode($atts) {
        $this->ensure_frontend_loaded();
        return function_exists('roe_properties_shortcode') ? roe_properties_shortcode($atts) : '';
    }

    public function property_display_shortcode($atts = []) {
        $this->ensure_frontend_loaded();
        return function_exists('roe_property_display_shortcode') ? roe_property_display_shortcode() : '';
    }

    public function agents_shortcode($atts) {
        $this->ensure_frontend_loaded();
        return function_exists('roe_agents_shortcode') ? roe_agents_shortcode($atts) : '';
    }

    public function agent_profile_shortcode($atts = []) {
        $this->ensure_frontend_loaded();
        return function_exists('roe_agent_profile_shortcode') ? roe_agent_profile_shortcode() : '';
    }

    public function booking_form_shortcode($atts = []) {
        return function_exists('roe_crm_booking_form_shortcode')
            ? roe_crm_booking_form_shortcode($atts)
            : '';
    }

    public function property_report_form_shortcode($atts = []) {
        $this->ensure_frontend_loaded();
        return function_exists('roe_crm_property_report_form_shortcode')
            ? roe_crm_property_report_form_shortcode()
            : '';
    }

    public function save_lead_callback() {
        $this->ensure_frontend_loaded();

        if (!function_exists('dg_re_process_property_report_lead')) {
            wp_send_json_error(['message' => 'Property report handler is unavailable.'], 500);
        }

        $result = dg_re_process_property_report_lead(wp_unslash($_POST));

        if (!empty($result['success'])) {
            wp_send_json_success(['message' => $result['message']]);
        }

        wp_send_json_error(['message' => $result['message'] ?? 'Unable to process report request.']);
    }

    public function get_available_slots_callback() {
        $date = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
        $service_id = (int) ($_POST['service_id'] ?? 0);
        if (!$date || !$service_id) {
            wp_send_json_error(['message' => 'Missing booking details.']);
        }
        $booking = new Roe_CRM_Booking();
        wp_send_json_success(['slots' => $booking->get_available_slots($date, $service_id)]);
    }

    public function create_booking_callback() {
        if (!function_exists('dg_re_process_booking_creation')) {
            wp_send_json_error(['message' => 'Booking handler is unavailable.'], 500);
        }

        $result = dg_re_process_booking_creation(wp_unslash($_POST));

        if (!empty($result['success'])) {
            wp_send_json_success([
                'message' => $result['message'],
                'booking_id' => $result['booking_id'] ?? null,
            ]);
        }

        wp_send_json_error(['message' => $result['message'] ?? 'Unable to create booking.']);
    }
    
    // ============================================================
    // RENDER PAGES
    // ============================================================
    
    public function render_dashboard() {
        global $wpdb;
        $counts = [
            'properties' => wp_count_posts('property')->publish,
            'agents' => wp_count_posts('agent')->publish,
            'contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}roe_crm_contacts"),
            'vendor_leads' => class_exists('DG_RE_Vendor_Leads') ? DG_RE_Vendor_Leads::count('new') : 0,
            'buyer_leads' => class_exists('DG_RE_Buyer_Leads') ? DG_RE_Buyer_Leads::count('new') : 0,
            'bookings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}roe_crm_bookings"),
        ];
        ?>
        <div class="wrap">
            <h1>🏠 Real Estate Dashboard</h1>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0;">
                <div style="background:#fff;padding:20px;border-radius:12px;border-left:4px solid #C9A46C;"><div style="font-size:28px;font-weight:700;"><?php echo $counts['properties']; ?></div><div style="color:#666;">Properties</div></div>
                <div style="background:#fff;padding:20px;border-radius:12px;border-left:4px solid #1565C0;"><div style="font-size:28px;font-weight:700;"><?php echo $counts['agents']; ?></div><div style="color:#666;">Agents</div></div>
                <div style="background:#fff;padding:20px;border-radius:12px;border-left:4px solid #7B1FA2;"><div style="font-size:28px;font-weight:700;"><?php echo $counts['contacts']; ?></div><div style="color:#666;">Contacts</div></div>
                <div style="background:#fff;padding:20px;border-radius:12px;border-left:4px solid #00897B;"><div style="font-size:28px;font-weight:700;"><?php echo $counts['vendor_leads']; ?></div><div style="color:#666;">New Vendor Leads</div></div>
                <div style="background:#fff;padding:20px;border-radius:12px;border-left:4px solid #5E35B1;"><div style="font-size:28px;font-weight:700;"><?php echo $counts['buyer_leads']; ?></div><div style="color:#666;">New Buyer Enquiries</div></div>
                <div style="background:#fff;padding:20px;border-radius:12px;border-left:4px solid #F57C00;"><div style="font-size:28px;font-weight:700;"><?php echo $counts['bookings']; ?></div><div style="color:#666;">Bookings</div></div>
            </div>
            <div style="background:#fff;padding:20px;border-radius:12px;border:1px solid #ddd;">
                <h3>🚀 Quick Actions</h3>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <a href="<?php echo admin_url('post-new.php?post_type=property'); ?>" class="button button-primary">➕ Add Property</a>
                    <a href="<?php echo admin_url('post-new.php?post_type=agent'); ?>" class="button">👤 Add Agent</a>
                    <a href="<?php echo admin_url('admin.php?page=dg-re-vendor-pipeline'); ?>" class="button">📋 Vendor Pipeline</a>
                    <a href="<?php echo admin_url('admin.php?page=dg-re-pipeline-reports'); ?>" class="button">📊 Pipeline Reports</a>
                    <a href="<?php echo admin_url('admin.php?page=dg-re-buyer-leads'); ?>" class="button">🛒 Buyer Leads</a>
                    <a href="<?php echo admin_url('admin.php?page=dg-re-import'); ?>" class="button">📥 Import Properties</a>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_vendor_leads() {
        if (!class_exists('DG_RE_Vendor_Leads')) {
            echo '<div class="wrap"><h1>Vendor Leads</h1><p>Vendor leads service is unavailable.</p></div>';
            return;
        }

        if (isset($_POST['dg_re_update_lead_status']) && check_admin_referer('dg_re_vendor_lead_status')) {
            $lead_id = (int) ($_POST['lead_id'] ?? 0);
            $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
            if ($lead_id && DG_RE_Vendor_Leads::update_status($lead_id, $status)) {
                echo '<div class="notice notice-success is-dismissible"><p>Lead status updated.</p></div>';
            }
        }

        $status_filter = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));
        $assigned_filter = (int) ($_GET['assigned_to'] ?? 0);
        $leads = DG_RE_Vendor_Leads::list([
            'status' => $status_filter !== '' ? $status_filter : null,
            'assigned_to' => $assigned_filter > 0 ? $assigned_filter : null,
            'limit' => 100,
        ]);
        $statuses = DG_RE_Vendor_Leads::statuses();
        $assignable_users = class_exists('DG_RE_Lead_Assignment') ? DG_RE_Lead_Assignment::users() : [];
        ?>
        <div class="wrap">
            <h1>🎯 Vendor Leads</h1>
            <p style="color:#666;">Property report submissions and other vendor acquisition sources. <a href="<?php echo esc_url(admin_url('admin.php?page=dg-re-vendor-pipeline')); ?>">View pipeline board →</a></p>
            <form method="get" style="margin:12px 0;display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="page" value="dg-re-vendor-leads">
                <?php if ($status_filter !== '') : ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
                <label>Assigned:</label>
                <select name="assigned_to" onchange="this.form.submit()">
                    <option value="0">All</option>
                    <?php foreach ($assignable_users as $user) : ?>
                        <option value="<?php echo (int) $user->ID; ?>" <?php selected($assigned_filter, (int) $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <ul class="subsubsub">
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=dg-re-vendor-leads')); ?>" <?php echo $status_filter === '' ? 'class="current"' : ''; ?>>All</a> |</li>
                <?php foreach ($statuses as $key => $label) : ?>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=dg-re-vendor-leads&status=' . $key)); ?>" <?php echo $status_filter === $key ? 'class="current"' : ''; ?>><?php echo esc_html($label); ?></a><?php echo $key !== 'lost' ? ' |' : ''; ?></li>
                <?php endforeach; ?>
            </ul>
            <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Contact</th>
                        <th>Property</th>
                        <th>Source</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($leads) : foreach ($leads as $lead) :
                        $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
                        $email = $lead->email ?? '';
                        if (strpos($email, '@leads.roerealty.local') !== false) {
                            $email = '';
                        }
                        ?>
                        <tr>
                            <td><?php echo (int) $lead->id; ?></td>
                            <td>
                                <strong><a href="<?php echo esc_url(admin_url('admin.php?page=dg-re-vendor-lead&id=' . (int) $lead->id)); ?>"><?php echo esc_html($name !== '' ? $name : 'Unknown'); ?></a></strong><br>
                                <?php if ($email) : ?><small><?php echo esc_html($email); ?></small><br><?php endif; ?>
                                <?php if (!empty($lead->phone)) : ?><small><?php echo esc_html($lead->phone); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo esc_html($lead->property_address); ?></td>
                            <td><?php echo esc_html(str_replace('_', ' ', $lead->source)); ?></td>
                            <td><?php echo esc_html(str_replace('_', ' ', $lead->stage ?? 'vendor_lead')); ?></td>
                            <td>
                                <form method="post" style="display:flex;gap:6px;align-items:center;">
                                    <?php wp_nonce_field('dg_re_vendor_lead_status'); ?>
                                    <input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>">
                                    <select name="status">
                                        <?php foreach ($statuses as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($lead->status, $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="dg_re_update_lead_status" class="button button-small">Save</button>
                                </form>
                            </td>
                            <td><?php echo esc_html(DG_RE_Lead_Assignment::user_label($lead->assigned_to ?? 0)); ?></td>
                            <td><?php echo esc_html($lead->created_at); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="8" style="text-align:center;padding:30px 0;color:#999;">No vendor leads yet. Submissions from the property report form will appear here.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_vendor_lead_detail() {
        $this->render_lead_detail_page('vendor');
    }

    public function render_buyer_lead_detail() {
        $this->render_lead_detail_page('buyer');
    }

    private function render_lead_detail_page($type) {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            echo '<div class="wrap"><p>Invalid lead.</p></div>';
            return;
        }

        if ($type === 'vendor' && class_exists('DG_RE_Vendor_Leads')) {
            if (isset($_POST['dg_re_save_lead_notes']) && check_admin_referer('dg_re_lead_detail')) {
                DG_RE_Vendor_Leads::update_notes($id, wp_unslash($_POST['notes'] ?? ''));
                if (!empty($_POST['status'])) {
                    DG_RE_Vendor_Leads::update_status($id, sanitize_text_field(wp_unslash($_POST['status'])));
                }
                if (!empty($_POST['stage'])) {
                    DG_RE_Vendor_Leads::advance_stage($id, sanitize_text_field(wp_unslash($_POST['stage'])));
                }
                DG_RE_Vendor_Leads::assign($id, (int) ($_POST['assigned_to'] ?? 0));
                echo '<div class="notice notice-success"><p>Lead updated.</p></div>';
            }
            $lead = DG_RE_Vendor_Leads::get($id);
            $statuses = DG_RE_Vendor_Leads::statuses();
            $stages = DG_RE_Vendor_Leads::stages();
            $entity_type = 're_lead';
            $back_url = admin_url('admin.php?page=dg-re-vendor-leads');
            $title = 'Vendor Lead';
        } elseif ($type === 'buyer' && class_exists('DG_RE_Buyer_Leads')) {
            if (isset($_POST['dg_re_save_lead_notes']) && check_admin_referer('dg_re_lead_detail')) {
                if (!empty($_POST['status'])) {
                    DG_RE_Buyer_Leads::update_status($id, sanitize_text_field(wp_unslash($_POST['status'])));
                }
                if (!empty($_POST['stage'])) {
                    DG_RE_Buyer_Leads::advance_stage($id, sanitize_text_field(wp_unslash($_POST['stage'])));
                }
                DG_RE_Buyer_Leads::assign($id, (int) ($_POST['assigned_to'] ?? 0));
                echo '<div class="notice notice-success"><p>Lead updated.</p></div>';
            }
            $lead = DG_RE_Buyer_Leads::get($id);
            $statuses = DG_RE_Buyer_Leads::statuses();
            $stages = DG_RE_Buyer_Leads::stages();
            $entity_type = 're_buyer';
            $back_url = admin_url('admin.php?page=dg-re-buyer-leads');
            $title = 'Buyer Lead';
        } else {
            echo '<div class="wrap"><p>Lead service unavailable.</p></div>';
            return;
        }

        if (!$lead) {
            echo '<div class="wrap"><p>Lead not found.</p></div>';
            return;
        }

        $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
        $email = class_exists('DG_RE_Contacts') ? DG_RE_Contacts::display_email($lead->email ?? '') : ($lead->email ?? '');
        $activities = class_exists('DG_Activities') ? array_merge(
            DG_Activities::get_for_entity($entity_type, $id, 30),
            DG_Activities::get_for_contact((int) $lead->contact_id, 30)
        ) : [];
        usort($activities, function ($a, $b) {
            return strcmp($b->created_at, $a->created_at);
        });
        $activities = array_slice($activities, 0, 30);
        $meta = json_decode($lead->pipeline_metadata ?? '{}', true);
        $assignable_users = class_exists('DG_RE_Lead_Assignment') ? DG_RE_Lead_Assignment::users() : [];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?> #<?php echo (int) $lead->id; ?></h1>
            <p><a href="<?php echo esc_url($back_url); ?>">← Back to list</a>
            <?php if (!empty($lead->dg_contact_id)) : ?>
                | <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-contacts&action=edit&id=' . (int) $lead->dg_contact_id)); ?>">View contact</a>
            <?php endif; ?>
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
                    <h2 style="margin-top:0;">Contact</h2>
                    <p><strong><?php echo esc_html($name ?: 'Unknown'); ?></strong></p>
                    <?php if ($email) : ?><p><?php echo esc_html($email); ?></p><?php endif; ?>
                    <?php if (!empty($lead->phone)) : ?><p><?php echo esc_html($lead->phone); ?></p><?php endif; ?>
                    <?php if ($type === 'vendor' && !empty($lead->property_address)) : ?>
                        <p><strong>Property:</strong> <?php echo esc_html($lead->property_address); ?></p>
                    <?php endif; ?>
                    <?php if ($type === 'buyer' && !empty($lead->requirements)) : ?>
                        <p><strong>Enquiry:</strong><br><?php echo nl2br(esc_html($lead->requirements)); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meta['property_url'])) : ?>
                        <p><a href="<?php echo esc_url($meta['property_url']); ?>" target="_blank">View property page</a></p>
                    <?php endif; ?>
                    <p style="color:#666;font-size:12px;">Created <?php echo esc_html($lead->created_at); ?></p>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
                    <h2 style="margin-top:0;">Pipeline</h2>
                    <form method="post">
                        <?php wp_nonce_field('dg_re_lead_detail'); ?>
                        <p>
                            <label>Stage</label><br>
                            <select name="stage">
                                <?php foreach ($stages as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($lead->stage ?? '', $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label>Status</label><br>
                            <select name="status">
                                <?php foreach ($statuses as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($lead->status, $key === 'new' && $type === 'buyer' ? 'active' : $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label>Assigned to</label><br>
                            <select name="assigned_to">
                                <option value="0">Unassigned</option>
                                <?php foreach ($assignable_users as $user) : ?>
                                    <option value="<?php echo (int) $user->ID; ?>" <?php selected((int) ($lead->assigned_to ?? 0), (int) $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <?php if ($type === 'vendor') : ?>
                        <p>
                            <label>Notes</label><br>
                            <textarea name="notes" rows="4" style="width:100%;"><?php echo esc_textarea($lead->notes ?? ''); ?></textarea>
                        </p>
                        <?php endif; ?>
                        <button type="submit" name="dg_re_save_lead_notes" class="button button-primary">Save</button>
                    </form>
                </div>
            </div>
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-top:20px;">
                <h2 style="margin-top:0;">Activity timeline</h2>
                <?php if ($activities) : ?>
                    <ul style="margin:0;padding-left:18px;">
                        <?php foreach ($activities as $activity) : ?>
                            <li style="margin-bottom:10px;">
                                <strong><?php echo esc_html($activity->subject ?: $activity->activity_type); ?></strong>
                                <span style="color:#888;font-size:12px;"> — <?php echo esc_html($activity->created_at); ?></span>
                                <?php if (!empty($activity->content)) : ?><br><span style="color:#666;"><?php echo esc_html(wp_trim_words($activity->content, 20)); ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p style="color:#999;">No activity logged yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_vendor_pipeline() {
        if (!class_exists('DG_RE_Vendor_Leads')) {
            echo '<div class="wrap"><h1>Vendor Pipeline</h1><p>Unavailable.</p></div>';
            return;
        }

        if (isset($_POST['dg_re_advance_vendor_stage']) && check_admin_referer('dg_re_vendor_pipeline')) {
            $lead_id = (int) ($_POST['lead_id'] ?? 0);
            $stage = sanitize_text_field(wp_unslash($_POST['stage'] ?? ''));
            if ($lead_id && DG_RE_Vendor_Leads::advance_stage($lead_id, $stage)) {
                echo '<div class="notice notice-success is-dismissible"><p>Lead moved to ' . esc_html(DG_RE_Vendor_Leads::stages()[$stage] ?? $stage) . '.</p></div>';
            }
        }

        $kanban = DG_RE_Vendor_Leads::list_for_kanban();
        $stages = DG_RE_Vendor_Leads::stages();
        $this->render_pipeline_board('Vendor Acquisition Pipeline', $kanban, $stages, 'dg-re-vendor-pipeline', 'dg_re_advance_vendor_stage', 'dg_re_vendor_pipeline');
    }

    public function render_buyer_leads() {
        if (!class_exists('DG_RE_Buyer_Leads')) {
            echo '<div class="wrap"><h1>Buyer Leads</h1><p>Unavailable.</p></div>';
            return;
        }

        if (isset($_POST['dg_re_update_buyer_status']) && check_admin_referer('dg_re_buyer_lead_status')) {
            $buyer_id = (int) ($_POST['buyer_id'] ?? 0);
            $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
            if ($buyer_id && DG_RE_Buyer_Leads::update_status($buyer_id, $status)) {
                echo '<div class="notice notice-success is-dismissible"><p>Buyer status updated.</p></div>';
            }
        }

        $assigned_filter = (int) ($_GET['assigned_to'] ?? 0);
        $leads = DG_RE_Buyer_Leads::list([
            'assigned_to' => $assigned_filter > 0 ? $assigned_filter : null,
            'limit' => 100,
        ]);
        $statuses = DG_RE_Buyer_Leads::statuses();
        $assignable_users = class_exists('DG_RE_Lead_Assignment') ? DG_RE_Lead_Assignment::users() : [];
        ?>
        <div class="wrap">
            <h1>🛒 Buyer Leads</h1>
            <p style="color:#666;">Property enquiry submissions. <a href="<?php echo esc_url(admin_url('admin.php?page=dg-re-buyer-pipeline')); ?>">View pipeline board →</a></p>
            <form method="get" style="margin:12px 0;display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="page" value="dg-re-buyer-leads">
                <label>Assigned:</label>
                <select name="assigned_to" onchange="this.form.submit()">
                    <option value="0">All</option>
                    <?php foreach ($assignable_users as $user) : ?>
                        <option value="<?php echo (int) $user->ID; ?>" <?php selected($assigned_filter, (int) $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                <thead><tr><th>ID</th><th>Contact</th><th>Requirements</th><th>Stage</th><th>Status</th><th>Assigned</th><th>Created</th></tr></thead>
                <tbody>
                    <?php if ($leads) : foreach ($leads as $lead) :
                        $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
                        $meta = json_decode($lead->pipeline_metadata ?? '{}', true);
                        $property = $meta['property_address'] ?? '';
                        ?>
                        <tr>
                            <td><?php echo (int) $lead->id; ?></td>
                            <td>
                                <strong><a href="<?php echo esc_url(admin_url('admin.php?page=dg-re-buyer-lead&id=' . (int) $lead->id)); ?>"><?php echo esc_html($name ?: 'Unknown'); ?></a></strong><br>
                                <?php $email = DG_RE_Contacts::display_email($lead->email ?? ''); ?>
                                <?php if ($email) : ?><small><?php echo esc_html($email); ?></small><br><?php endif; ?>
                                <?php if (!empty($lead->phone)) : ?><small><?php echo esc_html($lead->phone); ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($property) : ?><strong><?php echo esc_html($property); ?></strong><br><?php endif; ?>
                                <small><?php echo esc_html(wp_trim_words($lead->requirements ?? '', 20)); ?></small>
                            </td>
                            <td><?php echo esc_html(str_replace('_', ' ', $lead->stage ?? 'inquiry')); ?></td>
                            <td>
                                <form method="post" style="display:flex;gap:6px;align-items:center;">
                                    <?php wp_nonce_field('dg_re_buyer_lead_status'); ?>
                                    <input type="hidden" name="buyer_id" value="<?php echo (int) $lead->id; ?>">
                                    <select name="status">
                                        <?php foreach ($statuses as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($lead->status, $key === 'new' ? 'active' : $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="dg_re_update_buyer_status" class="button button-small">Save</button>
                                </form>
                            </td>
                            <td><?php echo esc_html(DG_RE_Lead_Assignment::user_label($lead->assigned_to ?? 0)); ?></td>
                            <td><?php echo esc_html($lead->created_at); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="7" style="text-align:center;padding:30px 0;color:#999;">No buyer enquiries yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_buyer_pipeline() {
        if (!class_exists('DG_RE_Buyer_Leads')) {
            echo '<div class="wrap"><h1>Buyer Pipeline</h1><p>Unavailable.</p></div>';
            return;
        }

        if (isset($_POST['dg_re_advance_buyer_stage']) && check_admin_referer('dg_re_buyer_pipeline')) {
            $buyer_id = (int) ($_POST['buyer_id'] ?? 0);
            $stage = sanitize_text_field(wp_unslash($_POST['stage'] ?? ''));
            if ($buyer_id && DG_RE_Buyer_Leads::advance_stage($buyer_id, $stage)) {
                echo '<div class="notice notice-success is-dismissible"><p>Buyer moved to ' . esc_html(DG_RE_Buyer_Leads::stages()[$stage] ?? $stage) . '.</p></div>';
            }
        }

        $kanban = DG_RE_Buyer_Leads::list_for_kanban();
        $stages = DG_RE_Buyer_Leads::stages();
        $this->render_pipeline_board('Buyer Acquisition Pipeline', $kanban, $stages, 'dg-re-buyer-pipeline', 'dg_re_advance_buyer_stage', 'dg_re_buyer_pipeline', 'buyer_id');
    }

    private function render_pipeline_board($title, $kanban, $stages, $page, $submit_name, $nonce_action, $id_field = 'lead_id') {
        ?>
        <div class="wrap">
            <h1>📋 <?php echo esc_html($title); ?></h1>
            <p style="color:#666;margin-bottom:16px;">Drag-free kanban — use the dropdown on each card to advance a lead.</p>
            <div style="display:grid;grid-template-columns:repeat(<?php echo count($stages); ?>, minmax(180px, 1fr));gap:12px;overflow-x:auto;padding-bottom:20px;">
                <?php foreach ($stages as $stage_key => $stage_label) : ?>
                    <div style="background:#f6f7f7;border-radius:8px;padding:10px;min-width:180px;">
                        <div style="font-weight:600;margin-bottom:10px;padding-bottom:8px;border-bottom:2px solid #C9A46C;">
                            <?php echo esc_html($stage_label); ?>
                            <span style="background:#ddd;border-radius:10px;padding:2px 8px;font-size:11px;margin-left:4px;"><?php echo count($kanban[$stage_key] ?? []); ?></span>
                        </div>
                        <?php if (!empty($kanban[$stage_key])) : foreach ($kanban[$stage_key] as $card) :
                            $name = trim(($card->first_name ?? '') . ' ' . ($card->last_name ?? ''));
                            $email = DG_RE_Contacts::display_email($card->email ?? '');
                            $address = $card->property_address ?? '';
                            if (!$address && !empty($card->pipeline_metadata)) {
                                $meta = json_decode($card->pipeline_metadata, true);
                                $address = $meta['property_address'] ?? '';
                            }
                            if (!$address && !empty($card->requirements)) {
                                $address = wp_trim_words($card->requirements, 8);
                            }
                            $record_id = $card->id;
                            ?>
                            <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:10px;margin-bottom:8px;font-size:13px;">
                                <strong><?php echo esc_html($name ?: 'Unknown'); ?></strong>
                                <?php if ($address) : ?><div style="color:#666;margin:4px 0;"><?php echo esc_html($address); ?></div><?php endif; ?>
                                <?php if ($email) : ?><div style="color:#888;font-size:11px;"><?php echo esc_html($email); ?></div><?php endif; ?>
                                <form method="post" style="margin-top:8px;">
                                    <?php wp_nonce_field($nonce_action); ?>
                                    <input type="hidden" name="<?php echo esc_attr($id_field); ?>" value="<?php echo (int) $record_id; ?>">
                                    <select name="stage" style="width:100%;font-size:11px;margin-bottom:4px;">
                                        <?php foreach ($stages as $sk => $sl) : ?>
                                            <option value="<?php echo esc_attr($sk); ?>" <?php selected($stage_key, $sk); ?>><?php echo esc_html($sl); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="<?php echo esc_attr($submit_name); ?>" class="button button-small" style="width:100%;">Move</button>
                                </form>
                            </div>
                        <?php endforeach; else : ?>
                            <div style="color:#aaa;font-size:12px;text-align:center;padding:16px 0;">Empty</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function render_contacts() {
        global $wpdb;
        $contacts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}roe_crm_contacts ORDER BY created_at DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1>📇 Contacts</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Source</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                    <?php if ($contacts) : foreach ($contacts as $contact) : ?>
                        <tr><td><?php echo $contact->id; ?></td><td><?php echo esc_html($contact->first_name . ' ' . $contact->last_name); ?></td><td><?php echo esc_html($contact->email); ?></td><td><?php echo esc_html($contact->phone); ?></td><td><?php echo esc_html($contact->source); ?></td><td><span style="background:<?php echo $contact->status === 'active' ? '#2E7D32' : '#C62828'; ?>;color:#fff;padding:2px 10px;border-radius:12px;font-size:11px;"><?php echo esc_html($contact->status); ?></span></td><td><?php echo $contact->created_at; ?></td></tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="7" style="text-align:center;padding:30px 0;color:#999;">No contacts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function render_pipeline_reports() {
        if (!class_exists('DG_RE_Pipeline_Reports')) {
            echo '<div class="wrap"><h1>Pipeline Reports</h1><p>Reports unavailable.</p></div>';
            return;
        }

        $vendor_stages = DG_RE_Pipeline_Reports::vendor_stage_counts();
        $vendor_sources = DG_RE_Pipeline_Reports::vendor_source_counts();
        $buyer_stages = DG_RE_Pipeline_Reports::buyer_stage_counts();
        $conversion = DG_RE_Pipeline_Reports::vendor_conversion_summary();
        $activity = DG_RE_Pipeline_Reports::recent_activity_summary(30);
        $bookings_month = DG_RE_Pipeline_Reports::bookings_this_month();
        $reports_month = DG_RE_Pipeline_Reports::property_reports_this_month();
        $vendor_total = array_sum(array_column($vendor_stages, 'count'));
        $buyer_total = array_sum(array_column($buyer_stages, 'count'));
        ?>
        <div class="wrap">
            <h1>📊 Pipeline Reports</h1>
            <p style="color:#666;">Vendor acquisition funnel, lead sources, and booking activity.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:20px 0;">
                <div style="background:#fff;padding:16px;border-radius:10px;border-left:4px solid #C9A46C;">
                    <div style="font-size:24px;font-weight:700;"><?php echo (int) $reports_month; ?></div>
                    <div style="color:#666;font-size:13px;">Property reports this month</div>
                </div>
                <div style="background:#fff;padding:16px;border-radius:10px;border-left:4px solid #F57C00;">
                    <div style="font-size:24px;font-weight:700;"><?php echo (int) $bookings_month; ?></div>
                    <div style="color:#666;font-size:13px;">Bookings this month</div>
                </div>
                <div style="background:#fff;padding:16px;border-radius:10px;border-left:4px solid #00897B;">
                    <div style="font-size:24px;font-weight:700;"><?php echo (int) $conversion['rate']; ?>%</div>
                    <div style="color:#666;font-size:13px;">Vendor → appraisal+ rate</div>
                </div>
                <div style="background:#fff;padding:16px;border-radius:10px;border-left:4px solid #1565C0;">
                    <div style="font-size:24px;font-weight:700;"><?php echo (int) $activity['vendor_leads']; ?></div>
                    <div style="color:#666;font-size:13px;">Vendor leads (30 days)</div>
                </div>
                <div style="background:#fff;padding:16px;border-radius:10px;border-left:4px solid #5E35B1;">
                    <div style="font-size:24px;font-weight:700;"><?php echo (int) $activity['buyer_leads']; ?></div>
                    <div style="color:#666;font-size:13px;">Buyer enquiries (30 days)</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
                    <h2 style="margin-top:0;">Vendor pipeline by stage</h2>
                    <p style="color:#888;font-size:12px;margin-top:-8px;"><?php echo (int) $vendor_total; ?> active leads total</p>
                    <table class="widefat striped" style="margin-top:12px;">
                        <thead><tr><th>Stage</th><th style="width:80px;text-align:right;">Count</th><th style="width:120px;">Share</th></tr></thead>
                        <tbody>
                            <?php foreach ($vendor_stages as $key => $row) :
                                $pct = $vendor_total > 0 ? round(($row['count'] / $vendor_total) * 100) : 0;
                                ?>
                                <tr>
                                    <td><?php echo esc_html($row['label']); ?></td>
                                    <td style="text-align:right;"><?php echo (int) $row['count']; ?></td>
                                    <td>
                                        <div style="background:#eee;border-radius:4px;height:8px;overflow:hidden;">
                                            <div style="background:#C9A46C;height:100%;width:<?php echo (int) $pct; ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin:12px 0 0;color:#666;font-size:12px;">
                        <?php echo (int) $conversion['appraisal_plus']; ?> of <?php echo (int) $conversion['total']; ?> vendor leads reached appraisal stage or beyond.
                    </p>
                </div>

                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
                    <h2 style="margin-top:0;">Leads by source</h2>
                    <?php if ($vendor_sources) : ?>
                        <table class="widefat striped" style="margin-top:12px;">
                            <thead><tr><th>Source</th><th style="width:80px;text-align:right;">Count</th></tr></thead>
                            <tbody>
                                <?php foreach ($vendor_sources as $row) : ?>
                                    <tr>
                                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $row->source))); ?></td>
                                        <td style="text-align:right;"><?php echo (int) $row->total; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p style="color:#999;">No vendor leads recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
                <h2 style="margin-top:0;">Buyer pipeline by stage</h2>
                <p style="color:#888;font-size:12px;margin-top:-8px;"><?php echo (int) $buyer_total; ?> active buyers total</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-top:12px;">
                    <?php foreach ($buyer_stages as $key => $row) : ?>
                        <div style="background:#f6f7f7;border-radius:6px;padding:12px;text-align:center;">
                            <div style="font-size:22px;font-weight:700;"><?php echo (int) $row['count']; ?></div>
                            <div style="font-size:12px;color:#666;"><?php echo esc_html($row['label']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_bookings() {
        global $wpdb;
        $bookings = $wpdb->get_results("SELECT b.*, c.email, c.first_name, c.last_name FROM {$wpdb->prefix}roe_crm_bookings b LEFT JOIN {$wpdb->prefix}roe_crm_contacts c ON b.contact_id = c.id ORDER BY b.booking_date DESC LIMIT 50");
        ?>
        <div class="wrap">
            <h1>📅 Bookings</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>ID</th><th>Contact</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if ($bookings) : foreach ($bookings as $booking) : ?>
                        <tr><td><?php echo $booking->id; ?></td><td><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></td><td><?php echo esc_html($booking->service_name); ?></td><td><?php echo date('M j, Y', strtotime($booking->booking_date)); ?></td><td><?php echo date('g:i A', strtotime($booking->booking_time)); ?></td><td><span style="background:<?php echo $booking->status === 'confirmed' ? '#2E7D32' : ($booking->status === 'pending' ? '#F57C00' : '#C62828'); ?>;color:#fff;padding:2px 10px;border-radius:12px;font-size:11px;"><?php echo ucfirst($booking->status); ?></span></td></tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="6" style="text-align:center;padding:30px 0;color:#999;">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_email_templates() {
        if (!class_exists('DG_RE_Email_Templates')) {
            echo '<div class="wrap"><p>Email templates unavailable.</p></div>';
            return;
        }
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Email templates saved.</p></div>';
        }
        $templates = DG_RE_Email_Templates::all();
        ?>
        <div class="wrap">
            <h1>✉️ Email Templates</h1>
            <p style="color:#666;">Placeholders: <?php echo esc_html(DG_RE_Email_Templates::placeholders_help()); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dg_re_save_email_templates'); ?>
                <input type="hidden" name="action" value="dg_re_save_email_templates">
                <?php foreach ($templates as $key => $template) : ?>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px;">
                        <h2 style="margin-top:0;font-size:16px;"><?php echo esc_html($template['label'] ?? $key); ?></h2>
                        <p>
                            <label>Subject</label><br>
                            <input type="text" name="templates[<?php echo esc_attr($key); ?>][subject]" value="<?php echo esc_attr($template['subject']); ?>" class="large-text">
                        </p>
                        <p>
                            <label>Body</label><br>
                            <textarea name="templates[<?php echo esc_attr($key); ?>][body]" rows="8" class="large-text code"><?php echo esc_textarea($template['body']); ?></textarea>
                        </p>
                    </div>
                <?php endforeach; ?>
                <p><button type="submit" class="button button-primary">Save all templates</button></p>
            </form>
            <div style="background:#F5F2EF;border-radius:8px;padding:16px;margin-top:8px;">
                <h3 style="margin-top:0;">Booking shortcodes for /card/</h3>
                <p style="margin-bottom:8px;">Add these to your Oxygen/Breakdance pages:</p>
                <code>[roe_crm_booking_form service="property-appraisal"]</code><br>
                <code>[roe_crm_booking_form service="strategy-call"]</code><br>
                <code>[roe_crm_booking_form]</code> — shows service picker
            </div>
        </div>
        <?php
    }

    public function handle_save_email_templates() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_re_save_email_templates') || !DG_RE_Permissions::can_manage_leads()) {
            wp_die('Unauthorized');
        }
        if (class_exists('DG_RE_Email_Templates') && isset($_POST['templates'])) {
            DG_RE_Email_Templates::save(wp_unslash($_POST['templates']));
        }
        wp_redirect(admin_url('admin.php?page=dg-re-email-templates&saved=1'));
        exit;
    }
    
    public function render_import() {
        ?>
        <div class="wrap">
            <h1>📥 Import Properties</h1>
            <div style="background:#fff;padding:20px;border:1px solid #ddd;margin:20px 0;border-radius:4px;">
                <h2>Upload File</h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('roe_import_action', 'roe_import_nonce'); ?>
                    <table class="form-table">
                        <tr><th><label for="import_file">File</label></th><td><input type="file" name="import_file" id="import_file" accept=".json,.xml,.csv"><p class="description">Supported formats: JSON, XML, CSV</p></td></tr>
                        <tr><th><label for="import_provider">Source Provider</label></th><td><select name="import_provider" id="import_provider"><option value="rea">REA</option><option value="domain">Domain</option><option value="vaultre">VaultRE</option><option value="agentbox">Agentbox</option></select></td></tr>
                        <tr><th><label for="import_format">Format</label></th><td><select name="import_format" id="import_format"><option value="json">JSON</option><option value="xml">XML</option><option value="csv">CSV</option></select></td></tr>
                    </table>
                    <p class="submit"><input type="submit" name="roe_import_submit" class="button-primary" value="Import Properties"></p>
                </form>
            </div>
            <?php if (isset($_POST['roe_import_submit']) && check_admin_referer('roe_import_action', 'roe_import_nonce')) {
                if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                    $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
                    $provider = sanitize_text_field($_POST['import_provider']);
                    $format = sanitize_text_field($_POST['import_format']);
                    $source = $_FILES['import_file']['name'];
                    $importer = new Roe_Property_Importer($source, $provider);
                    $result = $importer->import($file_content, $format);
                    echo '<div class="notice notice-success"><p>Import completed: ' . $result['success'] . ' imported, ' . $result['failed'] . ' failed.</p></div>';
                }
            } ?>
        </div>
        <?php
    }
}

// ============================================================
// REGISTER MODULE
// ============================================================

add_action('dg_platform_modules_loaded', function() {
    if (class_exists('DG_Platform')) {
        $core = DG_Platform::get_instance();
        $module = DG_Module_RealEstate::get_instance($core);
        $core->register_module('real-estate', $module);
    }
});

// ============================================================
// BOOKING CLASS
// ============================================================

if (!class_exists('Roe_CRM_Booking')) {
class Roe_CRM_Booking {
    private $table_bookings, $table_services, $table_availability, $table_holidays;
    
    public function __construct() {
        global $wpdb;
        $this->table_bookings = $wpdb->prefix . 'roe_crm_bookings';
        $this->table_services = $wpdb->prefix . 'roe_crm_services';
        $this->table_availability = $wpdb->prefix . 'roe_crm_availability';
        $this->table_holidays = $wpdb->prefix . 'roe_crm_holidays';
    }
    
    public function get_services($type = null) {
        global $wpdb;
        $where = $type ? $wpdb->prepare(" WHERE slug LIKE %s", '%' . $type . '%') : '';
        return $wpdb->get_results("SELECT * FROM {$this->table_services} WHERE is_active = 1 $where ORDER BY name");
    }
    
    public function get_service_by_slug($slug) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_services} WHERE slug = %s AND is_active = 1", $slug));
    }
    
    public function get_available_slots($date, $service_id) {
        global $wpdb;
        $service = $wpdb->get_row($wpdb->prepare("SELECT duration FROM {$this->table_services} WHERE id = %d", $service_id));
        if (!$service) return [];
        $day_of_week = date('w', strtotime($date));
        $availability = $wpdb->get_row($wpdb->prepare("SELECT start_time, end_time FROM {$this->table_availability} WHERE day_of_week = %d AND is_active = 1", $day_of_week));
        if (!$availability) return [];
        $is_holiday = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_holidays} WHERE date = %s", $date));
        if ($is_holiday > 0) return [];
        
        $slots = [];
        $start = strtotime($availability->start_time);
        $end = strtotime($availability->end_time);
        $duration = $service->duration * 60;
        $existing_bookings = $wpdb->get_results($wpdb->prepare("SELECT booking_time, duration FROM {$this->table_bookings} WHERE booking_date = %s AND status IN ('pending', 'confirmed')", $date));
        $booked_slots = [];
        foreach ($existing_bookings as $booking) {
            $booked_slots[] = ['start' => strtotime($booking->booking_time), 'end' => strtotime($booking->booking_time) + ($booking->duration * 60)];
        }
        $current = $start;
        while ($current + $duration <= $end) {
            $is_available = true;
            foreach ($booked_slots as $booked) {
                if ($current < $booked['end'] && $current + $duration > $booked['start']) {
                    $is_available = false;
                    break;
                }
            }
            if ($is_available) {
                $slots[] = date('H:i:s', $current);
            }
            $current += $duration;
        }
        return $slots;
    }
    
    public function create_booking($data) {
        global $wpdb;
        $result = $wpdb->insert($this->table_bookings, [
            'contact_id' => $data['contact_id'],
            'booking_type' => $data['booking_type'],
            'service_name' => $data['service_name'],
            'booking_date' => $data['booking_date'],
            'booking_time' => $data['booking_time'],
            'duration' => $data['duration'] ?? 30,
            'status' => 'pending',
            'notes' => $data['notes'] ?? '',
            'created_at' => current_time('mysql')
        ]);
        return $result ? $wpdb->insert_id : false;
    }
}
}

// ============================================================
// PROPERTY IMPORTER CLASS
// ============================================================

if (!class_exists('Roe_Property_Importer')) {
class Roe_Property_Importer {
    private $mapper;
    private $source;
    private $log_id;
    private $provider;
    private $wpdb;
    
    public function __construct($source, $provider = 'rea') {
        global $wpdb;
        $this->source = $source;
        $this->provider = $provider;
        $this->mapper = new Roe_Feed_Mapper();
        $this->wpdb = $wpdb;
    }
    
    public function import($data, $format = 'json') {
        $this->start_log();
        $items = $this->parse_data($data, $format);
        $processed = 0; $success = 0; $failed = 0;
        foreach ($items as $item) {
            $processed++;
            $result = $this->process_item($item);
            if ($result) $success++;
            else $failed++;
        }
        $this->end_log($processed, $success, $failed);
        return ['processed' => $processed, 'success' => $success, 'failed' => $failed];
    }
    
    private function parse_data($data, $format) {
        switch ($format) {
            case 'xml': return $this->parse_xml($data);
            case 'csv': return $this->parse_csv($data);
            case 'json': default: return json_decode($data, true) ?: [];
        }
    }
    
    private function parse_xml($data) {
        $xml = simplexml_load_string($data);
        if (!$xml) return [];
        $items = [];
        foreach ($xml as $item) {
            $items[] = json_decode(json_encode($item), true);
        }
        return $items;
    }
    
    private function parse_csv($data) {
        $lines = explode("\n", $data);
        if (empty($lines)) return [];
        $headers = str_getcsv(array_shift($lines));
        $items = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            $item = [];
            foreach ($headers as $index => $header) {
                if (isset($row[$index])) $item[$header] = $row[$index];
            }
            $items[] = $item;
        }
        return $items;
    }
    
    private function process_item($item) {
        $mapped = $this->mapper->get_mapped_fields($this->provider, $item);
        if (empty($mapped)) return false;
        $external_id = isset($mapped['roe_property_external_id']) ? $mapped['roe_property_external_id'] : '';
        if (empty($external_id)) return false;
        $existing = $this->find_by_external_id($external_id);
        return $existing ? $this->update_property($existing, $mapped) : $this->create_property($mapped);
    }
    
    private function find_by_external_id($external_id) {
        $sync_table = $this->wpdb->prefix . 'roe_property_sync';
        return $this->wpdb->get_var($this->wpdb->prepare("SELECT property_id FROM $sync_table WHERE external_id = %s AND source = %s", $external_id, $this->provider));
    }
    
    private function create_property($data) {
        $post_data = ['post_type' => 'property', 'post_status' => 'publish', 'post_title' => $this->get_title_from_data($data)];
        if (isset($data['roe_property_description'])) $post_data['post_content'] = $data['roe_property_description'];
        $property_id = wp_insert_post($post_data);
        if (is_wp_error($property_id)) return false;
        foreach ($data as $key => $value) {
            if (strpos($key, 'roe_property_') === 0) update_post_meta($property_id, $key, $value);
        }
        $this->save_sync_record($property_id, $data['roe_property_external_id']);
        return $property_id;
    }
    
    private function update_property($property_id, $data) {
        $post_data = ['ID' => $property_id, 'post_title' => $this->get_title_from_data($data)];
        if (isset($data['roe_property_description'])) $post_data['post_content'] = $data['roe_property_description'];
        wp_update_post($post_data);
        foreach ($data as $key => $value) {
            if (strpos($key, 'roe_property_') === 0) update_post_meta($property_id, $key, $value);
        }
        $this->update_sync_record($property_id);
        return $property_id;
    }
    
    private function get_title_from_data($data) {
        if (isset($data['roe_property_title']) && !empty($data['roe_property_title'])) return $data['roe_property_title'];
        if (isset($data['roe_property_address']) && isset($data['roe_property_suburb'])) return $data['roe_property_address'] . ', ' . $data['roe_property_suburb'];
        return 'Property ' . time();
    }
    
    private function save_sync_record($property_id, $external_id) {
        $sync_table = $this->wpdb->prefix . 'roe_property_sync';
        $this->wpdb->insert($sync_table, ['property_id' => $property_id, 'external_id' => $external_id, 'source' => $this->provider, 'last_synced' => current_time('mysql'), 'sync_status' => 'active']);
    }
    
    private function update_sync_record($property_id) {
        $sync_table = $this->wpdb->prefix . 'roe_property_sync';
        $this->wpdb->update($sync_table, ['last_synced' => current_time('mysql'), 'sync_status' => 'active'], ['property_id' => $property_id]);
    }
    
    private function start_log() {
        $log_table = $this->wpdb->prefix . 'roe_import_logs';
        $this->wpdb->insert($log_table, ['source' => $this->source, 'type' => 'import', 'status' => 'started', 'started_at' => current_time('mysql')]);
        $this->log_id = $this->wpdb->insert_id;
    }
    
    private function end_log($processed, $success, $failed) {
        $log_table = $this->wpdb->prefix . 'roe_import_logs';
        $status = ($failed > 0) ? 'completed_with_errors' : 'completed';
        $this->wpdb->update($log_table, ['status' => $status, 'items_processed' => $processed, 'items_success' => $success, 'items_failed' => $failed, 'completed_at' => current_time('mysql'), 'log_message' => "Processed $processed, Success: $success, Failed: $failed"], ['id' => $this->log_id]);
    }
}
}

// ============================================================
// FEED MAPPER CLASS
// ============================================================

if (!class_exists('Roe_Feed_Mapper')) {
class Roe_Feed_Mapper {
    private $internal_fields = [
        'external_id' => 'roe_property_external_id',
        'status' => 'roe_property_status',
        'price' => 'roe_property_price',
        'address' => 'roe_property_address',
        'suburb' => 'roe_property_suburb',
        'state' => 'roe_property_state',
        'postcode' => 'roe_property_postcode',
        'bedrooms' => 'roe_property_bedrooms',
        'bathrooms' => 'roe_property_bathrooms',
        'car_spaces' => 'roe_property_car_spaces',
        'land_size' => 'roe_property_land_size',
        'building_size' => 'roe_property_building_size',
        'year_built' => 'roe_property_year_built',
        'property_type' => 'roe_property_type',
        'title' => 'roe_property_title',
        'description' => 'roe_property_description',
        'features' => 'roe_property_features',
        'gallery' => 'roe_property_gallery',
        'floorplans' => 'roe_property_floorplans',
        'videos' => 'roe_property_videos',
        'virtual_tour' => 'roe_property_virtual_tour',
        'inspection_times' => 'roe_property_inspection_times',
        'agent_name' => 'roe_property_agent_name',
        'agent_phone' => 'roe_property_agent_phone',
        'agent_email' => 'roe_property_agent_email'
    ];
    
    private $provider_mappings = [
        'rea' => [
            'id' => 'external_id', 'status' => 'status', 'price' => 'price', 'address' => 'address',
            'suburb' => 'suburb', 'state' => 'state', 'postcode' => 'postcode', 'bedrooms' => 'bedrooms',
            'bathrooms' => 'bathrooms', 'carSpaces' => 'car_spaces', 'landSize' => 'land_size',
            'buildingSize' => 'building_size', 'yearBuilt' => 'year_built', 'propertyType' => 'property_type',
            'headline' => 'title', 'description' => 'description', 'features' => 'features',
            'images' => 'gallery', 'floorplans' => 'floorplans', 'videoUrl' => 'videos',
            'virtualTour' => 'virtual_tour', 'inspectionTimes' => 'inspection_times',
            'agentName' => 'agent_name', 'agentPhone' => 'agent_phone', 'agentEmail' => 'agent_email'
        ],
        'domain' => [
            'listingId' => 'external_id', 'listingStatus' => 'status', 'price' => 'price',
            'streetAddress' => 'address', 'suburb' => 'suburb', 'state' => 'state', 'postcode' => 'postcode',
            'bedroomCount' => 'bedrooms', 'bathroomCount' => 'bathrooms', 'parkingCount' => 'car_spaces',
            'landArea' => 'land_size', 'buildingArea' => 'building_size', 'propertyType' => 'property_type',
            'title' => 'title', 'description' => 'description', 'features' => 'features', 'media' => 'gallery'
        ],
        'vaultre' => [
            'ListingID' => 'external_id', 'ListingStatus' => 'status', 'ListPrice' => 'price',
            'StreetAddress' => 'address', 'Suburb' => 'suburb', 'State' => 'state', 'Postcode' => 'postcode',
            'Bedrooms' => 'bedrooms', 'Bathrooms' => 'bathrooms', 'Parking' => 'car_spaces',
            'LandSize' => 'land_size', 'PropertyType' => 'property_type', 'Headline' => 'title',
            'Description' => 'description'
        ],
        'agentbox' => [
            'id' => 'external_id', 'status' => 'status', 'price' => 'price', 'address' => 'address',
            'suburb' => 'suburb', 'postcode' => 'postcode', 'beds' => 'bedrooms', 'baths' => 'bathrooms',
            'cars' => 'car_spaces', 'land' => 'land_size', 'type' => 'property_type', 'title' => 'title',
            'desc' => 'description'
        ]
    ];
    
    public function get_mapped_fields($provider, $external_data) {
        $mapped_data = [];
        $mapping = isset($this->provider_mappings[$provider]) ? $this->provider_mappings[$provider] : [];
        foreach ($mapping as $external_field => $internal_key) {
            if (isset($external_data[$external_field])) {
                $internal_field = $this->internal_fields[$internal_key];
                $mapped_data[$internal_field] = $external_data[$external_field];
            }
        }
        return $mapped_data;
    }
}
}

// ============================================================
// ENQUIRY HANDLER
// ============================================================

if (!has_action('init', 'dg_re_handle_property_enquiry_submit')) {
add_action('init', 'dg_re_handle_property_enquiry_submit');
function dg_re_handle_property_enquiry_submit() {
    if (!isset($_POST['submit_enquiry'])) {
        return;
    }
    if (!isset($_POST['dg_re_enquiry_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dg_re_enquiry_nonce'])), 'dg_re_property_enquiry')) {
        return;
    }

    if (!function_exists('dg_re_process_property_enquiry')) {
        return;
    }

    $result = dg_re_process_property_enquiry(wp_unslash($_POST));
    $url = wp_get_referer() ?: home_url('/');
    $url = remove_query_arg(['enquiry_sent', 'enquiry_error'], $url);
    $url = add_query_arg(!empty($result['success']) ? 'enquiry_sent' : 'enquiry_error', '1', $url);
    wp_safe_redirect($url);
    exit;
}
}