# Branches and merge gates

Current route: `feature/<ticket>-<description>` → `dev` → `main`.
Use `fix/`, `chore/`, `refactor/`, `docs/`, `test/` or `ci/` where appropriate.
Reference the ticket in the PR when one exists; exploratory work need not manufacture
a ticket. `dev` is the default branch and integration target.

Feature PRs normally squash into `dev`. Promote `dev` to `main` with a merge commit
to preserve ancestry; do not squash repeated promotions. Keep `dev` after promotion.
Automatic head-branch deletion is disabled to protect this integration branch;
delete completed feature branches manually.

CI runs on PRs targeting `dev`/`main`, pushes to those branches and manual dispatch.
It has no path exclusions. `PR route` validates branch routing and disallows a fork's
similarly named `dev` from targeting `main`. `Quality gate` requires server and client
checks to succeed, plus PR routing on pull requests. Failures/cancellations never
become an acceptable aggregate result.

Server gates cover Composer/Compose validation, PHP formatting, Larastan, MySQL
migrations/tests and browser cookie/CSRF/2FA E2E. Client gates cover lint, types,
formatting, browser/native behavior tests, browser/server builds and all Expo exports.
See the security/dependency reviews for residual advisories and production controls.

## Protection status

On 2026-10-10 the repository became public and the **Integration branches**
ruleset (ID `24822021`) was activated for both `dev` and `main`.

The rules require PRs, resolved conversations and a successful, current
`Quality gate` from GitHub Actions. Force pushes and branch deletion are blocked;
there are no bypass actors. Required human approvals are zero for solo development;
Copilot is optional. The payload is `.github/rulesets/integration.json`.

Strict checks require feature branches to incorporate the latest `dev` before
merging. If `main` changes independently, merge it back into `dev` before promotion.
The integration workflow is published on `dev`; promoting it to `main` must use a
PR from `dev` with that workflow and successful checks.

For future changes, update the existing ruleset rather than creating a duplicate,
and read the installed rules back to verify enforcement.

## Optional Copilot review

In the PR's Reviewers menu, request Copilot when useful; request another review
manually after fixes. No automatic-review rule or paid review is triggered by CI.
Availability depends on account entitlement. Review guidance lives in
`.github/copilot-instructions.md`; app/root instructions remain authoritative.
See [GitHub's manual review instructions](https://docs.github.com/en/copilot/how-tos/use-copilot-agents/use-code-review).

## Later release branches

When deployment is defined, replace `main` with explicit `testing` and `production`
stages. Update CI branch triggers, PR-route validation, protection patterns and
promotion documentation together. Create branches and verify their checks before
retiring `main`. This setup does not provision environments or deploy anything.
