<?php

namespace App\Mcp\DSAPIServer\Tools\Discovery;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use App\Services\DSAPIService;
use Illuminate\JsonSchema\JsonSchema;

class GetFilterOptionsTool extends Tool
{
    protected string $description = 'Get available filter options (types, themes, locations, guest cards) for Kärnten experiences. Use this to discover what categories and filters are available before searching or listing experiences. Returns all available experience types, holiday themes, locations, and guest card options that can be used for filtering.';

    public function __construct(
        protected DSAPIService $dsapiService
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'language' => 'nullable|string|in:de,en,it',
        ]);

        // Create an empty filter to get options
        $filterResult = $this->dsapiService->createFilter();
        
        if (!$filterResult['success']) {
            return Response::text('Failed to get filter options: ' . ($filterResult['error'] ?? 'Unknown error'));
        }

        $filterId = $filterResult['data']['id'] ?? null;
        
        // Get the options
        $result = $this->dsapiService->getFilterOptions(
            $filterId,
            $validated['language'] ?? 'de'
        );

        if (!$result['success']) {
            return Response::text('Failed to get filter options: ' . ($result['error'] ?? 'Unknown error'));
        }

        $data = $result['data'];

        return Response::text(json_encode([
            'success' => true,
            'filter_options' => [
                'types' => $data['types'] ?? [],
                'holiday_themes' => $data['holidayThemes'] ?? [],
                'locations' => $data['locations'] ?? [],
                'guest_cards' => $data['guestCards'] ?? [],
            ],
            'note' => 'Use these IDs when filtering experiences by types, themes, locations, or guest cards',
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->string()
                ->description('Language code: de, en, or it (default: de)')
                ->default('de'),
        ];
    }
}

