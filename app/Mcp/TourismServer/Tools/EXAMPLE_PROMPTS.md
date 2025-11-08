# Tourism Server Tools - Example Prompts Guide

This guide provides practical example prompts you can use to test each Tourism Server tool. These prompts are designed to help you understand how the LLM interacts with the Tourism Server.

## 🔍 Discovery Tools

### 1. GetTopAttractions

**Purpose:** Get the must-see sights for any destination

**Example Prompts:**
```
1. "What are the top attractions in Vienna?"
2. "Show me the must-see sights in Salzburg"
3. "What should I visit in Innsbruck? Show me the top 4"
4. "I'm visiting Vienna for the first time, what are the top sights?"
5. "What are the best attractions in Hallstatt?"
6. "Show me top 5 things to see in Vienna"
```

**Expected Behavior:**
- Returns 3-4 top attractions by default (configurable with `limit`)
- Prioritizes bookable attractions (🎫 icon)
- Shows category, description, pricing, duration, and opening hours
- Provides attraction IDs for further actions

---

### 2. RecommendAttractions

**Purpose:** Get personalized recommendations based on user preferences

**Example Prompts:**
```
1. "I love art and history, what do you recommend in Vienna?"
2. "What's good for families in Salzburg?"
3. "I'm traveling solo, what attractions would I enjoy in Vienna?"
4. "Show me romantic places in Vienna"
5. "I'm interested in architecture and culture, what should I visit?"
6. "What attractions are good for seniors in Innsbruck?"
7. "Budget-friendly options in Vienna?"
8. "I love nature and adventure, what's available in Carinthia?"
```

**With Specific Preferences:**
```
9. "Recommend attractions in Vienna for someone who loves art, history, and architecture"
10. "I'm traveling with my family (2 adults, 2 kids), what's good in Salzburg?"
11. "Show me luxury experiences in Vienna"
12. "What cultural experiences are available for a solo traveler?"
```

**Expected Behavior:**
- Returns personalized recommendations with match scores (60-100%)
- Shows why each attraction matches (matched tags)
- Filters by preferences, travel type, age group, and budget
- Ranks by relevance score

---

### 3. NearbyAttractions

**Purpose:** Find attractions near a specific location

**Example Prompts:**
```
1. "What attractions are near Vienna?"
2. "Show me attractions within 5 km of Schönbrunn Palace"
3. "Find attractions near Salzburg city center"
4. "What's around Innsbruck?"
5. "Show me attractions near coordinates 48.2082, 16.3738"
6. "What can I visit near Hallstatt?"
```

**With Radius:**
```
7. "Find attractions within 10 km of Vienna"
8. "Show me what's within 2 km of my location (lat: 48.2, long: 16.3)"
```

**Expected Behavior:**
- Returns attractions sorted by distance
- Can use destination name or GPS coordinates
- Configurable search radius (default: 10 km)
- Shows distance from reference point

---

### 4. GetAttractionDetails

**Purpose:** Get comprehensive information about a specific attraction

**Example Prompts:**
```
1. "Tell me more about Schönbrunn Palace" (after seeing it in a list)
2. "Show me details for attraction ID 101"
3. "What's included in the Belvedere Palace ticket?"
4. "Give me full information about St. Stephen's Cathedral"
5. "What are the booking details for this attraction?"
```

**Typical Flow:**
```
User: "What are the top attractions in Vienna?"
→ GetTopAttractions returns Schönbrunn Palace (ID: 101)

User: "Tell me more about Schönbrunn Palace"
→ GetAttractionDetails(attraction_id: 101)
→ Shows full details, pricing, duration, opening hours, booking info
```

**Expected Behavior:**
- Requires attraction_id from previous discovery
- Shows complete details: category, description, pricing, duration
- Indicates if bookable (🎫) or not (📍)
- Provides booking instructions if bookable

---

### 5. GetRestaurantsAndCafes

**Purpose:** Find dining options in a destination

