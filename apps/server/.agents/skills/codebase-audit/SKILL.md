---
name: codebase-audit
description: Perform an evidence-backed codebase audit or audit follow-up for unnecessary complexity, redundant defensive checks, and maintainability problems. Use for requested audits, not routine edits or mechanical formatting.
---

# Codebase audit

Reduce accidental complexity without weakening correctness. A suspicious construct is
an investigation candidate, not a proven defect. Shorter code alone is not an improvement.
Review-only requests authorize investigation; implement changes when the task also
includes them. Distinguish behavior-preserving refactors from behavior-changing fixes.

## Scope and evidence

- Record the repository, baseline commit, branch, dirty work and requested scope. Read
  current project and app guidance; preserve unrelated changes.
- Inventory the scoped files with `git ls-files`, including relevant untracked work when
  auditing the working tree. Review file by file, including relevant tests and configuration;
  searches identify candidates but do not establish coverage. Mark generated output,
  third-party internals, binaries and fixture data separately from manually reviewed source.
- Keep a compact coverage ledger and candidate log in `.context/audits/` or the requested
  location. Record reviewed, pending and excluded files with reasons. Do not assume this
  directory is ignored; inspect Git rules and keep private data and secrets out of reports.
  Truncated output requires another read; it does not count as complete coverage.
- If delegation is authorized, split non-overlapping scopes and reconcile each reviewer's
  evidence and file ledger. Delegation does not itself prove coverage or authorize edits.

For each meaningful candidate record: file/symbol, suspected problem, traced callers,
invariants, proposed change, semantic risks, disposition (keep/refactor/fix/defer) and
validation. Retain rejected candidates with the reason the code is necessary. A bounded
review must state its boundaries; do not claim completion while scoped proofs are pending.

## Trace the contract before changing it

Follow callers, types, construction, deserialization, normalization, prior validation,
storage and consumers. Include HTTP, Filament, CLI, queued jobs, AI/tool and direct Action
callers where applicable. Validation in one controller does not prove an invariant for
an Action called through another entry point.

Distinguish:

- Untrusted request, provider, document, cache, configuration and persisted-data boundaries.
- Domain rules and genuinely missing, optional, uncertain or partial information.
- Internal invariants established on every reachable path.
- Guards compensating for weak contracts, invalid intermediate states or misplaced ownership.

Prefer validation at ingress after proving compatibility, error behavior and bypass paths.
PHPDoc, TypeScript types and casts do not prove external or persisted values are valid.
Repeated-looking parses can normalize, apply defaults or clone: establish their semantics
before merging them. Do not silently tighten acceptance of existing records or drafts.

Trace temporal invariants too: earlier validation does not replace ownership, revisions,
locks, cancellation, expiry or attempt identifiers. Async results can outlive their owner;
a failed operation can already have produced a side effect. Prove retry/idempotency behavior
before changing it, especially around uploads, extraction and invoice confirmation.

## Candidate patterns

- **SQL and Eloquent:** Review nested queries, joins, relation loads and repeated queries.
  Prove cardinality, duplicate handling, filtering, aggregation, ordering/limits and NULL
  behavior before simplifying. Preserve scopes, authorization, outer-join predicates,
  transaction/rollback boundaries and migration/backfill repeatability. Measure suspected
  query hot spots before claiming a performance improvement.
- **Control flow:** Examine nested branches, boolean assignments and empty-loop guards.
  Prove types, truthiness, short-circuiting, side effects and evaluation order. Preserve
  exception behavior, meaningful defaults and unknown state instead of inventing values.
- **Defensive checks:** Remove a guard only after proving the state on every caller path,
  including retained data and alternate adapters. Where invalid state is permitted, correct
  the owning boundary or contract rather than deleting its symptom. Preserve validation,
  permission checks and concurrency protection that serve different purposes.
- **Ownership and abstractions:** Check domain boundaries, explicit DI and shared client
  ownership. Web and mobile must share common API/state/save behavior through
  `@motominator/client`; platform presentation stays local. Keep useful cloning and distinct
  cache/freshness policies. Split at demonstrated seams; avoid generic frameworks,
  repositories or memoization introduced solely to reduce file size or line counts.

## Authorized changes and verification

- Complete the candidate's wider-context proof before editing. When implementation and
  verification are authorized, characterize existing behavior and reproduce defects with
  failing regressions where feasible before applying the fix.
- Make narrow changes. Exercise boundary values, missing/unknown state, duplicates, existing
  data, replacement attempts and cleanup failures where the traced contract requires them.
- Use the relevant app's checks. Server acceptance includes maximum-level Larastan/PHPStan,
  PHPCS and meaningful PHPUnit regressions through Sail; clients use their package tests,
  lint, typecheck and format gates. Keep levels and scope intact rather than suppressing
  findings to make checks pass. State what focused checks leave unverified.
- Verify the affected integration proportionately: HTTP/admin callers for Action changes,
  queue payload compatibility for moved jobs, isolated data for persistence, and shared
  client consumers for common logic. Native or browser checks should prove meaningful
  behavior when affected; cosmetic pixel checks are not an audit requirement.
- Measure before/after workloads for performance claims. Use existing authorization for
  live services and paid AI calls when verification needs them. An audit alone grants no
  permission to mutate user data, deploy, send external messages or publish changes.

Finish with prioritized findings, deliberately retained candidates, verification results,
remaining decisions and exact coverage limits. Keep the detailed ledger local unless its
publication is requested. Do not create tickets or impose a commit/PR/release workflow
unless those activities belong to the user's task.
