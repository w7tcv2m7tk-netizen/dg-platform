# AGENTS.md

## Hard architectural rule — WordPress is legacy / connector only

**DigitalGate Gen 2 (`dg-platform-web`) is the platform. This WordPress plugin is not.**

WordPress must not be treated as a fallback when Gen 2 does not yet have a capability. If Gen 2 is missing something, **build it in Gen 2**. Do not recreate it here and migrate later. That doubles development, testing, and maintenance.

### Before writing any code

Ask: **“Is this DigitalGate platform functionality?”**

| Answer | Where it belongs |
|--------|------------------|
| **Yes** — identity, orgs, users, permissions, CRM, onboarding, Founding 10, billing, Stripe state, Apps, Business Profile, Goals, implementation, Operator OS, support, communications, AI, intelligence, scoring, automation, platform admin, customer-facing DigitalGate product | **`dg-platform-web` only.** Stop. Do not implement it in this plugin. Use old WP code as a **specification**, then build it in Gen 2. |
| **No** — talking to a **customer’s existing WordPress website** (forms → webhook, listing mirror, health probe, iCal feed) | Thin **WordPress Connector** change in this plugin is allowed. No new domain SoT. |
| **Unsure** | Read `dg-platform/docs/ARCHITECTURE-BOUNDARY.md` and `dg-platform/docs/GEN1-GEN2-DECOUPLING-AUDIT.md`. Default to Gen 2. |

If you catch yourself thinking “the old WordPress version already does this, so we can use that…” — **no.** Use the old implementation to understand the requirements, then build it properly in Gen 2.

This workspace often contains **only** `dg-platform`. Missing `dg-platform-web` is not permission to implement platform features in WordPress.

Full rule + end-state diagram: `dg-platform/docs/ARCHITECTURE-BOUNDARY.md`.  
Capability audit: `dg-platform/docs/GEN1-GEN2-DECOUPLING-AUDIT.md`.  
Retirement order (do not start a new WP product path): `dg-platform/docs/WP-RETIREMENT-MIGRATION-PROGRAMME.md`.

## Cursor Cloud specific instructions

### What this repo is
`dg-platform/` is the **legacy Gen 1 WordPress plugin**, shrinking toward a **WordPress Connector** (forms, public mirrors, health probes, Dev API). It still contains historical CRM/modules and `digitalgate/v1` — those are **not** the place to add new DigitalGate product. The platform is **`dg-platform-web`** (Gen 2). `dg-platform/mcp-server/` is an **optional** Node.js MCP sidecar for Cursor. There is **no PHP dependency manager** (no Composer) and **no automated lint/test tooling** (no PHPUnit/PHPCS/ESLint, no CI).

### Environment config (source of truth)
Repository-managed via `.cursor/environment.json` (Dockerfile base):

| Phase | Command | Purpose |
|---|---|---|
| `build` | `.cursor/Dockerfile` | PHP 8.3, MariaDB, WP-CLI, Node 22 |
| `install` | `bash .cursor/scripts/install-wordpress.sh` | WordPress at `~/wordpress`, DB/user, plugin symlink + activate, MCP `npm install` |
| `start` | `bash .cursor/scripts/start-mariadb.sh` | Idempotent MariaDB daemon |
| `terminals` | `wordpress` | `wp server` on `:8080` |

After clone, editing `/workspace/dg-platform` is live — the plugin is symlinked into `~/wordpress/wp-content/plugins/dg-platform`.

### Services

| Service | Required | How it starts |
|---|---|---|
| MariaDB | Yes | Environment `start` (`start-mariadb.sh`). Manual fallback: same script. |
| WordPress (`wp server` :8080) | Yes | Environment `terminals` entry `wordpress`. Manual: `cd ~/wordpress && wp server --host=0.0.0.0 --port=8080 --allow-root` |
| MCP server | No | `cd dg-platform/mcp-server && npm start` (stdio) |

### Credentials / access (local dev only)
- WP admin: `admin` / `admin` (email `admin@example.com`)
- DB: database `wordpress`, user `wpuser`, password `wppass`, host `localhost`
- Admin pages: `admin.php?page=dg-platform` (dashboard), `admin.php?page=dg-platform-contacts` (Contacts)

### Non-obvious notes
- `start-mariadb.sh` bridges PHP's mysqli socket (`/var/run/mysqld/mysqld.sock`) to the real MariaDB socket under `/run/mysqld` when `/var/run` is not symlinked to `/run` (common in agent images). Always start MariaDB via that script, not bare `mysqld_safe`.
- All `wp` (WP-CLI) commands need `--allow-root` in this environment.
- Plugin schema (`wp_dg_*` tables) auto-creates/upgrades on activation and admin requests (`DG_Activator`). No standalone migrations. If tables look missing: `wp plugin activate dg-platform --path="$HOME/wordpress" --allow-root`.
- Activation enables only the `core` module first; industry modules load on the next admin request (deferred to avoid activation timeouts).
- External integrations (Stripe, OpenAI/Anthropic, Google, email/Resend, Airbnb, Gen-2/Neon) are optional and can stay unconfigured for local smoke tests.
- Package the installable plugin zip with `bash dg-platform/scripts/build-zip.sh` (not required for day-to-day development).
