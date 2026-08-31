# DigitalGate architectural boundary

**Status:** Binding from 31 August 2026  
**Applies to:** all DigitalGate development (`dg-platform-web`, this plugin, connectors, agents)  
**Supersedes:** treating WordPress as a fallback, stand-in, or temporary implementation platform

This is a development constraint, not a suggestion. Check every major feature against it before writing code.

---

## The rule

**WORDPRESS IS LEGACY / CONNECTOR ONLY.**

WordPress is not an implementation platform for DigitalGate Gen 2.

If Gen 2 does not currently have a capability we need, we build that capability in **Gen 2**. We do not recreate it in WordPress and migrate it later.

That approach creates duplicate systems, duplicate state, and twice the development, testing, and maintenance.

---

## Decision gate (required before implementation)

1. **Is this DigitalGate platform functionality?**
2. If **yes** → implement in `dg-platform-web`. Stop work in this plugin.
3. If the old WP system has useful behaviour → treat it as a **reference / specification** only, then implement properly in Gen 2.
4. If this is integration with a **customer’s WordPress website** → WordPress Connector only (thin webhook, mirror, or probe). No new platform SoT in WP.
5. Never create a temporary WP implementation with the intention of migrating it later.

If a developer says “the old WordPress version already does this, so we can use that…” the answer is:

> **No. Use the old implementation to understand the requirements, then build it properly in Gen 2.**

Missing `dg-platform-web` in the current workspace is **not** an exception.

---

## What WordPress may be

| Allowed | Meaning |
|---------|---------|
| A customer’s existing website | Their CMS, not DigitalGate |
| A WordPress Connector | Forms, listing mirrors, health probes, iCal, inbound webhooks |
| A legacy Gen 1 installation during migration | Read/sync until retired |
| A source of historical / customer data | Import or dual-write **out**, not new SoT |
| An external system DigitalGate connects to | Same class as Shopify, Google, Xero |

---

## What WordPress must not be used for

DigitalGate authentication · organisations · users/permissions · onboarding · Founding 10 · CRM · customer management · billing · Stripe subscription state · Apps · Business Profiles · Goals · implementation management · Operator OS · support · communications · AI · intelligence · scoring · automation · platform configuration · platform administration · DigitalGate workflow/state · **any new customer-facing DigitalGate functionality**

**WordPress can connect to DigitalGate. It cannot be the place where DigitalGate operates.**

---

## One source of truth

| Concern | Source of truth |
|---------|-----------------|
| Identity | Clerk / Gen 2 |
| Organisation | Gen 2 (Neon) |
| Customer | Gen 2 |
| Business Profile | Gen 2 |
| CRM | Gen 2 |
| Apps | Gen 2 |
| Subscriptions | Gen 2 + Stripe |
| Billing state | Gen 2 + Stripe |
| Onboarding | Gen 2 |
| Support | Gen 2 |
| Communications | Gen 2 |
| AI / Intelligence | Gen 2 |
| Implementation | Gen 2 |
| Operator OS | Gen 2 |

WordPress must not contain another version of those systems as a living product.

Capability-by-capability status: [GEN1-GEN2-DECOUPLING-AUDIT.md](./GEN1-GEN2-DECOUPLING-AUDIT.md).

---

## End state

```
DigitalGate Public Website
        ↓
DigitalGate Gen 2
        ↓
Platform Core
        ↓
Apps / Services / Intelligence / Automation
        ↓
Stripe + external connectors


Customer WordPress website
        ↕
DigitalGate WordPress Connector
        ↕
DigitalGate Gen 2
```

The WordPress Connector is an **integration**. It is not the DigitalGate platform.

---

## Categories (use these in PRs)

| Category | Meaning |
|----------|---------|
| **KEEP IN GEN 2** | Already (or should be) native Gen 2. Do not add a WP twin. |
| **MIGRATE** | Still live or dual-written in WP. Move SoT to Gen 2, then stop writing WP. |
| **CONNECTOR ONLY** | WP remains as an external website integration. |
| **LEGACY** | Keep running for existing installs until migration completes. No new features. |
| **DELETE / RETIRE** | Remove or 301 away once Gen 2 covers it. |

---

## Related

- This repo: `AGENTS.md` (agent gate)
- Gen 2 (copy into `dg-platform-web` if not yet merged): `docs/adr/0002-wordpress-as-connector.md` (too soft — see `patches/gen2-docs/ADR-0014-wordpress-legacy-connector-only.md`)
- Gen 2 detach tickets: `dg-platform-web` `docs/WP-DETACH-BACKLOG.md` (execution list; this boundary is stricter than that backlog’s “temporary bridge” language)
