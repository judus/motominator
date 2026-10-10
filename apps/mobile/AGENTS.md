This is an Expo/React Native mobile application. Prioritize mobile-first patterns, performance, and cross-platform compatibility.

## Expo has changed — do not trust your training data

Expo ships breaking changes every SDK release. APIs you remember are likely renamed, moved, or removed. Before writing any code that touches an Expo, EAS, or React Native API:

1. Read the major version of the `expo` package in `package.json`.
2. Fetch the matching versioned docs: `https://docs.expo.dev/versions/v<major>.0.0/`
3. For anything else, fetch https://docs.expo.dev/llms.txt — an index of all Expo docs with corrections to common LLM misconceptions. Follow its links to the specific page you need; never answer from memory.

## Commands

Use `bunx` instead of `npx` if the project uses bun (`bun.lock` present).

```bash
npx expo install <package>  # ALWAYS use instead of npm/yarn/pnpm/bun add — resolves SDK-compatible versions
npx expo start              # start the dev server
npx expo lint               # lint
npx tsc --noEmit            # typecheck
npx expo-doctor             # diagnose dependency and config issues
npx expo install --fix      # fix incompatible package versions
```

Run lint and typecheck before declaring any task done.

## Shared client behavior

Before adding API operations or stateful behavior, read `packages/client/AGENTS.md`
from the repository root and inspect its exports. Common garage, AI, pagination and
form behavior belongs in `@motominator/client` and `@motominator/client/react`.
Extend these APIs instead of duplicating logic in native screens. Import public
package exports; never another app's source or package internals.

This app owns Tamagui presentation, Expo Router, platform controls, SecureStore and
native authentication. Compose the authenticated shared client in `src/client.ts`;
keep token expiry/account refresh in the adapter. Do not move Expo or storage APIs
into the shared package or force different platform interactions into generic hooks.
`eslint.config.js` enforces import boundaries; preserve those rules. Shared contract
changes require both client suites and relevant builds, per the package instructions.

## UI foundation: Tamagui

The project uses Tamagui 2.7.7 for mobile UI, replacing Expo UI. This explicit
project choice overrides the Expo skills' default recommendation of `@expo/ui`.
Keep Expo Router and native tabs; the browser application independently uses Mantine.

Before Tamagui work, read `.agents/skills/tamagui/SKILL.md` and relevant references.
This is guidance from the official Tamagui repository; provenance is in
`.agents/skills/tamagui/source.json`. From this app directory, run
`npm run ui:context`, then read the generated `tamagui-prompt.md` for actual tokens,
themes, fonts and breakpoints. The generated file and `.tamagui/` are ignored.

`tamagui.config.ts` owns the configuration and light/dark themes. Shared controls
and screen spacing live in `src/ui/components.tsx`. Prefer built-in components and
tokens; keep customization centralized. No compiler plugin or animation driver
is configured. Keep all `tamagui` and `@tamagui/*` versions aligned. Official docs:
https://tamagui.dev/docs/intro/installation and https://tamagui.dev/docs/guides/expo.

Buttons use `ui/Button`'s semantic `intent`: `primary` (default, filled blue) for
save/create/confirm, `secondary` (outlined blue) for browsing, back/cancel, retries
and supporting actions, and `danger` (outlined red) for removing, unlinking,
revoking, disabling security or explicitly discarding changes. Opening a creation
form is primary; opening an existing record or settings section is secondary.
Do not set button colors/themes in screens. Disabled controls fade the same intent.

## Navigation & Routing

- Use **Expo Router** for all navigation. Routes live in `src/app/` — every file there is a screen, `_layout.tsx` files define navigators. Keep non-route code (components, hooks, utils) outside `src/app/`.
- Import `Link`, `router`, and `useLocalSearchParams` from `expo-router`.
- Docs: https://docs.expo.dev/router/introduction.md

Keep overview, list, detail and form routes focused. `(tabs)` owns Home and domain
destinations; each domain owns a Stack with an index anchor for deep links.
`navigation/domain-stack.tsx` supplies native Back and the domain-root shortcut.
Use `ui/Screen` for ordinary scrollable screens and `ui/RecordList` for FlatList
screens; do not nest virtualized lists inside Screen. Wider layouts arrange cards
in columns without merging forms or lists into an overview. Shared record loaders
load by route ID; retained screens refresh on focus using `useRefreshOnFocus`.
See `.context/09-client-navigation.md` at the workspace root for reference files,
save navigation, draft limitations and the next-feature checklist.

Account forms use `src/account/editor.tsx` and shared `useAccountSettings`/`client.account`
operations. Keep password/token storage and the native provider browser handoff in
the auth adapter. Existing tokens need a new sign-in for added abilities; never
silently broaden stored token permissions. Successful password changes and revoking
the current token must refresh auth and clear the account tree. Clear password drafts
after success, preserve them on failure, and keep recovery material out of storage/logs.

## Building with EAS

Use EAS to build, sign, and submit the app in the cloud (`eas build`, `eas submit`) and to ship over-the-air updates (`eas update`) — no local Xcode or Android Studio required. Run EAS CLI as `bunx eas-cli <command>` in Bun projects, or `npx eas-cli@latest <command>` otherwise; substitute that for bare `eas` in docs examples.
Docs: https://docs.expo.dev/eas/index.md

## Rules

- If `ios/` and `android/` directories do not exist, they are generated (Continuous Native Generation). Never create or edit them by hand — configure native behavior in `app.json` and config plugins.
- Expo Go only includes its bundled native modules. After adding a library with native code, the app needs a development build: `npx expo run:ios|android` locally, or `eas build --profile development`.
- Prefer recommended Expo modules over third-party libraries, and check your available skills before adding dependencies. Docs: https://docs.expo.dev/versions/latest/index.md

Invoice picking/sharing belongs in `src/invoices/files.ts`. Own a feature-cache
copy of selections, preserve originals, and release abandoned copies after any
upload settles. Reuse the adapter's cache leases/sweep; do not delete arbitrary
cache paths or persist private drafts. Auth response ownership must be checked
after every storage/network await; token mutations stay serialized in the adapter.
