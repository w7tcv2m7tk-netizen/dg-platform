# Gen 2 P1 — onboarding decoupling (apply in `dg-platform-web`)

This environment’s GitHub token can write `dg-platform` only. P1 was implemented on a local clone of `dg-platform-web` (`cursor/p1-gen2-onboarding-decouple-03c2`) and exported here.

This is **the same change**, not a third onboarding system.

## Apply

```bash
cd dg-platform-web
git checkout main
git checkout -b cursor/p1-gen2-onboarding-decouple-03c2
git am ../dg-platform/patches/gen2-p1/0001-*.patch ../dg-platform/patches/gen2-p1/0002-*.patch
npm run test:p1-onboarding
git push -u origin cursor/p1-gen2-onboarding-decouple-03c2
```

## What landed

1. WordPress removed from the onboarding request path (`fetchPortalMe`, `pingApi`, portal sync, webhook).
2. Team + Systems added to `GEN2_ONBOARDING_STEPS`; `/founding/setup` still uses `/onboarding`.

Do **not** 301 public WP `/onboarding/` until this branch is merged and the WP-unavailable test is signed off in a Clerk + Neon environment.
