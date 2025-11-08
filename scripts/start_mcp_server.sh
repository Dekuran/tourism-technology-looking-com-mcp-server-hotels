#!/usr/bin/env bash
set -Eeuo pipefail

# start_mcp_server.sh - Start Laravel MCP Server with auto-setup and readiness checks

# Colors
if [[ -t 1 ]]; then
  RED="$(printf '\033[31m')"
  GREEN="$(printf '\033[32m')"
  YELLOW="$(printf '\033[33m')"
  BLUE="$(printf '\033[34m')"
  MAGENTA="$(printf '\033[35m')"
  CYAN="$(printf '\033[36m')"
  BOLD="$(printf '\033[1m')"
  RESET="$(printf '\033[0m')"
else
  RED=""; GREEN=""; YELLOW=""; BLUE=""; MAGENTA=""; CYAN=""; BOLD=""; RESET="";
fi

info(){ echo "${CYAN}➤${RESET} $*"; }
success(){ echo "${GREEN}✔${RESET} $*"; }
warn(){ echo "${YELLOW}▲${RESET} $*"; }
error(){ echo "${RED}✖${RESET} $*" >&2; }

PORT="${PORT:-8000}"
RESTART=0

usage() {
  cat <<EOF
Usage: $0 [--port N] [--restart]

Options:
  -p, --port N    Port to run the server on (default: 8000)
      --restart   Stop existing server (if any) and start a new one
  -h, --help      Show this help
EOF
}

# Parse args
while [[ $# -gt 0 ]]; do
  case "$1" in
    -p|--port)
      PORT="$2"; shift 2;;
    --port=*)
      PORT="${1#*=}"; shift;;
    --restart)
      RESTART=1; shift;;
    -h|--help)
      usage; exit 0;;
    *)
      warn "Unknown argument: $1"; shift;;
  esac
done

# Ensure directories
mkdir -p storage/logs database

# Prerequisites
for cmd in php composer curl; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    error "Missing prerequisite: $cmd"
    exit 1
  fi
done

# Composer install if needed
if [[ ! -d vendor ]]; then
  info "Installing PHP dependencies (composer install)..."
  composer install --no-interaction --prefer-dist
  success "Composer install complete."
fi

# Environment setup
if [[ ! -f .env ]]; then
  if [[ -f .env.example ]]; then
    info "Creating .env from .env.example ..."
    cp .env.example .env
  else
    warn ".env.example not found; creating minimal .env"
    cat > .env <<'EOF'
APP_NAME="Tourism MCP Server"
APP_ENV=local
APP_DEBUG=true
APP_KEY=
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
CACHE_DRIVER=file
EOF
  fi
  info "Generating APP_KEY ..."
  if ! grep -q '^APP_KEY=' .env; then
    printf '\nAPP_KEY=\n' >> .env
  fi
  php artisan key:generate
fi

# Ensure APP_KEY
if ! grep -E '^APP_KEY=.+$' .env >/dev/null 2>&1; then
  info "APP_KEY not set or missing; generating ..."
  if ! grep -q '^APP_KEY=' .env; then
    printf '\nAPP_KEY=\n' >> .env
  fi
  php artisan key:generate
fi

# Ensure SQLite database file
if [[ ! -f database/database.sqlite ]]; then
  info "Creating SQLite database at database/database.sqlite ..."
  : > database/database.sqlite
fi

# Run migrations
info "Running database migrations ..."
if ! php artisan migrate --force; then
  warn "Migrations failed or none to run."
fi

# Stop existing server if requested
if [[ -f .mcp_server.pid ]]; then
  PID="$(cat .mcp_server.pid || true)"
  if [[ -n "${PID:-}" ]] && ps -p "$PID" >/dev/null 2>&1; then
    if [[ "$RESTART" -eq 1 ]]; then
      warn "Stopping existing server (PID $PID) ..."
      kill "$PID" || true
      sleep 1
    else
      success "Server already running (PID $PID)."
      echo "URL: http://127.0.0.1:$PORT"
      echo "Endpoints:"
      echo " - Tourism: http://127.0.0.1:$PORT/mcp/tourism"
      echo " - DSAPI:   http://127.0.0.1:$PORT/mcp/dsapi"
      exit 0
    fi
  fi
fi

# Start server
LOG_FILE="storage/logs/serve.log"
info "Starting Laravel server on port $PORT ..."
nohup php artisan serve --host=127.0.0.1 --port="$PORT" > "$LOG_FILE" 2>&1 &
SRV_PID=$!
echo "$SRV_PID" > .mcp_server.pid
info "PID: $SRV_PID (logs: $LOG_FILE)"

# Readiness probe (up to 60s)
ATTEMPTS=60
for i in $(seq 1 "$ATTEMPTS"); do
  HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/" || true)"
  info "Readiness attempt $i/$ATTEMPTS → HTTP ${HTTP_CODE:-N/A}"
  if [[ "$HTTP_CODE" =~ ^[0-9]+$ ]] && [[ "$HTTP_CODE" -ge 200 && "$HTTP_CODE" -lt 500 ]]; then
    success "Server is reachable (HTTP $HTTP_CODE) at http://127.0.0.1:$PORT"
    echo
    echo "Endpoints:"
    echo " - Tourism: http://127.0.0.1:$PORT/mcp/tourism"
    echo " - DSAPI:   http://127.0.0.1:$PORT/mcp/dsapi"
    exit 0
  fi
  sleep 1
  if ! ps -p "$SRV_PID" >/dev/null 2>&1; then
    error "Server process exited unexpectedly; see $LOG_FILE"
    exit 1
  fi
done

error "Server did not become ready within ${ATTEMPTS}s. See $LOG_FILE"
exit 1