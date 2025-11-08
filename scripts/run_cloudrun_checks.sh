#!/usr/bin/env bash
set -Eeuo pipefail
# Cloud Run verification script for remote CapCorn MCP Server
# - Does NOT start a local server
# - Probes a remote Cloud Run URL for / and /mcp/capcorn
# - Optionally runs MCP Inspector via npx against the remote URL (best-effort)
# - Optionally performs upstream CapCorn API smoke checks if CAPCORN_BASE_URL is set
#
# Usage:
#   bash scripts/run_cloudrun_checks.sh --url https://SERVICE-URL.run.app
# Env:
#   CLOUDRUN_URL                Remote base URL (e.g. https://mcp-hotel-server-*.run.app)
#   CAPCORN_BASE_URL            Optional upstream API base (e.g. https://lookingcom-backend.vercel.app)
#   CAPCORN_HOTEL_ID            Optional hotel id for availability smoke check (default HOTEL-EXAMPLE/9100)
#   CAPCORN_SMOKE_*             Optional overrides for search/availability examples (see below)

# Colors
if [[ -t 1 ]]; then
  RED="$(printf '\033[31m')"; GREEN="$(printf '\033[32m')"; YELLOW="$(printf '\033[33m')"
  BLUE="$(printf '\033[34m')"; MAGENTA="$(printf '\033[35m')"; CYAN="$(printf '\033[36m')"
  BOLD="$(printf '\033[1m')"; RESET="$(printf '\033[0m')"
else
  RED=""; GREEN=""; YELLOW=""; BLUE=""; MAGENTA=""; CYAN=""; BOLD=""; RESET=""
fi

info(){ echo "${CYAN}➤${RESET} $*"; }
success(){ echo "${GREEN}✔${RESET} $*"; }
warn(){ echo "${YELLOW}▲${RESET} $*"; }
error(){ echo "${RED}✖${RESET} $*" >&2; }
hr(){ echo "${MAGENTA}────────────────────────────────────────────────────────────${RESET}"; }

REPORTS_DIR="reports"
TMP_DIR=".tmp_mcp_cloudrun"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
REPORT_PATH="${REPORTS_DIR}/mcp_cloudrun_report-${TIMESTAMP}.md"

usage() {
  cat <<EOF
Usage: $0 --url https://SERVICE-URL.run.app

Options:
  -u, --url URL    Cloud Run base URL (required unless CLOUDRUN_URL env is set)
  -h, --help       Show help

Environment variables:
  CLOUDRUN_URL                 Base URL (e.g. https://mcp-hotel-server-*.run.app)
  CAPCORN_BASE_URL             Optional upstream API base (e.g. https://lookingcom-backend.vercel.app)
  CAPCORN_HOTEL_ID             Optional hotel id for availability smoke (default 9100)
  CAPCORN_SMOKE_TIMESPAN_FROM  Default 2025-11-20
  CAPCORN_SMOKE_TIMESPAN_TO    Default 2025-11-25
  CAPCORN_SMOKE_DURATION       Default 2
  CAPCORN_SMOKE_ADULTS         Default 2
  CAPCORN_SMOKE_LANG           Default 0
  CAPCORN_SMOKE_ARRIVAL        Default 2025-11-21
  CAPCORN_SMOKE_DEPARTURE      Default 2025-11-23
  CAPCORN_SMOKE_ROOM1_ADULTS   Default 2
EOF
}

# Parse args
CLOUDRUN_URL="${CLOUDRUN_URL:-}"
while [[ $# -gt 0 ]]; do
  case "$1" in
    -u|--url) CLOUDRUN_URL="$2"; shift 2;;
    --url=*) CLOUDRUN_URL="${1#*=}"; shift;;
    -h|--help) usage; exit 0;;
    *) warn "Unknown argument: $1"; shift;;
  esac
done

if [[ -z "${CLOUDRUN_URL}" ]]; then
  error "Missing --url or CLOUDRUN_URL. Example: --url https://mcp-hotel-server-XXXX.run.app"
  exit 2
fi

mkdir -p "$REPORTS_DIR" "$TMP_DIR"
: > "$REPORT_PATH"

add_md() { printf "%s\n" "$*" >> "$REPORT_PATH"; }
add_md_code() { local lang="$1"; shift; printf '```%s\n%s\n```\n' "$lang" "$*" >> "$REPORT_PATH"; }

# Start report
add_md "# MCP Cloud Run Verification Report"
add_md
add_md "- Generated: $(date -Iseconds)"
add_md "- Service URL: ${CLOUDRUN_URL}"
add_md

# Prereqs
hr
info "Checking prerequisites (curl, optional npx)..."
missing=0
for cmd in curl; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    error "Missing prerequisite: $cmd"
    missing=1
  fi
done
if [[ "$missing" -eq 1 ]]; then
  add_md "❌ Missing prerequisite(s). Install curl and retry."
  exit 1
fi
success "curl present."
if command -v npx >/dev/null 2>&1; then success "npx present (inspector optional)."; else warn "npx not found; inspector step will be skipped."; fi
add_md "✅ Prerequisites: curl present; npx optional"

