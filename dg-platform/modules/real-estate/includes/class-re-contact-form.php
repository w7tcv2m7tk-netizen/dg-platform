<?php
/**
 * Roe Realty general contact form shortcode + AJAX handler.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

function dg_re_process_contact_enquiry($data) {
    $name = sanitize_text_field($data['name'] ?? $data['full_name'] ?? '');
    $email = sanitize_email($data['email'] ?? '');
    $phone = sanitize_text_field($data['phone'] ?? '');
    $subject = sanitize_text_field($data['subject'] ?? 'General enquiry');
    $message = sanitize_textarea_field($data['message'] ?? '');
    $recipient = sanitize_email($data['recipient'] ?? '') ?: get_option('admin_email');

    if ($name === '' || $email === '' || $message === '') {
        return [
            'success' => false,
            'message' => 'Name, email, and message are required.',
        ];
    }

    if (!is_email($email)) {
        return [
            'success' => false,
            'message' => 'Please enter a valid email address.',
        ];
    }

    $site_name = class_exists('DG_Site_Profile') ? DG_Site_Profile::label() : get_bloginfo('name');
    $admin_rows = [
        'Name' => $name,
        'Email' => $email,
        'Phone' => $phone ?: 'Not provided',
        'Subject' => $subject,
        'Message' => $message,
    ];

    if (class_exists('DG_Email_Brand')) {
        $admin_html = DG_Email_Brand::admin_notification('Contact form enquiry', $admin_rows, [
            'theme' => 'roe',
            'footer_note' => 'Website contact form — Roe Realty',
        ]);
        $headers = array_merge(DG_Email_Brand::mail_headers(true), [
            'Reply-To: ' . $name . ' <' . $email . '>',
        ]);
        $sent = wp_mail($recipient, 'Contact form: ' . $name, $admin_html, $headers);

        $first_name = class_exists('DG_Email_Names') ? DG_Email_Names::first_name($name) : $name;
        $guest_inner = '<p style="margin:0 0 14px;line-height:1.6;">Dear ' . esc_html($first_name) . ',</p>'
            . '<p style="margin:0 0 14px;line-height:1.6;">Thank you for contacting ' . esc_html($site_name) . '.</p>'
            . '<p style="margin:0 0 14px;line-height:1.6;">We have received your message and will respond shortly.</p>'
            . '<p style="margin:0 0 8px;color:#6B7A78;"><strong>Your message:</strong></p>'
            . '<p style="margin:0 0 14px;line-height:1.6;">' . nl2br(esc_html($message)) . '</p>'
            . '<p style="margin:0;line-height:1.6;">Warm regards,<br>' . esc_html($site_name) . ' Team</p>';
        $guest_html = DG_Email_Brand::wrap($guest_inner, [
            'theme' => 'roe',
            'footer_note' => 'Roe Realty — Currumbin & Southern Gold Coast',
        ]);
        wp_mail($email, 'Thank you for contacting ' . $site_name, $guest_html, array_merge(
            DG_Email_Brand::mail_headers(true),
            ['From: ' . $site_name . ' <' . $recipient . '>']
        ));
    } else {
        $admin_body = "Name: $name\nEmail: $email\nPhone: " . ($phone ?: 'Not provided') . "\nSubject: $subject\n\nMessage:\n$message";
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $name . ' <' . $email . '>',
        ];
        $sent = wp_mail($recipient, 'Contact form: ' . $name, $admin_body, $headers);
        $first_name = class_exists('DG_Email_Names') ? DG_Email_Names::first_name($name) : $name;
        $guest_body = "Dear $first_name,\n\nThank you for contacting $site_name.\n\nWe have received your message and will respond shortly.\n\nYour message:\n$message\n\nWarm regards,\n$site_name Team";
        wp_mail($email, 'Thank you for contacting ' . $site_name, $guest_body, [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . $recipient . '>',
        ]);
    }

    $admin_body = "Name: $name\nEmail: $email\nPhone: " . ($phone ?: 'Not provided') . "\nSubject: $subject\n\nMessage:\n$message";

    if (class_exists('DG_RE_Buyer_Leads')) {
        $buyer_id = DG_RE_Buyer_Leads::create([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $subject . "\n\n" . $message,
            'property_address' => '',
            'source' => 'website_contact',
            'status' => 'new',
        ]);
        if (is_wp_error($buyer_id)) {
            if (!$sent) {
                return [
                    'success' => false,
                    'message' => $buyer_id->get_error_message(),
                ];
            }
        }
    }

    if (class_exists('DG_Activities')) {
        DG_Activities::log([
            'activity_type' => 'note',
            'subject' => 'Website contact form',
            'content' => $admin_body,
            'metadata' => ['source' => 'roe_contact_form', 'email' => $email],
        ]);
    }

    if (!$sent) {
        return [
            'success' => false,
            'message' => 'Could not send email. Please call us directly.',
        ];
    }

    return [
        'success' => true,
        'message' => "Thank you, $name. We'll be in touch shortly.",
    ];
}

function roe_contact_form_shortcode($atts = []) {
    $atts = shortcode_atts([
        'title' => 'Get in Touch',
        'subtitle' => "Tell us how we can help with your property goals.",
        'recipient' => get_option('admin_email'),
        'button_text' => 'Send Message',
    ], $atts, 'roe_contact_form');

    $form_id = 'roe-contact-form-' . wp_unique_id();

    ob_start();
    ?>
    <div class="roe-form-wrap roe-contact-form-wrap" id="<?php echo esc_attr($form_id); ?>">
        <div class="roe-form-card">
            <div class="roe-contact-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <?php if ($atts['subtitle']) : ?>
                    <p><?php echo esc_html($atts['subtitle']); ?></p>
                <?php endif; ?>
            </div>

            <div class="roe-contact-success" style="display:none;"></div>
            <div class="roe-contact-error" style="display:none;"></div>

            <form class="roe-contact-fields" novalidate>
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="roe-honeypot">

                <div class="roe-contact-row">
                    <div class="roe-form-field">
                        <label for="<?php echo esc_attr($form_id); ?>-name">Full Name *</label>
                        <input type="text" id="<?php echo esc_attr($form_id); ?>-name" name="name" required placeholder="Your name">
                    </div>
                    <div class="roe-form-field">
                        <label for="<?php echo esc_attr($form_id); ?>-phone">Phone</label>
                        <input type="tel" id="<?php echo esc_attr($form_id); ?>-phone" name="phone" placeholder="Your phone number">
                    </div>
                </div>

                <div class="roe-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-email">Email *</label>
                    <input type="email" id="<?php echo esc_attr($form_id); ?>-email" name="email" required placeholder="you@example.com">
                </div>

                <div class="roe-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-subject">Subject</label>
                    <input type="text" id="<?php echo esc_attr($form_id); ?>-subject" name="subject" placeholder="Buying, selling, appraisal…">
                </div>

                <div class="roe-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-message">Message *</label>
                    <textarea id="<?php echo esc_attr($form_id); ?>-message" name="message" rows="5" required placeholder="How can Roe Realty help you?"></textarea>
                </div>

                <button type="submit" class="roe-btn-primary roe-contact-submit"><?php echo esc_html($atts['button_text']); ?></button>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var wrap = document.getElementById(<?php echo wp_json_encode($form_id); ?>);
        if (!wrap) return;
        var form = wrap.querySelector('.roe-contact-fields');
        var btn = wrap.querySelector('.roe-contact-submit');
        var successEl = wrap.querySelector('.roe-contact-success');
        var errorEl = wrap.querySelector('.roe-contact-error');
        var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        var recipient = <?php echo wp_json_encode($atts['recipient']); ?>;
        var defaultBtnText = <?php echo wp_json_encode($atts['button_text']); ?>;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            var name = (fd.get('name') || '').trim();
            var email = (fd.get('email') || '').trim();
            var message = (fd.get('message') || '').trim();
            if (!name || !email || !message) {
                errorEl.textContent = 'Please fill in name, email, and message.';
                errorEl.style.display = 'block';
                successEl.style.display = 'none';
                return;
            }
            fd.append('action', 'dg_re_submit_contact');
            fd.append('recipient', recipient);
            btn.disabled = true;
            btn.textContent = 'Sending…';
            errorEl.style.display = 'none';
            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        successEl.textContent = (res.data && res.data.message) ? res.data.message : 'Thank you — your message has been sent.';
                        successEl.style.display = 'block';
                        form.style.display = 'none';
                    } else {
                        errorEl.textContent = (res.data && res.data.message) ? res.data.message : 'Something went wrong. Please try again.';
                        errorEl.style.display = 'block';
                        btn.disabled = false;
                        btn.textContent = defaultBtnText;
                    }
                })
                .catch(function () {
                    errorEl.textContent = 'Network error — please try again or call us directly.';
                    errorEl.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = defaultBtnText;
                });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
