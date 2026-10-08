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

See [dependency audit](../docs/dependency-audit.md) for unresolved upstream SDK 57
findings. No forced major upgrades or speculative overrides were applied. Continue
with external configuration and verification when available, plus upstream fixes;
these limits must remain visible in the open GitHub work.
