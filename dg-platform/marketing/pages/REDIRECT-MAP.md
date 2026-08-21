# DigitalGate Redirect Map

**Effective:** 12 August 2026  
**Branch:** `cursor/homepage-commercial-polish`  
**Use when:** Configuring WP Redirection plugin, Cloudflare bulk rules, or Oxygen deploy QA.

---

## Consultation paths (canonical — read first)

| Intent | Canonical URL | Repo source | Notes |
|--------|---------------|-------------|-------|
| **Book Platform Consultation** | `/strategy-session/` (or `/contact/#platform-consultation`) | `strategy-session-page.html` / `contact-page.html` | Secondary CTA beside Founding · keep consultation paths live |
| **AI Platform Discovery** (structured audit form) | `/discover/` | `discovery-form.html` | Header/footer Resources · posts to `/wp-json/digitalgate/v1/discovery` · **not** a duplicate of contact |
| **Founding Customer Programme** | `/founding-customers/` | `founding-customers-page.html` | **Primary sales URL** · on-page application at `#application` · site-wide “Become a Founding Customer →” |
| **Client Onboarding** (post-acceptance) | `/onboarding/` | `onboarding-form.html` | **Not** acquisition — accepted founding / customers ready to implement |
| **Strategy session (legacy)** | `/strategy-session/` → see Redirects | `strategy-session-page.html` | Still used for Book Platform Consultation CTAs |

### Founding Customer funnel (canonical)

`Become a Founding Customer →` → `/founding-customers/` → Apply (`#application` on-page form) → consultation as needed → acceptance → `/onboarding/` (Client Onboarding) → Platform

**Do not** send cold Founding CTAs to `/onboarding/`. **Do not** maintain a separate `/founding-application/` page — application is a section on `/founding-customers/`.

**`/beta/` soft-deprecate:** 301 `/beta/` → `/founding-customers/`. `beta-program-page.html` is a short redirect/canonical pointer only. Also 301 `/founding/` → `/founding-customers/` if that slug exists.

---

## Retain (no redirect)

Canonical URLs after BOS revamp. Paste from repo; no slug change.

| URL | Repo file | Role |
|-----|-----------|------|
| `/` | `homepage.html` | Homepage · platform overview anchors (`#platform-overview`, `#industries`, etc.) |
| `/pricing/` | `pricing-page.html` | Platform · Apps · Professional Services · Customer Success · Founding |
| `/about/` | `about-page.html` | Company / platform story |
| `/contact/` | `contact-page.html` | Contact + `#platform-consultation` booking section |
| `/founding-customers/` | `founding-customers-page.html` | **Founding Customer Programme™** + on-page application (`#application`) |
| `/founding-customer-terms/` | `founding-customer-terms.html` | **Founding Customer Terms & Conditions** · programme rules (alongside general Terms) |
| `/discover/` | `discovery-form.html` | AI Platform Discovery form |
| `/insights/` | `insights-page.html` | Blog index chrome (keep WP posts) |
| `/business-brain/` | `business-brain-page.html` | Connected Business / Business Brain™ narrative |
| `/from-dumb-businesses-to-smart-businesses/` | `from-dumb-businesses-to-smart-businesses.html` | Featured Insight |
| `/onboarding/` | `onboarding-form.html` | **DigitalGate Client Onboarding** (post-acceptance / post-sale) |
| `/card/` | `digital-business-card.html` | Ben Roe digital business card |
| `/privacy-policy/` | `privacy-policy.html` | Privacy Policy (12 Aug 2026) |
| `/legal-notice/` | `legal-notice.html` | Legal Notice & Platform Disclaimer |
| `/ai-visibility-framework/` | `ai-visibility-framework.html` | Framework page · local refined · paste to WP |
| `/appraisal-magnet-system/` | `appraisal-magnet-system.html` | Framework page · local refined · paste to WP |
| `/listing-pipeline-framework/` | `listing-pipeline-framework-page.html` | Framework page · local refined · paste to WP |
| `/vendor-velocity-system/` | `vendor-velocity-system.html` | Framework page · local refined · paste to WP · **keep slug** |
| `/sitemap/` | — (live WP) | Sitemap |
| `https://app.digitalgate.com.au/login` | — | **Client Portal** (header/footer CTA — not `/client-portal/`) |

**External app (not WP redirects):**

| URL | Role |
|-----|------|
| `https://app.digitalgate.com.au/onboarding` | Gen 2 self-serve checklist |
| `https://app.digitalgate.com.au/command` | Command Centre |

---

## Redirects required

