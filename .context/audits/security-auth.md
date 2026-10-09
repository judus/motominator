# Security audit: Accounts and authentication

Date: 2026-10-10. Repository: `/home/maduser/workspace/motominator`.
Baseline: branch `main`, HEAD `222a60ad9587c43a04fc6a1d9d8992cf148860f1`.
Reviewed working tree, not just HEAD: entire new `app/Accounts` is untracked after domain moves; User/SocialIdentity, routes, bootstrap and guidance have pending modifications. Many other app changes exist; preserved. This report is not Git-ignored. No credentials, real account payloads or provider responses are included.

Scope: Accounts application/authentication code, user authorization and admin user resource, auth-related configuration and contracts. Read-only application review; isolated fake-user probes were explicitly authorized. No implementation changes, commits, external provider linking or real-user mutations.

## Confirmed findings

### AUTH-1 — High: a stolen session/token can replace the recovery mailbox without reauthentication

Evidence: `app/Accounts/Actions/Fortify/UpdateUserProfileInformation.php:28-44,58-66` validates name/email only and immediately replaces authoritative `users.email`. Both `PUT /api/v1/account/profile` (account:write) and retained cookie `PUT /user/profile-information` call it. API routes authenticate and throttle but do not require current password. Fortify password broker resolves recovery by that new email and does not require `email_verified_at` (`vendor/laravel/framework/src/Illuminate/Auth/Passwords/PasswordBroker.php:sendResetLink,validateReset`).

Exploit prerequisite: stolen account:write bearer or valid victim browser session; attack controls replacement mailbox. Change email, request forgot-password to replacement, receive reset link, set password. Token theft becomes persistent account takeover for accounts without 2FA. Resetting email verification does not protect the reset channel. For enabled 2FA, reset preserves the authenticator and fresh password-only login still requires challenge; nevertheless attacker controls password/recovery address and can deny owner recovery. No claim of authenticator bypass.

Validation: isolated HTTP probe proved profile200 -> unverified new email -> fake reset notification addressed to changed user -> reset200 -> fresh native token201 with attacker password. Separate probe proved enabled 2FA remained and new native login returned202 challenge.

Fix direction: require fresh reauthentication for email change in the shared Action, including retained Fortify endpoint; consider pending-email verification before replacing trusted recovery email, and old-address change notification. Keep harmless name editing independent.

### AUTH-2 — Medium, conditional takeover: native identity link commits before verifier confirmation

Evidence: `app/Accounts/Http/Controllers/SocialAuthController.php:37-47` accepts the pending native_link code in any browser session; real provider OAuth state is then generated for that browser. Callback `:100-112` calls `CompleteNativeSocialLink`, whose `:27` writes SocialIdentity. `ConsumeNativeSocialLink.php:15-31` subsequently checks actor/verifier but only forgets cache; it cannot undo or prevent the committed link.

Exploit prerequisite: obtain victim's random pending native_link URL/code during its 5-minute lifetime, plus authenticate attacker's own configured provider identity. Victim normally created intent after password confirmation. An attacker cannot manufacture a victim intent with only a public endpoint or guessed code. Once code is disclosed, attacker can open own OAuth browser flow and link own identity to victim before any victim verifier proof; failed/cancelled final app completion leaves identity linked. OAuth state is correct for attacker browser and does not bind it to initiating native app.

Impact: persistent additional login method; still passes existing victim 2FA requirement when configured. For no-2FA accounts it enables takeover. Ordinary custom-scheme interception only after callback is not by itself a substitute-provider exploit; attacker must get the pending code before commit.

Validation: direct Action probe proved SocialIdentity persisted immediately after callback-stage completion without ever supplying verifier; existing native account test also intentionally asserts linking at browser callback. OAuth provider interaction mocked in existing tests, not live-tested.

Fix direction: callback records verified provider identity as pending data; authenticated native consume verifies actor and challenge then performs the link atomically. Cancellation/invalid proof must leave no new login identity.

### AUTH-3 — Medium: approved native handoff survives password revocation

Evidence: `app/Accounts/Data/NativeAuthIntent.php` stores user ID/expiry/challenge, no credential version. `ApproveNativeAuth.php` saves user ID; `ExchangeNativeAuth.php:30-42` later finds current user and issues token based solely on cached approval. `app/Models/User.php:78-85` revokes existing personal tokens when password changes but does not invalidate pending native approvals.

Exploit prerequisite: attacker has an already approved native intent and its verifier, then password changes/reset before exchange within 5 minutes. Exchange mints a fresh 30-day token after supposed credential revocation. This is a bounded stale-authorization problem, not password guessing or OAuth-state bypass. Source also shows no re-evaluation if 2FA is enabled after approval, but that separate transition was not runtime-probed.

