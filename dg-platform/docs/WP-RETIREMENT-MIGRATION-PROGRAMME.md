# WordPress retirement / migration programme

**Status:** Execution plan — not a product build  
**Date:** 31 August 2026  
**Rule:** [ARCHITECTURE-BOUNDARY.md](./ARCHITECTURE-BOUNDARY.md)  
**SoT map:** [GEN1-GEN2-DECOUPLING-AUDIT.md](./GEN1-GEN2-DECOUPLING-AUDIT.md)  
**Does not replace:** Gen 2 `docs/WP-DETACH-BACKLOG.md` (ticket inventory). This programme is the **ordered retirement** of WordPress as a DigitalGate platform.  
**Repos:** work lands in **`dg-platform-web`**. This plugin is the retirement target and field-list archive.

This is not another major product feature. It is the controlled elimination of the WordPress side of DigitalGate until WordPress is only the **customer-site connector**.

---

## Goal

Systematically eliminate WordPress as a DigitalGate source of truth.

| After this programme | WordPress may still be |
|----------------------|------------------------|
| Auth, orgs, onboarding, plans, entitlements, Stripe SaaS, support, booking ops, Founding 10 | A customer’s CMS |
| | A thin WordPress Connector (forms, mirrors, health, iCal) |
| | Historical data / archive |

The goal is **not** to keep both systems working indefinitely.

---

## Hard rules (every ticket)

1. **Move the source of truth and the business logic.** UI-only moves fail the programme.
2. **Do not create a third implementation** while resolving a twin. Extend the existing Gen 2 system. Use WP as a specification. Delete or freeze the WP product path.
3. **Do not rebuild Founding 10 in WordPress.** Founding 10 waits for Priority 1 and Priority 3, then ships entirely in Gen 2.
4. After each migration, run the **DECOUPLED test** below. If it fails, the item is not done.

---

## DECOUPLED test (required after every item)

**Question:** Can this functionality operate with WordPress completely unavailable?

**How to make WP unavailable (all of these):**

1. Stop the WordPress process (`wp server` / php).
2. Unset / blank `DG_API_BASE_URL`, `DG_WP_CONNECTOR_BASE_URL`, and any `DG_WP_*` site lists in the Gen 2 environment under test.
3. Confirm `GET {WP}/wp-json/digitalgate/v1/...` fails (connection refused / timeout).
4. Confirm Gen 2 does **not** fall back to a WP URL (no `fetchPortalMe` WP hop, no `pingApi` OPTIONS `/onboarding`, no Growth Engine entitlement pull).

**Pass → mark `DECOUPLED`.**  
**Fail → not decoupled.** Record the exact remaining call, table, or webhook. Do not mark the item done.

A green UI while WP is still on the request path is **not** decoupled.

---

## Programme order (do not skip ahead)

| # | Item | Why this order | Current verdict |
|---|------|----------------|-----------------|
| **P1** | Gen 2 onboarding | Founding 10 and every new org need a complete, resumable Gen 2 journey. Remaining `fetchPortalMe` / WP POST is the last onboarding twin. | **NOT DECOUPLED** |
| **P2** | Plans + entitlements | Apps must follow Gen 2 subscription state, not WP plan/module flags or Growth Engine push. | **NOT DECOUPLED** |
| **P3** | Stripe (DigitalGate SaaS) | Platform checkout, webhook, and trial state must live only in Gen 2 + Stripe. Required for Founding 10 14-day trial. | **NOT DECOUPLED** |
| **P4** | Support | Org-scoped conversations already exist in Neon; WP inbox is a second SoT and the proven cross-business leak. | **NOT DECOUPLED** |
| **P5** | Public booking | Document first, then move public book-now to StayBooking. Acc guest Stripe travels with this item, not with P3. | **NOT DECOUPLED** |
| **F10** | Founding 10 journey | **Blocked on P1 + P3** (Plan + Apps also need P2). Build only in Gen 2. | **NOT STARTED IN GEN 2 END-TO-END** |

Do not start P2 product work until P1’s WP read/write path is gone.  
Do not start Founding 10 until P1 can complete/resume without WP and P3 can create a 14-day trial without WP.

---

## Shared “no third twin” map

| Twin | Keep | Retire | Do not build |
|------|------|--------|--------------|
| Onboarding wizard | Gen 2 `GEN2_ONBOARDING_STEPS` + `/onboarding` | WP form + `/portal/me` as SoT | A WP Founding/setup engine; a second Gen 2 wizard |
| Plans / Apps | Gen 2 `PlatformSubscription` → entitlements → `settings.apps` | WP `DG_Plan_Registry` as SaaS licence; Growth Engine entitlement push | A third catalogue |
| Stripe SaaS | Gen 2 Checkout Session + `/api/webhooks/stripe` | WP Payment Links + WP `/billing/webhook` for DigitalGate seats | WP Checkout for Founding 10 |
| Support | Neon `SupportConversation` (`clerkUserId` + `organisationId`) | `wp_dg_support_*` + wp-admin inbox | A WP org-id patch “to hold us over” |
| Booking | Gen 2 `StayBooking` / `AccommodationUnit` | WP public book-now as SoT | A new WP booking engine |

