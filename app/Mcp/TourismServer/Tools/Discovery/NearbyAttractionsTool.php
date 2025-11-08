<?php

namespace App\Mcp\TourismServer\Tools\Discovery;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;

class NearbyAttractionsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Find tourist attractions near a given location or destination.
        You can provide either latitude/longitude or a destination name.
        The tool returns a list of nearby attractions with name, category, and distance.
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
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'radius_km' => 'nullable|numeric',
        ]);

        Log::info('Validated data:', $validated);
        
        // Cast radius_km to int if provided
        if (isset($validated['radius_km'])) {
            $validated['radius_km'] = (int) $validated['radius_km'];
        }

        // Look up destination by name if provided
        $destinationId = null;
        if (!empty($validated['destination_name'])) {
            $destination = $this->tourismService->getDestinationByName($validated['destination_name']);
            if (!$destination) {
                return Response::text("Destination '{$validated['destination_name']}' not found. Please try a different destination name.");
            }
            $destinationId = $destination['id'];
        }

        $attractions = $this->tourismService->findNearbyAttractions(
            destinationId: $destinationId,
            latitude: $validated['latitude'] ?? null,
            longitude: $validated['longitude'] ?? null,
            radiusKm: $validated['radius_km'] ?? 10
        );

        if (empty($attractions)) {
            return Response::text('No attractions found in the specified area.');
        }

        $response = "Found " . count($attractions) . " nearby attractions:\n\n";
        
        foreach ($attractions as $attraction) {
            $response .= "📍 **{$attraction['name']}**\n";
            $response .= "   Category: {$attraction['category']}\n";
            $response .= "   Distance: {$attraction['distance_km']} km\n";
            $response .= "   Description: {$attraction['description']}\n\n";
        }

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
                ->description('Optional destination name to use as a reference point (e.g., "Vienna", "Salzburg").'),

            'latitude' => $schema->number()
                ->description('Latitude of the reference point.'),

            'longitude' => $schema->number()
                ->description('Longitude of the reference point.'),

            'radius_km' => $schema->number()
                ->description('Search radius in kilometers (default: 10).')
                ->default(10),
        ];
    }
}
