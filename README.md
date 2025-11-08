# MCP Server Suite

A comprehensive Model Context Protocol (MCP) server suite built with Laravel that provides AI agents with tourism discovery, booking, and payment capabilities. This project includes multiple specialized MCP servers for different tourism domains.

## 🏗️ Architecture Overview

This project follows a **Server-First Organization** architecture, where each MCP server is self-contained with its own tools, resources, and configuration. This design provides:

- ✅ **Clear Ownership**: Each server owns its tools and logic
- ✅ **Better Encapsulation**: Servers are independent and portable
- ✅ **Easier Scaling**: Add new servers without affecting existing ones
- ✅ **Developer-Friendly**: Clear structure for new team members

## 📦 Available Servers

### 🗺️ Tourism Server
General tourism services for Austrian destinations (Vienna, Salzburg, Innsbruck, Hallstatt)
- Attraction discovery and booking
- Restaurant reservations
- Hotel accommodations
- ATM location services

**Endpoint:** `/mcp/tourism`  
**Location:** `app/Mcp/TourismServer/`  
**Example Prompts:** [Tourism Server Example Prompts](app/Mcp/TourismServer/Tools/EXAMPLE_PROMPTS.md)

### 🏔️ DSAPI Server
Kärnten (Carinthia) regional experiences booking system
- Experience discovery and filtering
- Product availability and pricing
- Shopping cart and checkout integration

**Endpoint:** `/mcp/dsapi`  
**Location:** `app/Mcp/DSAPIServer/`  
**Example Prompts:** [DSAPI Server Example Prompts](app/Mcp/DSAPIServer/Tools/EXAMPLE_PROMPTS.md)

## What is This?

The **MCP Server Suite** provides middleware layers that enable AI assistants (like Claude, ChatGPT, or Cursor) to interact with tourism services. It bridges the gap between natural language queries and tourism data, allowing AI agents to help users discover attractions, find restaurants, make bookings, and even locate ATMs.

Each server is designed for specific use cases:
- **Tourism Server**: General Austrian tourism (Vienna, Salzburg, etc.)
- **DSAPI Server**: Kärnten/Carinthia regional experiences booking

### Key Features

**Tourism Server:**
- 🗺️ **Destination Discovery**: Search and explore destinations across Austria
- 🎫 **Attraction Booking**: Book tickets for tourist attractions with a 2-step confirmation process
- 🍽️ **Restaurant Reservations**: Reserve tables at restaurants and cafes
- 🏨 **Hotel Accommodations**: Check room availability and create hotel reservations
- 🏧 **ATM Locator**: Find nearby ATMs with detailed information

**DSAPI Server:**
- 🔍 **Experience Discovery**: Browse and search Kärnten experiences with advanced filtering
- 📅 **Date-Specific Availability**: Find experiences available on specific dates
- 🛒 **Shopping Cart**: Complete booking flow with shopping list and checkout
- 💰 **Real-Time Pricing**: Get detailed pricing and availability schedules

**Both Servers:**
- 🤖 **AI-Powered**: Designed for seamless integration with AI agents via MCP protocol

## 📋 Use Cases

### For Travelers

**Tourism Server:**
- **"What should I visit in Vienna?"** → Get top attractions with prices
- **"I love art and history"** → Receive personalized recommendations
- **"Book 2 tickets for Schönbrunn Palace"** → Complete booking with confirmation
- **"Where can I eat in Salzburg?"** → Find restaurants sorted by price
- **"I need an ATM near the palace"** → Locate nearby ATMs with detailed information
- **"Check hotel availability for tomorrow"** → Search and reserve hotel rooms

**DSAPI Server:**
- **"What experiences are available in Kärnten?"** → Browse all Kärnten experiences
- **"Show me activities available in November"** → Date-specific availability search
- **"Find family-friendly experiences in Carinthia"** → Filtered experience discovery
- **"I want to book an alpaca hiking tour"** → Complete booking flow with checkout

### For AI Agents

The servers expose tools that AI agents can call programmatically:

**Tourism Server Tools:**
- Discover destinations and attractions
- Provide personalized recommendations based on preferences
- Handle the complete booking workflow (2-step process)
- Process restaurant reservations without payment
- Locate nearby ATMs with detailed information
- Manage hotel reservations

**DSAPI Server Tools:**
- Discover and filter Kärnten experiences
- Search experiences by date availability
- Get product details and pricing
- Check detailed availability schedules
- Create shopping lists and add items
- Complete bookings via checkout URL

### For Developers