WP field lists, emails, and admin copy are **reference**. They are not a runtime to preserve.

---

# Priority 1 — Gen 2 onboarding

**Customer journey (must complete and resume entirely in Gen 2):**

Business Profile → Goals → Team → Systems → Plan + Apps → Implementation

The old WP onboarding form is **reference only**.

**Extend the existing Gen 2 wizard.** Do not invent a second or third wizard. Team and Systems are missing as first-class steps today; add them to `GEN2_ONBOARDING_STEPS`, mapped to Membership and connectors.

### Current DECOUPLED verdict

**NOT DECOUPLED.** Gen 2 still reads WP (`fetchPortalMe` → `GET /portal/me`) when Neon has no portal row, still `OPTIONS` WP `/onboarding` (`pingApi`), and can still merge WP portal state into the org (`syncOrganisationFromPortal` / `POST /api/webhooks/dg-onboarding-sync`). `/founding/setup` is a redirect, not the wizard itself.

### 1. Current WP dependency

| Piece | Where |
|-------|--------|
| Public questionnaire + REST | `class-client-onboarding.php` — `POST digitalgate/v1/onboarding`, admin-post, shortcode |
| Field list / HTML | `marketing/pages/onboarding-form.html` — includes team, platforms, `business_systems` / `systems`, goals |
| Portal profile SoT | `class-client-portal-api.php` — `GET /portal/me` (setup flags, purchased apps, onboarding blob) |
| After-submit push | `DG_Growth_Engine_Sync::on_onboarding_completed` → `POST /api/webhooks/dg-onboarding-sync` `{ email }` |
| State | Contact/org entity meta `onboarding_submission`; WP user/contact/org rows |
| Live URL | `digitalgate.com.au/onboarding/` (stub / old form — do not 301 until Gen 2 wizard is complete) |

### 2. Current Gen 2 implementation

| Piece | Where |
|-------|--------|
| Canonical steps | `packages/platform-core/src/onboarding/gen2-journey.ts` — `welcome` → `business_identity` → `business_profile` → `goals` → `plan` → `apps` → `billing_cadence` → `order_summary` → `stripe` → `connect` → `checklist` → `implementation` |
| Progress | `getGen2OnboardingProgress` / `saveGen2OnboardingProgress` on `org.settings.gen2Onboarding` |
| Profile | `updateOrganisationBusinessProfile` / `getOrganisationBusinessProfile` |
| APIs | `GET`/`PATCH /api/v1/onboarding/gen2` (Neon; can start Checkout) · `POST /api/onboarding` → `capturePublicOnboardingIntent` (Neon) |
| UI | `/onboarding` · `/founding/setup` **redirects** to `/founding/agreement` or `/onboarding` |
| Remaining WP hops | `fetchPortalMe` (`src/lib/dg-api.ts`) — Neon first, **WP fallback** · `pingApi` — `OPTIONS {WP}/onboarding` · `ensureOrganisationOnboardingSync` / `syncOrganisationFromPortal` |

**Gap vs required sequence:** Team and Systems are not first-class steps. Do **not** add a parallel “Founding onboarding” product. Fold Team (memberships) and Systems (connectors) into this same progress object.

### 3. Target Gen 2 architecture

```
Clerk session
    → Organisation
        → settings.profile          (Business Profile)
        → settings.goals            (Goals)
        → Membership[]              (Team)
        → Connector installs        (Systems)
        → settings.gen2Onboarding   (resume cursor + completed steps)
        → settings.apps + plan      (Plan + Apps — written here, owned after P2)
        → Implementation workspace  (existing founding/implementation)
```

- One wizard: `/onboarding` (Founding uses the same wizard after agreement; `/founding/setup` stays a thin gate).
- Resume = reopen `/onboarding` → `currentStep` from Neon. No WP cookie, no `/portal/me`.
- WP HTML is the **field catalogue** for Team / Systems questions only.

### 4. Database / state changes required

| Change | Detail |
|--------|--------|
| Extend `Gen2OnboardingProgress` | Add `team` and `systems` (or `connect`) as first-class completed steps. Bump `version` if the step array changes; migrate in-place existing `org.settings.gen2Onboarding`. |
| Team | Write `Membership` (+ invite emails). Do not store the only copy of team in the progress JSON. |
| Systems | Write connector install / “systems in use” on org settings or connector registry. Progress only records completion. |
| Profile / goals | Already Neon. Stop copying from WP portal. |
| Stop writing | WP `onboarding_submission` meta, WP org create-on-submit, Growth Engine `{ email }` sync as the way progress is restored. |
| One-time | Optional import of leftover WP `onboarding_submission` blobs into Neon profile/goals **once**, keyed by email + org slug. Not a live sync. |

### 5. API changes required