Validation: direct Action probe approved intent, changed user password, verified zero tokens, exchanged old intent and verified one fresh token. Password reset uses the same User save/event path; actual reset-between-approval HTTP scenario not separately executed.

Fix direction: capture and compare an authentication/security epoch tied to credentials and relevant security changes; lock around recheck/token issuance. Apply same temporal check to pending native link approvals.

### AUTH-4 — Medium: retained browser password oracles lack rate limiting

Evidence: installed `vendor/laravel/fortify/routes/routes.php:112-130` exposes `PUT /user/password` and `POST /user/confirm-password` with only auth:web. `app/Accounts/Http/Middleware/ThrottleRecoveryRequests.php:21-31` limits register/forgot/reset only. No global web throttle in `bootstrap/app.php`. Controllers execute current-password hash check and return success/error; native account settings10/min does not cover these old endpoints.

Exploit prerequisite: authenticated victim browser session plus CSRF token (e.g. session theft), or authenticated user's own session for hash-computation abuse. Unlimited password guesses against the victim's current password defeat intended reauthentication protection and can impose hash CPU load. Login throttle does not apply.

Validation: source/middleware trace, no live brute-force workload generated.

Fix direction: add explicit actor and IP-based throttles to all password-verification endpoints, keeping compatibility routes under same policy.

### AUTH-5 — Medium: administrative password edit leaves pre-existing reset token usable

Evidence: `app/Accounts/Filament/Resources/Users/Pages/EditUser.php:14-22` directly updates model; User updated handler deletes device tokens only. Generic Filament EditRecord has no password-reset invalidation behavior. In contrast, `AccountSettingsController::password` and installed Fortify `PasswordController::update` explicitly delete broker tokens.

Exploit prerequisite: possession of valid previously issued reset token (up to60min), then administrator changes user password without changing email. The old reset token remains valid and can replace the new password, undermining administrative credential recovery. 2FA still applies after reset where enabled.

Validation: root added an isolated fake-user probe invoking the actual protected
`EditUser::handleRecordUpdate` adapter. An earlier broker token remained valid and
successfully reset the administratively replaced password. This proves the adapter
and broker behavior; it is not a complete Livewire UI test. No real account changed.

Fix direction: centralize password-change security side effects for HTTP and administrative paths, including invalidating reset tokens and consistent events. Preserve admin authorization and hashing.

## Rejected candidates and deliberately retained defenses

- Cross-user token revocation: tokens queried through explicit actor relation, foreign IDs404. Cookie TransientToken distinguished from actual personal token.
- is_admin mass assignment / registration escalation: User fillable excludes is_admin; regular registration/admin user forms cannot hydrate it; grants are explicit CLI. Policy and FilamentUser always restrict admin even local env.
- Provider email auto-merge takeover: authentication resolves provider+provider_user_id and deliberately rejects existing-email registration. DB unique constraints protect identity conflicts. GitHub provider installed source only selects primary verified email; Google explicitly checks email_verified.
- OAuth state disabled: code uses normal Socialite user(), installed state validation retained; no stateless() in callbacks.
- General social login2FA bypass: callback routes existing 2FA through Fortify challenge before web login/native approval. Password-only device authentication checks 2FA before issuing token.
- Single-use code races in custom native flow: cache locks serialize approve/exchange/consume; hashed64-character random code, challenge match and expiry are retained. These defenses do not solve AUTH-2/3.
- Plaintext secret persistence in model serialization: password/remember/2FA secret/recovery hidden; Fortify encrypts secret/recovery; explicit account JSON omits secrets/provider token payloads. Device JSON omits token/hash.
- Unprotected cookie API mutation: Sanctum stateful pipeline installs CSRF and session authentication only for configured frontend origins; untrusted origins receive no usable cookie session. Native bearer traffic intentionally does not require cookie CSRF. CORS is explicit, credentials allowed only for configured origins.
- Forgotten password user enumeration: generic responses and installed broker timebox reduce direct response/timing signal. Registration/profile uniqueness still disclose existence to callers as normal current product contract; not silently called fully anonymous.
- Future confirmedAt timestamp: current external callers do not accept client confirmation timestamp. Values arise from server time or protected session/cache; no external bypass established.

## Validation and limits

Temporary probe: `/tmp/AuditAuthProbeTest.php`, copied into running Sail container `/tmp` and executed with `./vendor/bin/sail bin phpunit /tmp/AuditAuthProbeTest.php`. Test config selects database `testing`, fake notifications, array cache/sessions and no provider calls. Root coordinated exclusive database access. Initial reviewer result: **4 tests passed,14 assertions**. Root independently reran these and added the admin-password probe: **5 tests passed,17 assertions**. Full project gates belong to root consolidation; existing tests below were read, not re-run by this reviewer. No actual production deployment, TLS/session cookie configuration, remote OAuth applications, live provider calls, parallel recovery-code exhaustion, concurrent browser password reset or external pentest established here.

