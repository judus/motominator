# Hetzner deployment direction

Status: intended architecture, not a deployable configuration. No server was contacted,
no production resources were changed, and no deployment workflow is active.

## Existing CompanionAI reference

Inspected the local checkouts under `/home/maduser/workspace/companion-ai`:

- `companion-ai-backend/.github/workflows/deploy.yml`
- `companion-ai-backend/docker-compose.yml`, `.docker/Dockerfile` and `justfile`
- `react-companion-ai/.github/workflows/deploy.yml`, `docker-compose.yml` and `.docker/Dockerfile`
- `maduser-server/docker-compose.yml`

These files show GitHub Actions triggered by pushes to a `hetzner` branch, SSH into
a project checkout, and Docker Compose build/restart commands on the server.
The backend has PHP-FPM, nginx, Redis, a queue worker and Reverb. The browser app
builds with Node and serves static assets through nginx. Traefik provides the shared
edge on the external `proxy` network; shared MariaDB lives on the `shared` network.
This is evidence from local files, not verification of the currently running server.

## Intended Motominator shape

- One repository and CI, with separately buildable/deployable server and browser services.
- A production PHP image for `apps/server` plus nginx or another chosen production HTTP server.
- A static browser image built from `apps/web`, with the root npm workspace as build context.
- Horizon workers using the same PHP release and Redis queues; a scheduler for
  Horizon metrics and application tasks.
- Redis and a production database with persistent storage and backups.
- HTTP-facing services attached to the Hetzner `proxy` network if the existing edge is reused.
- Mobile builds distributed through the appropriate mobile channels; they consume the API
  and are not hosted as native apps on Hetzner.

Sail, its development credentials and Mailpit are local development tools.
Do not reuse the Sail Compose file as the production deployment.
No Reverb service is needed until a feature calls for it.
Telescope and Debugbar are local development dependencies and are not registered
outside the `local` environment. Production Horizon dashboard access requires an
explicit authorization policy; the current gate denies all non-local access.

## Decisions needed before implementing deployment

- Actual domains, checkout/release paths, Compose project name and SSH deployment identity.
- Whether to reuse shared MariaDB or provision an isolated MySQL database. Local MySQL
  compatibility does not establish production MariaDB compatibility.
- Browser/API origin and authentication/cookie configuration; replace public scaffold CORS
  when the application's authentication requirements are defined.
- Production mail provider, AI/catalogue credentials, persistent files and secret provisioning.
- Image build location, registry if any, and the deployment trigger/branch/environment.
- Health/readiness checks, migrations, worker restarts, backup/restore and rollback procedure.

Use the CompanionAI layout as a reference while designing a reviewable deployment.
Its current scripts tear down containers before building, install dependencies into running
containers, and use autostash during pulls. Those operations need deliberate review here;
a future workflow should build a release before switching traffic, keep secrets outside images,
and avoid silently changing the state of the deployment checkout.

The repository currently contains CI only. Add deployment configuration once its actual
inputs are known and it can be reviewed concretely.
