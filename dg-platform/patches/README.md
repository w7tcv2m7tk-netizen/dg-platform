# Patches for `dg-platform-web`

This plugin repo cannot push Gen 2. Copy files into `dg-platform-web` as listed below.

## Architecture boundary (required)

See `gen2-docs/README.md` — ADR 0014 + `AGENTS.md` snippet. WordPress is legacy / connector only.

## Gen2 mobile menu fix (dg-platform-web)

The live DigitalGate site (`digitalgate.com.au`) renders marketing chrome through **Gen2** (`dg-platform-web`). Header `<script>` tags are stripped on seed/render, so the hamburger in `header.html` does nothing unless Gen2 re-binds it.

## Root causes (confirmed on production)

1. **JS**: `ChromeHeaderHtml` listeners were attached to nodes that React could replace; clicks never toggled `.open`.
2. **CSS**: Gen2 forced `max-height: 0 !important` and opened with `max-height: min(85vh, 720px) !important` while a `max-height` transition was active — the drawer stayed at **0px** even after `.open` was added.
3. **Fallback**: `.wb-chrome-html:has(#dgMobileBtn) .wb-chrome-html-menu-btn { display:none }` hid the working Gen2 fallback whenever the native button existed — even when hydration failed.

## Fix in this monorepo (`dg-platform`)

`marketing/pages/header.html` now uses a **CSS checkbox toggle** (`#dgMobileNavToggle` + label `#dgMobileBtn`) with `:has(:checked)` rules that beat Gen2’s `max-height: 0 !important`. Scripts remain progressive enhancement for Oxygen.

After merge, **re-seed DigitalGate chrome** from the Gen2 app (sibling checkout):

```bash
cd dg-platform-web
node --env-file=.env.local scripts/seed-digitalgate-marketing-pages.mjs
```

That script reads `../dg-platform/marketing/pages/header.html`, strips scripts, and writes `metadata.chrome.headerHtml`.

## Fix to apply in `dg-platform-web` (this agent cannot push that repo)

Copy from `dg-platform/patches/`:

| Patch file | Destination in dg-platform-web |
|---|---|
| `ChromeHeaderHtml.tsx` | `src/components/websites/ChromeHeaderHtml.tsx` |
| `gen2-mobile-menu-css-block.css` | Replace the `@media (max-width: 880px)` DigitalGate drawer block inside `src/components/websites/website-renderer-css.ts` |
| `test-dg-mobile-chrome-menu.mjs` | `scripts/test-dg-mobile-chrome-menu.mjs` (optional regression) |

Gen2 changes:

- Event delegation + React state sync for `#dgMobileBtn` / `#dgNavLinks`
- Respect `#dgMobileNavToggle` checkbox (no `preventDefault` on the label)
- Open drawer with `display:flex` / closed with `display:none` (no `min()` + transition trap)
- Hide fallback hamburger only when `.dg-menu-bound` is set

Then deploy Gen2 and re-seed chrome as above.
