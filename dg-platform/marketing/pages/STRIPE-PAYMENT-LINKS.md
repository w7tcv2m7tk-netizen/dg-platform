# Stripe Payment Links — Migration Guide

Align Stripe with the DigitalGate pricing framework (Platform · Growth Systems · Industry Apps · Add-ons).

**Currency:** AUD · **Billing:** Monthly recurring unless noted

---

## 1. What to do with existing links

| Current link | Price | Action | New name / price |
|--------------|-------|--------|------------------|
| **Foundation** | A$497/mo | ✅ **Keep** — rename if needed | Growth Systems — Foundation · A$497/mo |
| **Growth** | A$997/mo | ✅ **Keep** | Growth Systems — Growth · A$997/mo |
| **Market Authority** | A$1,997/mo | ✏️ **Rename** | Growth Systems — Authority · A$1,997/mo |
| **Agency Growth Partner** | A$2,997/mo | ✅ **Keep** — updated to A$2,997/mo | Growth Systems — Growth Partner · A$2,997/mo |
| **Vendor Lead Generation System** | A$997/mo | 🗄️ **Archive** | Duplicate of Growth — redirect old URL to Growth link |
| **Launch Agency Website** | A$1,497 one-time | 🗄️ **Off main pricing page** | Keep link — use on `/website-projects/` or proposals |
| **Growth Agency Website** | A$2,997 one-time | 🗄️ **Off main pricing page** | Same |
| **Authority Agency Website** | A$4,997 one-time | 🗄️ **Off main pricing page** | Same |

> **Do not delete** old links if existing customers subscribe through them. **Deactivate** and set redirects in Stripe (Payment Link settings → After payment → or use Stripe Dashboard note).

**Public website Professional Services:**  
- **Website Migration & DigitalGate Setup** — From $1,497 one-time (existing site → live on DigitalGate Infrastructure; no redesign)  
- **Website Build** — From $1,997 one-time (new site only; do not publish Launch/Growth/Business/Custom package tables)  
Internal Build quote bands: Entry $1,997+ · Growth $3,497+ · Business $5,497+ · Custom $7,500+.

---

## 2. Create new Products (Stripe → Product catalogue)

Create one product per sellable item. Suggested naming:

### Platform (SaaS)
| Product name | Price | Recurring |
|--------------|-------|-----------|
| DG Platform — Starter | A$99.00 | Monthly |
| DG Platform — Professional | A$249.00 | Monthly |
| DG Platform — Business | A$499.00 | Monthly |

Enterprise = **no Payment Link** → Contact Sales only.

### Growth Systems (Agency) — update/create
| Product name | Price | Recurring |
|--------------|-------|-----------|
| DG Growth — Foundation | A$497.00 | Monthly |
| DG Growth — Growth | A$997.00 | Monthly |
| DG Growth — Authority | A$1,997.00 | Monthly |
| DG Growth — Growth Partner | A$2,997.00 | Monthly |

### Industry Apps (+$99/mo) + Industry Templates (+$29/mo each extra)

Canonical rule: **Industry App $99** includes **1 Template**; additional Templates **+$29/mo**. Do not create separate $99 Industry products for Real Estate, Commercial, or Accommodation — those are Templates under Property / Hospitality & Accommodation.

| Product name | Stripe key in HTML | Notes |
|--------------|-------------------|--------|
| DG Industry — Property | `app-property` | Includes 1 Template (e.g. Real Estate) |
| DG Industry — Hospitality & Accommodation | `app-hospitality-accommodation` | Includes 1 Template (e.g. Short-Stay) |
| DG Industry — Services | `app-services` | Includes 1 Template (e.g. Cleaning) |
| DG Industry — Finance | `app-finance` | Includes 1 Template (e.g. Accounting) |
| DG Industry — Automotive | `app-automotive` | |
| DG Industry — Creator & Media | `app-creator` | |
| DG Template — additional (generic) | `template-extra` | A$29/mo — or one product per Template |
| Legacy: Real Estate / Accommodation / Commercial | `app-real-estate` etc. | Alias / migrate to Industry + Template; do not sell as separate Industries |

Platform checkout already computes Industry + extra Template line items via `industryCheckoutLines()` in platform-core.

### Premium Apps
| Product name | Stripe key in HTML |
|--------------|-------------------|
| DG Premium — Prospecting & Opportunity Engine (+$99/mo) | `premium-prospecting` — create Payment Link when Stripe product exists |
| DG Premium — AI Visibility Pro | `premium-ai-visibility` |
| DG Premium — SEO Pro | `premium-seo` |
| DG Premium — Automation Pro | `premium-automation` |
| DG Premium — Analytics Pro | `premium-analytics` |
| DG Premium — Social Pro (+$79/mo) | `premium-social` → `https://buy.stripe.com/aFaaEWh2T4CAa7q11hb7y0s` |

