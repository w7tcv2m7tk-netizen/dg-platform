# Founding 10 journey — implementation plan

**Status:** Architecture correction. WordPress is **out of scope** as an application/runtime for this journey. Implement and test in **`dg-platform-web` / Gen 2** only.  
**Approved architecture date:** 31 August 2026  
**Canonical invite URL:** `https://digitalgate.com.au/founding-customers/`  
**Canonical product host:** `https://app.digitalgate.com.au`  
**Principle:** Invitation → Explore → Discovery → Decision → Formal Offer → Acceptance → Onboarding → Trial → Implementation → Go Live. Application ≠ Acceptance. Terms are agreed only after DigitalGate offers a place and the prospect chooses to proceed.

This plan does **not** change Founding 10 commercial rules (published pricing, 10 places, programme benefits, 20% × 12 month referral, annual ≈ 10 months, Professional Services not included unless in the written offer). It does **not** rewrite `https://digitalgate.com.au/founding-customer-terms/`.

---

## 0. Architecture lock — WordPress is not the product

```
Public website / DigitalGate marketing
        →  DigitalGate Gen 2

Customer
        →  Gen 2 account / organisation

Founding 10 acceptance
        →  app.digitalgate.com.au/founding/accept/[token]

Founding onboarding
        →  app.digitalgate.com.au/founding/setup

Plan + Apps
        →  Gen 2 (organisation Business Profile, Apps, permissions)

Stripe subscription
        →  Gen 2  →  Stripe
        →  Gen 2 webhook  →  organisation / subscription state

Implementation
        →  Gen 2 / Operator OS

Go Live
        →  Gen 2
```

**There is no WordPress involvement in this customer journey.**

| Allowed use of this `dg-platform` repo | Forbidden |
|----------------------------------------|-----------|
| Historical field list (`onboarding-form.html`, `DG_Client_Onboarding` field names) as a **checklist of information we used to collect** | WP routes, forms, or admin screens as the Founding product |
| Historical commercial numbers (published prices, annual ≈ 10 months) | WP Stripe Checkout / WP webhooks in the middle of Founding |
| Marketing HTML **sources** for the public website (`founding-customers-page.html`, homepage/pricing/header CTAs) | Claiming the journey is “implemented” because a local WP stand-in returned 200 |
| Optional later 301: `digitalgate.com.au/onboarding/` → Gen 2 `/founding/setup` (compatibility only, after Gen 2 returns 200) | A second WP onboarding system, alias flag, or questionnaire |
| Migration/reference for old CRM contacts | Creating or attaching the Founding organisation in WordPress |

A local WordPress environment is **not** an acceptable substitute for Gen 2.

Do **not** rebuild Gen 1 patterns (PHP templates, `wp_options` offer tokens, WP contact tags, WP Stripe handlers) inside Gen 2. Use Gen 2 authentication, organisation model, Business Profile, Goals, Team, connected systems, Apps, subscription/commerce, implementation records, permissions, Neon, and Operator OS.

---

## 1. Target journey (v1)

```
Personal invite email
    →  https://digitalgate.com.au/founding-customers/     Invite + Explore (no Terms checkbox, no payment)
    →  /apps/  /pricing/  screenshots  (optional /discover/  audit)
    →  /contact/#platform-consultation                     Discovery / demo (human)
    →  Written offer by email
    →  app.digitalgate.com.au/founding/accept/[token]      Accept Founding Customer Terms
    →  app.digitalgate.com.au/founding/setup               Gen 2 Founding onboarding
           Business Profile → Goals → Team → Systems
           → confirm Plan + Apps
           → Gen 2 creates Stripe Checkout Session
    →  Stripe 14-day trial (card now, $0 now, charge when trial ends)
    →  Gen 2 webhook  →  organisation subscription = trialing
    →  Implementation (Operator OS)
    →  Go Live
    →  trial ends  →  Stripe active  →  Gen 2 subscription = ACTIVE / paid
```

**Nick (now, before live funnel switch):** do not send `#application`. Send public research URLs + book discovery. See §10.

