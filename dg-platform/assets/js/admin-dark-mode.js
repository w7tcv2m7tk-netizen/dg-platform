(function () {
    'use strict';

    var cfg = window.dgDarkMode || {};
    var CLASS = 'admin-dark-mode';
    var COOKIE = cfg.cookie || 'dg_admin_dark';
    var html = document.documentElement;

    function setCookie(isDark) {
        var expires = new Date();
        expires.setFullYear(expires.getFullYear() + 1);
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = COOKIE + '=' + (isDark ? '1' : '0') + '; path=/; expires=' + expires.toUTCString() + '; SameSite=Lax' + secure;
    }

    function updateToggleLabel(isDark) {
        var btn = document.querySelector('#wp-admin-bar-dg-dark-toggle > a');
        if (!btn) {
            return;
        }
        btn.textContent = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.querySelector('.dg-dark-toggle-btn a, #wp-admin-bar-dg-dark-toggle a');
        if (!toggle || !cfg.canToggle) {
            return;
        }

        updateToggleLabel(html.classList.contains(CLASS));

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            var isDark = html.classList.toggle(CLASS);
            setCookie(isDark);
            updateToggleLabel(isDark);

            var fd = new FormData();
            fd.append('action', 'dg_toggle_dark_mode');
            fd.append('is_dark', isDark ? '1' : '0');
            fd.append('nonce', cfg.nonce || '');

            fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            });
        });
    });
})();
