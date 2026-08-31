# Gen 1 → Gen 2 decoupling audit

**Date:** 31 August 2026  
**Repos inspected:** `dg-platform` (this checkout, Gen 1 plugin) · `dg-platform-web` (GitHub HEAD, Gen 2)  
**Rule:** [ARCHITECTURE-BOUNDARY.md](./ARCHITECTURE-BOUNDARY.md)  
**Does not replace:** Gen 2 `docs/WP-DETACH-BACKLOG.md` (ticket list). This audit is the **SoT map** and the **stop-building-in-WP** checklist.

WordPress is **legacy / connector only**. Gen 2 is the source of truth for DigitalGate. If a row says MIGRATE, the work is **in Gen 2**, not a new WP module.

---

## How to use this before a major feature

1. Find the domain below.
2. Read **Category** and **WP still required?**
3. If the feature is DigitalGate platform functionality → implement in `dg-platform-web`.
4. If WP is CONNECTOR ONLY → only add a thin webhook/mirror/probe.
5. If you would “just use the old WP form/handler” → use it as a spec, then build Gen 2.

---

## Method

- **Gen 1:** plugin PHP (`includes/`, `modules/`, `digitalgate/v1`, `wp_dg_*`).
- **Gen 2:** `packages/platform-core`, Prisma/Neon, `src/app/(shell)`, `src/app/api/v1`, Clerk.
- Dual-write and `fetchPortalMe` / `DG_API_BASE_URL` count as **Gen 2 still depends on WP**.

---

## Heatmap

| Domain | Category | SoT today | WP still required? | Duplicate logic / state? | Gen 2 depends on WP? |
|--------|----------|-----------|--------------------|--------------------------|----------------------|
| Authentication | KEEP IN GEN 2 | Clerk + Gen 2 session | No (except WP-admin for legacy sites) | WP users vs Clerk | No for app login |
| Organisations | KEEP IN GEN 2 / MIGRATE leftovers | Gen 2 `Organisation` | Legacy WP `dg_organisations` | Yes — two org tables | Portal sync still pulls WP |
| Users / memberships | KEEP IN GEN 2 | Clerk + `Membership` | WP roles on legacy sites | Yes | No for Gen 2 app |
| Permissions | KEEP IN GEN 2 / MIGRATE | Gen 2 feature registry | WP caps (`DG_Permissions`) | Yes — two models | No for Gen 2 UI |
| CRM | KEEP IN GEN 2 / MIGRATE capture | Gen 2 Contact/Company/Opportunity | Dual-write from some WP forms | Yes until forms go Gen 2-first | Optional webhook/pull |
| Business Profile | KEEP IN GEN 2 / MIGRATE | Gen 2 org profile | WP onboarding form + portal | Yes | `fetchPortalMe` / onboarding POST |
| Onboarding | MIGRATE | Split — wizard in Gen 2, still posts/reads WP | WP form + `/portal/me` | Yes | **Yes** (P2 in detach backlog) |
| Founding 10 | KEEP IN GEN 2 / MIGRATE gaps | Gen 2 founding + Command | Live marketing apply form still Gen 1 HTML | Journey docs still mention apply | Must not add WP runtime |
| Plans | KEEP IN GEN 2 / LEGACY WP gating | Gen 2 billing + apps | `DG_Plan_Registry` gates WP modules | Yes | No if checkout is Gen 2 |
| Apps | KEEP IN GEN 2 / MIGRATE entitlements | Gen 2 `settings.apps` | WP module enable + Growth Engine push | Yes | **Yes** until P2 exit |
| Stripe / billing | KEEP IN GEN 2 / MIGRATE WP Payment Links | Split — two Stripe paths | `DG_Stripe_Billing` Payment Links | **Yes — critical** | WP path pushes Gen 2 |
| Connectors | CONNECTOR ONLY | Gen 2 connector registry | WP is one connector | Intended | By definition |
| Communications | KEEP IN GEN 2 / LEGACY WP mail | Gen 2 communications app | WP `wp_mail` / marketing emails | Partial | No for in-app inbox |
| Support | MIGRATE | Neon `SupportConversation` exists; live staff inbox still WP | `dg_support_*` + wp-admin | Yes | **Yes** (proxy) |
| AI | KEEP IN GEN 2 / LEGACY WP assist | Gen 2 AI services | WP `DG_AI_*` | Yes | No for Gen 2 AI |
| Intelligence / scoring | KEEP IN GEN 2 / MIGRATE discovery | Gen 2 scoring + discovery | WP maturity / audit scoring | Yes | Discovery webhook WP → Gen 2 |
| SEO | CONNECTOR ONLY / MIGRATE product SEO | Split | WP SEO module on customer sites | Product SEO vs site SEO | Health/meta still WP for WP sites |
| Marketing (public site) | KEEP IN GEN 2 | Gen 2 website renderer | Legacy Oxygen/HTML sources in this repo | Chrome HTML copied both ways | Seed script reads this repo |
| Automation | KEEP IN GEN 2 / LEGACY WP | Gen 2 automation app | WP Automation Pro | Yes | No for Gen 2 rules |
| Reporting | KEEP IN GEN 2 / LEGACY WP | Gen 2 reports | WP `DG_Reports` + industry reports | Yes | Some RE reports already Neon |
| Documents | KEEP IN GEN 2 / LEGACY WP | Gen 2 `OrgDocument` | WP `DG_Documents` | Yes | No if using Gen 2 library |
| Commerce | KEEP IN GEN 2 | Gen 2 commerce + Stripe | WP Acc Stripe is **guest stay** payments | Different products | Acc book-now still WP |
| Operator OS | KEEP IN GEN 2 | Command Centre | None equivalent | No | No |
| Admin | KEEP IN GEN 2 / LEGACY WP | Gen 2 settings + Command | wp-admin DG Platform | Yes | Operators still use wp-admin for leftover SoT |
| Notifications | KEEP IN GEN 2 / LEGACY WP | Gen 2 `Notification` | WP admin notices + email | Partial | No for in-app |
| Public customer journeys | MIGRATE / CONNECTOR | Mixed | Founding apply, Acc book-now, some forms | Yes | Until Gen 2-first capture |

