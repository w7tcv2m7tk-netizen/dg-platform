# ADR 0014 — WordPress is legacy / connector only

**Status:** Proposed (copy into `dg-platform-web/docs/adr/`)  
**Date:** 31 August 2026  
**Supersedes in part:** [ADR 0002](https://github.com/w7tcv2m7tk-netizen/dg-platform-web/blob/HEAD/docs/adr/0002-wordpress-as-connector.md) — 0002 correctly said WP is not the platform, but still allowed a “temporary bridge” and implied missing Gen 2 features could wait on WP. That fallback is **closed**.

## Context

Teams kept implementing DigitalGate product (onboarding, Founding 10, billing, CRM) in the Gen 1 WordPress plugin when Gen 2 was incomplete, intending to migrate later. That doubles build/test/maintain cost and recreates Gen 1 inside Gen 2’s timeline.

## Decision

WordPress is **legacy / connector only**.

- If Gen 2 lacks a DigitalGate capability, **build it in Gen 2** (`dg-platform-web`).
- Do **not** create a temporary WP implementation to migrate later.
- Old WP code is a **specification**, not a runtime to extend.
- Customer WordPress sites talk to Gen 2 through the **WordPress Connector** only.

## WordPress may be

A customer’s existing website · a connector · a leftover Gen 1 install during migration · historical data · an external system (same class as Shopify).

## WordPress must not be

DigitalGate auth, orgs, users, permissions, onboarding, Founding 10, CRM, billing, Stripe subscription state, Apps, Business Profile, Goals, implementation, Operator OS, support, communications, AI, intelligence, scoring, automation, platform admin, or any new customer-facing DigitalGate product.

## SoT

Identity → Clerk / Gen 2 · Organisation, customer, CRM, Apps, onboarding, support, communications, AI, implementation, Operator OS → Gen 2 · Subscriptions / billing state → Gen 2 + Stripe.

## Consequences

**Positive:** One platform, one test surface, no WP stand-ins.  
**Negative:** Features wait until they exist in Gen 2 — that is intended.  
**Neutral:** Connector endpoints and bugfixes on customer WP sites remain allowed.

## Checklist (before a major feature)

- [ ] Is this DigitalGate platform functionality? If yes → `dg-platform-web` only.
- [ ] Is this a customer WP website integration? If yes → connector only.
- [ ] Did we almost “use the old WP version”? If yes → spec from WP, implement in Gen 2.
