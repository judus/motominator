# Direction and boundaries

## Product exploration

Potential audiences: riders, mechanic workshops and shops. Group rides are another
possible direction: planning, meeting points, routes and seeing who has reached a stop.
None has been chosen as the product. The first implementation experiment is a
personal garage and maintenance log.

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
- `packages/client`: shared API contracts, operations and React behavior for both clients.

Server credentials stay on the server. Clients consume its HTTP API.
Do not import one app's source directly into another app.
Share client API access or nonvisual logic through explicit packages when needed.
Allow browser and native interfaces to evolve independently.

### Shared client implementation (2026-10-09)

The duplicated garage and AI settings logic now lives in the private npm workspace
`packages/client`, imported by both applications as `@motominator/client`.
Its base entry owns API models, endpoint operations, common form metadata and
error helpers. Its `/react` entry owns pagination, garage selection/create/update,
maintenance save/refresh flows, form drafts/submission guards and AI settings
loading/save/test/remove behavior. UI trees remain separate.

Each app composes the client with its authenticated request adapter. Browser
cookie/CSRF handling and native token storage, OAuth and account refresh stay in
their apps. The shared package has no DOM, Expo, Mantine or Tamagui imports;
React is a peer dependency. Vite and Metro consume its TypeScript source directly,
using the existing root npm workspace lockfile. No publishing or package build
server is required. Root lint/types/format checks include the package; existing
web/mobile behavior suites exercise its hooks through both adapters. See
`packages/client/README.md` for ownership and lifecycle conventions.

Browser forms now use the shared controlled draft state, preserving native HTML
constraints. Native fields use the same metadata with mobile keyboard/date-label
presentation. Costs remain decimal strings; Laravel remains authoritative for
validation and authorization. Account-owned AI views are keyed by user identity.

Verification: 25 browser and 23 mobile tests pass using the actual shared hooks;
only the existing AI test's model import changed. Root client lint, typecheck and
format checks include the new package and pass. Browser production build and
Android/iOS/Expo web exports pass. The real-browser cookie/CSRF/account/2FA
regression passes against the testing database. The Android emulator loads the
garage, motorcycle history and populated edit form through the shared client.
No new native save or paid AI request was issued in the device smoke check;
failed-save/retry behavior remains covered by the existing component suites.
Physical tablet and iOS runtime checks were not performed for this extraction.

### Shared client guardrails (2026-10-09)

Root, browser, mobile and `packages/client/AGENTS.md` now instruct agents to inspect
and extend the shared package before adding common client behavior. They distinguish
shared contracts/hooks from platform presentation and authentication adapters, require
public exports and both-consumer checks, and discourage speculative abstractions.
Project instructions are sufficient for this rule; no separate skill is installed.

Existing Oxlint/ESLint checks reject cross-app imports and direct paths into the
shared package's internals. Package Oxlint rejects app imports, known platform/UI
libraries (Expo, React Native/DOM, Mantine, Tamagui and Node built-ins), and React
imports/re-exports outside `src/react`. React hooks retain their rules-of-hooks and
dependency checks. The root lint command already runs these configurations in CI.
This guards import patterns, not runtime-computed module names, all possible aliases
or duplicated behavior; agents must still review ownership and dependency additions.

Verification: root lint and formatting pass. Temporary probes outside tracked source
confirmed rejection of four shared-core violations, three browser boundary violations
and two native violations. Existing React hooks pass the package's scoped exception.

npm workspaces manage JavaScript dependencies with one root lockfile. Composer manages
PHP dependencies inside the server app. No additional monorepo orchestration is needed yet.
Future API contract/type generation remains an idea; none is installed.

## Current scope

Framework setup and authentication are implemented. The current experiment is a
personal garage and maintenance log, described below.
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

## Garage and maintenance experiment

The first motorcycle slice is implemented in the Laravel API, Filament administration,
React browser client and Expo client. This is an experiment, not a fixed product roadmap.
Use functional tests and the existing quality gates; no ticket ceremony or pixel tests
are required for this phase.

Each motorcycle belongs to one user. The API always scopes records to the authenticated
owner, including administrator requests. Filament administrators can manage motorcycles
and maintenance for all users. Reads are available to unverified accounts; writes require
verified email. Native tokens require explicit garage read/write abilities. Shared Laravel
actions own validation, authorization and transactional persistence for API and Filament.

Motorcycles record make, model, year, optional nickname and current mileage in kilometres.
Maintenance records store date, mileage, work performed, optional notes and optional cost
with a three-letter uppercase currency code. Costs persist as exact decimals and travel
as strings; currency codes are syntax-checked rather than validated against a catalogue.
Mileage updates lock the motorcycle row. Maintenance can raise its current mileage;
older entries never lower it, and manual corrections cannot go below the highest stored
maintenance reading. Editing an entry to a lower reading does not lower current mileage.

