<?php

namespace App\Mcp\TourismServer\Tools\Booking;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;
use LVR\CreditCard\CardCvc;
use LVR\CreditCard\CardNumber;
use LVR\CreditCard\CardExpirationDate;

class PrepareBookingTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Prepare a booking for an attraction. This creates a pending booking/reservation.
        The booking will include pricing details and a booking ID that needs to be confirmed.
        
        ⚠️ CRITICAL: You MUST collect REAL user information before calling this tool!
        
        **REQUIRED USER INFORMATION:**
        - Visitor's REAL full name (NOT "user", "guest", or "John Doe")
        - Visitor's REAL email address (NOT "user@example.com" or any @example.com address)
        - REAL credit card details (number, holder name, expiry, CVV)
        
        **NEVER use placeholder or example values** - Always ask the user for their actual information first!
        
        IMPORTANT: Credit card details are REQUIRED to prepare a booking.
        
        This is the FIRST STEP in the booking process. After preparing the booking:
        1. Show the user the booking details and total price
        2. Wait for user confirmation
        3. Use ConfirmBooking tool to finalize the reservation
    MARKDOWN;

    public function __construct(
        protected TourismService $tourismService
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // First validate basic fields
        $validated = $request->validate([
            'attraction_id' => 'required|integer',
            'number_of_tickets' => 'required|integer|min:1|max:10',
            'visit_date' => 'required|date',
            'visitor_name' => 'required|string|max:100',
            'visitor_email' => 'required|email|max:100',
            'card_number' => 'required|string',
            'card_holder_name' => 'required|string|max:100',
            'card_expiry' => 'required|string',
            'card_cvv' => 'required|string',
        ]);

        $creditCardValidator = Validator::make($validated, [
            'card_number' => ['required', new CardNumber],
            'card_expiry' => ['required', new CardExpirationDate($validated['card_expiry'] ?? '')],
            'card_cvv' => ['required', new CardCvc($validated['card_number'] ?? '')],
            'card_holder_name' => 'required|string|max:100',
        ]);

        // If credit card validation fails, return detailed errors
        if ($creditCardValidator->fails()) {
            $errors = $creditCardValidator->errors()->all();
            
            $response = "# ❌ Credit Card Validation Failed\n\n";
            $response .= "The booking cannot be prepared due to the following credit card validation errors:\n\n";
            foreach ($errors as $index => $error) {
                $response .= ($index + 1) . ". " . $error . "\n";
            }
            $response .= "\n**Please provide valid credit card details to proceed with the booking.**\n";
            return Response::text($response);
        }

        $attraction = $this->tourismService->getAttraction($validated['attraction_id']);

        if (!$attraction) {
            return Response::text("Attraction with ID {$validated['attraction_id']} not found.");
        }

        if (!$attraction['bookable']) {
            return Response::text("Sorry, {$attraction['name']} is not available for advance booking. Entry is free or tickets are purchased on-site.");
        }

        // Prepare payment details (masked for security)
        $paymentDetails = [
            'card_last_four' => substr(preg_replace('/[\s-]/', '', $validated['card_number']), -4),
            'card_holder_name' => $validated['card_holder_name'],
            'card_expiry' => $validated['card_expiry'],
        ];

        $booking = $this->tourismService->prepareBooking(
            attractionId: $validated['attraction_id'],
            numberOfTickets: $validated['number_of_tickets'],
            visitDate: $validated['visit_date'],
            visitorName: $validated['visitor_name'],
            visitorEmail: $validated['visitor_email'],
            paymentDetails: $paymentDetails
        );

        if (!$booking) {
            return Response::text("Failed to prepare booking. Please try again.");
        }

        // Build detailed booking confirmation request
        $response = "# 🎫 Booking Prepared\n\n";
        $response .= "Your booking has been prepared and is awaiting confirmation.\n\n";
        $response .= "## Booking Details\n";
        $response .= "**Booking ID:** `{$booking['booking_id']}`\n";
        $response .= "**Attraction:** {$booking['attraction_name']}\n";
        $response .= "**Category:** {$booking['category']}\n";
        $response .= "**Visit Date:** {$booking['visit_date']}\n";
        $response .= "**Number of Tickets:** {$booking['number_of_tickets']}\n\n";
        
        $response .= "## Pricing\n";
        $response .= "**Price per Ticket:** {$booking['price_per_ticket']} {$booking['currency']}\n";
        $response .= "**Total Amount:** **{$booking['total_amount']} {$booking['currency']}**\n\n";
        
        $response .= "## Payment Method\n";
        $response .= "**Card Number:** **** **** **** {$booking['payment_details']['card_last_four']}\n";
        $response .= "**Cardholder:** {$booking['payment_details']['card_holder_name']}\n";
        $response .= "**Expiry:** {$booking['payment_details']['card_expiry']}\n\n";
        
        $response .= "## What's Included\n";
        $response .= "{$booking['booking_details']}\n";
        $response .= "**Duration:** ~{$booking['duration_minutes']} minutes\n";
        $response .= "**Opening Hours:** {$booking['opening_hours']}\n\n";
        
        $response .= "## Visitor Information\n";
        $response .= "**Name:** {$booking['visitor_name']}\n";
        $response .= "**Email:** {$booking['visitor_email']}\n\n";
        
        $response .= "---\n\n";
        $response .= "⚠️ **IMPORTANT:** This booking is currently **PENDING** and NOT yet confirmed.\n\n";
        $response .= "**Next Steps:**\n";
        $response .= "1. Review the booking details above\n";
        $response .= "2. Confirm with the user that they want to proceed\n";
        $response .= "3. Use the **ConfirmBooking** tool with booking_id: `{$booking['booking_id']}` to finalize\n\n";
        $response .= "💡 The booking will be held for 15 minutes. Please confirm soon!\n";

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
            'attraction_id' => $schema->integer()
                ->description('The ID of the attraction to book.')
                ->required(),

            'number_of_tickets' => $schema->integer()
                ->description('Number of tickets to book (1-10).')
                ->required(),

            'visit_date' => $schema->string()
                ->description('Date of visit in YYYY-MM-DD format.')
                ->required(),

            'visitor_name' => $schema->string()
                ->description('REAL full name of the visitor. NEVER use "user", "guest", "John Doe", or other placeholders. Ask the user for their actual name.')
                ->required(),

            'visitor_email' => $schema->string()
                ->description('REAL email address of the visitor. NEVER use "user@example.com", "guest@example.com", or any @example.com addresses. Ask the user for their actual email.')
                ->required(),

            'card_number' => $schema->string()
                ->description('Credit card number (16 digits).')
                ->required(),

            'card_holder_name' => $schema->string()
                ->description('Full name of the cardholder as it appears on the card.')
                ->required(),

            'card_expiry' => $schema->string()
                ->description('Card expiry date in MM/YY or MM/YYYY format.')
                ->required(),

            'card_cvv' => $schema->string()
                ->description('Card CVV/CVC security code (3 or 4 digits).')
                ->required(),
        ];
    }
}

