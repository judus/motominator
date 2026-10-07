# Agent guidance and MCP — 2026-10-07

The user now explicitly authorizes root AGENTS.md. The earlier restriction was
about avoiding premature guidance before official framework instructions existed.

- Added root instructions routing to app guidance and documenting monorepo boundaries.
- Added locally maintained browser guidance linked to official React docs.
- Preserved Laravel Boost and Expo generated instructions.
- Installed 12 official Expo skills, preserving reference files and MIT license, at
  `apps/mobile/.agents/skills`, pinned to expo/skills commit
  `d4f484024fec15196bfd3c272e953e3f983972cf`; source manifest records the selection.
- The official Expo Codex plugin is available but not installed/enabled. Installation
  was attempted and failed with a read-only user Codex cache error. The skill installer
  helper's Git fetch also failed DNS; copied the selected skills from a separately
  cloned official repository after verifying its source commit.
- Root .codex and .agents directories are protected read-only in this session. Added
  `scripts/codex.mjs`, `scripts/boost-mcp.sh` and `just agent` to configure both MCP
  servers per invocation. No permission changes or user-level configuration writes.
- Codex MCP list verifies Boost stdio and Expo remote URL registrations from that launcher.
- Boost MCP protocol initialization, tools/list and application-info succeeded in a
  disposable cached PHP 8.4 Sail container, returning ten tools. The actual project
  launcher still needs the configured PHP 8.5 Sail application container to be started.
- Added Expo-compatible expo-mcp 0.2.4 and opt-in mobile MCP launch script/just helper.
- Expo CLI reports Not logged in. Expo OAuth and device/DevTools operations remain
  pending user login. No EAS project linking or cloud builds/deployments performed.
- No general first-party React MCP was found. Official Chrome DevTools MCP is documented
  as a separate browser helper option, not installed.

See docs/agent-tooling.md for commands, official sources and verification limits.

Verification after installation: client lint, TypeScript, all 8 client behavior tests,
Prettier and Expo Android/iOS/web exports pass. Node and shell launcher syntax checks
and Just recipe parsing pass. Root-launch MCP registration is confirmed by Codex CLI.
The Expo dependency audit still reports 65 affected packages (49 high, none critical).

## 2026-10-07 follow-up: project-scoped MCP configuration

The Codex launcher scripts were removed. Root `.codex/config.toml` now registers
Laravel Boost and Expo MCP for Motominator. Boost's `cwd` is `apps/server`, while
Codex starts at the monorepo root. The root Just recipes for launching Codex and Expo
OAuth login were removed; use `codex` and `codex mcp login expo` from the repo root.
`just mobile-mcp` remains for Expo's opt-in local DevTools MCP. Codex CLI recognizes
both project registrations. The user's global Codex config was not changed.

## 2026-10-07 follow-up: Chrome DevTools MCP

Added Chrome's official `chrome-devtools-mcp` server to the root project-scoped
Codex configuration, pinned to 1.10.1 and launched through host `npx`. It uses
headless Chrome with an isolated temporary profile, disables usage statistics and
CrUX integration, and allows 60 seconds for startup. Host Node 24.14.0 and Google
Chrome 155.0.8059.39 are installed. No app dependency or global Codex registration
was added.

Codex CLI recognizes the registration. A disposable stdio MCP client initialized
the actual server, listed 30 tools and used browser tools to open and inspect both
Horizon and Telescope. Horizon rendered an active Redis worker; Telescope rendered
recorded requests. Neither page reported console errors. The temporary browser was
closed after verification. A new Codex session at the repo root loads the new tools.
