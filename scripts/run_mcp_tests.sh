#!/usr/bin/env bash
set -Eeuo pipefail

# Avoid SIGPIPE-caused termination in some terminals/CI when using pipes
# We keep -eEu but turn off pipefail for resilience.
set +o pipefail || true

# run_mcp_tests.sh - Thorough verification and Markdown report for MCP Server
# - Colorful console logs
# - Generates timestamped Markdown report under ./reports/
# - Verifies environment, routes, endpoints, MCP Inspector startup, and PHPUnit tests
# - Auto-starts the server (on port 8000 by default) if not reachable, and stops it after

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
hr(){ echo "${MAGENTA}────────────────────────────────────────────────────────────${RESET}"; }

PORT="${PORT:-8000}"
REPORTS_DIR="reports"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
REPORT_PATH="${REPORTS_DIR}/mcp_report-${TIMESTAMP}.md"
TMP_DIR=".tmp_mcp"
STARTED_SERVER=0

usage() {
  cat <<EOF
Usage: $0 [--port N]

Options:
  -p, --port N    Port to test against (default: 8000)
  -h, --help      Show this help

Environment variables:
  PORT            Port to test against (default: 8000)
EOF
}

# Parse args
while [[ $# -gt 0 ]]; do
  case "$1" in
    -p|--port)
      PORT="$2"; shift 2;;
    --port=*)
      PORT="${1#*=}"; shift;;
    -h|--help)
      usage; exit 0;;
    *)
      warn "Unknown argument: $1"; shift;;
  esac
done

# Ensure directories
mkdir -p "$REPORTS_DIR" "$TMP_DIR"

# Helper: append Markdown
add_md() { printf "%s\n" "$*" >> "$REPORT_PATH"; }
add_md_code() { local lang="$1"; shift; printf '```%s\n%s\n```\n' "$lang" "$*" >> "$REPORT_PATH"; }

# Start report
: > "$REPORT_PATH"
add_md "# MCP Server Verification Report"
add_md
add_md "- Generated: $(date -Iseconds)"
add_md "- Host: $(hostname)"
add_md "- Port: ${PORT}"
add_md

hr
info "Collecting environment information..."
PHP_VER="$(php -v 2>/dev/null | head -n1 || true)"
COMPOSER_VER="$(composer --version 2>/dev/null || true)"
LARAVEL_VER="$(php artisan --version 2>/dev/null || true)"
OS_UNAME="$(uname -a 2>/dev/null || true)"

add_md "## Environment"
add_md
add_md "- PHP: ${PHP_VER}"
add_md "- Composer: ${COMPOSER_VER}"
add_md "- Laravel/Artisan: ${LARAVEL_VER}"
add_md "- OS: ${OS_UNAME}"
add_md

# Basic prerequisites
hr
info "Checking prerequisites (php, composer, curl)..."
for cmd in php composer curl; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    error "Missing prerequisite: $cmd"
    add_md "❌ Missing prerequisite: $cmd"
    exit 1
  fi
done
success "All prerequisites present."
add_md "✅ Prerequisites present: php, composer, curl"
add_md

# Ensure dependencies for tests
if [[ ! -d vendor ]]; then
  hr
  info "Installing PHP dependencies (composer install) ..."
  add_md "### Composer Install"
  add_md
  add_md "_vendor directory not found; running composer install._"
  {
    composer install --no-interaction --prefer-dist
  } &> "${TMP_DIR}/composer_install.txt" || true
  success "Composer install completed (see logs)."
  add_md_code "text" "$(tail -n 100 "${TMP_DIR}/composer_install.txt" || true)"
  add_md
fi

# Ensure .env and APP_KEY (so artisan/test won't choke)
if [[ ! -f .env ]]; then
  hr
  info "Setting up environment (.env + APP_KEY) ..."
  if [[ -f .env.example ]]; then
    cp .env.example .env
  else
    cat > .env <<'EOF'
APP_NAME="CapCorn MCP Server"
APP_ENV=local
APP_DEBUG=true
APP_KEY=
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
CACHE_DRIVER=file
EOF
  fi
  php artisan key:generate || true
  success "Environment initialized."
  add_md "### Environment Setup"
  add_md
  add_md "- .env created and APP_KEY generated"
  add_md
fi

# Ensure SQLite DB exists so migrations/tests can run
mkdir -p database
if [[ ! -f database/database.sqlite ]]; then
  : > database/database.sqlite
  add_md "- Created database/database.sqlite"
fi

# Try to hit the server root; start if not reachable
BASE_URL="http://127.0.0.1:${PORT}"
ROOT_STATUS="$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}/" || true)"
if [[ ! "$ROOT_STATUS" =~ ^[0-9]+$ ]] || [[ "$ROOT_STATUS" -ge 500 || "$ROOT_STATUS" -eq 000 ]]; then
  hr
  info "Server not reachable (HTTP ${ROOT_STATUS:-N/A}). Attempting to start it..."
  add_md "## Server Startup"
  add_md
  add_md "_Auto-starting server because it was not reachable on port ${PORT}._"
  # Ensure migrations before start (safe if none)
  php artisan migrate --force >/dev/null 2>&1 || true
  if bash scripts/start_mcp_server.sh --port "$PORT"; then
    STARTED_SERVER=1
    success "Server started on ${BASE_URL}"
    add_md "- Server started on ${BASE_URL}"
  else
    error "Failed to start server. See storage/logs/serve.log"
    add_md "❌ Failed to start server. See storage/logs/serve.log"
    exit 1
  fi
