<?php

namespace App\Mcp\DSAPIServer\Tools\Products;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use App\Services\DSAPIService;
use Illuminate\JsonSchema\JsonSchema;

class GetProductAvailabilityTool extends Tool
{
    protected string $description = 'Get detailed availability schedule for products of a Kärnten experience within a specific date range. Requires spIdentity, serviceId, date_from, and date_to. Returns comprehensive availability information including specific booking dates, time slots, real-time pricing, available capacity/slots, booking deadlines, and cancellation policies. Essential for showing users exactly when they can book an experience.';

    public function __construct(
        protected DSAPIService $dsapiService
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sp_identity' => 'required|string',
            'service_id' => 'required|string',
            'date_from' => 'required|string',
            'date_to' => 'required|string',
            'language' => 'nullable|string|in:de,en,it',
            'currency' => 'nullable|string',
        ]);

        // Format dates
        $dateFrom = $this->formatDate($validated['date_from']);
        $dateTo = $this->formatDate($validated['date_to']);

        // Create search object internally
        $searchResult = $this->dsapiService->createSearch($dateFrom, $dateTo);
        
        if (!$searchResult['success']) {
            return Response::text('Failed to create search: ' . ($searchResult['error'] ?? 'Unknown error'));
        }

        $searchId = $searchResult['data']['id'] ?? null;

        // Create empty filter internally (required by API)
        $filterResult = $this->dsapiService->createFilter();
        
        if (!$filterResult['success']) {
            return Response::text('Failed to create filter: ' . ($filterResult['error'] ?? 'Unknown error'));
        }

        $filterId = $filterResult['data']['id'] ?? null;

        // Get availability
        $result = $this->dsapiService->getProductAvailability(
            $validated['sp_identity'],
            $validated['service_id'],
            $searchId,
            $filterId,
            $validated['language'] ?? 'de',
            $validated['currency'] ?? 'EUR'
        );

        if (!$result['success']) {
            return Response::text('Failed to get product availability: ' . ($result['error'] ?? 'Unknown error'));
        }

        return Response::text(json_encode([
            'success' => true,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'availability' => $result['data'],
            'note' => 'Each product includes bookInfo array with dates, times, prices, availability counts, and cancellation policies',
        ], JSON_PRETTY_PRINT));
    }

    private function formatDate(string $date): string
    {
        if (strlen($date) === 10) {
            return $date . 'T00:00:00.000';
        }
        return $date;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sp_identity' => $schema->string()
                ->description('Service provider identity from experience listing'),

            'service_id' => $schema->string()
                ->description('Service ID from experience listing'),

            'date_from' => $schema->string()
                ->description('Start date in ISO format (e.g., 2025-11-01 or 2025-11-01T00:00:00.000)'),

            'date_to' => $schema->string()
                ->description('End date in ISO format (e.g., 2025-11-10 or 2025-11-10T00:00:00.000)'),

            'language' => $schema->string()
                ->description('Language code: de, en, or it (default: de)')
                ->default('de'),

            'currency' => $schema->string()
                ->description('Currency code (default: EUR)')
                ->default('EUR'),
        ];
    }
}

