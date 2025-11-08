<?php

namespace App\Mcp\DSAPIServer\Tools\Discovery;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use App\Services\DSAPIService;
use Illuminate\JsonSchema\JsonSchema;

class ListExperiencesTool extends Tool
{
    protected string $description = 'List all available Kärnten experiences (AddServices) with optional filtering. Use this when you do NOT need date-specific availability - it returns all experiences regardless of dates. You can filter by experience types, locations, holiday themes, guest cards, or search by name. Perfect for browsing the complete catalog of experiences.';

    public function __construct(
        protected DSAPIService $dsapiService
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
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
            'page_number' => 'nullable|integer|min:0',
            'page_size' => 'nullable|integer|min:1|max:10000',
        ]);

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

        // List experiences with the filter
        $result = $this->dsapiService->listExperiences(
            $filterId,
            $validated['language'] ?? 'de',
            $validated['currency'] ?? 'EUR',
            $validated['page_number'] ?? 0,
            $validated['page_size'] ?? 5000
        );

        if (!$result['success']) {
            return Response::text('Failed to list experiences: ' . ($result['error'] ?? 'Unknown error'));
        }

        return Response::text(json_encode([
            'success' => true,
            'experiences' => $result['data'],
            'note' => 'Extract spIdentity and serviceId from experiences to fetch products or availability',
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
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
                ->description('Page number for pagination (default: 0)')
                ->default(0),

            'page_size' => $schema->integer()
                ->description('Number of results per page (default: 5000, max: 10000)')
                ->default(5000),
        ];
    }
}

