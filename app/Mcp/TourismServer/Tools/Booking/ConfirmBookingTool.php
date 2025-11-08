<?php

namespace App\Mcp\TourismServer\Tools\Booking;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;
use App\Mail\BookingConfirmation;

class ConfirmBookingTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Confirm and finalize a prepared booking. This is the FINAL STEP in the booking process.
        
        IMPORTANT: Only call this tool AFTER:
        1. A booking has been prepared using PrepareBooking tool
        2. The user has explicitly confirmed they want to proceed with the booking
        3. You have the booking_id from the prepared booking
        
        This tool will:
        - Finalize the reservation
        - Generate ticket numbers
        - Mark the booking as confirmed
        - Simulate payment processing (mock transaction)
        - Send a confirmation email to the visitor with all booking details
    MARKDOWN;

    public function __construct(
        protected TourismService $tourismService
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'booking_id' => 'required|string',
            'payment_method' => 'nullable|string',
        ]);

        Log::info('Confirming booking:', $validated);

        $booking = $this->tourismService->getBooking($validated['booking_id']);

        if (!$booking) {
            return Response::text("Booking with ID {$validated['booking_id']} not found. Please prepare a booking first using the PrepareBooking tool.");
        }

        if ($booking['status'] === 'confirmed') {
            return Response::text("This booking has already been confirmed.\n\nBooking ID: {$booking['booking_id']}\nConfirmed at: {$booking['confirmed_at']}");
        }

        if ($booking['status'] === 'cancelled') {
            return Response::text("This booking has been cancelled and cannot be confirmed.");
        }

        // Validate that payment details are present
        if (empty($booking['payment_details'])) {
            return Response::text("# ❌ Payment Details Missing\n\nThis booking does not have valid payment details. Please prepare a new booking with valid credit card information.");
        }

        // Simulate payment processing
        $paymentMethod = 'Credit Card';
        $mockTransactionId = 'TXN-' . strtoupper(substr(md5($booking['booking_id'] . time()), 0, 12));
        
        Log::info('Processing mock payment', [
            'booking_id' => $booking['booking_id'],
            'amount' => $booking['total_amount'],
            'currency' => $booking['currency'],
            'transaction_id' => $mockTransactionId,
            'payment_method' => $paymentMethod,
        ]);

        // Confirm the booking
        $confirmedBooking = $this->tourismService->confirmBooking(
            bookingId: $validated['booking_id'],
            paymentTransactionId: $mockTransactionId
        );

        if (!$confirmedBooking) {
            return Response::text("Failed to confirm booking. Please try again or contact support.");
        }

        // Send confirmation email
        try {
            Mail::to($confirmedBooking['visitor_email'])
                ->send(new BookingConfirmation($confirmedBooking, $mockTransactionId));
            
            Log::info('Booking confirmation email sent', [
                'booking_id' => $confirmedBooking['booking_id'],
                'email' => $confirmedBooking['visitor_email']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send booking confirmation email', [
                'booking_id' => $confirmedBooking['booking_id'],
                'email' => $confirmedBooking['visitor_email'],
                'error' => $e->getMessage()
            ]);
            // Continue even if email fails - booking is still confirmed
        }

        // Build confirmation response
        $response = "# ✅ Booking Confirmed!\n\n";
        $response .= "🎉 Your booking has been successfully confirmed and paid for.\n\n";
        
        $response .= "## Confirmation Details\n";
        $response .= "**Booking ID:** `{$confirmedBooking['booking_id']}`\n";
        $response .= "**Status:** ✅ CONFIRMED\n";
        $response .= "**Confirmed At:** {$confirmedBooking['confirmed_at']}\n\n";
        
        $response .= "## Attraction Information\n";
        $response .= "**Attraction:** {$confirmedBooking['attraction_name']}\n";
        $response .= "**Category:** {$confirmedBooking['category']}\n";
        $response .= "**Visit Date:** {$confirmedBooking['visit_date']}\n";
        $response .= "**Opening Hours:** {$confirmedBooking['opening_hours']}\n";
        $response .= "**Duration:** ~{$confirmedBooking['duration_minutes']} minutes\n\n";
        
        $response .= "## Tickets\n";
        $response .= "**Number of Tickets:** {$confirmedBooking['number_of_tickets']}\n";
        $response .= "**Ticket Numbers:**\n";
        foreach ($confirmedBooking['ticket_numbers'] as $index => $ticketNumber) {
            $response .= "  " . ($index + 1) . ". `{$ticketNumber}`\n";
        }
        $response .= "\n";
        
        $response .= "## Payment Information\n";
        $response .= "**Amount Paid:** {$confirmedBooking['total_amount']} {$confirmedBooking['currency']}\n";
        $response .= "**Payment Method:** {$paymentMethod}\n";
        $response .= "**Card Number:** **** **** **** {$confirmedBooking['payment_details']['card_last_four']}\n";
        $response .= "**Cardholder:** {$confirmedBooking['payment_details']['card_holder_name']}\n";
        $response .= "**Transaction ID:** `{$mockTransactionId}`\n";
        $response .= "**Transaction Status:** ✅ SUCCESSFUL\n\n";
        
        $response .= "## Visitor Information\n";
        $response .= "**Name:** {$confirmedBooking['visitor_name']}\n";
        $response .= "**Email:** {$confirmedBooking['visitor_email']}\n\n";
        
        $response .= "---\n\n";
        $response .= "📧 **Confirmation email sent to:** {$confirmedBooking['visitor_email']}\n\n";
        $response .= "💡 **What's next?**\n";
        $response .= "- Save your booking ID and ticket numbers\n";
        $response .= "- Bring a printed copy or show this on your phone at the entrance\n";
        $response .= "- Arrive 15 minutes before your scheduled time\n\n";
        $response .= "**Included:** {$confirmedBooking['booking_details']}\n\n";
        $response .= "Have a wonderful visit! 🎉\n";

        return Response::text($response);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->string()
                ->description('The booking ID from the prepared booking (format: BKG-XXXXXXXX).')
                ->required(),

            'payment_method' => $schema->string()
                ->description('Payment method to use (e.g., "credit_card", "debit_card", "paypal"). Defaults to "credit_card".'),
        ];
    }
}

