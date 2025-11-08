# CapCorn Hotel MCP Server

A focused Laravel application that exposes a Model Context Protocol (MCP) server for hotel room discovery and reservations via an upstream CapCorn API. It is designed to be simple, secure, and easy to extend with new MCP tools.

Key capabilities:
- Search available rooms for flexible stays within a timespan
- Check direct room availability for specific dates
- Create reservations with guest details and pricing

MCP endpoint:
- /mcp/capcorn (web-transport MCP server)

Quick references:
- Route registration: [routes/ai.php](routes/ai.php)
- Server class: [class CapCornServer extends Server](app/Mcp/CapCornServer/CapCornServer.php:10)
- Tools: [app/Mcp/CapCornServer/Tools/](app/Mcp/CapCornServer/Tools/)
  - [class SearchRoomsTool extends Tool](app/Mcp/CapCornServer/Tools/SearchRoomsTool.php:12)
  - [class SearchRoomAvailabilityTool extends Tool](app/Mcp/CapCornServer/Tools/SearchRoomAvailabilityTool.php:12)
  - [class CreateReservationTool extends Tool](app/Mcp/CapCornServer/Tools/CreateReservationTool.php:12)
- Service configuration: [config/services.php](config/services.php)
- Deployment guide: [docs/DEPLOYMENT-CLOUD-RUN.md](docs/DEPLOYMENT-CLOUD-RUN.md)
- Useful scripts: [scripts/run_mcp_tests.sh](scripts/run_mcp_tests.sh), [scripts/run_cloudrun_checks.sh](scripts/run_cloudrun_checks.sh), [scripts/start_mcp_server.sh](scripts/start_mcp_server.sh)

NOTE ON CLEANUP
- The project was streamlined. Unused scaffolding was removed: legacy auth User model/migration/factory, unused services, and an unused mailable. Config/services.php now only keeps capcorn.base_url.


## 1) Architecture Overview

- HTTP entrypoints for MCP are declared in [routes/ai.php](routes/ai.php). We use Laravel MCP to mount a server at /mcp/capcorn.
- The server class [class CapCornServer extends Server](app/Mcp/CapCornServer/CapCornServer.php:10) defines:
  - protected string $name, protected string $version
  - protected string $instructions (LLM-facing guidance)
  - protected array $tools: the MCP Tool classes that implement the actual functionality
- Each tool extends Laravel MCP Tool and implements:
  - protected string $description (displayed to the LLM)
  - public function handle(Request): Response (core logic)
  - public function schema(JsonSchema): array (strongly-typed parameters for tool invocation)

Data flow (typical):
1) Client prompts the LLM
2) LLM chooses a tool and calls the MCP server (over HTTP)
3) The Tool validates/normalizes input, calls the upstream CapCorn API via HTTP and formats a human-friendly result (plain text response)
4) The LLM presents the tool result to the user, possibly chaining multiple tools


## 2) Current Tools

- [class SearchRoomsTool extends Tool](app/Mcp/CapCornServer/Tools/SearchRoomsTool.php:12)
  - Flexible stay search within a timespan with a given duration. Generates all date ranges in the period and queries them.
  - Parameters include language, timespan.from/to, duration, adults, children[].

- [class SearchRoomAvailabilityTool extends Tool](app/Mcp/CapCornServer/Tools/SearchRoomAvailabilityTool.php:12)
  - Direct availability for exact arrival/departure dates and room compositions.
  - Parameters include language (0/1), arrival, departure, rooms[*].

- [class CreateReservationTool extends Tool](app/Mcp/CapCornServer/Tools/CreateReservationTool.php:12)
  - Create a reservation using a room_type_code from availability results, with guest information and optional services.
  - Validates guest counts and fields, returns a concise confirmation or a formatted error list.


## 3) Configuration

Only one config block is required for CapCorn:

- [config/services.php](config/services.php)
  - capcorn.base_url: Upstream HTTP base for the CapCorn API used by all tools.

Environment:
- CAPCORN_BASE_URL=https://lookingcom-backend.vercel.app
  - Override in your .env to point to your upstream (local or remote).

Auth scaffolding:
- This app does not ship with a runtime user model or auth routes. In case you later add one, [config/auth.php](config/auth.php) still references a model string via env('AUTH_MODEL'), which is safe even if no model exists.  


## 4) Running Locally

Prerequisites:
- PHP 8.2+, Composer, curl

Install:
```bash
composer install
cp .env.example .env
php artisan key:generate
```

Start the app:
```bash
php artisan serve
# App: http://localhost:8000
# MCP endpoint: http://localhost:8000/mcp/capcorn (MCP over HTTP POST)
# Metadata (JSON): http://localhost:8000/mcp/capcorn/meta
```

MCP Inspector (interactive local test):
```bash
php artisan mcp:inspector mcp/capcorn
```

The /mcp/capcorn path speaks MCP (streamed HTTP POST). A GET on /mcp/capcorn will return 405 (expected). Use the /mcp/capcorn/meta helper to discover server name, version, instructions and tool list.


## 5) Testing and Verification

PHPUnit:
```bash
php artisan test
```

End-to-end MCP verification:
- [scripts/run_mcp_tests.sh](scripts/run_mcp_tests.sh) starts a local server (if needed), performs sanity checks, and writes a Markdown report under reports/ (ignored by Git).
```bash
bash scripts/run_mcp_tests.sh
# Output: reports/mcp_report-YYYYMMDD-HHMMSS.md
```

