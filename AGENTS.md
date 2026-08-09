# AGENTS.md

## Cursor Cloud specific instructions

### What this repo is
`dg-platform/` is a single **WordPress plugin** (PHP), "DG Platform" — a modular CRM (Contacts, Tasks, Calendar, Documents, Reports, REST API `digitalgate/v1`) with several industry modules. `dg-platform/mcp-server/` is an **optional** Node.js MCP sidecar for Cursor. There is **no PHP dependency manager** (no Composer) and **no automated lint/test tooling** (no PHPUnit/PHPCS/ESLint, no CI).

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
