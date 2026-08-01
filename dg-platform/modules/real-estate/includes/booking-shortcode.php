<?php
/**
 * Booking form shortcode for /property-appraisal, /strategy-call, and /card/.
 *
 * Usage: [roe_crm_booking_form service="strategy-call"]
 *        [roe_crm_booking_form service="property-appraisal"]
 *        [roe_crm_booking_form] — shows service picker
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

function roe_crm_booking_form_shortcode($atts = []) {
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    $atts = shortcode_atts([
        'service' => '',
        'title' => '',
        'submit' => 'Book Appointment →',
    ], $atts, 'roe_crm_booking_form');

    $booking = new Roe_CRM_Booking();
    $services = $booking->get_services();
    $selected = null;

    if ($atts['service'] !== '') {
        $selected = $booking->get_service_by_slug(sanitize_title($atts['service']));
        if (!$selected && is_numeric($atts['service'])) {
            global $wpdb;
            $selected = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}roe_crm_services WHERE id = %d AND is_active = 1",
                (int) $atts['service']
            ));
        }
    }

    $ajax_url = admin_url('admin-ajax.php');
    $nonce = class_exists('DG_RE_Form_Security') ? DG_RE_Form_Security::nonce_field('create_booking') : '';
    $slots_nonce = class_exists('DG_RE_Form_Security') ? DG_RE_Form_Security::nonce_field('booking_slots') : '';
    $title = $atts['title'] !== '' ? $atts['title'] : ($selected ? $selected->name : 'Book an Appointment');
    $show_picker = !$selected && count($services) > 1;

    ob_start();
    ?>
    <div class="roe-booking-form-wrap roe-form-wrap">
        <h3><?php echo esc_html($title); ?></h3>
        <form id="roeCrmBookingForm" class="roe-booking-form">
            <div class="roe-honeypot" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <?php if ($show_picker) : ?>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-weight:600;margin-bottom:0.4rem;">Appointment type *</label>
                <select id="roeBookingService" name="service" required style="width:100%;padding:0.7rem;border:1px solid #ddd;border-radius:8px;">
                    <option value="">Select...</option>
                    <?php foreach ($services as $svc) : ?>
                        <option value="<?php echo (int) $svc->id; ?>"
                            data-name="<?php echo esc_attr($svc->name); ?>"
                            data-slug="<?php echo esc_attr($svc->slug); ?>"
                            data-duration="<?php echo (int) $svc->duration; ?>">
                            <?php echo esc_html($svc->name); ?> (<?php echo (int) $svc->duration; ?> min)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else : ?>
                <input type="hidden" id="roeBookingService" name="service" value="<?php echo (int) ($selected->id ?? ($services[0]->id ?? 1)); ?>"
                    data-name="<?php echo esc_attr($selected->name ?? ($services[0]->name ?? 'Appointment')); ?>"
                    data-slug="<?php echo esc_attr($selected->slug ?? ''); ?>"
                    data-duration="<?php echo (int) ($selected->duration ?? 30); ?>">
            <?php endif; ?>

            <div style="margin-bottom:1rem;">
                <label style="display:block;font-weight:600;margin-bottom:0.4rem;">Full Name *</label>
                <input type="text" id="roeBookingName" required style="width:100%;padding:0.7rem;border:1px solid #ddd;border-radius:8px;">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-weight:600;margin-bottom:0.4rem;">Email *</label>
                <input type="email" id="roeBookingEmail" required style="width:100%;padding:0.7rem;border:1px solid #ddd;border-radius:8px;">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-weight:600;margin-bottom:0.4rem;">Phone</label>
                <input type="tel" id="roeBookingPhone" style="width:100%;padding:0.7rem;border:1px solid #ddd;border-radius:8px;">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-weight:600;margin-bottom:0.4rem;">Date *</label>
                <input type="date" id="roeBookingDate" required min="<?php echo esc_attr(date('Y-m-d')); ?>" style="width:100%;padding:0.7rem;border:1px solid #ddd;border-radius:8px;">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-weight:600;margin-bottom:0.4rem;">Available Times *</label>
                <div id="roeTimeSlots" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(85px,1fr));gap:8px;min-height:48px;">
                    <div style="grid-column:1/-1;color:#999;text-align:center;padding:12px 0;">Select a date first</div>
                </div>
                <input type="hidden" id="roeBookingTime" value="">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-weight:600;margin-bottom:0.4rem;">Notes</label>
                <textarea id="roeBookingNotes" rows="2" style="width:100%;padding:0.7rem;border:1px solid #ddd;border-radius:8px;"></textarea>
            </div>
            <div id="roeBookingStatus" class="roe-form-status" role="status" aria-live="polite"></div>
            <button type="submit" id="roeBookingSubmit" class="roe-btn-primary">
                <?php echo esc_html($atts['submit']); ?>
            </button>
        </form>
    </div>
    <script>
    (function() {
        const form = document.getElementById('roeCrmBookingForm');
        if (!form) return;
        const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        const ajaxNonce = <?php echo wp_json_encode($nonce); ?>;
        const slotsNonce = <?php echo wp_json_encode($slots_nonce); ?>;
        const serviceEl = document.getElementById('roeBookingService');
        const dateEl = document.getElementById('roeBookingDate');
        const slotsEl = document.getElementById('roeTimeSlots');
        const timeEl = document.getElementById('roeBookingTime');
        const statusEl = document.getElementById('roeBookingStatus');
        const submitBtn = document.getElementById('roeBookingSubmit');

        function serviceMeta() {
            if (serviceEl.tagName === 'SELECT') {
                const opt = serviceEl.options[serviceEl.selectedIndex];
                return {
                    id: serviceEl.value,
                    name: opt ? opt.dataset.name : '',
                    slug: opt ? opt.dataset.slug : '',
                    duration: opt ? opt.dataset.duration : '30'
                };
            }
            return {
                id: serviceEl.value,
                name: serviceEl.dataset.name,
                slug: serviceEl.dataset.slug,
                duration: serviceEl.dataset.duration || '30'
            };
        }

        function bookingTypeFromSlug(slug) {
            return (slug || 'appointment').replace(/-/g, '_');
        }

        function showStatus(msg, ok) {
            statusEl.textContent = msg;
            statusEl.className = 'roe-form-status is-visible is-' + (ok ? 'success' : 'error');
        }

        async function loadSlots() {
            const meta = serviceMeta();
            const date = dateEl.value;
            timeEl.value = '';
            if (!meta.id || !date) {
                slotsEl.innerHTML = '<div class="roe-slot-empty">Select a service and date</div>';
                return;
            }
            slotsEl.innerHTML = '<div class="roe-slot-empty">Loading...</div>';
            const slotNonce = (window.dgReForms && window.dgReForms.getNonce)
                ? await window.dgReForms.getNonce('booking_slots', slotsNonce)
                : slotsNonce;
            const body = new URLSearchParams();
            body.append('action', 'roe_crm_get_available_slots');
            body.append('dg_re_nonce', slotNonce);
            body.append('service_id', meta.id);
            body.append('date', date);
            const res = await fetch(ajaxUrl, { method: 'POST', body: body.toString() });
            const json = await res.json();
            const slots = (json.data && json.data.slots) ? json.data.slots : [];
            if (!slots.length) {
                slotsEl.innerHTML = '<div class="roe-slot-empty">No slots available</div>';
                return;
            }
            slotsEl.innerHTML = '';
            slotsEl.className = 'roe-time-slots';
            slots.forEach(function(slot) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'roe-slot-btn';
                btn.setAttribute('aria-pressed', 'false');
                btn.textContent = new Date('1970-01-01T' + slot).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                btn.dataset.time = slot;
                btn.addEventListener('click', function() {
                    slotsEl.querySelectorAll('.roe-slot-btn').forEach(b => {
                        b.classList.remove('is-selected');
                        b.setAttribute('aria-pressed', 'false');
                    });
                    btn.classList.add('is-selected');
                    btn.setAttribute('aria-pressed', 'true');
                    timeEl.value = slot;
                });
                slotsEl.appendChild(btn);
            });
        }

        dateEl.addEventListener('change', loadSlots);
        if (serviceEl.tagName === 'SELECT') serviceEl.addEventListener('change', loadSlots);

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const meta = serviceMeta();
            if (!timeEl.value) {
                showStatus('Please select a time slot.', false);
                return;
            }
            submitBtn.disabled = true;
            showStatus('Booking...', true);
            const honeypot = form.querySelector('[name="website"]');
            const bookNonce = (window.dgReForms && window.dgReForms.getNonce)
                ? await window.dgReForms.getNonce('create_booking', ajaxNonce)
                : ajaxNonce;
            const body = new URLSearchParams();
            body.append('action', 'roe_crm_create_booking');
            body.append('dg_re_nonce', bookNonce);
            body.append('name', document.getElementById('roeBookingName').value.trim());
            body.append('email', document.getElementById('roeBookingEmail').value.trim());
            body.append('phone', document.getElementById('roeBookingPhone').value.trim());
            body.append('service', meta.id);
            body.append('service_name', meta.name);
            body.append('booking_type', bookingTypeFromSlug(meta.slug));
            body.append('date', dateEl.value);
            body.append('time', timeEl.value);
            body.append('duration', meta.duration);
            body.append('notes', document.getElementById('roeBookingNotes').value.trim());
            if (honeypot) body.append('website', honeypot.value);
            const res = await fetch(ajaxUrl, { method: 'POST', body: body.toString() });
            const json = await res.json();
            submitBtn.disabled = false;
            if (json.success) {
                showStatus(json.data.message || 'Booked!', true);
                form.reset();
                timeEl.value = '';
                slotsEl.innerHTML = '<div class="roe-slot-empty">Select a date first</div>';
            } else {
                showStatus(json.data && json.data.message ? json.data.message : 'Booking failed.', false);
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