| Old URL | New URL | Type | Notes |
|---------|---------|------|-------|
| `/services/` | `/pricing/` | 301 | Agency hub removed · seeded in `DG_SEO_Redirects` |
| `/services/*` | `/pricing/` or `/pricing#professional-services` | 301 | Old agency child pages (SEO, ads, retainers) · verify live slugs in WP |
| `/free-agency-audit/` | `/contact/#platform-consultation` | 301 | Was hero/pricing CTA · seeded to `/contact/` — **update target** to consultation anchor |
| `/strategy-session/` | `/contact/#platform-consultation` | 301 | Public/campaign traffic · **Exception:** keep page live if client portal booking must stay at this slug (see below) |
| `/beta/` | `/founding-customers/` | 301 | Soft-deprecate old programme slug · `beta-program-page.html` is interim redirect HTML |
| `/founding/` | `/founding-customers/` | 301 | Only if `/founding/` exists on WP — do not publish both |
| `/founding-application/` | `/founding-customers/#application` | 301 | Do not maintain a separate application page |
| `/platform/` | `/#platform-overview` | 301 | Optional · nav uses homepage anchor, not standalone page |
| `/growth-systems/` | `/pricing/#customer-success` | 301 | Legacy product category removed from nav |
| `/growth-systems/*` | `/pricing/#customer-success` | 301 | Foundation / Authority / Partner retainers → support & success section |
| `/customer-account/` | `/client-account/` | 301 | Handled by DG Platform plugin (`legacy_redirects`) |
| `/system-pages/client-portal/` | `/client-portal/` | 301 | Plugin v10.34+ |
| `/system-pages/client-dashboard/` | `/client-dashboard/` | 301 | Plugin v10.34+ |
| `/system-pages/customer-account/` | `/client-account/` | 301 | Plugin v10.34+ |
| `/system-pages/client-account/` | `/client-account/` | 301 | Plugin v10.34+ |
| `/system-pages/client-reports/` | `/client-reports/` | 301 | Plugin v10.34+ |
| `/reports/` | `/client-reports/` | 301 | If old slug exists |

### Strategy session — choose one deploy path

| Option | Action |
|--------|--------|
| **A (recommended for marketing)** | 301 `/strategy-session/` → `/contact/#platform-consultation` · update `client-dashboard-oxygen.html` portal link to contact anchor or in-app booking |
| **B (keep portal deep link)** | Retain `/strategy-session/` with `strategy-session-page.html` · **no public nav links** · do not use for new CTAs |

Repo position (`WEBSITE-MASTER-AUDIT.md`): Option A for public; Option B only if logged-in clients rely on bookmarked URL.

---

## Unlist / noindex

Legacy ops pages — **remove from nav/sitemap**; optional redirect for anonymous traffic.

| URL | Action | Notes |
|-----|--------|-------|
| `/client-portal/` | noindex · unlist | Login rendered by DG Platform plugin · not in header (header → `app.digitalgate.com.au/login`) |
| `/client-dashboard/` | noindex · unlist | Paste `client-dashboard-oxygen.html` · logged-in only |
| `/client-account/` | noindex · unlist | Paste `client-account-oxygen.html` |
| `/client-reports/` | noindex · unlist | Paste `client-reports-oxygen.html` |
| Old agency **Services** children | 301 → `/pricing/` | Unlist from WP menus · redirect if indexed |

**Optional anonymous redirect:** `/client-dashboard/`, `/client-account/`, `/client-reports/` → `/client-portal/` or `https://app.digitalgate.com.au/login` (302) when not logged in — plugin may already guard.

---

## Pending

| Item | URL | Status |
|------|-----|--------|
| **Terms & Conditions Oxygen paste** | `/terms-conditions/` | Repo draft: `terms-page.html` (12 August 2026) · **do not replace live** until Ben / legal review (`LEGAL-PACK.md`) |
| **Industry landing pages** | `/industries/real-estate/` etc. | Not in repo · create in Oxygen when ready |
| **Standalone `/platform/` page** | `/platform/` | Not in repo · homepage `#platform-overview` is canonical for now |
| **Thank-you / post-Stripe** | `/thank-you/`, `/onboarding-thank-you/` | Referenced in Stripe/onboarding docs · verify live |
| **REA/claims framework pages** | `/ai-visibility-framework/` etc. | **No redirect** — local refined HTML in repo · paste to WP when ready |

---

## Implementation notes

### WP Redirection plugin
- Import table above as **301** unless noted (302 only for temporary campaign swaps).
- Enable **Preserve query string** for `/free-agency-audit/` and UTM-tagged links.
- Test with and without trailing slash (WP usually normalises; add both rules if needed).

### DG Platform built-in redirects
`includes/seo/class-seo-redirects.php` seeds:
- `/services` → `/pricing/`
- `/free-agency-audit` → `/contact/` (**update to `/contact/#platform-consultation`** after deploy)

Client legacy paths handled in `includes/class-client-portal.php` + `class-site-portal-config.php` (`legacy_redirects`).

### Cloudflare bulk
- Page Rules or Redirect Rules: match `digitalgate.com.au/services*` → `/pricing/` (301).
- Bulk redirect CSV: columns `Source,Target,Status` — one row per old slug.

### 301 vs 302
- **301:** Permanent repositioning (services → pricing, agency audit → contact, `/founding/` → `/beta/`).
- **302:** Temporary A/B or “coming soon” only — not for BOS revamp.

### Anchor redirects
- WP Redirection and Cloudflare often **strip or ignore hash fragments** on redirect targets. For `/free-agency-audit/` → consultation section, landing on `/contact/` is acceptable; page hero CTA scrolls to `#platform-consultation`.
- Prefer **in-page anchor links** in HTML over redirect rules when linking from site chrome.

### Post-deploy QA checklist
1. Header/footer: no `/services/`, no `/strategy-session/`, no Growth Systems nav.
2. All “Become a Founding Customer” → `/beta/`.
3. All “Book Platform Consultation” → `/contact/#platform-consultation`.
4. Resources “AI Platform Discovery” → `/discover/`.
5. Client Portal → `https://app.digitalgate.com.au/login`.
6. Framework footer links load (unchanged).
7. Logged-in client: dashboard → strategy/booking link still works (if Option B).

---

## Summary counts

| Category | Count |
|----------|------:|
| **Retain (canonical)** | 16 WP paths + 2 app URLs |
| **Redirects required** | 14 rules (+ `/services/*` wildcards · strategy session option) |
| **Unlist / noindex** | 4 client ops paths + agency Services children |
| **Pending** | 5 items |