# Endpoint tests against Cloud Run
test_endpoint() {
  local path="$1"
  local url="${CLOUDRUN_URL}${path}"
  local status body snippet
  status="$(curl -sSLm 20 -o /dev/null -w '%{http_code}' "$url" || true)"
  body="$(curl -sSLm 20 "$url" || true)"
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
info "Testing Cloud Run HTTP endpoints..."
add_md "## Endpoint Checks (Cloud Run)"
add_md
test_endpoint "/"
test_endpoint "/mcp/capcorn"

# Optional: MCP Inspector (remote) via npx (best-effort)
hr
info "Attempting MCP Inspector against remote (best-effort, non-fatal)..."
add_md "## MCP Inspector Output (remote; best-effort)"
add_md
INSPECT_OUT="${TMP_DIR}/inspector_remote.txt"
if command -v npx >/dev/null 2>&1; then
  set +e
  # Launch inspector briefly; capture initial output and terminate
  npx -y @modelcontextprotocol/inspector@latest server --url "${CLOUDRUN_URL}/mcp/capcorn" > "$INSPECT_OUT" 2>&1 &
  INSPECT_PID=$!
  sleep 4
  if ps -p "$INSPECT_PID" >/dev/null 2>&1; then
    kill "$INSPECT_PID" >/dev/null 2>&1 || true
  fi
  set -e
  if [[ -s "$INSPECT_OUT" ]]; then
    add_md "_Captured initial inspector output (first 120 lines):_"
    add_md_code "text" "$(head -n 120 "$INSPECT_OUT")"
    success "Inspector output captured."
  else
    add_md "⚠ Inspector produced no output (this is non-fatal)."
    warn "Inspector produced no output."
  fi
else
  add_md "_npx not available; skipping remote inspector._"
fi
add_md

# Optional upstream CapCorn API smoke checks (against upstream, not Cloud Run)
if [[ -n "${CAPCORN_BASE_URL:-}" ]]; then
  hr
  info "Performing upstream CapCorn API smoke checks (SearchRooms, Availability)..."
  add_md "## Upstream CapCorn API Smoke Checks"
  add_md
  add_md "- CAPCORN_BASE_URL: ${CAPCORN_BASE_URL}"

  # SearchRoomsTool smoke (rooms/search)
  add_md "### SearchRoomsTool (rooms/search)"
  SEARCH_JSON="$(cat <<JSON
{
  "language": "de",
  "timespan": { "from": "${CAPCORN_SMOKE_TIMESPAN_FROM:-2025-11-20}", "to": "${CAPCORN_SMOKE_TIMESPAN_TO:-2025-11-25}" },
  "duration": ${CAPCORN_SMOKE_DURATION:-2},
  "adults": ${CAPCORN_SMOKE_ADULTS:-2}
}
JSON
)"
  SMOKE_OUT_SEARCH="${TMP_DIR}/capcorn_search.json"
  set +e
  HTTP_STATUS_SEARCH="$(curl -sS -m 25 -o "$SMOKE_OUT_SEARCH" -w '%{http_code}' -H 'Content-Type: application/json' -H 'Accept: application/json' -d "$SEARCH_JSON" "${CAPCORN_BASE_URL}/api/v1/rooms/search" || true)"
  set -e
  add_md "- HTTP Status: ${HTTP_STATUS_SEARCH}"
  add_md "**Response (first 120 lines):**"
  add_md_code "json" "$(head -n 120 "$SMOKE_OUT_SEARCH" || true)"
  add_md

  # SearchRoomAvailabilityTool smoke (rooms/availability)
  add_md "### SearchRoomAvailabilityTool (rooms/availability)"
  AVAIL_JSON="$(cat <<JSON
{
  "language": ${CAPCORN_SMOKE_LANG:-0},
  "hotel_id": "${CAPCORN_HOTEL_ID:-9100}",
  "arrival": "${CAPCORN_SMOKE_ARRIVAL:-2025-11-21}",
  "departure": "${CAPCORN_SMOKE_DEPARTURE:-2025-11-23}",
  "rooms": [
    { "adults": ${CAPCORN_SMOKE_ROOM1_ADULTS:-2} }
  ]
}
JSON
)"
  SMOKE_OUT_AVAIL="${TMP_DIR}/capcorn_availability.json"
  set +e
  HTTP_STATUS_AVAIL="$(curl -sS -m 25 -o "$SMOKE_OUT_AVAIL" -w '%{http_code}' -H 'Content-Type: application/json' -H 'Accept: application/json' -d "$AVAIL_JSON" "${CAPCORN_BASE_URL}/api/v1/rooms/availability" || true)"
  set -e
  add_md "- HTTP Status: ${HTTP_STATUS_AVAIL}"
  add_md "**Response (first 120 lines):**"
  add_md_code "json" "$(head -n 120 "$SMOKE_OUT_AVAIL" || true)"
else
  add_md "## Upstream CapCorn API Smoke Checks"
  add_md
  add_md "_Skipped — set CAPCORN_BASE_URL to enable upstream smoke checks._"
fi
add_md

# Summary
add_md "## Summary"
add_md
add_md "- Service URL: ${CLOUDRUN_URL}"
add_md "- Endpoints:"
add_md "  - Root: ${CLOUDRUN_URL}/"
add_md "  - CapCorn: ${CLOUDRUN_URL}/mcp/capcorn"
add_md
hr
success "Cloud Run verification complete. Markdown report: ${REPORT_PATH}"
echo "$REPORT_PATH"