<?php if (!defined('ABSPATH')) exit;
/** @var array<int,array<string,mixed>> $integration_rows */
$integration_rows = $integration_rows ?? (class_exists('DG_Integrations') ? DG_Integrations::get_hub_rows() : []);
$can_test = current_user_can('manage_options');
?>
<div class="dg-panel dg-integrations-hub">
    <h3>Integration status</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Service</th>
                <th>Status</th>
                <th>Details</th>
                <?php if ($can_test) : ?><th>Test</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($integration_rows as $row) :
                $configured = !empty($row['configured']);
                $status = (string) ($row['status'] ?? '');
                $badge = $status === 'connected' ? '✅ Connected' : ($status === 'ready' ? '🟡 Ready' : '⚪ Not configured');
            ?>
            <tr>
                <td><strong><?php echo esc_html($row['label'] ?? ''); ?></strong></td>
                <td><?php echo esc_html($badge); ?></td>
                <td><?php echo esc_html($row['detail'] ?? ''); ?></td>
                <?php if ($can_test) : ?>
                <td>
                    <?php if (!empty($row['testable']) && ($row['key'] ?? '') === 'openai') : ?>
                        <button type="button" class="button button-small dg-test-integration" data-service="openai">Test</button>
                    <?php elseif (!empty($row['testable']) && ($row['key'] ?? '') === 'smtp') : ?>
                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-site-tools&tab=email')); ?>">Site Tools</a>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="description" style="margin-top:12px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-api')); ?>">API Settings</a>
        · <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-site-tools')); ?>">Site Tools</a>
        · <a href="<?php echo esc_url(admin_url('admin.php?page=dg-platform-reports')); ?>">Growth Intelligence</a>
    </p>
    <?php if ($can_test) : ?>
    <p id="dg-integration-test-result" class="description" style="display:none;margin-top:8px;"></p>
    <script>
    (function () {
        var btn = document.querySelector('.dg-test-integration[data-service="openai"]');
        if (!btn || typeof ajaxurl === 'undefined') return;
        btn.addEventListener('click', function () {
            var out = document.getElementById('dg-integration-test-result');
            btn.disabled = true;
            out.style.display = 'block';
            out.textContent = 'Testing OpenAI…';
            var body = new FormData();
            body.append('action', 'dg_test_integration');
            body.append('service', 'openai');
            body.append('_wpnonce', '<?php echo esc_js(wp_create_nonce('dg_test_integration')); ?>');
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    out.textContent = data.success ? (data.data.message || 'OK') : (data.data && data.data.message ? data.data.message : 'Test failed');
                })
                .catch(function () { out.textContent = 'Could not reach server.'; })
                .finally(function () { btn.disabled = false; });
        });
    })();
    </script>
    <?php endif; ?>
</div>
