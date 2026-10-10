# Agent guidance and MCP

## Framework guidance

Root `AGENTS.md` routes work to each app's instructions. Laravel Boost owns the
server's generated guidance and skills. Expo's generated mobile `AGENTS.md` has
an app-specific Tamagui section. `apps/web/AGENTS.md` is our local guide to official
React documentation.

Server project rules live in `apps/server/.ai/guidelines/architecture.blade.php`,
which Boost includes when generating `apps/server/AGENTS.md`. This is our guidance,
not upstream Laravel policy. It requires explicit DI, domain namespaces with global
Eloquent models, readable contracts and maximum-level Larastan. Framework conventions
remain the starting point; speculative layers and interfaces are discouraged.

The installed Laravel best-practices skill already recommends explicit injection.
Project guidance strengthens that recommendation into a service-locator prohibition
outside composition roots and test setup. Read the skill for PHP architecture work.

To refresh generated server guidance without replacing installed skills:

```sh
just artisan boost:update --no-discover --ignore-skills --no-interaction
```

Preserve the custom guideline sources when updating Boost. Do not patch generated
upstream sections as the sole durable record of project decisions. Older code that
violates these rules remains migration work; guidance changes do not move namespaces
or refactor existing operations automatically.

PHP formatting uses PHPCS 4.0.4 / PHPCBF with PSR-12 and Slevomat 8.31.1:
`Files.LineLength` (120 columns, excluding comments/imports) and
`Functions.RequireMultiLineCall`. Pint cannot enforce a general line width, so it
has been removed. `phpcs.xml` explicitly references the Slevomat sniffs; the optional
Composer installer plugin is disabled.

`just format` fixes and `just format-check` / `just style-check` check the standard;
CI uses the same Composer check. The small `scripts/format.php` launcher normalizes
PHPCBF's successful-fixes exit status; unresolved findings still fail. `just analyse`
runs maximum-level Larastan. Review architecture and contract quality separately.

### PhpStorm and Eloquent types

Laravel IDE Helper 3.7.0 is a server development dependency. Model field and relation
PHPDoc is generated into the model files and tracked. Facade metadata (`_ide_helper.php`)
and PhpStorm container metadata (`.phpstorm.meta.php`) are ignored local artifacts.
The configuration avoids redundant query-method annotations, per-field magic `where` methods, relation count properties,
and modifying Eloquent vendor files. Decimal casts use `numeric-string`; schema
nullability and enum/date casts must remain accurate.

After migrations, casts or relationship changes, run:

```sh
just ide-helpers
```

This refreshes local helpers, appends model PHPDoc and formats the models with PHPCBF.
Append mode preserves custom annotations; review changed fields for stale existing
PHPDoc and correct those explicitly. Do not use normal public typed properties for
Eloquent attributes. On a fresh checkout, run migrations before generating model docs.

PhpStorm must index the server project, Composer vendor code and the ignored helpers.
Let indexing finish after generation. Laravel already annotates `DB::transaction()`;
if it remains highlighted, inspect the diagnostic text and import before assuming a
missing method. Actual editor diagnostics are not verified by successful generation.

Twelve official Expo framework skills are installed under `apps/mobile/.agents/skills`.
Their original content and references are preserved with the MIT license and source
commit in `expo-source.json`. They cover overview, project structure, routing, native
UI, Expo UI, networking, animation, design systems, DOM components, examples,
development clients and SDK upgrades. Load the overview first. This is a pinned
snapshot; refreshing upstream is an explicit maintenance action.

## Start Codex

Start Codex from the Motominator repository root:

```sh
just up
codex
```

The project-scoped `.codex/config.toml` registers the MCP servers. Expo uses the
remote `https://mcp.expo.dev/mcp` endpoint. Laravel Boost starts via Sail with its
working directory set to `apps/server`, so Codex can keep the whole monorepo as its
workspace while Boost runs in the Laravel application. The configured Sail container
must be running.

Boost's `cwd` is an absolute path to this checkout. If you clone the repository
elsewhere, update that value in the root `.codex/config.toml` to your `apps/server`
directory before starting Codex.

Codex must trust the project before it loads project-scoped configuration. A config
change takes effect in a new Codex session. Use `codex mcp list --json` from the repo
root to inspect the registrations. Server-only sessions can continue using
`apps/server/.codex/config.toml`.

