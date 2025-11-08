<?php

namespace App\Mcp\TourismServer\Tools\Discovery;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;

class GetTopAttractionsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get the top tourist attractions for a specific destination.
        Perfect for travelers who want to know the must-see sights in a city.
        Returns the most popular attractions, prioritizing bookable ones.
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
            'destination_name' => 'nullable|string|max:100',
            'destination_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        Log::info('Getting top attractions:', $validated);

        $destinationId = null;
        $destinationName = null;

        // Get destination by ID or name
        if (!empty($validated['destination_id'])) {
            $destination = $this->tourismService->getDestination($validated['destination_id']);
            if ($destination) {
                $destinationId = $destination['id'];
                $destinationName = $destination['name'];
            }
        } elseif (!empty($validated['destination_name'])) {
            $destination = $this->tourismService->getDestinationByName($validated['destination_name']);
            if ($destination) {
                $destinationId = $destination['id'];
                $destinationName = $destination['name'];
            } else {
                return Response::text("Destination '{$validated['destination_name']}' not found. Please search for destinations first using SearchDestinations tool.");
            }
        } else {
            return Response::text("Please provide either a destination_name or destination_id.");
        }

        $limit = $validated['limit'] ?? 4;
        $attractions = $this->tourismService->getTopAttractions($destinationId, $limit);

        if (empty($attractions)) {
            return Response::text("No attractions found for {$destinationName}.");
        }

        $response = "# 🌟 Top " . count($attractions) . " Attractions in {$destinationName}\n\n";
        $response .= "Here are the must-see sights:\n\n";

        foreach ($attractions as $index => $attraction) {
            $number = $index + 1;
            $bookableIcon = $attraction['bookable'] ? '🎫' : '📍';
            
            $response .= "## {$number}. {$bookableIcon} {$attraction['name']}\n";
            $response .= "**Category:** {$attraction['category']}\n";
            $response .= "**Description:** {$attraction['description']}\n";
            
            if ($attraction['bookable']) {
                $response .= "**💰 Price:** {$attraction['price']} {$attraction['currency']} per ticket\n";
                $response .= "**⏱️ Duration:** {$attraction['duration_minutes']} minutes\n";
                $response .= "**🕐 Hours:** {$attraction['opening_hours']}\n";
                $response .= "**✅ Bookable:** Yes - Attraction ID: `{$attraction['id']}`\n";
                $response .= "**📋 Includes:** {$attraction['booking_details']}\n";
            } else {
                $response .= "**ℹ️ Bookable:** No (Free entry or buy tickets on-site)\n";
            }
            
            $response .= "\n";
        }

        $response .= "---\n\n";
        $response .= "💡 **How to book:**\n";
        $response .= "1. Use **GetAttractionDetails** tool with the attraction ID for more info\n";
        $response .= "2. Use **PrepareBooking** tool to start the booking process\n";
        $response .= "3. Review and confirm with **ConfirmBooking** tool\n";

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
            'destination_name' => $schema->string()
                ->description('Name of the destination (e.g., "Vienna", "Salzburg").'),

            'destination_id' => $schema->integer()
                ->description('ID of the destination.'),

            'limit' => $schema->integer()
                ->description('Maximum number of attractions to return (1-10, default: 4).')
                ->default(4),
        ];
    }
}

