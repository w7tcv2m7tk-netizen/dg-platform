<?php
/**
 * Plugin activation, schema migrations, and data migrations.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Activator {

    public static function activate() {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        self::create_tables();
        self::migrate_legacy_contacts();
        if (class_exists('DG_Site_Profile') && DG_Site_Profile::is_digitalgate()) {
            self::migrate_marketing_schema();
        }

        DG_Permissions::register_capabilities();
        DG_Permissions::install_role_templates();

        $recommended = class_exists('DG_Site_Profile')
            ? DG_Site_Profile::recommended_modules()
            : ['core'];

        // Activate core only first — vertical module loads on next admin request (avoids timeout).
        update_option('dg_platform_active_modules', ['core']);
        if (count($recommended) > 1) {
            update_option('dg_platform_deferred_modules', $recommended);
        }

        update_option(self::site_profile_option(), (class_exists('DG_Site_Profile') ? DG_Site_Profile::hostname() : '') . '|' . implode(',', $recommended));

        if (!file_exists(DG_MODULES_PATH)) {
            wp_mkdir_p(DG_MODULES_PATH);
        }

        update_option('dg_acc_needs_rewrite_flush', 1);
        update_option('dg_re_needs_rewrite_flush', 1);
        update_option('dg_seo_needs_rewrite_flush', 1);
        update_option('dg_ai_visibility_needs_rewrite_flush', 1);
        update_option('dg_platform_db_version', DG_PLATFORM_VERSION);

        if (class_exists('DG_Plan_Registry') && !get_option(DG_Plan_Registry::OPTION_PLAN)) {
            DG_Plan_Registry::set_plan(DG_Plan_Registry::default_for_site());
        }

        if (class_exists('DG_SEO_Redirects')) {
            DG_SEO_Redirects::seed_defaults();
        }
    }

    private static function site_profile_option() {
        return 'dg_platform_site_profile_applied';
    }

    public static function maybe_enable_deferred_modules() {
        $deferred = get_option('dg_platform_deferred_modules');
        if (!$deferred || !is_array($deferred)) {
            return false;
        }
        update_option('dg_platform_active_modules', array_values(array_unique($deferred)));
        delete_option('dg_platform_deferred_modules');
        update_option('dg_platform_show_module_refresh_notice', 1);
        return true;
    }

    /** Run schema updates after zip deploy (activation not required). */
    public static function maybe_upgrade_schema() {
        $saved = get_option('dg_platform_db_version', '');
        if ($saved === DG_PLATFORM_VERSION) {
            return;
        }
        self::create_tables();
        self::ensure_support_ai_columns();
        if (class_exists('DG_Permissions')) {
            DG_Permissions::register_capabilities();
        }
        if (class_exists('DG_SEO_Redirects')) {
            DG_SEO_Redirects::seed_defaults();
        }
        update_option('dg_platform_db_version', DG_PLATFORM_VERSION);
    }

    /** Add Live Support AI columns on existing installs (dbDelta can miss tinyint defaults). */
    public static function ensure_support_ai_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'dg_support_conversations';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $col = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'ai_paused'");
        if (empty($col)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN ai_paused tinyint(1) NOT NULL DEFAULT 0 AFTER status");
        }
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            $wpdb->prefix . 'dg_organisations' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(200) NOT NULL,
                email varchar(100) DEFAULT NULL,
                phone varchar(20) DEFAULT NULL,
                website varchar(255) DEFAULT NULL,
                industry varchar(100) DEFAULT NULL,
                suburb varchar(100) DEFAULT NULL,
                state varchar(50) DEFAULT NULL,
                status varchar(20) DEFAULT 'active',
                source varchar(50) DEFAULT 'website',
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY email (email),
                KEY status (status)
            ",
            $wpdb->prefix . 'dg_contacts' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                organisation_id bigint(20) DEFAULT NULL,
                first_name varchar(50) NOT NULL,
                last_name varchar(50) DEFAULT NULL,
                email varchar(100) NOT NULL,
                phone varchar(20) DEFAULT NULL,
                position varchar(100) DEFAULT NULL,
                is_primary tinyint(1) DEFAULT 0,
                status varchar(20) DEFAULT 'active',
                source varchar(50) DEFAULT 'website',
                owner_id bigint(20) DEFAULT NULL,
                tags varchar(500) DEFAULT NULL,
                notes text,
                legacy_table varchar(50) DEFAULT NULL,
                legacy_id bigint(20) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY email (email),
                KEY organisation_id (organisation_id),
                KEY status (status),
                KEY owner_id (owner_id),
                KEY legacy (legacy_table, legacy_id)
            ",
            $wpdb->prefix . 'dg_entity_meta' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                entity_type varchar(50) NOT NULL,
                entity_id bigint(20) NOT NULL,
                meta_key varchar(191) NOT NULL,
                meta_value longtext,
                PRIMARY KEY (id),
                UNIQUE KEY entity_meta (entity_type, entity_id, meta_key),
                KEY entity (entity_type, entity_id)
            ",
            $wpdb->prefix . 'dg_activities' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                entity_type varchar(50) DEFAULT NULL,
                entity_id bigint(20) DEFAULT NULL,
                contact_id bigint(20) DEFAULT NULL,
                user_id bigint(20) DEFAULT NULL,
                activity_type varchar(50) NOT NULL,
                subject varchar(255) DEFAULT NULL,
                content longtext,
                metadata longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY entity (entity_type, entity_id),
                KEY contact_id (contact_id),
                KEY activity_type (activity_type),
                KEY created_at (created_at)
            ",
            $wpdb->prefix . 'dg_tasks' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                description text,
                assigned_to bigint(20) DEFAULT NULL,
                created_by bigint(20) DEFAULT NULL,
                entity_type varchar(50) DEFAULT NULL,
                entity_id bigint(20) DEFAULT NULL,
                contact_id bigint(20) DEFAULT NULL,
                priority varchar(20) DEFAULT 'normal',
                status varchar(20) DEFAULT 'pending',
                due_date datetime DEFAULT NULL,
                recurrence varchar(50) DEFAULT NULL,
                completed_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY assigned_to (assigned_to),
                KEY status (status),
                KEY due_date (due_date),
                KEY entity (entity_type, entity_id)
            ",
            $wpdb->prefix . 'dg_calendar_events' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                description text,
                event_type varchar(50) DEFAULT 'meeting',
                start_at datetime NOT NULL,
                end_at datetime DEFAULT NULL,
                all_day tinyint(1) DEFAULT 0,
                assigned_to bigint(20) DEFAULT NULL,
                contact_id bigint(20) DEFAULT NULL,
                entity_type varchar(50) DEFAULT NULL,
                entity_id bigint(20) DEFAULT NULL,
                status varchar(20) DEFAULT 'scheduled',
                location varchar(255) DEFAULT NULL,
                metadata longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY start_at (start_at),
                KEY assigned_to (assigned_to),
                KEY event_type (event_type),
                KEY entity (entity_type, entity_id)
            ",
            $wpdb->prefix . 'dg_documents' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                attachment_id bigint(20) DEFAULT NULL,
                entity_type varchar(50) DEFAULT NULL,
                entity_id bigint(20) DEFAULT NULL,
                title varchar(255) NOT NULL,
                file_path varchar(500) DEFAULT NULL,
                mime_type varchar(100) DEFAULT NULL,
                uploaded_by bigint(20) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY entity (entity_type, entity_id),
                KEY attachment_id (attachment_id)
            ",
            $wpdb->prefix . 'dg_reviews' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                platform varchar(50) NOT NULL DEFAULT 'manual',
                author_name varchar(100) DEFAULT NULL,
                author_photo varchar(500) DEFAULT NULL,
                rating decimal(2,1) DEFAULT 0,
                title varchar(255) DEFAULT NULL,
                content longtext,
                review_date date DEFAULT NULL,
                source_url varchar(500) DEFAULT NULL,
                external_id varchar(100) DEFAULT NULL,
                listing_id varchar(100) DEFAULT NULL,
                status varchar(20) DEFAULT 'published',
                imported_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY platform (platform),
                KEY status (status),
                KEY review_date (review_date),
                KEY external_id (external_id),
                KEY listing_id (listing_id)
            ",
            $wpdb->prefix . 'dg_automations' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                module varchar(50) DEFAULT 'core',
                trigger_type varchar(50) NOT NULL,
                trigger_settings longtext,
                steps longtext,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY module (module),
                KEY is_active (is_active)
            ",
            $wpdb->prefix . 'dg_automation_logs' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                automation_id bigint(20) NOT NULL,
                entity_type varchar(50) DEFAULT NULL,
                entity_id bigint(20) DEFAULT NULL,
                contact_id bigint(20) DEFAULT NULL,
                step_index int(11) NOT NULL,
                status varchar(20) DEFAULT 'pending',
                error_message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                processed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY automation_id (automation_id),
                KEY status (status)
            ",
            $wpdb->prefix . 'dg_audit_log' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) DEFAULT NULL,
                action varchar(100) NOT NULL,
                entity_type varchar(50) DEFAULT NULL,
                entity_id bigint(20) DEFAULT NULL,
                old_value longtext,
                new_value longtext,
                ip_address varchar(45) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY action (action),
                KEY entity (entity_type, entity_id),
                KEY created_at (created_at)
            ",
            $wpdb->prefix . 'dg_support_conversations' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                contact_id bigint(20) DEFAULT NULL,
                status varchar(20) DEFAULT 'open',
                ai_paused tinyint(1) NOT NULL DEFAULT 0,
                last_message_at datetime DEFAULT CURRENT_TIMESTAMP,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id),
                KEY contact_id (contact_id),
                KEY last_message_at (last_message_at)
            ",
            $wpdb->prefix . 'dg_support_messages' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                conversation_id bigint(20) NOT NULL,
                sender_role varchar(20) NOT NULL,
                sender_user_id bigint(20) DEFAULT NULL,
                body text NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY conversation_id (conversation_id),
                KEY created_at (created_at)
            ",
            // Legacy tables kept for backward compatibility
            $wpdb->prefix . 'dg_platform_companies' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                company_name varchar(200) NOT NULL,
                email varchar(100) DEFAULT NULL,
                phone varchar(20) DEFAULT NULL,
                website varchar(255) DEFAULT NULL,
                industry varchar(100) DEFAULT NULL,
                suburb varchar(100) DEFAULT NULL,
                state varchar(50) DEFAULT NULL,
                status varchar(20) DEFAULT 'active',
                source varchar(50) DEFAULT 'website',
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY email (email)
            ",
            $wpdb->prefix . 'dg_platform_contacts' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                company_id bigint(20) DEFAULT NULL,
                first_name varchar(50) NOT NULL,
                last_name varchar(50) DEFAULT NULL,
                email varchar(100) NOT NULL,
                phone varchar(20) DEFAULT NULL,
                position varchar(100) DEFAULT NULL,
                is_primary tinyint(1) DEFAULT 0,
                status varchar(20) DEFAULT 'active',
                source varchar(50) DEFAULT 'website',
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY email (email)
            ",
            $wpdb->prefix . 'dg_platform_notes' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                company_id bigint(20) DEFAULT NULL,
                content text NOT NULL,
                type varchar(50) DEFAULT 'note',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ",
            $wpdb->prefix . 'dg_platform_company_meta' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                company_id bigint(20) NOT NULL,
                meta_key varchar(191) NOT NULL,
                meta_value longtext,
                PRIMARY KEY (id),
                UNIQUE KEY company_meta (company_id, meta_key)
            ",
            $wpdb->prefix . 'dg_platform_voice_logs' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                company_id bigint(20) DEFAULT NULL,
                contact_id bigint(20) DEFAULT NULL,
                call_summary text,
                call_transcript longtext,
                lead_score int(11) DEFAULT 0,
                is_qualified tinyint(1) DEFAULT 0,
                lead_quality varchar(20) DEFAULT 'warm',
                call_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ",
            $wpdb->prefix . 'dg_platform_audits' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                company_id bigint(20) NOT NULL,
                audit_date datetime NOT NULL,
                pdf_path varchar(255) DEFAULT NULL,
                ai_score int(11) DEFAULT 0,
                google_score int(11) DEFAULT 0,
                website_score int(11) DEFAULT 0,
                vendor_lead_score int(11) DEFAULT 0,
                overall_score int(11) DEFAULT 0,
                grade varchar(5) DEFAULT NULL,
                recommendations longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ",
            // Real Estate pipeline tables
            $wpdb->prefix . 'dg_re_pipeline_records' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                record_type varchar(50) NOT NULL,
                stage varchar(50) NOT NULL,
                contact_id bigint(20) DEFAULT NULL,
                property_id bigint(20) DEFAULT NULL,
                owner_id bigint(20) DEFAULT NULL,
                title varchar(255) DEFAULT NULL,
                status varchar(50) DEFAULT 'active',
                metadata longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY record_type (record_type),
                KEY stage (stage),
                KEY contact_id (contact_id),
                KEY property_id (property_id),
                KEY owner_id (owner_id)
            ",
            $wpdb->prefix . 'dg_re_leads' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                pipeline_id bigint(20) NOT NULL,
                contact_id bigint(20) NOT NULL,
                source varchar(50) DEFAULT 'website',
                status varchar(50) DEFAULT 'new',
                property_address varchar(255) DEFAULT NULL,
                motivation text,
                notes text,
                assigned_to bigint(20) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY pipeline_id (pipeline_id),
                KEY contact_id (contact_id),
                KEY status (status),
                KEY source (source)
            ",
            $wpdb->prefix . 'dg_re_appraisals' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                pipeline_id bigint(20) NOT NULL,
                contact_id bigint(20) NOT NULL,
                property_id bigint(20) DEFAULT NULL,
                status varchar(50) DEFAULT 'booked',
                cma_value decimal(15,2) DEFAULT NULL,
                vendor_notes text,
                motivation text,
                competitor_info text,
                presentation_notes text,
                calendar_event_id bigint(20) DEFAULT NULL,
                assigned_to bigint(20) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY pipeline_id (pipeline_id),
                KEY contact_id (contact_id),
                KEY status (status)
            ",
            $wpdb->prefix . 'dg_re_buyers' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                pipeline_id bigint(20) DEFAULT NULL,
                contact_id bigint(20) NOT NULL,
                requirements text,
                budget_min decimal(15,2) DEFAULT NULL,
                budget_max decimal(15,2) DEFAULT NULL,
                suburbs varchar(500) DEFAULT NULL,
                property_types varchar(255) DEFAULT NULL,
                status varchar(50) DEFAULT 'active',
                assigned_to bigint(20) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY pipeline_id (pipeline_id),
                KEY contact_id (contact_id),
                KEY status (status)
            ",
            $wpdb->prefix . 'dg_re_offers' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                pipeline_id bigint(20) NOT NULL,
                property_id bigint(20) NOT NULL,
                buyer_id bigint(20) NOT NULL,
                offer_amount decimal(15,2) NOT NULL,
                status varchar(50) DEFAULT 'pending',
                conditions text,
                expiry_date date DEFAULT NULL,
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY pipeline_id (pipeline_id),
                KEY property_id (property_id),
                KEY buyer_id (buyer_id),
                KEY status (status)
            ",
            $wpdb->prefix . 'dg_re_contracts' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                pipeline_id bigint(20) NOT NULL,
                property_id bigint(20) NOT NULL,
                offer_id bigint(20) DEFAULT NULL,
                contract_date date DEFAULT NULL,
                settlement_date date DEFAULT NULL,
                sale_price decimal(15,2) DEFAULT NULL,
                commission decimal(15,2) DEFAULT NULL,
                status varchar(50) DEFAULT 'pending',
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY pipeline_id (pipeline_id),
                KEY property_id (property_id),
                KEY status (status)
            ",
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $table_name => $table_sql) {
            dbDelta("CREATE TABLE $table_name ($table_sql) $charset_collate;");
        }
    }

    /**
     * Migrate legacy contact/company tables into unified core tables.
     */
    public static function migrate_legacy_contacts() {
        global $wpdb;

        if (get_option('dg_contacts_migrated')) {
            return;
        }

        $orgs_table = $wpdb->prefix . 'dg_organisations';
        $contacts_table = $wpdb->prefix . 'dg_contacts';
        $legacy_companies = $wpdb->prefix . 'dg_platform_companies';
        $legacy_contacts = $wpdb->prefix . 'dg_platform_contacts';
        $roe_contacts = $wpdb->prefix . 'roe_crm_contacts';

        if ($wpdb->get_var("SHOW TABLES LIKE '$legacy_companies'") === $legacy_companies) {
            $companies = $wpdb->get_results("SELECT * FROM $legacy_companies");
            foreach ($companies as $company) {
                $existing = $company->email ? $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $orgs_table WHERE email = %s LIMIT 1",
                    $company->email
                )) : null;
                if ($existing) {
                    continue;
                }
                $wpdb->insert($orgs_table, [
                    'name' => $company->company_name,
                    'email' => $company->email,
                    'phone' => $company->phone,
                    'website' => $company->website,
                    'industry' => $company->industry,
                    'suburb' => $company->suburb,
                    'state' => $company->state,
                    'status' => $company->status,
                    'source' => $company->source,
                    'notes' => $company->notes,
                    'created_at' => $company->created_at,
                    'updated_at' => $company->updated_at,
                ]);
            }
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$legacy_contacts'") === $legacy_contacts) {
            $contacts = $wpdb->get_results("SELECT * FROM $legacy_contacts");
            foreach ($contacts as $contact) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $contacts_table WHERE email = %s AND legacy_table = 'dg_platform_contacts' AND legacy_id = %d",
                    $contact->email,
                    $contact->id
                ));
                if ($existing) {
                    continue;
                }
                $org_id = null;
                if ($contact->company_id) {
                    $legacy_company = $wpdb->get_row($wpdb->prepare(
                        "SELECT email FROM $legacy_companies WHERE id = %d",
                        $contact->company_id
                    ));
                    if ($legacy_company && $legacy_company->email) {
                        $org_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM $orgs_table WHERE email = %s LIMIT 1",
                            $legacy_company->email
                        ));
                    }
                }
                $wpdb->insert($contacts_table, [
                    'organisation_id' => $org_id,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'position' => $contact->position,
                    'is_primary' => $contact->is_primary,
                    'status' => $contact->status,
                    'source' => $contact->source,
                    'notes' => $contact->notes,
                    'legacy_table' => 'dg_platform_contacts',
                    'legacy_id' => $contact->id,
                    'created_at' => $contact->created_at,
                    'updated_at' => $contact->updated_at,
                ]);
            }
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$roe_contacts'") === $roe_contacts) {
            $contacts = $wpdb->get_results("SELECT * FROM $roe_contacts");
            foreach ($contacts as $contact) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $contacts_table WHERE legacy_table = 'roe_crm_contacts' AND legacy_id = %d",
                    $contact->id
                ));
                if ($existing) {
                    continue;
                }
                $wpdb->insert($contacts_table, [
                    'first_name' => $contact->first_name ?: 'Unknown',
                    'last_name' => $contact->last_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'status' => $contact->status,
                    'source' => $contact->source,
                    'owner_id' => $contact->agent_id,
                    'legacy_table' => 'roe_crm_contacts',
                    'legacy_id' => $contact->id,
                    'created_at' => $contact->created_at,
                    'updated_at' => $contact->updated_at,
                ]);
                $new_id = $wpdb->insert_id;
                if ($contact->property_id) {
                    DG_Contacts::set_meta($new_id, 'property_id', $contact->property_id);
                }
            }
        }

        update_option('dg_contacts_migrated', true);
    }

    public static function migrate_marketing_schema() {
        global $wpdb;
        $orgs = $wpdb->prefix . 'dg_organisations';

        if ($wpdb->get_var("SHOW TABLES LIKE '$orgs'") === $orgs) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM $orgs", 0);
            if (!in_array('legacy_table', $cols, true)) {
                $wpdb->query("ALTER TABLE $orgs ADD legacy_table varchar(50) DEFAULT NULL");
            }
            if (!in_array('legacy_id', $cols, true)) {
                $wpdb->query("ALTER TABLE $orgs ADD legacy_id bigint(20) DEFAULT NULL");
            }
            if ($wpdb->get_var("SHOW INDEX FROM $orgs WHERE Key_name = 'legacy'") === null) {
                $wpdb->query("ALTER TABLE $orgs ADD KEY legacy (legacy_table, legacy_id)");
            }
        }

        $clients_file = DG_PLATFORM_PATH . 'modules/marketing/includes/class-marketing-clients.php';
        if (file_exists($clients_file)) {
            require_once $clients_file;
        }

        if (class_exists('DG_Marketing_Clients')) {
            $companies = DG_Marketing_Clients::companies_table();
            if ($wpdb->get_var("SHOW TABLES LIKE '$companies'") === $companies) {
                $rows = $wpdb->get_results("SELECT id FROM $companies");
                foreach ($rows as $row) {
                    DG_Marketing_Clients::sync_company((int) $row->id);
                }
            }
        }

        $ai_file = DG_PLATFORM_PATH . 'modules/marketing/includes/class-marketing-ai-visibility.php';
        if (file_exists($ai_file)) {
            require_once $ai_file;
            if (class_exists('DG_Marketing_AI_Visibility')) {
                DG_Marketing_AI_Visibility::ensure_table();
            }
        }
    }
}
