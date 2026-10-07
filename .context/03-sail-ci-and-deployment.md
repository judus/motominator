# Sail, CI, helpers and deployment direction — 2026-10-07

Supersedes the initial SQLite/host-PHP development setup in `02-bootstrap.md`.

## Local development

Installed `laravel/sail` 1.68.0 and ran its installer with `mysql,redis,mailpit`.
Kept its default PHP 8.5 runtime, MySQL 8.4, Redis Alpine and Mailpit images.
The generated Compose configuration is in `apps/server/compose.yaml`.

Small workspace adaptations:

- Explicit Compose project name `motominator` isolates containers, networks and volumes.
- API host port 8000 preserves existing browser/mobile configuration.
- Sail Vite host port 5174 avoids competing with the separate browser app on 5173.
- Database `motominator`, standard local Sail credentials, SMTP to `mailpit:1025`.
- Redis is available at `redis:6379`; default database cache/session/queue drivers remain.
- Updated both the tracked environment template and existing local environment.
- Preserved the original SQLite file; no user data was migrated or removed.
- Sail's installer configures PHPUnit to use a separate MySQL `testing` database.
- Boost regenerated with Sail support. A custom app-scoped monorepo guideline explains
  host JavaScript workspaces, the Composer bootstrap exception, CI and the Hetzner direction.

Sail publishes service ports using its defaults; local tools should run on a trusted network.
No production credentials are included.

## CLI helpers

The root `justfile` manages Sail lifecycle and server commands. `just setup` installs
workspace dependencies, starts the stack, generates a missing key and applies migrations.
`just up`/`stop`/`down`/`status`/`logs` handle the local stack. `just artisan`, `composer`,
`sail` and `test` forward arguments using positional arguments, preserving spaces.
Queue and scheduler helpers run in the foreground.

Browser/Expo commands remain npm workspace scripts; `just web`, `mobile` and `check`
are conveniences. `npm run dev:server` now starts Sail and `npm run test:server` uses Sail.
The old host-PHP/LAN npm server helpers have been replaced by the published Sail API port.

## GitHub Actions

`.github/workflows/ci.yml` runs on pushes, pull requests and manual dispatch.
It grants read-only repository access and cancels superseded runs per ref.

- Server job: PHP 8.5, Composer validation/install, Compose config validation,
  Pint formatting check, migrations and PHPUnit against MySQL 8.4; Redis is also provided.
- Client job: Node 24/npm 11, root lockfile installation, lint, TypeScript checks,
  browser/Laravel asset builds and Expo Android/iOS/web bundle export.

No deployment job is enabled. The GitHub repository/remote has not been created or pushed,
so hosted execution is still to be verified after publication.

## Hetzner reference

Read the local CompanionAI backend, frontend and shared server deployment files.
They show GitHub Actions -> SSH -> Docker Compose, with Traefik on `proxy`, shared
MariaDB on `shared`, separate PHP/nginx and static browser services, and workers.
This was read-only inspection of local files, not an SSH audit of the live server.

`docs/deployment.md` records the references, proposed shape and unresolved inputs.
Actual domains/paths, credentials, production database, image delivery and rollback
remain undecided. Sail and Mailpit are development-only. No remote changes were made.

## Verification

- Justfile parses, lists recipes and dry-runs setup/argument forwarding.
- Compose configuration validates.
- CI workflow passes Actionlint 1.7.12.
- Composer manifest/lock validation and PHP formatting pass.
- Client lint and TypeScript checks pass after the tooling changes.

- MySQL, Redis and Mailpit containers started and passed their health checks.
- This checkout uses `FORWARD_DB_PORT=3307` in its ignored `.env` because a local
  database already occupies 3306. The tracked defaults and container port remain 3306.
- Migrations completed against MySQL, including the AI SDK conversation tables;
  the Sail initialization script also created the separate `testing` database.
- All 4 server tests passed (7 assertions).
- Redis returned `PONG`; Laravel sent a test message through SMTP to local Mailpit.

Runtime limitation: the standard Sail PHP 8.5 image build was stopped after repeated
Ubuntu package download timeouts. Migrations, tests and SMTP were verified using an
already cached PHP 8.4 Sail image in disposable containers on the same Compose network.
The configured PHP 8.5 application container has not started. The three infrastructure
containers remain running. Retry `just setup` to finish the first PHP 8.5 build and start
Laravel; GitHub-hosted CI has not run yet.