Both clients support creating/editing motorcycles and maintenance, paginated history,
loading failures and retrying without losing a form draft. Deletion, ownership transfer,
unit conversion and imported specifications are not implemented; their semantics remain
open. Records are user-entered facts, with no inferred fitment or repair advice.

## UI foundation decision

Use Mantine for the React browser client, Tamagui for native mobile screens, and
Filament for server administration. Their interfaces can follow their own platform
conventions. Prefer library defaults, standard layouts and a small centralized theme
instead of ongoing custom CSS work. Functional regression checks remain required;
no visual snapshots or new tickets are part of this exploratory design pass.

The browser uses Mantine core/hooks with an AppShell header, bike cards, maintenance
records and styled authentication/account forms. Its old global scaffold CSS and
server-connectivity button were removed. Mantine's official documentation MCP is
registered in the repository config; official docs and skill sources are linked in
the web app guidance. Expo UI was the initial native foundation, replaced by Tamagui
on 2026-10-09 after device layout problems and the user's explicit framework choice.

Verification for the Mantine adoption: 19 browser component/API tests, 18 mobile tests,
and the existing real-browser authentication/account/2FA regression pass. Client lint,
type checks, formatting and the browser production build pass. Tests use library-aware
labels and a Mantine provider, with Vite dependency resolution retained in Vitest to
avoid loading the native app's React copy. No screenshot comparisons were added.

## AI credentials decision

The initial AI model is bring your own key (BYOK). Each user chooses a supported
provider and a suitable model, and supplies their own API key through account settings.
OpenAI is the first integration target; the project owner's key belongs to their own
account rather than serving as a shared application fallback. Provider usage is billed
to the user's provider account. Manual garage and maintenance features remain usable
without AI credentials.

Laravel owns provider calls. Store credentials encrypted on the server, return only
masked metadata to clients, and exclude secrets from logs, Telescope and queue payloads.
Queued imports contain only an import ID and attempt ID, resolving the owning
account and its current authorized credentials when they execute. Use the Laravel AI SDK's on-demand
providers without changing shared provider configuration for individual users.
Provider/model choices must support the document or image input and structured output
required by the invoice importer; SDK support alone does not establish those capabilities.

The importer extracts uploaded invoices into a reviewable draft before normal
application actions persist workshop and maintenance data (implementation details below).
ChatGPT plan connections are a separate possible future integration;
they are not assumed to be available through an OpenAI API key.

### Credential settings implementation

The browser Account page and the native Account tab now manage
one active provider/model/key configuration per user. Supported provider choices are
OpenAI, Anthropic and Google Gemini; model IDs are entered explicitly. The account
owns a separate `ai_credentials` row, with an encrypted, serialization-hidden key and
a masked last-four-character hint. Saving and testing require verified email; removal
does not. Replacing the provider requires a new key. Omitting a replacement key for
the same provider preserves the current one. Removing credentials is idempotent.

The versioned API uses session/CSRF authentication or native Sanctum tokens with
`ai:read`/`ai:write` abilities. Existing native tokens need sign-out/sign-in to obtain
the new permissions. Settings responses prohibit caching. Credential routes disable
Telescope and Debugbar recording, including in local development; Telescope also
redacts `api_key` request parameters globally.

Connection testing sends only a small text prompt, is rate limited, uses a 20-second
timeout and sanitizes provider errors. It never uses a global key or provider fallback.
OpenAI response storage is disabled. The selected model must be placed in the on-demand
provider configuration: in SDK 1.1.0 a separate prompt model argument does not override
an on-demand provider supplied in an array. Provider state is flushed after testing.
Connection success establishes text access only, not PDF/image or extraction capability.
The importer must add capability checks when its extraction contract is implemented.

No real provider key was configured or charged during implementation. Automated checks
use SDK/HTTP fakes; native components have not been walked through on a device for this
slice. Manual maintenance entry is unchanged.

Verification: all 127 server tests (615 assertions), 25 browser component/API tests,
23 native component/client tests and the real-browser authentication/account regression
pass. The browser regression checks the new AI section and unverified-account gating.
PHP analysis, PHP/client formatting, client lint/types and the browser build pass.
The additive credentials migration is applied to the local development database.

### Native layout follow-up (2026-10-09; before Tamagui)

Historical fixes to the previous Expo UI implementation; the Tamagui migration
below supersedes its host and wrapper structure.

