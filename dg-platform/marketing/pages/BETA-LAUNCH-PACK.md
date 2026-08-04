# DigitalGate Business Platform — Beta Launch Pack

**Version:** 10.16.0  
**Beta vertical:** Real Estate Agency CRM + Growth Platform  
**Reference deployment:** [roerealty.com.au](https://roerealty.com.au)  
**Last updated:** August 2026

---

## Standard site stack (all four sites)

Every production site should run **only these four plugins**:

| Plugin | Purpose |
|--------|---------|
| **Oxygen** | Page builder / templates |
| **Breakdance Elements for Oxygen** | Breakdance elements in Oxygen |
| **Breakdance Forms for Oxygen** | Forms in Oxygen |
| **DG Platform** | CRM, industry modules, SEO, Site Tools, automations |

**Sites:** digitalgate.com.au · roerealty.com.au · currumbinvalleyhideaway.com.au · aetherra.com.au

No Rank Math, Fluent SMTP, Fluent Snippets, Smush, Site Kit, or Super Page Cache — DG Platform replaces them. Beta Setup → **Legacy plugin cleanup** should show ✅ pass with this stack.

**Deploy target:** v10.16.0+ (`dg-platform-build.zip`). If Plugins shows **10.13.4**, upload the latest zip (delete old `dg-platform` folder first, then upload).

---

## 1. Beta positioning (website copy)

### Headline
**The AI-powered operating platform for growth-focused real estate agencies.**

### Subhead
CRM, vendor pipeline, SEO, AI visibility, and automation — in one WordPress-native platform. Built and battle-tested on Roe Realty.

### One-liner
Replace disconnected CRM, SEO, automation, and reporting tools with a single modular platform.

### Who it's for
- Independent and boutique real estate agencies (5–50 staff)
- Agencies already on WordPress + Breakdance/Oxygen
- Teams who want AI visibility + SEO + CRM without 10 separate subscriptions

### Founding beta offer
- Discounted pilot pricing (see pricing page / Stripe founding links)
- Priority support + direct input into the product roadmap
- White-glove onboarding (DigitalGate installs + configures)
- Roe Realty as live reference site

### What's included in beta

| Included | Description |
|----------|-------------|
| CRM Core | Contacts, tasks, calendar, activities, documents, search, custom fields |
| Real Estate module | Vendor leads, buyer leads, appraisals/bookings, listings, agents, property report |
| SEO | Meta, schema, sitemap, redirects (Rank Math replacement) |
| AI Visibility Pro | ChatGPT + Gemini scoring, recommendations, `/llms.txt` |
| Automation Pro | Multi-step workflows, delays, webhooks, templates |
| Analytics Pro | KPI snapshots, trends, CSV export, weekly email |
| Site Tools | Cache purge, SMTP, image compression, Cloudflare stats |
| Roles & permissions | Admin, Sales Agent, Reception, Marketing templates |
| Dev API | REST + MCP for integrations |

### Explicitly NOT in beta v1 (roadmap)

| Preview / coming soon | Notes |
|----------------------|-------|
| Finance, Services, Commercial, Automotive modules | Admin MVP only — labelled **Preview** in plugin |
| Creator module | Internal use (Aetherra) |
| Email/SMS campaign builder | Transactional + automation emails only |
| Visual workflow builder | Template-based automations |
| REA / Domain syndication | Manual import API available |
| Google Search Console deep dashboard | PageSpeed + SEO cover basics |
| Multi-tenant hosted SaaS | Per-site WordPress install today |
| White label | Enterprise roadmap |

---

## 2. Beta agency onboarding checklist

Use this when onboarding a new pilot agency.

### A. Pre-install (DigitalGate)

- [ ] Agency signed founding beta agreement
- [ ] WordPress 6.0+ hosting confirmed (HTTPS, PHP 7.4+)
- [ ] Domain + staging URL agreed
- [ ] Plan tier selected (Business recommended for RE beta)
- [ ] Stripe subscription or invoice arranged

### B. Plugin install

- [ ] Confirm only the **standard four plugins** are active (Oxygen, Breakdance Elements, Breakdance Forms, DG Platform)
- [ ] Upload `dg-platform-build.zip` (contains `dg-platform/` folder) — **v10.16.0+**
- [ ] Activate DG Platform
- [ ] **Settings → Permalinks → Save** (no plain permalinks)
- [ ] Confirm **DG Platform → Modules & Plan**:
  - Plan: **Business** (or Enterprise for DigitalGate)
  - Modules: site-specific (Core + Real Estate / Accommodation / Marketing)
  - **Add-ons:** tick Premium apps sold (SEO Pro, AI Visibility Pro, Automation Pro, Analytics Pro)
- [ ] Refresh admin once if module menus missing

### C. Site Tools setup

- [ ] **DG Platform → Beta Setup** — review auto-checklist (target ≥ 85% complete, zero failures)
- [ ] **DG Platform → Site Tools → Email** — SMTP configured + test email sent
- [ ] **Site Tools → Cache & CDN** — Cloudflare API token + Zone ID
- [ ] **Site Tools → Images** — compress on upload enabled; run bulk optimize if migrating from Smush
- [ ] **Site Tools → Platform Health** — score ≥ 85%, zero failures

### D. API keys

- [ ] **DG Platform → API Settings** — PageSpeed API key (Google Cloud)
- [ ] OpenAI + Gemini keys (AI Visibility Pro)
- [ ] Twilio (optional — SMS automations)
- [ ] Property import key generated (if using listing import)

### E. SEO & AI

- [ ] **DG Platform → SEO Pro** — global settings + home title/description
- [ ] Confirm `/sitemap_index.xml` loads
- [ ] Deactivate **Rank Math** after verifying meta tags *(skip if standard stack)*
- [ ] **AI Visibility Pro** — run first scan
- [ ] Install automation templates from **Automation Pro**

### F. Legacy plugin cleanup

**Target:** only Oxygen + Breakdance + DG Platform (see **Standard site stack** above).

If any of these are still installed, deactivate after DG Platform covers the workflow:

- [ ] Rank Math → DG SEO Pro
- [ ] Fluent SMTP → Site Tools → Email
- [ ] Fluent Snippets → DG modules / Site Tools → Snippets
- [ ] Smush → Site Tools → Images
- [ ] Google Site Kit → SEO Pro + Site Tools → Analytics
- [ ] Super Page Cache (optional — edge cache can stay; use DG cache purge)

With the standard four-plugin stack, this section is **already complete** ✅

### G. Real Estate module config

- [ ] Property + Agent post types working
- [ ] `/property-appraisal/` booking page live
- [ ] Contact form shortcode on site
- [ ] Vendor lead form → CRM pipeline tested
- [ ] Buyer inquiry form tested
- [ ] Property report lead magnet tested
- [ ] Email follow-up automations active
- [ ] Agent profiles + property grid shortcodes rendering

### H. Roles & users

- [ ] Create agency users with correct roles (Sales Agent, Reception, etc.)
- [ ] Verify menu visibility per role
- [ ] Admin ≠ sharing passwords

### I. Go-live

- [ ] Purge Cloudflare cache
- [ ] Platform Health re-check on production
- [ ] Send agency admin link to beta feedback channel
- [ ] Schedule 2-week check-in

---

## 3. Roe Realty production test script

Run on **roerealty.com.au** before each beta release. Check every box.

### Environment

- [ ] DG Platform v10.16.0+ active
- [ ] **DG Platform → Beta Setup** ≥ 85% complete, zero failures
- [ ] Only four plugins active (Oxygen, Breakdance ×2, DG Platform)
- [ ] Permalinks pretty (not `?p=123`)
- [ ] No legacy plugin warnings in admin
- [ ] SMTP test email received

### Frontend — public pages

- [ ] Homepage loads without PHP errors
- [ ] No third-party SEO plugin admin bar on frontend
- [ ] Property listings grid displays (`[properties]` or equivalent)
- [ ] Single property page loads with gallery + details
- [ ] Agent profile pages load
- [ ] Contact form submits successfully
- [ ] `/property-appraisal/` booking calendar loads available slots
- [ ] Property report / lead magnet form submits

### Vendor pipeline

- [ ] Submit test vendor lead (website form or admin)
- [ ] Lead appears in **DG Platform → Vendor Leads**
- [ ] Contact created in **Contacts**
- [ ] Activity logged on timeline
- [ ] Automation fires (welcome email / task created) if configured
- [ ] Lead status can be updated in admin
- [ ] Lead detail page loads without error

### Buyer pipeline

- [ ] Submit test buyer inquiry
- [ ] Appears in **Buyer Leads**
- [ ] Buyer pipeline board loads
- [ ] Status update works

### Appraisals / bookings

- [ ] Book appraisal slot on frontend
- [ ] Booking appears in **DG Platform → Bookings**
- [ ] Confirmation email received (check spam)
- [ ] Admin notification received

### Listings & properties

- [ ] Create/edit property in admin
- [ ] Property meta saves (price, beds, address, etc.)
- [ ] Property appears on frontend grid
- [ ] Property import API responds (if used): `POST /wp-json/roerealty/v1/import` with `X-API-Key`

### SEO

- [ ] View source: `<title>`, meta description, canonical present
- [ ] OG tags on homepage + property page
- [ ] `/sitemap_index.xml` returns 200
- [ ] JSON-LD schema on property page (RealEstateListing)
- [ ] 301 redirect test (if configured in SEO → Redirects)

### AI Visibility Pro

- [ ] Run manual scan from admin
- [ ] Scores saved to history
- [ ] Recommendations display
- [ ] `/llms.txt` accessible

### Automation Pro

- [ ] Install "Vendor lead nurture" or "Buyer inquiry follow-up" template
- [ ] Trigger test lead → verify email/task in automation logs

### Analytics Pro

- [ ] Dashboard shows CRM metrics
- [ ] Snapshot captured (or manual refresh works)
- [ ] CSV export downloads

### Site Tools

- [ ] Purge cache button succeeds
- [ ] PageSpeed scores refresh
- [ ] Image upload compresses (check file size reduction)

### REST / API

- [ ] `GET /wp-json/digitalgate/v1/` responds
- [ ] Dev API key works from MCP (optional internal check)

### Regression checks

- [ ] **Properties** admin menu click — no critical error
- [ ] **Agents** admin menu click — no critical error
- [ ] Dark mode admin usable (if enabled)
- [ ] Mobile: booking form usable on phone

---

## 4. Beta feedback template

Send to pilot agencies after 2 weeks.

**Agency name:** _______________  
**Primary user:** _______________  
**Date:** _______________

1. What workflow do you use most? (Vendor leads / Buyers / Bookings / Listings / Other)
2. What saved you time compared to your previous tools?
3. What broke or confused you? (screenshots welcome)
4. What's missing before you'd pay full price?
5. Would you recommend to another agency? (1–10)
6. Permission to use as case study? Y / N

**Send to:** ben@digitalgate.com.au (or your beta Slack/email)

---

## 5. Release checklist (DigitalGate internal)

Before tagging a beta release:

- [ ] Roe Realty test script 100% pass
- [ ] `dg-platform-build.zip` rebuilt (wraps `dg-platform/` folder)
- [ ] Version bumped in `dg-platform.php`
- [ ] Deploy to all 4 production sites OR document which sites get update
- [ ] Permalinks flush reminder in release notes
- [ ] Platform Health on each site ≥ 85%
- [ ] Changelog sent to beta agencies

---

## 6. File locations

| Asset | Path |
|-------|------|
| Plugin source | `/Users/aetherra/Documents/dg-platform/` |
| Deploy zip | `/Users/aetherra/Documents/dg-platform-build.zip` |
| Beta website page | `marketing/pages/beta-program-page.html` |
| Pricing | `marketing/pages/pricing-page.html` |
| Stripe links | `marketing/pages/STRIPE-PAYMENT-LINKS.md` |
| Beta setup wizard | WP Admin → DG Platform → Beta Setup |
| Platform Health | WP Admin → DG Platform → Site Tools → Platform Health |
| Changelog | `CHANGELOG.md` |

---

## 7. Recommended next builds (post-beta start)

Priority order:

1. GSC OAuth in Site Tools (complete Site Kit replacement)
2. Unified admin notification centre
3. Onboarding wizard (plan → modules → keys → SMTP → health)
4. Stripe webhook → auto-set plan tier
5. REA/Domain feed research
6. Email campaign builder (lists + broadcasts)

---

## 8. Emergency recovery (critical error)

If the site shows **"There has been a critical error on this website"** after updating DG Platform:

### Step 0 — Is it DG Platform? (30 seconds)

Create an empty file via FTP/hosting file manager:

`wp-content/.dg-platform-off`

Reload the site.

- **Site loads** → DG Platform is the cause. Continue below, then remove `.dg-platform-off` after a clean reinstall.
- **Still 500** → Another plugin or theme is failing. Rename `wp-content/plugins/dg-platform` → `dg-platform.disabled` anyway, then disable other plugins one by one.

### Step 1 — Get the exact error

**Option A — debug script (shows error on screen)**

1. Upload `dg-platform/emergency/dg-debug.php` to the **WordPress root** (same folder as `wp-load.php`)
2. Visit `https://yoursite.com/dg-debug.php`
3. Copy the error line shown
4. **Delete `dg-debug.php` immediately**

**Option B — fatal log**

1. Upload `dg-platform/emergency/dg-platform-diagnose.php` to `wp-content/mu-plugins/`
2. Reload the site once
3. Download `wp-content/dg-fatal.log`
4. Delete the mu-plugin file

### Step 2 — Clean reinstall (do not skip delete)

1. **Delete** the entire folder `wp-content/plugins/dg-platform/` (do not upload over the top — mixed old/new files cause this exact issue)
2. Upload **`dg-platform-build.zip` v10.16.1+** and extract so the path is exactly `wp-content/plugins/dg-platform/dg-platform.php`
3. Activate → **Settings → Permalinks → Save**
4. Remove `wp-content/.dg-platform-off` if you created it

### Option A — Safe mode (keeps CRM Core only)

1. Upload `dg-platform/emergency/dg-platform-safe-mode.php` to **`wp-content/mu-plugins/dg-platform-safe-mode.php`**
2. Reload the site — industry modules are disabled
3. Fix or re-upload the plugin, then **delete** the mu-plugin file

### Option B — Emergency disable (fastest, keeps plugin folder intact)

1. Upload `dg-platform/emergency/dg-platform-emergency-disable.php` to **`wp-content/mu-plugins/dg-platform-emergency-disable.php`**
2. Reload the site — DG Platform is skipped on every request
3. Log in to wp-admin, delete the broken plugin folder, upload fresh **v10.13.2+** zip, activate
4. **Delete** the mu-plugin file

### Option C — Deactivate via FTP / hosting file manager

1. Rename `wp-content/plugins/dg-platform` → `dg-platform.disabled`
2. Log in to WordPress admin
3. Delete or replace the plugin folder with a fresh zip upload

### Option D — Disable Site Tools snippets

If the error started after adding a Site Tools snippet, remove the option from the database:

```sql
DELETE FROM wp_options WHERE option_name = 'dg_site_tools_snippets';
```

(Replace `wp_` with your table prefix.)

### After recovery

1. Upload **`dg-platform-build.zip` v10.13.3+** (full zip, not partial files)
2. **Settings → Permalinks → Save**
3. **DG Platform → Site Tools → Platform Health** — fix any failures
4. Only enable **Preview** modules if you need them for testing
