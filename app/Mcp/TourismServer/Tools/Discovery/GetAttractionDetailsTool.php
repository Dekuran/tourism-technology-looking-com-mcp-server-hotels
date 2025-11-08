<?php

namespace App\Mcp\TourismServer\Tools\Discovery;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;

class GetAttractionDetailsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get detailed information about a specific tourist attraction including pricing, booking availability, and opening hours.
        Use this tool when the user wants to know more about a specific attraction before booking.
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
        ]);

        Log::info('Getting attraction details:', $validated);

        $attraction = $this->tourismService->getAttraction($validated['attraction_id']);

        if (!$attraction) {
            return Response::text("Attraction with ID {$validated['attraction_id']} not found.");
        }

        // Build detailed response
        $response = "# {$attraction['name']}\n\n";
        $response .= "**Category:** {$attraction['category']}\n";
        $response .= "**Description:** {$attraction['description']}\n\n";

        if ($attraction['bookable']) {
            $response .= "## Booking Information\n";
            $response .= "✅ **Bookable:** Yes\n";
            $response .= "💰 **Price:** {$attraction['price']} {$attraction['currency']} per ticket\n";
            $response .= "⏱️ **Duration:** " . ($attraction['duration_minutes'] ?? 'N/A') . " minutes\n";
            $response .= "🕐 **Opening Hours:** " . ($attraction['opening_hours'] ?? 'N/A') . "\n";
            $response .= "📋 **What's Included:** " . ($attraction['booking_details'] ?? 'Standard access') . "\n\n";
            $response .= "To book this attraction, use the PrepareBooking tool with attraction_id: {$attraction['id']}\n";
        } else {
            $response .= "## Booking Information\n";
            $response .= "❌ **Bookable:** No (Free entry or no advance booking required)\n";
        }

        $response .= "\n**Location:** {$attraction['latitude']}, {$attraction['longitude']}\n";
        $response .= "**Attraction ID:** {$attraction['id']}\n";

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
                ->description('The ID of the attraction to get details for.')
                ->required(),
        ];
    }
}