**Example Prompts:**
```
1. "Where can I eat in Vienna?"
2. "Show me restaurants in Salzburg"
3. "What cafes are available in Innsbruck?"
4. "Find restaurants near Vienna"
5. "Show me budget-friendly restaurants in Salzburg"
6. "What are good places to dine in Hallstatt?"
7. "Show me fine dining options in Vienna"
8. "I'm looking for cafes with outdoor seating"
```

**With Limits:**
```
9. "Show me top 10 restaurants in Vienna"
10. "List 5 cafes in Salzburg"
```

**Expected Behavior:**
- Returns restaurants and cafes sorted by price (budget-friendly first)
- Shows pricing, opening hours, reservation availability
- Includes tags (e.g., "family-friendly", "romantic", "budget")
- Indicates if reservations are available

---

## 🎫 Booking Tools (2-Step Process)

### 6. PrepareBooking

**Purpose:** Create a pending booking (Step 1 of 2)

**Example Prompts:**
```
1. "I want to book 2 tickets for Schönbrunn Palace for tomorrow"
2. "Book me tickets for Belvedere Palace on November 15th"
3. "I'd like to reserve 3 tickets for Vienna State Opera on Friday"
4. "Prepare a booking for 2 adults for this attraction"
5. "I want to book this experience for November 20th"
```

**⚠️ CRITICAL:** This tool requires REAL user information:
- Visitor's REAL full name (NOT "user", "guest", or "John Doe")
- Visitor's REAL email address (NOT "user@example.com")
- REAL credit card details (number, holder name, expiry, CVV)

**Typical Flow:**
```
User: "I want to book Schönbrunn Palace"

AI: "I'll need some information to prepare your booking:
     - Your full name
     - Your email address
     - Credit card details (number, holder name, expiry, CVV)
     - Number of tickets
     - Visit date"

User: "Name: Maria Schmidt, Email: maria.schmidt@email.com, 
       Card: 4532 1234 5678 9010, Holder: Maria Schmidt, 
       Expiry: 12/25, CVV: 123, 2 tickets for 2025-11-15"

AI: [Calls PrepareBooking with REAL information]
→ Returns pending booking with total price
→ Shows booking details and asks for confirmation
```

**Expected Behavior:**
- Creates PENDING booking (not yet confirmed)
- Requires valid credit card information
- Shows total price and booking details
- Returns booking_id (format: BKG-XXXXXXXX)
- Booking held for 15 minutes

---

### 7. ConfirmBooking

**Purpose:** Finalize booking and process payment (Step 2 of 2)

**Example Prompts:**
```
1. "Yes, confirm the booking"
2. "I confirm, go ahead with the booking"
3. "Confirm booking BKG-12345678"
4. "Yes, I want to proceed"
5. "Confirm it"
```

**⚠️ IMPORTANT:** Only call after:
1. PrepareBooking has been called
2. User has explicitly confirmed they want to proceed
3. You have the booking_id from PrepareBooking

**Typical Flow:**
```
Step 1: PrepareBooking creates pending booking
→ Returns: Booking ID: BKG-12345678, Total: 52 EUR

AI: "Your booking is ready! Total: 52 EUR for 2 tickets.
     Would you like to confirm and complete the booking?"

User: "Yes, confirm it"

Step 2: ConfirmBooking(booking_id: "BKG-12345678")
→ Processes mock payment
→ Generates ticket numbers
→ Sends confirmation email
→ Returns complete booking confirmation
```

**Expected Behavior:**
- Finalizes the booking
- Processes mock payment (generates transaction ID)
- Generates ticket numbers (format: TKT-XXXXXXXXXX)
- Sends confirmation email (simulated)
- Returns complete booking confirmation

---

## 🍽️ Restaurant Reservation Tools (2-Step Process - NO PAYMENT)

### 8. PrepareRestaurantReservation

**Purpose:** Create a pending table reservation (Step 1 of 2)

