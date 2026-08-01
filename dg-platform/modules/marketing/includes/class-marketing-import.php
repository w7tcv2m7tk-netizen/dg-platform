<?php
/**
 * CSV contact import for DigitalGate agency clients.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Import {

    public static function expected_headers() {
        return ['First Name', 'Last Name', 'Mobile Phone (Personal)', 'Mobile Phone (Business)', 'Email'];
    }

    public static function import_csv_path($file_path) {
        if (!is_readable($file_path)) {
            return new WP_Error('unreadable', 'Cannot read uploaded file.');
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('open_failed', 'Could not open CSV file.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new WP_Error('empty', 'CSV file is empty.');
        }

        $map = self::map_headers($header);
        if (empty($map['email'])) {
            fclose($handle);
            return new WP_Error('missing_email', 'CSV must include an Email column.');
        }

        $stats = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $row_num = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;
            if (self::row_is_empty($row)) {
                continue;
            }

            $result = self::import_row($row, $map);
            if (is_wp_error($result)) {
                $stats['errors'][] = 'Row ' . $row_num . ': ' . $result->get_error_message();
                $stats['skipped']++;
                continue;
            }
            if ($result === 'updated') {
                $stats['updated']++;
            } else {
                $stats['imported']++;
            }
        }

        fclose($handle);
        return $stats;
    }

    private static function map_headers($header) {
        $normalized = [];
        foreach ($header as $i => $label) {
            $key = strtolower(trim($label));
            $normalized[$key] = $i;
        }

        $find = function ($candidates) use ($normalized) {
            foreach ($candidates as $candidate) {
                if (isset($normalized[$candidate])) {
                    return $normalized[$candidate];
                }
            }
            return null;
        };

        return [
            'first_name' => $find(['first name', 'firstname', 'first']),
            'last_name' => $find(['last name', 'lastname', 'last']),
            'phone_personal' => $find(['mobile phone (personal)', 'phone personal', 'mobile personal']),
            'phone_business' => $find(['mobile phone (business)', 'phone business', 'mobile business', 'phone']),
            'email' => $find(['email', 'email address']),
        ];
    }

    private static function row_is_empty($row) {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    private static function val($row, $index) {
        if ($index === null || !isset($row[$index])) {
            return '';
        }
        return trim((string) $row[$index]);
    }

    private static function import_row($row, $map) {
        global $wpdb;

        $email = sanitize_email(self::val($row, $map['email']));
        if ($email === '') {
            return new WP_Error('no_email', 'Missing email');
        }

        $first = sanitize_text_field(self::val($row, $map['first_name']));
        $last = sanitize_text_field(self::val($row, $map['last_name']));
        $phone = sanitize_text_field(self::val($row, $map['phone_business']));
        if ($phone === '') {
            $phone = sanitize_text_field(self::val($row, $map['phone_personal']));
        }

        $company_name = trim($first . ' ' . $last);
        if ($company_name === '') {
            $company_name = $email;
        }

        $table = DG_Marketing_Clients::companies_table();
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));
        $is_update = (bool) $existing;

        if ($existing) {
            $company_id = (int) $existing->id;
            $wpdb->update($table, [
                'company_name' => $company_name,
                'phone' => $phone,
                'source' => 'csv_import',
                'status' => 'lead',
            ], ['id' => $company_id]);
        } else {
            $wpdb->insert($table, [
                'company_name' => $company_name,
                'email' => $email,
                'phone' => $phone,
                'source' => 'csv_import',
                'status' => 'lead',
                'created_at' => current_time('mysql'),
            ]);
            $company_id = (int) $wpdb->insert_id;
        }

        $contacts = DG_Marketing_Clients::contacts_table();
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $contacts WHERE company_id = %d AND email = %s",
            $company_id,
            $email
        ));

        $contact_data = [
            'company_id' => $company_id,
            'first_name' => $first ?: $company_name,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'is_primary' => 1,
            'status' => 'active',
            'source' => 'csv_import',
        ];

        if ($contact) {
            $wpdb->update($contacts, $contact_data, ['id' => (int) $contact->id]);
        } else {
            $wpdb->insert($contacts, $contact_data);
        }

        DG_Marketing_Clients::sync_company($company_id);

        return $is_update ? 'updated' : 'created';
    }

    public static function render_admin_page() {
        if (!DG_Marketing_Permissions::can_import() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $stats = null;
        if (!empty($_GET['imported'])) {
            $stats = [
                'imported' => (int) ($_GET['new'] ?? 0),
                'updated' => (int) ($_GET['updated'] ?? 0),
                'skipped' => (int) ($_GET['skipped'] ?? 0),
            ];
        }
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>📥 Import Contacts</h1>
            <p style="color:#666;">Upload a CSV export (First Name, Last Name, phone columns, Email). Contacts are added to Agency Clients and synced to core CRM.</p>

            <?php if ($stats) : ?>
                <div class="notice notice-success"><p>
                    Import complete — <?php echo (int) $stats['imported']; ?> new,
                    <?php echo (int) $stats['updated']; ?> updated,
                    <?php echo (int) $stats['skipped']; ?> skipped.
                </p></div>
            <?php endif; ?>

            <?php if (!empty($_GET['error'])) : ?>
                <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['error']))); ?></p></div>
            <?php endif; ?>

            <div class="dg-panel" style="max-width:640px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="dg_marketing_import_csv">
                    <?php wp_nonce_field('dg_marketing_import_csv'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="csv_file">CSV file</label></th>
                            <td><input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required></td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary">Import contacts</button></p>
                </form>
            </div>

            <div class="dg-panel" style="max-width:640px;margin-top:20px;">
                <h3>Expected format</h3>
                <pre style="background:#f6f6f6;padding:12px;border-radius:8px;overflow:auto;">First Name,Last Name,Mobile Phone (Personal),Mobile Phone (Business),Email</pre>
                <p class="description">Duplicate emails update the existing agency client and core contact.</p>
            </div>
        </div>
        <?php
    }

    public static function handle_upload() {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dg_marketing_import_csv')) {
            wp_die('Invalid nonce');
        }
        if (!DG_Marketing_Permissions::can_import() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (empty($_FILES['csv_file']['tmp_name'])) {
            wp_redirect(admin_url('admin.php?page=dg-marketing-import&error=' . rawurlencode('No file uploaded.')));
            exit;
        }

        $result = self::import_csv_path($_FILES['csv_file']['tmp_name']);
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=dg-marketing-import&error=' . rawurlencode($result->get_error_message())));
            exit;
        }

        $url = add_query_arg([
            'page' => 'dg-marketing-import',
            'imported' => 1,
            'new' => (int) $result['imported'],
            'updated' => (int) $result['updated'],
            'skipped' => (int) $result['skipped'],
        ], admin_url('admin.php'));

        if (!empty($result['errors'])) {
            set_transient('dg_marketing_import_errors_' . get_current_user_id(), $result['errors'], 300);
        }

        wp_redirect($url);
        exit;
    }
}
