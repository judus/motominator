# Security audit: platform, dependencies and repository hygiene

Date: 2026-10-10. Repository `motominator`, branch `main`, baseline
`222a60ad9587c43a04fc6a1d9d8992cf148860f1`. Current dirty working tree reviewed.
Read-only application audit: reports and disposable probes only; no configuration,
code, firewall, real-account or production changes. Reports are not Git-ignored.

## P1 — High in the current development network: private infrastructure and diagnostics are exposed

`apps/server/compose.yaml:13-15,68-69,93-94,108-110` publishes the app,
database, Redis and Mailpit without a host bind address. Runtime `docker ps`
confirmed `0.0.0.0` and IPv6 publications: HTTP8000, Redis6379, MySQL3307,
Mailpit8025/1025 and Vite5174. A prior temporary test server is also published on8001.
No service was stopped during this audit.

Read-only runtime proof from the host, without user-data output:

- Redis6379 answers unauthenticated PING with PONG.
- Telescope and Horizon pages return200 without account login.
- Telescope request-list API returns JSON200 with an anonymous browser session
  and its public CSRF token; Horizon stats API returns JSON200 anonymously.
- Mailpit UI returns200 anonymously. Mailpit has no configured authentication here.
- Protected `/api/v1/user` correctly returns401 for an anonymous request.

Telescope authorization intentionally trusts `APP_ENV=local`; Horizon has the same
local-environment bypass. Telescope records ordinary garage requests and responses
(D1 in `security-data.md`). Someone who can reach these published ports can read
diagnostics and captured mail/reset links or manipulate the unauthenticated queue
store. This audit did not enumerate private records or attempt queue injection/RCE.
MySQL still requires credentials; its publication alone is not an authentication bypass.

Local development defaults explain the setup, but real invoices and BYOK use mean
the network must not be treated as inherently trusted. Loopback-bind/remove host
publications for Redis, MySQL, Mailpit and tool ports; require explicit administrator
authorization for diagnostic dashboards even locally. Keep only the deliberate
mobile-facing HTTP/Metro access available to devices. Test-server publication should
be loopback-bound and its container reliably cleaned up.

