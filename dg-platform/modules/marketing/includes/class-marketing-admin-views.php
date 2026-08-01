<?php
/**
 * Marketing admin screens — dashboard, pipeline, email templates, documents.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Marketing_Admin_Views {

    public static function render_dashboard() {
        if (!DG_Marketing_Permissions::can_view_clients() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $activity = DG_Marketing_Pipeline_Reports::recent_activity_summary(30);
        $conversion = DG_Marketing_Pipeline_Reports::client_conversion_summary();
        $averages = DG_Marketing_AI_Visibility::platform_averages();
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>📊 DigitalGate CRM</h1>
            <p style="color:#64748B;">Agency clients, AI visibility audits, voice leads, and automation.</p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0;">
                <?php self::stat_card('Agency Clients', $conversion['total'], '#3B82F6'); ?>
                <?php self::stat_card('New (30d)', $activity['new_clients'], '#34D399'); ?>
                <?php self::stat_card('Audits (30d)', $activity['audits'], '#8B5CF6'); ?>
                <?php self::stat_card('Voice Leads (30d)', $activity['voice_leads'], '#F59E0B'); ?>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                <div class="dg-panel">
                    <h2>Pipeline conversion</h2>
                    <p><strong><?php echo esc_html($conversion['rate']); ?>%</strong> lead → engaged/client</p>
                    <p style="color:#64748B;">Avg AI visibility (90d): <strong><?php echo esc_html($averages['ai_avg']); ?>%</strong> · Scans: <?php echo (int) $averages['scans']; ?></p>
                </div>
                <div class="dg-panel">
                    <h2>Quick actions</h2>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-clients&action=add')); ?>">Add client</a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-audits')); ?>">Run audit</a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dg-marketing-import')); ?>">Import CSV</a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-api')); ?>">API settings</a>
                    </p>
                </div>
            </div>
            <?php self::render_status_table(DG_Marketing_Pipeline_Reports::status_counts()); ?>
        </div>
        <?php
    }

    public static function render_pipeline_reports() {
        if (!DG_Marketing_Permissions::can_view_clients() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $activity = DG_Marketing_Pipeline_Reports::recent_activity_summary(7);
        $conversion = DG_Marketing_Pipeline_Reports::client_conversion_summary();
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>📈 Pipeline Reports</h1>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0;">
                <?php self::stat_card('Audits this month', DG_Marketing_Pipeline_Reports::audits_this_month(), '#3B82F6'); ?>
                <?php self::stat_card('Voice this month', DG_Marketing_Pipeline_Reports::voice_leads_this_month(), '#F59E0B'); ?>
                <?php self::stat_card('Automation sent (7d)', $activity['automation_sent'], '#34D399'); ?>
                <?php self::stat_card('Conversion rate', $conversion['rate'] . '%', '#8B5CF6'); ?>
            </div>
            <h2>Client pipeline</h2>
            <?php self::render_status_table(DG_Marketing_Pipeline_Reports::status_counts()); ?>
            <h2>Leads by source</h2>
            <table class="wp-list-table widefat striped"><thead><tr><th>Source</th><th>Total</th></tr></thead><tbody>
            <?php foreach (DG_Marketing_Pipeline_Reports::source_counts() as $row) : ?>
                <tr><td><?php echo esc_html(ucwords(str_replace('_', ' ', $row->source))); ?></td><td><?php echo (int) $row->total; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    public static function render_client_pipeline() {
        if (!DG_Marketing_Permissions::can_manage_clients() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (!empty($_GET['advance']) && !empty($_GET['client_id']) && check_admin_referer('dg_marketing_advance_stage')) {
            DG_Marketing_Client_Pipeline::advance_stage((int) $_GET['client_id'], sanitize_text_field(wp_unslash($_GET['advance'])));
            wp_safe_redirect(admin_url('admin.php?page=dg-marketing-client-pipeline&updated=1'));
            exit;
        }
        $board = DG_Marketing_Client_Pipeline::list_for_kanban();
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>🗂️ Client Pipeline</h1>
            <?php if (!empty($_GET['updated'])) : ?><div class="notice notice-success"><p>Stage updated.</p></div><?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
            <?php foreach ($board as $status => $column) : ?>
                <div class="dg-panel" style="min-height:200px;">
                    <h3><?php echo esc_html($column['label']); ?> (<?php echo count($column['clients']); ?>)</h3>
                    <?php foreach ($column['clients'] as $client) : ?>
                        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:10px;margin-bottom:8px;">
                            <strong><?php echo esc_html($client->company_name); ?></strong><br>
                            <span style="font-size:12px;color:#64748B;"><?php echo esc_html($client->email); ?></span><br>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-clients&client_id=' . $client->id . '&tab=view')); ?>">View</a>
                            <?php foreach (DG_Marketing_Client_Pipeline::stages() as $key => $label) :
                                if ($key === ($client->status ?: 'lead')) continue; ?>
                                · <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=dg-marketing-client-pipeline&client_id=' . $client->id . '&advance=' . $key), 'dg_marketing_advance_stage')); ?>"><?php echo esc_html($label); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public static function render_email_templates() {
        if (!DG_Marketing_Permissions::can_manage_clients() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (!empty($_POST['dg_save_marketing_templates']) && check_admin_referer('dg_marketing_email_templates')) {
            DG_Marketing_Email_Templates::save($_POST['templates'] ?? []);
            echo '<div class="notice notice-success"><p>Templates saved.</p></div>';
        }
        $templates = DG_Marketing_Email_Templates::all();
        ?>
        <div class="wrap dg-platform-wrap">
            <h1>✉️ Email Templates</h1>
            <p style="color:#64748B;">Placeholders: <?php echo esc_html(DG_Marketing_Email_Templates::placeholders_help()); ?></p>
            <form method="post">
                <?php wp_nonce_field('dg_marketing_email_templates'); ?>
                <?php foreach ($templates as $key => $template) : ?>
                    <div class="dg-panel" style="margin-bottom:16px;">
                        <h2><?php echo esc_html($template['label']); ?></h2>
                        <p><label>Subject<br><input type="text" class="large-text" name="templates[<?php echo esc_attr($key); ?>][subject]" value="<?php echo esc_attr($template['subject']); ?>"></label></p>
                        <p><label>Body<br><textarea class="large-text" rows="6" name="templates[<?php echo esc_attr($key); ?>][body]"><?php echo esc_textarea($template['body']); ?></textarea></label></p>
                    </div>
                <?php endforeach; ?>
                <p><button type="submit" name="dg_save_marketing_templates" class="button button-primary">Save templates</button></p>
            </form>
        </div>
        <?php
    }

    public static function handle_attach_document() {
        if (!DG_Marketing_Permissions::can_manage_clients() && !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('dg_marketing_attach_document');
        $company_id = (int) ($_POST['company_id'] ?? 0);
        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        if ($company_id && $attachment_id && class_exists('DG_Documents')) {
            $org_id = DG_Marketing_Clients::get_org_id($company_id);
            DG_Documents::attach($attachment_id, 'organisation', $org_id ?: $company_id, sanitize_text_field($_POST['title'] ?? ''));
            if (class_exists('DG_Permissions')) {
                DG_Permissions::log_audit('document_attached', 'organisation', $org_id ?: $company_id, null, $attachment_id);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=dg-platform-clients&client_id=' . $company_id . '&tab=documents&added=1'));
        exit;
    }

    public static function render_documents_tab($client_id) {
        $org_id = DG_Marketing_Clients::get_org_id($client_id);
        $docs = class_exists('DG_Documents') ? DG_Documents::get_for_entity('organisation', $org_id ?: $client_id) : [];
        if (!empty($_GET['added'])) {
            echo '<div class="notice notice-success"><p>Document attached.</p></div>';
        }
        echo '<h3>Documents</h3>';
        if ($docs) {
            echo '<ul>';
            foreach ($docs as $doc) {
                $url = wp_get_attachment_url((int) $doc->attachment_id);
                echo '<li><a href="' . esc_url($url) . '" target="_blank">' . esc_html($doc->title) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color:#64748B;">No documents yet.</p>';
        }
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
            <?php wp_nonce_field('dg_marketing_attach_document'); ?>
            <input type="hidden" name="action" value="dg_marketing_attach_document">
            <input type="hidden" name="company_id" value="<?php echo (int) $client_id; ?>">
            <p><label>Media attachment ID<br><input type="number" name="attachment_id" class="regular-text" required></label></p>
            <p><label>Title<br><input type="text" name="title" class="regular-text"></label></p>
            <p class="description">Upload a file in Media Library first, then paste the attachment ID here.</p>
            <p><button type="submit" class="button button-primary">Attach document</button></p>
        </form>
        <?php
    }

    private static function stat_card($label, $value, $color) {
        echo '<div class="dg-panel" style="border-left:4px solid ' . esc_attr($color) . ';">';
        echo '<div style="font-size:28px;font-weight:700;">' . esc_html((string) $value) . '</div>';
        echo '<div style="color:#64748B;">' . esc_html($label) . '</div></div>';
    }

    private static function render_status_table($statuses) {
        echo '<table class="wp-list-table widefat striped"><thead><tr><th>Stage</th><th>Count</th></tr></thead><tbody>';
        foreach ($statuses as $row) {
            echo '<tr><td>' . esc_html($row['label']) . '</td><td>' . (int) $row['count'] . '</td></tr>';
        }
        echo '</tbody></table>';
    }
}