else
  success "Server is reachable (HTTP $ROOT_STATUS) at ${BASE_URL}"
  add_md "## Server Availability"
  add_md
  add_md "- Server reachable (HTTP $ROOT_STATUS) at ${BASE_URL}"
fi
add_md

# Verify MCP routes in Laravel
hr
info "Verifying MCP routes (route:list)..."
ROUTES_TXT="$(php artisan route:list 2>&1 || true)"
MCP_ROUTES="$(php artisan route:list 2>&1 | grep 'mcp/' || true)"
add_md "## MCP Routes"
add_md
if [[ -n "$MCP_ROUTES" ]]; then
  add_md "_Found MCP routes in route:list output._"
  add_md_code "text" "$MCP_ROUTES"
  success "MCP routes detected."
else
  add_md "❌ No MCP routes found in route:list output."
  add_md_code "text" "$ROUTES_TXT"
  warn "No /mcp routes found; including full route:list in report."
fi
add_md

# Endpoint tests
test_endpoint() {
  local path="$1"
  local url="${BASE_URL}${path}"
  local status body snippet
  status="$(curl -sS -m 10 -o /dev/null -w '%{http_code}' "$url" || true)"
  body="$(curl -sS -m 10 "$url" || true)"
  snippet="$(printf "%s" "$body" | head -c 1000)"
  add_md "### GET ${path}"
  add_md
  add_md "- URL: ${url}"
  add_md "- HTTP Status: ${status}"
  add_md
  add_md "**Response (first 1000 bytes):**"
  add_md_code "" "$snippet"
  add_md
  if [[ "$status" =~ ^2[0-9][0-9]$ || "$status" =~ ^3[0-9][0-9]$ ]]; then
    success "Endpoint ${path} responded with HTTP ${status}"
  else
    warn "Endpoint ${path} responded with HTTP ${status}"
  fi
}

hr
info "Testing HTTP endpoints..."
add_md "## Endpoint Checks"
add_md
test_endpoint "/"
test_endpoint "/mcp/capcorn"

# MCP Inspector snippet
hr
info "Probing MCP Inspector (php artisan mcp:inspector mcp/capcorn) ..."
add_md "## MCP Inspector Output (snippet)"
add_md
INSPECT_OUT="${TMP_DIR}/inspector.txt"
# Run inspector briefly and capture output, then terminate
set +e
php artisan mcp:inspector mcp/capcorn > "$INSPECT_OUT" 2>&1 &
INSPECT_PID=$!
sleep 3
if ps -p "$INSPECT_PID" >/dev/null 2>&1; then
  kill "$INSPECT_PID" >/dev/null 2>&1 || true
fi
set -e
if [[ -s "$INSPECT_OUT" ]]; then
  add_md "_Captured initial inspector output (first 120 lines):_"
  add_md_code "text" "$(head -n 120 "$INSPECT_OUT")"
  success "Inspector output captured."
else
  add_md "⚠ Inspector produced no output."
  warn "Inspector produced no output."
fi
add_md

# AI Agent View — server instructions and tools (via reflection)
hr
info "Extracting CapCorn server instructions and tools (AI Agent View)..."
add_md "## AI Agent View"
add_md
SERVER_META_PHP="${TMP_DIR}/server_meta.php"
cat > "$SERVER_META_PHP" <<'PHP'
<?php
// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fqcn = \App\Mcp\CapCornServer\CapCornServer::class;
$server = app($fqcn);

$prop = static function(object $obj, string $name) {
    $rp = new ReflectionProperty($obj, $name);
    $rp->setAccessible(true);
    return $rp->getValue($obj);
};

$instructions = $prop($server, 'instructions');
$toolClasses = $prop($server, 'tools') ?? [];

$tools = [];
foreach ($toolClasses as $toolClass) {
    $desc = null;
    try {
        $trc = new ReflectionClass($toolClass);
        if ($trc->hasProperty('description')) {
            $rp = $trc->getProperty('description');
            $rp->setAccessible(true);
            // Try instantiate without constructor to access default description
            $inst = $trc->newInstanceWithoutConstructor();
            $desc = $rp->getValue($inst);
        }
    } catch (\Throwable $e) {
        $desc = null;
    }
    $tools[] = [
        'class' => $toolClass,
        'description' => $desc,
    ];
}