**Example Prompts:**
```
1. "I want to reserve a table at Cafe Schwarzenberg for 2 people on November 20th at 7 PM"
2. "Book a table for 4 at this restaurant on Friday evening"
3. "Reserve a table for me at this cafe for tomorrow at 12:30"
4. "I'd like to make a reservation for 2 people on Saturday at 8 PM"
5. "Book a table for my birthday celebration on November 25th"
```

**⚠️ CRITICAL:** This tool requires REAL user information:
- Guest's REAL full name (NOT "guest", "user", or "John Doe")
- Guest's REAL email address (NOT "user@example.com")
- NO credit card needed - this is just a table reservation!

**Typical Flow:**
```
User: "I want to reserve a table at Cafe Schwarzenberg"

AI: "I'll need some information for your reservation:
     - Your full name
     - Your email address
     - Number of people
     - Date and time"

User: "Name: Peter Mueller, Email: peter.mueller@email.com,
       2 people, November 20th at 7:00 PM"

AI: [Calls PrepareRestaurantReservation with REAL information]
→ Returns pending reservation with details
→ Asks for confirmation
```

**Expected Behavior:**
- Creates PENDING reservation (not yet confirmed)
- NO payment required
- Requires restaurant/cafe attraction_id
- Returns reservation_id (format: RSV-XXXXXXXX)
- Reservation held for 30 minutes

---

### 9. ConfirmRestaurantReservation

**Purpose:** Finalize table reservation (Step 2 of 2)

**Example Prompts:**
```
1. "Yes, confirm the reservation"
2. "I confirm, proceed with the reservation"
3. "Confirm reservation RSV-12345678"
4. "Yes, book the table"
5. "Confirm it"
```

**⚠️ IMPORTANT:** Only call after:
1. PrepareRestaurantReservation has been called
2. User has explicitly confirmed they want to proceed
3. You have the reservation_id from PrepareRestaurantReservation

**Typical Flow:**
```
Step 1: PrepareRestaurantReservation creates pending reservation
→ Returns: Reservation ID: RSV-12345678

AI: "Your table reservation is ready for 2 people on November 20th at 7 PM.
     Would you like to confirm?"

User: "Yes, confirm it"

Step 2: ConfirmRestaurantReservation(reservation_id: "RSV-12345678")
→ Generates confirmation number (format: CNF-XXXXXXXXXX)
→ Sends confirmation email (simulated)
→ Returns complete reservation confirmation
```

**Expected Behavior:**
- Finalizes the table reservation
- Generates confirmation number
- Sends confirmation email (simulated)
- NO payment processing
- Returns complete reservation confirmation

---

## 🏨 Accommodation Tools

### 10. HotelRoomAvailability

**Purpose:** Check hotel room availability and pricing

**Example Prompts:**
```
1. "Check hotel availability in Vienna for November 15-20, 2025"
2. "Show me available rooms for 2 adults from Dec 1-5"
3. "What rooms are available for a family (2 adults, 2 children) next week?"
4. "Check hotel availability for 1 adult arriving tomorrow, leaving in 3 days"
5. "Show me room options for 2 adults, 1 child (age 8) from Nov 20-25"
```

**With Specific Hotel:**
```
6. "Check availability at hotel ID 12345 for November 10-15"
7. "What rooms are available at this hotel for 2 adults?"
```

**Expected Behavior:**
- Returns available room options with pricing
- Shows room types, sizes, board options (breakfast, half board, etc.)
- Displays pricing (total, per person, per night)
- Requires arrival/departure dates and occupancy details
- Supports multiple room requests

---

### 11. CreateHotelReservation

**Purpose:** Create a hotel reservation (OTA standard)

**Example Prompts:**
```
1. "Book hotel room type ABC123 for 2 adults from Nov 15-20"
2. "Create a reservation for 1 unit of room type XYZ for next week"
3. "Book a hotel room for 2 adults, 1 child (age 5) from Dec 1-5"
```