Form and garage Expo UI hosts match content height only and retain the available
React Native width. Matching both axes measures Android content with an unbounded
width, which prevents paragraph wrapping and clips forms. Home's safe-area container
fills the available screen width. Shared native text/input wrappers apply the app's
light/dark text colors explicitly; sign-in also supplies its matching background.
Native tabs own the bottom inset for Home and Garage; their screen safe-area views
apply only top and side insets. Home has no manual tab-height padding outside its
scroll view, so the viewport can extend to the tab bar.
Mobile tests (23), lint, typecheck and formatting pass. These checks do not establish
physical-tablet or iOS layout correctness.

### Tamagui migration (2026-10-09)

Tamagui 2.7.7 replaces `@expo/ui` in the mobile app. Its v5 default configuration
provides tokens, system fonts and light/dark themes in `apps/mobile/tamagui.config.ts`.
Shared screen, card, button and labeled-field components live in `src/ui/components.tsx`.
Fields have instance-specific IDs and theme-aware native placeholder colors. Use
library components and theme tokens; no optional Tamagui compiler or animation driver
is configured. Expo Router retains platform-native tabs and virtualized garage/history
lists. Native tabs own the bottom inset; screens apply top and side safe areas.

The Garage is the landing screen. Account contains profile, AI settings and sign-out.
The scaffold Home/Explore pages and animated starter overlay are removed. Existing
authentication, pagination, form drafts and AI credential operations are preserved.
Official Tamagui guidance is pinned locally with source and license; `npm run ui:context`
in the mobile app generates configuration-specific agent context. See app AGENTS and
`docs/agent-tooling.md`.

Android emulator checks show working garage/edit and Account layouts, including
paragraph wrapping and scrolling to the lower AI controls above the native tabs.
The existing 23 mobile tests, lint, typecheck, formatting and Android/iOS/web exports
pass. Tests mock Tamagui with native controls to exercise application logic; exports
and the emulator check separately cover integration. Physical tablet and iOS device
layouts remain unverified. Metro needed a clean restart after dependency replacement.

## Application logging and user activity (2026-10-09)

Laravel logs use structured JSON by default (`LOG_JSON=false` keeps text formatting).
The default stack uses daily files with 14-day retention; the existing local `.env`
was switched from `single` to `daily`. Production containers can select
`LOG_STACK=stderr`; collection and retention of container output still belong to the
deployment setup. `just app-logs` follows application events through Pail; `just logs`
continues to follow container output.
Reload existing Horizon processes with
`just sail exec horizon php artisan horizon:terminate`; invoking termination in the
API container cannot signal a supervisor in a different container/hostname.

HTTP requests receive a server-generated `X-Request-ID`. Logs include the trace,
route template, method, status, duration and authenticated user ID, without request
bodies, query strings, raw URLs or authentication headers. Laravel Context carries
the trace into queued jobs; queue lifecycle logs add the job ID, class, connection
and attempt. AI connection failures log safe provider/model metadata, never the raw
provider exception. Monolog processors redact sensitive context keys, common token
formats and labeled secrets, omit object dumps and remove exception trace arguments.
Pail's handler is also sanitized because it consumes original Laravel events before
Monolog processing. Telescope request redaction now applies in local development too.
Redaction is defense in depth: never deliberately log raw credentials, provider
responses or arbitrary request data. These processors do not sanitize external
infrastructure logs, database query capture or third-party telemetry.

`user_activities` is a separate database trail. Shared garage actions record motorcycle
and maintenance creation/updates, including mileage increases caused by maintenance.
AI setting saves/removals also record safe provider/model metadata. No-op edits and
repeated removals create no activity; key replacement creates an event without copying
the key or its hint. Each event records the owner, actor, typed event, subject ID,
source, trace and selected before/after values. Maintenance title/notes changes use a
boolean marker, without copying their contents. Money remains an exact decimal string.
Writes share the domain transaction; rollback removes both the change and its activity.
Filament operations explicitly identify the admin source, while HTTP adapters identify
session/browser and persisted device-token requests. This identifies the authentication
channel, not a verified physical device. Background actions default to `system`; future
AI actions must explicitly supply `activity_source=ai` through scoped Context.

Filament's **User activity** resource is admin-only and read-only, with owner/event/source
filters. The internal `GetUserActivity` action provides a bounded owner-scoped feed for
future AI context assembly; callers must pass the authenticated owner. No AI prompt or
public activity API is connected yet. Current domain data remains authoritative.
The trail starts with new actions: no historical backfill, click tracking, login/profile
audit or inferred preferences are implemented. It is an application history, not a
tamper-proof compliance ledger.

Activity retention defaults to 365 days (`ACTIVITY_RETENTION_DAYS`, minimum one day),
pruned daily by the scheduler. Deleting an owner cascades their activity; deleting a
different actor clears the attribution FK. Subject IDs remain historical references
and are not dereferenced automatically. No additional Composer package was installed.

## Invoice upload and extraction (2026-10-09)