- **MCP Protocol Implementation**: Learn how to build MCP servers with Laravel
- **Server-First Architecture**: Self-contained servers with organized tool structure
- **Service Layer Architecture**: Clean separation between tools and business logic
- **Booking Systems**: Two-step booking flows (prepare → confirm)
- **Mock Payment Processing**: Safe testing without real transactions
- **Extensible Design**: Easy to add new servers, tools, and destinations

## 🚀 Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Laravel 12.x

### Step 1: Clone and Install Dependencies

```bash
# Clone the repository
git clone https://github.com/EngageMediaGmbH/mcp-server/tree/main
cd mcp-server

# Install PHP dependencies
composer install
```

### Step 2: Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Step 3: Configure Environment Variables

Edit `.env` file with your settings:

```env
APP_NAME="Tourism MCP Server"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (using SQLite by default)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Cache (using file cache by default)
CACHE_DRIVER=file
```

### Step 5: Start the Server

```bash
# Development mode (with hot reload)
php artisan serve
```

The server will be available at `http://localhost:8000`

## 🔧 Configuration

### MCP Server Endpoints

The MCP servers are configured in `routes/ai.php`:

```php
// Tourism Server - General Austrian tourism
Mcp::web('/mcp/tourism', \App\Mcp\TourismServer\TourismServer::class);

// DSAPI Server - Kärnten regional experiences
Mcp::web('/mcp/dsapi', \App\Mcp\DSAPIServer\DSAPIServer::class);
```

**Endpoints:**
- Tourism Server: `http://localhost:8000/mcp/tourism`
- DSAPI Server: `http://localhost:8000/mcp/dsapi`


## 📖 How to Use

### With AI Agents (Recommended)

The server is designed to work with MCP-compatible AI clients:

1. **Claude Desktop**
   - Add server configuration to your MCP settings
   - Point to your server URL

2. **ChatGPT with MCP**
   - Configure MCP server endpoint
   - Start chatting about tourism!

3. **Cursor**
   - Add server configuration to your MCP settings
   - Point to your server URL
   - Use Cursor's AI features to interact with tourism tools

4. **MCP Inspector** (For Testing & Debugging)
   - The MCP Inspector is an interactive tool for testing and debugging your MCP servers
   - Use it to connect to your server, verify authentication, and try out tools, resources, and prompts
   - Run the inspector for your tourism server:
     ```bash
     php artisan mcp:inspector mcp/tourism
     ```
   - This command launches the MCP Inspector and provides the client settings that you may copy into your MCP client to ensure everything is configured correctly

### Example Conversation Flows

**Tourism Server:**
```
User: "I just landed in Vienna. What are the top 4 sights I should visit?"

AI Agent: [Calls GetTopAttractions]
→ Returns: Schönbrunn Palace, St. Stephen's Cathedral, Belvedere Palace, Vienna State Opera

User: "Tell me more about Schönbrunn Palace"

AI Agent: [Calls GetAttractionDetails with attraction_id: 101]
→ Returns: Full details, price (26 EUR), duration (120 min), opening hours

User: "I'd like to book 2 tickets for tomorrow"

AI Agent: [Calls PrepareBooking]
→ Returns: Pending booking with total price (52 EUR)
→ AI asks: "Please confirm your booking?"

User: "Yes, confirm it"

AI Agent: [Calls ConfirmBooking]
→ Returns: Confirmed booking with transaction ID and ticket numbers
```

**DSAPI Server:**
```
User: "What experiences are available in Kärnten in November?"

AI Agent: [Calls SearchDSAPIExperiences]
→ Returns: Experiences available Nov 1-30, 2025

User: "Show me the availability for the alpaca hiking tour"

AI Agent: [Calls GetDSAPIProductAvailability]
→ Returns: Detailed schedule with dates, times, prices, and slots

User: "I want to book it for November 15th"

AI Agent: [Calls CreateDSAPIShoppingList]
→ Returns: Shopping list ID

AI Agent: [Calls AddToDSAPIShoppingList]
→ Returns: Checkout URL for completing booking
```

**📖 For more example prompts and use cases, see:**
- [Tourism Server Example Prompts](app/Mcp/TourismServer/Tools/EXAMPLE_PROMPTS.md)
- [DSAPI Server Example Prompts](app/Mcp/DSAPIServer/Tools/EXAMPLE_PROMPTS.md)

## 🛠️ Available Tools

### Tourism Server Tools

**Discovery Tools:**
| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| **GetTopAttractions** | Get must-see sights for a destination | `destination_name`, `limit` |
| **RecommendAttractions** | Personalized recommendations | `destination_name`, `preferences`, `travel_type`, `age_group`, `budget` |
| **NearbyAttractions** | Find attractions near a location | `destination_name` or `lat/long`, `radius_km` |
| **GetAttractionDetails** | Full details about a specific attraction | `attraction_id` |
| **GetRestaurantsAndCafes** | Find dining options | `destination_name`, `limit` |

