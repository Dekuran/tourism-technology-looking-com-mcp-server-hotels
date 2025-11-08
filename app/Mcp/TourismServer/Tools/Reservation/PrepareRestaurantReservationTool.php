<?php

namespace App\Mcp\TourismServer\Tools\Reservation;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;

class PrepareRestaurantReservationTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Reserve a table at a restaurant or cafe. This creates a simple reservation without payment.
        
        ⚠️ CRITICAL: You MUST collect REAL user information before calling this tool!
        
        **REQUIRED USER INFORMATION:**
        - Guest's REAL full name (NOT "guest", "user", or "John Doe")
        - Guest's REAL email address (NOT "user@example.com" or any @example.com address)
        - Number of people
        - Reservation date and time
        
        **NEVER use placeholder or example values** - Always ask the user for their actual information first!
        
        NO CREDIT CARD NEEDED - This is just a table reservation!
        
        This is the FIRST STEP in the reservation process. After preparing the reservation:
        1. Show the user the reservation details
        2. Wait for user confirmation
        3. Use ConfirmRestaurantReservation tool to finalize the reservation
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
            'attraction_id' => 'required|integer',
            'number_of_people' => 'required|integer|min:1|max:20',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required|string',
            'guest_name' => 'required|string|max:100',
            'guest_email' => 'required|email|max:100',
            'special_requests' => 'nullable|string|max:500',
        ]);

        $attraction = $this->tourismService->getAttraction($validated['attraction_id']);

        if (!$attraction) {
            return Response::text("Attraction with ID {$validated['attraction_id']} not found.");
        }

        if (!in_array($attraction['category'], ['Restaurant', 'Cafe'])) {
            return Response::text("Sorry, {$attraction['name']} is not a restaurant or cafe. Only restaurants and cafes can be reserved using this tool.");
        }

        $reservation = $this->tourismService->prepareRestaurantReservation(
            attractionId: $validated['attraction_id'],
            numberOfPeople: $validated['number_of_people'],
            reservationDate: $validated['reservation_date'],
            reservationTime: $validated['reservation_time'],
            guestName: $validated['guest_name'],
            guestEmail: $validated['guest_email'],
            specialRequests: $validated['special_requests'] ?? null
        );

        if (!$reservation) {
            return Response::text("Failed to prepare reservation. Please try again.");
        }

        // Build detailed reservation confirmation request
        $response = "# 🍽️ Restaurant Reservation Prepared\n\n";
        $response .= "Your table reservation has been prepared and is awaiting confirmation.\n\n";
        $response .= "## Reservation Details\n";
        $response .= "**Reservation ID:** `{$reservation['reservation_id']}`\n";
        $response .= "**Restaurant/Cafe:** {$reservation['attraction_name']}\n";
        $response .= "**Type:** {$reservation['category']}\n";
        $response .= "**Reservation Date:** {$reservation['reservation_date']}\n";
        $response .= "**Reservation Time:** {$reservation['reservation_time']}\n";
        $response .= "**Number of People:** {$reservation['number_of_people']}\n\n";
        
        $response .= "## Guest Information\n";
        $response .= "**Name:** {$reservation['guest_name']}\n";
        $response .= "**Email:** {$reservation['guest_email']}\n";
        
        if (!empty($reservation['special_requests'])) {
            $response .= "**Special Requests:** {$reservation['special_requests']}\n";
        }
        
        $response .= "\n## Restaurant Information\n";
        $response .= "**Opening Hours:** {$reservation['opening_hours']}\n\n";
        
        $response .= "---\n\n";
        $response .= "⚠️ **IMPORTANT:** This reservation is currently **PENDING** and NOT yet confirmed.\n\n";
        $response .= "**Next Steps:**\n";
        $response .= "1. Review the reservation details above\n";
        $response .= "2. Confirm with the user that they want to proceed\n";
        $response .= "3. Use the **ConfirmRestaurantReservation** tool with reservation_id: `{$reservation['reservation_id']}` to finalize\n\n";
        $response .= "💡 The reservation will be held for 30 minutes. Please confirm soon!\n";
        $response .= "✅ **No payment required** - This is just a table reservation!\n";

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
                ->description('The ID of the restaurant or cafe.')
                ->required(),

            'number_of_people' => $schema->integer()
                ->description('Number of people for the reservation (1-20).')
                ->required(),

            'reservation_date' => $schema->string()
                ->description('Date of reservation in YYYY-MM-DD format.')
                ->required(),

            'reservation_time' => $schema->string()
                ->description('Time of reservation (e.g., "7:00 PM", "19:00", "12:30").')
                ->required(),

            'guest_name' => $schema->string()
                ->description('REAL full name of the guest making the reservation. NEVER use "guest", "user", "John Doe", or other placeholders. Ask the user for their actual name.')
                ->required(),

            'guest_email' => $schema->string()
                ->description('REAL email address of the guest. NEVER use "guest@example.com", "user@example.com", or any @example.com addresses. Ask the user for their actual email.')
                ->required(),

            'special_requests' => $schema->string()
                ->description('Optional special requests (e.g., "window seat", "birthday celebration", "high chair needed").')
                ->nullable(),
        ];
    }
}