---

## Domain records

Each record answers: (1) Gen 1 (2) Gen 2 (3) SoT (4) WP required (5) duplicate logic (6) duplicate state (7) duplicate APIs (8) Gen 2 depends on WP (9) migrate (10) retire.

### Authentication

| # | Finding |
|---|--------|
| 1 | WP users, `wp-login`, client portal cookies (`class-client-portal.php`) |
| 2 | Clerk + Gen 2 session (`docs/adr/0005`, `packages/platform-core/src/session`) |
| 3 | **Gen 2 / Clerk** |
| 4 | Only for wp-admin on leftover Gen 1 sites |
| 5 | Two identity systems |
| 6 | `wp_users` vs Clerk users |
| 7 | WP login vs `/login` |
| 8 | No for `app.digitalgate.com.au` |
| 9 | Stop portal-as-identity; map leftover WP emails to Clerk once |
| 10 | DigitalGate product login on WP |

**Category:** KEEP IN GEN 2 · portal identity → MIGRATE

### Organisations

| # | Finding |
|---|--------|
| 1 | `DG_Organisations` / `wp_dg_organisations` |
| 2 | Prisma `Organisation` + settings (profile, billing, apps) |
| 3 | **Gen 2** |
| 4 | Not for new customers |
| 5 | Org create on WP Stripe vs Gen 2 checkout |
| 6 | Two org rows for the same business if both paths fire |
| 7 | WP REST orgs vs `/api/v1/org` |
| 8 | `syncOrganisationFromPortal` / `dg-onboarding-sync` still read WP |
| 9 | Kill portal org sync as request-path; one-time import |
| 10 | WP as DigitalGate org directory |

**Category:** KEEP IN GEN 2 · WP org table → LEGACY → DELETE / RETIRE

### Users / permissions

| # | Finding |
|---|--------|
| 1 | WP roles + `class-permissions.php` |
| 2 | Membership + feature registry (`adr/0007`) |
| 3 | **Gen 2** |
| 4 | WP caps only on leftover plugin admin |
| 5 | Two permission languages |
| 6 | Membership vs WP user meta |
| 7 | WP admin menus vs Gen 2 sidebar |
| 8 | No |
| 9 | Do not port new caps into WP |
| 10 | Product RBAC in WP |

**Category:** KEEP IN GEN 2

### CRM

| # | Finding |
|---|--------|
| 1 | `DG_Contacts`, activities, tasks, calendar; industry pipelines (RE, marketing, services, …) |
| 2 | `Contact`, `Company`, `Lead`, `Opportunity`, `Task`, `Activity`; CRM shell under `/apps/crm` |
| 3 | **Gen 2** for CRM records |
| 4 | Only as form origin until public capture is Gen 2-first |
| 5 | Pipeline stage rules exist in both |
| 6 | Dual-write contacts/leads (`DG_RE_Platform_Sync`, `DG_Growth_Engine_Sync`) |
| 7 | `digitalgate/v1/contacts` vs `/api/v1/contacts` + `/leads` |
| 8 | Optional pull-sync; RE auto-sync default **off** |
| 9 | Public forms → Gen 2 webhooks only; stop WP CRM as operator UI |
| 10 | wp-admin Contacts as DigitalGate CRM |

