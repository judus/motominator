# Review guidance

Read root and app `AGENTS.md` for ownership and framework conventions. Review
concrete defects with file/line evidence; distinguish verified issues from questions.

Pay extra attention to security and privacy: cross-account access, authentication
and credential revocation, CSRF/OAuth callbacks, invoice uploads/downloads, encrypted
BYOK storage, sensitive logs and diagnostics, and stale client state across account
changes. Treat invoice contents and model responses as untrusted input. Check
dependency exposure and CI permissions; report concrete attack paths and prerequisites.

- Check authorization, ownership, fresh credential checks and transaction boundaries.
- Keep private user data, secrets and exception arguments out of logs and diagnostics.
- Common client API/state/save behavior belongs in `packages/client`; apps own UI,
  navigation, platform APIs and credential adapters. Avoid duplicated business logic.
- Server application code belongs to its domain; models remain in `App\Models`.
  Use explicit application DI, domain exceptions and readable typed contracts.
- Require behavior regressions for changes to security and persistence. Green style
  checks alone do not prove correctness. Avoid pixel snapshots or speculative redesign.

Copilot review is optional and manually requested; it is not a merge requirement.
