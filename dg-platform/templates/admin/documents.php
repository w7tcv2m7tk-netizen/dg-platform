<?php if (!defined('ABSPATH')) exit;
/** @var array<int,object> $documents */
$documents = $documents ?? [];
?>
<div class="wrap dg-platform-wrap">
    <h1>Documents</h1>
    <?php if (isset($_GET['uploaded'])) : ?><div class="notice notice-success"><p>Document attached.</p></div><?php endif; ?>
    <?php if (isset($_GET['deleted'])) : ?><div class="notice notice-success"><p>Document removed.</p></div><?php endif; ?>

    <div class="dg-panel">
        <h3>Attach document</h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('dg_save_document'); ?>
            <input type="hidden" name="action" value="dg_save_document">
            <div class="dg-form-grid">
                <div><label>Media attachment ID</label><input type="number" name="attachment_id" class="regular-text" required min="1">
                    <p class="description">Upload via Media Library first, then paste the attachment ID.</p></div>
                <div><label>Title</label><input type="text" name="title" class="regular-text"></div>
                <div><label>Entity type</label>
                    <select name="entity_type">
                        <option value="organisation">Organisation</option>
                        <option value="contact">Contact</option>
                        <option value="property">Property</option>
                        <option value="booking">Booking</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div><label>Entity ID</label><input type="number" name="entity_id" class="regular-text" value="0" min="0"></div>
            </div>
            <p class="submit"><button type="submit" class="button button-primary">Attach document</button></p>
        </form>
    </div>

    <div class="dg-panel">
        <h3>All documents (<?php echo count($documents); ?>)</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Title</th><th>Entity</th><th>Type</th><th>Uploaded</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($documents) : foreach ($documents as $doc) :
                    $file_url = !empty($doc->attachment_id) ? wp_get_attachment_url((int) $doc->attachment_id) : '';
                ?>
                <tr>
                    <td><?php echo esc_html($doc->title ?: 'Untitled'); ?></td>
                    <td><?php echo esc_html($doc->entity_type . ' #' . (int) $doc->entity_id); ?></td>
                    <td><?php echo esc_html($doc->mime_type ?: '—'); ?></td>
                    <td><?php echo esc_html($doc->created_at ?? ''); ?></td>
                    <td>
                        <?php if ($file_url) : ?><a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener">Open</a> · <?php endif; ?>
                        <?php echo DG_Admin_Delete::link('dg_delete_document', (int) $doc->id); ?>
                    </td>
                </tr>
                <?php endforeach; else : ?>
                <tr><td colspan="5">No documents yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