| Change | Detail |
|--------|--------|
| Keep | `GET`/`PATCH /api/v1/onboarding/gen2` as the only customer write path |
| Add fields | PATCH accepts team invites + systems inventory; server persists Membership / connectors, then marks steps complete |
| Remove from request path | `fetchPortalMe` WP branch · `pingApi` WP OPTIONS · `syncOrganisationFromPortal` on page load · `POST /api/webhooks/dg-onboarding-sync` applying WP portal as SoT |
| Public capture | `POST /api/onboarding` stays Gen 2 intent-only (lead). It must not POST to WP. |
| WP REST | `POST digitalgate/v1/onboarding` becomes archive / 410 after cutover — not a product API |

### 6. Migration required

1. Ship Team + Systems on the **existing** Gen 2 wizard (same progress JSON).
2. Prove complete + resume with `DG_API_BASE_URL` unset.
3. One-shot: copy any still-needed WP onboarding meta into Neon for in-flight customers (script in `dg-platform-web`, dry-run first).
4. Stop Growth Engine `push_onboarding_sync` from being required to “see” the customer in Gen 2.
5. Only then 301 `digitalgate.com.au/onboarding/` → Gen 2 `/onboarding` (or Founding setup). **Do not 301 while the wizard is incomplete.**
6. Do not enable WP `/onboarding/` aliases as a compatibility layer.

### 7. What WP code can be retired

After DECOUPLED:

- Product use of `class-client-onboarding.php` REST + admin-post
- `assets/js/onboarding-form.js` enqueue on DigitalGate.com.au
- `GET /portal/me` as onboarding/profile SoT (`class-client-portal-api.php` portal product routes)
- `DG_Growth_Engine_Sync::on_onboarding_completed` / `push_onboarding_sync`
- Public WP questionnaire pages as a product

**Keep as archive:** `onboarding-form.html` field list; historical entity meta (read-only).

### 8. Dependencies / blockers

- Clerk session + Organisation must exist before the wizard (already true for `/onboarding`).
- Plan + Apps **selection** can be stored in progress now; **enforcement** waits for P2.
- Stripe **activation** step can start a Gen 2 Checkout Session now; **WP-free trial correctness** waits for P3.
- Founding 10 **uses** this wizard; it does not get its own WP or third Gen 2 onboarding product.
- Workspace often has only `dg-platform` checked out — implementation still happens in `dg-platform-web`.

### 9. How we test that WP is no longer required

| Test | Pass |
|------|------|
| New org, WP down | Completes Business Profile → Goals → Team → Systems → Plan + Apps → Implementation |
| Kill browser mid-wizard, WP down | Resume lands on `currentStep`; no data loss |
| Founding path | `/founding/setup` → agreement if needed → **same** `/onboarding` wizard |
| `fetchPortalMe` | With Neon empty and WP down, returns unlinked/Neon defaults — **never** hangs on WP |
| `pingApi` | Does not call WP; health is Gen 2-only |
| Network log | Zero requests to `*/wp-json/digitalgate/v1/portal/me` or `/onboarding` |
| `/signup` | Still a generic picker — not Founding onboarding |

### 10. Proposed implementation order (P1)

1. Delete the WP fallback in `fetchPortalMe` (Neon or unlinked only). Stop `pingApi` hitting WP.
2. Remove `syncOrganisationFromPortal` / `ensureOrganisationOnboardingSync` from the request path. Webhook becomes no-op or archive import.
3. Add Team + Systems as first-class steps on `GEN2_ONBOARDING_STEPS` (extend, do not fork). Persist Membership + connectors.
4. Confirm `/founding/setup` only gates agreement, then the same wizard.
5. One-shot historical import if needed.
6. Run DECOUPLED test. Mark P1 **DECOUPLED**.
7. 301 public WP `/onboarding/` **after** step 6.

---

# Priority 2 — Plans + entitlements

**Target:** Gen 2 subscription / plan → Gen 2 entitlements → Gen 2 Apps.  
WordPress must not determine what a DigitalGate organisation owns.

### Current DECOUPLED verdict

**NOT DECOUPLED.** Gen 2 already has `resolveEntitlement` + `settings.apps`, but WP purchases still push `POST /api/webhooks/dg-onboarding-sync`, which re-enters `fetchPortalMe` and `syncOrganisationFromPortal` and can overwrite Apps from WP `purchased_*` fields. `DG_Plan_Registry` still gates leftover WP modules.

### 1. Current WP dependency

| Piece | Where |
|-------|--------|
| Plan catalogue / module gating | `DG_Plan_Registry`, module registry, add-on flags |
| Purchase → Apps | Stripe Payment Link metadata → contact tags → `DG_Growth_Engine_Sync::sync_platform_after_purchase` |
| Portal entitlements | `/portal/me` `purchased_apps` / `purchased_premium` / `platform_tier` |
| Operator view | wp-admin module enablement |

### 2. Current Gen 2 implementation

| Piece | Where |
|-------|--------|
| Commercial entitlement | `packages/platform-core/src/billing/entitlement-resolver.ts` — `PlatformSubscription` → level / capabilities |
| App ids | `resolveEnabledAppIds` / `appIdsFromPlanSelection` on `org.settings.apps` |
| Checkout provision | `provisionFromPlatformCheckout` writes plan + apps |
| Industry / comms gates | `industry/entitlements.ts`, `communications/entitlements.ts` |
| Remaining WP input | `dg-onboarding-sync` + portal sync applying WP purchase as Apps |

