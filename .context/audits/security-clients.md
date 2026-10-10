# Client security and privacy audit

Date: 2026-10-10. Working tree on `main`, baseline
`222a60ad9587c43a04fc6a1d9d8992cf148860f1`; 209 dirty/untracked entries at
start. Read-only application review; this report is the only repository change.
The server/database, network exposure and dependency audit belong to other reviewers.
Applied the project `codebase-audit` skill and app guidance. No real keys, account
changes, OAuth provider calls, deployments or external publication were used.

## Confirmed findings

### C1 — Medium: an old browser account response can restore the authenticated UI after logout

- `apps/web/src/App.tsx:49-52`: `refresh()` blindly applies the `/api/v1/user`
  response. `:178-183` clears the user after logout; `:69-71` clears it after
  an expiry event. Neither invalidates an already running refresh.
- Caller: `AccountSettings` passes refresh into shared `useAccountSettings.perform`,
  which calls it after a successful write. Account editor busy state is local;
  it does not disable the AppFrame sign-out control. A slow refresh and a normal
  sign-out can therefore overlap. A response already authorized before logout
  can arrive after the logout has completed.
- Effect: the previous user's name/email and authenticated shell can reappear
  on a shared browser after successful sign-out. Server logout still works;
  protected subsequent requests should fail. This is a client privacy/state
  defect, **not** an authenticated API bypass or demonstrated full invoice leak.
- Evidence: `/tmp/motominator-web-security-probe.cjs` transpiles the actual App.tsx
  and invokes the actual refresh and sign-out closures using isolated fake hook
  state and transport. Result: `loggedOut:true, staleRefreshRestoresUser:true`.
  This characterizes the stale closure; it is not a real-browser integration test.
- Fix candidate: auth generation/attempt ownership for refresh and initial loads;
  invalidate on logout, expiry and account changes. Check ownership after awaits.
  Regression: defer old `/user` response, logout, resolve old success, verify the
  login screen stays visible; repeat with account replacement and expiry.
- Disposition: fix before calling the template reliable. No implementation edits.

### C2 — Medium (availability): a stale native 401 deletes a replacement credential

- `apps/mobile/src/auth/client.ts:127-134`: `authenticatedApi` captures a token
  for the request, then clears the global SecureStore slot on any 401, without
  checking that the slot still contains that token.
- Caller: every shared client feature request. Old requests may remain pending
  while password revocation, foreground auth refresh or manual sign-out makes
  sign-in available. The user signs in and saves token B; delayed request A's
  401 then deletes B.
- Effect: valid new login is silently lost, followed by sign-out when the adapter
  refreshes authentication. No bearer disclosure/account escalation demonstrated.
- Evidence: `/tmp/motominator-client-security-probe.cjs` transpiles actual client.ts
  using fake SecureStore and fetch. Captured token A -> save B -> resolve A as401
  reports `stale401ClearedReplacementToken:true`. No real tokens were read.
- Related candidate: `auth-context.tsx:49-52` checks generation before awaiting
  token deletion, then updates user without rechecking; token ownership and
  storage mutation ordering should be addressed together. A simple read/compare
  followed by async delete can itself race with a new save; serialize storage
  changes or own mutations by an auth epoch.
- Disposition: fix with replacement/expired-storage/logout races tested, keeping
  legitimate revoked-token cleanup. No implementation edits.

### C3 — Low: abandoned invoice selections leave private cache copies behind

- `apps/mobile/src/invoices/files.ts:17-21` requests a cached copy of documents;
  selected camera/photos may also be app cache files.
- `apps/mobile/src/invoices/invoices.tsx:37-42` cleanup only sets an active flag.
  The chosen file is deleted on replacement (`:52`) or an acknowledged upload
  success (`:69-70`), not when leaving the screen or signing out.
- `packages/client/src/react/use-invoices.ts` upload returns null after unmount,
  even on success. That makes the successful-but-abandoned path retain the file
  as well. Existing invoice tests correctly preserve failed drafts for retry,
  but contain no unmount cleanup regression.
- Prerequisite/effect: choose a document and leave the screen without replacing
  or completing it; the sensitive copy remains in the app sandbox until OS cache
  eviction. This is unnecessary local retention, **not** exposure to another
  ordinary sandboxed app, public web storage or demonstrated cloud backup leak.
