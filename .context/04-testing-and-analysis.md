# Testing and code analysis — 2026-10-07

## Decisions

- Keep PHPUnit 12.5.38. The user knows PHPUnit and has not chosen Pest.
  Laravel 13 officially supports both. Pest is built on PHPUnit and adds a different
  authoring style and optional features; no migration is needed to get Laravel test helpers.
- Add Larastan 3.13.0 / PHPStan 2.3.0 using the documented starting level 5 and Carbon extension.
  Analyse app, routes, bootstrap/app.php, database and tests. Cache under ignored storage.
  No baseline. One inline suppression preserves the intentional Laravel starter
  `assertTrue(true)` test, which PHPStan correctly identifies as always true.
- Keep Pint's default Laravel preset; add Composer analysis/format/check scripts.
- Browser: Vitest 5 + React Testing Library + jest-dom in jsdom.
  Both local Phoenix and Control Deck repositories use Vitest; React/Vite does not
  impose a single test runner. Keep the generated Vite template's Oxlint setup.
- Mobile: SDK-compatible Jest 29, jest-expo 57 and React Native Testing Library 14,
  installed through Expo CLI. Use the Expo preset and a CSS module stub because the
  generated theme imports global.css. Keep tests outside the route directory.
  TypeScript includes Jest types; tests use globalThis rather than Node's global.
- Prettier with default formatting for both clients. Existing source received its
  first formatting pass. Generated agent guidance, Expo types, output and caches are ignored.
- Root npm exposes client test/format commands. Just exposes server analysis/formatting,
  aggregate `check` and `test-all`. Existing `just test` stays server-only.
- CI checks Larastan, formatting and both client suites in addition to previous gates.

## Coverage and limits

Each client has four behavior tests for the existing server-status UI: valid connection,
HTTP failure, unexpected payload, and network failure, including retry availability.
Fetch is mocked; these do not establish real network/device compatibility. Server retains
its existing PHPUnit suite. Browser/device end-to-end tooling is deferred until a real flow.

The configured PHP 8.5 Sail application image remains unbuilt due to the previously
recorded Ubuntu download failures. PHP verification uses the cached PHP 8.4 image in
disposable containers; PHP 8.5 and GitHub-hosted CI remain unverified.

## Dependency findings

After installing Expo's recommended SDK-compatible test packages, npm audit reports
65 affected packages (49 high, 16 moderate, 0 critical) after a clean npm ci, including propagated findings
through Jest 29 / jest-expo and the existing Expo/React Native graph. Root advisories
include braces, node-forge, sprintf-js, uuid and decode-uri-component. npm's suggested
remediation changes framework major versions and sometimes suggests older Expo/React
Native releases. No forced upgrade, downgrade or transitive override was applied.
Composer reports no security advisories.

## Sources

- https://laravel.com/docs/13.x/testing
- https://pestphp.com/docs/why-pest
- https://github.com/larastan/larastan
- https://vitest.dev/guide/
- https://docs.expo.dev/develop/unit-testing/
- https://docs.expo.dev/guides/using-eslint/
- Read-only references: ../control-deck-suite/{phoenix,control-deck}/package.json.

## Verification

- Fresh root `npm ci` succeeds.
- PHPUnit: 4 tests, 7 assertions passed using cached Sail PHP 8.4.
- Vitest: 4 tests passed; Jest/Expo: 4 tests passed after the fresh install.
- Larastan: no errors. Pint formatting passes across 30 PHP files.
- Both client linters, TypeScript checks and Prettier checks pass.
- Browser production build and Expo Android/iOS/web exports pass after installation.
- Composer validation and Actionlint pass; just recipes parse and aggregate commands dry-run.
- GitHub-hosted execution, Sail PHP 8.5 and real-device/browser tests remain unverified.

## Repository baseline refresh — 2026-10-08

This supersedes the PHP image limitation and test totals above. The configured PHP
8.5 Sail application is running. `just check` and `just test-all` pass: 9 server tests
(12 assertions), 4 browser tests and 4 mobile tests. Browser and Laravel asset builds
and Expo Android/iOS/web exports pass. Composer manifest/lock validation passes;
all local migrations are applied and Horizon is running. Boost application-info
connects to the live application.