`/signup` stays the generic self-serve picker. It is **not** Founding onboarding.

---

## 2. One onboarding system

There is **one** Founding onboarding system:

`https://app.digitalgate.com.au/founding/setup`

| URL | Role after this work |
|-----|----------------------|
| `app.digitalgate.com.au/founding/accept/[token]` | Formal acceptance. Creates/attaches the user to the **correct Gen 2 organisation**. Required Founding Customer Terms checkbox. |
| `app.digitalgate.com.au/founding/setup` | The Gen 2 wizard. Auth + organisation + Business Profile + Goals + Team + systems + Plan + Apps + Stripe. |
| `app.digitalgate.com.au/onboarding` | Compatibility alias → `/founding/setup`. Do not keep a second wizard. |
| `digitalgate.com.au/onboarding/` | Compatibility 301 → `https://app.digitalgate.com.au/founding/setup` **only after** that route is the real wizard and returns 200. Until then, do not point it at a 404. |
| `digitalgate.com.au/signup` / `app…/signup` | Unchanged generic plan/App picker. Not Founding entry. |

Do not build: WP `/founding/setup/`, WP `/founding/accept/`, WP `/founding-customers-preview/`, or `DG_Founding_*` plugin classes.

---

## 3. What already exists in `dg-platform-web` (use it; do not reimplement in WP)

Inspected on `w7tcv2m7tk-netizen/dg-platform-web` HEAD. This checkout does **not** contain that repo — implementation PRs belong there.

| Piece | Location today | Notes |
|-------|----------------|-------|
| Founding setup route | `src/app/(shell)/founding/setup/page.tsx` | Currently claims invite, then **redirects** to `/founding/agreement` or `/onboarding`. Must become the real wizard, not a bounce page. |
| Founding agreement | `src/app/(shell)/founding/agreement/` | Closest existing Terms step. Promote/reshape to `/founding/accept/[token]`. |
| Public invite accept API | `src/app/api/public/founding-invite/accept/` | Attach invite → organisation. Must not create a duplicate org. |
| Gen 2 onboarding steps | `packages/platform-core/src/onboarding/gen2-journey.ts` | Welcome → identity → Business Profile → Goals → plan → Apps → cadence → Stripe → connect → checklist → implementation. |
| Founding domain | `packages/platform-core/src/founding/` | Invitations, onboarding record, implementation, pipeline, Command workspace. |
| Business Profile | `packages/platform-core/src/org/business-profile-types.ts` | Persist here — not a form blob. |
| Stripe Checkout | `packages/platform-core/src/billing/platform-stripe.ts` + `src/app/api/v1/billing/checkout` | Already `trial_period_days` from `BILLING_COMMERCIAL_CONFIG.trialDays` (14), `payment_method_collection: always`. **Not** Payment Links. |
| Stripe webhook | `src/app/api/webhooks/stripe/route.ts` | Must drive Gen 2 subscription state. |
| Commercial statuses | `packages/platform-core/src/billing/subscription-types.ts` | `TRIALING`, `ACTIVE`, … |
| Operator OS / Command | `src/app/(shell)/command/founding`, delivery onboarding | Implementation after trial starts. |
| Marketing page source | `dg-platform-web/marketing/pages/founding-customers-page.html` | Live public page is Gen 2-rendered. |

### 3.1 Known Gen 2 defect to fix (do not copy WP tags)

`provisionFromPlatformCheckout` currently sets Founding / exempt orgs to organisation `status: "active"` and `billing.subscriptionStatus: "active"` on checkout, even when Stripe is in a 14-day trial.

That is the opposite of the required model:

| Stripe | Gen 2 organisation / subscription | Must not |
|--------|-----------------------------------|----------|
| Checkout complete, subscription `trialing`, amount due $0 | `TRIALING`. Card on file. Org + Stripe customer + subscription IDs linked. | Label as paid, `ACTIVE`, or “Payment Received” |
| `customer.subscription.updated` → `active` (trial ended, first invoice paid) | `ACTIVE`. Then it is correct to record payment. | Leave `TRIALING` stuck |
| `invoice.payment_failed` after trial | Dunning / `PAYMENT_FAILED` — do not undo onboarding | |