**Category:** KEEP IN GEN 2 · remaining WP CRM UI → LEGACY · inbound forms → CONNECTOR ONLY

### Business Profile

| # | Finding |
|---|--------|
| 1 | Onboarding form fields + contact/org meta |
| 2 | `business-profile-types`, org `settings.profile` |
| 3 | **Gen 2** |
| 4 | No, once onboarding stops POSTing to WP |
| 5 | Same fields collected twice |
| 6 | WP onboarding row vs Neon profile |
| 7 | WP `/onboarding` REST vs `/api/v1/onboarding` |
| 8 | **Yes** until WP-D-203 |
| 9 | Wizard writes Neon only |
| 10 | WP onboarding as profile SoT |

**Category:** MIGRATE (request path) · KEEP IN GEN 2 (model)

### Onboarding

| # | Finding |
|---|--------|
| 1 | `class-client-onboarding.php`, `onboarding-form.html`, wp-admin beta setup |
| 2 | `GEN2_ONBOARDING_STEPS`, `/onboarding`, `/founding/setup` (setup is still a **redirect** today) |
| 3 | **Must be Gen 2** — today split |
| 4 | Still used for `/portal/me` and some signup POST |
| 5 | Two wizards + WP questionnaire |
| 6 | WP onboarding submission vs Gen 2 progress JSON |
| 7 | WP onboarding REST + Gen 2 `/api/onboarding` + `/api/v1/onboarding` |
| 8 | **Yes** (`submitOnboarding`, `fetchPortalMe`) |
| 9 | One Gen 2 wizard (`/founding/setup` for Founding; `/onboarding` aliases to it). No WP stand-in |
| 10 | Public WP questionnaire; WP as onboarding SoT |

**Category:** MIGRATE · **do not** rebuild in WP

### Founding 10

| # | Finding |
|---|--------|
| 1 | Live marketing apply form + Terms checkbox; plugin has no approved Founding runtime (WP stand-in **withdrawn**) |
| 2 | `packages/platform-core/src/founding/*`, `/founding/agreement`, `/founding/setup`, Command `/command/founding`, invite APIs |
| 3 | **Gen 2** |
| 4 | Not for accept/setup/Stripe |
| 5 | Risk if anyone rebuilds WP offers/checkout |
| 6 | None if WP runtime stays gone; live apply form is a separate leak |
| 7 | Must be `app…/founding/accept/[token]` + `/founding/setup` only |
| 8 | No, unless someone adds WP again |
| 9 | Finish accept + setup + trial state **in Gen 2**; fix Founding checkout marking org `active` during trial |
| 10 | `#application` Terms gate; any WP Founding engine |

**Category:** KEEP IN GEN 2 · live apply form → DELETE / RETIRE after invite page rewrite

### Plans / Apps / entitlements

| # | Finding |
|---|--------|
| 1 | `DG_Plan_Registry`, module registry, add-on flags |
| 2 | `AppInstallation`, org `settings.apps`, billing provision |
| 3 | **Gen 2** |
| 4 | WP module gating for leftover Gen 1 admin |
| 5 | Two plan catalogues |
| 6 | WP options vs Neon apps |
| 7 | None equivalent — WP is options, Gen 2 is API |
| 8 | **Yes** — Growth Engine / `dg-onboarding-sync` still pushes entitlements from WP purchases |
| 9 | Entitlements only from Gen 2 Stripe + admin |
| 10 | WP plan options as SaaS licence SoT |

**Category:** KEEP IN GEN 2 · WP registry → LEGACY

### Stripe / billing

| # | Finding |
|---|--------|
| 1 | `DG_Stripe_Billing` — Payment Links, `checkout.session.completed`, tags `Payment Received` |
| 2 | `createPlatformCheckoutSession`, `PlatformSubscription`, `/api/webhooks/stripe`, 14-day `BILLING_COMMERCIAL_CONFIG` |
| 3 | **Gen 2 + Stripe** for DigitalGate SaaS |
| 4 | Only for **legacy** Payment Links and **Acc guest** stay payments until Acc book-now is Gen 2 |
| 5 | **Yes** — two checkout + webhook stacks |
| 6 | WP contact tags vs Neon `PlatformSubscription` |
| 7 | WP `/billing/webhook` vs Gen 2 `/api/webhooks/stripe` |
| 8 | WP purchase path still syncs Gen 2 |
| 9 | All new Founding / platform subs on Gen 2 Checkout; WP SaaS webhook legacy-only |
| 10 | WP Payment Links for DigitalGate seats; WP “Payment Received” as subscription SoT |