### Add-ons
| Product name | Stripe key in HTML |
|--------------|-------------------|
| DG Add-on — Voice AI | `addon-voice-ai` |
| DG Add-on — Extra User | `addon-extra-users` |
| DG Add-on — White Label | `addon-white-label` |
| DG Add-on — Training (one-time) | `addon-training` |

---

## 3. Create Payment Links (Stripe → Payment Links → Create)

For each product/price:

1. **Create payment link**
2. **Name** = match table above (shows in Stripe dashboard)
3. **Price** = select the recurring price
4. **Settings recommended:**
   - Collect billing address: Optional
   - Allow promotion codes: Yes (for Founding Customer pilots)
   - After payment: Redirect to `https://digitalgate.com.au/thank-you/` (or strategy session)
   - Confirmation page message: mention onboarding email within 24h
5. **Metadata** (important for future automation):

   | Key | Example value |
   |-----|---------------|
   | `dg_category` | `platform` / `growth` / `app` / `premium` / `addon` |
   | `dg_plan` | `starter` / `professional` / `business` / `foundation` / etc. |
   | `dg_platform_tier` | maps to `DG_Plan_Registry` key when applicable |

6. Copy URL → paste into `pricing-page.html` → `STRIPE_PAYMENT_LINKS` config (below)

---

## 4. Paste URLs into pricing page

After creating links, edit `marketing/pages/pricing-page.html` — find `STRIPE_PAYMENT_LINKS` and replace `REPLACE_ME` values:

```javascript
const STRIPE_PAYMENT_LINKS = {
  platform: {
    starter:       'https://buy.stripe.com/...',
    professional:  'https://buy.stripe.com/...',
    business:      'https://buy.stripe.com/...',
  },
  growth: {
    foundation:    'https://buy.stripe.com/...',  // existing Foundation link OK
    growth:        'https://buy.stripe.com/...',  // existing Growth link OK
    authority:     'https://buy.stripe.com/...',  // was Market Authority
    partner:       'https://buy.stripe.com/...',  // NEW $2,997 link
  },
  hero_trial: 'https://digitalgate.com.au/onboarding/',
  cta_trial: 'https://digitalgate.com.au/onboarding/',
};
```

Re-paste HTML into Oxygen after updating URLs.

---

## 5. Quick checklist in Stripe Dashboard

### Keep / rename (no new link needed if price unchanged)
- [ ] Foundation A$497/mo
- [ ] Growth A$997/mo
- [ ] Market Authority → rename to **Authority** A$1,997/mo

### Replace
- [x] Agency Growth Partner: updated to **Growth Partner** A$2,997/mo — `https://buy.stripe.com/eVq5kC7sj0mk2EYdO3b7y07`

### Archive (deactivate, keep for existing subs)
- [ ] Vendor Lead Generation System (duplicate)
- [ ] Website one-time links (unless still selling separately)

### Create new
- [ ] Platform Starter A$99/mo
- [ ] Platform Professional A$249/mo
- [ ] Platform Business A$499/mo
- [ ] 5 Industry App links
- [ ] 5 Premium App links (incl. Social Pro)
- [ ] 4 Add-on links

---

## 6. Founding Customer pilot

Create a Stripe **Promotion code** e.g. `FOUNDING50` (50% off 3 months) and enable on Platform + Growth Partner links.

Or create separate **Founding Customer** payment links at discounted prices with metadata `dg_founding: true`.

---

## 7. Plugin alignment (post-checkout)

**v10.30.0+** — Stripe Payment Link checkout triggers automatic client provisioning on DigitalGate:

1. Stripe webhook URL: `https://digitalgate.com.au/wp-json/digitalgate/v1/billing/webhook`
2. Event: `checkout.session.completed`
3. Set signing secret in **DG Platform → API & MCP** (option `dg_stripe_billing_webhook_secret`) or via wp-cli:
   `wp option update dg_stripe_billing_webhook_secret whsec_...`
4. Add metadata on each Payment Link (see §3)

**What happens automatically:**
- CRM contact + organisation created (tags: `DigitalGate Client`, `Payment Received`, `Awaiting Onboarding`)
- `dg_client` WordPress user + portal access
- Email to customer with onboarding form link (pre-filled from metadata) + client portal login
- Admin notification to `onboarding@digitalgate.com.au`

**Manual step still required:** enable the correct plan/modules on the **client's** WordPress site via **Modules & Plan** after deployment.

```php
// Future: auto-sync on client site from metadata
DG_Plan_Registry::set_plan('professional'); // from dg_platform_tier metadata
```

---

## 8. Suggested Stripe dashboard folder structure

Use Payment Link **names** with prefixes so they sort cleanly:

```
Platform · Starter
Platform · Professional
Platform · Business
Growth · Foundation
Growth · Growth
Growth · Authority
Growth · Partner
App · Real Estate
App · Accommodation
...
```
