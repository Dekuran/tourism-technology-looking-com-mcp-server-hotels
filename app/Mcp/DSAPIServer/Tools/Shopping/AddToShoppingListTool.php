<?php

namespace App\Mcp\DSAPIServer\Tools\Shopping;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use App\Services\DSAPIService;
use Illuminate\JsonSchema\JsonSchema;

class AddToShoppingListTool extends Tool
{
    protected string $description = 'Add products to a Kärnten shopping list (cart). Requires a shopping_list_id from CreateDSAPIShoppingList. Supports adding multiple item types: experiences (add_service_items), accommodations (accommodation_items), brochures (brochure_items), packages (package_items), and tours (tour_items). Returns a checkout URL where users complete their booking and payment. This is the FINAL STEP in the booking process.';

    public function __construct(
        protected DSAPIService $dsapiService
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'shopping_list_id' => 'required|string',
            'add_service_items' => 'nullable|array',
            'accommodation_items' => 'nullable|array',
            'brochure_items' => 'nullable|array',
            'package_items' => 'nullable|array',
            'tour_items' => 'nullable|array',
        ]);

        $result = $this->dsapiService->addToShoppingList(
            $validated['shopping_list_id'],
            $validated['add_service_items'] ?? [],
            $validated['accommodation_items'] ?? [],
            $validated['brochure_items'] ?? [],
            $validated['package_items'] ?? [],
            $validated['tour_items'] ?? []
        );

        if (!$result['success']) {
            return Response::text('Failed to add items to shopping list: ' . ($result['error'] ?? 'Unknown error'));
        }

        $shoppingListId = $validated['shopping_list_id'];

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Items added to shopping list successfully',
            'shopping_list_id' => $shoppingListId,
            'checkout_url' => "https://work.schanitz.at/onlim/shoppingcart/?initcart=true&poscode=KTN&shoppinglist={$shoppingListId}",
            'added_items' => [
                'experiences' => count($validated['add_service_items'] ?? []),
                'accommodations' => count($validated['accommodation_items'] ?? []),
                'brochures' => count($validated['brochure_items'] ?? []),
                'packages' => count($validated['package_items'] ?? []),
                'tours' => count($validated['tour_items'] ?? []),
            ],
            'note' => 'Visit the checkout URL to complete your booking',
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'shopping_list_id' => $schema->string()
                ->description('Shopping list ID from CreateDSAPIShoppingList'),

            'add_service_items' => $schema->array()
                ->description('Array of experience/service items to add. Each item should include product details.')
                ->items($schema->object()),

            'accommodation_items' => $schema->array()
                ->description('Array of accommodation items to add')
                ->items($schema->object()),

            'brochure_items' => $schema->array()
                ->description('Array of brochure items to add')
                ->items($schema->object()),

            'package_items' => $schema->array()
                ->description('Array of package items to add')
                ->items($schema->object()),

            'tour_items' => $schema->array()
                ->description('Array of tour items to add')
                ->items($schema->object()),
        ];
    }
}