### 3. Target Gen 2 architecture

```
Stripe subscription (P3)
    → PlatformSubscription (planTier, status, founding/exempt)
        → resolveEntitlement()          (canWrite, canActivatePaidApps, …)
        → appIdsFromPlanSelection()     (settings.apps)
            → App catalog / shell
```

- Command / admin may grant Apps. WP module checkboxes must not.
- Founding / exempt still resolve through **this** resolver, not a WP tag.
- Leftover Gen 1 wp-admin may keep `DG_Plan_Registry` for **that site’s plugin modules only**. That is not DigitalGate SaaS ownership.

### 4. Database / state changes required

| Change | Detail |
|--------|--------|
| SoT | `PlatformSubscription` + `organisation.settings.apps` only |
| Stop merging | WP `purchased_*` / portal purchase into `settings.apps` on every sync |
| One-time | For orgs whose Apps exist only because of a WP Payment Link: copy portal `purchased_*` into `settings.apps` **once**, then freeze WP as input |
| WP tables | No new WP plan schema. Existing options become leftover-plugin config |

### 5. API changes required

| Change | Detail |
|--------|--------|
| Keep | Gen 2 org/apps APIs; entitlement checks on mutating routes |
| Change | `POST /api/webhooks/dg-onboarding-sync` must **not** set entitlements. Either delete, or accept a one-shot import flag used only by a migration script |
| Remove | Any Gen 2 code path that treats `/portal/me` `purchased_*` as licence SoT |
| WP | No new plan REST. Existing module options stay local to the plugin |

### 6. Migration required

1. Inventory orgs whose `settings.apps` were last written by portal sync (audit script).
2. Reconcile those rows from Stripe/Gen 2 subscription where possible; else one-shot portal snapshot.
3. Disable WP→Apps write in `syncOrganisationFromPortal`.
4. Leave WP `DG_Plan_Registry` for leftover plugin admin only — document that it is **not** platform entitlement.

### 7. What WP code can be retired

After DECOUPLED:

- Growth Engine entitlement push (`sync_platform_after_purchase` as Apps SoT)
- Portal `purchased_*` as the way Gen 2 decides Apps
- Treating `DG_Plan_Registry` as DigitalGate SaaS licensing

**Keep (legacy plugin):** module enablement for a customer WP site that still runs Gen 1 admin. That is site config, not platform ownership.

### 8. Dependencies / blockers

- P1 should have removed `fetchPortalMe` from the hot path; P2 removes the remaining entitlement merge.
- Full “WP never starts a platform subscription” is P3. P2 can still finish if new Apps are only granted from Gen 2 admin or Gen 2 checkout.
- Do not invent a WP “entitlement proxy” to keep old Payment Links alive.

### 9. How we test that WP is no longer required

| Test | Pass |
|------|------|
| WP down | Org Apps match `settings.apps` + `resolveEntitlement` only |
| Flip a WP plan option | Gen 2 Apps **do not** change |
| Fire `dg-onboarding-sync` with a WP purchase payload | Apps **do not** change (or endpoint is gone) |
| Command grant / Gen 2 checkout | Apps change without WP |
| Two orgs, same email on WP | Each Gen 2 org keeps its own Apps (no portal-email bleed) |

### 10. Proposed implementation order (P2)

1. Audit: who still receives Apps from portal sync.
2. One-shot snapshot those Apps into Neon.
3. Remove WP purchase merge from `syncOrganisationFromPortal` / webhook.
4. Confirm shell and API use `resolveEnabledAppIds` + `resolveEntitlement` only.
5. Run DECOUPLED test. Mark P2 **DECOUPLED**.

---

# Priority 3 — Stripe (DigitalGate platform subscriptions)

**Target:**

Gen 2 → Stripe Checkout Session → Stripe → webhook → Gen 2 subscription state

No WordPress billing dependency for DigitalGate platform subscriptions.  
Must support the Founding 10 **14-day trial** (card now, $0 due, charge after trial) on **monthly and yearly**.

**Out of scope for P3:** Acc / Stay **guest stay** Stripe (`class-acc-payments.php`, `dg-stripe/v1`). That moves with **P5**.

### Current DECOUPLED verdict

**NOT DECOUPLED.** Gen 2 already creates Checkout Sessions with `trial_period_days` and handles `/api/webhooks/stripe`, but live DigitalGate seats can still be sold via WP Payment Links. WP `DG_Stripe_Billing` tags `Payment Received` and pushes Growth Engine. Gen 2 `provisionFromPlatformCheckout` currently marks Founding / exempt orgs `status: "active"` / `billing.subscriptionStatus: "active"` even during a 14-day trial — that is a **Gen 2 defect**, not a reason to use WP.

### 1. Current WP dependency

| Piece | Where |
|-------|--------|
| Payment Links | `DG_Stripe_Billing` · `marketing/pages/STRIPE-PAYMENT-LINKS.md` |
| Webhook | `POST digitalgate/v1/billing/webhook` — `checkout.session.completed` |
| Local SoT | Contact tags `Payment Received` / `Awaiting Onboarding` |
| Downstream | `sync_platform_after_purchase($email)` → Gen 2 webhook → portal pull |
| Commercial copy | Pricing HTML still embeds Payment Link URLs |

