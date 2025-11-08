# DSAPI Tools - Example Prompts Guide

This guide provides practical example prompts you can use to test each DSAPI tool. These prompts are designed to help you understand how the LLM interacts with the DSAPI Server.

## 🔍 Discovery Tools

### 1. GetDSAPIFilterOptions

**Purpose:** Discover available filter categories before searching

**Example Prompts:**
```
1. "What types of experiences are available in Kärnten?"
2. "Show me all the experience categories available in Carinthia"
3. "What filters can I use to search for Kärnten activities?"
4. "List all available experience types, themes, and locations for Kärnten"
5. "What holiday themes are available for experiences in Carinthia?"
```

**Expected Behavior:**
- Returns lists of experience types (e.g., culture, nature, adventure, gastronomy)
- Shows holiday themes (e.g., family, romantic, adventure)
- Lists locations within Kärnten region
- Shows guest card options

---

### 2. ListDSAPIExperiences

**Purpose:** Browse all experiences without date constraints

**Example Prompts:**
```
1. "Show me all experiences available in Kärnten"
2. "List all cultural experiences in Carinthia"
3. "What experiences are there in Kärnten? I don't have specific dates yet"
4. "Browse all available activities in Carinthia"
5. "Show me all nature experiences in Kärnten"
6. "List family-friendly experiences in Carinthia"
7. "What experiences are available in Villach?" (location filter)
8. "Find experiences with names containing 'hiking'"
```

**With Filters:**
```
9. "Show me nature experiences in Carinthia"
10. "List all gastronomy experiences for families"
11. "What cultural experiences are available in the Wörthersee area?"
```

**Expected Behavior:**
- Returns all experiences matching the filters
- Does NOT check date availability
- Results can be filtered by types, locations, themes, guest cards, or name
- Useful for exploratory browsing

---

### 3. SearchDSAPIExperiences

**Purpose:** Find experiences available on specific dates

**Example Prompts:**
```
1. "Show me experiences available in Kärnten from November 1-10, 2025"
2. "What can I do in Carinthia between December 20-27, 2025?"
3. "Find activities available in Kärnten next week"
4. "I'm visiting Carinthia from 2025-11-15 to 2025-11-20, what's available?"
5. "Show me what I can book in Kärnten for the Christmas holidays"
```

**With Filters:**
```
6. "Find adventure activities available in Kärnten from Nov 1-10, 2025"
7. "Show me family-friendly experiences in Carinthia between 2025-12-01 and 2025-12-15"
8. "What cultural experiences are available in Villach from November 5-12?"
9. "Find romantic experiences available in Kärnten on Valentine's Day 2026"
```

**Expected Behavior:**
- Returns ONLY experiences with confirmed availability on the specified dates
- Must provide date_from and date_to
- Can combine with type, location, theme filters
- More specific than ListDSAPIExperiences

---

### 4. GetDSAPIServiceProducts

**Purpose:** Get specific product details for an experience

**Example Prompts:**
```
1. "Show me the products available for [experience name]"
2. "What tickets can I buy for this experience?" (after seeing an experience)
3. "Get me the product details for spIdentity: [id] and serviceId: [id]"
4. "What are the pricing options for this activity?"
5. "Show me all available packages for this experience"
```

**Typical Flow:**
```
User: "Show me nature experiences in Kärnten"
→ ListDSAPIExperiences is called
→ User selects an experience
User: "What products are available for nature experiences?"
→ GetDSAPIServiceProducts is called with spIdentity and serviceId
```

**Expected Behavior:**
- Requires spIdentity and serviceId from a previous experience listing
- Returns concrete products/tickets with pricing
- Shows different variants or packages if available
- Provides product IDs needed for adding to shopping list

---

### 5. GetDSAPIProductAvailability

**Purpose:** Get detailed availability schedule with dates, times, and slots

**Example Prompts:**
```
1. "When is this experience available in November?"
2. "Show me the availability schedule for this activity from Nov 1-10"
3. "What dates and times are available for this experience in December?"
4. "Check availability for [experience] between 2025-11-15 and 2025-11-20"
5. "When can I book this activity? Show me all available time slots"
6. "What's the cancellation policy for this experience?"
```

**Typical Flow:**
```
User: "I want to book [experience name] in November"
→ SearchDSAPIExperiences finds the experience
User: "Show me when it's available"
→ GetDSAPIProductAvailability with date range
→ Shows specific dates, times, available slots, prices, cancellation policies
```

**Expected Behavior:**
- Requires spIdentity, serviceId, date_from, date_to
- Returns day-by-day availability schedule
- Shows specific time slots (e.g., "10:00 AM", "2:00 PM")
- Includes available capacity (e.g., "5 slots remaining")
- Shows real-time pricing per date
- Displays booking deadlines
- Includes cancellation policies

---

## 🛒 Shopping & Booking Tools

### 6. CreateDSAPIShoppingList

**Purpose:** Create a cart before adding experiences

**Example Prompts:**
```
1. "I want to book this experience, create a shopping cart"
2. "Start a new booking for me"
3. "Create a shopping list so I can add experiences"
4. "I'm ready to book, set up a cart"
5. "Initialize a shopping list for Kärnten experiences"
```

**Expected Behavior:**
- No parameters required
- Returns a shopping_list_id (UUID)
- This ID must be saved and used for AddToDSAPIShoppingList
- Should be called ONCE per booking session

