<?php
if (!defined('ABSPATH')) exit;
$entity_type = 'contact';
$fields = DG_Entity_Meta::get_definitions($entity_type);
if (isset($_GET['saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>Custom fields saved.</p></div>';
}
?>
<div class="wrap dg-platform-wrap">
    <h1>🏷️ Custom Fields</h1>
    <p style="color:#666;">Define extra fields for contacts. Values are edited on each contact record.</p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('dg_save_custom_fields'); ?>
        <input type="hidden" name="action" value="dg_save_custom_fields">
        <input type="hidden" name="entity_type" value="<?php echo esc_attr($entity_type); ?>">
        <table class="wp-list-table widefat fixed striped" id="dg-custom-fields-table">
            <thead><tr><th>Key</th><th>Label</th><th>Type</th><th></th></tr></thead>
            <tbody>
                <?php if ($fields) : foreach ($fields as $i => $field) : ?>
                    <tr>
                        <td><input type="text" name="fields[<?php echo $i; ?>][key]" value="<?php echo esc_attr($field['key'] ?? ''); ?>" required></td>
                        <td><input type="text" name="fields[<?php echo $i; ?>][label]" value="<?php echo esc_attr($field['label'] ?? ''); ?>" required></td>
                        <td>
                            <select name="fields[<?php echo $i; ?>][type]">
                                <?php foreach (['text', 'textarea', 'number', 'date', 'select'] as $type) : ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected($field['type'] ?? 'text', $type); ?>><?php echo esc_html(ucfirst($type)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><button type="button" class="button button-small dg-remove-field">Remove</button></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr class="dg-empty-row"><td colspan="4" style="color:#999;text-align:center;">No custom fields yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:12px;">
            <button type="button" class="button" id="dg-add-field">Add field</button>
            <button type="submit" class="button button-primary">Save fields</button>
        </p>
    </form>
</div>
<script>
(function() {
    let idx = <?php echo count($fields); ?>;
    document.getElementById('dg-add-field')?.addEventListener('click', function() {
        const tbody = document.querySelector('#dg-custom-fields-table tbody');
        const empty = tbody.querySelector('.dg-empty-row');
        if (empty) empty.remove();
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="fields[' + idx + '][key]" required></td>' +
            '<td><input type="text" name="fields[' + idx + '][label]" required></td>' +
            '<td><select name="fields[' + idx + '][type]"><option value="text">Text</option><option value="textarea">Textarea</option><option value="number">Number</option><option value="date">Date</option><option value="select">Select</option></select></td>' +
            '<td><button type="button" class="button button-small dg-remove-field">Remove</button></td>';
        tbody.appendChild(tr);
        idx++;
    });
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('dg-remove-field')) {
            e.target.closest('tr').remove();
        }
    });
})();
</script>