### 2. Current Gen 2 implementation

| Piece | Where |
|-------|--------|
| Checkout | `createPlatformCheckoutSession` — `mode: subscription`, `payment_method_collection: always`, `trial_period_days` from `BILLING_COMMERCIAL_CONFIG.trialDays` (14) for non-exempt |
| Customer portal | `createBillingPortalSession` |
| Webhook | `/api/webhooks/stripe` + receipt-state migration |
| Store | `PlatformSubscription` (`TRIALING` / `ACTIVE` / …) |
| APIs | `/api/v1/billing/checkout` |
| Founding defect | Founding / `platformExempt` provision writes **active**, not **trialing** |

### 3. Target Gen 2 architecture

```
Gen 2 onboarding / Founding setup
    → createPlatformCheckoutSession (metadata: organisation_id, tier, cadence, founding)
        → Stripe Checkout (card required, trial_period_days = 14)
            → customer.subscription.created/updated (status = trialing)
                → PlatformSubscription.status = TRIALING
                → entitlements = trial (P2)
            → trial ends, first invoice paid
                → status = ACTIVE
                → “Payment Received” equivalent lives here only
```

- `trialing` is **not** payment. Operator copy must not say Payment Received until `active` (or equivalent paid status).
- Founding uses **standard pricing + 14-day trial**, not a WP Payment Link and not a percent-off stand-in.
- Existing leftover Payment Links: deactivate in Stripe; do not keep WP webhook as the provisioner.

### 4. Database / state changes required

| Change | Detail |
|--------|--------|
| Fix provision | Founding / exempt + Checkout trial → `TRIALING` / `trialing`, set `trialEnd` from Stripe. Do not force `active`. |
| SoT | `PlatformSubscription` + Stripe subscription id + customer id on the org |
| Stop | WP contact tags as subscription SoT for DigitalGate seats |
| One-time | Map leftover WP-provisioned orgs: Stripe customer/subscription → Neon row. Dry-run. |
| Webhook receipts | Already have receipt-state tables — keep in Gen 2 |

### 5. API changes required

| Change | Detail |
|--------|--------|
| Keep | `POST /api/v1/billing/checkout` · Stripe Customer Portal · `/api/webhooks/stripe` |
| Checkout metadata | Always include `organisation_id`, cadence, founding flag |
| Events | Handle `customer.subscription.created/updated/deleted`, `invoice.paid`, `invoice.payment_failed`. Map `trialing` ≠ paid |
| Retire as product | WP `POST /billing/webhook` for DigitalGate SaaS (legacy replay only until leftover links are dead) |
| Do not add | WP Checkout Session creator; WP “Founding Payment Link” |

### 6. Migration required

1. Fix Founding / exempt trial status in `provisionFromPlatformCheckout` (Gen 2).
2. Prove **monthly and yearly** 14-day trials in Stripe **test mode** (card now, $0, `trialing` webhook, then `active` after trial / test-clock).
3. Point all new DigitalGate + Founding checkouts at Gen 2 only.
4. Deactivate WP Payment Links for platform seats; Stripe redirect leftover links to Gen 2 billing if any remain.
5. Import leftover WP-created Stripe customers into `PlatformSubscription`.
6. Disconnect Stripe from sending DigitalGate SaaS events to the WP webhook (Acc stay events stay until P5).

### 7. What WP code can be retired

After DECOUPLED (SaaS only):

- `DG_Stripe_Billing` as DigitalGate seat provisioner
- Payment Link catalogue as the buy path (`STRIPE-PAYMENT-LINKS.md` becomes historical)
- `Payment Received` tag as platform subscription SoT
- Growth Engine “after purchase” as the way Gen 2 learns about a seat

**Keep until P5:** Acc guest stay PaymentIntents / `dg-stripe/v1`.  
**Keep as archive:** webhook replay admin for leftover forensic sessions.

### 8. Dependencies / blockers

- P1 should own the “start checkout” button inside the wizard.
- P2 should consume `PlatformSubscription` only.
- Stripe Dashboard Price IDs / `STRIPE_SECRET_KEY` / webhook secret on **Gen 2**.
- Test clocks (or equivalent) for monthly **and** yearly trial → first invoice.
- Do not wait on WP `sk_test_` — this environment’s WP has never been the billing SoT we want.

### 9. How we test that WP is no longer required

| Test | Pass |
|------|------|
| WP down | Gen 2 checkout creates a Session; Stripe webhook updates Neon |
| Monthly trial (test) | Card collected, $0, `trialing`, entitlements trial, **no** Payment Received |
| Yearly trial (test) | Same |
| Trial → first invoice | Status `active`; paid flag only then |
| Founding org | Same trial semantics — **not** forced `active` at checkout |
| WP Payment Link clicked | Does not provision a new Gen 2 org/Apps (link deactivated or no-ops) |
| Network | Zero Gen 2 calls to WP `/billing/webhook` |

### 10. Proposed implementation order (P3)

