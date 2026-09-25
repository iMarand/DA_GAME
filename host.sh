#!/usr/bin/env bash
# 100 Hearts — host the game on this server.
#
#   ./host.sh              install / update and start  (http://<server-ip>:2027)
#   ./host.sh status       is it running?
#   ./host.sh logs         live log (without the once-a-second polling noise)
#   ./host.sh restart      restart after you edit files
#   ./host.sh stop         stop the game
#   ./host.sh uninstall    remove the service (your data/ folder is kept)
#
# Options (env vars):  PORT=2027  WORKERS=4
set -euo pipefail

PORT="${PORT:-2027}"
WORKERS="${WORKERS:-4}"
SERVICE="hearts-game"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA_DIR="$APP_DIR/data"
UNIT="/etc/systemd/system/$SERVICE.service"

say()  { printf '\033[1;35m💗 %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠  %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m✖  %s\033[0m\n' "$*" >&2; exit 1; }

SUDO=""
if [ "$(id -u)" -ne 0 ]; then
    command -v sudo >/dev/null || die "Run this as root, or install sudo."
    SUDO="sudo"
fi

# Who runs the game: the person who called the script, or www-data when run as root.
RUN_USER="${SUDO_USER:-$(id -un)}"
if [ "$RUN_USER" = "root" ] && id www-data >/dev/null 2>&1; then
    RUN_USER="www-data"
    # e.g. the game lives in /root/…, which www-data can't read
    if command -v runuser >/dev/null && ! runuser -u www-data -- test -r "$APP_DIR/index.php" 2>/dev/null; then
        RUN_USER="root"
    fi
fi

has_systemd() { command -v systemctl >/dev/null && [ -d /run/systemd/system ]; }
public_ip()   { curl -fsS --max-time 4 https://api.ipify.org 2>/dev/null || hostname -I 2>/dev/null | awk '{print $1}'; }

need_php() {
    if ! command -v php >/dev/null || ! php -m 2>/dev/null | grep -qi '^pdo_sqlite$'; then
        say "Installing PHP with SQLite…"
        if command -v apt-get >/dev/null; then
            $SUDO apt-get update -qq
            $SUDO apt-get install -y -qq php-cli php-sqlite3
        elif command -v dnf >/dev/null; then
            $SUDO dnf install -y -q php-cli php-pdo
        elif command -v yum >/dev/null; then
            $SUDO yum install -y -q php-cli php-pdo
        else
            die "Please install PHP 8+ with the pdo_sqlite extension, then run this again."
        fi
    fi
    php -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);' \
        || die "PHP 8.0+ is needed (found $(php -r 'echo PHP_VERSION;')). On Ubuntu: sudo add-apt-repository ppa:ondrej/php && sudo apt install php8.3-cli php8.3-sqlite3"
    php -m | grep -qi '^pdo_sqlite$' || die "PHP is missing pdo_sqlite (try: apt install php-sqlite3)."
}

prepare_files() {
    [ -f "$APP_DIR/index.php" ] && [ -f "$APP_DIR/router.php" ] || die "Run host.sh from inside the game folder."

    $SUDO mkdir -p "$DATA_DIR"
    $SUDO chown -R "$RUN_USER" "$DATA_DIR"
    $SUDO chmod 775 "$DATA_DIR"

    # Give the login cookie a real secret the first time (logs everyone out once).
    if grep -q "replace-this-with-a-long-random-string" "$APP_DIR/config.php"; then
        local secret
        secret="$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"
        $SUDO sed -i "s/replace-this-with-a-long-random-string/$secret/" "$APP_DIR/config.php"
        say "Generated a random login secret in config.php"
    fi
    if grep -qi "change-me" "$APP_DIR/config.php"; then
        warn "config.php still has a 'change-me' login code — edit it, then run ./host.sh restart"
    fi
}

check_port() {
    if command -v ss >/dev/null && ss -ltn "( sport = :$PORT )" | grep -q LISTEN; then
        if has_systemd && systemctl is-active --quiet "$SERVICE"; then return; fi
        die "Port $PORT is already used by another program. Pick another: PORT=2028 ./host.sh"
    fi
}

