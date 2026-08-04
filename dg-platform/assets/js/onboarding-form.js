(function () {
    'use strict';

    var cfg = window.dgOnboardingForm || {};
    var form = document.getElementById('onboardingForm');
    if (!form) {
        return;
    }

    if (cfg.actionUrl) {
        form.setAttribute('action', cfg.actionUrl);
    }

    if (cfg.nonce && !form.querySelector('input[name="_wpnonce"]')) {
        var nonce = document.createElement('input');
        nonce.type = 'hidden';
        nonce.name = '_wpnonce';
        nonce.value = cfg.nonce;
        form.insertBefore(nonce, form.firstChild);
    }

    var params = new URLSearchParams(window.location.search);
    var sessionId = params.get('session_id') || '';
    var plan = params.get('plan') || params.get('tier') || '';
    var category = params.get('category') || '';

    function setHidden(name, value) {
        if (!value) {
            return;
        }
        var input = form.querySelector('input[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }

    function checkValues(name, values) {
        if (!values.length) {
            return;
        }
        form.querySelectorAll('input[name="' + name + '"]').forEach(function (el) {
            if (values.indexOf(el.value) !== -1) {
                el.checked = true;
            }
        });
    }

    setHidden('stripe_session_id', sessionId);

    if (plan) {
        var tierInput = form.querySelector('select[name="platform_tier"]');
        if (tierInput) {
            tierInput.value = plan;
        }
        var growthInput = form.querySelector('select[name="growth_tier"]');
        if (growthInput && category === 'growth') {
            growthInput.value = plan;
        }
    }

    if (category === 'app' && plan) {
        checkValues('purchased_apps[]', [plan]);
    }

    if (category === 'premium' && plan) {
        checkValues('purchased_premium[]', [plan]);
    }

    if (sessionId || plan || category) {
        var summary = [category, plan].filter(Boolean).join(' · ');
        setHidden('purchase_summary', summary);
        var banner = document.getElementById('purchaseBanner');
        if (banner && summary) {
            banner.style.display = 'block';
            banner.textContent = 'Thanks for your purchase — we\'ve pre-filled your plan details below. Please complete the rest of the form.';
        }
    }
}());
