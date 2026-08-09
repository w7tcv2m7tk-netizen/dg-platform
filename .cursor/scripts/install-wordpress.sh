#!/usr/bin/env bash
# Idempotent WordPress + DG Platform plugin setup (environment.json "install").
# Assumes PHP, MariaDB client/server packages, and WP-CLI are already on the image.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WP_HOME="${WP_HOME:-$HOME/wordpress}"
DB_NAME="${DB_NAME:-wordpress}"
DB_USER="${DB_USER:-wpuser}"
DB_PASS="${DB_PASS:-wppass}"
SITE_URL="${SITE_URL:-http://localhost:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"

echo "==> Ensuring system dependencies"
bash "$ROOT/.cursor/scripts/ensure-system-deps.sh"

echo "==> Refreshing optional MCP server deps"
if [ -f "$ROOT/dg-platform/mcp-server/package.json" ]; then
  npm install --prefix "$ROOT/dg-platform/mcp-server"
fi

echo "==> Ensuring MariaDB is up for schema bootstrap"
bash "$ROOT/.cursor/scripts/start-mariadb.sh"

echo "==> Ensuring database + user"
sudo mariadb -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mariadb -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

echo "==> WordPress core at ${WP_HOME}"
mkdir -p "$WP_HOME"
if [ ! -f "$WP_HOME/wp-load.php" ]; then
  wp core download --path="$WP_HOME" --allow-root
fi

if [ ! -f "$WP_HOME/wp-config.php" ]; then
  wp config create \
    --path="$WP_HOME" \
    --dbname="$DB_NAME" \
    --dbuser="$DB_USER" \
    --dbpass="$DB_PASS" \
    --dbhost=localhost \
    --skip-check \
    --allow-root
fi

if ! wp core is-installed --path="$WP_HOME" --allow-root 2>/dev/null; then
  wp core install \
    --path="$WP_HOME" \
    --url="$SITE_URL" \
    --title="DG Platform Dev" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email \
    --allow-root
fi

echo "==> Linking and activating dg-platform plugin"
mkdir -p "$WP_HOME/wp-content/plugins"
ln -sfn "$ROOT/dg-platform" "$WP_HOME/wp-content/plugins/dg-platform"
wp plugin activate dg-platform --path="$WP_HOME" --allow-root

echo "==> Install complete"
wp core version --path="$WP_HOME" --allow-root
wp plugin list --path="$WP_HOME" --allow-root | head -20
