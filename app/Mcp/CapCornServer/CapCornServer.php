<?php

namespace App\Mcp\CapCornServer;

use Laravel\Mcp\Server;
use App\Mcp\CapCornServer\Tools\SearchRoomsTool;
use App\Mcp\CapCornServer\Tools\SearchRoomAvailabilityTool;
use App\Mcp\CapCornServer\Tools\CreateReservationTool;

class CapCornServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'CapCorn Hotel API';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.1.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        # CapCorn Hotel API Server Instructions

        This MCP server provides access to the CapCorn Hotel API for searching room availability and creating hotel reservations.

        ## Purpose
        The CapCorn Server allows the model to:
        - Search for available rooms with flexible duration within a timespan
        - Search direct room availability for specific dates
        - Create hotel reservations with guest information and services

        ## Available Tools

        ### 1. SearchRooms
        Search for available rooms with flexible duration within a timespan. This endpoint generates all possible date ranges for the specified duration and searches them in parallel.

        **Use when:** User wants to find rooms for a flexible stay duration within a date range.
        
        **Parameters:**
        - `language`: "de" for German, "en" for English (default: "de")
        - `timespan`: Date range to search within (from/to dates)
        - `duration`: Length of stay in days (must be ≤ timespan)
        - `adults`: Number of adults (minimum 1)
        - `children`: List of children with their ages (1-17, max 8 children)

        **Example:** If timespan is 7 days and duration is 4 days, 4 parallel queries will be made covering all possible 4-day stays.

        ### 2. SearchRoomAvailability
        Direct room availability search for specific check-in/check-out dates.

        **Use when:** User knows exact arrival and departure dates.
        
        **Parameters:**
        - `language`: 0 for German, 1 for English (default: 0)
        - `hotel_id`: CapCorn Hotel ID
        - `arrival`: Check-in date (YYYY-MM-DD)
        - `departure`: Check-out date (YYYY-MM-DD)
        - `rooms`: List of rooms with adults and children (max 10 rooms)

        ### 3. CreateReservation
        Create a new hotel reservation with guest information and optional services.

        **Use when:** User wants to book a room after finding availability.
        
        **Required Parameters:**
        - `hotel_id`: CapCorn Hotel ID
        - `room_type_code`: Room category code from availability search (max 8 chars)
        - `meal_plan`: Included meals (1=Breakfast, 2=Half board, 3=Full board, 4=No meals, 5=All inclusive)
        - `guest_counts`: Adults and children counts with age qualifying codes
        - `arrival`: Check-in date
        - `departure`: Check-out date
        - `total_amount`: Total price in EUR
        - `guest`: Guest personal information (name, contact, address)
        - `reservation_id`: Unique booking ID from your system

        **Optional Parameters:**
        - `number_of_units`: Number of rooms to book (default: 1)
        - `services`: Additional services with name, quantity, and price
        - `booking_comment`: Special requests (max 200 chars)
        - `source`: Source/channel name (default: "Hackathon")

        ## Meal Plan Codes
        - 1 = Breakfast only
        - 2 = Half board (breakfast + dinner)
        - 3 = Full board (all meals)
        - 4 = No meals included
        - 5 = All inclusive

        ## Room Types
        - 1 = Hotel room
        - 2 = Apartment/Holiday home

        ## Guest Count Age Qualifying Codes
        - 10 = Adults
        - 8 = Children (must include age 1-17)

        ## Workflow Examples

        **Flexible Search:**
        ```
        User: "Find me a room for 4 nights anytime between Nov 15-25"
        → Use SearchRooms with timespan (Nov 15 - Nov 25) and duration: 4
        → Returns all possible 4-night stays within that period
        ```

        **Direct Availability:**
        ```
        User: "Check availability for Nov 20-23 for 2 adults and 1 child (age 8)"
        → Use SearchRoomAvailability with exact dates
        → Returns available rooms for that specific period
        ```

        **Complete Booking:**
        ```
        User finds a room and wants to book
        → Get room_type_code from search results
        → Collect guest information
        → Use CreateReservation with all details
        → Returns success confirmation or errors
        ```

        ## Data Format Guidelines
        - Dates: YYYY-MM-DD format (e.g., "2025-11-20")
        - Prices: Decimal numbers (e.g., 150.00)
        - Languages: "de" or "en" for SearchRooms, 0 or 1 for SearchRoomAvailability
        - Children ages: Integer 1-17

        ## Error Handling
        - Always validate dates (arrival before departure)
        - Ensure duration ≤ timespan for flexible search
        - Verify all required guest information before reservation
        - Check that guest counts match actual guests
        - Handle API errors gracefully and inform user

        ## Best Practices
        1. Use SearchRooms for flexible date searching
        2. Use SearchRoomAvailability for exact date queries
        3. Always show price breakdowns (total, per person, per night)
        4. Explain meal plan options to users
        5. Confirm all details before creating reservation
        6. Generate unique reservation_id for each booking
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        SearchRoomsTool::class,
        SearchRoomAvailabilityTool::class,
        CreateReservationTool::class,
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
