<?php
/**
 * Property report lead capture for Roe Realty.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

function dg_re_process_property_report_lead($data) {
    global $wpdb;

    $full_name = sanitize_text_field($data['fullName'] ?? $data['full_name'] ?? '');
    $email = sanitize_email($data['email'] ?? '');
    $phone = sanitize_text_field($data['phone'] ?? '');
    $property_address = sanitize_text_field($data['propertyAddress'] ?? $data['property_address'] ?? '');
    $honeypot = sanitize_text_field($data['website'] ?? '');

    if (!empty($honeypot)) {
        return [
            'success' => true,
            'message' => 'Report request received.',
        ];
    }

    if ($full_name === '') {
        return [
            'success' => false,
            'message' => 'Full name is required.',
        ];
    }

    if ($email === '' && $phone === '') {
        return [
            'success' => false,
            'message' => 'Either email or phone number is required.',
        ];
    }

    $first_name = explode(' ', trim($full_name))[0];
    $headers = DG_RE_Email_Templates::mail_headers();
    $submitted_at = current_time('Y-m-d H:i:s');

    $admin_to = apply_filters('dg_re_property_report_admin_email', 'enquiries@roerealty.com.au');
    $admin_mail = DG_RE_Email_Templates::render('property_report_admin', [
        'full_name' => $full_name,
        'first_name' => $first_name,
        'property_address' => $property_address,
        'email' => $email !== '' ? $email : 'Not provided',
        'phone' => $phone !== '' ? $phone : 'Not provided',
        'submitted_at' => $submitted_at,
    ]);
    $admin_sent = wp_mail($admin_to, $admin_mail['subject'], $admin_mail['body'], $headers);

    if ($email !== '') {
        $lead_mail = DG_RE_Email_Templates::render('property_report_lead', [
            'full_name' => $full_name,
            'first_name' => $first_name,
            'property_address' => $property_address,
            'email' => $email,
            'phone' => $phone,
            'submitted_at' => $submitted_at,
        ]);
        wp_mail($email, $lead_mail['subject'], $lead_mail['body'], $headers);
    }

    dg_re_store_property_report_lead([
        'full_name' => $full_name,
        'first_name' => $first_name,
        'email' => $email,
        'phone' => $phone,
        'property_address' => $property_address,
    ]);

    $vendor_lead_id = null;
    if (class_exists('DG_RE_Vendor_Leads')) {
        $vendor_lead_id = DG_RE_Vendor_Leads::create([
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'property_address' => $property_address,
            'source' => 'property_report',
            'status' => 'new',
            'notes' => 'Submitted via Property Value & Buyer Demand Report form.',
        ]);
        if (is_wp_error($vendor_lead_id)) {
            $vendor_lead_id = null;
        }
    }

    if (class_exists('DG_Activities') && !$vendor_lead_id) {
        DG_Activities::log([
            'activity_type' => 'lead',
            'subject' => 'Property report requested',
            'content' => $property_address,
            'metadata' => [
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'source' => 'property_report',
            ],
        ]);
    }

    if (!$admin_sent && !$vendor_lead_id) {
        return [
            'success' => false,
            'message' => 'Failed to send email. Please try again or call us directly.',
        ];
    }

    $message = "Report request sent successfully! We'll be in touch within 2 hours.";
    if (!$admin_sent && $vendor_lead_id) {
        $message = "Report request received! We'll be in touch within 2 hours.";
    }

    return [
        'success' => true,
        'message' => $message,
        'vendor_lead_id' => $vendor_lead_id,
    ];
}

function dg_re_store_property_report_lead($lead) {
    global $wpdb;

    $leads_table = $wpdb->prefix . 'roe_realty_leads';
    $wpdb->insert($leads_table, [
        'full_name' => $lead['full_name'],
        'first_name' => $lead['first_name'],
        'email' => $lead['email'],
        'phone' => $lead['phone'],
        'property_address' => $lead['property_address'],
        'submitted_at' => current_time('mysql'),
    ]);
}

function roe_crm_property_report_form_shortcode() {
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = class_exists('DG_RE_Form_Security') ? DG_RE_Form_Security::nonce_field('property_report') : '';
    ob_start();
    ?>
    <div class="roe-property-report roe-form-wrap">
        <div id="roe-report-step-address" class="roe-report-card roe-form-card">
            <h3>Get Your Free Property Report</h3>
            <p class="roe-form-muted">Value range, buyer demand, and comparable sales.</p>
            <form id="roePropertyReportAddressForm">
                <div class="roe-form-field">
                    <label for="roePropertyAddress">Property address</label>
                    <input type="text" id="roePropertyAddress" name="propertyAddress" required placeholder="e.g. 123 Main Street, Currumbin QLD">
                </div>
                <button type="submit" class="roe-btn-primary">Get My Free Report</button>
            </form>
        </div>

        <div id="roe-report-step-contact" class="roe-report-card roe-form-card" style="display:none;">
            <h3>Almost there</h3>
            <p class="roe-form-muted">Where should we send your Property Value &amp; Buyer Demand Report?</p>
            <form id="roePropertyReportContactForm">
                <div class="roe-honeypot" aria-hidden="true">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="roe-form-field">
                    <label for="roeReportFullName">Full name</label>
                    <input type="text" id="roeReportFullName" name="fullName" required>
                </div>
                <div class="roe-form-field">
                    <label for="roeReportEmail">Email</label>
                    <input type="email" id="roeReportEmail" name="email">
                </div>
                <div class="roe-form-field">
                    <label for="roeReportPhone">Mobile</label>
                    <input type="tel" id="roeReportPhone" name="phone">
                </div>
                <button type="submit" id="roeReportSubmitBtn" class="roe-btn-primary">Send My Report</button>
                <div id="roeReportStatus" class="roe-form-status" role="status" aria-live="polite"></div>
            </form>
        </div>
    </div>
    <script>
    (function() {
        const addressForm = document.getElementById('roePropertyReportAddressForm');
        const contactForm = document.getElementById('roePropertyReportContactForm');
        const stepAddress = document.getElementById('roe-report-step-address');
        const stepContact = document.getElementById('roe-report-step-contact');
        const statusEl = document.getElementById('roeReportStatus');
        const submitBtn = document.getElementById('roeReportSubmitBtn');
        const ajaxNonce = <?php echo wp_json_encode($nonce); ?>;
        let propertyAddress = '';

        if (!addressForm || !contactForm) return;

        function showStatus(msg, type) {
            statusEl.textContent = msg;
            statusEl.className = 'roe-form-status is-visible is-' + type;
        }

        addressForm.addEventListener('submit', function(e) {
            e.preventDefault();
            propertyAddress = document.getElementById('roePropertyAddress').value.trim();
            if (!propertyAddress) return;
            stepAddress.style.display = 'none';
            stepContact.style.display = 'block';
        });

        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fullName = document.getElementById('roeReportFullName').value.trim();
            const email = document.getElementById('roeReportEmail').value.trim();
            const phone = document.getElementById('roeReportPhone').value.trim();
            const honeypot = contactForm.querySelector('[name="website"]');

            if (!fullName) {
                showStatus('Please enter your full name.', 'error');
                return;
            }
            if (!email && !phone) {
                showStatus('Please provide either an email or mobile number.', 'error');
                return;
            }

            submitBtn.disabled = true;
            showStatus('Sending...', 'loading');

            const nonce = (window.dgReForms && window.dgReForms.getNonce)
                ? await window.dgReForms.getNonce('property_report', ajaxNonce)
                : ajaxNonce;

            const payload = new URLSearchParams();
            payload.append('action', 'roe_realty_save_lead');
            payload.append('dg_re_nonce', nonce);
            payload.append('fullName', fullName);
            payload.append('email', email);
            payload.append('phone', phone);
            payload.append('propertyAddress', propertyAddress);
            if (honeypot) payload.append('website', honeypot.value);

            try {
                const response = await fetch(<?php echo wp_json_encode($ajax_url); ?>, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: payload.toString()
                });
                const result = await response.json();
                const message = result.data && result.data.message ? result.data.message : (result.message || 'Something went wrong.');

                if (result.success) {
                    showStatus(message, 'success');
                    contactForm.reset();
                    setTimeout(function() {
                        stepContact.style.display = 'none';
                        stepAddress.style.display = 'block';
                        statusEl.className = 'roe-form-status';
                        statusEl.textContent = '';
                        document.getElementById('roePropertyAddress').value = '';
                        submitBtn.disabled = false;
                    }, 3000);
                } else {
                    showStatus(message, 'error');
                    submitBtn.disabled = false;
                }
            } catch (error) {
                showStatus('Network error. Please try again.', 'error');
                submitBtn.disabled = false;
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
