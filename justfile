# Server lifecycle uses Sail; frontend tooling stays in npm workspaces.
set positional-arguments

# List available helpers.
default:
    @just --list

# Install dependencies and create the server environment, preserving existing values.
bootstrap:
    cd apps/server && composer install --no-interaction
    cd apps/server && if [ ! -f .env ]; then cp .env.example .env; fi
    npm ci

# Prepare the local stack, application key and database on a fresh checkout.
setup: bootstrap
    just up
    cd apps/server && if ! grep -q '^APP_KEY=.' .env; then ./vendor/bin/sail artisan key:generate --no-interaction; fi
    just migrate

# Start PHP, MySQL, Redis and Mailpit; wait for services to be ready.
up:
    cd apps/server && ./vendor/bin/sail up -d --wait

# Stop containers and remove their network, preserving database/cache volumes.
down:
    cd apps/server && ./vendor/bin/sail down

# Stop the stack without removing containers.
stop:
    cd apps/server && ./vendor/bin/sail stop

# Build the Sail runtime.
build:
    cd apps/server && ./vendor/bin/sail build

# Show container status.
status:
    cd apps/server && ./vendor/bin/sail ps

# Follow all logs, or pass a service name.
logs *args:
    cd apps/server && ./vendor/bin/sail logs --follow "$@"

# Forward arbitrary arguments to Sail (for example: just sail exec mysql mysql --version).
sail *args:
    cd apps/server && ./vendor/bin/sail "$@"

# Run Artisan inside Sail (for example: just artisan route:list).
artisan *args:
    cd apps/server && ./vendor/bin/sail artisan "$@"

# Run Composer inside Sail.
composer *args:
    cd apps/server && ./vendor/bin/sail composer "$@"

# Apply database migrations.
migrate:
    just artisan migrate --no-interaction

# Run the server test suite against Sail's testing database.
test *args:
    cd apps/server && ./vendor/bin/sail test --compact "$@"

# Follow Horizon worker logs (started by just up).
queue:
    just sail logs --follow horizon

# Follow scheduler logs (started by just up).
scheduler:
    just sail logs --follow scheduler

# Open a shell inside the PHP container.
shell:
    cd apps/server && ./vendor/bin/sail shell

# Start the dedicated browser app on the host.
web:
    npm run dev:web

# Start Expo on the host.
mobile:
    npm run dev:mobile

# Check PHP analysis/formatting and frontend analysis/formatting.
check:
    just analyse
    just format-check
    npm run lint
    npm run typecheck

# Run Larastan through PHPStan.
analyse:
    cd apps/server && ./vendor/bin/sail composer analyse

# Format PHP and client source files.
format:
    cd apps/server && ./vendor/bin/sail composer format
    npm run format

# Check formatting without changing files.
format-check:
    cd apps/server && ./vendor/bin/sail composer format:check
    npm run format:check

# Run all three app test suites.
test-all:
    just test
    npm test

# Start Expo with local MCP/DevTools capabilities (requires Expo login).
mobile-mcp:
    npm run dev:mobile:mcp
