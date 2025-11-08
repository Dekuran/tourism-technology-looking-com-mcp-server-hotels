# CapCorn Hotel API - MCP Server Contract

## Overview

This MCP server provides access to the CapCorn Hotel API for room search and reservation management.

**Base URL:** https://lookingcom-backend.vercel.app  
**API Version:** 0.1.0

---

## 🔧 Configuration

Add to `config/services.php`:

```php
'capcorn' => [
    'base_url' => env('CAPCORN_BASE_URL', 'https://lookingcom-backend.vercel.app'),
],
```

Add to `.env`:
```
CAPCORN_BASE_URL=https://lookingcom-backend.vercel.app
```

---

## 📚 Available Tools (3 Total)

### 1. SearchRooms - Flexible Duration Search

**API Endpoint:** `POST /api/v1/rooms/search`

**Purpose:** Search for rooms with flexible duration within a date range. Generates all possible date combinations automatically.

**When to use:** User wants flexibility in dates (e.g., "4 nights anytime between Nov 15-25")

**Parameters:**
```php
[
    'language' => 'de',              // 'de' or 'en' (default: 'de')
    'timespan' => [
        'from' => '2025-11-15',      // Start of search period
        'to' => '2025-11-25'         // End of search period
    ],
    'duration' => 4,                 // Length of stay in days
    'adults' => 2,                   // Number of adults (min: 1)
    'children' => [                  // Optional, max 8 children
        ['age' => 8],
        ['age' => 5]
    ]
]
```

**Response:**
```json
{
    "total_queries": 7,
    "total_options": 42,
    "duration_days": 4,
    "options": [
        {
            "arrival": "2025-11-15",
            "departure": "2025-11-19",
            "catc": "DZ",
            "type": "Double Room",
            "description": "Cozy double room with mountain view",
            "size": 25,
            "price": 480.00,
            "price_per_night": 120.00,
            "price_per_person": 240.00,
            "price_per_adult": 240.00,
            "board": 1,
            "room_type": 1
        }
    ]
}
```

**Example Prompts:**
```
1. "Find a room for 3 nights anytime between December 1-10"
2. "Search for 5-night stays in the first two weeks of November"
3. "I need a room for 2 nights sometime next week"
4. "Show me 4-night options between Nov 20-30 for 2 adults and 1 child (age 7)"
```

---

### 2. SearchRoomAvailability - Direct Date Search

**API Endpoint:** `POST /api/v1/rooms/availability`

**Purpose:** Check availability for specific check-in/check-out dates.

**When to use:** User knows exact dates (e.g., "Check-in Nov 20, check-out Nov 23")

**Parameters:**
```php
[
    'language' => 0,                 // 0=German, 1=English (default: 0)
    'arrival' => '2025-11-20',       // Check-in date
    'departure' => '2025-11-23',     // Check-out date
    'rooms' => [                     // Can request multiple rooms (max 10)
        [
            'adults' => 2,
            'children' => [
                ['age' => 8]
            ]
        ],
        [
            'adults' => 2,
            'children' => []
        ]
    ]
]
```

**Response:**
```json
{
    "members": [
        {
            "rooms": [
                {
                    "arrival": "2025-11-20",
                    "departure": "2025-11-23",
                    "adults": 2,
                    "children": [{"age": 8}],
                    "options": [
                        {
                            "catc": "DZ",
                            "type": "Double Room",
                            "description": "Cozy double room",
                            "size": 25,
                            "price": 360.00,
                            "price_per_night": 120.00,
                            "price_per_person": 180.00,
                            "price_per_adult": 180.00,
                            "board": 1,
                            "room_type": 1
                        }
                    ]
                }
            ]
        }
    ]
}
```

**Example Prompts:**
```
1. "Check availability for Nov 20-23 at hotel 12345"
2. "Search rooms for Dec 1-5 for 2 adults"
3. "I need 2 rooms from Nov 15-20: one for 2 adults, one for 2 adults + 2 kids (ages 8 and 5)"
4. "Check if there are rooms available for next weekend"
```

---

### 3. CreateReservation - Book a Room

**API Endpoint:** `POST /api/v1/reservations`

**Purpose:** Create a hotel reservation with guest information.

**When to use:** After finding a room, user wants to book it.

**Parameters:**
```php
[
    'room_type_code' => 'DZ',        // From search results (catc)
    'number_of_units' => 1,          // Number of rooms (default: 1)
    'meal_plan' => 1,                // 1=Breakfast, 2=Half board, 3=Full board, 4=No meals, 5=All inclusive
    'guest_counts' => [
        [
            'age_qualifying_code' => 10,  // 10=Adults
            'count' => 2
        ],
        [
            'age_qualifying_code' => 8,   // 8=Children
            'count' => 1,
            'age' => 8                    // Required for children
        ]
    ],
    'arrival' => '2025-11-20',
    'departure' => '2025-11-23',
    'total_amount' => 360.00,
    'guest' => [
        'name_prefix' => 'Mr',
        'given_name' => 'John',
        'surname' => 'Smith',
        'phone_number' => '+1234567890',
        'email' => 'john.smith@example.com',
        'address' => [
            'address_line' => '123 Main St',
            'city_name' => 'New York',
            'postal_code' => '10001',
            'country_code' => 'US'
        ]
    ],
    'services' => [                  // Optional
        [
            'name' => 'Airport Transfer',
            'quantity' => 1,
            'amount_after_tax' => 50.00
        ]
    ],
    'booking_comment' => 'Late check-in requested',  // Optional
    'reservation_id' => 'BOOK-2025-001',  // Unique ID from your system
    'source' => 'Hackathon'          // Optional (default: "Hackathon")
]
```