- Fix candidate: own the selected cache copy independently of mounted UI; release
  it on dismissal/unmount/sign-out without deleting the original user file or
  breaking an in-flight upload. Bounded stale-cache cleanup covers crashes.
- Disposition: fix or deliberately document retention; low severity.

## Deliberately rejected candidates / retained safeguards

- Browser credentials are cookie based, not localStorage/sessionStorage; mutating
  transport initializes Sanctum CSRF, sends X-XSRF-TOKEN and credentials include.
  Native transport uses bearer credentials with cookies omitted. Backend CORS,
  cookie attributes and response caching are covered by server reviewers.
- Native token uses expo-secure-store with WHEN_UNLOCKED_THIS_DEVICE_ONLY. Installed
  SecureStore plugin defaults configureAndroidBackup=true; its Android XML explicitly
  excludes SecureStore shared preferences from cloud/device transfer. No claim of
  unencrypted Android token backup is supported. No native build artifacts exist
  here to prove the effective release manifest.
- Native social sign-in/linking generate a 256-bit random verifier, send SHA256
  challenge, keep verifier in memory and validate callback scheme/host/exact code
  before exchange. Device bearer tokens are not in browser/deep-link URLs. A second
  app registering the custom scheme can interrupt login; the observed proof flow
  does not let it redeem the login token merely by intercepting the callback.
  Server actor binding/state/expiry/one-use checks belong to the server audit.
- Browser email verification accepts only the configured API origin and expected
  path prefix. There is no observed arbitrary-origin verification fetch/open redirect.
- Invoice strings, names, extraction warnings and server errors are rendered as
  React text/input values; no dangerouslySetInnerHTML/innerHTML/eval sinks found in
  client source. Download paths derive from numeric IDs rather than invoice filenames
  or workshop URL fields. No evidence of stored script execution from extracted prose.
- Download/share creates an app-cache file and deletes it in finally; deliberate
  sharing can transfer the invoice to the destination app. App crash termination
  can leave cache remnants; finally is not a secure-erasure guarantee.
- Shared hooks ignore unmounted reads; useRecord/usePage scope old results to loader
  identities. Browser account/AI editors key by user ID and mobile root Stack keys
  by user ID. Those protections are valid and should stay; C1 concerns the separate
  top-level auth refresh ownership.
- BYOK drafts are memory-only, password inputs; saved/removed/provider-switched keys
  are cleared by shared useAiSettings. Recovery codes/secrets are not persisted or
  logged by the shared package. Retained native screens can retain sensitive in-memory
  form state while navigating; no unexpected durable storage was found.
- Client source has no console logging of auth/invoice/provider payloads. Tracked
  browser/mobile env examples contain only public API base URLs. The source only
  consumes EXPO_PUBLIC_API_BASE_URL/VITE_API_BASE_URL (plus Expo platform flags);
  no provider credentials are loaded into client environment config.
- Development API defaults are plain HTTP (including native emulator address).
  Credentials/documents are not confidential on that transport; release builds
  need mandatory HTTPS configuration and fail-closed handling. This is deployment
  hardening, not evidence that a live HTTPS release exists or has leaked keys.
- CSP/frame policy, TLS endpoints, Android release permissions/screenshots/background
  snapshots, signed OTA update configuration and production sourcemap access are
  not established by these source checks. They remain release validation items.

## Verification and limits

Two focused isolated probes above reproduce auth lifecycle flaws without network,
real credentials or account edits. Existing tests were reviewed, not rerun here
(the parent runs overall gates). No live native provider linking, emulator account
switching, real-browser race test, crash/cache forensic test or released native binary
review was performed. No product fixes, tickets or commits were made.

## Coverage ledger

The following files were manually read completely for security ownership/flows:

- `apps/mobile/.env.example`
- `apps/mobile/__tests__/auth-client.test.ts`
- `apps/mobile/__tests__/invoices.test.tsx`
- `apps/mobile/app.json`
- `apps/mobile/package.json`
- `apps/mobile/src/account-screen.tsx`
- `apps/mobile/src/account/devices-screen.tsx`
- `apps/mobile/src/account/editor.tsx`
- `apps/mobile/src/account/password-screen.tsx`
- `apps/mobile/src/account/profile-screen.tsx`
- `apps/mobile/src/account/social-screen.tsx`
- `apps/mobile/src/account/two-factor-screen.tsx`
- `apps/mobile/src/ai/ai-settings.tsx`
- `apps/mobile/src/app/(tabs)/_layout.tsx`
- `apps/mobile/src/app/(tabs)/account/_layout.tsx`
- `apps/mobile/src/app/(tabs)/account/ai.tsx`
- `apps/mobile/src/app/(tabs)/account/devices.tsx`
- `apps/mobile/src/app/(tabs)/account/index.tsx`
- `apps/mobile/src/app/(tabs)/account/password.tsx`
- `apps/mobile/src/app/(tabs)/account/profile.tsx`
- `apps/mobile/src/app/(tabs)/account/social.tsx`
- `apps/mobile/src/app/(tabs)/account/two-factor.tsx`
- `apps/mobile/src/app/(tabs)/garage/_layout.tsx`
- `apps/mobile/src/app/(tabs)/garage/index.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/edit.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/index.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/invoices/[invoiceId].tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/invoices/index.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/invoices/upload.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/maintenance/[recordId]/edit.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/maintenance/[recordId]/index.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/maintenance/index.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/[motorcycleId]/maintenance/new.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/index.tsx`
- `apps/mobile/src/app/(tabs)/garage/motorcycles/new.tsx`
- `apps/mobile/src/app/(tabs)/index.tsx`
- `apps/mobile/src/app/+not-found.tsx`
- `apps/mobile/src/app/_layout.tsx`
- `apps/mobile/src/app/auth-return.tsx`
- `apps/mobile/src/auth/auth-context.tsx`
- `apps/mobile/src/auth/client.ts`
- `apps/mobile/src/client.ts`
- `apps/mobile/src/components/app-tabs.tsx`
- `apps/mobile/src/components/app-tabs.web.tsx`
- `apps/mobile/src/components/external-link.tsx`
- `apps/mobile/src/components/hint-row.tsx`
- `apps/mobile/src/components/server-status.tsx`
- `apps/mobile/src/components/sign-in.tsx`
- `apps/mobile/src/components/themed-text.tsx`
- `apps/mobile/src/components/themed-view.tsx`
- `apps/mobile/src/components/web-badge.tsx`
- `apps/mobile/src/garage/components.tsx`
- `apps/mobile/src/garage/garage-form.tsx`
- `apps/mobile/src/garage/garage-screen.tsx`
- `apps/mobile/src/garage/motorcycle-route.tsx`
- `apps/mobile/src/garage/motorcycle-screens.tsx`
- `apps/mobile/src/hooks/use-color-scheme.ts`
- `apps/mobile/src/hooks/use-color-scheme.web.ts`
- `apps/mobile/src/hooks/use-theme.ts`
- `apps/mobile/src/invoices/files.ts`
- `apps/mobile/src/invoices/invoice-screens.tsx`
- `apps/mobile/src/invoices/invoices.tsx`
- `apps/mobile/src/navigation/domain-stack.tsx`
- `apps/mobile/src/navigation/routes.ts`
- `apps/mobile/src/navigation/use-refresh-on-focus.ts`
- `apps/mobile/src/ui/components.tsx`
- `apps/mobile/src/ui/record-list.tsx`
- `apps/web/.env.example`
- `apps/web/index.html`
- `apps/web/src/AccountPages.tsx`
- `apps/web/src/AccountSettings.tsx`
- `apps/web/src/App.tsx`
- `apps/web/src/Auth.test.tsx`
- `apps/web/src/ai/AiSettings.tsx`
- `apps/web/src/api.test.ts`
- `apps/web/src/api.ts`
- `apps/web/src/client.ts`
- `apps/web/src/garage/Garage.tsx`
- `apps/web/src/garage/GarageForm.tsx`
- `apps/web/src/garage/GaragePages.tsx`
- `apps/web/src/garage/MaintenancePages.tsx`
- `apps/web/src/garage/MotorcyclePages.tsx`
- `apps/web/src/garage/components.tsx`
- `apps/web/src/garage/use-bike.ts`
- `apps/web/src/invoices/InvoicePages.tsx`
- `apps/web/src/invoices/Invoices.tsx`
- `apps/web/src/main.tsx`
- `apps/web/src/navigation/routes.ts`
- `apps/web/src/ui/ActionLink.tsx`
- `apps/web/src/ui/AppFrame.tsx`
- `apps/web/src/ui/AppProvider.tsx`
- `apps/web/src/ui/Page.tsx`
- `apps/web/vite.config.ts`
- `packages/client/src/accounts.ts`
- `packages/client/src/client.ts`
- `packages/client/src/errors.ts`
- `packages/client/src/garage-form.ts`
- `packages/client/src/index.ts`
- `packages/client/src/invoices.ts`
- `packages/client/src/models.ts`
- `packages/client/src/react/index.ts`
- `packages/client/src/react/use-account-settings.ts`
- `packages/client/src/react/use-ai-settings.ts`
- `packages/client/src/react/use-garage-form.ts`
- `packages/client/src/react/use-garage.ts`
- `packages/client/src/react/use-invoices.ts`
- `packages/client/src/react/use-page.ts`
- `packages/client/src/react/use-record.ts`