open_firewall() {
    if command -v ufw >/dev/null && $SUDO ufw status 2>/dev/null | grep -q "Status: active"; then
        $SUDO ufw allow "$PORT/tcp" >/dev/null && say "Opened port $PORT in ufw"
    elif command -v firewall-cmd >/dev/null && $SUDO firewall-cmd --state >/dev/null 2>&1; then
        $SUDO firewall-cmd --quiet --permanent --add-port="$PORT/tcp" && $SUDO firewall-cmd --quiet --reload
        say "Opened port $PORT in firewalld"
    fi
}

start_systemd() {
    local php_bin
    php_bin="$(command -v php)"
    $SUDO tee "$UNIT" >/dev/null <<EOF
[Unit]
Description=100 Hearts game (port $PORT)
After=network.target

[Service]
User=$RUN_USER
WorkingDirectory=$APP_DIR
Environment=PHP_CLI_SERVER_WORKERS=$WORKERS
ExecStart=$php_bin -S 0.0.0.0:$PORT -t $APP_DIR $APP_DIR/router.php
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
EOF
    $SUDO systemctl daemon-reload
    $SUDO systemctl enable --quiet "$SERVICE"
    $SUDO systemctl restart "$SERVICE"
}

start_nohup() {
    stop_nohup
    PHP_CLI_SERVER_WORKERS="$WORKERS" nohup php -S "0.0.0.0:$PORT" -t "$APP_DIR" "$APP_DIR/router.php" \
        >"$DATA_DIR/server.log" 2>&1 &
    echo $! >"$DATA_DIR/server.pid"
    warn "No systemd here: started in the background, but it won't restart after a reboot."
}

stop_nohup() {
    if [ -f "$DATA_DIR/server.pid" ]; then
        kill "$(cat "$DATA_DIR/server.pid")" 2>/dev/null || true
        rm -f "$DATA_DIR/server.pid"
    fi
}

verify() {
    local code private
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/" || true)"
        [ "$code" = "200" ] && break
        sleep 0.5
    done
    [ "$code" = "200" ] || die "The game did not answer on port $PORT. Check: ./host.sh logs"

    private="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/data/game.sqlite" || true)"
    [ "$private" = "404" ] || die "The database is reachable from the web (HTTP $private) — stopping for safety."
}

install() {
    say "Setting up 100 Hearts on port $PORT…"
    need_php
    prepare_files
    check_port
    if has_systemd; then start_systemd; else start_nohup; fi
    open_firewall
    verify
    echo
    say "The game is live:  http://$(public_ip):$PORT/"
    echo "   Manage it with:   ./host.sh status | logs | restart | stop"
    echo "   If it doesn't open from your phone, also allow TCP port $PORT in your VPS provider's firewall panel."
}

case "${1:-install}" in
    install|start|update) install ;;
    restart)
        if has_systemd && [ -f "$UNIT" ]; then $SUDO systemctl restart "$SERVICE"; else start_nohup; fi
        verify && say "Restarted — http://$(public_ip):$PORT/" ;;
    stop)
        if has_systemd && [ -f "$UNIT" ]; then $SUDO systemctl stop "$SERVICE"; else stop_nohup; fi
        say "Stopped." ;;
    status)
        if has_systemd && [ -f "$UNIT" ]; then systemctl status "$SERVICE" --no-pager -n 5 || true
        elif [ -f "$DATA_DIR/server.pid" ] && kill -0 "$(cat "$DATA_DIR/server.pid")" 2>/dev/null; then say "Running (pid $(cat "$DATA_DIR/server.pid"))"
        else warn "Not running."; fi ;;
    logs)
        if has_systemd && [ -f "$UNIT" ]; then $SUDO journalctl -u "$SERVICE" -n 50 -f | grep --line-buffered -v 'action=poll'
        else tail -n 50 -f "$DATA_DIR/server.log" | grep --line-buffered -v 'action=poll'; fi ;;
    uninstall)
        if has_systemd && [ -f "$UNIT" ]; then
            $SUDO systemctl disable --now --quiet "$SERVICE" || true
            $SUDO rm -f "$UNIT"
            $SUDO systemctl daemon-reload
        fi
        stop_nohup
        say "Removed the service. Your data/ folder (questions, answers, chat) is untouched." ;;
    *) die "Unknown command '$1'. Use: install | status | logs | restart | stop | uninstall" ;;
esac
