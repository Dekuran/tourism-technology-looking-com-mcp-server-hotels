<?php

namespace App\Mcp\DSAPIServer;

use Laravel\Mcp\Server;
use App\Mcp\DSAPIServer\Tools\Discovery\GetFilterOptionsTool;
use App\Mcp\DSAPIServer\Tools\Discovery\ListExperiencesTool;
use App\Mcp\DSAPIServer\Tools\Discovery\SearchExperiencesTool;
use App\Mcp\DSAPIServer\Tools\Products\GetServiceProductsTool;
use App\Mcp\DSAPIServer\Tools\Products\GetProductAvailabilityTool;
use App\Mcp\DSAPIServer\Tools\Shopping\CreateShoppingListTool;
use App\Mcp\DSAPIServer\Tools\Shopping\AddToShoppingListTool;

class DSAPIServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'DSAPI Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        # DSAPI Server Instructions

        This MCP server provides access to the Deskline Solutions API (DSAPI) for Kärnten (Carinthia), Austria. It offers real bookable experiences including activities, tours, and regional attractions with a complete shopping cart and booking system.

        ## Purpose
        The DSAPI Server allows the model to:
        - Discover experiences and activities available in Kärnten/Carinthia region
        - Filter experiences by type, location, themes, and guest cards
        - Search for experiences available in specific date ranges
        - Get detailed product information and availability schedules
        - Create shopping lists (carts) and add experiences for checkout
        - Authentication is handled automatically using configured credentials

        ## Available Tools

        ### Discovery Tools
        - **GetDSAPIFilterOptions(language)**: Get available filter options (types, themes, locations, guest cards) for Kärnten experiences. Use this to discover what categories and filters are available.
        - **ListDSAPIExperiences(types[], locations[], holiday_themes[], guest_cards[], name, language, currency, page_number, page_size)**: List experiences with optional filtering. Use this when you do not need date-specific availability. You can filter by types, locations, holiday themes, guest cards, or name.
        - **SearchDSAPIExperiences(date_from, date_to, types[], locations[], holiday_themes[], guest_cards[], name, language, currency, page_number, page_size)**: Search experiences available in a specific date range with optional filtering. Returns only experiences that have availability in the specified period.
        - **GetDSAPIServiceProducts(sp_identity, service_id, language, currency)**: Get bookable products for a specific Kärnten experience/service. Products are the concrete offerings you can add to a shopping list.
        - **GetDSAPIProductAvailability(sp_identity, service_id, date_from, date_to, language, currency)**: Get detailed availability schedule for products of a Kärnten experience, including booking dates, times, prices, available slots, and cancellation policies for a specific date range.

        ### Shopping & Booking Tools
        - **CreateDSAPIShoppingList()**: Create a new shopping list (cart) for Kärnten experiences. This is required before adding products.
        - **AddToDSAPIShoppingList(shopping_list_id, add_service_items[], accommodation_items[], brochure_items[], package_items[], tour_items[])**: Add products to a Kärnten shopping list. Supports experiences, accommodations, brochures, packages, and tours. Returns checkout URL for completing the booking.

        ## Common Use Cases

        **When users want to book Kärnten experiences:**
        - "What experiences are available in Kärnten?" → `ListDSAPIExperiences()` with no filters
        - "Show me culture experiences" → `ListDSAPIExperiences(types: ["culture-type-id"])`
        - "Show me activities in Carinthia in November" → `SearchDSAPIExperiences(date_from: "2025-11-01", date_to: "2025-11-30")`
        - "Find adventure activities for families in November" → `SearchDSAPIExperiences(date_from, date_to, types: ["adventure"], holiday_themes: ["family"])`
        - "Book an alpaca hiking tour" → Search/list experiences, get availability, create shopping list, add to cart
        - "What types of experiences are available?" → Use `GetDSAPIFilterOptions()` to see all available categories
        - Note: Authentication, search, and filter objects are handled automatically in the background

        ## Typical Booking Flow

        1. **Discover Experiences**
           - Use `ListDSAPIExperiences()` to browse all available experiences
           - OR use `SearchDSAPIExperiences(date_from, date_to)` to find experiences available on specific dates
           - Optionally filter by types, locations, themes, or guest cards

        2. **Get Product Details**
           - Extract `spIdentity` and `serviceId` from the experience
           - Use `GetDSAPIServiceProducts(sp_identity, service_id)` to see available products
           - OR use `GetDSAPIProductAvailability(sp_identity, service_id, date_from, date_to)` for detailed availability

        3. **Create Shopping List**
           - Use `CreateDSAPIShoppingList()` to create a cart
           - Get the `shopping_list_id` from the response

        4. **Add Items to Cart**
           - Use `AddToDSAPIShoppingList(shopping_list_id, add_service_items: [...])` to add experiences
           - The response includes a checkout URL
           - Direct the user to visit the checkout URL to complete their booking

        ## Input and Output Format
        - Date format: `YYYY-MM-DD` or `YYYY-MM-DDTHH:MM:SS.mmm` (e.g., "2025-11-01" or "2025-11-01T00:00:00.000")
        - Language codes: `de` (German), `en` (English), `it` (Italian) - default: `de`
        - Currency: `EUR` (default)
        - spIdentity: Service provider identity string from experience listing
        - serviceId: Service ID string from experience listing
        - All IDs (types, locations, themes, guest_cards) are UUID strings

        ## Important Notes
        - **Authentication**: Completely automatic - handled internally by the service layer
        - **Search/Filter Objects**: Created automatically when needed - no manual management required
        - **Checkout**: The final booking is completed on the DSAPI checkout page (external URL)
        - **Pagination**: Default page size is 5000, maximum is 10000
        - **Caching**: Authentication tokens are cached for 8 hours

        ## Error Handling
        - If authentication fails, check DSAPI credentials in environment variables
        - If experiences not found, try without filters or adjust date range
        - If products unavailable, the experience may not be bookable or dates are incorrect
        - Session IDs are managed automatically - no manual handling needed

        ## Usage Guidelines
        - Use this server **only for Kärnten/Carinthia regional experiences**
        - For other Austrian regions (Vienna, Salzburg, Innsbruck), use the Tourism Server
        - The DSAPI provides real bookable experiences from real providers
        - Always provide the checkout URL to users for completing their bookings
        - Experiences are displayed in German by default; specify language parameter if needed
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Discovery Tools
        GetFilterOptionsTool::class,
        ListExperiencesTool::class,
        SearchExperiencesTool::class,
        GetServiceProductsTool::class,
        GetProductAvailabilityTool::class,
        
        // Shopping & Booking Tools
        CreateShoppingListTool::class,
        AddToShoppingListTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}

