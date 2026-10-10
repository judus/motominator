# Security and privacy contracts

Implemented after the 2026-10-10 technical audit. The detailed evidence and remaining
release controls are in [the audit report](audits/2026-10-10-security-review.md).

## Account mutations

Email changes require the current password; name-only changes do not. Security
writes load and lock a fresh user before checking credentials and saving. Password
changes revoke device tokens and broker reset tokens through the Accounts observer;
admin writes keep that entire operation in a transaction. Reset actions recheck
broker-token validity under the same user lock.

Native authorization intents retain a server-only security fingerprint. Password,
email and two-factor changes invalidate pending proof. A provider callback stages
the identity; linking occurs only after authenticated verifier consumption. Keep
actor and IP limits independent for retained password-check endpoints.

## Private data

Telescope and Horizon require a verified administrator, including locally. Personal
API, authentication and admin traffic is excluded from diagnostics. Exception logs
retain class, code, location, trace ID and argument-free stacks; messages, arguments,
previous exceptions and SQL bindings cannot carry private prose into those logs.
Treat the activity feed as user data with explicit ownership, not ordinary logging.

Uploads are bounded per request and per account: default 100 documents / 100 MiB.
Account locking serializes allocation across motorcycles; failed writes/transactions
remove partial files. Native invoice copies have feature-owned paths and leases;
abandonment waits for active uploads, and launch sweeps unleased copies older than
24 hours. Original selected documents and photos remain intact.

## Text copilot

Laravel owns BYOK credentials, SDK requests and conversation storage. Both clients
use authenticated HTTP/SSE; they do not connect to the model provider directly.
Conversation queries scope the participant type and ID, and native permissions
separate `ai:read`, `ai:write` and `garage:read`. Writes require a freshly verified
account. User keys never fall back to an application key.

The agent has two read-only garage tools: paginated motorcycle summaries and
bounded maintenance/mileage history. Every tool checks current account ownership;
missing records are unknown history, not evidence that maintenance never happened.
Model output renders as plain text. Tools cannot change data or access invoices,
credentials or another rider's records. Prompt instructions are additional guidance;
authorization is enforced in PHP independently of model behavior.

Messages and requested garage facts are disclosed to the selected provider. The
SDK keeps conversation content and internal tool steps in the private database;
the public history exposes user/assistant text only. Provider exception messages
are replaced before SDK persistence and excluded from application diagnostics.
OpenAI requests disable provider-side response storage where supported; this does
not establish a provider-wide retention policy.

Reply/deletion locks prevent concurrent turns and deletion during generation.
Stop closes the client connection; Laravel detects it between SDK events, stores
an incomplete reply and releases the lock. This does not promise immediate upstream
cancellation or prevent charges already incurred. Production SSE/proxy behavior,
conversation retention/quotas and account-wide export/deletion remain release work.

## Client authentication

Browser auth generations prevent old refresh/401 responses from restoring or
expiring replacement sessions. A CSRF bootstrap that crosses account replacement
cannot submit a mutation under the new cookies. Native credential storage serializes
comparison and deletion; stale requests may clear only their own token.

## Local infrastructure and release work

Backing service and Vite host ports bind to loopback. The ordinary backend and
Expo development server remain LAN-reachable for devices. Disposable browser-test
servers bind to loopback and remove their own container on graceful termination.

The runtime Expo decoder is patched reproducibly; five tooling advisory roots
remain documented in [the dependency review](../docs/dependency-audit.md).

Production transport/cookies/proxies, storage and backup access/encryption, data
deletion/export/retention, provider retention and signed native builds still need
verification. Historical ordinary logs/backups were not inspected or erased. Old
Telescope diagnostics were pruned once after fixing capture. Account quotas do not
replace infrastructure capacity monitoring or a retention policy.
