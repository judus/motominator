# Developer handoff: continuing from the setup baseline

Updated: 2026-10-08.

## Intent

The current workspace is a useful, clean starting point containing the developer's
usual tools. Preserve that baseline before starting product implementation. A future
task is to extract a reusable template/skeleton, potentially in a separate GitHub
template repository. No template copy or separate repository has been created yet.

Motominator itself remains exploratory. Authentication implementation and remote
deployment are pending; installing their packages did not complete those features.

## Where we left off

- Repository: [judus/motominator](https://github.com/judus/motominator), private.
- Setup baseline commit: `b5067ba` on `main`.
- Laravel 13 / PHP 8.5 through Sail; MySQL, Redis, Mailpit, Horizon and scheduler.
- Separate React/TypeScript/Vite browser app and Expo SDK 57 mobile app.
- Fortify, Sanctum and Socialite installed with headless backend scaffolding.
  Google/GitHub credential placeholders exist; only built-in Socialite providers
  are in scope. Client authentication, social callbacks/linking and mobile token
  issuance remain to be implemented. Passkeys are disabled.
- PHPUnit, Larastan, Pint, Vitest/Testing Library, Jest/Expo, client lint/type/format
  checks and GitHub Actions CI are configured.
- Project-scoped Laravel Boost, Expo and Chrome DevTools MCP registrations exist.
- Setup baseline checks passed locally and in
  [GitHub Actions](https://github.com/judus/motominator/actions/runs/37694477837):
  9 server tests, 4 browser tests, 4 mobile tests, analysis/formatting and all builds.
  Expo export produces bundles, not signed installable native applications.
- npm audit reported 65 affected packages: 49 high, 16 moderate, 0 critical.
  Issue #7 owns investigation and compatible remediation.

These are dated baseline results, not a guarantee about later changes. Existing tests
cover scaffold/status and development tooling behavior, not complete authentication.

## Resume locally

Read root `AGENTS.md`, this context index and the relevant app's guidance first.
Start Codex from the repository root. Full command details are in the root README;
MCP setup is in `docs/agent-tooling.md`.

For an existing initialized checkout:

```sh
git status --short --branch
git pull --ff-only
just up
```

Preserve or resolve local work before pulling if the checkout is dirty. On a fresh
checkout use `just setup` to install dependencies, start Sail, generate the server key
and apply migrations. Configure client `.env` files from their examples as needed;
a physical phone requires a reachable API address rather than localhost.

Run `just web` and `just mobile` in separate terminals as needed. Start `codex` from
the repository root after Sail is running. Boost's root MCP `cwd` currently contains
this checkout's absolute server path; adjust it when cloning elsewhere. Expo account
login and native device tooling are separate prerequisites for those capabilities.

Baseline commands:

```sh
just check
just test-all
npm run build:web
npm run build:server
npm run build:mobile
```

Use Sail for PHP/Artisan/Composer and root npm workspaces for JavaScript. Never copy
credentials into clients or commit populated environment files.

## Next steps: reusable template

Before product-specific implementation, extract the setup as a separate task:

1. Choose whether to preserve a local skeleton copy, create a separate private
   repository, or maintain a GitHub template repository. Choose its name/visibility
   before creating or publishing it.
2. Start from the committed setup baseline. Keep Motominator's working repository
   intact and give the template its own Git identity/history and remote.
3. Carry over the app boundaries, dependency lockfiles, Sail/services, quality gates,
   CI, agent guidance and project-scoped MCP setup. Retain upstream license notices.
4. Inventory and replace project-specific names: npm workspaces, Composer metadata,
   app display names/identifiers, Compose project and database names, repository/issue
   links, context history and absolute paths. Make machine-specific setup explicit.
5. Include only example configuration. Exclude populated `.env` files, credentials,
   installed dependencies, database contents, logs, caches and generated build output.
6. Document which authentication features are scaffolded versus functional, and the
   outstanding advisory findings. Provider credentials and deployments remain unset.
7. Prove a fresh checkout can bootstrap, run migrations/checks/tests and build/export
   all apps. Verify services, queue processing and MCP registrations at a new path;
   avoid sharing Compose resources with Motominator during this check.
8. If a template repository is chosen, publish it after review, enable its template
   setting and test generating a new project from it. Decide how future shared setup
   improvements will be carried between the template and Motominator.

The current user request records these steps; it does not create or publish a template.

## Next steps: Motominator implementation tickets

This is a local snapshot of the open GitHub backlog. GitHub remains the source for
ticket status and detailed acceptance criteria; refresh this list when work resumes.

| Ticket | Work to continue |
| --- | --- |
| [#1](https://github.com/judus/motominator/issues/1) Browser authentication | Registration/login/logout screens, current-user endpoint, protected navigation, session cookies, CSRF, credentialed CORS and tests. |
| [#2](https://github.com/judus/motominator/issues/2) Recovery and verification | Password recovery/reset, email verification/resend, link handling, mail configuration and tests. |
| [#3](https://github.com/judus/motominator/issues/3) Socialite sign-in | Google/GitHub registrations, callbacks, explicit identity linking/unlinking, error handling and tests. |
| [#4](https://github.com/judus/motominator/issues/4) Expo authentication | Device token issuance, expiry/revocation, secure storage, two-factor handling, social return links and native verification. |
| [#5](https://github.com/judus/motominator/issues/5) Account settings | Profile/password changes, confirmation, optional two-factor enrollment/challenges/recovery and tests. |
| [#6](https://github.com/judus/motominator/issues/6) Authentication readiness | End-to-end verification, production origins/cookies/CORS, rate limits, operational dashboard access and production mail/secrets. |
| [#7](https://github.com/judus/motominator/issues/7) Dependency advisories | Review npm findings, apply compatible fixes, record remaining exposure and rerun clean installation/checks/builds. |
| [#8](https://github.com/judus/motominator/issues/8) Deployment architecture | Choose runtime/hosting, environment boundaries, release artifacts, migrations, health checks, promotion and rollback. |
| [#9](https://github.com/judus/motominator/issues/9) Remote staging | Provision isolated staging resources, access, DNS/TLS, services, secrets, monitoring and a verified baseline release. |
| [#10](https://github.com/judus/motominator/issues/10) Remote production | Provision production resources, access/secrets, backups and restore, monitoring, recovery and release readiness. |
| [#11](https://github.com/judus/motominator/issues/11) Deployment workflows | GitHub environments/identities, CI gates, reproducible releases, staging/production rollout, concurrency and rollback. |

For feature implementation, begin with #1. Follow with #2 and #3; coordinate #4 and
#5 around shared identity/two-factor behavior. #6 validates the completed flows.
Review #7 before reusing or exposing the baseline.

Deployment decisions begin with #8. Establish staging (#9), validate automation
(#11) there, then prepare production (#10) and enable its approved release flow.
Hosting provider, topology, packaging, deployment transport and branch strategy are
open decisions. These current tickets supersede older deployment assumptions in
the setup history. Native mobile distribution is a separate release concern.
