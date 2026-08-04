# DigitalGate Pricing Page — Deploy Guide

Paste the contents of `marketing/pages/pricing-page.html` into the Oxygen **HTML** component on `/pricing`.

**Stripe Payment Links:** see `marketing/pages/STRIPE-PAYMENT-LINKS.md` — paste URLs into `STRIPE_PAYMENT_LINKS` in the HTML before deploying.

## WordPress page SEO (outside HTML block)

| Field | Value |
|-------|--------|
| **Page title** | DigitalGate Pricing \| Business Operating Platform & Growth Systems |
| **Meta description** | DigitalGate Business Operating Platform from $99/mo. Add industry apps, premium modules, and optional Growth Systems. Software only, marketing only, or both. |

## Recommended nav restructure

Replace the top-level **Services** dropdown with platform-first navigation:

| Nav item | Links |
|----------|--------|
| **Platform** | `/pricing` (anchor `#platform`), Features, `/free-agency-audit/` |
| **Growth Systems** | `/pricing#growth-systems`, Foundation, Growth, Authority, Partner |
| **Industry Apps** | `/pricing#industry-apps` |
| **Frameworks** | (keep existing) |
| **Insights** | (keep existing) |
| **About** | (keep existing) |
| **Contact** | (keep existing) |
| **CTA** | Free Agency Audit or Start Free Trial |

## Footer copy update

Replace:

> Real Estate Growth Systems, AI Visibility and Lead Generation for Australian Agencies.

With:

> AI-powered Business Operating Platform with optional Growth Systems for Australian businesses.

## Section anchors (add `id` attributes in HTML if needed)

- `#platform` — Business Operating Platform tiers
- `#industry-apps` — Industry Apps grid
- `#premium-apps` — Premium Apps
- `#addons` — Add-ons
- `#growth-systems` — Growth Systems (agency)
- `#value-comparison` — Value comparison & industry savings

## Sales collateral (internal)

- `marketing/pages/SALES-ONE-PAGER.md` — Talk tracks, objection handling, bundles, do-not-sell list
- `marketing/pages/pricing-value-section.html` — Standalone value section (also embedded in pricing-page.html)

## Plugin alignment (v10.7.0+)

Plan tiers in **DG Platform → Modules & Plan** mirror the pricing page:

| Tier | Price | Industry modules |
|------|-------|------------------|
| Starter | $99 | 0 |
| Professional | $249 | 1 |
| Business | $499 | Unlimited |
| Enterprise | Custom | Unlimited |

Production defaults (by hostname):

- `digitalgate.com.au` → Enterprise
- `roerealty.com.au` → Business
- `currumbinvalleyhideaway.com.au` → Business
- `aetherra.com.au` → Professional
