<?php
if (!defined('ABSPATH')) exit;
$term = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
$results = $term !== '' ? DG_Search::query($term, 30) : [];
$type_labels = [
    'contact' => 'Contact',
    'property' => 'Property',
    'vendor_lead' => 'Vendor Lead',
    'buyer_lead' => 'Buyer Lead',
];
?>
<div class="wrap dg-platform-wrap">
    <h1>🔍 Universal Search</h1>
    <form method="get" style="margin:16px 0;max-width:640px;display:flex;gap:8px;">
        <input type="hidden" name="page" value="dg-platform-search">
        <input type="search" name="q" value="<?php echo esc_attr($term); ?>" placeholder="Search contacts, properties, leads..." class="regular-text" style="flex:1;">
        <button type="submit" class="button button-primary">Search</button>
    </form>

    <?php if ($term !== '') : ?>
        <p style="color:#666;">Results for <strong><?php echo esc_html($term); ?></strong></p>
        <?php if ($results) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Type</th><th>Title</th><th>Details</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($results as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($type_labels[$row['type']] ?? $row['type']); ?></td>
                            <td><strong><?php echo esc_html($row['title']); ?></strong></td>
                            <td><?php echo esc_html($row['subtitle']); ?></td>
                            <td><a href="<?php echo esc_url($row['url']); ?>" class="button button-small">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p style="color:#999;">No results found.</p>
        <?php endif; ?>
    <?php else : ?>
        <p style="color:#999;">Search across contacts, properties, vendor leads, and buyer enquiries.</p>
    <?php endif; ?>
</div>
