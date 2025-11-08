<?php

namespace App\Mcp\DSAPIServer\Tools\Discovery;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use App\Services\DSAPIService;
use Illuminate\JsonSchema\JsonSchema;

class SearchExperiencesTool extends Tool
{
    protected string $description = 'Search Kärnten experiences available in a specific date range with optional filtering. Returns ONLY experiences that have confirmed availability during the specified period (date_from to date_to). Use this when users specify travel dates or want to see what\'s available on specific dates. Can also filter by types, locations, themes, and guest cards.';

    public function __construct(
        protected DSAPIService $dsapiService
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => 'required|string',
            'date_to' => 'required|string',
            'types' => 'nullable|array',
            'types.*' => 'string',
            'locations' => 'nullable|array',
            'locations.*' => 'string',
            'holiday_themes' => 'nullable|array',
            'holiday_themes.*' => 'string',
            'guest_cards' => 'nullable|array',
            'guest_cards.*' => 'string',
            'name' => 'nullable|string',
            'language' => 'nullable|string|in:de,en,it',
            'currency' => 'nullable|string',
            'page_number' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:10000',
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

        // Create filter internally
        $filterResult = $this->dsapiService->createFilter(
            $validated['types'] ?? null,
            $validated['locations'] ?? null,
            $validated['holiday_themes'] ?? null,
            $validated['guest_cards'] ?? null,
            $validated['name'] ?? ''
        );

        if (!$filterResult['success']) {
            return Response::text('Failed to create filter: ' . ($filterResult['error'] ?? 'Unknown error'));
        }

        $filterId = $filterResult['data']['id'] ?? null;

        // Search experiences
        $result = $this->dsapiService->searchExperiences(
            $searchId,
            $filterId,
            $validated['language'] ?? 'de',
            $validated['currency'] ?? 'EUR',
            $validated['page_number'] ?? 1,
            $validated['page_size'] ?? 5000
        );

        if (!$result['success']) {
            return Response::text('Failed to search experiences: ' . ($result['error'] ?? 'Unknown error'));
        }

        return Response::text(json_encode([
            'success' => true,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'experiences' => $result['data'],
            'note' => 'Extract spIdentity and serviceId from experiences to fetch products or availability',
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
            'date_from' => $schema->string()
                ->description('Start date in ISO format (e.g., 2025-11-01 or 2025-11-01T00:00:00.000)'),

            'date_to' => $schema->string()
                ->description('End date in ISO format (e.g., 2025-11-10 or 2025-11-10T00:00:00.000)'),

            'types' => $schema->array()
                ->description('Array of experience type IDs (UUIDs) to filter by')
                ->items($schema->string()),

            'locations' => $schema->array()
                ->description('Array of location IDs to filter by')
                ->items($schema->string()),

            'holiday_themes' => $schema->array()
                ->description('Array of holiday theme IDs to filter by')
                ->items($schema->string()),

            'guest_cards' => $schema->array()
                ->description('Array of guest card IDs to filter by')
                ->items($schema->string()),

            'name' => $schema->string()
                ->description('Search experiences by name (partial match)'),

            'language' => $schema->string()
                ->description('Language code: de, en, or it (default: de)')
                ->default('de'),

            'currency' => $schema->string()
                ->description('Currency code (default: EUR)')
                ->default('EUR'),

            'page_number' => $schema->integer()
                ->description('Page number for pagination (default: 1)')
                ->default(1),

            'page_size' => $schema->integer()
                ->description('Number of results per page (default: 5000, max: 10000)')
                ->default(5000),
        ];
    }
}

