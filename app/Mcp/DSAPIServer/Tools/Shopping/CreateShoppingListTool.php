<?php

namespace App\Mcp\DSAPIServer\Tools\Shopping;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use App\Services\DSAPIService;
use Illuminate\JsonSchema\JsonSchema;

class CreateShoppingListTool extends Tool
{
    protected string $description = 'Create a new shopping list (cart) for Kärnten experiences. This is the FIRST STEP before adding any products to book. Takes no parameters and returns a shopping_list_id that must be used when adding items. Each user session should have one shopping list.';

    public function __construct(
        protected DSAPIService $dsapiService
    ) {}

    public function handle(Request $request): Response
    {
        $result = $this->dsapiService->createShoppingList();

        if (!$result['success']) {
            return Response::text('Failed to create shopping list: ' . ($result['error'] ?? 'Unknown error'));
        }

        $shoppingListId = $result['data']['id'] ?? $result['data'];

        return Response::text(json_encode([
            'success' => true,
            'message' => 'Shopping list created successfully',
            'shopping_list_id' => $shoppingListId,
            'checkout_url' => "https://work.schanitz.at/onlim/shoppingcart/?initcart=true&poscode=KTN&shoppinglist={$shoppingListId}",
            'note' => 'Save this shopping_list_id to add products. Use the checkout URL to complete the booking.',
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

