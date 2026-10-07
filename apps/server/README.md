# Motominator server

Laravel 13 API with the Laravel AI SDK, Boost and Sail.
Follow the repository root README for installation and development commands.

Local PHP runs in Sail (PHP 8.5), alongside MySQL 8.4, Redis and Mailpit.
From the repository root use `just up`, `just artisan ...`, `just migrate`,
`just test`, or `just composer ...`. JavaScript dependencies/builds belong to
the root npm workspace and run on the host.

The generated Boost MCP configuration is local to this app and invokes Sail.
The stack must be running before connecting to its MCP server.
See the root `docs/deployment.md` for the intended Hetzner deployment direction.