Both clients expose Invoices inside a motorcycle's detail view. Upload accepts one
PDF/JPEG/PNG up to 10 MB; native also offers photo selection and camera capture.
Originals live on the private `invoices` disk under `storage/app/private/invoices`,
with owner-authorized downloads. This disk needs persistent storage and backups in
production. Originals are private files, not encrypted filesystem objects. Extracted
review drafts are encrypted database JSON; confirmed workshop/invoice/position data
uses normal domain tables. Invoice routes and worker provider calls suppress
Telescope capture; activity records reference typed events/IDs without invoice prose.

Upload does not call AI. The explicit extraction button sends the original to the
user's saved BYOK provider/model through the Laravel AI SDK. Each attempt has one
queued job, one provider call, a 45-second provider timeout and a 55-second worker
timeout. The job has one try; no automatic paid retry or provider fallback is used.
An interrupted attempt can be replaced explicitly after three minutes. Attempt IDs
prevent stale workers from publishing over a newer attempt. Credentials are resolved
when processing begins; no global key fallback or callable tools are used.
PDF/image and structured-output support depend on the selected model.

Extraction produces an editable draft: issuer/workshop, invoice number/date/mileage,
maintenance title/notes, cost positions, labor minutes, currency, subtotal/tax/total.
Unknowns remain null. Review warnings compare supplied amounts using exact integer
cents; they do not calculate missing invoice facts. Date, mileage and maintenance
title must be supplied before confirmation. Currency is required when amounts exist;
workshop contact data requires a name. Amounts use decimal strings. Negative discount
positions are supported; negative invoice totals/credit notes are not currently
accepted by the existing maintenance-cost contract.

Confirm runs workshop upsert and maintenance/invoice/position creation in one database
transaction. Workshop matching is owner-scoped normalized name + address; blank contact
fields preserve prior details. Draft versions reject stale saves/confirmation. Confirming
the same import twice returns the same record, but uploading the same original as a
new import is not deduplicated. The saved draft remains viewable from Invoices after
confirmation. Maintenance mileage uses the existing rule: older entries never lower
the motorcycle's current mileage. Client behavior lives in `packages/client`; file
pickers, multipart adapters, download/share and presentation remain app-owned.

This slice handles individual documents, not multi-invoice batch splitting or workshop
address-book management. There is no invoice deletion/retention UI or orphan-file
sweeper yet. Future account/motorcycle deletion flows must clean up stored originals
explicitly; foreign-key cascades alone remove database rows. No user-activity feed is
sent to extraction prompts. Automated provider checks use fakes. A live OpenAI extraction of the synthetic
oil-service PDF now passes with the saved account model; arbitrary real-document
quality, physical-device photography and iOS operation still need trials.


### Live extraction response fix (2026-10-09)

A real synthetic oil-service extraction returned structured output but failed draft
validation on each cost position's tax rate. Provider output now normalizes explicit
percentage notation (`8.1%`, `8,1 %`) to decimal text (`8.1`) before validation; it
never guesses an absent rate or converts a fractional rate into a percentage. Agent
instructions also specify percentage/quantity notation. Invalid values still fail.
Validation failures log field paths without values or provider response bodies and
return a response-format error instead of misleading key/provider advice.

Retrying import 2 through the real Horizon/OpenAI path produced a ready draft:
2024-05-17, 28,000 km, three positions, 60 minutes, CHF 240.00 net + 19.44 tax =
259.44 total, with all three tax rates 8.1. These match the synthetic source.
No workshop or maintenance entry was confirmed during this verification.


## Server exception convention — 2026-10-09

Domain/application failures use domain-owned exception classes and named static
factories: `<Domain><MeaningfulScope>Exception::<failure>()`, for example
`GarageInvoiceException::draftChanged()`. Class names should identify the originating
domain in short-name logs. The prefix is preferred, with awkward/redundant cases
reviewed individually. Namespaces remain `App\<Domain>\Exceptions`.

Factories centralize construction and safe structured context. A class can represent
related failures; distinct catch behavior needs separate types or an explicit stable
reason, never message parsing. HTTP mapping belongs to adapters/rendering. Standard
Laravel validation/authorization contracts remain appropriate; unexpected failures
and original causes remain diagnosable. No blanket catch to silence IDE warnings.
The durable rule is in `.ai/guidelines/architecture.blade.php`; existing runtime paths
are not yet refactored by this documentation change.


### Current server organization — 2026-10-09

The domain layout is now implemented: Accounts, Garage, Ai and Activity own their
Actions, HTTP adapters, policies, jobs, providers and Filament resources as applicable.
Models stay global. Durable guidance is compacted in `.ai/guidelines`; Boost regenerates
server `AGENTS.md` from it. PHPCS/PHPCBF replaces Pint; Larastan remains at maximum level.