**⚠️ Note:** This tool requires detailed booking information including:
- Room type code (from HotelRoomAvailability)
- Guest details (name, email, phone, address)
- Total amount and currency
- External booking ID

**Expected Behavior:**
- Creates hotel reservation using OTA_HotelResNotifRQ standard
- Supports additional services
- Requires complete guest profile
- Returns confirmation with booking details

---

## 🏧 Location Services

### 12. ATMLocator

**Purpose:** Find nearby ATMs with detailed information

**Example Prompts:**
```
1. "Where can I find an ATM in Vienna?"
2. "Show me ATMs near Schönbrunn Palace"
3. "Find ATMs within 2 km of Salzburg"
4. "I need cash, where's the nearest ATM?"
5. "Show me ATMs near coordinates 48.2082, 16.3738"
6. "Find ATMs in Vienna city center"
```

**With Specific Features:**
```
7. "Show me 24-hour ATMs in Vienna"
8. "Find wheelchair accessible ATMs near Vienna"
9. "Where are ATMs that accept deposits?"
10. "Show me ATMs with EMV support in Salzburg"
```

**With Location Types:**
```
11. "Find ATMs near attraction ID 101"
12. "Show me ATMs in postal code 1010, Austria"
13. "ATMs near Vienna airport"
```

**Expected Behavior:**
- Returns detailed ATM information
- Shows distance from search point
- Includes features: wheelchair accessible, 24/7, camera, deposits, EMV
- Displays access fees (domestic/international)
- Supports multiple search methods (city, coordinates, attraction, postal code)

---

## 🎯 Complete Use Case Examples

### Use Case 1: First-Time Visitor

```
User: "I just landed in Vienna. What should I see?"

Step 1: GetTopAttractions(destination_name: "Vienna", limit: 4)
→ Returns: Schönbrunn Palace, St. Stephen's Cathedral, Belvedere Palace, Vienna State Opera

User: "Tell me more about Schönbrunn Palace"

Step 2: GetAttractionDetails(attraction_id: 101)
→ Shows full details, pricing (26 EUR), duration, opening hours

User: "I want to book 2 tickets for tomorrow"

Step 3: AI asks for user information (name, email, credit card)
User provides: Maria Schmidt, maria@email.com, card details

Step 4: PrepareBooking(attraction_id: 101, number_of_tickets: 2, ...)
→ Returns: Pending booking, total: 52 EUR

Step 5: AI asks: "Confirm booking?"
User: "Yes"

Step 6: ConfirmBooking(booking_id: "BKG-12345678")
→ Returns: Confirmed booking with ticket numbers
```

### Use Case 2: Personalized Recommendations

```
User: "I love art and history. What do you recommend in Vienna?"

Step 1: RecommendAttractions(
    destination_name: "Vienna",
    preferences: ["art", "history"],
    travel_type: "cultural"
)
→ Returns: Belvedere Palace (95% match), Kunsthistorisches Museum (88% match), etc.

User: "The Belvedere Palace looks great! When can I visit?"

Step 2: GetAttractionDetails(attraction_id: 103)
→ Shows availability and booking details

User: "Book it for November 15th"
→ [Booking flow as above]
```

### Use Case 3: Restaurant Discovery & Reservation

```
User: "Where can I eat in Vienna?"

Step 1: GetRestaurantsAndCafes(destination_name: "Vienna", limit: 6)
→ Returns: Restaurants sorted by price with details

User: "I want to reserve a table at Cafe Schwarzenberg for 2 people on Nov 20th at 7 PM"

Step 2: AI asks for name and email
User: "Peter Mueller, peter@email.com"

Step 3: PrepareRestaurantReservation(
    attraction_id: 501,
    number_of_people: 2,
    reservation_date: "2025-11-20",
    reservation_time: "7:00 PM",
    guest_name: "Peter Mueller",
    guest_email: "peter@email.com"
)
→ Returns: Pending reservation

Step 4: AI asks: "Confirm reservation?"
User: "Yes"

Step 5: ConfirmRestaurantReservation(reservation_id: "RSV-12345678")
→ Returns: Confirmed reservation with confirmation number
```