1. Fix Founding / exempt provision to `TRIALING`.
2. Stripe test-mode proof: monthly + yearly, 14-day, card required.
3. Wizard / Founding setup uses only `/api/v1/billing/checkout`.
4. Deactivate WP SaaS Payment Links; stop WP webhook as provisioner.
5. Import leftover Stripe ids.
6. Run DECOUPLED test. Mark P3 **DECOUPLED**.

---

# Priority 4 — Support

**Target:**

Customer → Gen 2 Support → organisation-scoped conversation

Every conversation belongs to the correct **organisation**, **business**, **user/contact**, and **conversation**.  
No WordPress support inbox as a second source of truth.

This is mandatory because WP keyed conversations by **WP `user_id` UNIQUE** with **no `organisation_id`**. Gen 2 used to proxy `/support/platform/*` and resolve a WP user by portal email — messages from one business appeared in another.

### Current DECOUPLED verdict

**NOT DECOUPLED for staff SoT / leftover WP inbox.** Customer chat in Gen 2 already writes Neon (`src/lib/support-chat.ts`) with `clerkUserId_organisationId`. wp-admin Support Inbox and WP REST (`digitalgate/v1/support/*`) are still a live second store. Any remaining proxy or dual-read keeps the leak class of bug.

### 1. Current WP dependency

| Piece | Where |
|-------|--------|
| Schema | `wp_dg_support_conversations` — `user_id` **UNIQUE**, optional `contact_id`, **no organisation_id** (`class-activator.php`) |
| Runtime | `DG_Client_Support::get_or_create_conversation($user_id)` — one global thread per WP user |
| Customer REST | `/support/conversation`, `/support/messages` |
| Platform REST | `/support/platform/*` (email → WP user) |
| Staff UI | wp-admin `admin.php?page=dg-platform-support` |
| AI | `class-support-ai.php` |

**Leak mechanism:** one WP user (or one email resolved to one WP user) ⇒ one conversation ⇒ all businesses that share that login/email share the thread.

### 2. Current Gen 2 implementation

| Piece | Where |
|-------|--------|
| Schema | Prisma `SupportConversation` unique `clerkUserId_organisationId` (migration `20260829_support_conversation_org_scope`) |
| Core | `packages/platform-core/src/support/conversation.ts` — requires `organisationId`; never reassigns it |
| Customer | `src/lib/support-chat.ts` → Neon (not WP) |
| Staff | Command `/command/support` — `listOpenSupportConversations({ organisationId })` |
| AI / notify | `ai-reply.ts`, `notify.ts` (org id on staff notifications) |

### 3. Target Gen 2 architecture

```
Clerk user + active Organisation
    → SupportConversation (unique clerkUserId + organisationId)
        → SupportMessage[]
            → Command inbox (filterable by organisation)
            → optional AI reply (same conversation id)
```

- Switching org in the shell **must** open a different conversation.
- Staff reply is always addressed to that conversation’s `organisationId`.
- Contact/business labels are resolved from Membership + Organisation, not from a WP user row.

### 4. Database / state changes required

| Change | Detail |
|--------|--------|
| Live SoT | Already Neon. Do not add `organisation_id` to WP to “fix” the leak — that would be a third/partial twin. |
| Historical WP threads | One-shot export. **Do not** attach a WP thread to a Gen 2 org by email alone. Require org slug / membership match; otherwise import as `unassigned` / operator review. |
| Soft-delete WP | After cutover, stop inserts; keep tables read-only for forensics, then drop in a later connector slim. |

### 5. API changes required

| Change | Detail |
|--------|--------|
| Keep | Gen 2 `/api/v1/support/*` (or current support-chat routes) + Command list/reply |
| Guarantee | Every GET/POST requires session `organisationId`; 409/403 on mismatch |
| Remove | Gen 2 → WP `/support/platform/*` proxy (if any remnant) |
| Retire | WP support REST as product; wp-admin inbox menu |

### 6. Migration required

1. Confirm production customer widget hits Neon only (network log).
2. Confirm Command inbox is the only staff tool; disable wp-admin inbox (hide menu / capability).
3. Export WP threads with `user_id`, email, `contact_id`, timestamps. Review-queue matches to `(clerkUserId, organisationId)`.
4. Never merge two businesses into one Gen 2 conversation.
5. After empty WP write path, 410 the WP support routes.

### 7. What WP code can be retired

After DECOUPLED:

- `class-client-support.php` product routes + admin inbox
- `class-support-ai.php` as DigitalGate support AI
- Platform support REST used as Gen 2 backend

**Keep:** nothing as SoT. Archive SQL dump is enough.

### 8. Dependencies / blockers

- Session must always have `organisationId` (onboarding P1 should create the org before chat).
- Historical mis-filed messages need a human pass — automation by email **recreates the leak**.
- Do not “fix” WP by adding `organisation_id` and keeping two inboxes.

### 9. How we test that WP is no longer required

| Test | Pass |
|------|------|
| WP down | Customer sends; staff sees thread in Command, scoped to that org |
| Same Clerk user, org A then org B | Two conversations; A’s messages never appear in B |
| Two businesses, same email historically in WP | After migration, no shared thread unless an operator explicitly linked after review |
| wp-admin inbox | Gone or read-only archive; new messages do not appear there |
| Network | Zero `/wp-json/digitalgate/v1/support/*` from the app |

