#!/usr/bin/env bash
# Idempotent MariaDB start for Cursor Cloud agents (used by environment.json "start").
set -euo pipefail

ensure_php_mysql_socket() {
  # In some agent images /var/run is not a symlink to /run, so PHP's default
  # mysqli socket (/var/run/mysqld/mysqld.sock) misses the real MariaDB socket
  # under /run/mysqld/mysqld.sock. Bridge them when needed.
  local php_sock run_sock
  php_sock="$(php -r 'echo ini_get("mysqli.default_socket") ?: "/var/run/mysqld/mysqld.sock";' 2>/dev/null || true)"
  php_sock="${php_sock:-/var/run/mysqld/mysqld.sock}"
  run_sock="/run/mysqld/mysqld.sock"

  if [ -S "$run_sock" ] && [ ! -e "$php_sock" ]; then
    sudo mkdir -p "$(dirname "$php_sock")"
    sudo ln -sfn "$run_sock" "$php_sock"
  elif [ -S "$run_sock" ] && [ -d "$php_sock" ]; then
    # Empty directory mistakenly created at the socket path — replace with symlink.
    sudo rmdir "$php_sock" 2>/dev/null || true
    sudo ln -sfn "$run_sock" "$php_sock"
  elif [ -S "$run_sock" ] && [ ! -S "$php_sock" ]; then
    sudo rm -f "$php_sock"
    sudo ln -sfn "$run_sock" "$php_sock"
  fi
}

if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
  ensure_php_mysql_socket
  echo "MariaDB already running"
  exit 0
fi

# Prefer /run/mysqld (actual runtime). Do not pre-create an empty /var/run/mysqld
# directory — that breaks PHP's default socket path when /var/run != /run.
sudo mkdir -p /var/lib/mysql /run/mysqld
sudo chown -R mysql:mysql /var/lib/mysql /run/mysqld

if [ ! -d /var/lib/mysql/mysql ]; then
  echo "Initializing MariaDB datadir..."
  sudo mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi

# Prefer systemd/service when available; fall back to mysqld_safe (no systemd in many agent VMs).
if command -v service >/dev/null 2>&1 && sudo service mariadb start >/dev/null 2>&1; then
  :
elif command -v mysqld_safe >/dev/null 2>&1; then
  sudo mysqld_safe --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock >/tmp/mariadb-safe.log 2>&1 &
elif command -v mariadbd-safe >/dev/null 2>&1; then
  sudo mariadbd-safe --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock >/tmp/mariadb-safe.log 2>&1 &
else
  echo "ERROR: no MariaDB start mechanism found" >&2
  exit 1
fi

for i in $(seq 1 30); do
  if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
    ensure_php_mysql_socket
    echo "MariaDB ready"
    exit 0
  fi
  sleep 1
done

echo "ERROR: MariaDB failed to become ready" >&2
tail -n 50 /tmp/mariadb-safe.log 2>/dev/null || true
exit 1
