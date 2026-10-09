# Authentication

## Local use

The React browser app uses Laravel's shared `web` session through Fortify and
Sanctum. Start it with `npm run dev:web` and open **http://localhost:5173**;
Laravel runs at **http://localhost:8000**. Use the same hostname on both sides.
Browser requests include credentials and initialize `/sanctum/csrf-cookie` before
mutations. Session cookies are HttpOnly; the readable XSRF cookie supplies the
`X-XSRF-TOKEN` header. No bearer tokens are stored in browser storage.

`AUTH_REGISTRATION_ENABLED` controls both browser and Filament registration and
new social accounts. It remains **false in this workspace's local environment**.
The example environment keeps the skeleton bootstrap default of true. New accounts
are never administrators. Grant administration with `just artisan app:grant-admin EMAIL`.

Browser routes: `/login`, `/register`, `/forgot-password`, `/reset-password`,
`/verify-email`, `/account`. Account settings include profile/email changes,
password changes, optional authenticator enrollment/confirmation, recovery-code
rotation, social linking and named device revocation. Filament remains at `/admin`.
Confirmed Fortify two-factor authentication is also enforced by Filament login.

## Recovery and verification

Reset emails use `FRONTEND_URL/reset-password?token=...&email=...`. Browser verification
emails use `FRONTEND_URL/verify-email?url=...`, containing a signed Laravel URL.
The UI only follows verification URLs on the configured API origin and verification
path. Filament retains its own reset and verification URLs. Local mail goes to
Mailpit (`http://localhost:8025`); queued panel notifications require Horizon.

Reset links expire after 60 minutes and are consumed once. Unknown and known
accounts receive the same recovery response; invalid reset links also return the
same error for known and unknown emails. Recovery and registration are limited
to six requests/minute per IP/path; login, verification and token routes have
additional rate limits. Verification changes only the authenticated account;
changing email clears verification and sends a new message.

Unverified accounts can read their own account and use settings, recovery and
device revocation. Filament requires verification. There are no motorcycle write
APIs yet: require `verified` on future domain writes. This is a route policy,
not an invented blanket restriction on all authenticated endpoints.

## Social accounts

Only Socialite's built-in **Google and GitHub** drivers are allowed. Configure
server-only `GOOGLE_*` / `GITHUB_*` values; register callback URLs ending in
`/auth/google/callback` and `/auth/github/callback`. Buttons appear only when
client ID and secret are configured. Real provider applications are not configured
or verified yet. Apple and custom providers are outside the agreed scope.