### 10. Proposed implementation order (P4)

1. Kill any remaining Gen 2→WP support proxy.
2. Make Command inbox the only staff UI; hide WP inbox.
3. Conservative historical import (review queue).
4. 410 WP support REST.
5. Run leak tests + DECOUPLED test. Mark P4 **DECOUPLED**.

---

# Priority 5 — Booking

**Target:** Gen 2 / StayBooking architecture. Booking state owned by the appropriate Gen 2 system, not WordPress.

**Method:** **Document first**, then migrate. Do not start a new WP booking feature. Acc guest Stripe moves here, not in P3.

### Current DECOUPLED verdict

**NOT DECOUPLED.** Ops can already create Gen 2-first stays (`StayBooking` / `AccommodationUnit`; flag `acc.gen2_first_booking`). Public book-now and stay PaymentIntents still run on WordPress. Dual-write `class-acc-platform-sync.php` → `POST /api/webhooks/dg-stay-booking` keeps WP on the write path.

### 1. Current WP dependency

| Piece | Where |
|-------|--------|
| Public book-now | Acc shortcode / book-now flow |
| Stay payments | `class-acc-payments.php`, `dg-stripe/v1` PaymentIntents (guest stay, **not** SaaS seats) |
| Dual-write | `class-acc-platform-sync.php` → Gen 2 stay webhook |
| Connector | Acc Dev API, iCal, customer-site calendar display |

### 2. Current Gen 2 implementation

| Piece | Where |
|-------|--------|
| SoT (ops) | Neon `StayBooking`, `AccommodationUnit` |
| Inbound | `/api/webhooks/dg-stay-booking` |
| Public | Still WP for CVH-class book-now |
| Calendars | Platform iCal for Airbnb/Booking already Gen 2-oriented |

### 3. Target Gen 2 architecture

```
Public book-now (Gen 2 or customer-site embed)
    → Gen 2 StayBooking
        → guest Stripe PaymentIntent (Gen 2 commerce / Acc Stripe)
        → AccommodationUnit availability
        → iCal / channel connectors

Customer WordPress site (optional)
    → Connector: display availability / embed
    → must not own the reservation row
```

### 4. Database / state changes required

| Change | Detail |
|--------|--------|
| SoT | `StayBooking` (+ payments on the Gen 2 payment record) |
| Stop | WP booking row as the reservation SoT |
| One-time | Remaining WP-only stays → Neon (id map). Dual-write becomes WP **pull/display** or off |
| Connector | Customer WP may cache published availability; cache is not SoT |

### 5. API changes required

| Change | Detail |
|--------|--------|
| Public create | Gen 2 public book-now / stay checkout API (or documented existing route if already sufficient) |
| Payments | Stay PaymentIntent on Gen 2; WP `dg-stripe/v1` retired for new stays |
| Webhook | `dg-stay-booking` becomes optional inbound from **external** sites, not from DigitalGate’s own WP product |
| Connector | Read-only availability / embed |

### 6. Migration required

1. **Write the booking twin note** (appendix below + Gen 2 `docs/`): public path, ops path, Stripe path, iCal, dual-write direction.
2. Point public book-now at Gen 2 create + pay.
3. Import leftover WP stays.
4. Disable WP→Gen 2 dual-write for DigitalGate-operated properties.
5. Leave calendar **display** on customer WP as connector if needed.

### 7. What WP code can be retired

After DECOUPLED:

- Public book-now as reservation SoT
- Acc Stripe as the DigitalGate stay processor for those properties
- `class-acc-platform-sync.php` as required for a booking to exist

**Keep (connector):** iCal publish, availability embed, health probes on the customer’s WP site.

### 8. Dependencies / blockers

- P3 must not be blocked by Acc Stripe — different product.
- Channel calendars (Airbnb/Booking) must keep using Gen 2 iCal, not WP rows.
- Live CVH-class domains need a cutover window; document before flipping.

### 9. How we test that WP is no longer required

| Test | Pass |
|------|------|
| WP down | Public book-now creates a `StayBooking`; payment webhook updates Neon |
| Ops create | Gen 2-first stay needs no WP |
| Customer WP embed (if any) | Display-only; killing WP admin booking UI does not lose reservations |
| Network | New reservations do not require `class-acc-platform-sync` to exist |

### 10. Proposed implementation order (P5)

1. Document the current twin (required before code).
2. Gen 2 public book-now + stay payment.
3. Import leftover WP stays.
4. Turn off WP SoT + dual-write for DigitalGate-operated book-now.
5. Run DECOUPLED test. Mark P5 **DECOUPLED**.

#### P5 documentation checklist (do this first)

- [ ] Public entry URLs (CVH / others) and which host renders the form
- [ ] Where the reservation row is written today (WP table vs Neon)
- [ ] PaymentIntent path and webhook destination
- [ ] iCal / Airbnb / Booking direction
- [ ] Dual-write direction and flags (`acc.gen2_first_booking`)
- [ ] What the customer WP site must still display after cutover

