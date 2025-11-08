<?php

namespace App\Mcp\TourismServer\Tools\External;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;
use App\Services\MastercardService;

class ATMLocatorTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Find nearby ATM locations using Mastercard's ATM Locator API.
        
        This tool helps tourists and travelers find ATMs near:
        - A city or destination name (e.g., "Vienna", "Paris")
        - A specific attraction or landmark
        - GPS coordinates (latitude/longitude)
        - A postal/ZIP code
        
        The tool returns detailed ATM information including:
        - Location name and full address
        - GPS coordinates
        - Distance from search point
        - Features (wheelchair accessible, 24/7 availability, camera, EMV support, deposit capability)
        - Owner/operator information
        - Access fees (domestic/international)
        
        Perfect for helping travelers find convenient cash withdrawal locations during their trips.
    MARKDOWN;

    public function __construct(
        protected TourismService $tourismService,
        protected MastercardService $mastercardService
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'location' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'destination_name' => 'nullable|string|max:100',
            'attraction_id' => 'nullable|integer',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|size:3',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'distance' => 'nullable|numeric|min:1|max:50',
            'distance_unit' => 'nullable|string|in:MILE,KM',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            // Determine search parameters
            $searchParams = $this->determineSearchParams($validated);
            
            if (!$searchParams) {
                return Response::text(json_encode([
                    'success' => false,
                    'error' => 'Unable to determine location. Please provide either: location/city name, attraction_id, postal_code, or latitude/longitude coordinates.',
                ]));
            }

            Log::info('ATM search parameters', $searchParams);

            // Get ATM locations from Mastercard API
            $atms = $this->searchATMs($searchParams);

            if (!$atms['success']) {
                return Response::text(json_encode([
                    'success' => false,
                    'error' => $atms['error'],
                    'message' => $atms['message'] ?? 'Failed to retrieve ATM locations',
                ]));
            }

            // Format response for AI
            $response = $this->formatATMResponse($atms['data'], $searchParams);

            return Response::text($response);

        } catch (\Exception $e) {
            Log::error('ATM Locator Tool error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'success' => false,
                'error' => 'ATM search failed',
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Determine search parameters from input
     */
    private function determineSearchParams(array $validated): ?array
    {
        $params = [
            'Distance' => $validated['distance'] ?? 5,
            'DistanceUnit' => $validated['distance_unit'] ?? 'MILE',
            'PageOffset' => 0,
            'PageLength' => $validated['limit'] ?? 10,
        ];

        // Priority 1: Direct coordinates
        if (isset($validated['latitude']) && isset($validated['longitude'])) {
            $params['Latitude'] = $validated['latitude'];
            $params['Longitude'] = $validated['longitude'];
            $params['search_type'] = 'coordinates';
            return $params;
        }

        // Priority 2: Postal code
        if (isset($validated['postal_code'])) {
            $params['PostalCode'] = $validated['postal_code'];
            $params['Country'] = $validated['country'] ?? 'USA';
            $params['search_type'] = 'postal';
            return $params;
        }

        // Priority 3: Attraction ID - get coordinates
        if (isset($validated['attraction_id'])) {
            $attraction = $this->tourismService->getAttraction($validated['attraction_id']);
            if ($attraction && isset($attraction['latitude']) && isset($attraction['longitude'])) {
                $params['Latitude'] = $attraction['latitude'];
                $params['Longitude'] = $attraction['longitude'];
                $params['search_type'] = 'attraction';
                $params['search_name'] = $attraction['name'];
                return $params;
            }
        }

        // Priority 4: Destination/City name - get coordinates
        $locationName = $validated['destination_name'] ?? $validated['city'] ?? $validated['location'] ?? null;
        if ($locationName) {
            $destination = $this->tourismService->getDestinationByName($locationName);
            if ($destination && isset($destination['latitude']) && isset($destination['longitude'])) {
                $params['Latitude'] = $destination['latitude'];
                $params['Longitude'] = $destination['longitude'];
                $params['search_type'] = 'destination';
                $params['search_name'] = $destination['name'];
                return $params;
            }
            
            // If not in our database, try to geocode (for now, return null)
            // In production, you could integrate a geocoding service
            return null;
        }

        return null;
    }

    /**
     * Search ATMs using Mastercard API
     */
    private function searchATMs(array $params): array
    {
        return $this->mastercardService->searchATMs($params);
    }

    /**
     * Format ATM response for AI
     */
    private function formatATMResponse(array $data, array $searchParams): string
    {
        if (isset($data['atms'])) {
            return $this->formatResponse($data, $searchParams);
        }
        
        // No ATMs found
        $searchInfo = $this->getSearchDescription($searchParams);
        return json_encode([
            'success' => true,
            'message' => "No ATMs found {$searchInfo}. Try increasing the search radius.",
            'total_count' => 0,
            'atms' => [],
        ]);
    }

    /**
     * Format v2 JSON response (new API)
     */
    private function formatResponse(array $data, array $searchParams): string
    {
        $atms = $data['atms'] ?? [];
        $count = $data['count'] ?? count($atms);
        $total = $data['total'] ?? $count;
        
        if (empty($atms)) {
            $searchInfo = $this->getSearchDescription($searchParams);
            return json_encode([
                'success' => true,
                'message' => "No ATMs found {$searchInfo}. Try increasing the search radius.",
                'total_count' => 0,
                'atms' => [],
            ]);
        }
        
        $searchInfo = $this->getSearchDescription($searchParams);
        $formattedAtms = [];

        foreach ($atms as $atm) {
            $features = [];
            if (($atm['handicapAccessible'] ?? 'NO') === 'YES') $features[] = 'wheelchair accessible';
            if (($atm['camera'] ?? 'NO') === 'YES') $features[] = 'security camera';
            if (($atm['sharedDeposit'] ?? 'NO') === 'YES') $features[] = 'accepts deposits';
            if (isset($atm['supportEmv']) && $atm['supportEmv'] != 0) $features[] = 'EMV chip support';
            
            $availability = str_replace('_', ' ', strtolower($atm['availability'] ?? 'unknown'));
            $locationType = isset($atm['locationName']) ? 'atm' : 'unknown';
            
            $formattedAtms[] = [
                'name' => $atm['locationName'] ?? $atm['owner'] ?? 'ATM',
                'address' => [
                    'street' => $atm['addressLine1'] ?? '',
                    'city' => $atm['city'] ?? '',
                    'state' => $atm['countrySubdivisionName'] ?? '',
                    'postal_code' => $atm['postalCode'] ?? '',
                    'country' => $atm['countryName'] ?? '',
                ],
                'coordinates' => [
                    'latitude' => (float) ($atm['latitude'] ?? 0),
                    'longitude' => (float) ($atm['longitude'] ?? 0),
                ],
                'distance' => isset($atm['distance']) ? round((float) $atm['distance'], 2) . ' ' . strtolower($atm['distanceUnit'] ?? 'mile') : 'N/A',
                'location_type' => $locationType,
                'availability' => $availability,
                'features' => $features,
                'access_fees' => str_replace('_', ' and ', strtolower($atm['accessFees'] ?? 'unknown')),
                'owner' => $atm['owner'] ?? 'Unknown',
                'is_24_7' => ($atm['availability'] ?? '') === 'ALWAYS_AVAILABLE',
            ];
        }

        return json_encode([
            'success' => true,
            'message' => "Found {$count} ATM(s) {$searchInfo}",
            'search_info' => $searchInfo,
            'total_count' => (int) $total,
            'returned_count' => $count,
            'atms' => $formattedAtms,
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get search description
     */
    private function getSearchDescription(array $params): string
    {
        if (isset($params['search_name'])) {
            return "near {$params['search_name']}";
        }
        
        if (isset($params['PostalCode'])) {
            return "near postal code {$params['PostalCode']}, {$params['Country']}";
        }
        
        if (isset($params['Latitude'])) {
            return "near coordinates ({$params['Latitude']}, {$params['Longitude']})";
        }
        
        return "in the area";
    }

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()
                ->description('General location name (city, landmark, or place name)')
                ->nullable(),

            'city' => $schema->string()
                ->description('City name (e.g., "Vienna", "Paris", "New York")')
                ->nullable(),

            'destination_name' => $schema->string()
                ->description('Destination name from the tourism database')
                ->nullable(),

            'attraction_id' => $schema->integer()
                ->description('Attraction ID to find ATMs near that attraction')
                ->nullable(),

            'postal_code' => $schema->string()
                ->description('Postal/ZIP code for the search area')
                ->nullable(),

            'country' => $schema->string()
                ->description('Three-letter ISO country code (e.g., USA, GBR, AUT) - required with postal_code')
                ->nullable(),

            'latitude' => $schema->number()
                ->description('Latitude coordinate (use with longitude)')
                ->nullable(),

            'longitude' => $schema->number()
                ->description('Longitude coordinate (use with latitude)')
                ->nullable(),

            'distance' => $schema->number()
                ->description('Search radius distance (default: 5)')
                ->default(5)
                ->nullable(),

            'distance_unit' => $schema->string()
                ->description('Distance unit: MILE or KM (default: MILE)')
                ->default('MILE')
                ->nullable(),

            'limit' => $schema->integer()
                ->description('Maximum number of ATMs to return (default: 10, max: 50)')
                ->default(10)
                ->nullable(),
        ];
    }
}

