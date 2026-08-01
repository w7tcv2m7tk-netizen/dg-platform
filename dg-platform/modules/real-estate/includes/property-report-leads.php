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

    if (!$admin_sent) {
        return [
            'success' => false,
            'message' => 'Failed to send email. Please try again or call us directly.',
        ];
    }

    return [
        'success' => true,
        'message' => "Report request sent successfully! We'll be in touch within 2 hours.",
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
    $ajax_url = admin_url('admin-ajax.php');
    ob_start();
    ?>
    <div class="roe-property-report" style="max-width:640px;margin:0 auto;">
        <div id="roe-report-step-address" class="roe-report-card" style="background:#fff;border:1px solid #E0D6CC;border-radius:16px;padding:24px;">
            <h3 style="margin-top:0;color:#1C2B2A;">Get Your Free Property Report</h3>
            <p style="color:#6B7A78;">Value range, buyer demand, and comparable sales.</p>
            <form id="roePropertyReportAddressForm">
                <label for="roePropertyAddress" style="display:block;font-weight:600;margin-bottom:6px;">Property address</label>
                <input type="text" id="roePropertyAddress" name="propertyAddress" required placeholder="e.g. 123 Main Street, Currumbin QLD" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:16px;">
                <button type="submit" style="background:#C9A46C;color:#fff;border:none;padding:12px 20px;border-radius:999px;font-weight:600;cursor:pointer;">Get My Free Report</button>
            </form>
        </div>

        <div id="roe-report-step-contact" class="roe-report-card" style="display:none;background:#fff;border:1px solid #E0D6CC;border-radius:16px;padding:24px;margin-top:16px;">
            <h3 style="margin-top:0;color:#1C2B2A;">Almost there</h3>
            <p style="color:#6B7A78;">Where should we send your Property Value &amp; Buyer Demand Report?</p>
            <form id="roePropertyReportContactForm">
                <input type="text" name="website" tabindex="-1" autocomplete="off" style="display:none !important;" aria-hidden="true">
                <label for="roeReportFullName" style="display:block;font-weight:600;margin-bottom:6px;">Full name</label>
                <input type="text" id="roeReportFullName" name="fullName" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:12px;">
                <label for="roeReportEmail" style="display:block;font-weight:600;margin-bottom:6px;">Email</label>
                <input type="email" id="roeReportEmail" name="email" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:12px;">
                <label for="roeReportPhone" style="display:block;font-weight:600;margin-bottom:6px;">Mobile</label>
                <input type="tel" id="roeReportPhone" name="phone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:16px;">
                <button type="submit" id="roeReportSubmitBtn" style="background:#C9A46C;color:#fff;border:none;padding:12px 20px;border-radius:999px;font-weight:600;cursor:pointer;">Send My Report</button>
                <div id="roeReportStatus" style="margin-top:12px;"></div>
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
        let propertyAddress = '';

        if (!addressForm || !contactForm) return;

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

            if (!fullName) {
                statusEl.innerHTML = '<span style="color:#c62828;">Please enter your full name.</span>';
                return;
            }
            if (!email && !phone) {
                statusEl.innerHTML = '<span style="color:#c62828;">Please provide either an email or mobile number.</span>';
                return;
            }

            submitBtn.disabled = true;
            statusEl.innerHTML = 'Sending...';

            const payload = new URLSearchParams();
            payload.append('action', 'roe_realty_save_lead');
            payload.append('fullName', fullName);
            payload.append('email', email);
            payload.append('phone', phone);
            payload.append('propertyAddress', propertyAddress);

            try {
                const response = await fetch(<?php echo wp_json_encode($ajax_url); ?>, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: payload.toString()
                });
                const result = await response.json();
                const message = result.data && result.data.message ? result.data.message : (result.message || 'Something went wrong.');

                if (result.success) {
                    statusEl.innerHTML = '<span style="color:#2E7D32;">' + message + '</span>';
                    contactForm.reset();
                    setTimeout(function() {
                        stepContact.style.display = 'none';
                        stepAddress.style.display = 'block';
                        statusEl.innerHTML = '';
                        document.getElementById('roePropertyAddress').value = '';
                        submitBtn.disabled = false;
                    }, 3000);
                } else {
                    statusEl.innerHTML = '<span style="color:#c62828;">Error: ' + message + '</span>';
                    submitBtn.disabled = false;
                }
            } catch (error) {
                statusEl.innerHTML = '<span style="color:#c62828;">Network error. Please try again.</span>';
                submitBtn.disabled = false;
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
