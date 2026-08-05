/**
 * Admin + frontend address finder for Roe property fields.
 */
(function (window, document) {
    'use strict';

    function ajaxUrl() {
        return (window.dgReForms && window.dgReForms.ajaxUrl) || '/wp-admin/admin-ajax.php';
    }

    function findField(id) {
        return document.getElementById(id) || document.querySelector('[name="' + id + '"]');
    }

    async function resolveAddress(raw) {
        var payload = new URLSearchParams();
        payload.append('action', 'dg_resolve_address');
        payload.append('rawAddress', raw);

        var res = await fetch(ajaxUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString(),
            credentials: 'same-origin'
        });
        var json = await res.json();
        if (!json || !json.success || !json.data || !json.data.data) {
            throw new Error((json && json.data && json.data.message) || 'Address lookup failed');
        }
        return json.data.data;
    }

    function applyResolved(data) {
        var line1 = findField('roe_property_address');
        var suburb = findField('roe_property_suburb');
        var state = findField('roe_property_state');
        var postcode = findField('roe_property_postcode');

        if (line1 && data.addressLine1) line1.value = data.addressLine1;
        if (suburb && data.suburb) suburb.value = data.suburb;
        if (state && data.state) state.value = data.state;
        if (postcode && data.postcode) postcode.value = data.postcode;
    }

    function attachFinder(input) {
        if (!input || input.dataset.dgAddressFinder === '1') return;
        input.dataset.dgAddressFinder = '1';

        var wrap = document.createElement('div');
        wrap.className = 'dg-address-finder-actions';
        wrap.style.marginTop = '6px';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'button button-secondary';
        btn.textContent = 'Find address automatically';
        wrap.appendChild(btn);

        input.parentNode.appendChild(wrap);

        async function runLookup() {
            var raw = input.value.trim();
            if (!raw) return;
            btn.disabled = true;
            btn.textContent = 'Finding address…';
            try {
                var data = await resolveAddress(raw);
                applyResolved(data);
                if (data.formatted) input.value = data.addressLine1 || data.formatted.split(',')[0];
                btn.textContent = 'Address updated';
            } catch (err) {
                btn.textContent = 'Find address automatically';
                window.alert(err.message || 'Address lookup failed');
            } finally {
                btn.disabled = false;
                setTimeout(function () {
                    btn.textContent = 'Find address automatically';
                }, 2500);
            }
        }

        btn.addEventListener('click', runLookup);
        input.addEventListener('blur', function () {
            if (input.value.trim()) runLookup();
        });
    }

    function init() {
        var addressInput = findField('roePropertyAddress') || findField('roe_property_address');
        if (addressInput) attachFinder(addressInput);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.dgAddressFinder = {
        resolveAddress: resolveAddress,
        applyResolved: applyResolved,
        attachFinder: attachFinder
    };
})(window, document);
