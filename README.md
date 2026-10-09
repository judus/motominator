# Motominator

An exploratory motorcycle project. Start with reliable data and enjoyable experiments;
let the product emerge. AI is a secondary capability grounded in identifiable sources.

## Workspace

- `apps/server`: Laravel 13 API, AI SDK, Boost and Sail; Composer owns PHP dependencies.
- `apps/web`: React, TypeScript and Vite browser application.
- `apps/mobile`: React Native, Expo SDK 57 and Expo Router for Android and iOS.
- `packages`: shared client packages when a concrete need appears.
- `.context`: project intent, decisions and setup history. Start at [.context/README.md](.context/README.md).
- `docs`: technical notes, including the [Hetzner deployment direction](docs/deployment.md).

Each app owns its configuration and build. Clients communicate with Laravel over HTTP.
The root npm workspace has one JavaScript lockfile; Laravel has its own Composer lockfile.

## Local setup

Requires Docker with Compose, a working Docker daemon, Composer 2, host PHP 8.4.1+
for bootstrapping, Node.js 24, npm 11, and `just`.
Sail supplies PHP 8.5, MySQL 8.4, Redis and Mailpit. Browser/Expo development runs on the host.

From the repository root:

```sh
just setup
```

This installs PHP/JS dependencies, creates the server `.env` if absent, starts Sail,
generates an application key only when absent, and migrates the database.
The local PHP 8.5 stack, Horizon and scheduler are running and verified.
See [.context/03-sail-ci-and-deployment.md](.context/03-sail-ci-and-deployment.md) for verification details.

On later visits:

```sh
just up
```

In separate terminals:

```sh
just web
just mobile
```

The equivalent `npm run dev:web` and `npm run dev:mobile` remain available.
`npm run dev:server` starts Sail; `just` is the main server management interface.
Run `just` to list all helpers.

## Local services

| Service       | Host address/port       | Container host    |
| ------------- | ----------------------- | ----------------- |
| Laravel API   | `http://localhost:8000` | `laravel.test:80` |
| MySQL         | `127.0.0.1:3306`        | `mysql:3306`      |
| Redis         | `127.0.0.1:6379`        | `redis:6379`      |
| Mailpit inbox | `http://localhost:8025` | `mailpit:8025`    |
| Mailpit SMTP  | `localhost:1025`        | `mailpit:1025`    |

Local MySQL credentials are `sail` / `password`, database `motominator`.
Sail also creates a separate `testing` database for tests on first MySQL initialization.
Queues use Redis and are processed by Horizon. `just up` starts Horizon and the
scheduler alongside the API. Cache and sessions continue to use the database.
Mail is sent to Mailpit using SMTP. No AI credentials are required to run the scaffold.

