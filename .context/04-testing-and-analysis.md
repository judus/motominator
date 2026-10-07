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