echo json_encode([
    'server' => $fqcn,
    'instructions' => $instructions,
    'tools' => $tools,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
PHP

META_JSON="${TMP_DIR}/server_meta.json"
php "$SERVER_META_PHP" > "$META_JSON" 2>/dev/null || true
if [[ -s "$META_JSON" ]]; then
  if command -v jq >/dev/null 2>&1; then
    INSTRUCTIONS="$(jq -r '.instructions' "$META_JSON" 2>/dev/null || cat "$META_JSON")"
  else
    INSTRUCTIONS="$(cat "$META_JSON")"
  fi
  add_md "### Server Instructions"
  if command -v head >/dev/null 2>&1; then
    printf "%s" "$INSTRUCTIONS" | head -n 400 > "${TMP_DIR}/instructions.txt"
    add_md_code "markdown" "$(cat "${TMP_DIR}/instructions.txt")"
  else
    add_md_code "markdown" "$INSTRUCTIONS"
  fi
  add_md
  add_md "### Registered Tools"
  if command -v jq >/dev/null 2>&1; then
    TOOL_COUNT="$(jq '.tools | length' "$META_JSON" 2>/dev/null || echo 0)"
    add_md "- Count: ${TOOL_COUNT}"
    add_md
    for i in $(seq 0 $((TOOL_COUNT-1))); do
      CLS="$(jq -r ".tools[$i].class" "$META_JSON")"
      DESC="$(jq -r ".tools[$i].description" "$META_JSON")"
      add_md "- ${CLS}"
      if [[ -n "$DESC" && "$DESC" != "null" ]]; then
        add_md_code "markdown" "$DESC"
      fi
      add_md
    done
  else
    add_md "_jq not available; dumping raw JSON:_"
    add_md_code "json" "$(cat "$META_JSON")"
  fi
else
  add_md "⚠ Unable to extract server metadata."
fi
add_md

# Optional upstream CapCorn API smoke check (if CAPCORN_BASE_URL provided)
if [[ -n "${CAPCORN_BASE_URL:-}" ]]; then
  hr
  info "Performing optional upstream CapCorn API smoke check (rooms search)..."
  add_md "## Upstream CapCorn API Smoke Check"
  add_md
  SEARCH_JSON="$(cat <<'JSON'
{
  "language": "de",
  "timespan": { "from": "2025-11-20", "to": "2025-11-25" },
  "duration": 2,
  "adults": 2
}
JSON
)"
  SMOKE_OUT="${TMP_DIR}/capcorn_search.json"
  set +e
  HTTP_STATUS="$(curl -sS -m 20 -o "$SMOKE_OUT" -w '%{http_code}' -H 'Content-Type: application/json' -H 'Accept: application/json' -d "$SEARCH_JSON" "${CAPCORN_BASE_URL}/api/v1/rooms/search" || true)"
  set -e
  add_md "- CAPCORN_BASE_URL: ${CAPCORN_BASE_URL}"
  add_md "- HTTP Status: ${HTTP_STATUS}"
  add_md "**Response (first 120 lines):**"
  add_md_code "json" "$(head -n 120 "$SMOKE_OUT" || true)"
else
  add_md "## Upstream CapCorn API Smoke Check"
  add_md
  add_md "_Skipped — set CAPCORN_BASE_URL to enable upstream smoke checks._"
fi
add_md

# Run tests
hr
info "Running PHPUnit tests (php artisan test) ..."
add_md "## PHPUnit Tests"
add_md
TEST_OUT="${TMP_DIR}/phpunit.txt"
set +e
php artisan config:clear --ansi >/dev/null 2>&1 || true
# Write full PHPUnit output to file to avoid SIGPIPE issues with tee
php artisan test > "$TEST_OUT" 2>&1
TEST_EXIT=$?
set -e

add_md "_Tail of test output:_"
add_md_code "text" "$(tail -n 200 "$TEST_OUT" || true)"
if [[ "$TEST_EXIT" -eq 0 ]]; then
  add_md
  add_md "✅ Tests passed (exit code 0)."
  success "Tests passed."
else
  add_md
  add_md "❌ Tests failed (exit code ${TEST_EXIT})."
  error "Tests failed."
fi
add_md

# Summary
add_md "## Summary"
add_md
if [[ "$TEST_EXIT" -eq 0 ]]; then
  add_md "- Overall Result: ✅ PASS"
else
  add_md "- Overall Result: ❌ FAIL"
fi
add_md "- Report file: ${REPORT_PATH}"
add_md "- Base URL: ${BASE_URL}"
add_md "- Endpoints:"
add_md "  - CapCorn: ${BASE_URL}/mcp/capcorn"
add_md

# Stop server if we started it here
if [[ "$STARTED_SERVER" -eq 1 ]]; then
  hr
  info "Stopping server started by this script..."
  if [[ -f .mcp_server.pid ]]; then
    PID="$(cat .mcp_server.pid || true)"
    if [[ -n "${PID:-}" ]]; then
      kill "$PID" >/dev/null 2>&1 || true
      rm -f .mcp_server.pid
      success "Server (PID $PID) stopped."
    fi
  else
    warn "PID file not found; server stop skipped."
  fi
fi

hr
if [[ "$TEST_EXIT" -eq 0 ]]; then
  success "Verification complete. Markdown report: ${REPORT_PATH}"
else
  error "Verification complete with failures. See report: ${REPORT_PATH}"
fi

# Exit with test status so CI can fail appropriately
exit "$TEST_EXIT"