## Follow-up: Redis queues and development diagnostics — 2026-10-07

The standard Sail PHP 8.5 application is now running. Boost's live application-info
call confirmed PHP 8.5 and Laravel 13.35.0. This supersedes the runtime limitation above.

- Switched the queue default, environment template and local `.env` to Redis. The
  database queue contained no pending jobs. Cache and sessions retain the database driver.
- Installed Horizon 5.50.0 as a runtime dependency, Telescope 5.25.0 and
  fruitcake/laravel-debugbar 4.4.4 as development dependencies.
- Telescope and Debugbar are excluded from package discovery and registered only
  in the local environment. Horizon retains its default deny gate outside local.
- Published Horizon/Telescope configuration and providers, and applied Telescope's
  migration to the development database.
- Sail now starts dedicated Horizon and scheduler services using the same PHP 8.5 image.
  Horizon uses Redis's default queue, up to three local processes, three attempts and
  a 60-second worker timeout, below Redis queue's 90-second reservation timeout.
- Scheduled Horizon snapshots every five minutes and local Telescope pruning daily
  with the default 24-hour retention. `just queue` / `just scheduler` now follow logs.
- CI explicitly requests pcntl and posix for Horizon. Hetzner remains the production
  direction; no production deployment or account linking was performed.

Verification: all 9 server tests pass (12 assertions), Larastan and Pint pass, Composer
manifest/lock validation and Sail Compose validation pass. A real no-recipient
notification job was dispatched through the configured Redis queue and completed by
Horizon. Both dashboards returned HTTP 200; Laravel's welcome page includes Debugbar,
and the API status endpoint still returns its exact JSON response. Telescope recorded
request, query, Redis and job entries. Production-mode route listings contain no
Telescope or Debugbar routes. Horizon reports running and stores metrics snapshots;
the scheduler lists the snapshot and pruning tasks. Browser interaction and hosted CI
were not exercised. The ignored Larastan cache had root-owned files from earlier runs;
ownership was restored to Sail so normal analysis can run.

## Authentication package installation

Installed server runtime dependencies Fortify 1.41.0, Sanctum 4.3.3 and Socialite
5.31.0 through Sail Composer. Published Fortify actions/provider/configuration and
Sanctum configuration, and applied the two-factor user columns and personal access
token migrations locally. The User model supports email verification, two-factor
authentication and Sanctum tokens; two-factor secrets are hidden from serialization.

Fortify runs without server-rendered views, with registration, password recovery,
email verification, profile/password updates and optional confirmed two-factor
authentication enabled. Passkeys remain disabled; the installer's unused passkey
migration was removed before migration. Fortify pulls in laravel/passkeys as a
dependency, which does not mean passkey login is enabled.

Sanctum stateful API middleware is enabled and includes the local Vite origin on
port 5173. Fortify redirects and reset-password email links use FRONTEND_URL
(default http://localhost:5173). The corresponding client pages do not exist yet.
Google/GitHub service settings and empty .env.example placeholders are present;
no provider credentials, social callback routes or account linking were added.
No mobile token issuance or token expiration policy is implemented yet. Sanctum's
published token expiration remains its default null. Browser credentialed CORS and
production origins/cookies must be configured with the client authentication work.

Installation checks: Composer manifest/lock validation, Pint formatting and Larastan
passed. Artisan lists Fortify account routes and Sanctum's CSRF cookie route.
No tests or real provider sign-ins were run during this installation.

## GitHub setup baseline — 2026-10-08

Created private repository https://github.com/judus/motominator with SSH origin.
The user authorized the initial commit/push and asked to finish setup before feature
implementation. GitHub issues #1–#6 own the authentication next steps; #7 owns the
existing npm advisories. No separate implementation roadmap Markdown file is needed.

Current local checks, all 17 existing tests and all three build/export commands pass;
see note 04 for counts and limits. Local credentials, vendor/node_modules, logs,
databases and build output are ignored. Root MCP config retains this checkout's
absolute Boost path; clones elsewhere must adjust it as documented in agent-tooling.
GitHub Actions is configured to run the baseline gates on every push and pull request.
Its live results are available in the repository Actions tab.
