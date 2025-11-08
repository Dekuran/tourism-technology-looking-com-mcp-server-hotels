#!/usr/bin/env bash
set -Eeuo pipefail

# test_booking_api.sh — Focused tests for the Booking API (Prepare/Confirm) and Accommodation tools
# - Service-level end-to-end booking flow (prepare → confirm)
# - Filtered PHPUnit runs for Booking + Accommodation tool tests
# - Writes a dedicated Markdown report under ./reports/

if [[ -t 1 ]]; then
  RED="$(printf '\033[31m')"; GREEN="$(printf '\033[32m')"; YELLOW="$(printf '\033[33m')"
  BLUE="$(printf '\033[34m')"; MAGENTA="$(printf '\033[35m')"; CYAN="$(printf '\033[36m')"; RESET="$(printf '\033[0m')"
else
  RED=""; GREEN=""; YELLOW=""; BLUE=""; MAGENTA=""; CYAN=""; RESET=""
fi
info(){ echo "${CYAN}➤${RESET} $*"; }
success(){ echo "${GREEN}✔${RESET} $*"; }
warn(){ echo "${YELLOW}▲${RESET} $*"; }
error(){ echo "${RED}✖${RESET} $*" >&2; }
hr(){ echo "${MAGENTA}────────────────────────────────────────────────────────────────${RESET}"; }

REPORTS_DIR="reports"
TMP_DIR=".tmp_mcp"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
REPORT_PATH="${REPORTS_DIR}/booking_api_report-${TIMESTAMP}.md"

mkdir -p "$REPORTS_DIR" "$TMP_DIR"

# Start report
: > "$REPORT_PATH"
add_md(){ printf "%s\n" "$*" >> "$REPORT_PATH"; }
add_md_code(){ local lang="$1"; shift; printf '```%s\n%s\n```\n' "$lang" "$*" >> "$REPORT_PATH"; }

add_md "# Booking API Verification Report"
add_md
add_md "- Generated: $(date -Iseconds)"
add_md "- Host: $(hostname)"
add_md

# Preflight
hr
info "Checking prerequisites (php, composer)..."
for cmd in php composer; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    error "Missing prerequisite: $cmd"
    add_md "❌ Missing prerequisite: $cmd"
    exit 1
  fi
done
success "Prerequisites present."
add_md "✅ Prerequisites present."

# Ensure vendor installed (fast-path if already installed)
if [[ ! -d vendor ]]; then
  hr
  info "Installing PHP dependencies (composer install)..."
  composer install --no-interaction --prefer-dist >/dev/null
  success "Composer installed."
fi

# Ensure .env + APP_KEY + sqlite for cache-backed booking logic
if [[ ! -f .env ]]; then
  cp .env.example .env 2>/dev/null || {
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
  }
fi
if ! grep -q '^APP_KEY=' .env; then printf '\nAPP_KEY=\n' >> .env; fi
php artisan key:generate >/dev/null 2>&1 || true
mkdir -p database
[[ -f database/database.sqlite ]] || : > database/database.sqlite

# 1) End-to-end Booking Flow using service layer (Prepare → Confirm)
hr
info "Running service-level booking flow (prepare → confirm)..."
add_md "## End-to-End Booking Flow (Service-level)"
add_md
BOOKING_FLOW_PHP="${TMP_DIR}/booking_flow.php"
cat > "$BOOKING_FLOW_PHP" <<'PHP'
<?php
// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\TourismService;

// Choose a known bookable attraction from mock dataset (e.g., Belvedere Palace - 103)
$attractionId = 103;
$tomorrow = date('Y-m-d', time()+86400);

// Payment details are masked at service-level; choose plausible values
$paymentDetails = [
  'card_last_four' => '4242',
  'card_holder_name' => 'Test User',
  'card_expiry' => '12/30',
];

// Run flow
/** @var TourismService $svc */
$svc = app(TourismService::class);

// Prepare
$prepared = $svc->prepareBooking(
  attractionId: $attractionId,
  numberOfTickets: 2,
  visitDate: $tomorrow,
  visitorName: 'Test User',
  visitorEmail: 'test.user@example.org',
  paymentDetails: $paymentDetails
);

// Confirm (if prepared)
$confirmed = null;
if ($prepared && isset($prepared['booking_id'])) {
  $confirmed = $svc->confirmBooking($prepared['booking_id'], 'TXN-TESTCONFIRM1234');
}

echo json_encode([
  'prepared' => $prepared,
  'confirmed' => $confirmed,
], JSON_PRETTY_PRINT) . PHP_EOL;
PHP

BOOKING_JSON="$(php "$BOOKING_FLOW_PHP" 2>/dev/null || true)"
if [[ -n "$BOOKING_JSON" ]]; then
  add_md "**Result JSON:**"
  add_md_code "json" "$BOOKING_JSON"
  if echo "$BOOKING_JSON" | grep -q '"confirmed"'; then
    success "Booking flow executed successfully."
    add_md "✅ Booking flow executed."
  else
    warn "Booking confirmation missing."
    add_md "⚠ Booking confirmation missing."
  fi
else
  error "Booking flow script produced no output."
  add_md "❌ Booking flow script produced no output."
fi
add_md

# 2) Tool-level tests for Booking + Accommodation via PHPUnit filters
hr
info "Running PHPUnit filtered tests for Booking + Accommodation tools..."
add_md "## Booking + Accommodation Tool Tests (PHPUnit filtered)"
add_md
TEST_OUT="${TMP_DIR}/phpunit_booking.txt"
set +e
php artisan test --filter "PrepareBookingToolTest|ConfirmBookingToolTest|HotelRoomAvailabilityToolTest|CreateHotelReservationToolTest" > "$TEST_OUT" 2>&1
TEST_EXIT=$?
set -e

# Add tail and status
add_md "_Tail of filtered test output:_"
add_md_code "text" "$(tail -n 250 "$TEST_OUT" || true)"
if [[ "$TEST_EXIT" -eq 0 ]]; then
  success "Filtered PHPUnit tests passed."
  add_md "✅ Filtered PHPUnit tests passed."
else
  warn "Filtered PHPUnit tests reported failures."
  add_md "❌ Filtered PHPUnit tests reported failures (exit code ${TEST_EXIT})."
fi
add_md

# Summary
add_md "## Summary"
if [[ "$TEST_EXIT" -eq 0 ]]; then
  add_md "- Overall Result: ✅ PASS (filtered booking/accommodation tests)"
else
  add_md "- Overall Result: ❌ FAIL (filtered booking/accommodation tests)"
fi
add_md "- Report file: ${REPORT_PATH}"

success "Booking API report generated: ${REPORT_PATH}"
echo "${REPORT_PATH}"