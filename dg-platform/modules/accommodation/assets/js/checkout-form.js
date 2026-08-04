(function () {
    'use strict';

    var stripeInstance = null;
    var cardElement = null;

    function cfg() {
        return window.dgCheckoutForm || {};
    }

    function getForm() {
        return document.querySelector('#dg-booking-form .dg-checkout-form');
    }

    function showError(message) {
        var box = document.getElementById('dg-checkout-error');
        if (!box) {
            alert(message);
            return;
        }
        box.textContent = message;
        box.style.display = 'block';
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearError() {
        var box = document.getElementById('dg-checkout-error');
        if (box) {
            box.style.display = 'none';
            box.textContent = '';
        }
        var stripeErr = document.getElementById('dg-stripe-card-errors');
        if (stripeErr) {
            stripeErr.textContent = '';
        }
    }

    function validateForm(form) {
        if (form.classList.contains('is-disabled')) {
            showError('Please select your dates on the calendar first.');
            return false;
        }

        var checkin = document.getElementById('dg-booking-checkin');
        var checkout = document.getElementById('dg-booking-checkout');
        if (!checkin || !checkout || !checkin.value || !checkout.value) {
            showError('Please select check-in and check-out dates on the calendar.');
            return false;
        }

        var name = document.getElementById('enquiry_name');
        var email = document.getElementById('enquiry_email');
        if (!name || !name.value.trim() || !email || !email.value.trim()) {
            showError('Please enter your name and email.');
            return false;
        }

        return true;
    }

    function bookingPayload(form) {
        return {
            accommodation_id: document.getElementById('dg-accommodation-id')?.value || 0,
            booking_total: document.getElementById('dg-booking-total')?.value || '0',
            enquiry_name: document.getElementById('enquiry_name')?.value.trim() || '',
            enquiry_email: document.getElementById('enquiry_email')?.value.trim() || '',
            enquiry_phone: document.getElementById('enquiry_phone')?.value || '',
            booking_checkin: document.getElementById('dg-booking-checkin')?.value || '',
            booking_checkout: document.getElementById('dg-booking-checkout')?.value || '',
            booking_nights: document.getElementById('dg-booking-nights')?.value || 0,
            enquiry_guests: document.getElementById('enquiry_guests')?.value || 2,
            enquiry_message: document.getElementById('enquiry_message')?.value || ''
        };
    }

    function setBusy(btn, busy, label) {
        if (!btn) return;
        if (busy) {
            btn.dataset.originalLabel = btn.textContent;
            btn.disabled = true;
            btn.textContent = label || '⏳ Processing…';
        } else {
            btn.disabled = false;
            btn.textContent = btn.dataset.originalLabel || btn.textContent;
        }
    }

    function submitPayid(form, btn) {
        clearError();
        if (!validateForm(form)) return;

        var stripeBtn = document.getElementById('dg-stripe-submit');
        var panel = document.getElementById('dg-stripe-panel');
        if (stripeBtn) {
            stripeBtn.classList.remove('is-active');
        }
        if (panel) {
            panel.hidden = true;
        }

        setBusy(btn, true, '⏳ Booking…');

        var data = new FormData(form);
        data.append('action', 'dg_confirm_payid_booking');

        fetch(cfg().ajaxUrl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.success && result.data && result.data.redirect_url) {
                    window.location.href = result.data.redirect_url;
                    return;
                }
                throw new Error((result.data && typeof result.data === 'string') ? result.data : 'Booking could not be completed.');
            })
            .catch(function (err) {
                showError(err.message || 'Something went wrong. Please try again.');
                setBusy(btn, false);
            });
    }

    function showStripePanel() {
        var panel = document.getElementById('dg-stripe-panel');
        var stripeBtn = document.getElementById('dg-stripe-submit');
        var payidBtn = document.getElementById('dg-payid-submit');
        if (payidBtn) {
            payidBtn.classList.remove('is-active');
        }
        if (stripeBtn) {
            stripeBtn.classList.add('is-active');
        }
        if (panel) {
            panel.hidden = false;
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function initStripe() {
        if (!cfg().stripeEnabled || !cfg().stripeKey || typeof Stripe === 'undefined') {
            return false;
        }
        var mount = document.getElementById('dg-stripe-card-element');
        if (!mount) {
            return false;
        }
        if (stripeInstance && cardElement) {
            return true;
        }

        stripeInstance = Stripe(cfg().stripeKey);
        var elements = stripeInstance.elements();
        cardElement = elements.create('card', {
            hidePostalCode: true,
            style: {
                base: {
                    color: '#2F2F2F',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    fontSize: '16px',
                    lineHeight: '24px',
                    '::placeholder': { color: '#9AA8A5' }
                },
                invalid: {
                    color: '#9b1c1c',
                    iconColor: '#9b1c1c'
                }
            }
        });
        cardElement.mount('#dg-stripe-card-element');
        cardElement.on('change', function (event) {
            var displayError = document.getElementById('dg-stripe-card-errors');
            if (displayError) {
                displayError.textContent = event.error ? event.error.message : '';
            }
        });
        return true;
    }

    function submitStripe(form, btn) {
        clearError();
        if (!validateForm(form)) return;
        if (!cfg().stripeEnabled) {
            showError('Card payments are not available. Please use PayID.');
            return;
        }

        showStripePanel();
        if (!initStripe()) {
            showError('Card payment could not load. Please refresh or use PayID.');
            return;
        }

        setBusy(btn, true, '⏳ Processing card…');

        var payload = bookingPayload(form);
        var restBase = cfg().restUrl || '/wp-json/dg-stripe/v1/';

        fetch(restBase + 'create-payment-intent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error || !data.clientSecret) {
                    throw new Error(data.error || 'Could not start card payment.');
                }
                return stripeInstance.confirmCardPayment(data.clientSecret, {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            name: payload.enquiry_name,
                            email: payload.enquiry_email
                        }
                    }
                }).then(function (result) {
                    return { result: result, booking_ref: data.booking_ref };
                });
            })
            .then(function (ctx) {
                if (ctx.result.error) {
                    throw new Error(ctx.result.error.message);
                }
                return fetch(restBase + 'confirm-booking', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payment_intent_id: ctx.result.paymentIntent.id })
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.error) throw new Error(data.error);
                    return data.booking_ref || ctx.booking_ref;
                });
            })
            .then(function (ref) {
                var base = cfg().confirmUrl || '/booking-confirmed/';
                window.location.href = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'ref=' + encodeURIComponent(ref) + '&payment_method=stripe';
            })
            .catch(function (err) {
                showError(err.message || 'Card payment failed.');
                setBusy(btn, false);
            });
    }

    function bindCheckoutForm() {
        var form = getForm();
        if (!form || form.dataset.dgBound === '1') {
            return;
        }
        form.dataset.dgBound = '1';

        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        var payidBtn = document.getElementById('dg-payid-submit');
        if (payidBtn) {
            payidBtn.addEventListener('click', function () {
                submitPayid(form, payidBtn);
            });
        }

        var stripeBtn = document.getElementById('dg-stripe-submit');
        if (stripeBtn) {
            stripeBtn.addEventListener('click', function () {
                showStripePanel();
                initStripe();
                submitStripe(form, stripeBtn);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', bindCheckoutForm);
    document.addEventListener('dg-booking-updated', bindCheckoutForm);
})();