Docker's published ports can bypass normal UFW rules. The host probe proves local
anonymous access and all-interface binding, not connectivity from a second physical
device, Internet exposure, router forwarding or the effective Docker firewall policy.
Source: [Docker and UFW](https://docs.docker.com/engine/network/packet-filtering-firewalls/#docker-and-ufw).

## P2 — Medium runtime availability risk plus tooling debt: dependency audit is not clean

Fresh npm registry audit: **77 affected package nodes**, 59 high,17 moderate,1 low,
zero critical. These are propagated findings, not77 independent vulnerabilities.
Six packages carry originating advisories: braces, decode-uri-component, esbuild,
node-forge, sprintf-js and uuid. No package changes or overrides applied.

`decode-uri-component`0.2.2 is used through `query-string` in Expo Router's actual
`getStateFromPath` query parsing. The installed version is affected by malformed
input CPU exhaustion; this is a runtime URL-processing concern, not merely test
tooling. The advisory identifies0.5.0 as fixed, while npm's parent fix proposes an
Expo Router major change. Compatibility needs review and tests; do not blindly force
the suggested upgrade. No malicious deep-link workload was sent to the emulator.
Source: [upstream advisory](https://github.com/advisories/GHSA-vcc3-ghjq-m6fr).

The other findings mainly affect build/test tooling. The esbuild advisory is Windows
development-server file reading, not a demonstrated Linux app endpoint leak. Runtime
reachability of every transitive advisory was not exhaustively proven. The current
`docs/dependency-audit.md` is older; this snapshot supersedes its package counts.

Fresh Composer audit: zero advisories, zero abandoned packages. That result is
time-dependent and does not independently audit dependency implementation.

Raw audit JSON stays in `/tmp/motominator-security-{npm,composer}-audit.json`.

## P3 — Before distribution: production security is not yet configured or verified

Clients currently support plain HTTP API URLs for development; passwords, bearer
tokens, invoice uploads and BYOK submissions have no end-to-end transport encryption
there. Release configuration should require HTTPS and fail closed on insecure URLs.
Production cookie attributes, trusted proxies, CORS/stateful origins, debug mode,
security headers, infrastructure credentials, backup/file encryption, provider
retention and native release manifests must be verified against the actual deployment.
No production deployment exists in this repository. These are open release controls,
not claims that a production system is currently misconfigured.

CI has minimum read-only GitHub permissions and checkout credentials disabled.
It runs quality/behavior checks, but contains no dependency-advisory or secret-scanning
gate. Add explicit vulnerability/secret gates when building the requested merge
pipeline. Tool findings need deliberate handling rather than a silent audit bypass.
Actions use moving version tags rather than immutable SHAs; pinning is a supply-chain
hardening recommendation, not evidence of an already compromised action.

## Repository secret checks

A masked pattern scan covered569 current text files selected by Git (tracked plus
untracked/nonignored) and335 distinct historical text blobs reachable through local
Git refs. No private-key block, recognized OpenAI-style key, GitHub token or AWS access
ID candidate was found. No secret values were printed. This is a deliberately limited
pattern scan, not a full gitleaks/entropy/PII scan; those tools were unavailable.
Ignored env/storage/node_modules/generated binaries and unavailable remote history
were excluded. Provider credentials in ignored local configuration were not inspected.

## Root file coverage and cross-checks

Manually reviewed: root/app AGENTS routing guidance; `.context/README.md`,
`.context/04-testing-and-analysis.md`, `.context/07-authentication.md`;
`.github/workflows/ci.yml`, `.codex/config.toml`, root/server ignore rules;
`apps/server/compose.yaml`, `.env.example`, `phpunit.xml`, `public/index.php`,
`bootstrap/app.php`, `routes/console.php`, `config/{cors,session,filesystems,logging,
telescope,queue,sanctum}.php`, `app/Providers/{AppServiceProvider,HorizonServiceProvider,
TelescopeServiceProvider}.php`, `app/Http/Middleware/ProtectSensitiveData.php`,
`app/Logging/{ConfigureLogging,RedactSensitiveData,RedactingPailHandler}.php`,
`apps/web/playwright.config.ts`, `docs/{deployment,dependency-audit}.md`.

Relevant sections reviewed: server `config/{app,auth}.php`, account profile/password
and native-link/approval/consume/exchange operations, admin EditUser/User model,
invoice upload/job, browser auth refresh/logout, mobile auth transport/upload cache,
installed Fortify password route/controller middleware, Laravel QueryException/
Connection, Telescope routes/watchers and Horizon authorization routes, Expo Router
query parsing and decode-uri-component. Complete application ledgers belong to the
three independent scope reports; root reconciliation is not a second complete manual
review of every app file.

Excluded: independent review of third-party internals beyond traced seams, production
hosts/cloud/accounts, router/firewall changes, binary PDF/image contents, real logs/mail/
credential/database payloads, release native apps and live OAuth/provider integrations.

Root reran218 server tests/1190 assertions,74 client tests,5 synthetic auth probes/
17 assertions and both fake-transport race probes. Independently applied the real
log redactor to a fake SQL exception: private bound prose remained in both message
and exception context. Passing existing suites does not contradict the reproduced
missing security contracts. No implementation fixes were made.

## Remediation

The original findings above are retained as audit history. Root applied loopback
bindings to MySQL, Redis, Mailpit and Vite and verified running Docker mappings.
Normal backend HTTP remains reachable on the LAN. Anonymous Telescope and Horizon
now return 403; old diagnostic entries were pruned and workers restarted.

The decoder is now 0.5.0, with a pinned integrity-checked query-string interoperability
bridge. Clean `npm ci`, actual Expo Router regression tests and Android/iOS/web
exports pass. Five tooling advisory roots remain; see `docs/dependency-audit.md`.

Playwright now gracefully terminates its uniquely named loopback-only Docker server.
Container-local SQLite makes local browser fixtures independent of PHP/MySQL tests
and normal app data. Root verified disposal after a browser run. Production controls
and historical ordinary log/backup retention remain outside this remediation.