Staff/CRM labels such as “Payment Received” (if you keep them at all) belong on the **Gen 2** organisation/subscription after a real paid `active` state — never during `trialing`.

---

## 4. Public marketing (not the product)

The public site invites people to explore. It is not onboarding.

Keep research/demo content (`/apps/`, `/pricing/`, `/discover/`, `/contact/`, `/strategy-session/`, audit). Remove the contractual application gate (no required `agree_founding_terms` on `/founding-customers/`).

This repo may hold **HTML sources** for chrome/CTAs. Shipping those sources is not “Founding 10 implemented.” Live `/founding-customers/` stays unchanged until Gen 2 accept + setup + Stripe trial work.

`/founding-application/` 301 → `/founding-customers/` (no `#application`).

---

## 5. Workstreams (Gen 2 first)

| # | Workstream | Where | Live? |
|---|------------|-------|-------|
| A | `/founding/accept/[token]` — Terms + attach Gen 2 org | `dg-platform-web` | After review |
| B | `/founding/setup` — real Gen 2 wizard (reuse Business Profile, Goals, Team, systems, Apps, commerce) | `dg-platform-web` | After review |
| C | Gen 2 Stripe Checkout Session + webhook lifecycle (`trialing` → `active`) | `dg-platform-web` + Stripe test mode | After monthly **and** yearly proof |
| D | Command / Operator OS implementation after trial | `dg-platform-web` | After C |
| E | Marketing invite page + CTA leak plugs | Public site sources (`dg-platform-web` marketing seed + this repo HTML) | **Last** — only when A–C work |
| F | Compatibility 301 `digitalgate.com.au/onboarding/` → Gen 2 `/founding/setup` | DNS/redirect only | After `/founding/setup` returns 200 as the wizard |
| G | Nick | Process | Immediate; no `#application` |

**This `dg-platform` plugin is not workstream A–D.**

Old WP questionnaire: read `marketing/pages/onboarding-form.html` / `DG_Client_Onboarding` field names only to check we have not forgotten useful information. Map those into Gen 2 Business Profile / Goals / Team / systems. Do not port the form.

---

## 6. Gen 2 build contract

### 6.1 `GET/POST /founding/accept/[token]`

- Token bound to the written offer (email, plan, Apps, organisation placeholder).
- Unauthenticated: accept with email match, then create Gen 2 login and attach to **one** organisation.
- Authenticated: attach current user to that organisation; **no duplicate organisation**.
- Required checkbox: existing Founding Customer Terms (link `https://digitalgate.com.au/founding-customer-terms/`) plus general T&Cs / Privacy as those Terms already require.
- Success → `/founding/setup`.
- Do not show this page from the public invite.

Reuse `claimFoundingInvite`, founding onboarding records, and `/founding/agreement` behaviour — do not add a WP token store.

### 6.2 `GET /founding/setup`

Genuine Gen 2 route. Must return **200** as the wizard (not a redirect-only shim) before any public `/onboarding/` alias is flipped.

Use existing Gen 2 objects:

- Session / permissions
- Organisation
- Business Profile
- Goals
- Team
- Connected systems
- Apps + plan entitlements
- Implementation record
- Operator OS

Pre-populate plan + Apps from the accepted offer. Customer confirms before Stripe.

Do **not** include a second Terms checkbox. Agreement already happened at accept.

Field *coverage* to check against the old WP form (names are historical only): business identity, ABN, address, contacts, about/goals, team, brand/website, Google/SEO, systems, plan, Apps, implementation notes. Persist on Neon, not as a WP post.

### 6.3 Stripe (Gen 2 only)

`createPlatformCheckoutSession` already supports 14-day trial + card collection. Founding 10 must use that path.

- No Payment Links
- Monthly and yearly (annual ≈ 10 months via `BILLING_COMMERCIAL_CONFIG`)
- Metadata: organisation id, founding flag, plan, Apps, offer/invite id
- Success: Gen 2 “trial started / implementation next” — not WP `/thank-you/`

