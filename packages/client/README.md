# Shared client

Private npm workspace consumed directly as TypeScript by Vite and Metro. Both
applications depend on `@motominator/client`; nothing is published to npm and no
separate package build or watch process is required.

## Boundaries

- `@motominator/client`: API models, endpoint operations, form metadata and error helpers.
- `@motominator/client/react`: pagination, garage/history operations, form drafts
  and AI settings hooks. These share behavior without rendering app UI. Copilot
  additionally keeps an account-scoped conversation session above the app routes.
- Each app owns its HTTP transport and authentication lifecycle. Browser requests
  use cookies/CSRF; native requests use SecureStore tokens and refresh account state
  on unauthorized responses. The package does not import either application.
- Mantine/Tamagui components, navigation, platform controls and account-specific
  authentication flows remain app-owned. Laravel owns authoritative validation
  and permissions. Money stays a decimal string.

## Usage

```ts
import { createClient } from "@motominator/client";
import { useGarage } from "@motominator/client/react";

// Create once, or memoize when the authenticated transport changes.
const client = createClient(authenticatedRequest);

// Inside a component or hook:
const garage = useGarage(client);
```

Keep the client stable across renders. Paginated loaders must also have stable
identities (`useCallback`); the shared garage hooks handle that internally.
Remount account-owned views when the user ID changes, so drafts and credentials
cannot carry over between accounts. Both apps already do this for AI settings.

Form initial values are read on mount. Key an editor by the record identity when
switching records. Saving preserves drafts on failure; successful operations close
their editor and reload the relevant data. Updating maintenance also refreshes
the motorcycle mileage. A failed read can be retried without repeating the write.
Apps own routes and return destinations. Use `useMotorcycle`, `useMaintenanceRecord`
and `useInvoiceImport` for direct record screens; a list need not be loaded first.
Native retained screens reload on focus using the app's navigation adapter.

## Checks

Root `npm run lint`, `npm run typecheck` and `npm run format:check` include this
package. Existing browser and mobile component suites exercise the shared hooks
through their respective UI and transport adapters. Run `npm test` for both.
`npm run build:web` and `npm run build:mobile` check bundler integration.

Agent ownership rules live in `AGENTS.md`. Oxlint rejects app imports, known
platform/UI libraries and React leaking into the base entry. Consumer lint rules
reject cross-app and private package imports. Import restrictions do not detect
duplicated behavior or arbitrary computed imports; review ownership explicitly.

React is a peer dependency; each app supplies its own supported version. The
browser retains Vite/Vitest React deduplication. Expo uses its automatic monorepo
resolution. Do not add a bundled React dependency or cross-app source aliases.

## Invoice imports

`client.invoices` owns upload/extract/review/confirm operations and contracts.
`useInvoiceList`, `useInvoiceImport`, `useInvoiceUpload` and `useInvoiceReview`
share pagination, record loading, uploads, draft editing, mutation guards and status
polling. Apps prepare multipart files, choose post-save navigation and render their own
review controls; camera, document picking and authenticated download/share stay native.

Both adapters supply `createClient(request, schedule)`, where `schedule(callback,
delay)` returns a cancellation function. Polling uses that adapter rather than
importing DOM or native timers. It retries status reads only; extraction is always
an explicit user action because provider requests may cost money. Failed saves keep
edits; reloading a saved draft explicitly discards them. Server versions reject stale
writes and confirmation is idempotent for a given import. Invoice values remain
nullable, money remains decimal text, and review warnings never fill in missing facts.

## Account settings

`client.account` owns profile/password updates, verification resend, two-factor
operations, device reads/revocation and provider linking contracts. `AccountUser`
is the authenticated account response; `AccountDevice` identifies the current
native token without exposing token values. `useAccountSettings` shares mutation
guards, feedback, account refresh, recovery display and device refresh behavior.
It preserves failed form data, ignores late results after unmount and supplies
success results so apps can clear plaintext password inputs. Key account-owned
editors by user identity. Apps own credentials, forms and navigation.

Browser provider linking keeps its password-confirmed cookie/OAuth redirect.
Native linking prepares a proof verifier, opens the provider in the platform browser
and validates/consumes the returned intent. These platform protocols stay in their
respective adapters; common HTTP operations remain here.

## Text copilot

Supply the optional third `createClient(request, schedule, stream)` argument for
chat. The app's stream adapter owns authenticated fetch, cancellation and its UTF-8
decoder; the package parses Laravel's small SSE contract. It never receives a
provider key. Mount `CopilotProvider` above authenticated routes and key it by user
ID. `useCopilot` owns history pagination, drafts, streamed replies and explicit
retries. Navigation within that account retains the selected chat; switching chats
or signing out cancels the active connection. Apps own screens and deletion prompts.

Failed or stopped replies retain partial text and restore the submitted draft.
Retries can incur another provider charge and are never automatic. Stopping closes
the client connection; server interruption is detected between SDK stream events.