### Use Case 4: Family Trip Planning

```
User: "We're a family visiting Salzburg. What's good for kids?"

Step 1: RecommendAttractions(
    destination_name: "Salzburg",
    travel_type: "family",
    age_group: "family"
)
→ Returns: Family-friendly attractions

User: "Show me restaurants suitable for families"

Step 2: GetRestaurantsAndCafes(destination_name: "Salzburg")
→ Returns: Family-friendly restaurants

User: "I need cash. Where's the nearest ATM?"

Step 3: ATMLocator(city: "Salzburg", limit: 5)
→ Returns: Nearby ATMs with features and distance
```

### Use Case 5: Hotel Booking

```
User: "Check hotel availability in Vienna for November 15-20 for 2 adults"

Step 1: HotelRoomAvailability(
    arrival: "2025-11-15",
    departure: "2025-11-20",
    rooms: [{adults: 2}]
)
→ Returns: Available room options with pricing

User: "Book room type ABC123"

Step 2: CreateHotelReservation(
    room_type_code: "ABC123",
    number_of_units: 1,
    adults: 2,
    start: "2025-11-15",
    end: "2025-11-20",
    total_amount: 500.00,
    guest: {...},
    res_id_value: "EXT-12345",
    res_id_source: "MCP_SERVER"
)
→ Returns: Hotel reservation confirmation
```

---

## 🧪 Testing Tips

1. **Start with discovery** when exploring:
   ```
   "What are the top attractions in Vienna?"
   ```

2. **Use recommendations** for personalized results:
   ```
   "I love art, what do you recommend?"
   ```

3. **Get details** before booking:
   ```
   "Tell me more about this attraction"
   ```

4. **Always confirm** bookings and reservations:
   ```
   Prepare → Show details → Wait for confirmation → Confirm
   ```

5. **Use ATMLocator** for practical needs:
   ```
   "Where can I find an ATM?"
   ```

---

## 📊 Quick Reference

| Tool | When to Use | Requires Payment? | 2-Step Process? |
|------|-------------|-------------------|-----------------|
| GetTopAttractions | Finding must-see sights | No | No |
| RecommendAttractions | Personalized suggestions | No | No |
| NearbyAttractions | Finding nearby places | No | No |
| GetAttractionDetails | Getting full info | No | No |
| GetRestaurantsAndCafes | Finding dining options | No | No |
| PrepareBooking | Starting attraction booking | Yes (credit card) | Yes (Step 1) |
| ConfirmBooking | Finalizing booking | Processed | Yes (Step 2) |
| PrepareRestaurantReservation | Starting table reservation | No | Yes (Step 1) |
| ConfirmRestaurantReservation | Finalizing reservation | No | Yes (Step 2) |
| HotelRoomAvailability | Checking room availability | No | No |
| CreateHotelReservation | Creating hotel booking | Requires amount | No |
| ATMLocator | Finding ATMs | No | No |

---

## 🎬 Ready-to-Use Test Prompts

Copy and paste these to test the complete flow:

```
1. "What are the top attractions in Vienna?"
2. "I love art and history, what do you recommend in Vienna?"
3. "Tell me more about Schönbrunn Palace"
4. "Where can I eat in Salzburg?"
5. "I want to book 2 tickets for Belvedere Palace for tomorrow"
6. "Reserve a table for 2 at Cafe Schwarzenberg on Friday at 7 PM"
7. "Where can I find an ATM in Vienna?"
8. "Check hotel availability in Vienna for next week"
9. "Show me attractions near Schönbrunn Palace"
10. "What's good for families in Salzburg?"
```

---

**Last Updated:** November 5, 2025
**Tourism Server Version:** 0.0.1

