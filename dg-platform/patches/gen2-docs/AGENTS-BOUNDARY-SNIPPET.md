# Paste near the top of `dg-platform-web/AGENTS.md` (after Next.js rules)

## Hard architectural rule — WordPress is legacy / connector only

DigitalGate Gen 2 (this repo) is the platform. The `dg-platform` WordPress plugin is not a fallback.

If a capability is missing here, **build it here**. Do not implement it in WordPress to migrate later.

Before implementing:

1. Is this DigitalGate platform functionality? → this repo.
2. Is this integration with a customer’s WordPress site? → WordPress Connector only.
3. Does old WP already do it? → use it as a spec, then implement here.

WordPress must not own: auth, organisations, CRM, onboarding, Founding 10, billing/Stripe state, Apps, Business Profile, Goals, implementation, Operator OS, support, communications, AI, or new customer-facing DigitalGate product.

Full text: sibling plugin `dg-platform/docs/ARCHITECTURE-BOUNDARY.md` and `dg-platform/docs/GEN1-GEN2-DECOUPLING-AUDIT.md`. Also add `docs/adr/0014-wordpress-legacy-connector-only.md` from `dg-platform/patches/gen2-docs/`.
