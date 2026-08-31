# Founding 10 journey — implementation plan

**Status:** Implementation in this plugin (WP routes as the working Gen 2 stand-in). **Do not ship live funnel changes** until accept, setup, and Stripe trial proofs pass. Live `/founding-customers/` is unchanged.  
**Approved architecture date:** 31 August 2026  
**Canonical invite URL:** `https://digitalgate.com.au/founding-customers/`  
**Principle:** Invitation → Explore → Discovery → Decision → Formal Offer → Acceptance → Onboarding → Trial → Implementation → Go Live. Application ≠ Acceptance. Terms are agreed only after DigitalGate offers a place and the prospect chooses to proceed.

This plan turns the approved architecture into route/component work. It does **not** change Founding 10 commercial rules (published pricing, 10 places, programme benefits, 20% × 12 month referral, annual ≈ 10 months, Professional Services not included unless in the written offer). It does **not** rewrite `https://digitalgate.com.au/founding-customer-terms/`.

---

## 1. Target journey (v1)

```
Personal invite email
    →  /founding-customers/          Invitation + Explore (no Terms checkbox, no payment)
    →  /apps/  /pricing/  screenshots  (optional /discover/  audit)
    →  /contact/#platform-consultation   Discovery / demo (human)
    →  Written offer by email
    →  app.digitalgate.com.au/founding/accept/[token]   Acceptance + Founding Terms
    →  app.digitalgate.com.au/founding/setup            Full Gen 2 onboarding
    →  Stripe Checkout Session (14-day trial, card now, charge later)
    →  Implementation → Go Live
```

**Nick (now, before build):** do not send `#application`. Send public research URLs + book discovery. See §10.

---

## 2. Route map after implementation

| URL | After change | Owner |
|-----|--------------|--------|
| `/founding-customers/` | Canonical invite + research page. Journey explained. CTAs: Explore / Book discovery. **No required Terms agreement.** | Source now in `marketing/pages/founding-customers-page.html`. Local preview: `/founding-customers-preview/`. Live paste is a later switch. |
| `/founding-customers/#application` | Either removed, or demoted to a non-binding “register interest / request discovery” with **no** `agree_founding_terms`. Must not be the primary CTA. | Same page |
| `/founding/`, `/founding-application/`, `/beta/` | Keep 301 → `/founding-customers/` (no `#application`) | Existing redirects |
| `/founding-customer-terms/` | Unchanged legal document. Linked as **read-only** on invite page; required checkbox only on accept. | Legal (do not rewrite) |
| `/apps/`, `/pricing/`, `/pricing/#screenshots` | Keep. Founding CTAs stop pointing at `#application`. | This repo HTML + live paste |
| `/discover/` | Keep. Results CTA must **not** go to `/onboarding/` or `#application`. | `discovery-form.js` |
| `/contact/`, `/strategy-session/` (301 → contact) | Keep as Discovery booking. | Existing |
| `https://audit.digitalgate.com.au/` | Keep as optional research. | Existing |
| `/onboarding/` | Alias only. Accepted + signed-in → `https://app.digitalgate.com.au/founding/setup`. Others → `/founding-customers/`. No WP questionnaire. | WP stub + Gen 2 |
| `app.digitalgate.com.au/founding/setup` | **Build.** Full Founding onboarding. | `dg-platform-web` |
| `app.digitalgate.com.au/founding/accept/[token]` | **Build.** Secure accept + Terms checkbox. | `dg-platform-web` |
| `app.digitalgate.com.au/founding/offer/[id]` | **Out of scope for v1.** Revisit after a handful of customers. | Later |
| `app.digitalgate.com.au/onboarding` | 301 → `/founding/setup` (do not revive as a second system). | `dg-platform-web` |
| `/signup` | Unchanged generic plan/App picker. Not Founding entry. | `dg-platform-web` |
| `/signup/account` | Reuse after Acceptance to create login. | `dg-platform-web` |
| `/login` | Unchanged. | `dg-platform-web` |
| `buy.stripe.com/…` Payment Links | **Do not use for Founding 10** until trial is verified. Keep for later self-serve if desired. | Stripe |
| `/thank-you/`, `/onboarding-thank-you/` | Already 404. Do not restore as the Founding success path. | Obsolete |