**Response:**
```json
{
    "success": true,
    "message": "Reservation created successfully",
    "reservation_id": "BOOK-2025-001",
    "errors": null
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Reservation failed",
    "reservation_id": null,
    "errors": [
        "Room no longer available",
        "Invalid guest information"
    ]
}
```

**Example Prompts:**
```
1. "Book room DZ for Nov 20-23 at hotel 12345"
2. "Create a reservation for the double room I just found"
3. "Book it for 2 adults, include breakfast"
4. "Make a reservation with guest name John Smith, email john@example.com"
```

---

## 📊 Data Reference

### Meal Plan Codes
- `1` = Breakfast only
- `2` = Half Board (breakfast + dinner)
- `3` = Full Board (all meals)
- `4` = No meals included
- `5` = All Inclusive

### Room Types
- `1` = Hotel room
- `2` = Apartment/Holiday home

### Age Qualifying Codes
- `10` = Adults
- `8` = Children (must include age 1-17)

### Languages
- **SearchRooms:** `"de"` or `"en"`
- **SearchRoomAvailability:** `0` (German) or `1` (English)

---

## 🔄 Complete Workflow Examples

### Example 1: Flexible Date Search

```
User: "I need a room for 4 nights anytime between Nov 15-25"

AI: [Calls SearchRooms]
→ timespan: {from: "2025-11-15", to: "2025-11-25"}
→ duration: 4
→ adults: 2

Result: Shows all possible 4-night combinations with available rooms

User: "I like the Nov 18-22 option, the double room with breakfast"

AI: [Calls CreateReservation]
→ room_type_code: "DZ"
→ arrival: "2025-11-18"
→ departure: "2025-11-22"
→ meal_plan: 1
→ [collects guest info]

Result: Reservation confirmed ✅
```

### Example 2: Specific Date Search

```
User: "Check hotel 12345 for Dec 1-5, I need 2 rooms: one for 2 adults, one for a family of 4 (2 adults, kids aged 8 and 5)"

AI: [Calls SearchRoomAvailability]
→ hotel_id: "12345"
→ arrival: "2025-12-01"
→ departure: "2025-12-05"
→ rooms: [
    {adults: 2, children: []},
    {adults: 2, children: [{age: 8}, {age: 5}]}
  ]

Result: Shows available room options for both room configurations

User: "Book both rooms with full board"

AI: [Calls CreateReservation twice or once with number_of_units: 2]
→ Collects guest information
→ Creates reservation(s)

Result: Reservations confirmed ✅
```

### Example 3: With Additional Services

```
User: "Find me a room for 3 nights next week"

AI: [Calls SearchRooms with appropriate dates]

User: "Book the deluxe room, and add airport transfer and spa package"

AI: [Calls CreateReservation]
→ room_type_code: "DLX"
→ services: [
    {name: "Airport Transfer", quantity: 1, amount_after_tax: 50.00},
    {name: "Spa Package", quantity: 2, amount_after_tax: 80.00}
  ]
→ total_amount: [room_price + services]

Result: Reservation with services confirmed ✅
```

---

## 🧪 Testing Prompts

### Basic Searches
```
1. "Find rooms for 2 nights anytime this week"
2. "Check availability for Nov 20-23"
3. "I need a room from Dec 1-5 for 2 adults and a child (age 7)"
4. "Search for 5-night stays between Nov 15-30"
```

### With Specific Requirements
```
5. "Find a family room for 4 nights with breakfast included"
6. "I need 2 separate rooms for next weekend"
7. "Show me apartments available for a week in December"
8. "Find all-inclusive options for 3 nights"
```

### Booking
```
9. "Book the double room with half board"
10. "Create a reservation for Nov 20-23"
11. "I want to book it with airport transfer"
12. "Reserve room DZ for 2 adults, breakfast included"
```

---

## ⚠️ Important Notes

1. **Date Format:** Always use YYYY-MM-DD format
2. **Children Ages:** Must be 1-17 years old
3. **Guest Information:** Required for all reservations
4. **Reservation ID:** Must be unique for each booking
5. **Room Code:** Use `catc` value from search results
6. **Language Difference:** Note the different language parameter formats between tools

---

## 🐛 Error Handling

Common errors and solutions:

**"Validation Error"**
- Check date format (YYYY-MM-DD)
- Verify duration ≤ timespan
- Ensure children ages are 1-17

**"No rooms available"**
- Try different dates
- Adjust guest counts
- Check meal plan availability

**"Reservation failed"**
- Verify room is still available
- Check all required guest fields
- Ensure unique reservation_id

---

## 📝 File Structure

```
app/Mcp/CapCornServer/
├── CapCornServer.php
└── Tools/
    ├── SearchRoomsTool.php
    ├── SearchRoomAvailabilityTool.php
    └── CreateReservationTool.php
```

---

**Last Updated:** November 8, 2025  
**Version:** 0.1.0  
**API Documentation:** https://lookingcom-backend.vercel.app/docs
