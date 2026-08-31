# Gen 2 P1 — onboarding decoupling (apply in `dg-platform-web`)

This environment’s GitHub token can **read** `dg-platform-web` and **write** only `dg-platform`. P1 was implemented on a local clone (`cursor/p1-gen2-onboarding-decouple-03c2`, tip `1af571b`) and exported here.

This is **the same change**, not a third onboarding system.

P1 is **NOT DECOUPLED** until these patches are on `dg-platform-web` origin **and** an authenticated Clerk + Neon walkthrough with WordPress unavailable passes.

## Apply

```bash
cd dg-platform-web
git checkout main
git checkout -b cursor/p1-gen2-onboarding-decouple-03c2
# Prefer git apply (verified clean against main d2ee67e). git am may complain about whitespace.
git apply ../dg-platform/patches/gen2-p1/0001-Retire-WordPress-from-the-Gen-2-onboarding-request-p.patch
git apply ../dg-platform/patches/gen2-p1/0002-Add-Team-and-Systems-as-first-class-Gen-2-onboarding.patch
git add -A
git commit -m "P1: retire WP from Gen 2 onboarding; add Team and Systems steps."
npm run test:p1-onboarding
npm run test:unit
git push -u origin cursor/p1-gen2-onboarding-decouple-03c2
```

## Remaining gates (do not mark DECOUPLED until all pass)

1. Branch exists on `w7tcv2m7tk-netizen/dg-platform-web` (this agent cannot push — `permissions.push: false`).
2. Clerk test keys + Neon `DATABASE_URL` + a test login in the environment that runs the walkthrough.
3. Authenticated `/onboarding` walkthrough with WordPress process down and `DG_API_BASE_URL` unset.
4. Browser/server network log: zero `/wp-json/digitalgate/v1/portal/me`, zero `digitalgate.com.au/onboarding/`.
5. Only then consider retiring public WP `/onboarding/` — not before.

## What the patches contain

1. WordPress removed from the onboarding request path (`fetchPortalMe`, `pingApi`, portal sync, webhook).
2. Team + Systems added to `GEN2_ONBOARDING_STEPS`; `/founding/setup` still uses `/onboarding`.