The refreshed npm audit still reports 65 affected packages (49 high, 16 moderate,
0 critical). Follow-up is tracked in GitHub issue #7. Current tests establish the
scaffold baseline; authentication implementation and its tests are tracked in
issues #1–#6. Real provider sign-in and native installable builds remain unverified.

## Tamagui dependency refresh — 2026-10-09

Mobile now uses `tamagui` and `@tamagui/config` 2.7.7, replacing `@expo/ui`.
The matching `@tamagui/cli` is a development dependency for generating agent context.
Its version-consistency check passes. The root audit reports 76 affected packages
(59 high, 16 moderate, 1 low, no critical). The CLI adds propagated findings through
its static tooling and dependencies, including glob/micromatch/chokidar/ts-morph.
The audit's proposed Tamagui fix downgrades to v1 and is not applied. Existing Expo
and test-tool findings remain. This is not a clean dependency security gate.

## Invoice import verification — 2026-10-09

- PHP: 157 tests / 814 assertions pass; Larastan and full Pint check pass.
- Browser: 33 behavior tests pass; mobile: 27 tests pass. Shared invoice behavior
  covers preserved failed drafts, exact decimals, stale-review recovery, polling
  cancellation and obsolete responses. Multipart transport keeps CSRF/session or
  bearer auth and leaves boundaries to fetch.
- Root lint/typecheck/format checks pass; browser production build and Expo
  Android/iOS/web exports pass. Expo reports SDK dependencies up to date.
- Existing real Chrome cookie/CSRF/profile/2FA regression passes against the
  isolated testing server. Android Expo Go loads the updated app, shows invoice
  controls for the existing motorcycle and opens/cancels the native document picker.
- Server checks cover private storage/download ownership, native abilities,
  confirmation atomicity/idempotence, workshop scoping, encrypted large drafts,
  stale attempts, revoked keys, sanitized failures and mocked OpenAI PDF transport
  with owner credentials and response storage disabled. SDK fakes also cover the
  three configured providers. No paid provider request was made.
- These checks do not establish real document-extraction quality or physical
  camera/iOS behavior. The emulator check did not upload a fixture into the user's
  motorcycle or change its maintenance history.
- SDK-matched document/image picker and file/share modules were added. The refreshed
  npm audit reports 78 affected packages (60 high, 17 moderate, 1 low, no critical).
  Existing dependency debt remains; no forced downgrade or override was applied.

### Live response regression — 2026-10-09

Provider-returned tax-rate formatting exposed a gap in the initial fake fixtures.
Added cases for percent signs/comma decimals and rejection of ambiguous values with
safe field diagnostics. Full server suite: 161 tests / 834 assertions; Larastan and
Pint pass. Live OpenAI/Horizon extraction of `01-oil-service-en.pdf` now reaches
`ready`, and its date/mileage/positions/duration/amounts/rates match the fixture.
Paid verification is authorized by the user. This supersedes the earlier limitation
that all provider checks were faked; broader real-document/model coverage remains open.

## Server architecture and quality policy — 2026-10-09

- The user approved explicit DI, pragmatic clean architecture/code, and domain-owned
  namespaces (`App\Garage\Actions`, `Http`, `Jobs`, `Policies`, `Providers`, etc.).
  Eloquent models remain global `App\Models` persistence infrastructure. No automatic
  repository/interface/DTO layering. Existing namespace moves and service-location
  cleanup are separate implementation work, not completed by guidance changes.
- Durable project guidance lives in `.ai/guidelines/architecture.blade.php`; Boost
  regenerates it into server `AGENTS.md`. Root instructions route to it. The installed
  best-practices skill already recommended injection; the project now prohibits service
  location outside composition roots and test setup.
- Larastan/PHPStan now uses `level: max` (currently level 10), including tests and
  database code. No baseline, file exclusions or new suppressions. An initial maximum
  run exposes 401 existing findings; the previous level-5 pass does not satisfy this gate.
- Added Slevomat 8.31.1 and PHP_CodeSniffer 4.0.4 as approved server dev tooling.
  `phpcs.xml` enables only `Files.LineLength` (120 columns, ignores comments/imports)
  and `Functions.RequireMultiLineCall` (121-column threshold). Pint retains general
  Laravel formatting. PHPCS references sniff files directly; the optional installer
  Composer plugin is disabled. Initial PHPCS run reports 523 errors, 235 fixable;
  these are existing formatting findings, not a clean style gate.
