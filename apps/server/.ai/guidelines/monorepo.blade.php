# Motominator monorepo tooling

Use Sail for local PHP, Artisan and Composer after bootstrap; host Composer installs
initial dependencies. CI uses setup-php and service containers. Root `just` recipes
forward server commands to Sail.

Root npm workspaces own JavaScript dependencies and the lockfile. Run npm on the host
from the repository root, including server assets; do not install a second tree in Sail.

See root `docs/deployment.md` for deployment direction. Local Sail and Mailpit are
not production infrastructure. Framework examples do not choose our hosting provider.
