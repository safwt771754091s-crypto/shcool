#!/usr/bin/env bash
# Keep the demo alive: restart MariaDB/Redis/API/web if they stop.
# Run detached:  setsid bash scripts/watchdog.sh >/tmp/school_watchdog.log 2>&1 &
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"; MOBILE="$ROOT/mobile"
API_PORT="${API_PORT:-12000}"; WEB_PORT="${WEB_PORT:-12001}"
SUDO=""
[ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1 && SUDO="sudo"

while true; do
  $SUDO service mariadb status >/dev/null 2>&1 || { echo "$(date -Is) restart mariadb"; sudo service mariadb start >/dev/null 2>&1; }
  redis-cli ping >/dev/null 2>&1 || { echo "$(date -Is) restart redis"; sudo service redis-server start >/dev/null 2>&1; }
  curl -s -o /dev/null "http://127.0.0.1:$API_PORT/api/v1/auth/login" \
    || { echo "$(date -Is) restart api"; (cd "$BACKEND" && setsid php artisan serve --host=0.0.0.0 --port="$API_PORT" >/tmp/school_api.log 2>&1 &); }
  curl -s -o /dev/null "http://127.0.0.1:$WEB_PORT/" \
    || { echo "$(date -Is) restart web"; (setsid python3 "$MOBILE/tool/serve_web.py" "$MOBILE/build/web" "$WEB_PORT" >/tmp/school_web.log 2>&1 &); }
  sleep 30
done
