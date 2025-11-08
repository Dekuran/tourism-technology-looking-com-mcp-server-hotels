<?php

namespace App\Mcp\DSAPIServer\Tools\Products;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use App\Services\DSAPIService;
use Illuminate\JsonSchema\JsonSchema;

class GetServiceProductsTool extends Tool
{
    protected string $description = 'Get bookable products for a specific Kärnten experience/service. Requires spIdentity and serviceId from an experience listing. Returns concrete product offerings (tickets, packages, variants) with pricing that can be added to a shopping list. Use this to see what specific products are available for an experience.';

    public function __construct(
        protected DSAPIService $dsapiService
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sp_identity' => 'required|string',
            'service_id' => 'required|string',
            'language' => 'nullable|string|in:de,en,it',
            'currency' => 'nullable|string',
        ]);

        // Create empty filter internally (required by API)
        $filterResult = $this->dsapiService->createFilter();
        
        if (!$filterResult['success']) {
            return Response::text('Failed to create filter: ' . ($filterResult['error'] ?? 'Unknown error'));
        }

        $filterId = $filterResult['data']['id'] ?? null;

        // Get products
        $result = $this->dsapiService->getServiceProducts(
            $validated['sp_identity'],
            $validated['service_id'],
            $filterId,
            $validated['language'] ?? 'de',
            $validated['currency'] ?? 'EUR'
        );

        if (!$result['success']) {
            return Response::text('Failed to get service products: ' . ($result['error'] ?? 'Unknown error'));
        }

        return Response::text(json_encode([
            'success' => true,
            'products' => $result['data'],
            'note' => 'Use product IDs to add items to shopping list or check availability',
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sp_identity' => $schema->string()
                ->description('Service provider identity from experience listing'),

            'service_id' => $schema->string()
                ->description('Service ID from experience listing'),

            'language' => $schema->string()
                ->description('Language code: de, en, or it (default: de)')
                ->default('de'),

            'currency' => $schema->string()
                ->description('Currency code (default: EUR)')
                ->default('EUR'),
        ];
    }
}

