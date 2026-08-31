# Founding 10 test path — Gen 2 only

Do **not** run this against WordPress. A local WP 200 on a stand-in route does not prove the journey.

Host: Gen 2 staging/test (`app.digitalgate.com.au` or the staging equivalent).  
Stripe: **test mode**. No Payment Links.

Live `https://digitalgate.com.au/founding-customers/` stays unchanged until this path passes.

`/signup` is a separate generic picker. Do not use it here.

---

## Journey

1. **Invite** — open the candidate invite/explore page (Gen 2 marketing source / staging founding-customers). No Terms checkbox. No application form.
2. **Explore** — `/apps/`, `/pricing/`, `/discover/`, optional audit.
3. **Discovery** — `/contact/#platform-consultation`. Human demo. No contract.
4. **Written offer** — issue from Gen 2 Command / founding invitations (not WP Admin).
5. **Accept Terms** — `https://app.digitalgate.com.au/founding/accept/[token]`
   - Creates or attaches **one** Gen 2 organisation
   - Required Founding Customer Terms checkbox
6. **Gen 2 Founding Setup** — `https://app.digitalgate.com.au/founding/setup`
   - Business Profile
   - Goals
   - Team
   - Systems
   - Confirm Plan + Apps
7. **Gen 2 Stripe Checkout** — session created by Gen 2. Card `4242 4242 4242 4242`. Repeat **monthly** and **yearly**.
8. **Trial** — Stripe subscription `trialing`. $0 charged. Card on file.
9. **Implementation** — Operator OS / Command, same organisation.
10. **Go Live** — Gen 2 dashboard.

Then force or wait for trial end (Stripe test clock) and confirm `trialing` → `active`.

---

## Stripe / org assertions

| Check | During trial | After trial becomes paid `active` |
|-------|--------------|-----------------------------------|
| Stripe subscription status | `trialing` | `active` |
| Amount charged | $0 | first period |
| Gen 2 commercial status | `TRIALING` | `ACTIVE` |
| “Payment Received” / paid label | **absent** | present once |
| Trialing flag | present | removed |
| `organisation_id` ↔ `cus_` ↔ `sub_` | linked | same IDs, no second org |
| WordPress | not in the path | not in the path |

Also confirm: no duplicate organisation; permissions and Apps sit on that org.

---

## What a WP run does **not** prove

- That `/founding/setup` is Gen 2
- That checkout was created by Gen 2
- That Neon organisation/subscription rows are correct
- That Operator OS can implement the customer

Use those only as historical notes if needed. Re-run the real test on Gen 2.
