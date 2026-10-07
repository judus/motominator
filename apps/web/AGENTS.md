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
