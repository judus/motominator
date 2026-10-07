# Developer note: begin implementation here

## What this skeleton provides

- Laravel API with Sail, MySQL, Redis queues, Horizon, scheduler and Mailpit.
- Separate React/TypeScript/Vite browser and Expo mobile applications.
- Fortify account endpoints, Sanctum session/token support and Socialite installed.
  Authentication screens, social callbacks and mobile token issuance are unfinished.
- Telescope and Debugbar for local development.
- Tests, static analysis, linting, formatting, build commands and GitHub Actions CI.
- Project-scoped Laravel Boost, Expo and Chrome DevTools MCP configuration.

Clients communicate with Laravel over HTTP. PHP dependencies belong to the server;
JavaScript dependencies use root npm workspaces and one lockfile.

## Start a project

1. Set project names and identifiers: npm workspaces, Composer metadata, app names,
   mobile identifiers, Compose project name and database name. Update project-specific
   context and documentation for the new application.
2. Configure local environments from `.env.example` files and update Boost's absolute
   server path in `.codex/config.toml`. Keep secrets server-side and out of Git.
3. Run `just setup`; use `just up` on subsequent visits. Run `just web` and
   `just mobile` in separate terminals. Start Codex from the repository root.
4. Confirm `just check`, `just test-all`, `npm run build:web`,
   `npm run build:server` and `npm run build:mobile` pass in the new checkout.

Read root `AGENTS.md` and the relevant app's guidance before implementation.
The root README contains command details; `docs/agent-tooling.md` covers MCP setup.

## Next tasks

- [ ] **Browser authentication:** registration/login/logout screens, current-user
  endpoint, protected navigation, session cookies, CSRF and credentialed CORS.
- [ ] **Recovery and verification:** password reset and email verification screens,
  resend/link handling and transactional email configuration.
- [ ] **Social sign-in:** configure chosen built-in Socialite providers, callbacks
  and explicit account linking/unlinking. Handle missing/unverified provider email
  without silently merging accounts. Google and GitHub settings are scaffolded.
- [ ] **Mobile authentication:** device token issuance, secure native storage,
  expiry/revocation, two-factor challenges and social-login return links.
- [ ] **Account settings:** profile/password updates, password confirmation,
  optional two-factor enrollment and recovery codes; decide session/device revocation
  and account deletion behavior.
- [ ] **Authentication verification:** add backend/client tests and end-to-end flows;
  check rate limits, access rules, production cookies/CORS and dashboard permissions.
- [ ] **Dependency review:** refresh npm/Composer audits, apply compatible fixes and
  record unresolved exposure. Known npm findings remain in this skeleton; passing
  tests do not resolve them. Preserve Expo SDK compatibility when updating.
- [ ] **Deployment design:** choose hosting/runtime, environment boundaries, secrets,
  release artifacts, migrations, health checks, promotion and rollback strategy.
- [ ] **Remote staging:** provision isolated resources, access, DNS/TLS, services,
  secrets and monitoring; verify a baseline release, queues and scheduler.
- [ ] **Remote production:** provision production resources, restricted access,
  backups with tested restore, monitoring/alerts and recovery procedures.
- [ ] **Deployment automation:** configure GitHub environments and deployment
  identities; gate releases on CI, prevent overlapping deployments, run migrations
  and worker restarts, verify health and exercise rollback on staging first.

Begin feature work with browser authentication, then recovery and social sign-in.
Coordinate mobile and settings around the same identity and two-factor behavior.
Add tests alongside each flow. Review dependencies before public exposure.

For deployment, choose the architecture first, establish staging and validate the
workflow there before enabling production releases. Hosting provider, topology,
packaging, deployment transport and branch strategy remain open. Signed native
mobile builds and distribution require their own release setup; Expo exports alone
do not produce installable apps.
