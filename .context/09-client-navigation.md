# Client navigation and focused screens

## Implemented pattern

Each screen has one purpose: overview, list, detail, form or document review.
Overviews contain short summaries and clear links. Forms and long lists have their
own routes. Wider screens arrange summary cards in columns; they keep the same
screen hierarchy as phones.

Home is currently a launchpad for Garage, Copilot and Account. Its eventual product content
is undecided. Add Social, Workshops or Catalogues destinations when those domains
have real screens; do not create empty navigation entries in anticipation.

```
Home
├── Garage overview
│   └── Motorcycles
│       ├── Add motorcycle
│       └── Motorcycle overview
│           ├── Edit motorcycle
│           ├── Maintenance history → Record details → Edit record
│           │                       └── Record maintenance
│           └── Invoice library → Upload invoice → Review invoice
├── Copilot conversations → Conversation
└── Account overview → Dedicated settings screens
```

## Browser reference

React Router owns URLs and browser history. `src/ui/AppFrame.tsx` owns the global
Home/Garage/Copilot/Account navigation: sidebar on larger screens, a menu on small screens.
`src/ui/Page.tsx` supplies the heading, focus on route changes, parent link and
Garage home shortcut. Parent links have fixed destinations and work after a direct
link or refresh; the browser Back button remains history-based.

`src/garage/Garage.tsx` is the route map. `GaragePages.tsx`, `MotorcyclePages.tsx`
and `MaintenancePages.tsx` are the reference overview/list/detail/form screens.
`InvoicePages.tsx` demonstrates upload and record review. `AccountPages.tsx`
demonstrates a second domain using the same layout. Use router links for navigation
and buttons for actions. `ActionLink` renders a real disabled button when a write
is unavailable; direct form routes also enforce the verified-account UI gate.

Production web hosting must serve the browser app's `index.html` for its client
routes. API and server administration routes belong to Laravel's host.

## Native reference

Expo Router files under `src/app/` are thin route adapters. The root layout owns
authentication. `(tabs)` contains Home, Garage, Copilot and Account; each domain has its own
Stack and an `index` anchor for deep links. `src/navigation/domain-stack.tsx` owns
the native Back header and a domain-root shortcut on descendant screens. The Home
tab is the global return destination. Reselecting a native domain tab returns to
its stack root using Expo's default behavior.

`src/garage/garage-screen.tsx` and `motorcycle-screens.tsx` show the hierarchy;
`src/invoices/invoice-screens.tsx` shows document routes. `Screen` owns scrolling,
keyboard behavior and width constraints; `RecordList` uses FlatList directly for
lists. Avoid nesting a virtualized list inside Screen's ScrollView. Native stacks
supply the top safe area; the standalone Home screen supplies its own.

## Shared data and save behavior

Shared record loaders (`useMotorcycle`, `useMaintenanceRecord`, `useInvoiceImport`)
load by route identity, so a detail does not require visiting its list first.
Shared pagination, forms, upload, review and polling remain in `packages/client`.
Routes and platform navigation stay in the apps. Keep clients/load callbacks stable,
key editors by record identity, and remount account-owned trees when the user changes.
Late responses from a previous identity or an unmounted screen cannot replace a
new record. Native retained screens reload when focused again.

Failed saves preserve the current form. Successful saves return to the relevant
detail or list and refresh affected reads, including motorcycle mileage. Upload
opens the uploaded invoice's review screen; extraction remains an explicit action.
Drafts are currently screen-local: leaving an editor discards unsaved changes.
Persistent drafts or navigation confirmation are a separate UX decision.

## Adding a feature

1. Choose its domain and overview/list/detail/form routes before building screens.
2. Extend shared contracts/hooks for common behavior; keep presentation local.
3. Use Page/AppFrame on web and the domain Stack with Screen/RecordList on native.
4. Provide loading, empty, retry, unauthorized and invalid-address states. Test direct
   record links, save failure/retry and return navigation rather than pixel colors.
5. Run both client suites, lint, typecheck, format and relevant builds. Shared API
   changes also require server tests, maximum Larastan and PHPCS.

Browser E2E uses the isolated `testing` database. Do not run it concurrently with
PHP database tests. Its default ports are 8001/5179; `E2E_API_PORT` and `E2E_WEB_PORT`
can select free ports without changing development servers.

Account settings now use the same shared operation hook in both apps. Mobile's
`src/account/` contains dedicated form screens with thin `/account/*` route files.
Password changes and revoking the current device refresh authentication and return
to sign-in. Social linking's browser protocol is platform-specific; provider lists,
unlinking and account contracts are shared. See `07-authentication.md` for permissions
and external callback verification still needed.
