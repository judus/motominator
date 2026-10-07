# Motominator monorepo tooling

This directory is the Laravel server application inside a larger repository.
Use Sail for local PHP, Artisan and Composer commands after initial bootstrap.
Host Composer is needed to install vendor dependencies on a fresh checkout before
Sail exists. CI intentionally uses setup-php and service containers instead of Sail.

JavaScript workspaces and their lockfile belong to the repository root. Run npm
commands from that root on the host for the browser, Expo and Laravel asset builds;
do not install a separate server JavaScript dependency tree inside Sail.
The root justfile provides local server management and forwards arguments to Sail.

Local development uses Sail. The envisioned production target is Docker Compose
on Hetzner, described in docs/deployment.md at the repository root. Production is
not Laravel Cloud and does not use Sail or Mailpit.
