<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap dg-platform-wrap">
    <h1>💬 Support Inbox</h1>
    <?php if (!empty($_GET['sent'])) : ?><div class="notice notice-success"><p>Reply sent. AI assist paused for this conversation.</p></div><?php endif; ?>
    <?php if (!empty($_GET['error'])) : ?><div class="notice notice-error"><p>Could not send reply.</p></div><?php endif; ?>
    <?php if (!empty($_GET['ai']) && $_GET['ai'] === 'paused') : ?><div class="notice notice-success"><p>AI assist paused.</p></div><?php endif; ?>
    <?php if (!empty($_GET['ai']) && $_GET['ai'] === 'resumed') : ?><div class="notice notice-success"><p>AI assist resumed — it will reply to the next client message.</p></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;max-width:1200px;">
        <div class="dg-panel" style="padding:0;overflow:hidden;">
            <div style="padding:12px 16px;border-bottom:1px solid #e2e8f0;font-weight:600;">Conversations</div>
            <?php if (!$conversations) : ?>
                <p style="padding:16px;color:#64748B;">No support messages yet.</p>
            <?php else : ?>
                <?php foreach ($conversations as $row) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-support&conversation_id=' . (int) $row->id)); ?>"
                       style="display:block;padding:12px 16px;border-bottom:1px solid #e2e8f0;text-decoration:none;<?php echo ($conversation_id === (int) $row->id) ? 'background:#eff6ff;' : ''; ?>">
                        <strong style="color:#0f172a;"><?php echo esc_html($row->display_name ?: $row->user_email); ?></strong><br>
                        <span style="font-size:12px;color:#64748B;"><?php echo esc_html(wp_trim_words((string) ($row->last_body ?? ''), 12, '…')); ?></span><br>
                        <span style="font-size:11px;color:#94a3b8;"><?php echo esc_html($row->last_message_at ?? ''); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="dg-panel">
            <?php if (!$active) : ?>
                <p style="color:#64748B;">Select a conversation to view messages and reply.</p>
            <?php else : ?>
                <?php
                $ai_paused = !empty($active->ai_paused);
                $ai_on = class_exists('DG_Support_AI') && DG_Support_AI::enabled();
                ?>
                <h2 style="margin-top:0;"><?php echo esc_html($active->display_name ?: $active->user_email); ?></h2>
                <p style="color:#64748B;font-size:13px;">
                    <?php echo esc_html($active->user_email); ?>
                    <?php if (!empty($active->contact_id)) : ?>
                        · <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-contacts&action=edit&id=' . (int) $active->contact_id)); ?>">View contact</a>
                    <?php endif; ?>
                    <?php if ($ai_on) : ?>
                        · <span style="color:<?php echo $ai_paused ? '#b45309' : '#047857'; ?>;">
                            AI assist: <?php echo $ai_paused ? 'paused' : 'on'; ?>
                        </span>
                    <?php endif; ?>
                </p>
                <div style="max-height:420px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:12px;padding:16px;background:#f8fafc;margin:16px 0;">
                    <?php foreach ($messages as $msg) : ?>
                        <?php
                        $is_staff = $msg['role'] === 'staff';
                        $is_ai = $msg['role'] === 'ai';
                        $align = $is_staff ? 'text-align:right;' : '';
                        if ($is_staff) {
                            $bubble = 'background:#3B82F6;color:#fff;';
                        } elseif ($is_ai) {
                            $bubble = 'background:#ecfdf5;border:1px solid #6ee7b7;color:#064e3b;';
                        } else {
                            $bubble = 'background:#fff;border:1px solid #e2e8f0;';
                        }
                        ?>
                        <div style="margin-bottom:12px;<?php echo $align; ?>">
                            <div style="display:inline-block;max-width:85%;padding:10px 14px;border-radius:12px;<?php echo $bubble; ?>">
                                <div style="font-size:11px;opacity:0.8;margin-bottom:4px;"><?php echo esc_html($msg['sender']); ?> · <?php echo esc_html($msg['at']); ?></div>
                                <?php echo nl2br(esc_html($msg['body'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($ai_on) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;margin-bottom:12px;">
                        <?php if ($ai_paused) : ?>
                            <?php wp_nonce_field('dg_support_resume_ai'); ?>
                            <input type="hidden" name="action" value="dg_support_resume_ai">
                            <input type="hidden" name="conversation_id" value="<?php echo (int) $active->id; ?>">
                            <button type="submit" class="button">Resume AI assist</button>
                        <?php else : ?>
                            <?php wp_nonce_field('dg_support_pause_ai'); ?>
                            <input type="hidden" name="action" value="dg_support_pause_ai">
                            <input type="hidden" name="conversation_id" value="<?php echo (int) $active->id; ?>">
                            <button type="submit" class="button">Pause AI assist</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('dg_support_reply'); ?>
                    <input type="hidden" name="action" value="dg_support_reply">
                    <input type="hidden" name="conversation_id" value="<?php echo (int) $active->id; ?>">
                    <textarea name="message" rows="4" class="large-text" placeholder="Type your reply… (sending pauses AI assist)" required></textarea>
                    <p><button type="submit" class="button button-primary">Send reply</button></p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