## Expo authentication and local tools

Authenticate the remote Expo connection from the repo root when account-backed tools
require it:

```sh
codex mcp login expo
```

For local DevTools and device capabilities, sign in to Expo CLI:

```sh
cd apps/mobile
npx expo login
```

Then return to the repository root and run:

```sh
just mobile-mcp
```

This starts Expo with `EXPO_UNSTABLE_MCP_SERVER=1`. The local MCP server is opt-in;
ordinary `just mobile` keeps its usual behavior. Account-backed remote capabilities
may require Expo authentication, a linked EAS project or a paid EAS plan. Device and
simulator capabilities also depend on local tooling.

## React browser helpers

React provides official docs, a machine-readable index and the Rules of React; there
is no general first-party React MCP in this setup. The Chrome team's official Chrome
DevTools MCP is registered in the root `.codex/config.toml` for browser automation,
screenshots, console/network inspection and performance analysis.

The registration pins `chrome-devtools-mcp` to 1.10.1 and uses `npx` on the host.
Node and Google Chrome must be installed. npm caches the tooling package; it is not
an application dependency. Chrome starts on the first browser tool call, in headless
mode with an isolated temporary profile. Usage statistics and the performance tools'
CrUX integration are disabled. Start a new Codex session from the repo root to load
the tools; no separate Chrome debugging port or launcher script is needed.

## Mantine browser helpers

Mantine's official documentation MCP is registered as `mantine`, using
`npx --yes @mantine/mcp-server@9.7.1`. It searches documentation and retrieves
component props and examples; it is not a browser automation tool. It does not
require account linking. Start a new Codex session to load the registration.

The browser app's `AGENTS.md` links Mantine's machine-readable docs, UI examples and
official skills repository. Skills are linked, not separately installed. Use
version-matched documentation when changing the library version. The tooling
package is fetched through npm and is not an application dependency.

Sources: https://mantine.dev/guides/llms/ and https://github.com/mantinedev/skills

## Tamagui mobile helpers

Official Tamagui agent guidance and its component, configuration and animation
references are installed under `apps/mobile/.agents/skills/tamagui`. `source.json`
records the pinned upstream commit; the upstream MIT license is included.
This is a repository snapshot, not an independently installed MCP server.

From `apps/mobile`, run `npm run ui:context` and read `tamagui-prompt.md` before
changing tokens or components. The CLI generates it from the actual app config;
generated files are ignored. `tamagui.config.ts` owns themes, tokens and fonts.
The CLI is a development dependency, with its version aligned to the UI packages.
Expo's official skills continue to cover navigation, networking and device APIs.

Sources: https://tamagui.dev/docs/intro/installation,
https://tamagui.dev/docs/guides/expo and
https://github.com/tamagui/tamagui/tree/5918dae34703cac917078c9e0d73c58782ec9ab8/plans/tamagui-skill/skills/tamagui

## Verification and limits

- `codex mcp list --json` recognizes the project-scoped MCP entries, including Mantine.
- Mantine MCP 9.7.1 initialized over stdio, exposed five tools and successfully
  searched for AppShell documentation. The current Codex session still needs a
  restart to load the new registration into its tool catalog.
- Expo documentation search, page retrieval and library lookup calls succeeded.
- Chrome DevTools MCP 1.10.1 initialized over stdio and exposed 30 tools. A real
  isolated headless Chrome opened Horizon and Telescope, inspected their rendered
  content and read their browser consoles. Horizon showed an active Redis worker;
  Telescope showed recorded requests. Neither page reported console errors.
- Boost's live `application-info` call succeeds against the running project Sail
  container and reports PHP 8.5, Laravel 13.35.0 and the installed auth packages.
  This verifies that the project registration connects; it does not verify every tool.
- Expo account-backed operations, local DevTools and device operations are not
  validated by documentation lookups.

## Official sources

- Laravel Boost: https://laravel.com/docs/13.x/boost
- Expo agents: https://docs.expo.dev/agents/
- Expo skills: https://docs.expo.dev/skills/ and https://github.com/expo/skills
- Expo MCP: https://docs.expo.dev/mcp/
- Codex MCP: https://developers.openai.com/codex/mcp/
- React: https://react.dev/llms.txt and https://react.dev/reference/rules
- Chrome DevTools MCP: https://github.com/ChromeDevTools/chrome-devtools-mcp
