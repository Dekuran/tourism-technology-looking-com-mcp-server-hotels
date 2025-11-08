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
