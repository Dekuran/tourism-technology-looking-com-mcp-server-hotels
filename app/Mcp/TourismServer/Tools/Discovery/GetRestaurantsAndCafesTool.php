<?php

namespace App\Mcp\TourismServer\Tools\Discovery;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;

class GetRestaurantsAndCafesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get a list of restaurants and cafes in a specific destination.
        Perfect for travelers looking for dining options, coffee shops, or food experiences.
        
        Use this tool when users ask:
        - "What are some good restaurants in Vienna?"
        - "I'm in Salzburg, where can I eat?"
        - "Show me cafes in Vienna"
        - "Looking for places to dine in Innsbruck"
        
        Returns restaurants and cafes sorted by price (budget-friendly first).
        Each result includes pricing, opening hours, and reservation availability.
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
            'destination_id' => 'nullable|integer',
            'destination_name' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $validated['limit'] ?? 6;

        // Check that at least one identifier is provided
        if (empty($validated['destination_id']) && empty($validated['destination_name'])) {
            return Response::text("❌ Please provide either `destination_id` or `destination_name` to search for restaurants.");
        }

        $restaurants = $this->tourismService->getRestaurantsAndCafes(
            destinationId: $validated['destination_id'] ?? null,
            destinationName: $validated['destination_name'] ?? null,
            limit: $limit
        );

        if (empty($restaurants)) {
            $location = $validated['destination_name'] ?? "destination ID {$validated['destination_id']}";
            return Response::text("No restaurants or cafes found for {$location}. Please check the destination name or ID.");
        }

        // Get destination name for the header
        $destinationName = $validated['destination_name'] ?? 'this destination';
        if (!empty($validated['destination_id']) && empty($validated['destination_name'])) {
            $destination = $this->tourismService->getDestination($validated['destination_id']);
            if ($destination) {
                $destinationName = $destination['name'];
            }
        }

        // Build response
        $response = "# 🍽️ Restaurants & Cafes in {$destinationName}\n\n";
        $response .= "Found **" . count($restaurants) . "** dining option(s) sorted by price:\n\n";

        foreach ($restaurants as $index => $restaurant) {
            $number = $index + 1;
            $categoryIcon = $restaurant['category'] === 'Cafe' ? '☕' : '🍽️';
            
            $response .= "## {$number}. {$categoryIcon} {$restaurant['name']}\n";
            $response .= "**Category:** {$restaurant['category']}\n";
            $response .= "**ID:** {$restaurant['id']}\n";
            $response .= "{$restaurant['description']}\n\n";
            
            // Price and booking info
            if (isset($restaurant['price']) && $restaurant['price'] > 0) {
                $response .= "💰 **Average Price:** {$restaurant['price']} {$restaurant['currency']}";
                
                // Add price category
                if ($restaurant['price'] < 15) {
                    $response .= " (Budget-friendly)";
                } elseif ($restaurant['price'] < 50) {
                    $response .= " (Moderate)";
                } else {
                    $response .= " (Fine Dining)";
                }
                $response .= "\n";
            }
            
            if (isset($restaurant['opening_hours'])) {
                $response .= "🕐 **Opening Hours:** {$restaurant['opening_hours']}\n";
            }
            
            if (isset($restaurant['duration_minutes'])) {
                $response .= "⏱️ **Typical Duration:** ~{$restaurant['duration_minutes']} minutes\n";
            }
            
            // Reservation info
            if ($restaurant['bookable']) {
                $response .= "📅 **Reservations:** Available (use PrepareRestaurantReservation tool)\n";
            } else {
                $response .= "📅 **Reservations:** Walk-in only\n";
            }
            
            // Tags
            if (!empty($restaurant['tags'])) {
                $tagsList = array_map(function($tag) {
                    return ucfirst(str_replace('-', ' ', $tag));
                }, $restaurant['tags']);
                $response .= "🏷️ **Tags:** " . implode(', ', $tagsList) . "\n";
            }
            
            $response .= "\n---\n\n";
        }

        $response .= "💡 **Tip:** To reserve a table at any of these restaurants or cafes, use the `PrepareRestaurantReservation` tool with the restaurant ID.\n";
        $response .= "\n📍 **Note:** All locations are in {$destinationName}.\n";

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
            'destination_id' => $schema->integer()
                ->description('ID of the destination. Either this or destination_name is required.'),

            'destination_name' => $schema->string()
                ->description('Name of the destination (e.g., "Vienna", "Salzburg"). Either this or destination_id is required.'),

            'limit' => $schema->integer()
                ->description('Maximum number of restaurants to return (1-20, default: 6).')
                ->default(6),
        ];
    }
}

