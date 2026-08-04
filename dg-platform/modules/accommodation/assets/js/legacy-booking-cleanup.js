(function () {
    'use strict';

    var NEEDLES = [
        'No Booking Details Found',
        'No booking details found',
        'check-in or check-out on a Saturday'
    ];

    var KEEP_SELECTORS = [
        '#dg-booking-summary-panel',
        '#dg-booking-form',
        '.dg-booking-summary-panel',
        '.dg-checkout-form',
        '.dg-single-wrapper',
        '.dg-book-now-page'
    ];

    function shouldKeep(el) {
        return KEEP_SELECTORS.some(function (sel) {
            return el.closest(sel);
        });
    }

    function matchesLegacy(text) {
        if (!text) return false;
        return NEEDLES.some(function (n) {
            return text.indexOf(n) !== -1;
        });
    }

    function hideLegacyBlocks() {
        var candidates = document.querySelectorAll('div, section, article, aside, p, .ct-div-block, .oxy-stock-content-code');
        candidates.forEach(function (el) {
            if (shouldKeep(el)) return;
            var text = (el.textContent || '').trim();
            if (!matchesLegacy(text)) return;
            var block = el;
            if (text.length > 400 && el.querySelector('div, section')) {
                var inner = Array.prototype.slice.call(el.querySelectorAll('div, section, p')).find(function (node) {
                    return matchesLegacy((node.textContent || '').trim()) && (node.textContent || '').trim().length < 400;
                });
                if (inner) block = inner;
            }
            block.style.setProperty('display', 'none', 'important');
            block.setAttribute('data-dg-legacy-hidden', '1');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideLegacyBlocks);
    } else {
        hideLegacyBlocks();
    }
    setTimeout(hideLegacyBlocks, 500);
})();