Local development dashboards are available at [Horizon](http://localhost:8000/horizon)
and [Telescope](http://localhost:8000/telescope). Telescope records API requests,
queries, jobs and other application activity. Debugbar appears on Laravel HTML pages
such as `/`; it does not add a toolbar to the separate React or Expo applications.
Telescope and Debugbar are development dependencies registered only in the `local`
environment. Horizon's dashboard denies access outside `local` until an authorization
policy is configured.

The scheduler records Horizon metrics every five minutes and prunes Telescope entries
older than 24 hours daily. After changing worker code or configuration, run
`just artisan horizon:terminate`; the Horizon container restarts it with the changes.

This checkout forwards MySQL on **3307** because 3306 is already occupied locally.
Fresh checkouts use the default 3306 shown above.

Ports are configurable in `apps/server/.env`: `APP_PORT`, `FORWARD_DB_PORT`,
`FORWARD_REDIS_PORT`, `FORWARD_MAILPIT_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT`.
Sail reserves `VITE_PORT=5174` so the dedicated browser app can use Vite's default 5173.
Compose uses the explicit project name `motominator` to isolate containers and volumes.
Sail's default published ports may be reachable from other machines; use the stack on a trusted network.

Both clients have a **Check server** button calling `GET /api/v1/status`.
It returns `{"name":"Motominator","status":"ok"}` and checks HTTP connectivity,
not database readiness or external providers. Laravel's starter welcome page remains at `/`.

## Server helpers

```sh
just status
just logs
just artisan route:list
just migrate
just test
just queue
just scheduler
just composer show --direct
just shell
just stop
just down
```

`just queue` and `just scheduler` follow their container logs; Ctrl-C stops following
the logs. Horizon and the scheduler run with the Sail stack.
`stop` preserves containers; `down` removes containers/networks and preserves named volumes.
The existing SQLite file from the initial scaffold is retained but is no longer the local database.

## Connecting a phone

Copy `apps/mobile/.env.example` to `apps/mobile/.env` and set
`EXPO_PUBLIC_API_BASE_URL` to your computer's LAN address, including port 8000.
For the Android emulator, use `http://10.0.2.2:8000`. On a physical phone,
`127.0.0.1` refers to the phone itself. Keep both devices on a reachable network;
Sail already publishes the API port. Restart Expo after changing the URL.

The browser URL is configurable through `apps/web/.env` using `VITE_API_BASE_URL`.
Client environment variables are public. Keep credentials in the server environment.
The status endpoint remains public. Authentication packages are installed, but client
sign-in flows and credentialed browser CORS are not implemented yet.

## Authentication baseline

Laravel Fortify provides headless account endpoints; Sanctum supplies browser session
and API/mobile token support. Socialite is installed for built-in providers only,
with empty Google/GitHub settings in the server `.env.example`. No provider credentials
or social callback routes are included. Apple adapters and Passport are deferred.

Set server `FRONTEND_URL` for browser redirects and password reset links. Local Vite
defaults to `http://localhost:5173`; the reset-password page is still to be built.
Implementation work is tracked in [GitHub issues](https://github.com/judus/motominator/issues),
starting with [browser authentication #1](https://github.com/judus/motominator/issues/1).
Known npm advisory findings are tracked separately in [#7](https://github.com/judus/motominator/issues/7).

## Server administration

Filament provides the server admin UI at `/admin`, with login at `/admin/login`
and public account registration at `/admin/register`. Registered accounts are regular
users: registration signs them out with confirmation, and administrator access must
be granted separately. The admin panel lists, creates and edits users. Administrator
status is read-only there; account deletion is not enabled.

To create the first administrator, register an account, then run:

```sh
just artisan app:grant-admin your-email@example.com
```

If public registration is disabled, create an account interactively with
`just artisan make:filament-user --panel=admin`, then grant access with the command
above. No default administrator or password is seeded. Admin permissions are enforced
in local development as well as other environments.

Set `AUTH_REGISTRATION_ENABLED=false` in the server environment and run
`just artisan optimize:clear` to disable both Filament and Fortify public registration.
Administrators can still create users through the panel. Login remains enabled.

The login page links to password recovery at `/admin/password-reset/request`.
Reset emails return to Filament and update the shared account password. Administrators
must verify their email before entering the panel; the verification screen offers a
resend link. Reset and panel verification notifications are queued through Redis and
Horizon. During local development, read those emails in Mailpit at `http://localhost:8025`.

Filament uses the shared Laravel `web` session guard and challenges confirmed Fortify
two-factor secrets on login. Two-factor enrollment and recovery-code management are available in the React account
settings; the admin login supports authenticator codes.
Changing a user's email in the panel clears email verification. Leaving its password
blank while editing preserves the existing password.

## Checks and builds

```sh
just check
just test-all
npm run build:web
npm run build:server
npm run build:mobile
```

`build:mobile` exports Android/iOS JavaScript bundles and the optional Expo web preview;
it does not produce installable native binaries. Native builds/signing are future steps.
Local iOS simulator/build tools require macOS.

| App     | Tests                                                                       | Analysis                                    | Formatting                    |
| ------- | --------------------------------------------------------------------------- | ------------------------------------------- | ----------------------------- |
| Server  | PHPUnit 12 via `just test`                                                  | Larastan/PHPStan level 5 via `just analyse` | Laravel Pint (Laravel preset) |
| Browser | Vitest + React Testing Library via `npm run test:web`                       | TypeScript + scaffold Oxlint config         | Prettier defaults             |
| Mobile  | Jest + `jest-expo` + React Native Testing Library via `npm run test:mobile` | TypeScript + Expo ESLint config             | Prettier defaults             |

`just check` checks analysis and formatting across all apps. `just test-all` runs all
three test suites; `npm test` runs the two client suites only. `just format` applies
PHP/client formatting. Tests also have app-scoped watch scripts (`npm run test:watch
--workspace=@motominator/web` or `@motominator/mobile`). Mobile tests live outside
`src/app` so Expo Router does not treat them as routes. Playwright also checks real browser cookie/CSRF authentication in CI; run it with
`npm run test:e2e --workspace=@motominator/web` after PHP tests. Native emulator
verification and its limits are recorded in [docs/authentication.md](docs/authentication.md).

PHPUnit stays in place while the Pest choice remains open. Larastan includes application,
route, bootstrap, database and test code. Its only current suppression is Laravel's
intentional `assertTrue(true)` starter test; there is no generated analysis baseline.

GitHub Actions runs Composer validation, PHP formatting/analysis, MySQL migrations/server tests,
client lint/type/format checks and tests, and all three asset/bundle builds on pushes and pull requests.
It does not deploy. See [docs/deployment.md](docs/deployment.md).

## Agent guidance and helpers

Laravel Boost generated `apps/server/AGENTS.md`, `.agents/skills/` and `.codex/config.toml`.
Run Boost through Sail from `apps/server`; its MCP config supports server-only sessions.
Refresh with `vendor/bin/sail artisan boost:update` there. App-specific monorepo guidance
lives in `apps/server/.ai/guidelines/monorepo.blade.php` and survives regeneration.
Root `AGENTS.md` now routes work to each app. Expo guidance lives in
`apps/mobile/AGENTS.md`, with 12 pinned official Expo skills under its `.agents/skills`.
The browser has a small locally maintained guide linked to React's official docs.

Start Codex from the repository root; `.codex/config.toml` registers Boost, Expo MCP
and Chrome DevTools MCP, with Boost pointed at `apps/server`. Chrome runs headless
with an isolated temporary profile for browser checks. Run `codex mcp login expo` if Expo OAuth is
needed. `just mobile-mcp` enables Expo's local DevTools integration after Expo CLI login.
The Sail PHP container must be running for Boost. See
[docs/agent-tooling.md](docs/agent-tooling.md) for setup and verification details.

Setup history, dependency findings and verification limits are in `.context/`.

## Authentication implementation

See [docs/authentication.md](docs/authentication.md) for browser sessions, recovery,
Socialite identities, two-factor settings, native device tokens and deferred external
configuration. Current dependency blockers are in [docs/dependency-audit.md](docs/dependency-audit.md).

## License

Copyright (c) 2026 Julien Duseyau. All rights reserved. Motominator is proprietary
and is not currently offered under an open-source license. See [LICENSE](LICENSE).
Third-party dependencies and scaffold code retain their own licenses, including
the Expo notice in [apps/mobile/LICENSE](apps/mobile/LICENSE).
