# CapCorn Hotel MCP Server

A single-purpose Model Context Protocol (MCP) server built with Laravel that exposes hotel discovery and reservation capabilities backed by the CapCorn Hotel API.

Key capabilities:
- Search available rooms for flexible stays within a timespan
- Check direct room availability for specific dates
- Create reservations with guest details and pricing

Endpoint:
- MCP Server: `/mcp/capcorn`

Quick links:
- Runtime route: `routes/ai.php`
- Server class: `app/Mcp/CapCornServer/CapCornServer.php`
- Tools: `app/Mcp/CapCornServer/Tools/`
- Deployment: `docs/DEPLOYMENT-CLOUD-RUN.md`
- Test/verification scripts: `scripts/start_mcp_server.sh`, `scripts/run_mcp_tests.sh`

## Installation

Prerequisites: PHP 8.2+, Composer, curl

Clone and install:

```bash
git clone https://github.com/Dekuran/tourism-technology-looking-com-mcp-server-hotels.git
cd mcp-hotel-server
composer install
```

Local environment:

```bash
cp .env.example .env
php artisan key:generate
```

SQLite (default):

```bash
mkdir -p database
: > database/database.sqlite
```

## Configuration

CapCorn base URL can be configured via `.env`; default is `http://localhost:9001`

```env
CAPCORN_BASE_URL=https://capcorn.example.com
```

It is consumed via `config/services.php` under `capcorn.base_url`.

## Start the server

```bash
php artisan serve
```

The app will be available on `http://localhost:8000`.

## MCP endpoint

The MCP endpoint is registered in `routes/ai.php`:

```php
Mcp::web('/mcp/capcorn', \App\Mcp\CapCornServer\CapCornServer::class);
```

## Tools

1) SearchRoomsTool  
- Flexible stay search within a timespan and duration  
- Params: `language` ("de"|"en"), `timespan.from`, `timespan.to`, `duration`, `adults`, `children[]`

2) SearchRoomAvailabilityTool  
- Exact date availability (arrival/departure)  
- Params: `language` (0=de,1=en), `hotel_id`, `arrival`, `departure`, `rooms[]`

3) CreateReservationTool  
- Create a reservation with guest info and pricing  
- Required: `hotel_id`, `room_type_code`, `meal_plan`, `guest_counts`, `arrival`, `departure`, `total_amount`, `guest`, `reservation_id`  
- Optional: `number_of_units`, `services[]`, `booking_comment`, `source`

## Verification (recommended)

You can auto-start the server and generate a Markdown verification report with `scripts/run_mcp_tests.sh`. It verifies:
- Environment and versions
- Route presence and endpoint reachability
- MCP Inspector snippet
- AI Agent View (server instructions + tool list)
- PHPUnit results

```bash
# Start (only if not already running) and test
bash scripts/run_mcp_tests.sh
```

The report is written to `reports/mcp_report-YYYYMMDD-HHMMSS.md` and ignored by Git (`reports/.gitkeep` retained).

### Optional upstream smoke check

If `CAPCORN_BASE_URL` is set, the verification script will attempt a rooms search against the upstream API and include the response snippet in the report.

## MCP Inspector

Launch an interactive inspector to exercise the endpoint locally:

```bash
php artisan mcp:inspector mcp/capcorn
```

Note: In CI/non-interactive runs the verification script captures an initial snippet only and then terminates the inspector.

## Project structure (key files)

- `app/Mcp/CapCornServer/CapCornServer.php`
- `app/Mcp/CapCornServer/Tools/`
- `routes/ai.php`
- `config/services.php`
- `scripts/start_mcp_server.sh`
- `scripts/run_mcp_tests.sh`
- `docs/DEPLOYMENT-CLOUD-RUN.md`

## Cloud Run deployment

A Dockerfile and GitHub Actions workflow are provided to deploy to Google Cloud Run with public ingress. See `docs/DEPLOYMENT-CLOUD-RUN.md` for step-by-step instructions, including Artifact Registry setup, service account, secrets, and environment variables.

## Testing

Run the Laravel test suite:

```bash
php artisan test
```

## Security notes

- External API credentials are not required for verification; the suite runs without contacting CapCorn unless `CAPCORN_BASE_URL` is set.
- Logs are emitted to `storage/logs` locally and stderr in Cloud Run.

## License

MIT
