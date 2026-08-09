# AGENTS.md

## Cursor Cloud specific instructions

### What this repo is
`dg-platform/` is a single **WordPress plugin** (PHP), "DG Platform" — a modular CRM (Contacts, Tasks, Calendar, Documents, Reports, REST API `digitalgate/v1`) with several industry modules. `dg-platform/mcp-server/` is an **optional** Node.js MCP sidecar for Cursor. There is **no PHP dependency manager** (no Composer) and **no automated lint/test tooling** (no PHPUnit/PHPCS/ESLint, no CI).

### Services

| Service | Required | How to run |
|---|---|---|
| MariaDB (MySQL) | Yes | `sudo mariadbd-safe --datadir=/var/lib/mysql &` then wait a few seconds |
| WordPress dev server (hosts the plugin) | Yes | `cd ~/wordpress && wp server --host=0.0.0.0 --port=8080 --allow-root` |
| MCP server | No (dev/AI sidecar) | `cd dg-platform/mcp-server && npm start` (stdio, no port) |

The VM snapshot already contains a full WordPress install at `~/wordpress` with the plugin **symlinked and activated** (`~/wordpress/wp-content/plugins/dg-platform -> /workspace/dg-platform`), so editing plugin code under `/workspace/dg-platform` is picked up live — no rebuild/reinstall needed.

### Starting the environment (both processes must be started manually on a fresh VM; the update script does NOT start them)
1. Start MariaDB: `sudo mariadbd-safe --datadir=/var/lib/mysql &` (idempotent; skip if already running: `sudo mariadb -e "SELECT 1"`).
2. Start WordPress: run the `wp server` command above (prefer a tmux session so it persists).
3. Open http://localhost:8080 (site) or http://localhost:8080/wp-admin (admin).

### Credentials / access (dev only)
- WP admin: `admin` / `admin` (email `admin@example.com`).
- DB: database `wordpress`, user `wpuser`, password `wppass`, host `localhost`.
- DG Platform admin landing page: `admin.php?page=dg-platform`; Contacts: `admin.php?page=dg-platform-contacts`.

### Non-obvious notes
- All `wp` (WP-CLI) commands here run as root, so pass `--allow-root`.
- The plugin auto-creates/upgrades its `wp_dg_*` DB tables on activation and on admin requests (`DG_Activator`); there are no standalone migrations. If tables look missing, re-run `wp plugin activate dg-platform --allow-root`.
- Activation only enables the `core` module first; vertical/industry modules load on the next admin request (deferred to avoid activation timeouts).
- External integrations (Stripe, OpenAI/Anthropic, Google, email/Resend, Airbnb, Gen-2/Neon) are all optional and can be left unconfigured for local dev/testing.
- Build/package the installable plugin zip with `bash dg-platform/scripts/build-zip.sh` (produces `dg-platform-build.zip`). This is the only build step; it is not required for local development.
