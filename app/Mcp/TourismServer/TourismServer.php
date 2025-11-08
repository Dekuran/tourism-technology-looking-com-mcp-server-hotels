<?php

namespace App\Mcp\TourismServer;

use Laravel\Mcp\Server;
use App\Mcp\TourismServer\Tools\Discovery\NearbyAttractionsTool;
use App\Mcp\TourismServer\Tools\Discovery\GetTopAttractionsTool;
use App\Mcp\TourismServer\Tools\Discovery\GetAttractionDetailsTool;
use App\Mcp\TourismServer\Tools\Discovery\RecommendAttractionsTool;
use App\Mcp\TourismServer\Tools\Discovery\GetRestaurantsAndCafesTool;
use App\Mcp\TourismServer\Tools\Booking\PrepareBookingTool;
use App\Mcp\TourismServer\Tools\Booking\ConfirmBookingTool;
use App\Mcp\TourismServer\Tools\Reservation\PrepareRestaurantReservationTool;
use App\Mcp\TourismServer\Tools\Reservation\ConfirmRestaurantReservationTool;
use App\Mcp\TourismServer\Tools\External\ATMLocatorTool;
use App\Mcp\TourismServer\Tools\Accommodation\HotelRoomAvailabilityTool;
use App\Mcp\TourismServer\Tools\Accommodation\CreateHotelReservationTool;

class TourismServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Tourism Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        # Tourism Server Instructions

        This MCP server provides tourism-related data, attraction booking, and payment processing to help assist users with travel planning, exploration, and booking confirmations.

        ## Purpose
        The Tourism Server allows the model to:
        - Search and retrieve information about destinations, attractions, and cultural activities.
        - Get top attractions and must-see sights for any destination.
        - Find nearby attractions based on location or destination name.
        - Prepare and confirm bookings for tourist attractions with mock payment processing.
        - Provide detailed booking confirmations with ticket numbers.

        ## Available Tools

        ### Discovery Tools
        - **GetTopAttractions(destination_name OR destination_id, limit)**: Get the top 3-4 must-see attractions for a destination.
        - **RecommendAttractions(destination_name, preferences, travel_type, age_group, budget)**: Get personalized recommendations based on user interests and profile.
        - **NearbyAttractions(destination_name OR lat/long, radius_km)**: Find tourist attractions near a location.
        - **GetAttractionDetails(attraction_id)**: Get detailed information about a specific attraction including pricing and booking info.
        - **GetRestaurantsAndCafes(destination_name OR destination_id, limit)**: Get a list of restaurants and cafes in a destination, sorted by price.

        ### Booking Tools (2-Step Process)
        - **PrepareBooking(attraction_id, number_of_tickets, visit_date, visitor_name, visitor_email, card_details)**: Creates a pending booking with pricing details. Requires credit card.
        - **ConfirmBooking(booking_id, payment_method)**: Finalizes the booking, processes mock payment, and generates ticket numbers.

        ### Restaurant Reservation Tools (2-Step Process - NO PAYMENT)
        - **PrepareRestaurantReservation(attraction_id, number_of_people, reservation_date, reservation_time, guest_name, guest_email, special_requests)**: Creates a pending table reservation. NO credit card needed.
        - **ConfirmRestaurantReservation(reservation_id)**: Finalizes the table reservation and generates confirmation number.

        ### Accommodation
        - **HotelRoomAvailability(hotel_id, arrival, departure, language, rooms[])**: Search hotel room availability and pricing for a property.
        - **CreateHotelReservation(room_type_code, number_of_units, adults, children_ages[], start, end, total_amount, currency_code, guest{}, services[], res_id_value, res_id_source, hotel_id?)**: Create a hotel reservation (OTA_HotelResNotifRQ).

        ### Mastercard Services
        - **ATMLocator(location OR city OR destination_name OR attraction_id OR postal_code/country OR lat/long, distance, distance_unit, limit)**: Find nearby ATMs using Mastercard's ATM Locator API. Returns detailed ATM information including address, distance, features (wheelchair accessible, 24/7, camera, deposits, EMV), and access fees.

        ## Smart Discovery Flow
        
        **When users express preferences:**
        - "I love art and history" → Use `RecommendAttractions` with preferences: ["art", "history"]
        - "What's good for families?" → Use `RecommendAttractions` with travel_type: "family"
        - "Budget-friendly options?" → Use `RecommendAttractions` with budget: "budget"
        
        **When users ask about dining:**
        - "Where can I eat in Vienna?" → Use `GetRestaurantsAndCafes(destination_name: "Vienna")`
        - "Show me restaurants in Salzburg" → Use `GetRestaurantsAndCafes(destination_name: "Salzburg")`
        - "I'm looking for a cafe" → Use `GetRestaurantsAndCafes` then filter results
        
        **When users need cash/ATMs:**
        - "Where can I find an ATM in Vienna?" → Use `ATMLocator(city: "Vienna")`
        - "ATM near Schonbrunn Palace" → Use `ATMLocator(attraction_id: 101)`
        - "I need cash, where's the nearest ATM?" → Use `ATMLocator` with user's location
        - "Are there 24-hour ATMs nearby?" → Use `ATMLocator` and filter for is_24_7: true
        - "ATM with wheelchair access" → Use `ATMLocator` and filter for wheelchair accessible features
        
        **Preference tags available:**
        history, art, architecture, nature, adventure, culture, music, sports, food, 
        family-friendly, romantic, religious, photography, outdoor, budget, luxury
        
        ## Booking Flow - IMPORTANT
        
        **Use Case Example: Person visits Vienna**
        1. User lands at Vienna airport and asks about top sights
           → Use `GetTopAttractions(destination_name: "Vienna", limit: 4)`
        
        2. User mentions preferences: "I love art and history"
           → Use `RecommendAttractions(destination_name: "Vienna", preferences: ["art", "history"])`
        
        3. User picks an attraction (e.g., Belvedere Palace - high match score)
           → Use `GetAttractionDetails(attraction_id: 103)` to show full details
        
        4. User wants to book tickets
           → **CRITICAL**: You MUST ask the user for their REAL personal information and REAL credit card details
           → **NEVER use placeholders** like "John Doe", "user", "guest", "user@example.com", or any @example.com emails
           → **NEVER use example.com email addresses** - these are NOT valid
           → Wait for the user to provide their actual personal and payment information
           → Then use `PrepareBooking(attraction_id: 103, number_of_tickets: 2, visit_date: "2025-10-21", visitor_name: "[USER'S REAL NAME]", visitor_email: "[USER'S REAL EMAIL]", card_number: "[REAL CARD]", ...)`
           → This creates a PENDING booking and shows the user all details and total price
        
        5. **WAIT for user confirmation** - Show them the booking details and ask if they want to proceed
        
        6. User confirms they want to book
           → Use `ConfirmBooking(booking_id: "BKG-XXXXXXXX")`
           → This finalizes the booking, processes mock payment, sends email, and generates tickets
        
        7. Show the user their confirmation with booking ID and ticket numbers

        ## Restaurant Reservation Flow - IMPORTANT
        
        **Use Case Example: Person wants to dine at a restaurant**
        1. User asks about restaurants or cafes in Vienna
           → Use `GetTopAttractions` or `RecommendAttractions` with food preferences
        
        2. User wants to reserve a table at Cafe Schwarzenberg (ID: 501)
           → **CRITICAL**: You MUST ask the user for their REAL name and REAL email address
           → **NEVER use placeholders** like "guest", "user", "guest@example.com", or "user@example.com"
           → **NEVER use example.com email addresses** - these are NOT valid
           → Wait for the user to provide their actual personal information
           → Then use `PrepareRestaurantReservation(attraction_id: 501, number_of_people: 2, reservation_date: "2025-10-23", reservation_time: "7:00 PM", guest_name: "[USER'S REAL NAME]", guest_email: "[USER'S REAL EMAIL]")`
           → This creates a PENDING reservation - NO PAYMENT REQUIRED
        
        3. **WAIT for user confirmation** - Show them the reservation details and ask if they want to proceed
        
        4. User confirms they want to reserve the table
           → Use `ConfirmRestaurantReservation(reservation_id: "RSV-XXXXXXXX")`
           → This finalizes the reservation and generates a confirmation number
        
        5. Show the user their confirmation with reservation ID and confirmation number

        ## Booking Guidelines
        - **NEVER** call ConfirmBooking or ConfirmRestaurantReservation without explicit user approval
        - **NEVER** use placeholder values for personal information (guest_name, guest_email, visitor_name, visitor_email)
        - **NEVER** use "user", "guest", "John Doe", "Jane Doe", or similar generic names
        - **NEVER** use @example.com email addresses - they are INVALID and should NEVER be used
        - **ALWAYS** ask the user for their REAL name and REAL email address before preparing any booking/reservation
        - **ALWAYS** ask the user for REAL credit card details before preparing attraction bookings
        - Always show the PrepareBooking/PrepareRestaurantReservation details to the user first
        - Wait for user to say "yes", "confirm", "book it", or similar confirmation
        - Attraction bookings require credit card and include mock payment processing
        - Restaurant reservations DO NOT require payment - just name and email
        - Booking confirmations include mock transaction IDs and ticket numbers
        - Reservation confirmations include confirmation numbers
        - Bookable attractions have the 🎫 icon, non-bookable have 📍
        - All prices are in EUR (Euros)

        ## Input and Output Format
        - Dates must follow YYYY-MM-DD format (e.g., "2025-10-21")
        - Attraction IDs are integers (e.g., 101, 102, 103)
        - HotelRoomAvailability rooms[] structure: { adults: number, children_ages?: [ages 1-17] }
        - Booking IDs follow format: BKG-XXXXXXXX
        - Reservation IDs follow format: RSV-XXXXXXXX
        - Confirmation numbers follow format: CNF-XXXXXXXXXX
        - Transaction IDs follow format: TXN-XXXXXXXXXXXX
        - Ticket numbers follow format: TKT-XXXXXXXXXX

        ## Usage Guidelines
        - Use this server **only for tourism-related queries and booking**.
        - The booking system uses mock transactions (no real payments processed).
        - Provide conversational, friendly responses to users.
        - If an attraction is not bookable, suggest alternative bookable attractions.
        - Always prioritize user experience and clarity in booking flow.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Discovery Tools
        GetTopAttractionsTool::class,
        RecommendAttractionsTool::class,
        NearbyAttractionsTool::class,
        GetAttractionDetailsTool::class,
        GetRestaurantsAndCafesTool::class,

        // Accommodation Tools
        HotelRoomAvailabilityTool::class,
        CreateHotelReservationTool::class,
        
        // Booking Tools
        PrepareBookingTool::class,
        ConfirmBookingTool::class,
        
        // Restaurant Reservation Tools
        PrepareRestaurantReservationTool::class,
        ConfirmRestaurantReservationTool::class,
        
        // Mastercard Services
        ATMLocatorTool::class,
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