**Category:** KEEP IN GEN 2 · WP SaaS Stripe → LEGACY → DELETE / RETIRE · Acc guest Stripe → CONNECTOR / MIGRATE with Acc

**Defect:** Gen 2 `provisionFromPlatformCheckout` sets Founding orgs to `active` instead of `trialing`. Fix in Gen 2.

### Connectors

| # | Finding |
|---|--------|
| 1 | Per-module integrations, Dev API keys |
| 2 | `connectors/wordpress`, Stripe, Google, etc. |
| 3 | Gen 2 connector install + secrets |
| 4 | **Yes** — as the **external website**, not the platform |
| 5 | Only if we re-implement platform features in the connector |
| 6 | Connector cache vs origin |
| 7 | `digitalgate/v1` Dev API is the connector contract |
| 8 | Only for sites that still use WP as CMS |
| 9 | Slim plugin to connector-only (WP-D-503) |
| 10 | Using the connector as CRM/billing/auth |

**Category:** CONNECTOR ONLY

### Communications

| # | Finding |
|---|--------|
| 1 | `wp_mail`, marketing email templates, Acc guest mail |
| 2 | Communications app + `OrgCommunication` + agents |
| 3 | **Gen 2** for platform comms |
| 4 | Transactional mail from leftover WP flows (bookings) until those migrate |
| 5 | Template/brand logic in both |
| 6 | WP sent-mail vs Gen 2 history |
| 7 | None shared |
| 8 | No for Gen 2 inbox |
| 9 | New customer messaging in Gen 2 |
| 10 | WP as DigitalGate inbox |

**Category:** KEEP IN GEN 2 · WP mail for Acc/RE leftovers → LEGACY

### Support

| # | Finding |
|---|--------|
| 1 | `class-client-support.php`, `class-support-ai.php`, wp-admin inbox |
| 2 | Prisma `SupportConversation` / `SupportMessage`; API still proxies WP in detach backlog |
| 3 | **Must be Gen 2** |
| 4 | Today **yes** for staff inbox |
| 5 | Yes |
| 6 | `dg_support_*` vs Neon support tables |
| 7 | WP support REST vs `/api/v1/support` |
| 8 | **Yes** |
| 9 | Persist and staff in Gen 2 only (WP-D-301) |
| 10 | WP support SoT |

**Category:** MIGRATE

### AI / Intelligence / scoring

| # | Finding |
|---|--------|
| 1 | `DG_AI_Assist`, visibility scanner, `DG_Marketing_Audit_Scoring`, discovery |
| 2 | `packages/platform-core/src/ai`, `intelligence`, `scoring`, `brain` |
| 3 | **Gen 2** |
| 4 | No for new AI product |
| 5 | Scoring formulas risk drifting |
| 6 | WP audit rows vs Gen 2 prospect audits |
| 7 | Discovery WP REST vs Gen 2 public discovery |
| 8 | Discovery webhook WP → Gen 2 |
| 9 | Discovery/audit originate on Gen 2; WP form optional connector |
| 10 | WP as intelligence engine |

**Category:** KEEP IN GEN 2 · WP scanners on customer WP sites → CONNECTOR ONLY (site-bound SEO/AI visibility)

### SEO / marketing site

| # | Finding |
|---|--------|
| 1 | `includes/seo/*`, marketing HTML in this repo, Oxygen leftovers |
| 2 | Gen 2 website renderer, `Website` / `WebsitePage`, SEO package |
| 3 | **Gen 2** for DigitalGate public sites; customer WP SEO stays on their site |
| 4 | Customer WP sites only |
| 5 | Header/footer HTML duplicated (seed from this repo into Gen 2) |
| 6 | WP posts vs Neon pages where both exist |
| 7 | None |
| 8 | Seed/sync chrome from plugin marketing folder |
| 9 | DigitalGate.com.au already Gen 2-rendered; keep HTML as **source files** if needed, not WP runtime |
| 10 | WP as DigitalGate marketing CMS |

**Category:** KEEP IN GEN 2 (DigitalGate sites) · customer WP SEO → CONNECTOR ONLY

### Automation / reporting / documents

