<?php
/**
 * Property documents, contracts/e-sign, and settlement checklist (Roe Realty).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_RE_Property_Workspace {

    const CONTRACTS_TABLE = 'dg_re_contracts';
    const SIGN_QUERY = 'dg_contract_sign';

    public static function init() {
        add_action('init', [__CLASS__, 'register_rewrite']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'maybe_render_sign_page']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('admin_post_dg_re_attach_property_file', [__CLASS__, 'handle_attach_file']);
        add_action('admin_post_dg_re_delete_property_file', [__CLASS__, 'handle_delete_file']);
        add_action('admin_post_dg_re_create_contract', [__CLASS__, 'handle_create_contract']);
        add_action('admin_post_dg_re_send_contract', [__CLASS__, 'handle_send_contract']);
        add_action('admin_post_dg_re_save_settlement', [__CLASS__, 'handle_save_settlement']);
        add_action('admin_post_dg_re_sign_contract', [__CLASS__, 'handle_public_sign']);
        add_action('admin_post_nopriv_dg_re_sign_contract', [__CLASS__, 'handle_public_sign']);
        add_action('dg_platform_register_menus', [__CLASS__, 'register_menu'], 16);
    }

    public static function register_menu() {
        if (!class_exists('DG_RE_Permissions') || !DG_RE_Permissions::can_view_listings()) {
            return;
        }
        add_submenu_page(
            'dg-platform',
            'Property Files',
            '📁 Property Files',
            DG_RE_Permissions::menu_cap_listings(),
            'dg-re-property-files',
            [__CLASS__, 'render_files_index']
        );
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . self::CONTRACTS_TABLE;
    }

    public static function ensure_tables() {
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            property_id bigint(20) NOT NULL,
            template_key varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            content_html longtext NOT NULL,
            status varchar(20) DEFAULT 'draft',
            signer_name varchar(100) DEFAULT NULL,
            signer_email varchar(100) DEFAULT NULL,
            sign_token varchar(64) DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            viewed_at datetime DEFAULT NULL,
            signed_at datetime DEFAULT NULL,
            signed_ip varchar(45) DEFAULT NULL,
            signed_snapshot longtext,
            attachment_id bigint(20) DEFAULT NULL,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY property_id (property_id),
            KEY sign_token (sign_token),
            KEY status (status)
        ) {$wpdb->get_charset_collate()};");
    }

    public static function register_rewrite() {
        add_rewrite_rule('^sign-contract/([^/]+)/?', 'index.php?' . self::SIGN_QUERY . '=$matches[1]', 'top');
        if (get_option('dg_re_needs_contract_rewrite_flush')) {
            flush_rewrite_rules(false);
            delete_option('dg_re_needs_contract_rewrite_flush');
        }
    }

    /** @param array<int,string> $vars */
    public static function query_vars($vars) {
        $vars[] = self::SIGN_QUERY;
        return $vars;
    }

    /** @return array<string,array<string,string>> */
    public static function contract_templates() {
        return apply_filters('dg_re_contract_templates', [
            'listing_authority' => [
                'label' => 'Exclusive Listing Authority',
                'subject' => 'Listing authority for {address}',
            ],
            'agency_agreement' => [
                'label' => 'Agency Agreement',
                'subject' => 'Agency agreement — {address}',
            ],
            'vendor_disclosure' => [
                'label' => 'Vendor Disclosure Statement',
                'subject' => 'Vendor disclosure — {address}',
            ],
        ]);
    }

    /** @return array<int,array<string,string>> */
    public static function settlement_items() {
        return apply_filters('dg_re_settlement_checklist', [
            ['key' => 'contract_signed', 'label' => 'Contract signed by all parties'],
            ['key' => 'deposit_received', 'label' => 'Deposit received'],
            ['key' => 'building_pest', 'label' => 'Building & pest inspection complete'],
            ['key' => 'finance_approved', 'label' => 'Finance approved (if applicable)'],
            ['key' => 'settlement_booked', 'label' => 'Settlement date booked with solicitor/conveyancer'],
            ['key' => 'final_inspection', 'label' => 'Final inspection completed'],
            ['key' => 'keys_handover', 'label' => 'Keys handover complete'],
        ]);
    }

    public static function register_meta_boxes() {
        add_meta_box(
            'roe_property_files',
            '📁 Property Files & Contracts',
            [__CLASS__, 'meta_box'],
            'property',
            'normal',
            'default'
        );
    }

    /** @param WP_Post $post */
    public static function meta_box($post) {
        self::ensure_tables();
        wp_nonce_field('roe_property_workspace', 'roe_property_workspace_nonce');
        $property_id = (int) $post->ID;
        $docs = class_exists('DG_Documents') ? DG_Documents::get_for_entity('property', $property_id) : [];
        $contracts = self::get_for_property($property_id);
        $settlement = self::get_settlement($property_id);
        $templates = self::contract_templates();
        include __DIR__ . '/../templates/property-workspace-meta.php';
    }

    public static function render_files_index() {
        if (!DG_RE_Permissions::can_view_listings() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $properties = get_posts([
            'post_type' => 'property',
            'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => 100,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>📁 Property Files & Contracts</h1>
            <p style="color:#64748B;">Upload contracts, agreements, and settlement documents per listing. Open a property to manage files, e-sign requests, and settlement checklist.</p>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>Property</th><th>Status</th><th>Files</th><th>Contracts</th><th></th></tr></thead>
                <tbody>
                <?php if (!$properties) : ?>
                    <tr><td colspan="5">No properties yet. <a href="<?php echo esc_url(admin_url('post-new.php?post_type=property')); ?>">Add a property</a></td></tr>
                <?php else : foreach ($properties as $p) :
                    $doc_count = class_exists('DG_Documents') ? count(DG_Documents::get_for_entity('property', $p->ID)) : 0;
                    $contract_count = count(self::get_for_property($p->ID));
                    $status = get_post_meta($p->ID, 'roe_property_status', true);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($p->post_title); ?></strong><br><span style="color:#64748B;font-size:12px;"><?php echo esc_html(get_post_meta($p->ID, 'roe_property_address', true)); ?></span></td>
                        <td><?php echo esc_html($status ?: '—'); ?></td>
                        <td><?php echo (int) $doc_count; ?></td>
                        <td><?php echo (int) $contract_count; ?></td>
                        <td><a class="button button-small" href="<?php echo esc_url(get_edit_post_link($p->ID, 'url')); ?>#roe_property_files">Manage</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_attach_file() {
        if (!DG_RE_Permissions::can_manage_listings() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('roe_property_workspace');
        $property_id = (int) ($_POST['property_id'] ?? 0);
        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        if ($property_id && $attachment_id && class_exists('DG_Documents')) {
            DG_Documents::attach($attachment_id, 'property', $property_id, sanitize_text_field($_POST['file_title'] ?? ''));
        }
        wp_safe_redirect(get_edit_post_link($property_id, 'url') . '#roe_property_files&uploaded=1');
        exit;
    }

    public static function handle_delete_file() {
        if (!DG_RE_Permissions::can_manage_listings() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('dg_re_delete_property_file');
        $doc_id = (int) ($_GET['doc_id'] ?? 0);
        $property_id = (int) ($_GET['property_id'] ?? 0);
        if ($doc_id && class_exists('DG_Documents')) {
            DG_Documents::delete($doc_id);
        }
        wp_safe_redirect(get_edit_post_link($property_id, 'url') . '#roe_property_files');
        exit;
    }

    public static function handle_create_contract() {
        if (!DG_RE_Permissions::can_manage_listings() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('roe_property_workspace');
        $property_id = (int) ($_POST['property_id'] ?? 0);
        $template_key = sanitize_key($_POST['contract_template'] ?? '');
        $templates = self::contract_templates();
        if (!$property_id || !isset($templates[$template_key])) {
            wp_die('Invalid contract template.');
        }
        self::ensure_tables();
        $html = self::render_template_html($template_key, $property_id);
        global $wpdb;
        $wpdb->insert(self::table(), [
            'property_id' => $property_id,
            'template_key' => $template_key,
            'title' => $templates[$template_key]['label'],
            'content_html' => $html,
            'status' => 'draft',
            'sign_token' => self::generate_token(),
            'created_by' => get_current_user_id(),
        ]);
        update_option('dg_re_needs_contract_rewrite_flush', 1);
        wp_safe_redirect(get_edit_post_link($property_id, 'url') . '#roe_property_files&contract=1');
        exit;
    }

    public static function handle_send_contract() {
        if (!DG_RE_Permissions::can_manage_listings() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('roe_property_workspace');
        $contract_id = (int) ($_POST['contract_id'] ?? 0);
        $signer_name = sanitize_text_field($_POST['signer_name'] ?? '');
        $signer_email = sanitize_email($_POST['signer_email'] ?? '');
        $contract = self::get($contract_id);
        if (!$contract || !$signer_email) {
            wp_die('Invalid contract or email.');
        }
        global $wpdb;
        $token = $contract->sign_token ?: self::generate_token();
        $wpdb->update(self::table(), [
            'signer_name' => $signer_name,
            'signer_email' => $signer_email,
            'sign_token' => $token,
            'status' => 'sent',
            'sent_at' => current_time('mysql'),
        ], ['id' => $contract_id]);
        $sign_url = home_url('/sign-contract/' . $token . '/');
        $subject = 'Please sign: ' . $contract->title;
        $first = DG_Email_Names::first_name($signer_name ?: 'there');
        $property = self::property_label($contract->property_id);

        if (class_exists('DG_Email_Brand')) {
            $inner = '<p style="margin:0 0 14px;line-height:1.6;">Hi ' . esc_html($first) . ',</p>'
                . '<p style="margin:0 0 14px;line-height:1.6;">Please review and sign the document for <strong>'
                . esc_html($property) . '</strong>.</p>'
                . DG_Email_Brand::cta($sign_url, 'Review &amp; sign', 'roe')
                . '<p style="margin:16px 0 0;line-height:1.6;color:#8A9A98;">— Roe Realty</p>';
            $body = DG_Email_Brand::wrap($inner, [
                'theme' => 'roe',
                'footer_note' => 'Roe Realty — secure e-signature request',
            ]);
            wp_mail($signer_email, $subject, $body, DG_Email_Brand::mail_headers(true));
        } else {
            $body = "Hi " . $first . ",\n\nPlease review and sign the document for "
                . $property . ":\n\n" . $sign_url . "\n\n— Roe Realty";
            wp_mail($signer_email, $subject, $body);
        }
        wp_safe_redirect(get_edit_post_link($contract->property_id, 'url') . '#roe_property_files&sent=1');
        exit;
    }

    public static function handle_save_settlement() {
        if (!DG_RE_Permissions::can_manage_listings() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('roe_property_workspace');
        $property_id = (int) ($_POST['property_id'] ?? 0);
        if (!$property_id) {
            wp_die('Invalid property.');
        }
        $checked = isset($_POST['settlement']) && is_array($_POST['settlement']) ? array_map('sanitize_key', $_POST['settlement']) : [];
        $dates = [
            'listing_date' => sanitize_text_field($_POST['listing_date'] ?? ''),
            'under_contract_date' => sanitize_text_field($_POST['under_contract_date'] ?? ''),
            'cooling_off_date' => sanitize_text_field($_POST['cooling_off_date'] ?? ''),
            'settlement_date' => sanitize_text_field($_POST['settlement_date'] ?? ''),
        ];
        update_post_meta($property_id, 'roe_property_settlement_checklist', $checked);
        foreach ($dates as $key => $val) {
            update_post_meta($property_id, 'roe_property_' . $key, $val);
        }
        wp_safe_redirect(get_edit_post_link($property_id, 'url') . '#roe_property_files&settlement=1');
        exit;
    }

    public static function handle_public_sign() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $token = sanitize_text_field($_POST['sign_token'] ?? '');
        $contract = self::get_by_token($token);
        if (!$contract || $contract->status === 'signed') {
            wp_die('This signing link is invalid or already completed.');
        }
        $name = sanitize_text_field($_POST['signer_name'] ?? '');
        $agree = !empty($_POST['agree']);
        if ($name === '' || !$agree) {
            wp_die('Name and agreement are required.');
        }
        global $wpdb;
        $snapshot = self::signed_snapshot_html($contract, $name);
        $wpdb->update(self::table(), [
            'status' => 'signed',
            'signer_name' => $name,
            'signed_at' => current_time('mysql'),
            'signed_ip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'signed_snapshot' => $snapshot,
            'viewed_at' => $contract->viewed_at ?: current_time('mysql'),
        ], ['id' => $contract->id]);
        do_action('dg_re_contract_signed', $contract->id, $contract->property_id);
        wp_safe_redirect(home_url('/sign-contract/' . $token . '/?signed=1'));
        exit;
    }

    public static function maybe_render_sign_page() {
        $token = get_query_var(self::SIGN_QUERY);
        if (!$token) {
            return;
        }
        self::ensure_tables();
        $contract = self::get_by_token(sanitize_text_field($token));
        if (!$contract) {
            status_header(404);
            echo '<!DOCTYPE html><html><body><h1>Signing link not found</h1></body></html>';
            exit;
        }
        if ($contract->status !== 'signed' && !$contract->viewed_at) {
            global $wpdb;
            $wpdb->update(self::table(), [
                'viewed_at' => current_time('mysql'),
                'status' => $contract->status === 'sent' ? 'viewed' : $contract->status,
            ], ['id' => $contract->id]);
        }
        $signed = isset($_GET['signed']);
        include __DIR__ . '/../templates/contract-sign-page.php';
        exit;
    }

    /** @return array<string,mixed> */
    public static function get_settlement($property_id) {
        return [
            'checked' => get_post_meta($property_id, 'roe_property_settlement_checklist', true) ?: [],
            'listing_date' => get_post_meta($property_id, 'roe_property_listing_date', true),
            'under_contract_date' => get_post_meta($property_id, 'roe_property_under_contract_date', true),
            'cooling_off_date' => get_post_meta($property_id, 'roe_property_cooling_off_date', true),
            'settlement_date' => get_post_meta($property_id, 'roe_property_settlement_date', true),
        ];
    }

    /** @return array<int,object> */
    public static function get_for_property($property_id) {
        self::ensure_tables();
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE property_id = %d ORDER BY created_at DESC',
            (int) $property_id
        ));
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id));
    }

    public static function get_by_token($token) {
        if ($token === '') {
            return null;
        }
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE sign_token = %s LIMIT 1',
            $token
        ));
    }

    private static function generate_token() {
        return bin2hex(random_bytes(16));
    }

    public static function property_label($property_id) {
        $address = get_post_meta($property_id, 'roe_property_address', true);
        $suburb = get_post_meta($property_id, 'roe_property_suburb', true);
        $title = get_the_title($property_id);
        return trim($address . ($suburb ? ', ' . $suburb : '')) ?: $title;
    }

    private static function render_template_html($template_key, $property_id) {
        $address = self::property_label($property_id);
        $price = get_post_meta($property_id, 'roe_property_price', true);
        $agent_id = get_post_meta($property_id, 'roe_property_agent_id', true);
        $agent_name = $agent_id ? get_the_title($agent_id) : get_bloginfo('name');
        $today = date_i18n(get_option('date_format'));

        $body = [
            'listing_authority' => '<h2>Exclusive Listing Authority</h2><p>Property: <strong>{address}</strong></p><p>Listing price guide: {price}</p><p>Listing agent: {agent}</p><p>The vendor authorises Roe Realty to market and sell the above property on exclusive terms.</p><p>Date: {today}</p><p>Vendor signature: _________________________</p>',
            'agency_agreement' => '<h2>Agency Agreement</h2><p>This agreement is between the vendor of <strong>{address}</strong> and Roe Realty.</p><p>Commission and terms as discussed with {agent}.</p><p>Date: {today}</p>',
            'vendor_disclosure' => '<h2>Vendor Disclosure Statement</h2><p>Property: <strong>{address}</strong></p><p>The vendor confirms material facts about the property have been disclosed to the best of their knowledge.</p><p>Date: {today}</p>',
        ];

        $html = $body[$template_key] ?? '<p>Contract for {address}</p>';
        $replacements = [
            '{address}' => esc_html($address),
            '{price}' => esc_html($price ? '$' . number_format((float) preg_replace('/[^0-9.]/', '', (string) $price)) : 'TBC'),
            '{agent}' => esc_html($agent_name),
            '{today}' => esc_html($today),
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    private static function signed_snapshot_html($contract, $signer_name) {
        return '<div class="dg-signed-contract">'
            . '<p><strong>Signed electronically</strong> by ' . esc_html($signer_name)
            . ' on ' . esc_html(current_time('mysql')) . '</p>'
            . $contract->content_html
            . '</div>';
    }
}
