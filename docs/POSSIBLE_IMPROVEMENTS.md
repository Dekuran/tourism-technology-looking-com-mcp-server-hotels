# Possible MCP Server Improvements

## Current State

The MCP server currently supports 3 tools:

1. **SearchRooms** - Flexible room search
2. **SearchRoomAvailability** - Exact date availability
3. **CreateReservation** - Book rooms

## Suggested Additional Tools

### 1. CancelReservation

Cancel existing bookings.

- **Parameters**: `reservation_id`, `cancellation_reason` (optional)
- **Returns**: Cancellation confirmation with refund details
- **API**: `DELETE /api/v1/reservations/{id}`

### 2. ModifyReservation

Change booking details (dates, guests, room type).

- **Parameters**: `reservation_id`, modified fields
- **Returns**: Updated reservation details
- **API**: `PATCH /api/v1/reservations/{id}`

### 3. GetReservationDetails

Retrieve full booking information.

- **Parameters**: `reservation_id`
- **Returns**: Complete reservation data
- **API**: `GET /api/v1/reservations/{id}`

### 4. ListUserReservations

View all bookings for a guest.

- **Parameters**: `email` or `guest_id`, date filters
- **Returns**: List of reservations
- **API**: `GET /api/v1/reservations?email={email}`

### 5. AddReservationServices

Add extras to existing bookings.

- **Parameters**: `reservation_id`, services array
- **Returns**: Updated total and service list
- **API**: `POST /api/v1/reservations/{id}/services`

### 6. GetCancellationPolicy

Check cancellation terms before booking.

- **Parameters**: `hotel_id`, `room_type_code`, check-in date
- **Returns**: Policy details, deadlines, refund info
- **API**: `GET /api/v1/hotels/{id}/cancellation-policy`

## How to Add a New Tool

### 1. Create Tool Class

```php
// app/Mcp/CapCornServer/Tools/YourTool.php
class YourTool extends Tool {
    protected string $description = 'Brief description';

    public function handle(Request $request): Response {
        $validated = $request->validate([...]);
        // Call API, process, return Response::text()
    }

    public function schema(JsonSchema $schema): array {
        return ['param' => $schema->string()->description('...')];
    }
}
```

### 2. Register Tool

```php
// app/Mcp/CapCornServer/CapCornServer.php
protected array $tools = [
    SearchRoomsTool::class,
    SearchRoomAvailabilityTool::class,
    CreateReservationTool::class,
    YourTool::class, // Add here
];
```

### 3. Update Instructions

Add tool documentation to `$instructions` in `CapCornServer.php`.

### 4. Test

```bash
php artisan mcp:inspector mcp/capcorn
```

## Implementation Priority

**High Priority:**

- CancelReservation (complete booking lifecycle)
- GetReservationDetails (retrieve booking info)

**Medium Priority:**

- ModifyReservation (change bookings)
- GetCancellationPolicy (inform before booking)

**Low Priority:**

- ListUserReservations (user convenience)
- AddReservationServices (upselling)