AUTH-4 remains source-confirmed without a brute-force workload. AUTH-5 now has the
root's isolated adapter/broker reproduction. No production or real-account settings
inspected. Infrastructure/secrets/log redaction, AI invoice processing and client
storage/deep-link handling belong to other reviewers.

## Coverage ledger

All following application/config/test files reviewed directly; searches used for callers, not coverage proof. Paths under repository root unless stated.

### Accounts source (47 files, all reviewed)
- `apps/server/app/Accounts/Actions/ApproveNativeAuth.php`
- `apps/server/app/Accounts/Actions/AuthenticateDevice.php`
- `apps/server/app/Accounts/Actions/AuthenticateSocialAccount.php`
- `apps/server/app/Accounts/Actions/CompleteNativeSocialLink.php`
- `apps/server/app/Accounts/Actions/ConfirmAccountPassword.php`
- `apps/server/app/Accounts/Actions/ConsumeNativeSocialLink.php`
- `apps/server/app/Accounts/Actions/EnsureRecentPasswordConfirmation.php`
- `apps/server/app/Accounts/Actions/EnsureSocialProvider.php`
- `apps/server/app/Accounts/Actions/ExchangeNativeAuth.php`
- `apps/server/app/Accounts/Actions/Fortify/CreateNewUser.php`
- `apps/server/app/Accounts/Actions/Fortify/PasswordValidationRules.php`
- `apps/server/app/Accounts/Actions/Fortify/ResetUserPassword.php`
- `apps/server/app/Accounts/Actions/Fortify/UpdateUserPassword.php`
- `apps/server/app/Accounts/Actions/Fortify/UpdateUserProfileInformation.php`
- `apps/server/app/Accounts/Actions/IssueDeviceToken.php`
- `apps/server/app/Accounts/Actions/LinkSocialIdentity.php`
- `apps/server/app/Accounts/Actions/ManageAccountTwoFactor.php`
- `apps/server/app/Accounts/Actions/RevokeCurrentDeviceToken.php`
- `apps/server/app/Accounts/Actions/RevokeDeviceToken.php`
- `apps/server/app/Accounts/Actions/StartNativeAuth.php`
- `apps/server/app/Accounts/Actions/StartNativeSocialLink.php`
- `apps/server/app/Accounts/Actions/UnlinkSocialIdentity.php`
- `apps/server/app/Accounts/Console/Commands/GrantAdmin.php`
- `apps/server/app/Accounts/Data/NativeAuthIntent.php`
- `apps/server/app/Accounts/Data/NativeLinkIntent.php`
- `apps/server/app/Accounts/Exceptions/AccountsAuthenticationException.php`
- `apps/server/app/Accounts/Exceptions/AccountsNativeAuthException.php`
- `apps/server/app/Accounts/Exceptions/AccountsSocialException.php`
- `apps/server/app/Accounts/Filament/Auth/Register.php`
- `apps/server/app/Accounts/Filament/Resources/Users/Pages/CreateUser.php`
- `apps/server/app/Accounts/Filament/Resources/Users/Pages/EditUser.php`
- `apps/server/app/Accounts/Filament/Resources/Users/Pages/ListUsers.php`
- `apps/server/app/Accounts/Filament/Resources/Users/Schemas/UserForm.php`
- `apps/server/app/Accounts/Filament/Resources/Users/Tables/UsersTable.php`
- `apps/server/app/Accounts/Filament/Resources/Users/UserResource.php`
- `apps/server/app/Accounts/Http/Controllers/AccountController.php`
- `apps/server/app/Accounts/Http/Controllers/AccountSettingsController.php`
- `apps/server/app/Accounts/Http/Controllers/DeviceTokenController.php`
- `apps/server/app/Accounts/Http/Controllers/NativeSocialAuthController.php`
- `apps/server/app/Accounts/Http/Controllers/NativeSocialLinkController.php`
- `apps/server/app/Accounts/Http/Controllers/SocialAuthController.php`
- `apps/server/app/Accounts/Http/Middleware/ThrottleRecoveryRequests.php`
- `apps/server/app/Accounts/Http/Responses/InvalidRecoveryResponse.php`
- `apps/server/app/Accounts/Http/Responses/RecoveryLinkResponse.php`
- `apps/server/app/Accounts/Policies/UserPolicy.php`
- `apps/server/app/Accounts/Providers/AccountsServiceProvider.php`
- `apps/server/app/Accounts/Providers/FortifyServiceProvider.php`

