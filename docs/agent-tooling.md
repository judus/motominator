# Agent guidance and MCP

## Framework guidance

Root `AGENTS.md` routes work to each app's instructions. Laravel Boost owns the
server's generated guidance and skills. Expo's generated mobile `AGENTS.md` is
preserved. `apps/web/AGENTS.md` is our local guide to official React documentation.

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

## Verification and limits

- `codex mcp list --json` recognizes the project-scoped MCP entries.
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
