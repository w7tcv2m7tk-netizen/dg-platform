# DG Platform Changelog

## 10.71.0 — Founding 10 accept → setup → 14-day Stripe trial (August 2026)

- **Founding 10 journey (testable, live funnel not switched):** invite preview `/founding-customers-preview/`, offer accept `/founding/accept/{token}/`, Gen 2-style setup `/founding/setup/`, Stripe Checkout Session with `trial_period_days=14` (no Payment Links)
- **Admin:** DG Platform → Founding 10 issues accept links and runs monthly + yearly trial proofs
- **Stripe webhooks:** `trialing` tags `Trialing` + `Founding 10` and does **not** apply `Payment Received`; `active` after trial adds payment and strips trialing
- **`/onboarding/` alias** stays OFF (`dg_founding_setup_ready`) until setup returns 200 and the live funnel is ready to switch
- **Marketing sources:** `founding-customers-page.html` is invite/explore (no Terms checkbox). Public CTAs stop pointing at `#application` and `/onboarding/`
- Founding Customer Terms and commercial rules unchanged. `/signup` stays a separate generic picker

## 10.67.0 — Acc StayBooking dual-write push (August 2026)

- **Acc:** `DG_Acc_Platform_Sync` listens to `dg_booking_created` / `dg_booking_confirmed` and POSTs the booking row to Gen 2 `/api/webhooks/dg-stay-booking` (non-blocking)
- Keeps Neon **StayBooking** warm for Gen 2 read SoT while public book-now / PayID / Stripe / Dev API create still originate on WordPress calendar
- Auth: `DG_STAY_BOOKING_WEBHOOK_SECRET` / option `dg_stay_booking_webhook_secret`, else discovery webhook secret, else Dev API key (match Gen 2 `DG_WP_ACCOMMODATION_API_KEY`)
- Optional org pin: `DG_ACC_ORGANISATION_ID` or option `dg_platform_organisation_id`
- **Dev API:** public `format_bookings_for_platform()` for dual-write payload shape

## 10.66.0 — RE inspection times + Acc ops guards (August 2026)

- **RE:** **POST `/properties` upsert** accepts `inspection_times` → `roe_property_inspection_times` (open homes); **GET `/properties`** returns `inspection_times` for Gen 2 listing sync / publish round-trip
- **Acc (ex-10.65.2):** **POST `/accommodation/bookings`** — reject overlapping nights (existing stays + manual blocks); Saturday check-in/out blocked unless `allow_saturday` / `force`; default `paid=no` (no longer inferred from confirmed); auto-quote total from unit rates when total omitted
- **Acc:** **GET `/accommodation/summary`** — fix tomorrow TZ (`wp_date` from site today); add `checkouts_today` (+ ids), `today`, `tomorrow`
- **Acc:** **GET `/accommodation/housekeeping`** — `last_report_id`, `checkout_today` per unit; summary counts match listed units (incl. draft/coming soon); `checkouts_today` + `today`

## 10.65.1 — Accommodation reviews API + guest prefs round-trip (August 2026)

- **GET `/accommodation/reviews`** — read-only surface over `dg_reviews` (Airbnb / TrustIndex import / manual) with platform counts for Gen 2 Acc Reviews
- **PATCH `/accommodation/guests`** — write `preferences` / `special_requests` meta; resolve guest by `id` → `contact_id` → `email`; return `skipped[]` when unmatched

## 10.65.0 — Accommodation Gen 2 booking create + richer APIs (August 2026)

- **POST `/accommodation/bookings`** — create manual/direct bookings (guest, unit, dates, guests/nights, paid, payment_method, message, source); rebuilds OTA blocked dates
- **PATCH bookings** — richer writable fields; **rebuild_blocked_dates()** when check-in/out or unit changes (same as DELETE cancel path)
- **GET bookings** — phone, guests, nights, paid, payment_method, message, source on every row
- **GET/PATCH guests** — VIP, notes, tags, address, source, total nights/spend, last stay; optional `contact_id` link for Gen 2 Contact sync
- **GET summary** — `checkins_today` (+ ids) alongside tomorrow

## 10.64.0 — CVH email logos + booking source (August 2026)

- **Root cause:** CVH/Roe email logos pointed at live `/uploads/` URLs that return **403** behind CDN hotlink protection — clients never showed images
- Bundle **CVH logo + icon** in `assets/brand/` and embed as **CID attachments** (with absolute HTTPS plugin URL fallback); header uses icon + wordmark lockup
- Add **Source:** line to guest confirmation, guest check-in, owner booking confirmed, owner check-in reminder, and enquiry admin mail (`Airbnb` / `Booking.com` / `Direct` / `Manual`)
- Helpers: `DG_Email_Brand::booking_source_label()`, `booking_source_label_for()`, `header_lockup()`, `brand_asset_url()`