### Auth integrations/configuration (reviewed)
- `apps/server/app/Models/User.php`
- `apps/server/app/Models/SocialIdentity.php`
- `apps/server/app/Http/Controllers/Controller.php`
- `apps/server/app/Providers/Filament/AdminPanelProvider.php`
- `apps/server/app/Http/Middleware/ProtectSensitiveData.php`
- `apps/server/app/Providers/AppServiceProvider.php`
- `apps/server/routes/api.php`
- `apps/server/routes/web.php`
- `apps/server/bootstrap/app.php`
- `apps/server/bootstrap/providers.php`
- `apps/server/config/auth.php`
- `apps/server/config/session.php`
- `apps/server/config/sanctum.php`
- `apps/server/config/fortify.php`
- `apps/server/config/services.php`
- `apps/server/config/cors.php`
- `apps/server/phpunit.xml`
- `apps/server/tests/TestCase.php`

### Tests reviewed (9 feature files)
- `apps/server/tests/Feature/AccountSettingsTest.php`
- `apps/server/tests/Feature/NativeAccountSettingsTest.php`
- `apps/server/tests/Feature/AccountsActionsTest.php`
- `apps/server/tests/Feature/AdminPanelTest.php`
- `apps/server/tests/Feature/AdminAccountRecoveryTest.php`
- `apps/server/tests/Feature/BrowserAuthenticationTest.php`
- `apps/server/tests/Feature/DeviceTokenTest.php`
- `apps/server/tests/Feature/SocialAuthenticationTest.php`
- `apps/server/tests/Feature/NativeSocialAuthTest.php`

### Schema reviewed
- `apps/server/database/migrations/0001_01_01_000000_create_users_table.php`
- `apps/server/database/migrations/2026_10_07_215848_create_personal_access_tokens_table.php`
- `apps/server/database/migrations/2026_10_07_215848_add_two_factor_columns_to_users_table.php`
- `apps/server/database/migrations/2026_10_07_230726_add_is_admin_to_users_table.php`
- `apps/server/database/migrations/2026_10_07_235318_create_social_identities_table.php`

User-password-nullable migration located by search; not fully reviewed. Framework files inspected selectively: Fortify route registrations, PasswordResetLinkController, NewPasswordController, PasswordController, ConfirmablePasswordController, CompletePasswordReset, TwoFactorLoginRequest; Laravel PasswordBroker; Sanctum Guard and EnsureFrontendRequestsAreStateful; Socialite GithubProvider; Filament Authenticate and generic EditRecord password handling search. Remaining vendor code excluded as third-party internals beyond traced contracts. Deleted pre-domain paths excluded; current replacements above reviewed. No pending application files in assigned Accounts scope.

## Remediation update (2026-10-10)

AUTH-1 through AUTH-5 implemented in the Accounts domain:

- Both retained Fortify profile and native profile API require explicit current_password
  for an actual email change; unchanged email/name edits stay independent. The shared
  Action locks and rereads the actor before confirmation/write. Verification resets and
  sends after the write. Existing password-reset tokens for the former mailbox are removed.
- Native OAuth linking stages only the provider identity. Authenticated proof consumption
  performs the actual link inside a transaction after actor, expiry, verifier and current
  security fingerprint checks. Wrong/cancelled proof leaves no identity committed.
- Approved native login captures a credential fingerprint; exchange checks a freshly
  locked actor before issuing a device token. Password, email, authenticator and recovery
  code changes invalidate approved exchanges. Browser authentication stores a separate
  pre-approval fingerprint covering password/email/authenticator state: ordinary recovery
  code consumption during 2FA is intentionally excluded there, then the full fresh state
  is captured at approval. Native linking captures full state at its password confirmation.
- Retained password confirmation/update/profile password checks share independent actor
  (10/minute) and IP (60/minute) limits. Existing native API throttles remain intact.
- Injected UserSecurityObserver owns password-change token revocation and password-broker
  token deletion for model, Fortify and Filament paths. Accounts provider registers it;
  no application service locator is added to model code.

Focused Accounts/admin suite passed 100 tests / 504 assertions. This includes both email
reauthentication adapters, pending-link absence/wrong proof, pre/post-approval credential
changes, authenticator/recovery regeneration and the real Filament update adapter's
reset-token invalidation. A further actual reset-between-approval endpoint regression and
final locked profile write are awaiting the coordinated full-suite run. Live provider apps
and production/release configuration remain outside this evidence.

Final concurrency review additionally found and fixed stale request-loaded credentials
in password changes and the gap between broker reset validation and its write. Both
Actions now lock/reread the user; reset token validity is checked again inside that
transaction. Link/unlink/device revocation similarly reject changed actor credentials
at their locked writes. The Filament update adapter now locks a fresh record and keeps
password update plus observer revocation in one transaction. A simulated broker failure
proves password/device changes roll back together. Additional focused suite passed
56 tests / 306 assertions; expanded authentication suite before the final rollback test
passed 106 tests / 519 assertions. Coordinated root full-suite evidence supersedes these
focused counts when recorded in the consolidated security report.