---

### 7. AddToDSAPIShoppingList

**Purpose:** Add experiences to cart and get checkout URL

**Example Prompts:**
```
1. "Add this experience to my shopping list"
2. "Book 2 tickets for this activity on November 5th"
3. "Add this Alpaca hike tour to my cart"
4. "I want to book this experience, please add it"
5. "Add [experience name] to the shopping list for November 10th"
```

**Typical Complete Booking Flow:**
```
User: "I want to book a Alpaca hike experience in Kärnten for November 5th"

Step 1: Search for experiences
→ SearchDSAPIExperiences(date_from: "2025-11-05", date_to: "2025-11-05", name: "hike")

Step 2: User selects an experience, wants details
→ GetDSAPIProductAvailability(sp_identity, service_id, "2025-11-05", "2025-11-05")

Step 3: User confirms booking
→ CreateDSAPIShoppingList()
→ Returns shopping_list_id: "abc-123-def"

Step 4: Add to cart
→ AddToDSAPIShoppingList(
    shopping_list_id: "abc-123-def",
    add_service_items: [{
      serviceId: "...",
      spIdentity: "...",
      date: "2025-11-05",
      quantity: 2,
      ...
    }]
  )
→ Returns checkout URL

Step 5: Direct user to checkout URL to complete payment
```

**Expected Behavior:**
- Requires shopping_list_id from CreateDSAPIShoppingList
- Can add multiple items in one call
- Returns checkout URL where user completes booking
- The actual payment happens on the DSAPI checkout page (external)

---

## 🎯 Complete Use Case Examples

### Use Case 1: Casual Browser

```
User: "What can I do in Kärnten?"
→ ListDSAPIExperiences()
→ Shows all available experiences

User: "Show me only nature activities"
→ GetDSAPIFilterOptions() first to get nature type ID
→ ListDSAPIExperiences(types: ["nature-uuid"])
```

### Use Case 2: Date-Specific Booking

```
User: "I'm visiting Carinthia from November 10-15, 2025. What's available?"
→ SearchDSAPIExperiences(date_from: "2025-11-10", date_to: "2025-11-15")
→ Returns experiences with availability

User: "Tell me more about the hiking tour"
→ GetDSAPIServiceProducts(sp_identity, service_id)
→ Shows products and pricing

User: "When exactly is it available on November 12th?"
→ GetDSAPIProductAvailability(sp_identity, service_id, "2025-11-12", "2025-11-12")
→ Shows time slots and availability

User: "Book it for 2 people at 10 AM"
→ CreateDSAPIShoppingList()
→ AddToDSAPIShoppingList(shopping_list_id, add_service_items: [...])
→ Provides checkout URL
```

### Use Case 3: Family Trip Planning

```
User: "We're a family traveling to Kärnten in December. What's good for kids?"
→ GetDSAPIFilterOptions()
→ Shows family theme options

User: "Show me family-friendly experiences available Dec 20-27"
→ SearchDSAPIExperiences(
    date_from: "2025-12-20",
    date_to: "2025-12-27",
    holiday_themes: ["family-uuid"]
  )

User: "What's the availability for the Christmas market tour on Dec 24th?"
→ GetDSAPIProductAvailability(sp_identity, service_id, "2025-12-24", "2025-12-24")

User: "Book 2 adults and 2 children for the 2 PM slot"
→ CreateDSAPIShoppingList()
→ AddToDSAPIShoppingList(...)
```

---

## 🧪 Testing Tips

1. **Always start with filters** if you don't know what's available:
   ```
   "What types of experiences are in Kärnten?"
   ```

2. **Use ListDSAPIExperiences** for browsing:
   ```
   "Show me all nature experiences"
   ```

3. **Use SearchDSAPIExperiences** when dates matter:
   ```
   "What's available November 1-10?"
   ```

4. **Get detailed info** before booking:
   ```
   "Show me availability for this experience"
   ```

5. **Complete bookings** with shopping list:
   ```
   "Create a cart" → "Add this to my cart"
   ```

---

## 📊 Quick Reference

| Tool | When to Use | Requires Dates? | Requires IDs? |
|------|-------------|----------------|---------------|
| GetDSAPIFilterOptions | First step, exploring | No | No |
| ListDSAPIExperiences | Browsing all experiences | No | No |
| SearchDSAPIExperiences | Finding available experiences on dates | Yes | No |
| GetDSAPIServiceProducts | Getting product/ticket details | No | Yes (sp/service) |
| GetDSAPIProductAvailability | Checking detailed availability | Yes | Yes (sp/service) |
| CreateDSAPIShoppingList | Starting booking process | No | No |
| AddToDSAPIShoppingList | Adding to cart & checkout | No | Yes (shopping_list) |

---

## 🎬 Ready-to-Use Test Prompts

Copy and paste these to test the complete flow:

```
1. "What experiences are available in Kärnten?"
2. "Show me cultural experiences in Carinthia"
3. "What's available in Kärnten from November 10-15, 2025?"
4. "Find family-friendly activities available in December 2025"
5. "Show me the products for [experience after searching]"
6. "What's the availability schedule for this experience?"
7. "I want to book this, create a shopping cart"
8. "Add this experience to my cart for 2 people"
```

