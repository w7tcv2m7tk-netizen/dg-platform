# DigitalGate Website Revamp — Deployment Guide

**Status:** Content ready in repo · Oxygen paste required on live site  
**Last updated:** August 2026

This is a **brand repositioning**, not a cosmetic redesign. DigitalGate is an AI-powered Business Operating Platform — not a marketing agency.

---

## Brand anchor

| Element | Copy |
|---------|------|
| **Tagline** | The Gateway to Your Digital World™ |
| **Position** | AI-powered Business Operating Platform |
| **Mission** | Simplify the digital world by connecting technology, data, customers and workflows into one intelligent platform |
| **Core message** | One Platform · One Login · One Source of Truth |
| **Five pillars** | Connect · Centralise · Understand · Automate · Grow |

---

## New navigation (replace Services dropdown)

| Item | URL |
|------|-----|
| **Platform** | `/platform/` or homepage `#platform-overview` |
| **Apps** | `/pricing#apps` |
| **Industries** | `/industries/` or homepage `#industries` |
| **Pricing** | `/pricing` |
| **Resources** | `/insights/` (blog), docs, audit |
| **Company** | `/about/`, `/contact/` |
| **Client Portal** | `https://app.digitalgate.com.au/login` |
| **CTA** | Start Free Trial → `/onboarding/` |

**Remove from primary nav:** Growth Systems, agency service SKUs (SEO-only, Foundation, Authority retainers).

---

## Files in this repo

| File | Use |
|------|-----|
| `header.html` | Paste into Oxygen **header template** (site-wide nav) |
| `footer.html` | Paste into Oxygen **footer template** (site-wide) |
| `homepage.html` | Paste into Oxygen on `/` (homepage) |
| `about-page.html` | Paste into Oxygen on `/about` |
| `contact-page.html` | Paste into Oxygen on `/contact` |
| `discovery-form.html` | Paste into Oxygen on **`/discover`** (WordPress page slug must be `discover`) |
| `digital-business-card.html` | Paste into Oxygen on `/card/` (Ben Roe digital card) |
| `pricing-page.html` | Paste into Oxygen on `/pricing` |
| `PRICING-DEPLOY.md` | Pricing-specific deploy notes |
| `WEBSITE-REVAMP.md` | This guide |

---

## Discovery page (`/discover/`)

| Where | URL |
|-------|-----|
| **Live URL** | [https://digitalgate.com.au/discover/](https://digitalgate.com.au/discover/) |
| **Nav** | Header → Resources → **AI Platform Discovery** |
| **Contact page CTA** | “Start AI Platform Discovery” |
| **Homepage hero** | “AI Platform Discovery” button |

**Deploy checklist**

1. Upload plugin **v10.49.0+** (includes `class-client-discovery.php` + `discovery-form.js`)
2. In WordPress → Pages → add or edit page with slug **`discover`**
3. Paste full contents of `discovery-form.html` into the Oxygen body (Code Block)
4. Publish and test — form posts to `/wp-json/digitalgate/v1/discovery`

If `/discover/` shows a critical error, the plugin is missing/outdated or the page body has a PHP conflict — check `wp-content/debug.log` after upload.

## Platform screenshots

Screenshots are hosted on the app: `https://app.digitalgate.com.au/marketing/screenshots/`

Regenerate after UI changes:

```bash
cd dg-platform-web
npm run build && npm run start -- -p 3010
npm run capture:screenshots
```

Copies PNGs to `public/marketing/screenshots/` and `dg-platform/marketing/assets/screenshots/`.

---

1. Hero — Gateway to Your Digital World™
2. Five Pillars — Connect, Centralise, Understand, Automate, Grow
3. What DigitalGate Connects — ecosystem diagram
4. Platform Overview — Core → Apps → AI → Automation → Connectors → BI
5. Platform Core — included with every plan
6. Apps — Core, Industry, Growth, Infrastructure
7. Digital Twin™ — intelligence layer
8. AI Throughout — 9 AI capabilities
9. Industries — vertical solutions via Apps
10. Why DigitalGate — one platform messaging
11. Success Stories — Roe Realty, CVH, DigitalGate
12. CTA — Start Free Trial / View Pricing

---

## Pricing page structure (done)

1. **Platform** — Starter, Growth, Scale, Enterprise
2. **Apps** — Industry, Growth & Intelligence, Platform Capabilities
3. **Professional Services** — implementation, migration, training, consulting
4. **Support Plans** — Standard (included), Priority $199, Success Partner $499, Enterprise Success

Growth Systems **removed** as primary category. Legacy Stripe links retained for existing customers.

---

## Pages still needing Oxygen updates

| Page | Action |
|------|--------|
| **Homepage** | Paste `homepage.html` · update meta title/description |
| **Pricing** | Paste `pricing-page.html` · wire Stripe support plan links |
| **About** | Rewrite as platform company · paste `about-page.html` |
| **Contact** | Paste `contact-page.html` · platform consultation form |
| **Services** | Redirect to `/pricing` or replace with `/platform/` |
| **Footer** | "AI-powered Business Operating Platform for Australian businesses" |

### Meta title / description (homepage)

| Field | Value |
|-------|--------|
| **Title** | DigitalGate \| The Gateway to Your Digital World™ |
| **Description** | AI-powered Business Operating Platform. Connect website, CRM, AI, automation, payments and customer data into one intelligent platform. |

---

## Messaging rules (all pages)

**Say:** platform, operating system, Apps, Digital Twin, AI-powered, modular, one login  
**Avoid:** primary positioning as agency, marketing retainers, "Growth Systems" as product name  
**Reinforce:** DigitalGate is NOT a CRM / website company / SEO agency — it unifies all of them

---

## Visual style

- Modern SaaS · dark navy `#0A0E17` · blue accent `#3B82F6`
- Generous whitespace · dashboard screenshots · platform diagrams
- Inter font · pill CTAs · section sub-labels
- Avoid agency-style stock photos and service-grid layouts

---

## Future expansion (architecture ready)

Homepage and pricing support future sections without redesign:

- Marketplace · Developer Platform · Partner Network
- Academy · Templates · Community
- Commerce App · AI Studio · additional Industry Apps

Add as new nav items under **Resources** or **Apps** when live.

---

## Rollout checklist

- [ ] Paste `header.html` into Oxygen header template (replace Services nav)
- [ ] Paste `footer.html` into Oxygen footer template (replace Services column)
- [ ] Paste `about-page.html` into Oxygen on `/about`
- [ ] Paste `contact-page.html` into Oxygen on `/contact`
- [ ] Paste `homepage.html` into Oxygen homepage
- [ ] Paste `pricing-page.html` into Oxygen `/pricing`
- [ ] Update WP nav (Platform, Apps, Industries, Pricing, Resources, Company)
- [ ] Update homepage + site meta titles
- [ ] Update footer tagline site-wide
- [ ] Redirect old Growth Systems URLs to `/pricing#support-plans`
- [ ] Create Stripe links for Priority ($199) and Success Partner ($499)
- [ ] Replace homepage hero on live site (currently Real Estate agency copy)
- [ ] Update `SALES-ONE-PAGER.md` for new positioning
- [ ] Add real platform screenshots when available

---

## Internal references

- Live platform: `https://app.digitalgate.com.au`
- Onboarding: `https://digitalgate.com.au/onboarding/`
- Site audit: `marketing/pages/SITE-AUDIT-2026-08.md`
