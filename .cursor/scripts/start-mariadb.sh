#!/usr/bin/env bash
# Idempotent MariaDB start for Cursor Cloud agents (used by environment.json "start").
set -euo pipefail

if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
  echo "MariaDB already running"
  exit 0
fi

sudo mkdir -p /var/lib/mysql /var/run/mysqld
sudo chown -R mysql:mysql /var/lib/mysql /var/run/mysqld

if [ ! -d /var/lib/mysql/mysql ]; then
  echo "Initializing MariaDB datadir..."
  sudo mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi

# Prefer systemd/service when available; fall back to mysqld_safe (no systemd in many agent VMs).
if command -v service >/dev/null 2>&1 && sudo service mariadb start >/dev/null 2>&1; then
  :
elif command -v mysqld_safe >/dev/null 2>&1; then
  sudo mysqld_safe --datadir=/var/lib/mysql >/tmp/mariadb-safe.log 2>&1 &
elif command -v mariadbd-safe >/dev/null 2>&1; then
  sudo mariadbd-safe --datadir=/var/lib/mysql >/tmp/mariadb-safe.log 2>&1 &
else
  echo "ERROR: no MariaDB start mechanism found" >&2
  exit 1
fi

for i in $(seq 1 30); do
  if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
    echo "MariaDB ready"
    exit 0
  fi
  sleep 1
done

echo "ERROR: MariaDB failed to become ready" >&2
tail -n 50 /tmp/mariadb-safe.log 2>/dev/null || true
exit 1
