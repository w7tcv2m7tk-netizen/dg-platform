# Four-Site Audit — Aug 2026

## Priority fixes

| # | Site | Issue | Fix |
|---|------|-------|-----|
| 1 | **CVH** | Contact form POSTs to `/inc/send-contact.php` → **404** | Replace Oxygen form HTML with `[dg_contact_form]` (v10.7.2+) |
| 2 | **Aetherra** | Contact form uses GET, no field names, no handler | Rebuild form — use WP plugin or POST handler |
| 3 | **DigitalGate** | Homepage still "Real Estate Growth Systems"; pricing says BOP | Content refresh (see DG section) |
| 4 | **DigitalGate** | Pricing CTAs link to `#` | Paste Stripe URLs into `pricing-page.html` |
| 5 | **CVH** | Deploy v10.7.1+ — book-now, descriptions, calendar | Upload plugin zip |

---

## currumbinvalleyhideaway.com.au

### Critical
- **Contact form broken** — `action="/inc/send-contact.php"` returns 404. Old Brevo script never deployed to live server.

**Fix (WP Admin):** Edit Contact page in Oxygen → replace "Send Us a Message" `<form>...</form>` block with:
```
[dg_contact_form]
```
Requires DG Platform v10.7.2+ with accommodation module active.

### High
- **Book-now page** — use `[dg_book_now]` shortcode (v10.7.1+)
- **Stay pages** — Studio/Tiny Home need `[dg_accommodation_details]` + rates in admin
- **`/local-attractions/`** → 404 — redirect to `/experiences/`

### Medium
- Contact form had no success/error feedback (even when PHP existed)
- Footer "Local Attractions" → Google Maps (misleading label)
- Copy says "Luxury eco domes" but bookable units are Studio + Tiny Home

### Low
- Contact hero image off-brand (city/beach vs rainforest)

---

## digitalgate.com.au

### High — Platform repositioning
Homepage, services, contact, and audit pages still sell **agency/Real Estate**. Pricing and footer say **Business Operating Platform**. This split confuses buyers.

**Content updates needed:**

| Page | Current | Target |
|------|---------|--------|
| Homepage hero | "More Appraisals. More Listings." | "Your Business Operating System" |
| Meta title | Real Estate Growth & AI Visibility | Business Operating Platform |
| Services nav | Agency SKUs only | Platform · Growth Systems · Industry Apps |
| Contact dropdown | SEO, Ads, Vendor Leads | + Platform trial, Industry App demo |
| About | Agency-focused | Platform company + optional Growth Systems |
| Footer tagline | Mixed | "AI-powered Business Operating Platform" |

**New pages/sections to add:**
- Platform overview (CRM, Growth Automation, AI Visibility, Growth Intelligence)
- Industry Apps landing (Real Estate, Accommodation, Finance, Services, Automotive, Commercial)
- Case study: Roe Realty (live on platform)
- Founding Customer Program CTA

### High — Pricing page
- Re-paste `marketing/pages/pricing-page.html` into Oxygen
- Wire Stripe URLs (7 core links minimum)
- Add **Pricing** to main nav
- Hero CTA should not all point to `/free-agency-audit/` for platform trials

### Medium
- Contact form OK (`/inc/send-dg-enquiry.php`) — add Platform-related service options
- Professional tier may be missing on live pricing (re-deploy HTML)

---

## roerealty.com.au

### Medium
- **No contact message form** — phone/email/appraisal CTA only
- **`/property/`** shows only Sold listings — looks inactive

### Low
- Honeypot field visible in property report form source
- Nav contrast on light hero

### OK
- Property report & appraisal forms work
- Core pages load

---

## aetherra.com.au

### Critical
- **Contact form broken** — GET method, no `name` attributes, no backend

### High
- **Mailing list subscribe** — input with no form/action
- **Broken artist images** on `/music/` and `/mixes/` (escaped HTML in Oxygen)

### Medium
- Dev copy visible on contact: "This section signals professionalism to promoters."

---

## Cross-site

| Item | Notes |
|------|-------|
| Phone numbers | DG `0405 227 227` vs RR `0420 227 227` — confirm intentional |
| Digital Business Card | Present on all sites ✅ |
| Cloudflare | Blocks automated crawlers — normal |

---

## DG Platform deploy checklist (CVH)

1. Upload `dg-platform-build.zip` v10.7.2+
2. Contact page → `[dg_contact_form]`
3. Book Now page → `[dg_book_now]`
4. DG Platform → Properties → add rates, features, descriptions for Studio & Tiny Home
5. DG Platform → Stripe → keys or disable if PayID-only
6. Test contact form submit → check stay@ inbox
