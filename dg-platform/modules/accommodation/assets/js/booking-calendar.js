(function () {
    function isSaturday(date) {
        return date.getDay() === 6;
    }

    /** Local date YYYY-MM-DD (avoids UTC shift on AU dates). */
    function fmtDate(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1);
        var d = String(date.getDate());
        if (m.length < 2) m = '0' + m;
        if (d.length < 2) d = '0' + d;
        return y + '-' + m + '-' + d;
    }

    function fmtDisplay(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr + 'T12:00:00');
        return d.toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function parseLocalDate(dateStr) {
        return new Date(dateStr + 'T12:00:00');
    }

    function isPeakSeason(dateStr, peakStart, peakEnd) {
        var md = dateStr.slice(5);
        if (peakStart <= peakEnd) {
            return md >= peakStart && md <= peakEnd;
        }
        return md >= peakStart || md <= peakEnd;
    }

    function calcTotal(config, checkin, checkout) {
        var start = parseLocalDate(checkin);
        var end = parseLocalDate(checkout);
        if (end <= start) return { nights: 0, subtotal: 0, total: 0 };

        var subtotal = 0;
        var nights = 0;
        var current = new Date(start);
        while (current < end) {
            nights++;
            var dow = current.getDay();
            var isWeekend = dow === 5 || dow === 6 || dow === 0;
            var inPeak = isPeakSeason(fmtDate(current), config.peakStart, config.peakEnd);
            if (isWeekend) {
                subtotal += inPeak ? config.weekendPeak : config.weekendRate;
            } else {
                subtotal += inPeak ? config.weekdayPeak : config.weekdayRate;
            }
            current.setDate(current.getDate() + 1);
        }
        return { nights: nights, subtotal: subtotal, total: subtotal + config.cleaningFee };
    }

    function setField(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function dispatchBookingUpdate(detail) {
        document.dispatchEvent(new CustomEvent('dg-booking-updated', { detail: detail }));
    }

    function updateGlobalSummary(detail) {
        var panel = document.getElementById('dg-booking-summary-panel');
        if (!panel) return;

        var empty = panel.querySelector('[data-dg-summary-empty]');
        var content = panel.querySelector('[data-dg-summary-content]');
        var warn = panel.querySelector('[data-dg-summary-saturday]');

        if (!detail || !detail.checkin || !detail.checkout || detail.nights < 1) {
            if (empty) empty.style.display = 'block';
            if (content) content.style.display = 'none';
            if (warn) warn.style.display = 'none';
            return;
        }

        if (empty) empty.style.display = 'none';
        if (content) content.style.display = 'block';
        if (warn) warn.style.display = 'none';

        var map = {
            '[data-dg-summary-checkin]': fmtDisplay(detail.checkin),
            '[data-dg-summary-checkout]': fmtDisplay(detail.checkout),
            '[data-dg-summary-nights]': String(detail.nights),
            '[data-dg-summary-subtotal]': '$' + detail.subtotal.toFixed(2),
            '[data-dg-summary-total]': '$' + detail.total.toFixed(2),
            '[data-dg-summary-property]': detail.propertyName || ''
        };
        Object.keys(map).forEach(function (sel) {
            var el = panel.querySelector(sel);
            if (el) el.textContent = map[sel];
        });
    }

    function initCalendar(root) {
        var calendarEl = root.querySelector('.dg-booking-calendar');
        if (!calendarEl || typeof FullCalendar === 'undefined') return;

        var config = JSON.parse(root.getAttribute('data-config') || '{}');
        var blockedDates = config.blockedDates || [];
        var today = new Date();
        today.setHours(0, 0, 0, 0);

        var summary = root.querySelector('.dg-booking-summary');
        var cta = root.querySelector('.dg-calendar-cta');
        var statusEl = root.querySelector('[data-dg-cal-status]');
        var hintEl = root.querySelector('[data-dg-cal-hint]');

        var rangeStart = null;
        var rangeEndExclusive = null;
        var pendingCheckin = null;

        function setStatus(text) {
            if (statusEl) statusEl.textContent = text || '';
        }

        function setHint(text) {
            if (hintEl) hintEl.textContent = text || 'Tap your check-in date, then your check-out date';
        }

        function isDateBlocked(date) {
            return blockedDates.indexOf(fmtDate(date)) !== -1;
        }

        function isPast(date) {
            var d = new Date(date);
            d.setHours(0, 0, 0, 0);
            return d < today;
        }

        function isValidCheckin(date) {
            if (isPast(date) || isSaturday(date) || isDateBlocked(date)) {
                return false;
            }
            return true;
        }

        function isValidCheckout(endExclusive, start) {
            if (!start || !endExclusive || endExclusive <= start) {
                return false;
            }
            if (isSaturday(endExclusive)) {
                return false;
            }
            var cursor = new Date(start);
            while (cursor < endExclusive) {
                if (isDateBlocked(cursor)) {
                    return false;
                }
                cursor.setDate(cursor.getDate() + 1);
            }
            var nights = Math.round((endExclusive - start) / 86400000);
            if (nights < 1) {
                return false;
            }
            if (start.getDay() === 5 && nights < 2) {
                return false;
            }
            return true;
        }

        function clearSelection() {
            rangeStart = null;
            rangeEndExclusive = null;
            pendingCheckin = null;
            setStatus('');
            setHint('Tap your check-in date, then your check-out date');
            if (summary) summary.style.display = 'none';
            calendar.render();
        }

        function updateInlineSummary(start, endExclusive, quote) {
            if (summary) {
                summary.style.display = 'block';
                var checkinEl = summary.querySelector('[data-dg-checkin]');
                var checkoutEl = summary.querySelector('[data-dg-checkout]');
                var nightsEl = summary.querySelector('[data-dg-nights]');
                var totalEl = summary.querySelector('[data-dg-total]');
                if (checkinEl) checkinEl.textContent = fmtDisplay(fmtDate(start));
                if (checkoutEl) checkoutEl.textContent = fmtDisplay(fmtDate(endExclusive));
                if (nightsEl) nightsEl.textContent = String(quote.nights);
                if (totalEl) totalEl.textContent = '$' + quote.total.toFixed(2);
            }

            var checkin = fmtDate(start);
            var checkout = fmtDate(endExclusive);
            dispatchBookingUpdate({
                checkin: checkin,
                checkout: checkout,
                nights: quote.nights,
                subtotal: quote.subtotal,
                total: quote.total,
                propertyName: config.propertyName || ''
            });
            updateGlobalSummary({
                checkin: checkin,
                checkout: checkout,
                nights: quote.nights,
                subtotal: quote.subtotal,
                total: quote.total,
                propertyName: config.propertyName || ''
            });
        }

        function applyInlineBooking(start, endExclusive, quote) {
            var checkin = fmtDate(start);
            var checkout = fmtDate(endExclusive);
            setField('dg-accommodation-id', String(config.accommodationId));
            setField('dg-booking-checkin', checkin);
            setField('dg-booking-checkout', checkout);
            setField('booking_checkin', checkin);
            setField('booking_checkout', checkout);
            setField('dg-booking-nights', String(quote.nights));
            setField('dg-booking-total', quote.total.toFixed(2));

            var inlineSummary = document.getElementById('dg-enquiry-date-summary');
            if (inlineSummary) {
                inlineSummary.style.display = 'block';
                inlineSummary.innerHTML = '<p style="margin:0 0 8px;font-size:0.9rem;">📅 <strong>' +
                    fmtDisplay(checkin) + '</strong> → <strong>' + fmtDisplay(checkout) + '</strong></p>' +
                    '<p style="margin:0;font-size:0.9rem;">' + quote.nights + ' night' + (quote.nights > 1 ? 's' : '') +
                    ' · Subtotal $' + quote.subtotal.toFixed(2) +
                    (config.cleaningFee ? ' · Cleaning $' + config.cleaningFee.toFixed(2) : '') +
                    ' · <strong>Total $' + quote.total.toFixed(2) + '</strong></p>';
            }

            var checkoutForm = document.querySelector('#dg-booking-form .dg-checkout-form');
            if (checkoutForm) {
                checkoutForm.classList.remove('is-disabled');
                checkoutForm.style.opacity = '1';
                checkoutForm.style.pointerEvents = 'auto';
            }
        }

        function applySelection(start, endExclusive) {
            rangeStart = new Date(start);
            rangeEndExclusive = new Date(endExclusive);
            pendingCheckin = null;

            var checkin = fmtDate(start);
            var checkout = fmtDate(endExclusive);
            var quote = calcTotal(config, checkin, checkout);

            setStatus('Selected ' + fmtDisplay(checkin) + ' → ' + fmtDisplay(checkout) + ' (' + quote.nights + ' night' + (quote.nights > 1 ? 's' : '') + ')');
            setHint('Tap any date to change your selection');
            updateInlineSummary(start, endExclusive, quote);
            calendar.render();

            if (config.mode === 'inline') {
                applyInlineBooking(start, endExclusive, quote);
                return;
            }

            var url = config.bookUrl;
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'checkin=' + checkin + '&checkout=' + checkout;
            if (confirm('Book ' + quote.nights + ' night' + (quote.nights > 1 ? 's' : '') + '?\n\nCheck-in: ' + fmtDisplay(checkin) + '\nCheck-out: ' + fmtDisplay(checkout) + '\nTotal: $' + quote.total.toFixed(2))) {
                window.location.href = url;
            }
        }

        function handleDateClick(clickedDate) {
            var clicked = new Date(clickedDate);
            clicked.setHours(0, 0, 0, 0);

            if (isPast(clicked)) {
                return;
            }

            if (!pendingCheckin) {
                if (!isValidCheckin(clicked)) {
                    if (isSaturday(clicked)) {
                        alert('Check-in is not available on Saturdays. Please choose another date.');
                    } else if (isDateBlocked(clicked)) {
                        alert('This date is unavailable.');
                    }
                    return;
                }

                pendingCheckin = clicked;
                rangeStart = clicked;
                rangeEndExclusive = null;
                setStatus('Check-in: ' + fmtDisplay(fmtDate(clicked)) + ' — now tap check-out');
                setHint('Tap your check-out date');
                calendar.render();
                return;
            }

            var start = pendingCheckin;
            var endExclusive = clicked;

            if (endExclusive.getTime() === start.getTime()) {
                clearSelection();
                return;
            }

            if (endExclusive < start) {
                if (!isValidCheckin(endExclusive)) {
                    if (isSaturday(endExclusive)) {
                        alert('Check-in is not available on Saturdays.');
                    } else if (isDateBlocked(endExclusive)) {
                        alert('This date is unavailable.');
                    }
                    return;
                }
                pendingCheckin = endExclusive;
                rangeStart = endExclusive;
                rangeEndExclusive = null;
                setStatus('Check-in: ' + fmtDisplay(fmtDate(endExclusive)) + ' — now tap check-out');
                setHint('Tap your check-out date');
                calendar.render();
                return;
            }

            if (!isValidCheckout(endExclusive, start)) {
                if (isSaturday(endExclusive)) {
                    alert('Check-out is not available on Saturdays.');
                } else {
                    var nights = Math.round((endExclusive - start) / 86400000);
                    if (start.getDay() === 5 && nights < 2) {
                        alert('Friday check-ins require a minimum 2-night stay.');
                    } else {
                        alert('Some selected dates are unavailable.');
                    }
                }
                pendingCheckin = start;
                rangeStart = start;
                rangeEndExclusive = null;
                calendar.render();
                return;
            }

            applySelection(start, endExclusive);
        }

        var events = [];
        blockedDates.forEach(function (d) {
            events.push({ start: d, display: 'background', color: '#dc3545' });
        });

        var d = new Date(today);
        var horizon = new Date(today);
        horizon.setFullYear(horizon.getFullYear() + 1);
        while (d <= horizon) {
            if (isSaturday(d)) {
                events.push({ start: fmtDate(d), display: 'background', color: '#fff3cd' });
            }
            d.setDate(d.getDate() + 1);
        }

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth'
            },
            height: 'auto',
            fixedWeekCount: false,
            events: events,
            dayCellClassNames: function (arg) {
                var classes = [];
                var cellDate = new Date(arg.date);
                cellDate.setHours(0, 0, 0, 0);
                var cellTime = cellDate.getTime();

                if (isPast(cellDate)) {
                    classes.push('dg-cal-past');
                }
                if (isSaturday(cellDate)) {
                    classes.push('dg-cal-saturday');
                }
                if (isDateBlocked(cellDate)) {
                    classes.push('dg-cal-blocked');
                }

                if (pendingCheckin && cellTime === pendingCheckin.getTime()) {
                    classes.push('dg-cal-checkin-pending');
                }

                if (rangeStart && rangeEndExclusive) {
                    if (cellTime === rangeStart.getTime()) {
                        classes.push('dg-cal-range-start');
                    } else if (cellTime === rangeEndExclusive.getTime()) {
                        classes.push('dg-cal-range-end');
                    } else if (cellTime > rangeStart.getTime() && cellTime < rangeEndExclusive.getTime()) {
                        classes.push('dg-cal-in-range');
                    }
                } else if (rangeStart && !rangeEndExclusive && pendingCheckin && cellTime === rangeStart.getTime()) {
                    classes.push('dg-cal-checkin-pending');
                }

                return classes;
            },
            dateClick: function (info) {
                handleDateClick(info.date);
            }
        });

        calendar.render();

        var clearBtn = root.querySelector('[data-dg-cal-clear]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                clearSelection();
            });
        }

        if (cta) {
            cta.addEventListener('click', function (e) {
                if (config.mode === 'inline') {
                    e.preventDefault();
                    if (!rangeStart || !rangeEndExclusive) {
                        alert('Please select check-in and check-out dates on the calendar first.');
                        return;
                    }
                    var checkoutPanel = document.getElementById('dg-book-now-checkout') || document.getElementById('dg-booking-form');
                    if (checkoutPanel) checkoutPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        if (config.checkin && config.checkout) {
            var preStart = parseLocalDate(config.checkin);
            var preEnd = parseLocalDate(config.checkout);
            if (isValidCheckin(preStart) && isValidCheckout(preEnd, preStart)) {
                applySelection(preStart, preEnd);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.dg-booking-calendar-wrap').forEach(initCalendar);
        document.querySelectorAll('.dg-legacy-booking-summary').forEach(function (el) {
            el.style.display = 'none';
        });
    });
})();
