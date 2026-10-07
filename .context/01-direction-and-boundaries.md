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

## Server administration implementation

The user subsequently chose Filament for server-side administration and asked to
begin with user management, including public registration and login pages. Filament
5.10.1 and Livewire 4.4.7 are installed in apps/server. The admin panel lives at /admin;
it lists, creates and edits users. React/Expo account flows remain separate work.

Users have an is_admin boolean defaulting to false, excluded from mass assignment.
Public registration creates regular accounts and signs out with confirmation; panel
and user policy access require explicit administrator status even locally. Grant it
with app:grant-admin for an existing account. No administrator credentials are seeded.
Status is read-only in the panel and deletion is not exposed. Email edits clear
verification; blank passwords retain the original password.

AUTH_REGISTRATION_ENABLED controls public registration in both Filament and Fortify.
Turning it off removes their registration routes after configuration/route caches
are cleared, and the custom Filament page also rejects already mounted registration
submissions. Admin user creation and login remain available.

Filament login challenges confirmed Fortify authenticator secrets using the shared
encrypted fields. Enrollment/recovery UI is deferred; no separate Filament recovery
code format is introduced. Verification emails use Laravel's existing notification
and Fortify verification route rather than a panel-only verification endpoint.

Verification: all 27 server tests pass (88 assertions), including 18 focused admin
tests. They cover registration/flag injection, disabled submissions, guest and regular
user access, user creation/editing, password hashing/preservation, email verification
reset, login and complete Fortify authenticator challenge handling, and console grants.
Larastan, Pint, Composer validation and the server asset build pass. Chrome rendered
the login and registration pages; authenticated admin interactions are covered by
Livewire tests rather than a full browser run. With AUTH_REGISTRATION_ENABLED=false,
a separate Artisan process lists no public registration routes. Guest verification
links redirect to the available admin login page. No real admin account was created.

### Admin password recovery and verification

Filament now enables password reset with the shared users broker and requires email
verification before panel access. The login page links to recovery; the verification
prompt supports resending a signed panel verification link. Filament's queued
notifications retain their panel URLs independently of Fortify's future client URLs.
Public registration remains disabled in the local environment. Tests explicitly
enable registration independently of that local setting.

All 37 server tests pass (130 assertions), including ten recovery/verification tests
covering reset mail URLs and password updates, rejected tokens/signatures, panel
verification enforcement, successful resend/verification, expired links, wrong-account
links and guest redirects. Pint and Larastan pass. The existing administrator received
reset and verification notifications for local delivery checking; its password was
not changed by that check.
