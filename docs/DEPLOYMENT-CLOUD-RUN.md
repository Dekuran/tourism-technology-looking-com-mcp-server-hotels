# Deploying Laravel MCP Server to Google Cloud Run

This guide deploys the MCP Tourism/DSAPI Laravel server to Google Cloud Run in your project:
- Project ID: tourism-hackathon-temp-project
- Region: europe-west1
- Service name: mcp-hotel-server
- Public access enabled (MCP clients can reach your endpoints)

CI/CD is configured via GitHub Actions to build a container image, push to Artifact Registry, and deploy to Cloud Run on every push to main.

## 1) Prerequisites

- Billing enabled on the GCP project
- gcloud CLI installed and logged in (for local verification)
- GitHub repository connected (this repo)

Enable APIs (one-time):
```bash
gcloud services enable \
  run.googleapis.com \
  artifactregistry.googleapis.com \
  iam.googleapis.com
```

## 2) Service Account and GitHub Secret

Create a purpose-built Service Account for CI:
```bash
PROJECT_ID="tourism-hackathon-temp-project"
SA_NAME="gh-actions-deployer"
SA_EMAIL="${SA_NAME}@${PROJECT_ID}.iam.gserviceaccount.com"

gcloud iam service-accounts create "$SA_NAME" \
  --project "$PROJECT_ID" \
  --display-name "GitHub Actions Cloud Run Deployer"
```

Grant required roles:
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

Create a JSON key (store securely):
```bash
gcloud iam service-accounts keys create ./gcp-sa-key.json \
  --iam-account "$SA_EMAIL" \
  --project "$PROJECT_ID"
```

Add GitHub Secret:
- In GitHub: Settings → Secrets and variables → Actions → New repository secret
- Name: GCP_SA_KEY
- Value: paste the contents of gcp-sa-key.json

Then delete the local key file if desired:
```bash
shred -u ./gcp-sa-key.json
```

## 3) CI/CD Workflow

The GitHub Action [deploy-cloudrun.yml](.github/workflows/deploy-cloudrun.yml) builds and deploys on push to main:
- Creates Artifact Registry repo if missing
- Builds Docker image from [Dockerfile](Dockerfile)
- Pushes to europe-west1-docker.pkg.dev
- Deploys Cloud Run service with:
  - --allow-unauthenticated (public)
  - --ingress all (public from internet)
  - MCP-friendly streaming over HTTP
  - MASTERCARD_MOCK=true (tests pass without live credentials)

Important workflow env defaults:
- PROJECT_ID: tourism-hackathon-temp-project
- REGION: europe-west1
- SERVICE: mcp-hotel-server
- GAR_REPO: mcp-hotel-server

Adjust inside [.github/workflows/deploy-cloudrun.yml](.github/workflows/deploy-cloudrun.yml) if needed.

Trigger a deploy:
- Push to main, or
- Manually: Actions → Deploy to Cloud Run → Run workflow

The workflow prints the service URL as a summary output.

## 4) Runtime Image, EntryPoint, and Public Access

- Container is built from [Dockerfile](Dockerfile) using multi-stage Composer build
- Entrypoint: [docker/entrypoint.sh](docker/entrypoint.sh)
  - Generates APP_KEY if missing
  - Caches config/views
  - Serves public/ via PHP built-in server on $PORT (Cloud Run injects PORT)
- Public access is enabled via:
  - --allow-unauthenticated
  - --ingress all

Cloud Run will expose a URL like:
```
https://mcp-hotel-server-xxxxxxxx-uc.a.run.app
```

## 5) MCP Endpoints and Quick Checks

MCP servers (HTTP endpoints) are registered in [routes/ai.php](routes/ai.php):
- Tourism Server: /mcp/tourism
- DSAPI Server: /mcp/dsapi

Public URLs after deploy:
- Root (Welcome): https://YOUR_SERVICE_URL/
- Tourism MCP: https://YOUR_SERVICE_URL/mcp/tourism
- DSAPI MCP: https://YOUR_SERVICE_URL/mcp/dsapi

Note: GET on /mcp endpoints returns 405 (Method Not Allowed) because MCP uses streaming HTTP POST and protocol handshakes. That is expected. The Root route (/) should return 200 with the welcome page.

Quick smoke tests:
```bash
SERVICE_URL="$(gcloud run services describe mcp-hotel-server \
  --region europe-west1 --format='value(status.url)')"

curl -i "$SERVICE_URL/"
curl -i "$SERVICE_URL/mcp/tourism"  # expected 405 on GET
curl -i "$SERVICE_URL/mcp/dsapi"    # expected 405 on GET
```

## 6) Environment Variables

By default, workflow sets:
- APP_ENV=production
- LOG_CHANNEL=stderr
- CACHE_DRIVER=file
- SESSION_DRIVER=array
- MASTERCARD_MOCK=true

To use real Mastercard API, set in Cloud Run (and remove the mock):
- MASTERCARD_CONSUMER_KEY
- MASTERCARD_PRIVATE_KEY (PEM contents)
- MASTERCARD_API_URL (e.g., https://api.mastercard.com)
- Remove MASTERCARD_MOCK=true or set to false

Update env vars (any time):
```bash
gcloud run services update mcp-hotel-server \
  --region europe-west1 \
  --set-env-vars "MASTERCARD_MOCK=false,MASTERCARD_CONSUMER_KEY=...,MASTERCARD_PRIVATE_KEY=...,MASTERCARD_API_URL=..."
```

Optional:
- APP_URL can be set to your Cloud Run URL for link generation.

## 7) Local Container Test (Optional)

Build and run locally (port 8080):
```bash
docker build -t mcp-hotel-server:local .
docker run --rm -p 8080:8080 \
  -e APP_ENV=production \
  -e LOG_CHANNEL=stderr \
  -e CACHE_DRIVER=file \
  -e SESSION_DRIVER=array \
  mcp-hotel-server:local

# Then visit http://localhost:8080/
```

## 8) Operations

- Logs: Cloud Logging (Logs Explorer), or `gcloud run services logs read mcp-hotel-server --region europe-west1`
- Revisions: Managed by Cloud Run; can roll back to prior revision easily
- Scaling: min-instances=0, max-instances=3 set in workflow (edit as needed)
- Streaming: MCP inspector and HTTP streaming are supported by Cloud Run

## 9) Security

- Public access is intentionally enabled for MCP clients
- If you later need to restrict access, remove `--allow-unauthenticated` and configure IAM or a custom proxy
- Keep the GitHub Service Account key secret (GCP_SA_KEY). Rotate periodically.

## 10) Repo Artifacts

- CI workflow: [.github/workflows/deploy-cloudrun.yml](.github/workflows/deploy-cloudrun.yml)
- Docker image: [Dockerfile](Dockerfile)
- Entrypoint: [docker/entrypoint.sh](docker/entrypoint.sh)

Once the first deployment finishes, get your service URL and use it in MCP clients:
- Tourism MCP: https://YOUR_SERVICE_URL/mcp/tourism
- DSAPI MCP: https://YOUR_SERVICE_URL/mcp/dsapi

You can also run the included validation before pushing:
- Start server locally and verify: [scripts/all.sh](scripts/all.sh)
- Generate full suite report: [scripts/run_mcp_tests.sh](scripts/run_mcp_tests.sh)
- Focused Booking API report: [scripts/test_booking_api.sh](scripts/test_booking_api.sh)