## 10.62.0 — Manual blocked dates via Dev API (August 2026)

- **GET `/accommodation/availability`** returns `manual_blocked_dates` (operator blocks from `dg_blocked_dates` only) alongside merged `blocked_dates`
- **PATCH `/accommodation/properties`** accepts `block_dates[]` / `unblock_dates[]` (incremental) or `manual_blocked_dates[]` (full replace of expanded days)
- Writes only `dg_blocked_dates` — never touches `dg_ota_blocked_dates` / OTA booking-derived blocks
- Enables Gen 2 Availability click-to-block

## 10.61.0 — iCal URL fields on accommodation properties API (August 2026)

- **GET/PATCH `/accommodation/properties`** includes OTA calendar fields: `airbnb_ical_url`, `bookingcom_ical_url`, `ical_export_url` (+ optional fallback), last sync / last error
- PATCH accepts `airbnb_ical_url` / `bookingcom_ical_url` (empty string clears); export URL is derived and read-only
- Enables Gen 2 Units iCal management (import URLs + copy DigitalGate export)

## 10.60.0 — Soft-delete bookings via Dev API (August 2026)

- **DELETE `/accommodation/bookings`** soft-cancels bookings (`dg_booking_status=cancelled`) — same pattern as OTA iCal UID removal; does not hard-destroy posts
- Rebuilds OTA blocked dates for affected units after cancel
- Enables Gen 2 Bookings table Delete button

## 10.56.0 — Branded email logos (August 2026)

- **Roe / CVH email shells** now include absolute HTTPS logo images (live site assets); text wordmark fallback if URL empty
- Shared helpers on `DG_Email_Brand`: `logo_url()`, `logo_img()`, `plain_to_html()`
- Branded HTML for: RE templates, CVH guest check-in + booking confirmation, contact forms, support chat, discovery admin, e-sign invites, cleaning reports, CVH enquiries
- DigitalGate marketing/onboarding/Stripe paths unchanged (already used `DG_Brand` lockup)

## 10.55.0 — Live Support AI first-line (August 2026)

- DigitalGate Assist auto-replies in Live Support after client messages (OpenAI/Gemini via `DG_AI_Client`)
- New `ai` message role + Support Inbox pause/resume controls (staff reply pauses AI)
- Schema: `ai_paused` on support conversations

## 10.17.1 — CHV admin UX (August 2026)

### Fixed
- **DG Platform sidebar** stays open when viewing Types, Properties, Bookings, and Guests (parent_file/submenu_file highlight)
- **Stripe status notice** limited to Stripe Settings and CVH dashboard — no longer on every accommodation screen

### Added
- **Booking Calendar** admin page — Sync all calendars + Refresh view toolbar

---

## 10.17.0 — CHV stay/booking presentation restore (August 2026)

### Fixed
- **Stay page** `[dg_accommodation_display]` — restored original rich property cards (description, feature chips, sleeps/beds/baths, Cormorant typography) with COMING SOON variants
- **Property details** `[dg_accommodation_details]` — restored full-width hero layout, gallery, sidebar pricing/map/features from original Fluent Snippet
- **Book now** `[dg_book_now]` — CVH-branded booking layout with property tabs, calendar, form, and sidebar summary
- **Booking confirmed** `[dg_booking_confirmation]` — restored payment page with PayID bank details and booking summary card

---

## 10.16.9 — Property brochure (August 2026)

### Added
- **Property brochure page** at `/brochure/?property={id}` — branded print layout with hero image, specs, gallery, floorplans, and agent card
- **Download Brochure** button on property pages opens brochure and triggers Save as PDF dialog
- Redirects to uploaded PDF when `roe_property_brochure` meta URL is set

---

## 10.16.8 — Beta Setup check fixes (August 2026)

### Fixed
- **AI Visibility scan** Beta Setup step now reads `dg_ai_visibility_scans` (was checking wrong table name)
- **Purge Cloudflare cache** step passes after first successful Site Tools cache purge

---

## 10.16.7 — Dashboard fatal hardening (August 2026)

### Fixed
- **DG Platform dashboard** no longer runs full Beta Setup checks (including HTTP probes) on every load
- Cached lightweight summary for dashboard button + admin notices; full checks only on Beta Setup page
- Duplicate `summary()` method removed from `class-onboarding.php`

---

## 10.16.6 — Onboarding Oxygen form (August 2026)

### Added
- Updated `marketing/pages/onboarding-form.html` — posts to `admin-post.php` (DG Platform handler)
- Frontend script injects WordPress nonce on `/onboarding/` page
- Shortcode `[dg_onboarding_hidden_fields]` for optional use in Oxygen/Breakdance
- Field mapping for `website_url`, `gmb_*`, `systems[]`, `deliverables[]`, `competitors[]`

---

## 10.16.5 — Client onboarding handler (August 2026)