---

## 3. Workstreams

Work spans **three places**. This checkout only contains the WordPress plugin + marketing HTML sources. The live Founding page and Gen 2 app live elsewhere.

| # | Workstream | Repo / system | Ships live? |
|---|------------|---------------|-------------|
| A | Invite/explore page rewrite + CTA cleanup | Live `/founding-customers/` (Gen 2 website renderer) + this repo’s marketing HTML | Yes, after review |
| B | Stop accidental contract/onboarding leaks | This repo (`dg-platform`) | Yes, after review |
| C | Accept link + `/founding/setup` + trial Checkout | `dg-platform-web` (not in this workspace) | Yes, after review |
| D | Stripe product/trial audit + webhook state | Stripe Dashboard + this repo `DG_Stripe_Billing` + Gen 2 webhook | Yes, after review |
| E | Nick / first invites | Process only | Immediate (no code) |

**Do not merge `/signup` into `/founding/setup`.**

---

## 4. Workstream A — `/founding-customers/` becomes invite + explore

### 4.1 Source of truth problem

`founding-customers-page.html` is now in this checkout (invite + explore, no Terms checkbox). `founding-customer-terms.html` is still not rewritten — live Terms stay unchanged. Local preview is `/founding-customers-preview/`. Do not paste over live `/founding-customers/` until the test path in `FOUNDING-10-TEST-PATH.md` passes.

### 4.2 Page must communicate (not just hide the form)

Keep programme commercial copy (cohorts, published pricing, referral 20% × 12 months, exclusions). Rewrite the **journey and CTAs** so the page is an invitation to evaluate, not an application to contract.

Required sections (order):

1. **Invitation** — “You’ve been invited to consider Founding 10.” No commitment, no payment, no agreement on this page.
2. **What Founding 10 is** — 10 places, access/influence, published pricing (reuse existing cohort/benefits copy).
3. **Explore DigitalGate** — links into `/apps/`, `/pricing/`, screenshot grid (reuse existing homepage/pricing shots), optional audit + `/discover/`.
4. **14-day trial** — card collected later; **$0 for 14 days**; billing starts after trial. Monthly or yearly. Do not imply they start a trial from this page.
5. **How it works** (replace current Apply → Discovery → Acceptance → Onboarding → Platform):

   Invitation → Explore → Discovery → Decision → Formal Offer → Acceptance → Onboarding → 14-day trial → Implementation → Go Live

6. **Book a discovery / demo** — primary CTA → `https://digitalgate.com.au/contact/#platform-consultation` (and/or existing booking widget). Secondary: View apps, View pricing, Read programme terms (read-only).

### 4.3 What happens to `#application`

| Option | Use |
|--------|-----|
| **Preferred** | Remove the apply form and Terms checkbox entirely. “Register interest” = book discovery (contact form) or a short name/email **without** `agree_founding_terms`. |
| Acceptable | Keep a collapsed “Request a conversation” form that posts to `/inc/send-dg-enquiry.php` **without** required Terms. Copy must say this is **not** acceptance and does **not** start a contract. |

**Remove:** `agree_founding_terms` required checkbox, “By submitting you agree to the Founding Terms”, “Apply for Founding 10” as the hero/header CTA.

**Header CTA (live):** change “Apply for Founding 10 → `#application`” to “Explore Founding 10 → `/founding-customers/`” or “Book a discovery → `/contact/#platform-consultation`”.

### 4.4 This-repo marketing CTA list (paste after page rewrite)

These still point at the old gates and must be updated in the same release:

| File | Current | New |
|------|---------|-----|
| `marketing/pages/homepage.html` | `#application` “Apply for Founding 10” | `/founding-customers/` “Explore Founding 10” (or Book discovery) |
| `marketing/pages/pricing-page.html` | `#application` + `hero_trial` / `cta_trial` → founding-customers | Same; do **not** overwrite with Stripe. Founding buttons must not say Apply. |
| `marketing/pages/header.html` | “Start Free Trial” → `/onboarding/` | “Explore Founding 10” → `/founding-customers/` |
| `marketing/pages/footer.html` | “Start Free Trial” → `/onboarding/` | `/founding-customers/` or `/contact/#platform-consultation` |
| `marketing/pages/about-page.html` | “Start Free Trial” → `/onboarding/` | `/founding-customers/` |
| `marketing/pages/digital-business-card.html` | “Start Free Trial” → `/onboarding/` | `/founding-customers/` |

Update `REDIRECT-MAP.md` funnel text to the approved journey. Change `/founding-application/` 301 target from `#application` to `/founding-customers/`.

---

## 5. Workstream B — this repo: leak plugs + `/onboarding/` alias

### 5.1 Discovery must not start a trial/contract

`assets/js/discovery-form.js` (~line 107) renders **Start Free Trial → `/onboarding/`**. Replace with Explore Founding 10 → `/founding-customers/` and keep **Book consultation → `/contact/`**.

### 5.2 `/onboarding/` alias (no second system)

Do **not** paste `onboarding-form.html` back onto the public page.

Implement a small WP redirect (prefer `DG_SEO_Redirects` or a dedicated `template_redirect` in `DG_Client_Onboarding`):

| Visitor | Destination |
|---------|-------------|
| Accepted Founding user, signed in (token/session/role to be defined with Gen 2) | `https://app.digitalgate.com.au/founding/setup` (302) |
| Signed in but not accepted | `/founding-customers/` or app dashboard |
| Anonymous | `/founding-customers/` (302) — never the old form, never a 404 |

Until Gen 2 `/founding/setup` exists, **do not** 302 `/onboarding/` to a 404. Sequence: build setup first, then flip the alias.

Live stub currently says onboarding lives at `/founding/setup` (404) and still offers “Apply for Founding 10”. Replace that stub when setup is live.

### 5.3 Keep WP form as field spec only

| Keep | Do not |
|------|--------|
| `marketing/pages/onboarding-form.html` | Re-publish as public UX |
| `DG_Client_Onboarding` field lists (`$scalar_fields`, `$array_fields`) | Require T&Cs / SMS on Gen 2 setup |
| `ONBOARDING-FORM.md` marked **reference only** | Point Stripe welcome email at the old public form once setup exists |

`DG_Stripe_Billing` welcome email still links `home_url('/onboarding/')` and `app.digitalgate.com.au/signup/account`. After setup ships, accepted Founding mail should use `/founding/setup` (and `/founding/accept` only before they have agreed).

### 5.4 Portal templates

`class-client-portal.php` and Oxygen client templates pass `onboarding_url` → `/onboarding/`. That becomes correct **once** `/onboarding/` aliases to setup. No need for a second URL in those templates.

---

## 6. Workstream C — Gen 2 (`dg-platform-web`)

Not in this workspace. Implement there; this section is the contract for that repo.

### 6.1 `GET /founding/accept/[token]` (v1 offer/accept)

v1: **written offer by email → this link.** No in-app offer CMS.

- Token is single-use or expiry-bounded, bound to email + offer payload (plan, apps, org placeholder, invitee name).
- Page copy: this is **formal acceptance** of a Founding 10 place DigitalGate has offered.
- Required checkbox: existing Founding Customer Terms (link/embed `https://digitalgate.com.au/founding-customer-terms/`) plus general T&Cs / Privacy as already referenced by those Terms.
- On submit: mark offer accepted, create/attach user + organisation, then send to `/signup/account` (if no login) or `/founding/setup`.
- Unauthenticated: allow accept with email match, then force account create.

**Do not** show this page from the public invite.

### 6.2 `GET/PATCH /founding/setup` — full Founding onboarding

Proper Gen 2 UX (stepper / sections). **Not** the WP HTML pasted in.