**Booking Tools (2-Step Process):**
| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| **PrepareBooking** | Create pending booking (Step 1) | `attraction_id`, `number_of_tickets`, `visit_date`, `visitor_name`, `visitor_email`, `card_details` |
| **ConfirmBooking** | Finalize booking (Step 2) | `booking_id`, `payment_method` |

**Reservation Tools (2-Step Process - NO PAYMENT):**
| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| **PrepareRestaurantReservation** | Create pending table reservation | `attraction_id`, `number_of_people`, `reservation_date`, `reservation_time`, `guest_name`, `guest_email` |
| **ConfirmRestaurantReservation** | Confirm table reservation | `reservation_id` |

**Accommodation Tools:**
| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| **HotelRoomAvailability** | Check hotel room availability | `hotel_id`, `arrival`, `departure`, `rooms[]` |
| **CreateHotelReservation** | Create hotel reservation (OTA standard) | `room_type_code`, `number_of_units`, `adults`, `start`, `end`, `total_amount` |

**Location Services:**
| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| **ATMLocator** | Find nearby ATMs | `location` or `city` or `lat/long`, `distance`, `limit` |

### DSAPI Server Tools

**Discovery Tools:**
| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| **GetFilterOptions** | Get available filter categories | `language` |
| **ListExperiences** | Browse all experiences (no date filter) | `types[]`, `locations[]`, `holiday_themes[]`, `guest_cards[]`, `name` |
| **SearchExperiences** | Find experiences available on dates | `date_from`, `date_to`, `types[]`, `locations[]`, `holiday_themes[]` |

**Product Tools:**
| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| **GetServiceProducts** | Get bookable products for an experience | `sp_identity`, `service_id`, `language`, `currency` |
| **GetProductAvailability** | Get detailed availability schedule | `sp_identity`, `service_id`, `date_from`, `date_to` |

**Shopping Tools:**
| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| **CreateShoppingList** | Create shopping cart | None |
| **AddToShoppingList** | Add items and get checkout URL | `shopping_list_id`, `add_service_items[]` |

**📖 For detailed tool documentation and example prompts:**
- [Tourism Server Example Prompts](app/Mcp/TourismServer/Tools/EXAMPLE_PROMPTS.md)
- [DSAPI Server Example Prompts](app/Mcp/DSAPIServer/Tools/EXAMPLE_PROMPTS.md)

## 🏗️ Architecture

### System Overview

```
┌─────────────────┐
│   AI Agents     │ (Claude, ChatGPT, Cursor)
│   (Clients)     │
└────────┬────────┘
         │ MCP Protocol
         ▼
┌─────────────────────────────────────────┐
│         MCP Server Suite                │
│                                         │
│  ┌─────────────────┐  ┌──────────────┐  │
│  │ Tourism Server  │  │ DSAPI Server │  │
│  │                 │  │              │  │
│  │ ┌─────────────┐ │  │ ┌──────────┐ │  │
│  │ │   Tools     │ │  │ │  Tools   │ │  │
│  │ │ Discovery   │ │  │ │Discovery │ │  │
│  │ │ Booking     │ │  │ │Products  │ │  │
│  │ │ Reservation │ │  │ │Shopping  │ │  │
│  │ │ External    │ │  │ └──────────┘ │  │
│  │ └─────┬───────┘ │  └──────┬───────┘  │
│  └───────┼─────────┘         │          │
└──────────┼───────────────────┼──────────┘
           │                   │
           │ Service Layer     │ Service Layer
           ▼                   ▼
┌─────────────────┐  ┌─────────────────┐
│ TourismService  │  │  DSAPIService   │
│                 │  │                 │
│ • Destinations  │  │ • Experiences   │
│ • Attractions   │  │ • Products      │
│ • Bookings      │  │ • Shopping Lists│
│ • Reservations  │  │ • Availability  │
└─────────────────┘  └─────────────────┘
```

### Key Components

**Server-First Organization:**
- **TourismServer** (`app/Mcp/TourismServer/TourismServer.php`): Self-contained tourism server
  - **Tools** (`app/Mcp/TourismServer/Tools/`): Organized by category (Discovery, Booking, Reservation, Accommodation, External)
- **DSAPIServer** (`app/Mcp/DSAPIServer/DSAPIServer.php`): Self-contained DSAPI server
  - **Tools** (`app/Mcp/DSAPIServer/Tools/`): Organized by category (Discovery, Products, Shopping)