Provider identities are unique globally and per user/provider. Login uses the
stored provider identity, never an email-only merge. New social accounts require
open registration and a verified provider email (Google's `email_verified`,
GitHub's primary verified email from Socialite). Tokens from providers are not
stored. Linking is explicit, authenticated and requires recent password confirmation;
unlinking cannot remove a social-only account's final sign-in method. A social-only
user can use password recovery to establish a password before password-confirmed
settings. A second provider cannot silently replace an existing provider link.

State, cancellation, duplicate identities and two-factor challenges are handled
server-side. Social-only accounts have a nullable password: rolling that migration
back requires first resolving accounts that still have null passwords.

## Native app

Expo uses expiring Sanctum bearer tokens with `account:read`, `garage:read`,
`garage:write`, `ai:read` and `ai:write` abilities. Garage writes and AI key
saving/connection tests additionally require a verified email. AI key removal
remains available without verification.
Existing device tokens retain their original abilities; sign out and sign in again
to obtain newly added garage and AI permissions.
`POST /api/v1/auth/tokens` checks credentials and any confirmed two-factor secret
before issuance, including one-use recovery codes. Tokens have a device name and
explicit expiry (`AUTH_DEVICE_TOKEN_TTL`, default 43,200 minutes / 30 days).
`DELETE /api/v1/auth/token` revokes the current device. Browser settings list and
revoke only the owner's devices, with recent password confirmation.

SecureStore stores token and expiry using `WHEN_UNLOCKED_THIS_DEVICE_ONLY`. Auth
hydrates before protected tabs render and refreshes on app resume; expired/revoked
tokens clear storage. Network errors retain the token for retry. Logout requires
server revocation before clearing local state; a failed connection offers retry.
Expo web directs users to the React app and does not persist native bearer tokens.

Native social login uses the system browser and a five-minute, single-use handoff.
A random verifier remains in app memory; Laravel stores its SHA-256 challenge.
After provider login and any two-factor challenge, the browser returns a code to
`AUTH_MOBILE_RETURN_URL` (default `motominator://auth-return`). The app exchanges
code + verifier for a token. Bearer tokens never appear in callback URLs. Use a
**development build** with the custom scheme for this flow; Expo Go is insufficient
for custom-scheme OAuth verification. The browser must be signed out before starting
a native social login; an existing web session is rejected rather than implicitly
selecting that account. Cancellation issues no token and the handoff expires.

Android emulator API default: `http://10.0.2.2:8000`. Override the public API origin
with `EXPO_PUBLIC_API_BASE_URL`; it contains no credentials. iOS simulator defaults
to localhost; physical devices require an accessible development API origin.

## Revocation and deletion policy

Any Eloquent password change (Fortify, recovery or Filament) revokes all device
tokens. Browser session password hashes are checked on subsequent requests;
stale sessions are rejected, while a password change in the current session updates
its hash. Password reset does not automatically log the user in. Sanctum expires
tokens at request time; the scheduler removes expired records daily after 24 hours.

Account deletion and a general browser-session management UI are deferred: define
data retention, ownership and account recovery consequences before adding deletion.
No account deletion endpoint is exposed. Provider linking, device revocation and
secret changes require password confirmation; recovery codes and secrets stay in
component memory and disappear when account UI unmounts.

## Production configuration to complete

Set real HTTPS `APP_URL` and `FRONTEND_URL`, explicit `CORS_ALLOWED_ORIGINS`
(no wildcard), and `SANCTUM_STATEFUL_DOMAINS` with host/port values. Deploy the
browser and API on the same site for this cookie design. Set `SESSION_SECURE_COOKIE=true`,
`SESSION_HTTP_ONLY=true`, appropriate `SESSION_DOMAIN`, `SESSION_SAME_SITE=lax`,
`APP_ENV=production`, `APP_DEBUG=false`, and close registration as intended.
Configure trusted proxies to match the actual ingress. Keep `APP_KEY`, OAuth and
mail credentials in server secrets; retain shared cache/locks and working queues.

Horizon permits verified administrators outside local development. Telescope and
Debugbar are registered only in local development. Real mail transport, sender-domain
authentication, provider callbacks, production cookie checks and native OAuth
verification remain external setup work; no production readiness is claimed.

## Verification

- `just test`: Laravel authentication, account isolation, limits, reset/verification,
  two-factor and recovery codes, Socialite mocks, device tokens, native handoff,
  Filament and development-tool authorization.
- `npm test`: browser UI/API and native token/storage/handoff unit tests.
- `npm run test:e2e --workspace=@motominator/web`: real Chrome cookie/CSRF flow,
  registration/login/logout and settings/two-factor checks. Local configuration
  launches an ephemeral Sail server on 8001 and Vite on 5179 with registration open
  **only in that server process**, using the `testing` database. Run after PHP tests,
  never concurrently with them. A local Chrome installation is required. If an
  interrupted run leaves its `motominator-laravel.test-run-*` container alive,
  stop that specific container before rerunning. CI uses isolated MySQL + Chromium.
- Android API 35 emulator / Expo Go SDK 57: email/password login, SecureStore
  persistence after process restart, protected tabs and logout token revocation
  were manually checked on 2026-10-08 using a disposable test account.

Mocked providers and exported bundles do not prove real provider/native callbacks,
iOS behavior, production mail or deployed HTTPS behavior. Those checks remain open.
