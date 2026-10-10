#!/usr/bin/env bash
# Keep the demo alive.
#
# Two failure modes are handled:
#   1. A service died but the toolchain is intact -> restart just that service.
#   2. The whole sandbox was reset (PHP / MariaDB / Redis gone) -> re-run
#      bootstrap_env.sh to reinstall, reseed and relaunch everything.
#
# Run detached:
#   setsid bash scripts/watchdog.sh >/tmp/school_watchdog.log 2>&1 &
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"; MOBILE="$ROOT/mobile"
API_PORT="${API_PORT:-12000}"; WEB_PORT="${WEB_PORT:-12001}"
SUDO=""
[ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1 && SUDO="sudo"

log() { echo "$(date -Is) $*"; }

# Full reset: the toolchain itself is gone. Ignore transient states while a
# package install is in progress (dpkg briefly renames init scripts).
full_reset() {
  if pgrep -x apt-get >/dev/null 2>&1 || pgrep -x dpkg >/dev/null 2>&1; then
    return 1
  fi
  [ ! -x /usr/bin/php8.4 ] || [ ! -x /usr/sbin/mariadbd ]
}

while true; do
  if full_reset; then
    log "toolchain missing -> running bootstrap_env.sh"
    FRESH=1 bash "$ROOT/scripts/bootstrap_env.sh" || true
    sleep 60
    continue
  fi

  $SUDO service mariadb status >/dev/null 2>&1 \
    || { log "restart mariadb"; $SUDO service mariadb start >/dev/null 2>&1; }
  redis-cli ping >/dev/null 2>&1 \
    || { log "restart redis"; $SUDO service redis-server start >/dev/null 2>&1; }

  curl -s -m 5 -o /dev/null "http://127.0.0.1:$API_PORT/api/v1/auth/login" \
    || { log "restart api"; (cd "$BACKEND" && setsid php artisan serve --host=0.0.0.0 --port="$API_PORT" >/tmp/school_api.log 2>&1 &); }
  curl -s -m 5 -o /dev/null "http://127.0.0.1:$WEB_PORT/" \
    || { log "restart web"; (setsid python3 "$MOBILE/tool/serve_web.py" "$MOBILE/build/web" "$WEB_PORT" >/tmp/school_web.log 2>&1 &); }

  sleep 30
done