| # | Finding |
|---|--------|
| 1 | Automation Pro, `DG_Reports`, `DG_Documents` |
| 2 | Automation app, analytics/reports, `OrgDocument` + signing |
| 3 | **Gen 2** |
| 4 | No for new product |
| 5 | Yes if both stay live |
| 6 | WP tables vs Neon |
| 7 | Parallel admin UIs |
| 8 | No |
| 9 | New automations/reports/docs in Gen 2 |
| 10 | WP Pro add-ons as DigitalGate product |

**Category:** KEEP IN GEN 2 · WP add-ons → LEGACY

### Commerce (platform vs Acc stays)

| # | Finding |
|---|--------|
| 1 | Acc Stripe/PayID for **stays**; SaaS Payment Links |
| 2 | Commerce quotes/invoices/subscriptions + platform SaaS Stripe |
| 3 | Platform commerce → **Gen 2**. Stay checkout → migrating to Gen 2 StayBooking; public book-now still WP |
| 4 | Public CVH book-now until WP-D-403 |
| 5 | Stay create in both if dual-write |
| 6 | `StayBooking` vs WP booking rows |
| 7 | Acc Dev API vs `/api/v1/accommodation` |
| 8 | Pull/dual-write still used |
| 9 | Gen 2-first book-now |
| 10 | WP as stay SoT |

**Category:** KEEP IN GEN 2 (SaaS commerce) · Acc public book-now → MIGRATE · WP calendar display → CONNECTOR ONLY

### Operator OS / admin / notifications

| # | Finding |
|---|--------|
| 1 | wp-admin “DG Platform” + beta setup |
| 2 | Command Centre, delivery workspace, `/api/v1/command`, `Notification` |
| 3 | **Gen 2** |
| 4 | wp-admin only while leftover SoT lives in WP |
| 5 | Two operator consoles |
| 6 | Tasks in both |
| 7 | wp-admin pages vs `/command/*` |
| 8 | No for Command itself |
| 9 | All DigitalGate ops in Command |
| 10 | wp-admin as DigitalGate HQ |

**Category:** KEEP IN GEN 2 · wp-admin product console → LEGACY → DELETE / RETIRE

### Public customer journeys

| Journey | Today | Category |
|---------|-------|----------|
| Founding invite / apply | Live page still has apply + Terms | DELETE / RETIRE apply gate · KEEP marketing in Gen 2 |
| Founding accept / setup / trial | Must be Gen 2; WP stand-in rejected | KEEP IN GEN 2 |
| `/signup` | Gen 2 picker; must stay **out** of Founding onboarding | KEEP IN GEN 2 |
| Discovery / audit | WP + webhook to Gen 2 | MIGRATE origin to Gen 2 · form may be CONNECTOR |
| Acc book-now | WP | MIGRATE |
| RE enquiry / property report | Dual-write; Gen 2 capture exists | CONNECTOR until Gen 2-first |
| `digitalgate.com.au/onboarding/` | Stub / old form | DELETE / RETIRE → 301 to Gen 2 setup **after** setup is 200 |

---

## Duplicate systems to stop feeding

These are the expensive twins. Do not add a third.

| Twin | Stop |
|------|------|
| WP Stripe SaaS + Gen 2 Stripe SaaS | New seats / Founding trials **only** via Gen 2 |
| WP onboarding + Gen 2 onboarding | One wizard in Gen 2 |
| WP contacts + Neon contacts | WP writes only as connector inbound |
| WP plan/modules + Gen 2 apps | Entitlements from Gen 2 billing |
| WP support inbox + Neon support | Staff in Gen 2 |
| WP users + Clerk | Product auth is Clerk |

---

## What can be permanently retired (when the matching Gen 2 row is done)

- Public WP Founding application + required Terms checkbox
- Any WP Founding accept/setup/Stripe engine
- WP `/onboarding/` questionnaire as a product
- WP Payment Links for DigitalGate platform seats
- wp-admin as the DigitalGate CRM / HQ for new operators
- `fetchPortalMe` on every Gen 2 page load
- Growth Engine entitlement push as the way Apps turn on
- Treating `dg-platform` as “the app we have locally, so ship it here”

Keep (as connector or archive): Dev API auth, inbound webhooks, optional property/stay mirrors, site health probes, historical field lists.

---

## Gen 2 copy-pack

This repo cannot push `dg-platform-web`. Land the same rule there:

- `patches/gen2-docs/ADR-0014-wordpress-legacy-connector-only.md`
- `patches/gen2-docs/AGENTS-BOUNDARY-SNIPPET.md`

ADR 0002 (“temporary bridge”, “no major new WP modules”) is **too weak**. 0014 makes the fallback ban explicit.
