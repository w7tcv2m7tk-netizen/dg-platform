<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>📥 Import Contacts</h1>
    <p class="description">Upload vCard files (.vcf) exported from iPhone, Google Contacts, Outlook, or Mac Contacts.</p>

    <?php if (!empty($_GET['imported'])) : ?>
        <div class="notice notice-success">
            <p>
                Import complete —
                <?php echo (int) ($_GET['new'] ?? 0); ?> new,
                <?php echo (int) ($_GET['updated'] ?? 0); ?> updated,
                <?php echo (int) ($_GET['skipped'] ?? 0); ?> skipped.
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['error']))); ?></p></div>
    <?php endif; ?>

    <?php
    $import_errors = get_transient('dg_contacts_vcard_import_errors_' . get_current_user_id());
    if ($import_errors && is_array($import_errors)) :
        delete_transient('dg_contacts_vcard_import_errors_' . get_current_user_id());
        ?>
        <div class="notice notice-warning">
            <p><strong>Import notes:</strong></p>
            <ul style="margin:0.5rem 0 0 1.25rem;list-style:disc;">
                <?php foreach (array_slice($import_errors, 0, 10) as $msg) : ?>
                    <li><?php echo esc_html($msg); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($import_errors) > 10) : ?>
                <p class="description"><?php echo count($import_errors) - 10; ?> more skipped.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-contacts')); ?>" class="button">← Back to Contacts</a>
    </p>

    <div class="dg-panel" style="max-width:640px;">
        <h2>Import vCard (.vcf)</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="dg_import_contacts_vcard">
            <?php wp_nonce_field('dg_import_contacts_vcard'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="vcf_files">vCard files</label></th>
                    <td>
                        <input type="file" name="vcf_files[]" id="vcf_files" accept=".vcf,text/vcard,text/x-vcard,text/directory" multiple required>
                        <p class="description">Select one or more .vcf files. Multi-contact exports are supported.</p>
                    </td>
                </tr>
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Import contacts</button></p>
        </form>
    </div>

    <div class="dg-panel" style="max-width:640px;margin-top:20px;">
        <h3>Supported fields</h3>
        <ul style="margin:0.5rem 0 0 1.25rem;list-style:disc;color:#64748B;">
            <li>Name (FN / N)</li>
            <li>Email (required for import)</li>
            <li>Phone (TEL)</li>
            <li>Job title (TITLE)</li>
            <li>Organisation (ORG) — creates or links an organisation</li>
            <li>Notes (NOTE)</li>
        </ul>
        <p class="description" style="margin-top:12px;">Duplicate emails update the existing contact. Cards without an email are skipped.</p>
    </div>
</div>
