# Motominator workspace

Read `.context/README.md` for project intent and current setup. This is an exploratory
motorcycle project with no fixed product yet. Prefer reliable, attributable data; AI
is a secondary capability and must not invent parts compatibility or repair advice.

## App guidance

Before editing an app, read its own guidance:

- `apps/server/AGENTS.md`: Laravel Boost's generated guidelines, app-scoped skills
  and our `.ai/guidelines/architecture.blade.php` project rules.
- `apps/web/AGENTS.md`: browser conventions and official React documentation.
- `apps/mobile/AGENTS.md`: Expo's generated guidance. Load
  `apps/mobile/.agents/skills/expo-overview/SKILL.md` first for Expo work, then the
  applicable official Expo skills in that directory.
- `packages/client/AGENTS.md`: shared client ownership and dependency rules.

Framework documentation and installed versions are the source of truth. Our web
guidance is maintained locally; the Expo skills and Laravel Boost content come from
their framework maintainers. Do not describe third-party React skills as first-party.

## Boundaries and tooling

### Server architecture

Read `apps/server/.ai/guidelines/architecture.blade.php` for domain ownership,
explicit DI, exception contracts and model types. Laravel facades and framework
construction remain supported; application service location belongs to composition
roots and test setup. Models stay in `App\Models`; application code belongs to its
domain. PHP gates are maximum-level Larastan and PHPCS/PHPCBF with PSR-12 and the
focused Slevomat rules. Review boundaries and readable contracts beyond tool output.

### Shared client architecture

Before adding client API operations, domain types, form state, loading/retry logic
or save flows, inspect `packages/client` and its instructions. Extend its public
`@motominator/client` or `@motominator/client/react` exports when behavior is common
to web and mobile. Do not copy shared behavior into an app or import another app's
source. Share concrete common behavior; keep genuinely different interactions local
instead of building speculative generic abstractions.

Apps own presentation, navigation, platform APIs and authenticated HTTP adapters.
The shared package receives its transport through `createClient`; it must not read
cookies, SecureStore, client environment variables or global credentials. Laravel
owns authoritative validation and permissions. Dependency direction is
`apps -> packages/client -> injected transport`; the package never imports an app.

Keep React hooks under the package's `/react` entry; its base entry is React-free.
Consume public package exports rather than relative paths into package internals.
Existing app lint rules guard cross-app/private imports, and package lint guards
platform/UI dependencies and React leaking into the base layer. These checks do not
detect duplicated business logic: review ownership explicitly when changing code.

### Workspace tooling

- Each app owns its implementation and configuration. Clients talk to Laravel over HTTP.
- Root npm workspaces own the JavaScript lockfile. Composer dependencies belong to the server.
- Use Sail for local PHP commands after bootstrap. The root justfile supplies helpers.
- Run relevant tests and checks: `just test` / `just analyse` for PHP;
  `npm test`, `npm run lint`, `npm run typecheck`, `npm run format:check` for clients.
  `just check` and `just test-all` aggregate the app checks. See README for build commands.
- Record setup and architecture decisions in `.context`; keep exploratory ideas separate
  from implementation commitments. Preserve unrelated workspace changes.
- Never put credentials into client environment variables or tracked configuration.

## Codex and MCP

Start Codex from this repository root. The project-scoped `.codex/config.toml`
registers Expo MCP, Laravel Boost and Chrome DevTools MCP; Boost's own `cwd` points
to `apps/server`.
See `docs/agent-tooling.md` for login and verification. Configuration changes require
a new Codex session to load.

For Laravel tasks, prefer Boost's `application-info`, `search-docs`, schema and log tools
when available. For Expo tasks, prefer its official skills and version-matched docs;
use Expo MCP when authenticated. If a tool is unavailable, state the limitation and
use official documentation or local source instead of claiming it was consulted.

Production is envisioned on Hetzner; see `docs/deployment.md`. Framework examples
using Laravel Cloud or EAS Hosting do not change that decision. External publication,
deployment, account linking and feedback submission need a corresponding user request.
