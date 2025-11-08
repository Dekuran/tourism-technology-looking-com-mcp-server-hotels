#!/usr/bin/env bash
set -Eeuo pipefail

# all.sh - Convenience wrapper to start server, run tests, and show report path

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

usage() {
  cat <<EOF2
Usage: $0 [--port N]

Options:
  -p, --port N    Port to use for the server and tests (default: 8000)
  -h, --help      Show this help
EOF2
}

# Parse args
while [[ $# -gt 0 ]]; do
  case "$1" in
    -p|--port) PORT="$2"; shift 2;;
    --port=*) PORT="${1#*=}"; shift;;
    -h|--help) usage; exit 0;;
    *) warn "Unknown argument: $1"; shift;;
  esac
done

info "Starting MCP server on port ${PORT}..."
bash scripts/start_mcp_server.sh --port "$PORT" || { error "Failed to start server"; exit 1; }

info "Running MCP test suite and report generation..."
set +e
PORT="$PORT" bash scripts/run_mcp_tests.sh --port "$PORT"
TEST_EXIT=$?
set -e

# Find most recent report
REPORT="$(ls -1t reports/mcp_report-*.md 2>/dev/null | head -n1 || true)"
if [[ -n "$REPORT" ]]; then
  success "Latest report: ${REPORT}"
else
  warn "No report found in ./reports"
fi

if [[ "$TEST_EXIT" -eq 0 ]]; then
  success "All checks passed."
else
  error "Some checks failed. See report for details."
fi

exit "$TEST_EXIT"
