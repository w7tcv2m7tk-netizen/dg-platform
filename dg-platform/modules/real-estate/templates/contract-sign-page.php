<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($contract->title); ?> — Sign</title>
    <style>
        body { font-family: Inter, -apple-system, sans-serif; background: #0A0F1A; color: #E2E8F0; margin: 0; padding: 24px; }
        .wrap { max-width: 720px; margin: 0 auto; background: #141B2B; border: 1px solid #334155; border-radius: 16px; padding: 32px; }
        h1 { color: #fff; font-size: 1.5rem; margin: 0 0 8px; }
        .content { background: #fff; color: #1C2B2A; padding: 24px; border-radius: 8px; margin: 24px 0; line-height: 1.6; }
        label { display: block; margin: 12px 0 4px; font-weight: 600; }
        input[type=text], input[type=email] { width: 100%; padding: 10px; border: 1px solid #CBD5E1; border-radius: 6px; box-sizing: border-box; }
        button { background: #3B82F6; color: #fff; border: 0; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 16px; }
        .success { background: #D1FAE5; color: #065F46; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
        .muted { color: #94A3B8; font-size: 14px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1><?php echo esc_html($contract->title); ?></h1>
    <p class="muted"><?php echo esc_html(DG_RE_Property_Workspace::property_label($contract->property_id)); ?></p>

    <?php if ($signed) : ?>
        <div class="success">Thank you — your signature has been recorded. Roe Realty will be in touch if anything else is required.</div>
    <?php elseif ($contract->status === 'signed') : ?>
        <div class="success">This document was signed on <?php echo esc_html($contract->signed_at); ?> by <?php echo esc_html($contract->signer_name); ?>.</div>
        <?php if ($contract->signed_snapshot) : ?>
            <div class="content"><?php echo wp_kses_post($contract->signed_snapshot); ?></div>
        <?php endif; ?>
    <?php else : ?>
        <div class="content"><?php echo wp_kses_post($contract->content_html); ?></div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dg_re_sign_contract">
            <input type="hidden" name="sign_token" value="<?php echo esc_attr($contract->sign_token); ?>">
            <label for="signer_name">Your full name</label>
            <input type="text" id="signer_name" name="signer_name" required value="<?php echo esc_attr($contract->signer_name); ?>">
            <label style="margin-top:16px;">
                <input type="checkbox" name="agree" value="1" required>
                I have read this document and agree to sign electronically.
            </label>
            <p class="muted">Electronic signatures are recorded with date, time, and IP address for audit purposes.</p>
            <button type="submit">Sign document</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
