<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1><?php echo $contact ? 'Edit Contact' : 'Add Contact'; ?></h1>
    <div class="dg-panel">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="dg_save_contact">
            <?php wp_nonce_field('dg_save_contact'); ?>
            <?php if ($contact) : ?><input type="hidden" name="contact_id" value="<?php echo esc_attr($contact->id); ?>"><?php endif; ?>
            <div class="dg-form-grid">
                <div>
                    <label>First Name</label>
                    <input type="text" name="first_name" class="regular-text" value="<?php echo esc_attr($contact->first_name ?? ''); ?>" required>
                </div>
                <div>
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="regular-text" value="<?php echo esc_attr($contact->last_name ?? ''); ?>">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="email" class="regular-text" value="<?php echo esc_attr($contact->email ?? ''); ?>" required>
                </div>
                <div>
                    <label>Phone</label>
                    <input type="text" name="phone" class="regular-text" value="<?php echo esc_attr($contact->phone ?? ''); ?>">
                </div>
                <div>
                    <label>Source</label>
                    <select name="source">
                        <?php foreach (['website','referral','phone','walk-in','google_ads','facebook','database'] as $src) : ?>
                            <option value="<?php echo esc_attr($src); ?>" <?php selected($contact->source ?? 'website', $src); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $src))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?php selected($contact->status ?? 'active', 'active'); ?>>Active</option>
                        <option value="inactive" <?php selected($contact->status ?? '', 'inactive'); ?>>Inactive</option>
                    </select>
                </div>
                <div class="full-width">
                    <label>Notes</label>
                    <textarea name="notes" rows="4" class="large-text"><?php echo esc_textarea($contact->notes ?? ''); ?></textarea>
                </div>
                <?php if (!empty($custom_fields)) : ?>
                    <?php foreach ($custom_fields as $field) :
                        $key = $field['key'] ?? '';
                        if ($key === '') continue;
                        $val = $custom_values[$key] ?? '';
                        ?>
                        <div class="<?php echo ($field['type'] ?? 'text') === 'textarea' ? 'full-width' : ''; ?>">
                            <label><?php echo esc_html($field['label'] ?? $key); ?></label>
                            <?php if (($field['type'] ?? 'text') === 'textarea') : ?>
                                <textarea name="custom_fields[<?php echo esc_attr($key); ?>]" rows="3" class="large-text"><?php echo esc_textarea($val); ?></textarea>
                            <?php else : ?>
                                <input type="<?php echo esc_attr($field['type'] ?? 'text'); ?>" name="custom_fields[<?php echo esc_attr($key); ?>]" class="regular-text" value="<?php echo esc_attr($val); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="submit"><button type="submit" class="button button-primary">Save Contact</button></p>
        </form>
    </div>
</div>