Cloud Run smoke checks (remote):
- After deployment, you can perform quick probes:
```bash
SERVICE_URL="$(gcloud run services describe mcp-hotel-server \
  --region europe-west1 --format='value(status.url)')"

curl -i "$SERVICE_URL/"
curl -i "$SERVICE_URL/mcp/capcorn/meta"  # MCP metadata (JSON)
# Note: GET "$SERVICE_URL/mcp/capcorn" is expected to return 405
```

CI checks (locally or to simulate CI):
- [scripts/run_cloudrun_checks.sh](scripts/run_cloudrun_checks.sh)
  - Lints repo structure and performs a small set of dry-run checks against your config and workflow.


## 6) Adding A New MCP Tool

1) Create a new Tool class under app/Mcp/CapCornServer/Tools, e.g. MyNewTool.php:
```php
<?php

namespace App\Mcp\CapCornServer\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Http;
use Illuminate\JsonSchema\JsonSchema;

class MyNewTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Short summary of what the tool does and when to use it.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'param' => 'required|string',
        ]);

        // Call upstream or implement your logic
        // $baseUrl = config('services.capcorn.base_url');
        // $resp = Http::post($baseUrl.'/api/v1/some-endpoint', [ 'param' => $validated['param'] ]);

        // Return a textual result (MCP transports plain text here)
        return Response::text("Result for {$validated['param']}");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'param' => $schema->string()->description('Description of param'),
        ];
    }
}
```

2) Register it in the server tool list [class CapCornServer extends Server](app/Mcp/CapCornServer/CapCornServer.php:10), by adding your class to protected array $tools:
```php
protected array $tools = [
    \App\Mcp\CapCornServer\Tools\SearchRoomsTool::class,
    \App\Mcp\CapCornServer\Tools\SearchRoomAvailabilityTool::class,
    \App\Mcp\CapCornServer\Tools\CreateReservationTool::class,
    \App\Mcp\CapCornServer\Tools\MyNewTool::class, // <-- add this line
];
```

3) Keep the tool’s description concise and helpful; ensure schema types match accepted inputs. Always validate inputs in handle() using $request->validate([...]) to safeguard the upstream and provide predictable UX.

4) If you add new configuration keys, place them under [config/services.php](config/services.php) and read them via config('services.capcorn...') or a new root-level service block.


## 7) Deployment (Google Cloud Run)

This repo includes a Dockerfile and a GitHub Actions workflow that:
- Builds a container image
- Pushes it to Artifact Registry
- Deploys it to Cloud Run with public ingress

Workflow:
- [deploy-cloudrun.yml](.github/workflows/deploy-cloudrun.yml)
  - Requires a GitHub secret GCP_SA_KEY (JSON of a GCP service account with roles: run.admin, artifactregistry.writer, iam.serviceAccountUser)
  - Uses google-github-actions/auth for workload identity via the JSON secret

First-time setup steps and full instructions:
- [docs/DEPLOYMENT-CLOUD-RUN.md](docs/DEPLOYMENT-CLOUD-RUN.md)

After deployment, fetch the service URL:
```bash
gcloud run services describe mcp-hotel-server \
  --region europe-west1 --format='value(status.url)'
```

Public endpoints:
- Root: GET / (welcome page)
- MCP Server metadata: GET /mcp/capcorn/meta
- MCP: POST /mcp/capcorn (MCP transport; GET returns 405 by design)


## 8) Security Notes

- Never commit plaintext cloud keys. The repository ignores .env and now also ignores gcp-sa-key.json.
- Use GitHub secrets for CI/CD (GCP_SA_KEY) and Cloud Run environment variables for runtime configuration.
- If a JSON key was ever committed, revoke and rotate it in GCP IAM, scrub it from repository history (BFG / git filter-repo), and force-push the cleaned history if needed.


## 9) Troubleshooting

- GET to /mcp/capcorn shows 405
  - Correct. MCP web transport expects POST/stream. Use /mcp/capcorn/meta for static JSON metadata.
- Autoload issues after adding/removing classes
  - Run composer dump-autoload -o
- Upstream API connectivity
  - Ensure CAPCORN_BASE_URL is reachable from the environment you run in.
- Container networking / PORT
  - Cloud Run injects PORT; our entrypoint respects it. Locally, default is 8080 unless overridden.


## 10) Conventions and Guidelines

- Keep tools small and composable. Present clear, human-readable output strings.
- Validate every input. Fail fast and return actionable error text.
- Log errors with context (avoid secrets) to simplify operations.
- Document new endpoints or parameters in the tool description and the README to keep human operators in the loop.


## Appendix: Project Structure (key paths)

- Server: [class CapCornServer extends Server](app/Mcp/CapCornServer/CapCornServer.php:10)
- Tools:
  - [class SearchRoomsTool extends Tool](app/Mcp/CapCornServer/Tools/SearchRoomsTool.php:12)
  - [class SearchRoomAvailabilityTool extends Tool](app/Mcp/CapCornServer/Tools/SearchRoomAvailabilityTool.php:12)
  - [class CreateReservationTool extends Tool](app/Mcp/CapCornServer/Tools/CreateReservationTool.php:12)
- Route registration: [routes/ai.php](routes/ai.php)
- Config: [config/services.php](config/services.php)
- Docker/Runtime: [Dockerfile](Dockerfile), [docker/entrypoint.sh](docker/entrypoint.sh)
- CI/CD: [.github/workflows/deploy-cloudrun.yml](.github/workflows/deploy-cloudrun.yml)
- Scripts: [scripts/run_mcp_tests.sh](scripts/run_mcp_tests.sh), [scripts/run_cloudrun_checks.sh](scripts/run_cloudrun_checks.sh), [scripts/start_mcp_server.sh](scripts/start_mcp_server.sh)
- Reports: [reports/](reports/)
