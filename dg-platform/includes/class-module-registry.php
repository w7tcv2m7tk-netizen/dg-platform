<?php
/**
 * Module registry — load, register, and validate modules.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Module_Registry {

    private $definitions = [];
    private $instances = [];
    private $pipelines = [];
    private $activity_types = [];

    public function __construct() {
        $this->define_core_modules();
    }

    private function define_core_modules() {
        $this->definitions = [
            'core' => [
                'key' => 'core',
                'name' => 'Core Engine',
                'icon' => '⚙️',
                'description' => 'Contacts, Tasks, Calendar, Activities, Documents',
                'version' => DG_PLATFORM_VERSION,
                'is_core' => true,
                'required' => true,
                'dependencies' => [],
                'capabilities' => [],
                'file' => null,
                'class' => null,
            ],
            'marketing' => [
                'key' => 'marketing',
                'name' => 'Digital Marketing',
                'icon' => '📊',
                'description' => 'Agency clients, AI Visibility, Voice Agent, Audits',
                'version' => '10.1.0',
                'class' => 'DG_Module_Marketing',
                'file' => 'marketing/marketing.php',
                'is_core' => false,
                'required' => false,
                'dependencies' => ['core'],
                'capabilities' => [
                    'dg_marketing_view_clients',
                    'dg_marketing_manage_clients',
                    'dg_marketing_view_audits',
                    'dg_marketing_manage_audits',
                    'dg_marketing_view_ai',
                    'dg_marketing_manage_ai',
                ],
            ],
            'real-estate' => [
                'key' => 'real-estate',
                'name' => 'Real Estate',
                'icon' => '🏠',
                'description' => 'Vendor leads, Appraisals, Listings, Buyers, Sales',
                'version' => '10.0.0',
                'class' => 'DG_Module_RealEstate',
                'file' => 'real-estate/real-estate.php',
                'is_core' => false,
                'required' => false,
                'dependencies' => ['core'],
                'capabilities' => [
                    'dg_re_view_leads',
                    'dg_re_manage_leads',
                    'dg_re_view_appraisals',
                    'dg_re_manage_appraisals',
                    'dg_re_view_listings',
                    'dg_re_manage_listings',
                    'dg_re_view_buyers',
                    'dg_re_manage_buyers',
                    'dg_re_view_sales',
                    'dg_re_manage_sales',
                ],
                'pipelines' => [
                    'vendor_acquisition' => [
                        'label' => 'Vendor Acquisition',
                        'stages' => ['vendor_lead', 'appraisal', 'listing', 'sale', 'settlement', 'past_client'],
                    ],
                    'buyer_acquisition' => [
                        'label' => 'Buyer Acquisition',
                        'stages' => ['inquiry', 'qualified', 'viewing', 'offer', 'purchased'],
                    ],
                ],
            ],
            'accommodation' => [
                'key' => 'accommodation',
                'name' => 'Accommodation',
                'icon' => '🏨',
                'description' => 'Reservations, Properties, Guests, Housekeeping',
                'version' => '10.4.0',
                'class' => 'DG_Module_Accommodation',
                'file' => 'accommodation/accommodation.php',
                'is_core' => false,
                'required' => false,
                'dependencies' => ['core'],
                'capabilities' => [
                    'dg_acc_view_bookings',
                    'dg_acc_manage_bookings',
                    'dg_acc_view_guests',
                    'dg_acc_manage_guests',
                ],
            ],
            'finance' => [
                'key' => 'finance',
                'icon' => '💰',
                'description' => 'Loans, Lenders, Borrowers',
                'version' => '0.0.0',
                'class' => 'DG_Module_Finance',
                'file' => 'finance/finance.php',
                'is_core' => false,
                'required' => false,
                'dependencies' => ['core'],
                'capabilities' => [],
            ],
            'commercial' => [
                'key' => 'commercial',
                'name' => 'Commercial',
                'icon' => '🏢',
                'description' => 'Commercial Listings, Tenants',
                'version' => '0.0.0',
                'class' => 'DG_Module_Commercial',
                'file' => 'commercial/commercial.php',
                'is_core' => false,
                'required' => false,
                'dependencies' => ['core'],
                'capabilities' => [],
            ],
            'dealership' => [
                'key' => 'dealership',
                'name' => 'Dealership',
                'icon' => '🚗',
                'description' => 'Inventory, Test Drives',
                'version' => '0.0.0',
                'class' => 'DG_Module_Dealership',
                'file' => 'dealership/dealership.php',
                'is_core' => false,
                'required' => false,
                'dependencies' => ['core'],
                'capabilities' => [],
            ],
            'services' => [
                'key' => 'services',
                'name' => 'Services',
                'icon' => '🔧',
                'description' => 'Jobs, Invoices',
                'version' => '0.0.0',
                'class' => 'DG_Module_Services',
                'file' => 'services/services.php',
                'is_core' => false,
                'required' => false,
                'dependencies' => ['core'],
                'capabilities' => [],
            ],
        ];

        $this->definitions = apply_filters('dg_platform_module_definitions', $this->definitions);
    }

    public function get_definitions() {
        return $this->definitions;
    }

    public function get_definition($key) {
        return isset($this->definitions[$key]) ? $this->definitions[$key] : null;
    }

    public function register_definition($key, $definition) {
        $defaults = [
            'key' => $key,
            'dependencies' => ['core'],
            'capabilities' => [],
            'pipelines' => [],
            'is_core' => false,
            'required' => false,
        ];
        $this->definitions[$key] = wp_parse_args($definition, $defaults);
    }

    public function register_instance($key, $instance) {
        $this->instances[$key] = $instance;
    }

    public function get_instance($key) {
        return isset($this->instances[$key]) ? $this->instances[$key] : null;
    }

    public function get_instances() {
        return $this->instances;
    }

    public function is_active($key) {
        $active = get_option('dg_platform_active_modules', ['core']);
        return in_array($key, $active, true);
    }

    public function get_active_modules() {
        $active = get_option('dg_platform_active_modules', ['core']);
        if (defined('DG_PLATFORM_SAFE_MODE') && DG_PLATFORM_SAFE_MODE) {
            return ['core'];
        }
        return $active;
    }

    public function load_active_modules($platform) {
        $active = $this->get_active_modules();

        foreach ($active as $module_key) {
            if ($module_key === 'core') {
                continue;
            }

            $definition = $this->get_definition($module_key);
            if (!$definition || empty($definition['file'])) {
                continue;
            }

            if (!$this->dependencies_met($definition)) {
                continue;
            }

            $module_file = DG_MODULES_PATH . $definition['file'];
            if (!file_exists($module_file)) {
                continue;
            }

            require_once $module_file;

            if (!empty($definition['class']) && class_exists($definition['class'])) {
                $instance = $definition['class']::get_instance($platform);
                $this->register_instance($module_key, $instance);

                if (!empty($definition['pipelines'])) {
                    foreach ($definition['pipelines'] as $pipeline_key => $pipeline) {
                        $this->register_pipeline($module_key, $pipeline_key, $pipeline);
                    }
                }
            }
        }

        do_action('dg_platform_modules_loaded', $platform);
    }

    private function dependencies_met($definition) {
        if (empty($definition['dependencies'])) {
            return true;
        }
        $active = $this->get_active_modules();
        foreach ($definition['dependencies'] as $dep) {
            if (!in_array($dep, $active, true)) {
                return false;
            }
        }
        return true;
    }

    public function register_pipeline($module_key, $pipeline_key, $pipeline) {
        $this->pipelines["{$module_key}.{$pipeline_key}"] = $pipeline;
        do_action('dg_platform_register_pipelines', $module_key, $pipeline_key, $pipeline);
    }

    public function get_pipelines() {
        return apply_filters('dg_platform_pipelines', $this->pipelines);
    }

    public function register_activity_type($type, $label) {
        $this->activity_types[$type] = $label;
        do_action('dg_platform_register_activity_types', $type, $label);
    }

    public function get_activity_types() {
        $defaults = [
            'note' => 'Note',
            'email' => 'Email',
            'call' => 'Call',
            'sms' => 'SMS',
            'meeting' => 'Meeting',
            'task' => 'Task',
            'system' => 'System Event',
        ];
        return apply_filters('dg_platform_activity_types', array_merge($defaults, $this->activity_types));
    }
}