---

# Founding 10 (after P1 + P3)

**Do not build another implementation in WordPress.**

Founding 10 waits for the Gen 2 foundations above — especially **onboarding (P1)** and **Stripe (P3)**. Plan + Apps enforcement needs **P2**.

Then build **entirely in Gen 2**:

Invite → Discovery → Offer → Accept → Gen 2 Setup → Plan + Apps → Stripe Trial → Implementation → Go Live

Related Gen 2 / plugin docs (spec only): `packages/platform-core/src/founding/*`, Command `/command/founding`, `/founding/agreement`, `/founding/setup` (shim), plugin `FOUNDING-10-IMPLEMENTATION-PLAN.md` on `cursor/founding-10-journey-impl-03c2`. Live marketing apply + Terms checkbox stays until this journey is 200 in Gen 2 — **do not** paste a WP engine under it.

### Current DECOUPLED verdict

**NOT READY.** Accept/setup/Stripe are not a complete Gen 2 journey yet. WP Founding runtime was **withdrawn** and must stay gone. Live page still has apply + Terms.

### 1. Current WP dependency

- Live `digitalgate.com.au/founding-customers/` apply + required Terms (marketing HTML).
- No approved WP Founding accept/setup/Stripe engine (stand-in deleted).
- Must not grow a new one.

### 2. Current Gen 2 implementation

- Invites, agreement, pipeline, Command founding, emails, implementation workspace.
- `/founding/setup` claims invite (optional) and **redirects** to agreement or `/onboarding`.
- Checkout provision **incorrectly** marks Founding orgs `active` during trial (P3 fix).

### 3. Target Gen 2 architecture

```
Invite (token)
  → Discovery (Gen 2)
  → Offer (Command)
  → Accept Terms ( /founding/agreement — existing)
  → Setup gate ( /founding/setup — existing shim)
  → P1 wizard (Business Profile → … → Plan + Apps)
  → P3 Checkout (14-day trial, card now)
  → Implementation workspace
  → Go Live
```

`/signup` remains a **generic picker**, not this journey.

### 4–7. State / API / migration / WP retire

Covered by P1–P3. Extra Founding-only work: invite claim, offer state, agreement signature, Command stage machine — **all already Gen 2 modules to finish**, not WP tables. Retire the live apply + Terms gate only after accept + setup + trial are proven. Commercial rules and Founding Customer Terms text stay unchanged.

### 8. Dependencies / blockers

- **Hard block:** P1 DECOUPLED (complete + resume without WP).
- **Hard block:** P3 DECOUPLED (monthly + yearly 14-day trial; Founding status `trialing`).
- **Soft block:** P2 DECOUPLED (Apps must not flip from a WP tag).
- Do not switch the live founding URL until those blocks clear.
- Nick / `#application` process is out of band — do not add a WP application inbox.

### 9. How we test that WP is no longer required

| Test | Pass |
|------|------|
| WP down | Invite → accept → setup → wizard → Checkout trial → implementation |
| Trial | `trialing`, card on file, $0, no Payment Received |
| After trial | `active` + paid semantics |
| `/signup` | Unchanged generic picker |
| Live marketing | Switched only after the Gen 2 path is 200 |

### 10. Proposed implementation order (F10)

1. P1 DECOUPLED.
2. P3 DECOUPLED (including Founding `trialing` fix + test-mode proof).
3. P2 DECOUPLED (or at least WP cannot grant Founding Apps).
4. Finish Gen 2 invite → discovery → offer → accept wiring against the **same** wizard and checkout.
5. Command operators run the cohort without wp-admin.
6. Rewrite live founding page to invite (no apply + Terms gate).
7. Run DECOUPLED test. Mark F10 **DECOUPLED**.

---

## After the programme — what WordPress is

```
DigitalGate public site  →  Gen 2  →  Platform Core  →  Apps / Stripe / connectors

Customer WordPress site  ↔  WordPress Connector  ↔  Gen 2
```

Allowed leftover plugin surfaces: Dev API auth, inbound webhooks from **that customer’s** forms, optional listing/availability **mirrors**, health probes, iCal.  
Not allowed: a second DigitalGate CRM, inbox, billing engine, onboarding, or Founding runtime.

---

## Operator checklist (start execution when instructed)

Work happens in **`dg-platform-web`**. This document is the order. Do not open a WP feature branch for P1–P5 or Founding 10.

| Step | Item | DECOUPLED? |
|------|------|------------|
| 1 | P1 Onboarding — remove `fetchPortalMe` / WP POST; Team + Systems on existing wizard | NO |
| 2 | P2 Entitlements — stop WP / Growth Engine as Apps SoT | NO |
| 3 | P3 Stripe SaaS — Gen 2 Checkout + webhook; Founding trial status | NO |
| 4 | P4 Support — WP inbox off; org-scoped Neon only | NO |
| 5 | P5 Booking — document, then public StayBooking | NO |
| 6 | Founding 10 — Gen 2 only | NO |

When an item passes the DECOUPLED test, change **NO** to **DECOUPLED** in this table and in the item header. Do not mark it from UI screenshots alone.
