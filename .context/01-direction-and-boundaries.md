# Direction and boundaries

## Product exploration

Potential audiences: riders, mechanic workshops and shops. Group rides are another
possible direction: planning, meeting points, routes and seeing who has reached a stop.
None has been chosen as the product or first feature.

The agreed approach is to investigate reliable online catalogues first: motorcycles,
parts, consumables, clothing/equipment, maintenance references, roads and routes.
Then experiment with what the accessible data makes possible.

For a candidate source, establish coverage, provenance, update frequency, access method,
cost and permitted reuse. Public visibility does not establish permission to republish.
No catalogue provider has been selected or integrated.

AI is secondary. It may help search, extract, translate or explain sourced data.
It must not invent fitment, specifications, repair instructions or maintenance intervals.
Keep source identity and version/date alongside future imported facts.

## Repository and application boundaries

One Git repository, with independently configured applications:

- `apps/server`: Laravel API, persistence, external catalogue integrations, queues and AI.
- `apps/web`: standalone React/TypeScript/Vite browser client.
- `apps/mobile`: React Native/Expo client targeting Android and iOS.
- `packages`: future shared client code, introduced only when useful.

Server credentials stay on the server. Clients consume its HTTP API.
Do not import one app's source directly into another app.
Share client API access or nonvisual logic through explicit packages when needed.
Allow browser and native interfaces to evolve independently.

npm workspaces manage JavaScript dependencies with one root lockfile. Composer manages
PHP dependencies inside the server app. No additional monorepo orchestration is needed yet.
Future API contract/type generation remains an idea; none is installed.

## Current scope

Scaffold frameworks, document decisions, and verify basic client/server wiring.
No catalogue domain model, appointment system, map provider,
AI agent or paid service has been chosen. Hetzner Docker Compose is the envisioned
deployment direction; its concrete configuration is still undecided.

The user initially deferred root agent instructions until official framework guidance
was installed, then explicitly authorized root `AGENTS.md`. It now routes to app-owned
Laravel, React and Expo guidance. Official framework files remain app-scoped.
Root `.codex/config.toml` registers project-scoped MCP servers; start Codex from the
repository root. Launcher wrappers were removed. See note 05.

## Authentication decision

Use Laravel Fortify for account flows, Sanctum for browser sessions and mobile/API
tokens, and Socialite for external sign-in. Support only providers built into
Socialite; Google and GitHub are the initial configuration targets. Apple is deferred
because it needs an additional adapter. Do not install community provider adapters
or Passport; operating an OAuth2 server is outside the current scope.

Packages and backend scaffolding are installed. Further implementation is deferred
to [GitHub issues](https://github.com/judus/motominator/issues): #1–#6 cover browser
authentication, recovery/verification, Socialite, mobile, settings/2FA and end-to-end
verification/production authentication configuration. Begin implementation with #1.
The user requested completing and publishing the setup baseline first, without
starting these feature flows. Issue #7 tracks npm dependency advisories.