**Auth:** accepted Founding customer only. Others → `/founding-customers/` or login.

**Pre-populate** plan + Apps from the accepted offer. Allow change with a note that commercial confirmation may be required if they add paid Apps.

Field groups (from WP form — spec only):

| Group | Fields (WP names) |
|-------|-------------------|
| Business profile | `business_name`, `abn`, `gst_number`, `industry_license_number`, address, `phone`, `business_email`, `business_hours` |
| Contact | `contact_name`, `position`, `contact_phone`, `contact_email`, `contact_method` |
| About / goals | `about_business`, `services`, `service_areas`, `ideal_customer`, `goals[]` |
| Team | `team_members` |
| Brand / website | `logo`, `brand_colours`, photos, `website_url`, `desired_domain`, `domain_registrar`, `website_platform`, `hosting_company` (no passwords) |
| Google / SEO | `google_assets[]`, `gmb_email`, `gmb_link` |
| Systems / connectors | `systems[]` |
| Plan | `platform_tier` — from offer |
| Apps | `purchased_apps[]`, `purchased_premium[]`, `purchased_addons[]` — from offer |
| Implementation | `deliverables[]`, `purchased_services[]`, `special_requests`, `referral_source` |

**Do not** include accuracy/T&Cs/SMS required checkboxes. Agreement already happened at accept.

Persist on the Gen 2 organisation (Neon). Optionally dual-write to WP `POST /wp-json/digitalgate/v1/onboarding` for existing CRM contacts — **optional**, not a blocker.

### 6.3 After setup: Stripe Checkout Session

Create session in Gen 2 (not Payment Links):

- `mode: subscription`
- `subscription_data.trial_period_days: 14`
- `payment_method_collection: always` (card now, $0 during trial)
- Support **monthly and yearly** prices (yearly ≈ 10 months — existing commercial rule)
- Metadata: `dg_founding=true`, `dg_organisation_id`, `dg_plan`, `dg_platform_tier`, app keys, `dg_offer_id`

Success URL: app dashboard or a short “trial started / implementation next” screen (do not use 404 `/thank-you/`).

### 6.4 `/signup` stays separate

No Founding gating on `/signup`. Copy may say Founding customers should use their accept/setup links. “Continue to checkout” on `/signup` remains the later self-serve path and must not be the Founding 10 entry.

---

## 7. Workstream D — Stripe 14-day trial (must verify, not assume)

Marketing and Founding Terms say a trial “where offered.” This plugin has **no `trial_period_days`**. Welcome email says “Thank you for your purchase.” Payment Links were not verifiable from HTML (Stripe Checkout is client-rendered).

### 7.1 Dashboard audit (before calling the flow production-ready)

For **each** Platform price used by Founding (Starter / Growth / Scale, monthly **and** yearly) and each App price that can be on the first subscription:

1. Open the Price in Stripe → confirm trial length, or that Checkout Session will set `trial_period_days: 14`.
2. Complete a **test-mode** checkout for monthly and yearly.
3. Confirm: subscription status **`trialing`**, invoice $0 (or $0 due), `trial_end` ≈ now + 14 days, first paid invoice after `trial_end`.
4. Confirm card is stored (`payment_method` on the subscription).
5. If a Payment Link charges today, **do not** send Founding customers to it.

### 7.2 Webhook / state

Today `DG_Stripe_Billing::handle_checkout_completed` treats `checkout.session.completed` and tags **Payment Received**. `session_skip_reason` skips `payment_status === unpaid` unless a subscription exists.

Required behaviour:

| Event / state | Treat as |
|---------------|----------|
| Checkout complete, subscription `trialing`, amount 0 | **Success** — provision / attach org. Tag e.g. `Trialing` / `Founding Accepted`, **not** `Payment Received` |
| `customer.subscription.updated` → `active` after trial | First bill succeeded — then `Payment Received` is appropriate |
| `invoice.payment_failed` after trial | Dunning / ops, do not undo onboarding |

