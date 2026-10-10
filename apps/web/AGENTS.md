# Browser application

This is React 19 with TypeScript and Vite. These project instructions are locally
maintained, not a React-generated or React-team-authored skill bundle.

## Official sources

- Documentation index for agents: https://react.dev/llms.txt
- Rules of React: https://react.dev/reference/rules
- Thinking in React: https://react.dev/learn/thinking-in-react
- Effects: https://react.dev/learn/you-might-not-need-an-effect
- Vite documentation: https://vite.dev/guide/

Confirm installed versions in package.json before using version-sensitive APIs.
Keep components and Hooks pure, obey Hook rules, and use Effects for synchronization
with external systems rather than deriving state that can be calculated during render.
Use semantic HTML and accessible controls. Follow existing app conventions before
adding abstractions, state-management packages or routing libraries.

## Workspace commands

Run npm commands from the repository root. Use `npm run test:web` for Vitest and
React Testing Library, `npm run lint`, `npm run typecheck`, `npm run format:check`,
and `npm run build:web`. Keep tests focused on observable behavior. Component tests
in jsdom do not establish real-browser compatibility.

The API base URL comes from VITE_API_BASE_URL. All VITE_ variables are public;
keep credentials in Laravel. Preserve the HTTP boundary with the server app.

## Shared client behavior

Before adding API operations or stateful behavior, read `packages/client/AGENTS.md`
from the repository root and inspect its existing exports. Common garage, AI,
pagination and form behavior belongs in `@motominator/client` and its `/react`
entry. Extend those APIs instead of duplicating logic in browser components.
Import public package exports; never another app's source or package internals.

This app owns Mantine presentation, browser navigation and cookie/CSRF transport.
Compose the shared client in `src/client.ts`; keep browser-only account flows local.
Do not force genuinely different platform interactions into shared abstractions.
Its `.oxlintrc.json` enforces import boundaries; preserve those rules. Shared contract
changes require both client suites and relevant builds, per the package instructions.

## UI foundation

Use Mantine for browser components and layout. Start with its styled defaults and
layout components (AppShell, Container, Stack, Group, SimpleGrid), and keep theme
changes centralized in src/ui/AppProvider.tsx. Avoid global element selectors,
custom control CSS, Tailwind, or a second component library without a concrete need.
Import package styles once in AppProvider. Keep money values as decimal strings;
use native input/FormData semantics rather than converting costs through floats.

Keep button roles consistent with mobile: filled blue (default) for save/create/
confirm, `variant="outline"` for browsing, back/cancel, retries and supporting
actions, and red outline for explicit removal, unlinking, revocation, security
disable or discarding changes. Opening a creation form is primary; opening an
existing record or settings section is secondary. Reserve subtle buttons for
navigation chrome; active navigation uses light blue. Theme defaults belong in
`ui/AppProvider.tsx`; do not choose colors independently per feature.

Official Mantine sources (match the installed major version):

- Agent documentation: https://mantine.dev/llms.txt
- Vite setup: https://mantine.dev/guides/vite/
- UI examples: https://ui.mantine.dev/
- AI tooling and official skills: https://mantine.dev/guides/llms/
- Official skills repository: https://github.com/mantinedev/skills

The project registers Mantine's official documentation MCP. Prefer it when available;
otherwise use these official docs or installed types. New MCP registrations load in
a new Codex session. Browser tests use src/test/render.tsx for MantineProvider.
Vite's React deduplication is shared with Vitest because Expo and the browser app
own different React versions. Tests cover behavior; no pixel snapshots are required.

## Focused screens and routing

Use React Router URLs for overview, list, detail and form screens. Overviews contain
summaries and links; forms, document review and long lists have dedicated routes.
Compose `ui/Page.tsx` inside `ui/AppFrame.tsx` for headings, parent navigation and
responsive global navigation. Keep route maps separate from domain screen code;
`garage/Garage.tsx` and `garage/MotorcyclePages.tsx` are reference patterns.
Use shared loaders by route ID, key editors by record identity, and refresh affected
reads after a successful save. Browser history must work alongside explicit parent
links. See `.context/09-client-navigation.md` at the workspace root for the hierarchy,
deep-link behavior, draft limitations and the next-feature checklist.

Account settings use shared `client.account` operations and `useAccountSettings`;
keep forms and cookie-based provider redirects local. Clear password inputs after
successful security operations and preserve failed drafts. Account API writes still
require cookie CSRF protection. Use the browser E2E regression for transport changes.
