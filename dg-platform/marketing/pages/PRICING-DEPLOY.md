# DigitalGate Pricing Page — Deploy Guide

Part of the website revamp — see **`WEBSITE-REVAMP.md`** for full repositioning guide, homepage, and nav structure.

Paste the contents of `marketing/pages/pricing-page.html` into the Oxygen **HTML** component on `/pricing`.



**Stripe Payment Links:** see `marketing/pages/STRIPE-PAYMENT-LINKS.md` — paste URLs into `STRIPE_PAYMENT_LINKS` in the HTML before deploying.



## WordPress page SEO (outside HTML block)



| Field | Value |

|-------|--------|

| **Page title** | DigitalGate Platform \| Business Operating System for Modern Businesses |

| **Meta description** | The Business Operating System for modern businesses. Connect website, CRM, marketing, AI, automation, payments and customer data into one intelligent platform. |



## Recommended nav restructure



Platform-first navigation — **no Growth Systems dropdown**:



| Nav item | Links |

|----------|--------|

| **Platform** | `/pricing#platform` |

| **Apps** | `/pricing#apps` |

| **Professional Services** | `/pricing#professional-services` |

| **Support Plans** | `/pricing#support-plans` |

| **Frameworks** | (keep existing) |

| **Insights** | (keep existing) |

| **About** | (keep existing) |

| **Contact** | (keep existing) |

| **CTA** | Start Free Trial |



## Footer copy update



Replace:



> Real Estate Growth Systems, AI Visibility and Lead Generation for Australian Agencies.



With:



> AI-powered Business Operating Platform for Australian businesses — with optional professional services and customer success plans.



## Pricing structure (four sections)



1. **#platform** — Starter, Growth, Scale, Enterprise (+ add-ons)

2. **#apps** — Industry Apps, Growth & Intelligence Apps, Platform Capabilities

3. **#professional-services** — Implementation, setup, migration, training, consulting (project-based)

4. **#support-plans** — Standard (included), Priority $199, Success Partner $499, Enterprise Success



## Narrative section anchors



- `#digital-twin` — Digital Twin hero feature

- `#platform-core` — Every platform includes

- `#website-capability` — Website as platform capability flow

- `#screenshots` — Platform screenshots

- `#trust` — Production deployments



## Legacy redirects



If nav or links still point to old anchors, update:



| Old | New |

|-----|-----|

| `#growth-systems` | `#support-plans` |

| `#industry-apps` | `#apps` (subsection) |

| `#growth-apps` | `#apps` (subsection) |

| `#infrastructure` | `#apps` (Platform Capabilities) |

| `#commerce` | `#apps` (Platform Capabilities) |



## Sales collateral (internal)



- `marketing/pages/SALES-ONE-PAGER.md` — **needs update** for new positioning

- `marketing/pages/pricing-value-section.html` — standalone value section



## Plugin alignment (v10.7.0+)



| Tier | Price | Industry modules |

|------|-------|------------------|

| Starter | $99 | 0 |

| Growth | $249 | 1 |

| Scale | $499 | Unlimited |

| Enterprise | Custom | Unlimited |



Production defaults (by hostname):



- `digitalgate.com.au` → Enterprise

- `roerealty.com.au` → Scale (was Business)

- `currumbinvalleyhideaway.com.au` → Scale (was Business)

- `aetherra.com.au` → Growth (was Professional)



## Stripe TODO



Create payment links for:



- Support Priority — $199/mo

- Support Success Partner — $499/mo



Paste into `STRIPE_PAYMENT_LINKS` keys `support-priority` and `support-success-partner`.



Legacy Growth Systems Stripe links remain in the script for existing customers but are no longer displayed on the page.