Implement in **Gen 2** (it owns Checkout Sessions) and align WP `DG_Stripe_Billing` if WP still receives the same webhook. Listen for `customer.subscription.created` / `updated` with `status=trialing`, not only paid checkout.

---

## 8. Implementation order (no live breakage)

| Phase | What | Live risk |
|-------|------|-----------|
| **0** | This plan reviewed. Nick: research + discovery only (§10). | None |
| **1** | Stripe test-mode trial proof (monthly + yearly). Fail = do not ship Founding checkout. | None |
| **2** | Build Gen 2 `/founding/accept/[token]` + `/founding/setup` + Checkout Session. `/onboarding` 301 → setup. | None until routed |
| **3** | WP: discovery JS, header/footer/homepage/pricing/about/card CTAs, `REDIRECT-MAP.md`. | Low — CTA copy only |
| **4** | Rewrite live `/founding-customers/` (invite + explore + journey). Remove Terms gate. | **This is the live funnel change** — only after 1–3 ready enough that Book discovery works |
| **5** | Flip `/onboarding/` alias to setup. Update Stripe/welcome links. | Medium — must not 404 |
| **6** | Seed first accept tokens; run one internal dry-run; then next Founding invite (not Nick until he has had discovery) | — |

**Do not** ship phase 4 alone (invite page without a working discovery CTA and a clear “no contract on this page” message).  
**Do not** ship phase 5 before `/founding/setup` returns 200.

---

## 9. Acceptance criteria

- Visiting `/founding-customers/` as an invitee: no required Founding Terms checkbox; page states the full journey; primary CTA is explore or book discovery.
- `#application` is not the hero/header gate.
- `/discover/` results do not link to `/onboarding/` or “Start Free Trial.”
- Header/footer do not say Start Free Trial → `/onboarding/`.
- `/onboarding/` never 404s; never shows the old WP questionnaire; accepted signed-in users reach `/founding/setup`.
- `/founding/setup` collects the field groups in §6.2; plan/Apps pre-filled from offer; no Terms checkboxes.
- `/signup` still a generic picker; not required to start Founding research.
- Accept link is the **only** required Founding Terms checkbox.
- Test-mode Stripe: monthly and yearly enter `trialing`, $0 for 14 days, card stored, first charge after trial.
- Webhooks do not require an immediate payment to treat setup as successful.
- `/founding-customer-terms/` body unchanged.
- Founding 10 commercial rules unchanged.

---

## 10. Nick — process (no product wait)

He asked to research without a contract. That is the intended stage 1–3.

Send (or confirm he has):

- `https://digitalgate.com.au/founding-customers/` — tell him the **apply/agree block is not for him**; he can ignore it until the page is rewritten
- `https://digitalgate.com.au/apps/`
- `https://digitalgate.com.au/pricing/`
- Screenshots on pricing/home
- Optional: `https://audit.digitalgate.com.au/` and `/discover/`
- Book discovery: `https://digitalgate.com.au/contact/#platform-consultation`

Do **not** send `#application`, `/onboarding/`, `/founding/setup`, `/signup`, or a Stripe Payment Link.

After discovery, if DigitalGate offers a place: email offer + accept link (once phase 2 exists). Until then, offer stays email-only and acceptance is not a website form.

---

## 11. Out of scope (v1)

- New `/founding-10` URL
- In-app `/founding/offer/[id]` offer admin
- Rewriting Founding Terms or changing published pricing / referral math
- Merging `/signup` into Founding onboarding
- Restoring `/thank-you/` or the public WP onboarding form
- Acquisition Partner (25%) programme

---

## 12. Review checklist for this plan

Before any implementation PR touches live pages:

- [ ] Confirm live Founding HTML will be edited in `dg-platform-web` (and whether to add `founding-customers-page.html` to this repo)
- [ ] Confirm discovery booking URL (`/contact/#platform-consultation` vs a calendar embed)
- [ ] Confirm who generates v1 accept tokens (manual admin / script / Stripe-free internal tool)
- [ ] Confirm Stripe is available in test mode for phase 1
- [ ] Approve phase order in §8
