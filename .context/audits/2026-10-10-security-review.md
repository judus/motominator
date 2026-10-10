# Technical security and privacy review

Date: 2026-10-10. Baseline `main` at
`222a60ad9587c43a04fc6a1d9d8992cf148860f1`; current uncommitted/untracked workspace
reviewed. Scope: account/admin authentication, garage/invoice ownership, AI/BYOK,
activity/diagnostics, browser/native/shared-client state and transport, repository,
dependencies and development/CI configuration. Three independent subagents plus
root review. Original audit below; subsequent remediation is recorded at the end.
No commits, tickets or publication.

## Assessment

**The original audited workspace needed fixes.** Ownership checks,
encrypted BYOK/drafts, safe invoice downloads, OAuth state, token hashing and secret
suppression provide a useful foundation. However, the audit found concrete defects
that existing green tests do not cover. No finding is evidence that an actual user
was compromised. Severity depends on the prerequisites below.

## Evaluated findings

| ID     | Severity                               | Confirmed issue and prerequisite                                                                                                                                             | Evidence / fix direction                                                                                                                                                                                                                                                                                                                                                                                                                        |
| ------ | -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| SEC-01 | High                                   | Stolen account token/session can replace recovery email without password confirmation, reset password and persist access. Enabled2FA still blocks fresh password-only login. | [AUTH-1](security-auth.md#auth-1--high-a-stolen-sessiontoken-can-replace-the-recovery-mailbox-without-reauthentication), isolated HTTP proof. Require reauthentication for trusted email changes across both API/Fortify paths.                                                                                                                                                                                                                 |
| SEC-02 | High on reachable development networks | Redis, Mailpit and diagnostic dashboards are anonymously accessible and published on all interfaces; Telescope captures ordinary private garage data.                        | [P1](security-platform.md#p1--high-in-the-current-development-network-private-infrastructure-and-diagnostics-are-exposed) + [D1](security-data.md#d1--private-garage-data-remains-in-development-diagnostics-medium-by-itself-high-when-combined-with-exposed-unauthenticated-telescope). Host runtime proof; second-device/firewall reachability not tested. Isolate backing services, authenticate dashboards, suppress personal diagnostics. |
| SEC-03 | Medium, conditional takeover           | Native social link writes a login identity before authenticated verifier completion; obtaining a victim's pending random intent URL lets an attacker supply their identity.  | AUTH-2, direct Action proof. OAuth state does not replace native app proof. Delay mutation until authenticated consume.                                                                                                                                                                                                                                                                                                                         |
| SEC-04 | Medium                                 | An already approved native login intent can mint a token after password revocation within its5-minute life.                                                                  | AUTH-3, direct Action proof. Bind approvals and token issuance to current security epoch under lock.                                                                                                                                                                                                                                                                                                                                            |
| SEC-05 | Medium                                 | Retained browser password-confirmation/update endpoints have no rate limit. Requires an authenticated session; native throttling does not cover them.                        | AUTH-4, installed route/controller/middleware trace. No brute-force load performed. Add actor/IP throttles consistently.                                                                                                                                                                                                                                                                                                                        |
| SEC-06 | Medium                                 | Admin password replacement leaves an earlier valid reset token usable. Requires possession of that token.                                                                    | AUTH-5, independently reproduced against actual EditUser adapter and broker. Centralize password-change revocation/reset-token side effects.                                                                                                                                                                                                                                                                                                    |
| SEC-07 | Medium privacy                         | Unexpected SQL errors can copy bound maintenance/workshop prose into ordinary logs despite credential redaction.                                                             | D2, synthetic actual QueryException/redactor independently reproduced. Enable framework binding masking and preserve safe diagnostics.                                                                                                                                                                                                                                                                                                          |
| SEC-08 | Medium privacy                         | An old browser refresh can restore identity and signed-in shell after logout. Server session remains revoked.                                                                | C1, actual source/fake transport independently reproduced. Invalidate stale auth loads on logout/expiry/account change.                                                                                                                                                                                                                                                                                                                         |
| SEC-09 | Medium availability                    | A stale native request401 clears a newer stored token.                                                                                                                       | C2, actual source/fake SecureStore independently reproduced. Own/serialize credential mutations by authentication generation.                                                                                                                                                                                                                                                                                                                   |
| SEC-10 | Medium availability                    | Verified account uploads are bounded per request/minute but unlimited cumulatively; persistent volume can be exhausted.                                                      | D3, source trace. No load attack. Add account/storage quotas with atomic accounting.                                                                                                                                                                                                                                                                                                                                                            |
| SEC-11 | Medium availability + tooling debt     | Installed Expo Router query parsing reaches a vulnerable decode-uri-component dependency.                                                                                    | P2, current npm advisory + installed dependency trace; no malicious device workload. Review compatible fix separately from forced SDK-major suggestions.                                                                                                                                                                                                                                                                                        |
| SEC-12 | Low privacy                            | Abandoned native invoice selections leave private sandbox cache copies until eviction.                                                                                       | C3, lifecycle/source trace. Release app-owned copies safely on dismissal/completion and bound stale cache retention.                                                                                                                                                                                                                                                                                                                            |

SEC-02 combines network exposure and diagnostics capture, avoiding duplicate findings.
SEC-03 requires disclosure of an unpredictable pending intent; it is not an anonymous
public endpoint takeover. SEC-04 requires a prior approved intent and its verifier.
SEC-08/09 are client state/availability defects, not server authorization bypasses.
These qualifications are part of the findings, not reasons to silently dismiss them.

## Validation

- Existing server suite: **218 tests,1190 assertions passed**.
- Existing clients: **37 browser +37 native tests passed**.
- Isolated authentication probes: **5 tests,17 assertions**, demonstrating email
  takeover/2FA boundary, stale native approval, pre-proof native link and admin reset
  token reuse. Fake accounts, notifications, array cache/session and testing DB only.
- Both client races independently reproduced with actual transpiled source and fake
  transport/storage. This is narrower than a browser/device integration test.
- Synthetic SQL prose leak independently reproduced with real installed exception
  class and application log processor; no live query failure/private logs accessed.
- Host runtime checks confirm anonymous Redis/dashboard/Mailpit access and published
  ports. Only status/type/booleans printed; private diagnostic/mail payloads not dumped.
- Composer audit: **zero advisories**. npm audit: **77 affected package nodes**
  (59 high,17 moderate,1 low), originating from six package advisories; counts are
  not numbers of independent application vulnerabilities.
- Limited masked secret scans:569 current text files and335 historical text blobs,
  no recognized key candidates. Not a full entropy/PII scan or unavailable remote-ref audit.

No paid/live AI call was needed to establish these findings. No real accounts,
invoices, credentials, queue payloads or firewall configuration were changed.

## Release controls still open

The repository contains development/CI configuration, not an audited production
deployment. Actual TLS/cookie/CORS/proxy settings, disabled production diagnostics,
storage and backup encryption/access, deletion/export and backup expiry, provider
retention, signed native builds/OTA and effective mobile backup/permission settings
still need verification. Development HTTP is unsuitable for confidential transport.
No infrastructure pentest, DoS, native binary reverse engineering, physical-device
or live OAuth-provider assessment was performed.

When implementing merge pipelines, include security regressions for these contracts,
dependency and secret scanning with explicit advisory dispositions, and reviewable
production configuration gates. Existing lint/static analysis/style gates remain
useful; they do not establish those security contracts by themselves.

## Coverage and next work

Individual reviewed/excluded-file ledgers and rejected candidates:

- [Authentication/admin](security-auth.md)
- [Data, invoices, AI and diagnostics](security-data.md)
- [Browser, native and shared client](security-clients.md)
- [Platform, dependencies and root reconciliation](security-platform.md)

Fix account recovery/native proof/revocation and development exposure first, then
diagnostics and client ownership races, throttling/reset-token consistency, quotas
and cache cleanup. Review the dependency fix with installed-SDK compatibility; do
not force upgrades or erase the advisory gate. Add regressions that fail on the
audited behavior, then rerun affected integrations and reconcile each finding.
Privacy retention/deployment controls need explicit technical decisions before
external users, rather than silently inventing deletion policies during fixes.

This is a bounded evidence-backed review, not ASVS certification or proof that all
possible vulnerabilities have been found.

## Remediation — 2026-10-10

The user authorized fixes. Three implementation agents and root review addressed
the confirmed application/runtime findings; this supersedes the original assessment.

| Finding | Implemented protection                                                                                                                                              |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| SEC-01  | Current password required for email changes; fresh locked user validation across API/Fortify.                                                                       |
| SEC-02  | Backing services/Vite bind to loopback; verified-admin gates apply locally; personal diagnostics suppressed. Backend HTTP stays LAN-reachable.                      |
| SEC-03  | Callback stages provider identity; authenticated verifier consumption commits the link.                                                                             |
| SEC-04  | Server-only credential/security fingerprints bind pending native approvals and linking; fresh locked checks reject revoked proof.                                   |
| SEC-05  | Retained password checks have independent actor and IP rate limits.                                                                                                 |
| SEC-06  | Central password observer revokes device/reset tokens; admin password writes and revocation are atomic.                                                             |
| SEC-07  | Database bindings masked; logs suppress exception messages/arguments/previous exceptions while keeping safe diagnostic metadata.                                    |
| SEC-08  | Browser auth generations reject stale loads/expiry; stale CSRF preparation cannot submit under replacement cookies.                                                 |
| SEC-09  | Native credential storage serializes comparison/deletion; stale 401 cannot erase replacement tokens.                                                                |
| SEC-10  | Account allocation is serialized with default 100 documents / 100 MiB; failed/partial writes are cleaned up.                                                        |
| SEC-11  | Runtime decoder upgraded to 0.5.0 with integrity-checked CommonJS/ESM bridge and actual-router regressions. Five tooling advisory roots remain open.                |
| SEC-12  | Native invoice copies are feature-owned, leased during selection/upload, cleaned on abandonment/completion and swept after 24 h when unleased. Originals preserved. |

Independent follow-up also fixed stale password-change/reset/link/unlink/revocation
checks and admin password-update/revocation transaction gaps. Reset validity is
checked again under the user lock. A broker-failure regression proves admin rollback.

### Final evidence

- Full PHP suite: **245 tests, 1328 assertions passed**, against a freshly reset
  disposable MySQL testing database, without overlapping browser writes.
- Clients: **44 browser + 53 native tests passed** after clean `npm ci`.
- Maximum-level PHPStan: zero errors. PHPCS, JS lint/typecheck/format and
  `git diff --check` pass.
- Browser auth E2E passes, including real cookie/CSRF/2FA/password flows and
  incorrect-password rejection for an email change.
- Browser/server builds and Android/iOS/web Expo exports pass. Tamagui prompt
  generation passes; exported bundles do not prove signed device releases.
- Actual router regression preserves encoded Unicode/plus/separators and processes
  long malformed UTF-8 without the vulnerable recursive decoder.
- Composer audit: zero advisories. npm: **74 affected nodes** (59 high, 14 moderate,
  1 low) from five tooling roots. Runtime decoder advisory absent; see
  [dependency dispositions](../../docs/dependency-audit.md).
- Running backend status 200; anonymous Telescope/Horizon 403. Docker publishes
  MySQL/Redis/Mailpit/Vite only on 127.0.0.1. Physical-device firewall reachability
  was not re-tested; the normal backend's LAN binding is preserved.
- Old abandoned test server removed; new temporary servers bind to loopback and
  are removed on graceful termination. Their database is container-local SQLite,
  independent of the normal app and PHP test database; browser CI overrides remain
  under CI ownership.
- Pruned 19,344 historical Telescope entries after changing capture; restarted the
  actual Horizon container. Ordinary historical logs and backups were preserved.

The first final run accidentally overlapped MySQL-backed browser/PHP tests, causing
four fixture-count failures. The recorded PHP result is the isolated rerun. Local
browser servers now use disposable SQLite to prevent that overlap and retained
browser fixtures; the server suite continues to cover MySQL behavior.

Known production/retention/tooling controls above remain open. The fixes do not
establish a clean dependency audit, a production deployment approval or absolute
security. No real account, invoice or credential content was altered.