The following legacy presentation, styling, unrelated tests/fixtures and images
were searched for credential storage, logging, network/redirect and executable HTML
sinks, but were **not** fully reviewed line by line. Images are excluded binaries;
passing searches do not imply line-by-line coverage.

- `apps/mobile/src/components/animated-icon.module.css`
- `apps/mobile/src/components/animated-icon.tsx`
- `apps/mobile/src/components/animated-icon.web.tsx`
- `apps/mobile/src/components/ui/collapsible.tsx`
- `apps/mobile/src/constants/theme.ts`
- `apps/mobile/src/global.css`
- `apps/web/src/AccountSettings.test.tsx`
- `apps/web/src/App.test.tsx`
- `apps/web/src/ai/AiSettings.test.tsx`
- `apps/web/src/assets/hero.png`
- `apps/web/src/assets/react.svg`
- `apps/web/src/assets/vite.svg`
- `apps/web/src/garage/Garage.test.tsx`
- `apps/web/src/invoices/InvoicePolling.test.tsx`
- `apps/web/src/invoices/Invoices.test.tsx`
- `apps/web/src/test/render.tsx`
- `apps/web/src/test/setup.ts`

Installed-source verification only: `expo-secure-store/plugin/src/withSecureStore.ts`,
`android/src/main/res/xml/secure_store_backup_rules.xml` and
`secure_store_data_extraction_rules.xml`. Framework dependency internals, built bundles,
ignored environment values and real SecureStore contents excluded from this reviewer
coverage; dependency/bundle/secret review is delegated to the parent.

## Remediation (2026-10-10)

- C1 / SEC08: browser account loads and refreshes now carry auth generation
  ownership. Logout, expiry, authentication attempts and unmount invalidate old
  results. The transport also suppresses obsolete expiry notifications and refuses
  a mutation if account ownership changes while its CSRF cookie initializes.
  Regressions defer initial/refresh responses through logout/expiry and defer a
  transport 401/CSRF response through account replacement.
- C2 / SEC09: native SecureStore reads, saves and deletions are serialized.
  Unauthorized cleanup compares the credential used by the failed request inside
  that serialized operation. AuthProvider owns account responses by generation and
  rechecks after credential cleanup; accepting a login invalidates earlier refreshes
  before storage begins. Regressions cover delayed 401 with replacement credentials,
  delayed cleanup with replacement user state and logout during initial loading.
- C3 / SEC12: invoice picking owns a distinct feature-cache copy. Abandonment releases
  that copy; an active upload retains it until its request settles. Late picker
  results are released without restoring UI. Originals are never removed; feature
  cleanup rejects unrelated paths. Boot/picking sweep unleased feature copies older
  than 24 hours, covering previous process crashes when the app next runs. This is
  bounded retention cleanup, not a secure-erasure guarantee or an OS background job.
- SEC01 consumer support: profile forms submit current_password only for an email
  change, preserve failed drafts and clear successful password drafts. Name-only
  changes remain usable without a password. Server confirmation remains authoritative.

These are automated lifecycle/adapter checks using fake credentials and in-memory
files. Native release storage, OS crash behavior, platform filesystem copying and
live social providers still need release/device validation. The parent remediation
report records final combined gates and runtime checks.
