(function () {
    'use strict';

    var cfg = window.dgAdminToolbar || {};

    function setLabel(id, text) {
        var link = document.querySelector('#wp-admin-bar-' + id + ' > a');
        if (link) {
            link.textContent = text;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var purgeBtn = document.querySelector('#wp-admin-bar-dg-purge-cache > a');
        if (!purgeBtn || !cfg.purgeNonce) {
            return;
        }

        purgeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (purgeBtn.getAttribute('data-dg-purging') === '1') {
                return;
            }
            purgeBtn.setAttribute('data-dg-purging', '1');
            setLabel('dg-purge-cache', '⏳ Purging…');

            var fd = new FormData();
            fd.append('action', 'dg_purge_site_cache_ajax');
            fd.append('nonce', cfg.purgeNonce);

            fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (json) {
                    if (json && json.success) {
                        setLabel('dg-purge-cache', '✅ Cache purged');
                        setTimeout(function () {
                            setLabel('dg-purge-cache', '🔄 Purge Cache');
                            purgeBtn.removeAttribute('data-dg-purging');
                        }, 2500);
                    } else {
                        setLabel('dg-purge-cache', '❌ Purge failed');
                        setTimeout(function () {
                            setLabel('dg-purge-cache', '🔄 Purge Cache');
                            purgeBtn.removeAttribute('data-dg-purging');
                        }, 3000);
                    }
                })
                .catch(function () {
                    setLabel('dg-purge-cache', '❌ Purge failed');
                    setTimeout(function () {
                        setLabel('dg-purge-cache', '🔄 Purge Cache');
                        purgeBtn.removeAttribute('data-dg-purging');
                    }, 3000);
                });
        });
    });
})();
