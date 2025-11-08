<?php

namespace App\Mcp\TourismServer\Tools\Reservation;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;

class ConfirmRestaurantReservationTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Confirm and finalize a restaurant/cafe reservation. This is the FINAL STEP in the reservation process.
        
        IMPORTANT: Only call this tool AFTER:
        1. A reservation has been prepared using PrepareRestaurantReservation tool
        2. The user has explicitly confirmed they want to proceed with the reservation
        3. You have the reservation_id from the prepared reservation
        
        This tool will:
        - Finalize the table reservation
        - Generate a confirmation number
        - Mark the reservation as confirmed
        - Send a confirmation email to the guest (simulated)
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
            'reservation_id' => 'required|string',
        ]);

        $reservation = $this->tourismService->confirmRestaurantReservation($validated['reservation_id']);

        if (!$reservation) {
            return Response::text("❌ Reservation not found. The reservation ID `{$validated['reservation_id']}` is invalid or has expired.");
        }

        // Build confirmation response
        $response = "# ✅ Restaurant Reservation Confirmed!\n\n";
        $response .= "Your table reservation has been successfully confirmed.\n\n";
        $response .= "## Confirmation Details\n";
        $response .= "**Confirmation Number:** `{$reservation['confirmation_number']}`\n";
        $response .= "**Reservation ID:** `{$reservation['reservation_id']}`\n";
        $response .= "**Status:** ✅ **CONFIRMED**\n\n";
        
        $response .= "## Reservation Information\n";
        $response .= "**Restaurant/Cafe:** {$reservation['attraction_name']}\n";
        $response .= "**Type:** {$reservation['category']}\n";
        $response .= "**Date:** {$reservation['reservation_date']}\n";
        $response .= "**Time:** {$reservation['reservation_time']}\n";
        $response .= "**Party Size:** {$reservation['number_of_people']} people\n\n";
        
        $response .= "## Guest Details\n";
        $response .= "**Name:** {$reservation['guest_name']}\n";
        $response .= "**Email:** {$reservation['guest_email']}\n";
        
        if (!empty($reservation['special_requests'])) {
            $response .= "**Special Requests:** {$reservation['special_requests']}\n";
        }
        
        $response .= "\n## Restaurant Details\n";
        $response .= "**Opening Hours:** {$reservation['opening_hours']}\n";
        $response .= "**Location:** Latitude {$reservation['location']['latitude']}, Longitude {$reservation['location']['longitude']}\n\n";
        
        $response .= "---\n\n";
        $response .= "📧 **Confirmation email sent to:** {$reservation['guest_email']}\n\n";
        $response .= "### Important Notes:\n";
        $response .= "- Please arrive on time for your reservation\n";
        $response .= "- Keep your confirmation number: `{$reservation['confirmation_number']}`\n";
        $response .= "- If you need to cancel, please contact the restaurant in advance\n";
        $response .= "- No payment was processed - this is just a table reservation\n\n";
        $response .= "🎉 **We look forward to serving you!**\n";

        Log::info('Restaurant reservation confirmed', [
            'reservation_id' => $reservation['reservation_id'],
            'confirmation_number' => $reservation['confirmation_number'],
            'restaurant' => $reservation['attraction_name'],
        ]);

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
            'reservation_id' => $schema->string()
                ->description('The reservation ID from the prepared reservation (format: RSV-XXXXXXXX).')
                ->required(),
        ];
    }
}

