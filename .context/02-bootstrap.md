# Initial bootstrap — 2026-10-07

## Installed

- Git repository initialized on `main`; no commits or remote created.
- PHP 8.4.26, Composer 2.9.5, Node 24.14.0 and npm 11.9.0 available during setup.
- Laravel framework 13.35.0, AI SDK 1.1.0 and Boost 2.10.2.
- React/Vite browser scaffold, TypeScript 6 and Vite 8.
- Expo SDK 57 default template (57.0.29), React Native 0.86.3, React 19.2.3 and Expo Router.
- Root npm workspaces and lockfile; independent server Composer manifest and lockfile.
- Local SQLite database initialized, including the AI conversation tables.
- AI configuration/stubs published; no provider keys, agents or AI calls configured.

The Laravel scaffold came from the official `laravel/laravel` GitHub archive.
Vite was scaffolded with `create-vite`. `create-expo-app` could not spawn its npm
subprocess in the execution environment (EPERM), so the official npm
`expo-template-default` archive was extracted directly. Its name/configuration,
gitignore and the generator's own AGENTS template were applied locally.
The installed Expo template dependency versions were preserved.

## Small scaffold adjustments

- Public `GET /api/v1/status` returns `name: Motominator`, `status: ok`.
- Both clients expose a Check server button, with configurable API base URLs.
- Two feature tests cover the status response and browser CORS headers.
- Default Laravel web page/assets retained separately from the browser app.
- Server Vite configuration no longer downloads Bunny fonts at build time;
  its CSS can fall back to system fonts, making the starter build independent of that service.
- Server Composer setup creates the SQLite file before migrating and leaves JS setup
  to the root npm workspace.
- Removed unused server `concurrently` dependency. Updated the transitive
  `shell-quote` within its existing semver range to resolve its critical advisory.
- Expo TypeScript config explicitly includes `expo/types`, so CSS imports typecheck
  before Expo has generated its ignored `expo-env.d.ts` file.
- Expo's web color-scheme hydration hook uses `useSyncExternalStore` to retain the
  server/client distinction without setting state synchronously in an effect.
- Added Expo ESLint tooling and root development/check/build commands.
- Boost configured for Codex only, scoped to `apps/server`, with Cloud integration disabled.
  Its generated AGENTS, skills and project MCP configuration are retained there.
- Expo AGENTS guidance is scoped to `apps/mobile`. No web or root AGENTS created.
- Empty `packages/` and `docs/` directories reserve future locations without adding abstractions.

## Validation

Passed:

- Composer strict manifest/lock validation.
- Laravel Pint formatting.
- Laravel PHPUnit: 4 tests, 7 assertions (including the two scaffold tests).
- SQLite migrations, including AI conversation storage.
- TypeScript checks and lint for both clients.
- Browser production build and Laravel starter asset build.
- Expo export: Android and iOS Hermes bundles, plus static web routes.
- Actual HTTP request to the server status endpoint: 200, expected JSON and CORS header.

The environment refused the listener launched by `artisan serve`; the HTTP check used
PHP's built-in server directly, with the Laravel router and `public/` as its working directory.
The normal documented Artisan command remains the development entry point.
Temporary verification servers were stopped afterward.

Not verified: interactive browser UI, Expo Go on a phone, native device networking,
Android/iOS binary builds, signing, background features or deployment.
Bundle export is not a real-device gate. Boost's generated MCP config is present,
but the running assistant session has not connected to that server.

## Dependency audit

After compatible updates, npm audit reports 29 affected packages:
18 high, 11 moderate, zero critical. These are dependency-chain counts, not 29 independent issues.
The underlying advisories concern `braces`, `node-forge`, `decode-uri-component` and `uuid`
in the Expo/React Native dependency tree.

- braces: https://github.com/advisories/GHSA-vfj7-8cjw-p6xm
- node-forge: https://github.com/advisories/GHSA-86w9-cpqp-85rv
- decode-uri-component: https://github.com/advisories/GHSA-vcc3-ghjq-m6fr
- uuid: https://github.com/advisories/GHSA-w5hq-g745-h8pq

`npm audit fix` did not resolve these within the selected stack. Its force suggestions
include incompatible Expo/React Native changes; none were applied. Revisit upstream fixes
before treating this scaffold as ready for deployment. Composer reported no advisories.

## Next exploration

Try both clients locally, then investigate real catalogue access and choose a small
experiment based on what data is available. No provider or product feature is committed.
