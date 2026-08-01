/**
 * Roe Realty public form helpers (nonce refresh for cached pages).
 */
(function (window) {
    'use strict';

    var cache = {};
    var pending = null;

    function ajaxUrl() {
        return (window.dgReForms && window.dgReForms.ajaxUrl) || '/wp-admin/admin-ajax.php';
    }

    function fetchAll() {
        if (pending) {
            return pending;
        }
        var url = ajaxUrl() + '?action=dg_re_form_nonces&_=' + Date.now();
        pending = fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                pending = null;
                if (json && json.success && json.data) {
                    cache = json.data;
                    return cache;
                }
                return cache;
            })
            .catch(function () {
                pending = null;
                return cache;
            });
        return pending;
    }

    window.dgReForms = window.dgReForms || {};
    window.dgReForms.fetchNonces = fetchAll;
    window.dgReForms.getNonce = function (action, fallback) {
        if (cache[action]) {
            return Promise.resolve(cache[action]);
        }
        return fetchAll().then(function (nonces) {
            return nonces[action] || fallback || '';
        });
    };
}(window));
