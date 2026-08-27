"use client";

import { useEffect, useId, useLayoutEffect, useRef, useState } from "react";

import { rewriteProductFunnelHref } from "@/lib/product-funnel-links";

const DG_MOBILE_MAX = 880;

function hasDigitalGateMenu(html: string) {
  return /id=["']dgMobileBtn["']|dg-mobile-menu-btn/.test(html);
}

function collectNavLinks(html: string): Array<{ href: string; label: string }> {
  const root = document.createElement("div");
  root.innerHTML = html;
  const collected = Array.from(
    root.querySelectorAll<HTMLAnchorElement>(
      ".nav-links a, .wb-aetherra-header .nav-links a, .dg-nav-links > li > a, nav a",
    ),
  )
    .map((a) => ({
      href: rewriteProductFunnelHref(a.getAttribute("href") || ""),
      label: (a.textContent || "").trim(),
    }))
    .filter((l) => l.href && l.label && l.href !== "#");
  const seen = new Set<string>();
  return collected.filter((l) => {
    const key = `${l.href}|${l.label}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function queryDgMobileBtn(root: HTMLElement) {
  return root.querySelector<HTMLElement>("#dgMobileBtn, .dg-mobile-menu-btn");
}

function queryDgNavMenu(root: HTMLElement) {
  return root.querySelector<HTMLElement>("#dgNavLinks, .dg-nav-links");
}

/**
 * Header HTML is injected without executing inline <script>.
 * Bind DigitalGate’s hamburger via event delegation on a stable root so
 * listeners survive React re-applying dangerouslySetInnerHTML.
 */
export function ChromeHeaderHtml({ html }: { html: string }) {
  const panelId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [links, setLinks] = useState<Array<{ href: string; label: string }>>([]);
  const [nativeDgMenu, setNativeDgMenu] = useState(() => hasDigitalGateMenu(html));

  useEffect(() => {
    setLinks(collectNavLinks(html));
    setOpen(false);
  }, [html]);

  // Detect native DG controls after paint (and whenever html changes).
  useLayoutEffect(() => {
    const root = rootRef.current;
    if (!root) return;
    const bound = Boolean(queryDgMobileBtn(root) && queryDgNavMenu(root));
    setNativeDgMenu(bound);
    root.classList.toggle("dg-menu-bound", bound);
  }, [html]);

  // Keep DOM class / aria in sync from React state (re-applies after innerHTML resets).
  useLayoutEffect(() => {
    const root = rootRef.current;
    if (!root) return;
    const btn = queryDgMobileBtn(root);
    const menu = queryDgNavMenu(root);
    const chromeRoot = root.querySelector<HTMLElement>(".wb-chrome-root") || root;

    if (menu) menu.classList.toggle("open", open);
    if (btn) {
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      const icon = btn.querySelector("i");
      if (icon) {
        icon.classList.toggle("fa-times", open);
        icon.classList.toggle("fa-bars", !open);
      }
    }
    document.body.classList.toggle("menu-open", open);
    chromeRoot.classList.toggle("menu-open", open);
    document.body.classList.add("dg-has-fixed-header");
    chromeRoot.classList.add("dg-has-fixed-header");

    return () => {
      document.body.classList.remove("menu-open");
      chromeRoot.classList.remove("menu-open");
    };
  }, [open, html]);

  // Event delegation on stable root — survives button node replacement.
  // Prefer the no-JS checkbox when present so Gen2 script-stripping still works.
  useEffect(() => {
    const root = rootRef.current;
    if (!root) return;

    let lastToggleAt = 0;
    const closeAllDropdowns = () => {
      root.querySelectorAll(".dg-dropdown").forEach((dd) => dd.classList.remove("open"));
      root
        .querySelectorAll(".dg-dropdown-toggle")
        .forEach((t) => t.classList.remove("open"));
    };

    const checkbox = root.querySelector<HTMLInputElement>("#dgMobileNavToggle");

    const onCheckboxChange = () => {
      setOpen(Boolean(checkbox?.checked));
    };

    const onPointerDown = (e: Event) => {
      const target = e.target as Element | null;
      if (!target) return;

      // Checkbox/label path: don't preventDefault — let the checkbox toggle.
      if (checkbox && target.closest("#dgMobileBtn, .dg-mobile-menu-btn, #dgMobileNavToggle")) {
        return;
      }

      const mobileBtn = target.closest<HTMLElement>("#dgMobileBtn, .dg-mobile-menu-btn");
      if (mobileBtn && root.contains(mobileBtn)) {
        e.preventDefault();
        e.stopPropagation();
        const now = Date.now();
        if (now - lastToggleAt < 350) return;
        lastToggleAt = now;
        setOpen((v) => !v);
        return;
      }

      const toggle = target.closest<HTMLElement>(".dg-dropdown-toggle");
      if (toggle && root.contains(toggle)) {
        if (window.innerWidth > DG_MOBILE_MAX) return;
        const dropdownId = toggle.getAttribute("data-dg-dropdown");
        const dropdown = dropdownId
          ? root.querySelector<HTMLElement>(`#${CSS.escape(dropdownId)}`)
          : null;
        if (!dropdown) return;
        e.preventDefault();
        e.stopPropagation();
        const now = Date.now();
        if (now - lastToggleAt < 350) return;
        lastToggleAt = now;
        const isOpen = dropdown.classList.contains("open");
        closeAllDropdowns();
        if (!isOpen) {
          dropdown.classList.add("open");
          toggle.classList.add("open");
        }
      }
    };

    const onClick = (e: Event) => {
      const target = e.target as Element | null;
      if (!target || window.innerWidth > DG_MOBILE_MAX) return;
      const link = target.closest<HTMLAnchorElement>(".dg-nav-links a");
      if (!link || !root.contains(link)) return;
      const isToggle = link.classList.contains("dg-dropdown-toggle");
      const isInDropdown = Boolean(link.closest(".dg-dropdown"));
      if (!isToggle && !isInDropdown) setOpen(false);
    };

    const onResize = () => {
      if (window.innerWidth > DG_MOBILE_MAX) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false);
    };

    checkbox?.addEventListener("change", onCheckboxChange);
    root.addEventListener("pointerdown", onPointerDown, true);
    root.addEventListener("click", onClick);
    window.addEventListener("resize", onResize);
    window.addEventListener("keydown", onKey);

    return () => {
      checkbox?.removeEventListener("change", onCheckboxChange);
      root.removeEventListener("pointerdown", onPointerDown, true);
      root.removeEventListener("click", onClick);
      window.removeEventListener("resize", onResize);
      window.removeEventListener("keydown", onKey);
      document.body.classList.remove("dg-has-fixed-header");
      root.classList.remove("dg-has-fixed-header", "dg-menu-bound", "menu-open");
    };
  }, [html]);

  // Keep checkbox in sync when React state changes (e.g. Escape / resize close).
  useLayoutEffect(() => {
    const root = rootRef.current;
    if (!root) return;
    const checkbox = root.querySelector<HTMLInputElement>("#dgMobileNavToggle");
    if (checkbox && checkbox.checked !== open) checkbox.checked = open;
  }, [open, html]);

  useEffect(() => {
    if (!open || nativeDgMenu) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false);
    };
    const htmlEl = document.documentElement;
    htmlEl.classList.add("wb-menu-scroll-lock");
    window.addEventListener("keydown", onKey);
    return () => {
      htmlEl.classList.remove("wb-menu-scroll-lock");
      window.removeEventListener("keydown", onKey);
    };
  }, [open, nativeDgMenu]);

  useEffect(() => {
    return () => {
      document.documentElement.classList.remove("wb-menu-scroll-lock");
      document.body.style.overflow = "";
      document.body.classList.remove("menu-open", "dg-has-fixed-header");
    };
  }, []);

  // Fallback drawer only when native DG controls are absent.
  // CSS also gates visibility on .dg-menu-bound so a dead native btn can't hide the fallback.
  const showFallbackDrawer = !nativeDgMenu && links.length > 0;

  return (
    <div
      ref={rootRef}
      className={["wb-chrome-html", open ? "is-menu-open" : ""].filter(Boolean).join(" ")}
    >
      <section
        className="wb-section wb-html-block wb-site-chrome wb-site-chrome-header"
        dangerouslySetInnerHTML={{ __html: html }}
      />
      {showFallbackDrawer ? (
        <>
          <button
            type="button"
            className="wb-chrome-html-menu-btn"
            aria-label={open ? "Close menu" : "Open menu"}
            aria-expanded={open}
            aria-controls={panelId}
            onClick={() => setOpen((v) => !v)}
          >
            <span className="wb-brand-chrome-menu-icon" aria-hidden="true">
              <span />
              <span />
              <span />
            </span>
          </button>
          <div
            className={["wb-brand-chrome-backdrop", open ? "is-open" : ""]
              .filter(Boolean)
              .join(" ")}
            hidden={!open}
            onClick={() => setOpen(false)}
          />
          <div
            id={panelId}
            className={["wb-brand-chrome-panel", open ? "is-open" : ""]
              .filter(Boolean)
              .join(" ")}
            hidden={!open}
            role="dialog"
            aria-modal="true"
            aria-label="Site menu"
          >
            <nav className="wb-brand-chrome-nav wb-brand-chrome-nav--mobile" aria-label="Mobile">
              {links.map((link) => (
                <a
                  key={`${link.href}-${link.label}`}
                  href={link.href}
                  onClick={() => setOpen(false)}
                >
                  {link.label}
                </a>
              ))}
            </nav>
          </div>
        </>
      ) : null}
    </div>
  );
}