Webhook (`src/app/api/webhooks/stripe`): update the **same** organisation’s subscription row. Link `cus_`, `sub_`, organisation id. No WP `DG_Stripe_Billing` in this path.

### 6.4 `/signup` stays separate

Copy may say Founding customers should use their accept/setup links. Self-serve checkout on `/signup` is a later path, not Founding 10 entry.

---

## 7. Implementation order

| Phase | What | Where |
|-------|------|-------|
| **0** | This corrected plan. Nick: research + discovery only. | Process |
| **1** | Gen 2: accept route + org attach (no duplicates) | `dg-platform-web` |
| **2** | Gen 2: `/founding/setup` is the wizard (200), using existing models | `dg-platform-web` |
| **3** | Gen 2 Stripe test-mode proof: monthly **and** yearly → `trialing`, $0, card on file | Stripe + Gen 2 |
| **4** | Webhook: `trialing` → `active`; no paid label during trial | `dg-platform-web` |
| **5** | Operator OS implementation / go-live | `dg-platform-web` |
| **6** | Marketing invite rewrite + CTA cleanup live | Public site |
| **7** | 301 `digitalgate.com.au/onboarding/` → Gen 2 setup | Redirect only |

Do **not** ship phase 6 (live funnel) before 1–4.  
Do **not** ship phase 7 before `/founding/setup` returns 200 as the wizard.

---

## 8. Acceptance criteria

- A test customer can complete the §9 path on **Gen 2 staging/test**, not WordPress.
- `/founding/setup` is a Gen 2 page that writes Business Profile, Goals, Team, systems, plan, Apps.
- Accept creates/attaches **one** Gen 2 organisation; Stripe IDs land on that org.
- Checkout: card collected, Stripe `trialing`, $0 for 14 days, charge when trial ends.
- Gen 2 state is `TRIALING` during trial — not paid / not `ACTIVE` / not “Payment Received”.
- When Stripe becomes `active`, Gen 2 becomes `ACTIVE`, trial flag removed, payment recorded once.
- No WP plugin class, option, route, or webhook is required for the path to succeed.
- `/signup` still a generic picker.
- `/founding-customer-terms/` unchanged. Commercial rules unchanged.
- Public Founding CTAs (when live-switched) do not send people to `#application` or a WP form.

---

## 9. Final test (Gen 2 staging / test)

See `FOUNDING-10-TEST-PATH.md`. Summary:

Invite → Explore → Discovery → Written offer → Accept Terms → Gen 2 Founding Setup → Business Profile → Goals → Team → Systems → Plan + Apps → Gen 2 Stripe Checkout → 14-day trial → subscription `trialing` → Implementation → Go Live.

Then Stripe lifecycle: `trialing` → `active`, with the state rules in §3.1.

A WordPress contact-tag simulation is **not** this test.

---

## 10. Nick — process (no product wait)

Send research URLs + book discovery. Do **not** send `#application`, WP `/onboarding/`, WP `/founding/setup`, `/signup`, or a Stripe Payment Link.

After discovery, if DigitalGate offers a place: email + Gen 2 accept link (once phase 1 exists).

---

## 11. Out of scope (v1)

- Any Founding 10 runtime inside this WordPress plugin
- New `/founding-10` marketing URL
- Rewriting Founding Terms or changing published pricing / referral math
- Merging `/signup` into Founding onboarding
- Restoring `/thank-you/` or the public WP onboarding form
- Acquisition Partner (25%) programme
- Dual-write of Founding checkout to WP as a blocker

---

## 12. This-repo leftover (not the journey)

If this branch still contains marketing HTML CTA cleanup (`Explore Founding 10` instead of `#application` / WP `/onboarding/`), that is **chrome only**. It does not implement Founding 10.

WordPress Founding stand-in classes, routes, and Stripe handlers that were added as a “testable WP substitute” are **withdrawn**. Do not resurrect them.
