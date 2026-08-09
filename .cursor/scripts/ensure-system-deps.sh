#!/usr/bin/env bash
# Install PHP/MariaDB/WP-CLI/Node when missing (fallback for non-Dockerfile bases).
# Idempotent; no-op when the Dockerfile image already provides these tools.
set -euo pipefail

need_apt=0
command -v php >/dev/null 2>&1 || need_apt=1
command -v mariadb >/dev/null 2>&1 || need_apt=1
command -v mysqld >/dev/null 2>&1 || command -v mariadbd >/dev/null 2>&1 || need_apt=1

if [ "$need_apt" -eq 1 ]; then
  echo "==> Installing PHP + MariaDB system packages"
  sudo apt-get update -y
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    ca-certificates curl git gnupg less unzip zip sudo \
    mariadb-server mariadb-client \
    php php-cli php-mysql php-curl php-gd php-mbstring php-xml php-zip php-bcmath php-intl
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "==> Installing WP-CLI"
  curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  sudo mv /tmp/wp-cli.phar /usr/local/bin/wp
  sudo chmod +x /usr/local/bin/wp
fi

if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
  echo "==> Installing Node.js 22"
  curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends nodejs
fi

echo "==> System deps OK (php=$(php -r 'echo PHP_VERSION;'), wp=$(wp --info --allow-root 2>/dev/null | awk -F': ' '/WP-CLI version/{print $2; exit}'), node=$(node -v 2>/dev/null || echo n/a))"
