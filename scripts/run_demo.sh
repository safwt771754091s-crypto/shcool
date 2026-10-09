#!/usr/bin/env bash
#
# Start the School Digital Platform for a local/CI demo:
#   * Laravel API  -> port 12000  (public: https://work-1-... )
#   * Flutter web  -> port 12001  (public: https://work-2-... )
#
# Idempotent: safe to re-run. Logs go to /tmp/school_*.log.
set -u

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"
SUDO=""
[ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1 && SUDO="sudo"

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$REPO_DIR/backend"
MOBILE="$REPO_DIR/mobile"
FLUTTER_BIN="${FLUTTER_BIN:-/workspace/tools/flutter/bin}"
API_PORT="${API_PORT:-12000}"
WEB_PORT="${WEB_PORT:-12001}"
API_PUBLIC="${API_PUBLIC:-https://work-1-qgkkdtekkcsslwlv.prod-runtime.all-hands.dev}"

echo "==> Ensuring services"
$SUDO service mariadb status >/dev/null 2>&1 || $SUDO service mariadb start >/dev/null 2>&1
redis-cli ping >/dev/null 2>&1 || $SUDO service redis-server start >/dev/null 2>&1

echo "==> Ensuring database + user"
$SUDO mariadb -e "CREATE DATABASE IF NOT EXISTS school_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'school'@'127.0.0.1' IDENTIFIED BY 'school_secret';
GRANT ALL PRIVILEGES ON school_platform.* TO 'school'@'127.0.0.1'; FLUSH PRIVILEGES;" 2>/dev/null

echo "==> Seeding (fresh) if requested"
if [ "${FRESH:-0}" = "1" ]; then
  (cd "$BACKEND" && php artisan migrate:fresh --seed --force)
fi

echo "==> Starting API on :$API_PORT"
pkill -f "artisan serve --host=0.0.0.0 --port=$API_PORT" 2>/dev/null
(cd "$BACKEND" && setsid php artisan serve --host=0.0.0.0 --port="$API_PORT" > /tmp/school_api.log 2>&1 &)

echo "==> Building Flutter web (API_BASE_URL=$API_PUBLIC/api/v1)"
export PATH="$FLUTTER_BIN:$PATH"
(cd "$MOBILE" && flutter pub get >/dev/null && \
  flutter build web --release --dart-define=API_BASE_URL="$API_PUBLIC/api/v1") || exit 1

echo "==> Starting web on :$WEB_PORT"
pkill -f "serve_web.py" 2>/dev/null
(setsid python3 "$MOBILE/tool/serve_web.py" "$MOBILE/build/web" "$WEB_PORT" > /tmp/school_web.log 2>&1 &)

sleep 3
curl -s -o /dev/null -w "API  :%{http_code}\n" "http://127.0.0.1:$API_PORT/api/v1/auth/login" \
  -X POST -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"email":"x","password":"y"}'
curl -s -o /dev/null -w "WEB  :%{http_code}\n" "http://127.0.0.1:$WEB_PORT/"

echo
echo "Open:  $API_PUBLIC  (swap host for the web host if API_PUBLIC differs)"
echo "Demo logins (password: password):"
echo "  owner@school-platform.local    (owner / ministry level)"
echo "  minister@school-platform.local (وزير التربية — read-only monitoring)"
echo "  manager@school-platform.local  (school manager)"
echo "  teacher1@school-platform.local (teacher)"
echo "  parent@school-platform.local   (guardian)"
echo "  student@school-platform.local  (student)"