- `composer format:check`, `just format-check` and CI check both Pint and PHPCS.
  `just style-check` / `composer style:check` run PHPCS alone. `composer style:fix`
  invokes PHPCBF; its exit 1 indicates changes applied. Run Pint afterward and review
  remaining line-length findings manually. The approved checks do not automatically
  refactor the existing application or enforce every architecture rule.

### IDE typing and current verification

- Added Laravel IDE Helper 3.7.0 as development tooling for PhpStorm. Generated field
  and relationship PHPDoc into all 18 models; existing custom casts/docs remain.
  Decimal casts are corrected to `numeric-string`. Local facade and PhpStorm container
  helper files are generated and ignored. `just ide-helpers` refreshes helpers and
  model docs, then runs Pint. No helpers are included in production autoload.
- `config/ide-helper.php` retains nullable/soft-delete relationship handling, avoids
  vendor edits and verbose magic-where/count generation, maps decimals accurately,
  and disables automatic post-migrate generation. Appended docs need review for stale
  existing types after schema changes. IDE metadata generation passed; the user's
  actual PhpStorm diagnostics have not been observed after reindexing.
- Full existing server suite still passes: 178 tests, 915 assertions. Pint passes.
  Maximum-level Larastan now reports 307 findings (down from 401 after accurate model
  relationship types); PHPCS still reports 523 findings. Neither stricter gate is green.
  No baselines, lower levels or ignored legacy source files were introduced.
- A temporary lint-only probe confirmed rejection of a long crowded call by both
  configured sniffs; the equivalent wrapped call passed. Temporary files were removed.
  Composer manifest/lock validation succeeds. These tooling changes do not complete
  the existing namespace/DI/readability refactor or fix all maximum-level type issues.

## Domain refactor and single PHP style tool — 2026-10-09

Supersedes the pending-cleanup state above. Application classes are organized under
`Accounts`, `Garage`, `Ai` and `Activity`; models remain in `App\Models`. Domain
providers register policy mappings and commands, and Filament discovers domain
resources. Routes and test references use the new namespaces.

Pint research found no general line-width or threshold-based call wrapping rule in
PHP-CS-Fixer. Pint was removed in favor of PHPCS/PHPCBF with PSR-12 and the two
focused Slevomat rules. Composer, just and CI use this single standard. The formatter
launcher treats PHPCBF exit 1 as successful fixes and retains unresolved-error exits.

Application collaborators use explicit DI; job services enter through `handle()` and
Filament pages through `boot()`. Activity owns motorcycle/maintenance snapshots.
Expected workflow failures use domain-named exception factories and stable reasons;
HTTP rendering maps conflicts to 409. Standard Laravel validation, authorization and
missing-model exceptions remain intact. Storage/programming failures remain reportable.

Maximum-level analysis now validates runtime boundary types and exact test contracts,
without a baseline, lowered level or ignored source. IDE Helper query-method generation
is disabled because its Builder-or-model unions contradicted Laravel's real return
contracts. Reviewed field/relation PHPDoc remains tracked.

Verification: all 179 server tests pass (967 assertions); PHPCS and Composer
manifest/lock validation pass. PhpStorm metadata/model docs were regenerated. The
default Redis queue had no pending, reserved or delayed jobs; Horizon was restarted
to load the new job namespace. Final maximum-level analysis includes provider wiring
and the formatter launcher as well as the existing application/database/test scope.

Final acceptance: maximum-level PHPStan reports zero errors in the expanded scope;
`composer format:check` passes with zero violations. `git diff --check` is clean and
Horizon reports running after the restart. No client implementation was changed.

## Security regression update — 2026-10-10

After remediation, the full server suite passes 245 tests / 1328 assertions; clients
pass 44 browser + 53 native tests. Maximum PHPStan, PHPCS, client lint/type/format,
builds, all-platform Expo exports and real browser auth E2E pass. Local browser
test servers use disposable container-local SQLite; PHP tests retain MySQL testing.
This prevents shared fixture interference and leaves no browser test database on
the host. The temporary server has graceful cleanup and loopback-only publication.

Security contracts, residual dependency findings and verification limits are recorded
in [10-security-and-privacy.md](10-security-and-privacy.md) and the detailed audit.
