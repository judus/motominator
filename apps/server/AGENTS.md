<laravel-boost-guidelines>
=== .ai/architecture rules ===

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

=== .ai/monorepo rules ===

# Motominator monorepo tooling

Use Sail for local PHP, Artisan and Composer after bootstrap; host Composer installs
initial dependencies. CI uses setup-php and service containers. Root `just` recipes
forward server commands to Sail.

Root npm workspaces own JavaScript dependencies and the lockfile. Run npm on the host
from the repository root, including server assets; do not install a second tree in Sail.

See root `docs/deployment.md` for deployment direction. Local Sail and Mailpit are
not production infrastructure. Framework examples do not choose our hosting provider.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `vendor/bin/sail composer show --direct` to list direct dependencies with versions, or `vendor/bin/sail composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `vendor/bin/sail artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `vendor/bin/sail artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/sail bin phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
