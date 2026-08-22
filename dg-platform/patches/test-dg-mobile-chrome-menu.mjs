/**
 * Regression: DigitalGate chrome mobile drawer must expand when .open is set.
 * Gen2 previously used max-height:min() with a transition which left the drawer at 0px.
 */
import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const css = readFileSync(
  join(root, "src/components/websites/website-renderer-css.ts"),
  "utf8",
);
const chrome = readFileSync(
  join(root, "src/components/websites/ChromeHeaderHtml.tsx"),
  "utf8",
);

describe("DigitalGate mobile chrome menu", () => {
  it("does not use max-height:min() for the open drawer (transition trap)", () => {
    assert.equal(
      /dg-nav-links\.open\s*\{[^}]*max-height:\s*min\(/s.test(css),
      false,
      "open drawer must not use max-height:min()",
    );
  });

  it("shows open drawer with display:flex and hides closed with display:none", () => {
    assert.match(css, /\.wb-site-chrome\s+\.dg-nav-links\s*\{[^}]*display:\s*none\s*!important/s);
    assert.match(css, /\.wb-site-chrome\s+\.dg-nav-links\.open\s*\{[^}]*display:\s*flex\s*!important/s);
  });

  it("only hides fallback hamburger after native menu is bound", () => {
    assert.match(css, /\.wb-chrome-html\.dg-menu-bound:has\(#dgMobileBtn\)/);
    assert.equal(
      /\.wb-chrome-html:has\(#dgMobileBtn\)\s+\.wb-chrome-html-menu-btn\s*\{[^}]*display:\s*none/s.test(
        css,
      ),
      false,
      "unconditional :has(#dgMobileBtn) hide rule must be removed",
    );
  });

  it("binds the hamburger via event delegation on a stable root", () => {
    assert.match(chrome, /addEventListener\("pointerdown"/);
    assert.match(chrome, /closest<HTMLElement>\("#dgMobileBtn/);
    assert.match(chrome, /setOpen/);
    assert.match(chrome, /classList\.toggle\("open", open\)/);
    assert.match(chrome, /dg-menu-bound/);
  });
});
