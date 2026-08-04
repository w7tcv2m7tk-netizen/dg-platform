<?php
if (!defined('ABSPATH')) {
    exit;
}
$settlement_checked = is_array($settlement['checked']) ? $settlement['checked'] : [];
?>
<div id="roe_property_files" class="roe-property-workspace">
    <style>
        .roe-property-workspace section { margin: 20px 0; padding-top: 12px; border-top: 1px solid #E0D6CC; }
        .roe-property-workspace h4 { margin: 0 0 10px; font-size: 14px; }
        .roe-pw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 782px) { .roe-pw-grid { grid-template-columns: 1fr; } }
    </style>

    <section>
        <h4>📎 Files &amp; documents</h4>
        <p style="color:#64748B;font-size:13px;">Attach contracts, floorplans, reports, and correspondence to this listing.</p>
        <?php if ($docs) : ?>
            <table class="widefat striped" style="margin-bottom:12px;">
                <thead><tr><th>File</th><th>Uploaded</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($docs as $doc) :
                    $url = $doc->attachment_id ? wp_get_attachment_url($doc->attachment_id) : '';
                    ?>
                    <tr>
                        <td><?php if ($url) : ?><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($doc->title); ?></a><?php else : ?><?php echo esc_html($doc->title); ?><?php endif; ?></td>
                        <td><?php echo esc_html($doc->created_at); ?></td>
                        <td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_re_delete_property_file&doc_id=' . (int) $doc->id . '&property_id=' . $property_id), 'dg_re_delete_property_file')); ?>">Remove</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p style="color:#64748B;">No files attached yet.</p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('roe_property_workspace'); ?>
            <input type="hidden" name="action" value="dg_re_attach_property_file">
            <input type="hidden" name="property_id" value="<?php echo (int) $property_id; ?>">
            <p>
                <label>Media attachment ID</label><br>
                <input type="number" name="attachment_id" required style="width:120px;">
                <span style="color:#64748B;font-size:12px;"> Upload via Media Library, then paste attachment ID here.</span>
            </p>
            <p><label>Title</label><br><input type="text" name="file_title" class="widefat" placeholder="e.g. Signed contract, Floorplan PDF"></p>
            <button type="submit" class="button">Attach file</button>
        </form>
    </section>

    <section>
        <h4>✍️ Contracts &amp; e-sign</h4>
        <p style="color:#64748B;font-size:13px;">Generate from template, email a signing link, and track status. For DocuSign integration, add API keys under API Settings (coming soon).</p>
        <?php if ($contracts) : ?>
            <table class="widefat striped" style="margin-bottom:12px;">
                <thead><tr><th>Document</th><th>Status</th><th>Signer</th><th>Link</th></tr></thead>
                <tbody>
                <?php foreach ($contracts as $c) : ?>
                    <tr>
                        <td><?php echo esc_html($c->title); ?></td>
                        <td><?php echo esc_html(ucfirst($c->status)); ?><?php if ($c->signed_at) : ?><br><small><?php echo esc_html($c->signed_at); ?></small><?php endif; ?></td>
                        <td><?php echo esc_html($c->signer_email ?: '—'); ?></td>
                        <td><?php if ($c->sign_token) : ?><a href="<?php echo esc_url(home_url('/sign-contract/' . $c->sign_token . '/')); ?>" target="_blank">Open</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px;">
            <?php wp_nonce_field('roe_property_workspace'); ?>
            <input type="hidden" name="action" value="dg_re_create_contract">
            <input type="hidden" name="property_id" value="<?php echo (int) $property_id; ?>">
            <select name="contract_template" required>
                <option value="">— Select template —</option>
                <?php foreach ($templates as $key => $tpl) : ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($tpl['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">Generate contract</button>
        </form>
        <?php if ($contracts) : $latest = $contracts[0]; if ($latest->status !== 'signed') : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('roe_property_workspace'); ?>
                <input type="hidden" name="action" value="dg_re_send_contract">
                <input type="hidden" name="contract_id" value="<?php echo (int) $latest->id; ?>">
                <div class="roe-pw-grid">
                    <p><label>Signer name</label><br><input type="text" name="signer_name" class="widefat"></p>
                    <p><label>Signer email</label><br><input type="email" name="signer_email" class="widefat" required></p>
                </div>
                <button type="submit" class="button button-primary">Email signing link</button>
            </form>
        <?php endif; endif; ?>
    </section>

    <section>
        <h4>🏁 Settlement &amp; listing management</h4>
        <p style="color:#64748B;font-size:13px;">Track sale milestones and settlement checklist for this property.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('roe_property_workspace'); ?>
            <input type="hidden" name="action" value="dg_re_save_settlement">
            <input type="hidden" name="property_id" value="<?php echo (int) $property_id; ?>">
            <div class="roe-pw-grid" style="margin-bottom:12px;">
                <p><label>Listing date</label><br><input type="date" name="listing_date" value="<?php echo esc_attr($settlement['listing_date']); ?>" class="widefat"></p>
                <p><label>Under contract</label><br><input type="date" name="under_contract_date" value="<?php echo esc_attr($settlement['under_contract_date']); ?>" class="widefat"></p>
                <p><label>Cooling-off ends</label><br><input type="date" name="cooling_off_date" value="<?php echo esc_attr($settlement['cooling_off_date']); ?>" class="widefat"></p>
                <p><label>Settlement date</label><br><input type="date" name="settlement_date" value="<?php echo esc_attr($settlement['settlement_date']); ?>" class="widefat"></p>
            </div>
            <?php foreach (DG_RE_Property_Workspace::settlement_items() as $item) : ?>
                <label style="display:block;margin:6px 0;">
                    <input type="checkbox" name="settlement[]" value="<?php echo esc_attr($item['key']); ?>" <?php checked(in_array($item['key'], $settlement_checked, true)); ?>>
                    <?php echo esc_html($item['label']); ?>
                </label>
            <?php endforeach; ?>
            <p style="margin-top:12px;"><button type="submit" class="button">Save settlement checklist</button></p>
        </form>
    </section>
</div>
