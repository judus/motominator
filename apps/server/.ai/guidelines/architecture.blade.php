# Motominator server conventions

Use Laravel features and conventions first. Read the installed
`laravel-best-practices` skill for PHP changes. These project choices select among
Laravel-supported approaches; avoid speculative layers, repositories and interfaces.
Eloquent remains the persistence layer. Keep operations cohesive and dependencies clear.

## Ownership

- Keep models in `App\Models`; migrations, factories and seeders in `database`.
- Put domain code in `App\<Domain>/{Actions,Http,Jobs,Policies,Providers,Enums,Exceptions,Filament}`,
  creating folders only when needed. Garage owns motorcycles, maintenance and invoice
  imports; Accounts owns authentication/users; Ai owns integrations/settings; Activity
  owns activity recording. Do not create modules for hypothetical future capabilities.
- Keep shared bootstrap, middleware, logging and composition in conventional locations.
- Controllers, Filament pages, commands and jobs adapt input and call Actions. Actions
  enforce use-case validation, permissions and transactions for every caller. Jobs
  manage queue payloads, attempts and failure behavior. Cross-domain writes use explicit
  operations; Actions must not depend on HTTP or Filament adapters.
- Register domain providers and explicitly map policies for global models. Namespace
  moves must update routes, discovery, queue compatibility and tests together.

## Dependencies and contracts

- Inject application collaborators using constructor promotion or supported method
  injection. Jobs inject services into `handle()`; Livewire pages inject through `boot()`.
- Service location (`app()`, `resolve()`, container `make()`/`get()`, static container
  access) is restricted to provider/bootstrap composition and test setup. Inject the
  actual collaborator. Laravel facades for framework facilities (DB, Gate, Storage,
  Config) and framework-managed factories remain supported.
- Pass actor and operation input explicitly to Actions. Shared activity recording may
  receive injected request/runtime context; callers do not supply global auth state.
- Review crowded signatures before merely wrapping them. Use meaningful operations,
  named scalar arguments and typed contracts where useful. Activity owns snapshot
  selection and serialization; callers express changes. Preserve transaction atomicity.
- Validate untrusted values at boundaries; use precise types, array shapes and relation
  generics. Do not invent PHPDoc or casts merely to satisfy analysis.

## Exceptions

- Domain failures live in `App\<Domain>\Exceptions`, normally named
  `<Domain><Scope>Exception::<failureFactory>()`. Evaluate awkward domain prefixes
  case by case. Prefer cohesive failure scopes over universal catch-all exceptions.
- Named factories centralize safe messages. Use distinct types for distinct handling,
  or a stable explicit reason when factories share a class; never parse messages.
- Actions express failures independently of HTTP. Adapters/rendering map responses;
  CLI and jobs handle their context. Laravel validation, authorization and missing-model
  exceptions remain appropriate and need no wrapper just to add a domain prefix.
- Translate integration failures only when a useful application contract exists; chain
  the original cause. Catch for recovery, translation or cleanup. Keep unexpected
  failures diagnosable and preserve queue/rollback semantics. Never catch `Throwable`
  solely to silence an IDE inspection. Document meaningful propagated exceptions;
  avoid blanket `@throws Throwable`.
- Keep credentials, private prose and provider payloads out of public errors and logs.

## Reference patterns

- Start from `Garage/Actions/SaveMotorcycle` for an authorized write with validation,
  locking and atomic activity recording; its HTTP and Filament callers share the Action.
- Use `Accounts/Actions/AuthenticateDevice` and `Accounts/Http/Controllers/DeviceTokenController`
  for authentication: the Action validates input and returns application data; the
  controller maps the documented result to HTTP. Keep sessions and redirects in adapters.
- Use `Accounts/Actions/ExchangeNativeAuth` for a single-use cache operation. Inject
  collaborators, retain the lock and expiry checks, and return a precise result shape.
- Follow domain exception factories and `bootstrap/app.php` for expected failures.
  Keep database-conflict translation in the operation that owns the transaction.
- Cover reusable operations directly as well as through their adapters; see
  `tests/Feature/AccountsActionsTest.php` for actor isolation and rollback checks.
  Add layers only when the operation needs them; these examples are not scaffolding mandates.

## Quality gates and editor support

- PHPCS/PHPCBF is the PHP style tool: PSR-12 plus Slevomat line width (120 columns,
  excluding comments/imports) and multiline calls above that width. Pint is removed.
  `composer format` fixes; `composer format:check` checks. Remaining width findings
  require manual wrapping/simplification; suppressions are not a formatting solution.
- Larastan/PHPStan runs at `level: max` over application, bootstrap, database, routes
  and tests. Do not lower it, add baselines/exclusions or suppress existing findings.
- `stubs/FrameworkCallbacks.stub` supplies source-verified framework callback types.
  Review it on framework upgrades. Fix missing library annotations at their source
  boundary instead of adding unreachable guards, casts or invented application types.
- Model PHPDoc must reflect schema nullability, casts and relationships. Do not shadow
  Eloquent attributes with public properties. Decimal casts are `numeric-string`.
- `just ide-helpers` refreshes reviewed model docs and ignored PhpStorm/facade metadata.
  Helpers are editor aids, not production autoload or runtime validation. Review stale
  annotations after schema changes. Inspect actual IDE warnings before treating them
  as missing types; `DB::transaction()` already has framework annotations.
- Run relevant gates and meaningful regression tests. Passing tools does not prove
  ownership or contract quality; review these explicitly and report limitations.
