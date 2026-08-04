# Client Onboarding Form — Oxygen Update

Paste this HTML into the **Oxygen → HTML Code** element on the `/onboarding/` page (post ID 527 on DigitalGate).

## What changed (v10.16.6)

| Before | After |
|--------|-------|
| `action="/save-onboarding.php"` | `action="/wp-admin/admin-post.php"` |
| FluentCRM hidden field | DG Platform `action=dg_submit_onboarding` + honeypot |
| Standalone PHP + FluentCRM | DG Platform handler (contacts, email, uploads) |

## Deploy checklist

1. Deploy **DG Platform v10.16.6+** to digitalgate.com.au
2. In Oxygen, open **Pages → Onboarding → HTML Code** element
3. Replace the entire HTML with `marketing/pages/onboarding-form.html` from this repo
4. Save and publish
5. **Delete** root `save-onboarding.php` (no longer needed)
6. Test submit → should redirect to `/onboarding-thank-you/` and create a contact in **DG Platform → Contacts**

## How the nonce works

The form HTML includes static hidden fields. DG Platform enqueues `assets/js/onboarding-form.js` on the onboarding page, which injects the WordPress `_wpnonce` field at load time.

Optional: use shortcode `[dg_onboarding_hidden_fields]` in a Breakdance/Oxygen **Shortcode** element inside the form if you prefer server-rendered fields.

## REST alternative

JSON submissions (no file uploads):

```
POST /wp-json/digitalgate/v1/onboarding
Content-Type: application/json
```

See **DG Platform → API Settings** for the full URL.