### Added
- **Client onboarding** — `POST /wp-json/digitalgate/v1/onboarding` replaces standalone `save-onboarding.php`
- Creates DG organisation + contact (tags: DigitalGate Client, Onboarding Complete), portal subscriber, file uploads to Media Library
- Branded admin notification to `onboarding@digitalgate.com.au` via Site Tools SMTP
- HTML form handler: `admin-post.php?action=dg_submit_onboarding` (supports multipart file uploads)

---

## 10.16.4 — SEO Pro editor panel (August 2026)

### Added
- **SEO Pro** sidebar meta box on pages, posts, and custom types — Google snippet preview, focus keyword, character counts, Social tab (OG title/description/image), Advanced tab (canonical, noindex/nofollow)

---

## 10.16.3 — Dashboard hardening (August 2026)

### Fixed
- Beta Setup checks wrapped in try/catch — DG Platform dashboard no longer fatals if a check throws
- `summary()` returns safe defaults instead of crashing wp-admin

---

## 10.16.2 — Onboarding fatal fix (August 2026)

### Fixed
- **Critical:** Beta Setup crashed wp-admin on Roe/DigitalGate — Real Estate `re_booking` step passed admin URL as callback (`call_user_func` TypeError in `class-onboarding.php`)
- Same fix for Marketing `mkt_voice` step on DigitalGate
- `evaluate()` now skips invalid callbacks instead of fatal error

---

## 10.16.1 — Schema upgrade on deploy (August 2026)

### Added
- **Core → Reviews** — import and manage reviews from GBP, Airbnb, Booking.com, REA, Domain, TripAdvisor, Facebook, Trustpilot, and more (manual + CSV)
- Frontend shortcode: `[dg_reviews limit="6" min_rating="4"]`

### Changed
- **Training & Onboarding** pricing shown as one-time ($497), not monthly, in Modules & Plan and pricing page
- **Premium Pro apps** (SEO Pro, AI Visibility Pro, Automation Pro, Analytics Pro) only appear in sidebar when selected in Platform Plan add-ons
- **CHV stay page** — Tiny Home and Private Retreat cards use COMING SOON styling (matches Eco Domes)
- **Book-now page** — calendar reinstated on booking page; legacy "No Booking Details Found" blocks stripped

### Note
After deploy, tick the Premium add-ons you need under **DG Platform → Modules & Plan** for each site.

---

## 10.15.0 — Admin UX & Roe property workspace (August 2026)

### Added
- **Admin sidebar grouping** — Core, Industry, Premium, Add-ons, and Platform section labels in the DG Platform menu
- **Roe Realty property workspace** — per-listing files, contract templates, e-sign links (`/sign-contract/{token}/`), and settlement checklist with key dates
- **Property Files** admin index under Real Estate (DG Platform → Property Files)

### Changed
- SEO module menu and settings renamed to **SEO Pro** (matches pricing page)
- Beta Setup wizard references updated to SEO Pro

---

## 10.14.0 — Beta release (August 2026)

### Added
- **Beta Setup wizard** — DG Platform → Beta Setup — interactive checklist with auto-checks for permalinks, modules, health, SMTP, SEO, AI Visibility, legacy plugins, and site-specific steps (Real Estate / Marketing)
- Dashboard banner when beta setup is incomplete
- Kill switch: `wp-content/.dg-platform-off` disables plugin without renaming folder
- Emergency tools: `dg-debug.php`, `dg-platform-diagnose.php`, `dg-platform-emergency-disable.php`

### Fixed
- **Critical:** Parse error in `class-seo-schema.php` line 223 (`priceRange` array assignment typo) — caused site-wide 500 on DigitalGate
- Safer module loading (try/catch around vertical module bootstrap)
- Site Tools snippets respect `DG_PLATFORM_DISABLE_SNIPPETS` in safe mode

### Beta readiness
- Platform Health panel (Site Tools → Platform Health)
- Preview badges on Finance, Services, Commercial, Automotive, Creator modules
- Beta Launch Pack doc + beta program HTML page
- Roe Realty reference deployment test script in BETA-LAUNCH-PACK.md

---

## 10.13.x — Site Tools & recovery (August 2026)

- Site Tools: cache purge, SMTP, images, snippets, Cloudflare, Platform Health
- AI Visibility Pro, Automation Pro, Analytics Pro on matched production sites
- Emergency recovery documentation (BETA-LAUNCH-PACK §8)

---

## 10.0.x–10.12.x — Foundation

- Modular CRM core with Real Estate, Marketing, Accommodation, Creator modules
- DG SEO engine (Rank Math replacement)
- Roe Realty production deployment (vendor/buyer pipelines, bookings, property reports)
- DigitalGate Marketing CRM (audits, voice agent, agency clients)
- Site profiles per hostname (digitalgate.com.au, roerealty.com.au, etc.)
