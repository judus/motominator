# Shared client package

Read `README.md` and the root `.context/01-direction-and-boundaries.md` before changes.
This private workspace is the common behavior layer for browser and native clients.

## Ownership

- Base entry `@motominator/client`: API contracts, endpoint operations, common form
  metadata and pure domain/error helpers. No React imports or re-exports from `/react`.
- `@motominator/client/react`: reusable React behavior, including pagination, form
  drafts, submission guards, garage/history flows and AI settings state.
- Apps supply authenticated HTTP transport to `createClient`. Never import app
  source, read environment settings or own cookies, tokens, storage or sign-in redirects.
- No DOM, Expo, React Native, Mantine or Tamagui dependencies. No rendered UI here.
  Add another runtime dependency only for a concrete shared need and explain it.
- React stays a peer dependency; consumers supply their supported versions.
  Both bundlers consume TypeScript exports directly. Do not add a package build
  pipeline, bundled React copy or app-specific source aliases without a concrete need.

## Behavior and lifecycle

Inspect both consumers before changing a shared contract. Keep platform differences
in adapters or presentation. Do not add flags for hypothetical future consumers.
Use stable client and loader identities. Ignore obsolete async results, preserve
failed drafts, guard duplicate submissions and reset account-owned state on user
changes. Clear plaintext key drafts after successful save/removal or provider changes;
never log or persist them in the package. Keep money as decimal strings.
Laravel remains authoritative for validation and authorization.

Expose intentional public APIs through `src/index.ts` or `src/react/index.ts`.
Update both consumers for contract changes; avoid private imports and duplicate app
implementations. Keep the base layer independent of React and platform libraries.

## Checks

From the repository root run `npm run lint`, `npm run typecheck`,
`npm run format:check` and `npm test`. The existing app suites exercise the actual
shared hooks through their different adapters. For package resolution, React
dependencies or export changes, also run `npm run build:web` and
`npm run build:mobile`. For browser auth/transport changes use the existing
`npm run test:e2e --workspace @motominator/web` regression.

Import restrictions are configured in `.oxlintrc.json`. Preserve those rules and
app-side boundaries. A new exception requires an explicit architectural reason;
do not disable a rule merely to make an import pass. Record boundary changes in
`.context` and this package's README. Pixel tests and new tickets are not required.