**Service Layer:**
- **TourismService** (`app/Services/TourismService.php`): Core business logic for tourism data
- **DSAPIService** (`app/Services/DSAPIService.php`): DSAPI integration and booking logic
- **MastercardService** (`app/Services/MastercardService.php`): ATM location services

This architecture ensures:
- ✅ Clear separation of concerns
- ✅ Independent, portable servers
- ✅ Easy to add new servers
- ✅ Users always see what they're paying for before committing

## 📁 Project Structure

```
MpcServer/
├── app/
│   ├── Http/
│   │   └── Controllers/          # HTTP controllers
│   ├── Mcp/
│   │   ├── TourismServer/        # Tourism Server (self-contained)
│   │   │   ├── TourismServer.php
│   │   │   └── Tools/
│   │   │       ├── Discovery/    # Discovery tools
│   │   │       ├── Booking/      # Booking tools
│   │   │       ├── Reservation/  # Restaurant reservation tools
│   │   │       ├── Accommodation/# Hotel tools
│   │   │       ├── External/    # ATM location services
│   │   │       └── EXAMPLE_PROMPTS.md
│   │   │
│   │   └── DSAPIServer/         # DSAPI Server (self-contained)
│   │       ├── DSAPIServer.php
│   │       └── Tools/
│   │           ├── Discovery/   # Experience discovery
│   │           ├── Products/     # Product & availability
│   │           ├── Shopping/     # Shopping cart tools
│   │           └── EXAMPLE_PROMPTS.md
│   │
│   ├── Models/                   # Eloquent models
│   ├── Services/
│   │   ├── TourismService.php    # Tourism business logic
│   │   ├── DSAPIService.php      # DSAPI integration
│   │   └── MastercardService.php# ATM location services
│   └── Mail/                     # Email classes
├── routes/
│   └── ai.php                    # MCP route registration
├── config/                       # Configuration files
├── database/                     # Migrations and seeders
└── tests/                        # Test suites
```

**Architecture Benefits:**
- 🎯 **Clear Ownership**: Each server owns its tools
- 📦 **Self-Contained**: Servers are independent modules
- 🔧 **Easy Maintenance**: Changes to one server don't affect others
- 📈 **Scalable**: Add new servers easily

## 🧪 Testing

### Run Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter TourismServiceTest

# Run with coverage
php artisan test --coverage
```

### Test Data

The system includes mock data for:
- **4 Destinations**: Vienna, Salzburg, Innsbruck, Hallstatt
- **18 Attractions**: Museums, palaces, restaurants, cafes
- **13 Bookable Attractions**: With pricing in EUR
- **Mock Payment Processing**: Safe testing without real charges

## 🔒 Security Notes

- **Mock Payments**: All payment processing is mocked. No real transactions are processed.
- **In-Memory Storage**: Bookings are stored in cache (not persisted to database by default).
- **Input Validation**: All tool inputs are validated before processing.
- **User Confirmation**: Bookings require explicit user approval before confirmation.

## 🚧 Current Limitations

- **Mock Data**: Uses in-memory data structures (no database persistence by default)
- **Mock Payments**: Payment processing is simulated, not real
- **Limited Destinations**: Currently covers 4 Austrian destinations
- **No Email**: Email confirmations are mocked
- **No Ticket Generation**: Ticket numbers are generated but no PDFs created

## 🤝 Contributing

Contributions are welcome! Areas that need improvement:

- More destinations and attractions
- Real payment gateway integration
- Email service implementation
- Ticket PDF generation
- Database persistence for bookings
- Additional tools and features

## 📝 License

This project uses the MIT license.

### Sample Queries

**Tourism Server:**
- "What should I visit in Vienna?"
- "I love art and history, what do you recommend?"
- "Where can I find restaurants in Salzburg?"
- "Book 2 tickets for Schönbrunn Palace for tomorrow"
- "Find ATMs near Vienna"
- "Check hotel availability in Vienna for next week"

**DSAPI Server:**
- "What experiences are available in Kärnten?"
- "Show me activities available in November 2025"
- "Find family-friendly experiences in Carinthia"
- "What's the availability for the alpaca hiking tour?"
- "I want to book an experience for November 15th"

**📖 For comprehensive example prompts and use cases:**
- [Tourism Server Example Prompts](app/Mcp/TourismServer/Tools/EXAMPLE_PROMPTS.md)
- [DSAPI Server Example Prompts](app/Mcp/DSAPIServer/Tools/EXAMPLE_PROMPTS.md)

---

**Built with ❤️ using Laravel MCP Framework**

For more information about MCP (Model Context Protocol), visit: [https://modelcontextprotocol.io/](https://modelcontextprotocol.io/)

