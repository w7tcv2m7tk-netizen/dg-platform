# Founding 10 test path (before live funnel switch)

Use this as a **test customer** on the WordPress plugin routes. Do **not** change live `https://digitalgate.com.au/founding-customers/` until accept, setup, and Stripe trial proofs pass.

**Already proven in this environment (no live funnel change):**

- `/founding-customers-preview/` 200 — invite/explore, no Terms checkbox, no application form, commercial copy kept
- `/founding/accept/{token}/` 200 — Terms required (422 without checkbox) then redirect to setup
- `/founding/setup/` 200 — 8-step wizard, plan/Apps confirm, yearly line totals use 10× monthly
- `/wp-json/digitalgate/v1/founding/health` — `setup_ready_flag: false`
- `/onboarding/` alias left **OFF** (local `/onboarding/` is still a 404 stub)
- Webhook state (simulated complete Checkout + subscription events, no Stripe key needed):
  - `trialing` → tags `DigitalGate Client,Founding 10,Trialing` — **no** `Payment Received`
  - later `active` → adds `Payment Received`, strips `Trialing`
- Stripe Checkout / `Prove monthly + yearly trial` still need `sk_test_…` in API Settings — this environment has no Stripe secret key

Local site: `http://localhost:8080` (or this environment’s WP host). WP admin: `admin` / `admin`.

`/onboarding/` stays on the old/stub page until **DG Platform → Founding 10 → Point /onboarding/ at Founding setup** is turned ON. Leave it OFF for this run.

`/signup` is a separate generic picker. Do not use it for this journey.

---

## 1. Invite

Open the candidate invite page (repo source, not live):

`/founding-customers-preview/`

Confirm: journey copy, no Terms checkbox, no application form, no Payment Link, no `/signup` as Founding onboarding.

## 2. Explore

From the preview page, open the public research URLs (live site is fine):

- `/apps/`
- `/pricing/` (and screenshots if present)
- `/discover/`
- `https://audit.digitalgate.com.au/`

Confirm those pages still exist and are useful. Confirm homepage/pricing/header/footer sources now say **Explore Founding 10** → `/founding-customers/` (live paste is a later switch).

## 3. Discovery

Book/open:

`/contact/#platform-consultation`

(or `/strategy-session/` if it still 301s there)

This is the human demo step. No contract.

## 4. Offer

In WP Admin → **DG Platform → Founding 10**:

1. Create a test offer (your email, name, business, plan, monthly or yearly, optional Apps).
2. Copy the accept URL.

That URL is the written-offer stand-in.

## 5. Accept Terms

Open `/founding/accept/{token}/`.

- Read-only links to live Founding Customer Terms (unchanged document).
- Check the Terms box.
- Submit.

You should land on `/founding/setup/?token=…`.

## 6. Gen 2 onboarding

On `/founding/setup/`:

1. Complete business / goals / team / brand / systems.
2. Confirm plan + Apps + monthly or yearly.
3. Implementation notes.

This must be the wizard — not the old WP `onboarding-form.html`.

Confirm `/wp-json/digitalgate/v1/founding/health` and `/founding/setup/` both return **200**.

## 7. Stripe 14-day trial

On the last setup step, **Start 14-day trial**.

Stripe Checkout (test mode, not a Payment Link):

- Use `ACCT-000015`.
- Complete checkout.

Land on `/founding/trial-started/`.

Expect:

- Subscription status **trialing**
- Card on file
- **$0** charged now
- Contact tags include `Trialing` and `Founding 10`
- Contact tags do **not** include `Payment Received`

Repeat once with **yearly** (annual ≈ 10 months) using a second offer or by changing billing on setup.

Admin shortcut: **Prove monthly + yearly trial** on the Founding 10 screen (requires `sk_test_…` in API Settings).

## 8. Implementation

Treat the setup answers + trialing subscription as the implementation brief. No second Terms gate.

## 9. Go live

After the 14-day trial, Stripe moves the subscription to **active** and charges the first period. Webhook `customer.subscription.updated` should then add `Payment Received` and remove `Trialing`.

Do **not** switch the live Founding 10 funnel until both monthly and yearly proofs pass and you have walked this path as a test customer.

---

## Health checks

| Check | URL | Expect |
|-------|-----|--------|
| Setup route | `/founding/setup/` | 200 (locked message if no accepted token) |
| Health JSON | `/wp-json/digitalgate/v1/founding/health` | `{ "ok": true, "setup_ready_flag": false }` |
| Invite preview | `/founding-customers-preview/` | 200, no Terms checkbox |
| Live invite (unchanged) | `https://digitalgate.com.au/founding-customers/` | still the current live page until you paste/switch |
