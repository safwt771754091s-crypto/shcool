#!/usr/bin/env bash
# Rebuild the demo environment from scratch after a sandbox reset.
# Installs PHP/MariaDB/Redis, creates the DB/user, seeds, then runs the demo.
# Safe to re-run: every step is idempotent.
set -uo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG=/tmp/bootstrap_env.log
exec >>"$LOG" 2>&1
echo "===== bootstrap $(date -Is) ====="

PHP_BIN=/usr/bin/php8.4

# 1. Packages ---------------------------------------------------------------
if [ ! -x "$PHP_BIN" ]; then
  echo ">> installing packages"
  sudo apt-get update -qq
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath \
    php8.4-gd php8.4-intl php8.4-mysql php8.4-redis \
    mariadb-server redis-server unzip curl
fi
sudo ln -sf "$PHP_BIN" /usr/local/bin/php

# 2. Services ---------------------------------------------------------------
sudo mkdir -p /var/lib/mysql /var/run/mysqld
sudo chown -R mysql:mysql /var/run/mysqld 2>/dev/null || true
if [ ! -d /var/lib/mysql/mysql ]; then
  echo ">> initialising mysql data dir"
  sudo mariadb-install-db --user=mysql --datadir=/var/lib/mysql
fi
sudo service mariadb start || true
sudo service redis-server start || true

# 3. Database + user --------------------------------------------------------
echo ">> ensuring database + user"
sudo mariadb -e "CREATE DATABASE IF NOT EXISTS school_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'school'@'127.0.0.1' IDENTIFIED BY 'school_secret';
GRANT ALL PRIVILEGES ON school_platform.* TO 'school'@'127.0.0.1';
FLUSH PRIVILEGES;"

# 4. Seed if empty ----------------------------------------------------------
if [ "${FRESH:-0}" = "1" ]; then
  echo ">> migrate:fresh --seed"
  (cd "$ROOT/backend" && php artisan migrate:fresh --seed --force)
fi

# 5. Run the demo (API + Flutter web) --------------------------------------
echo ">> starting demo"
bash "$ROOT/scripts/run_demo.sh"
echo "===== bootstrap done $(date -Is) ====="
