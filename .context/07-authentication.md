# Authentication implementation — 2026-10-08

The exploratory product scope is unchanged. Browser authentication now uses
Fortify + Sanctum cookie sessions, with explicit credentialed CORS and CSRF.
React provides registration/login, recovery/verification, profile/password and
optional two-factor settings. Registration stays closed in the actual local
workspace; test processes can enable it without changing that environment.

Socialite supports only built-in Google/GitHub, explicit identity linking, no
email-only merging, verified email for new social accounts, and no provider-token
storage. OAuth applications/credentials and live provider checks are deferred.

Expo uses named, limited, expiring Sanctum tokens in SecureStore. Native social
handoff uses a single-use code plus a verifier and requires a development build.
Android emulator email login/persistence/logout were checked; iOS and real native
provider callbacks remain unverified. Password changes revoke all native tokens;
stale browser sessions are rejected. Account deletion remains deferred until
retention/ownership rules exist. Horizon outside local development requires a
verified administrator; Telescope/Debugbar stay local-only.

See [authentication details](../docs/authentication.md) for contracts, testing and
production configuration still needed. Production mail, HTTPS/cookies and actual
OAuth credentials are external work intentionally skipped for now. Browser
Playwright checks run in CI after PHP tests against an isolated test database.

## Server reference pattern

Accounts Actions own device authentication, native handoff, identity persistence,
linking/unlinking and token revocation. They validate operation input, use explicit
actors and dependencies, and own transactions/locks. Password-confirmation timestamps
come from the authenticated server session; linking, unlinking and device-management
Actions enforce their freshness even for callers outside HTTP.

Controllers own request/session handling, Socialite's browser protocol, guard login,
redirects and HTTP responses. Expected domain failures have named factories and stable
reasons, with HTTP mapping in `bootstrap/app.php`. Database uniqueness conflicts are
handled inside the owning Actions. Direct Action tests cover reusable contracts,
including recovery-code rollback when token creation fails; endpoint tests cover the
HTTP/session boundary.

For future features, follow the reference operations in
[`architecture.blade.php`](../apps/server/.ai/guidelines/architecture.blade.php).
The PHPStan framework callback stubs match the installed Laravel implementation;
review them when upgrading rather than copying runtime result guards.

See [dependency audit](../docs/dependency-audit.md) for unresolved upstream SDK 57
findings. No forced major upgrades or speculative overrides were applied. Continue
with external configuration and verification when available, plus upstream fixes;
these limits must remain visible in the open GitHub work.

## Mobile account management — 2026-10-09

Mobile now has dedicated profile, password, two-factor, social-account and device
screens beside AI settings. The browser and mobile consume shared account operations
and `useAccountSettings`; apps keep their forms, routing and browser/native handoffs.

The `/api/v1/account` endpoints accept Sanctum cookies or device tokens. Reads require
`account:read`; writes require `account:write`, which newly issued device tokens
include. Existing devices must sign out and sign in once to obtain that permission.
Security operations validate the actor's password per request rather than borrowing
browser-session confirmation. Password updates reuse Fortify's updater, invalidate
reset tokens and revoke all mobile tokens; the current device then returns to sign-in.
Profile email changes reset verification and send a verification notification.

Two-factor operations reuse Fortify Actions behind password confirmation and row
locking. Setup secrets and recovery codes remain ephemeral UI state. Account API
responses are private/no-store and excluded from Telescope/Debugbar capture.

Native social linking uses a separate five-minute, actor-bound intent, OAuth state,
in-memory proof verifier and single-use completion. It never sends a bearer token
through the browser or creates a new login token. Unlinking and device revocation
reuse the existing authorized Actions. OAuth credentials and actual provider/device
callbacks remain external verification work; native callbacks require a development
build rather than Expo Go. Devices are mobile API tokens, not browser-session listings.

### Client lifecycle security (2026-10-10)

Email changes require current-password confirmation; browser and native profile
forms show that input only while changing the email. Successful saves clear it.
Auth responses are owned by a generation so expired/logged-out/replaced accounts
cannot reappear from delayed reads. Browser transport scopes expiry notifications
and pending CSRF setup to the current auth generation. Native token storage
serializes reads/writes/deletes and compares the failed request's token before
cleanup, preserving replacement sign-ins.

Invoice selections use their own cache directory and lease while an upload reads
it. Leaving the upload screen releases abandoned selections after any active
request settles. The app sweeps old unleased copies on boot and selection; it does
not delete original user files. Crash cleanup occurs on a later app launch, not as
an OS background guarantee.
