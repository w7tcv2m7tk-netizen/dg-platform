<?php
if (!defined('ABSPATH')) {
    exit;
}
$summary = $summary ?? [];
$sections = $sections ?? [];
$percent = (int) ($summary['percent'] ?? 0);
$ready = !empty($summary['ready']);
$bar_color = $ready ? '#059669' : ($percent >= 60 ? '#F59E0B' : '#DC2626');
?>
<div class="wrap dg-platform-wrap">
    <h1>🚀 Beta Setup</h1>
    <p style="color:#64748B;max-width:820px;">Interactive checklist for beta launch. Auto-checks run on each page load — complete warnings before onboarding pilot agencies. Full playbook: <code>marketing/pages/BETA-LAUNCH-PACK.md</code></p>

    <?php if (!empty($_GET['reset'])) : ?>
        <div class="notice notice-success"><p>Onboarding dismiss reset — dashboard notice restored.</p></div>
    <?php endif; ?>

    <div class="dg-panel" style="margin-top:16px;">
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
            <div style="font-size:2.5rem;font-weight:700;color:<?php echo esc_attr($bar_color); ?>;"><?php echo esc_html((string) $percent); ?>%</div>
            <div style="flex:1;min-width:200px;">
                <div style="background:#E5E7EB;border-radius:999px;height:10px;overflow:hidden;">
                    <div style="background:<?php echo esc_attr($bar_color); ?>;height:100%;width:<?php echo esc_attr((string) min(100, $percent)); ?>%;"></div>
                </div>
                <p style="margin:8px 0 0;color:#64748B;">
                    <?php echo (int) ($summary['complete'] ?? 0); ?>/<?php echo (int) ($summary['total'] ?? 0); ?> complete
                    · <?php echo (int) ($summary['fail'] ?? 0); ?> fail
                    · <?php echo (int) ($summary['warn'] ?? 0); ?> warn
                </p>
            </div>
            <?php if ($ready) : ?>
                <span class="dg-tag" style="background:#D1FAE5;color:#065F46;">Beta ready</span>
            <?php else : ?>
                <span class="dg-tag" style="background:#FEF3C7;color:#92400E;">In progress</span>
            <?php endif; ?>
        </div>
        <p style="margin-top:16px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-site-tools&tab=health')); ?>" class="button">Platform Health</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-modules')); ?>" class="button">Modules & Plan</a>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dg_reset_onboarding'), 'dg_reset_onboarding')); ?>" class="button">Reset dashboard notice</a>
        </p>
    </div>

    <?php foreach ($sections as $section) : ?>
        <div class="dg-panel">
            <h2><?php echo esc_html($section['title']); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:28px;"></th>
                        <th>Step</th>
                        <th>Detail</th>
                        <th style="width:100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($section['steps'] as $step) :
                        $status = $step['status'] ?? 'warn';
                        if ($status === 'pass') {
                            $icon = '✅';
                            $label = 'Done';
                        } elseif ($status === 'fail') {
                            $icon = '❌';
                            $label = 'Fix';
                        } else {
                            $icon = '⚠️';
                            $label = 'Todo';
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($icon); ?></td>
                            <td><strong><?php echo esc_html($step['label']); ?></strong></td>
                            <td style="color:#64748B;"><?php echo esc_html($step['detail']); ?></td>
                            <td><a href="<?php echo esc_url($step['url']); ?>" class="button button-small"><?php echo esc_html($label); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <div class="dg-panel">
        <h2>Roe Realty test script</h2>
        <p style="color:#64748B;">Before each beta release, run the full manual test script in <code>BETA-LAUNCH-PACK.md §3</code> on roerealty.com.au (properties, agents, bookings, forms, SEO, automations).</p>
    </div>
</div>
