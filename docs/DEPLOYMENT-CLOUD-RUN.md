# Deploying CapCorn MCP Server to Google Cloud Run

This guide explains how to deploy the CapCorn Hotel MCP Server to Google Cloud Run using the provided Dockerfile and GitHub Actions workflow. The service exposes a single MCP endpoint:

- Server: `/mcp/capcorn`
- Metadata (JSON): `/mcp/capcorn/meta`

Key repo paths:
- Route: [routes/ai.php](routes/ai.php)
- Server: [class CapCornServer extends Server](app/Mcp/CapCornServer/CapCornServer.php:10)
- Tools:
  - [class SearchRoomsTool extends Tool](app/Mcp/CapCornServer/Tools/SearchRoomsTool.php:12)
  - [class SearchRoomAvailabilityTool extends Tool](app/Mcp/CapCornServer/Tools/SearchRoomAvailabilityTool.php:12)
  - [class CreateReservationTool extends Tool](app/Mcp/CapCornServer/Tools/CreateReservationTool.php:12)
- CI workflow: [.github/workflows/deploy-cloudrun.yml](.github/workflows/deploy-cloudrun.yml)
- Runtime entrypoint: [docker/entrypoint.sh](docker/entrypoint.sh)
- Dockerfile: [Dockerfile](Dockerfile)

Note: This app does not depend on a database. It’s a stateless MCP server that proxies to an upstream CapCorn API configured via [config/services.php](config/services.php).


## 1) Prerequisites

- A Google Cloud project with billing enabled
- Google Cloud CLI (gcloud) installed and authenticated (for local checks)
- GitHub repository connected (this repo)
- APIs enabled (one-time):
```bash
gcloud services enable \
  run.googleapis.com \
  artifactregistry.googleapis.com \
  iam.googleapis.com
```


## 2) Service Account and GitHub Secret

Create a dedicated deployer Service Account (names are examples; adjust as needed):
```bash
PROJECT_ID="your-gcp-project-id"
SA_NAME="gh-actions-deployer"
SA_EMAIL="${SA_NAME}@${PROJECT_ID}.iam.gserviceaccount.com"

gcloud iam service-accounts create "$SA_NAME" \
  --project "$PROJECT_ID" \
  --display-name "GitHub Actions Cloud Run Deployer"
```

Grant minimal required roles:
```bash
gcloud projects add-iam-policy-binding "$PROJECT_ID" \
  --member "serviceAccount:${SA_EMAIL}" \
  --role "roles/run.admin"

gcloud projects add-iam-policy-binding "$PROJECT_ID" \
  --member "serviceAccount:${SA_EMAIL}" \
  --role "roles/artifactregistry.writer"

gcloud projects add-iam-policy-binding "$PROJECT_ID" \
  --member "serviceAccount:${SA_EMAIL}" \
  --role "roles/iam.serviceAccountUser"
```

Create a JSON key (store only long enough to add as a GitHub secret):
```bash
gcloud iam service-accounts keys create ./gcp-sa-key.json \
  --iam-account "$SA_EMAIL" \
  --project "$PROJECT_ID"
```

Add GitHub secret:
- GitHub → Settings → Secrets and variables → Actions → New repository secret
- Name: GCP_SA_KEY
- Value: paste the contents of `gcp-sa-key.json`

Important: Do not commit `gcp-sa-key.json` to the repository. The repo’s [.gitignore](.gitignore) prevents this; delete the local file after adding the secret.


## 3) CI/CD Workflow

The workflow [.github/workflows/deploy-cloudrun.yml](.github/workflows/deploy-cloudrun.yml) builds and deploys on push to main:
- Authenticates to GCP using `secrets.GCP_SA_KEY`
- Builds a Docker image
- Pushes to Artifact Registry
- Deploys to Cloud Run with public ingress

Default environment variables in the workflow:
- PROJECT_ID
- REGION
- SERVICE
- GAR_REPO

Adjust these via the `env:` block in the workflow if needed.

Trigger a deploy:
- Push to `main`, or
- Actions → “Deploy to Cloud Run” → “Run workflow”


## 4) Cloud Run Service Configuration

The deploy step sets typical defaults for a public HTTP service:
- `--allow-unauthenticated`
- `--ingress all`
- `--timeout 300`
- `--cpu 1`
- `--memory 512Mi`
- `--min-instances 0`
- `--max-instances 3`
- Env vars: `APP_ENV=production, LOG_CHANNEL=stderr, CACHE_DRIVER=file, SESSION_DRIVER=array`

After deployment, fetch the service URL:
```bash
gcloud run services describe "$SERVICE" \
  --region "$REGION" \
  --format='value(status.url)'
```


## 5) Runtime Image and Entrypoint

- Multi-stage Docker build: [Dockerfile](Dockerfile)
- Entrypoint script: [docker/entrypoint.sh](docker/entrypoint.sh)
  - Ensures `APP_KEY`
  - Caches config/views
  - Starts PHP’s built-in server bound to `$PORT` (injected by Cloud Run)


## 6) MCP Endpoints and Quick Checks

Public endpoints (after deploy):
- Root: `GET /` (welcome page)
- MCP metadata: `GET /mcp/capcorn/meta` (JSON with name, version, instructions, tool list)
- MCP server: `POST /mcp/capcorn` (MCP transport; `GET` returns 405 by design)

Quick smoke test:
```bash
SERVICE_URL="$(gcloud run services describe mcp-hotel-server \
  --region europe-west1 --format='value(status.url)')"

curl -i "$SERVICE_URL/"
curl -i "$SERVICE_URL/mcp/capcorn/meta"
# Note: GET "$SERVICE_URL/mcp/capcorn" should return 405
```


## 7) Configuration

Only one config block is required:
- [config/services.php](config/services.php)
  - `capcorn.base_url`: Upstream CapCorn base URL

Set in `.env`:
```env
CAPCORN_BASE_URL=https://your-capcorn-backend.example.com
```


## 8) Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate

php artisan serve
# http://localhost:8000
# MCP: POST http://localhost:8000/mcp/capcorn
# Meta: GET  http://localhost:8000/mcp/capcorn/meta
```

Inspector (optional):
```bash
php artisan mcp:inspector mcp/capcorn
```


## 9) Verification Scripts

Local end-to-end verification producing a Markdown report:
- [scripts/run_mcp_tests.sh](scripts/run_mcp_tests.sh)
```bash
bash scripts/run_mcp_tests.sh
# Outputs a report under ./reports/
```

Remote Cloud Run checks (no local server):
- [scripts/run_cloudrun_checks.sh](scripts/run_cloudrun_checks.sh)
```bash
bash scripts/run_cloudrun_checks.sh --url https://YOUR_SERVICE_URL
```

Both scripts probe only MCP endpoints and metadata. They do not directly call the upstream CapCorn REST API, ensuring testing flows through your tools.


## 10) Security

- Do not commit secret keys; use GitHub Secrets and Cloud Run environment variables.
- The repo ignores `.env` and `gcp-sa-key.json`.
- If a key was ever committed, revoke/rotate it in GCP IAM and scrub it from git history (BFG or `git filter-repo`).


## 11) Troubleshooting

- `GET /mcp/capcorn` returns 405
  - Expected. Use `POST` for MCP traffic or `GET /mcp/capcorn/meta` for metadata.
- Autoload issues after adding/removing classes
  - `composer dump-autoload -o`
- Service not reachable
  - Check Cloud Run logs, ingress settings, and whether your region/service name matches the workflow env vars.