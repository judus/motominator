# Integration setup — 2026-10-10

Created local/remote `dev` at the existing published main tip and set it as GitHub's
default PR target. At that stage, application changes remained uncommitted in the
original workspace; all 223 pre-existing changed paths were preserved.

Pipeline-only commit `a99d00a` lives on `ci/integration-workflow`, created in an
isolated worktree. [PR #12](https://github.com/judus/motominator/pull/12) targets `dev`
and was squash-merged after both hosted validation runs passed.
After PR #12, dev was at `a0f347e`; main stayed at the previous published baseline.
That PR contained no application changes or Copilot review request.

Actionlint 1.7.12 and 14 route/aggregate cases pass. All four hosted checks
(PR route, Laravel/MySQL, Browser/Expo and Quality gate) passed on commit a99d00a;
The ready-for-review repeat also passed all four gates. The normal post-merge
CI run on dev was started by GitHub automatically.

Initially, private-repository protection reads and activation returned 403, and
checks were advisory. On 2026-10-10 the user made the repository public. The
Integration branches ruleset (ID `24822021`) is now active; branch-rule reads
confirmed PR requirements, the current Quality gate, and force-push/deletion
prevention on both dev and main. No billing change was needed.

Read [branches-and-merging.md](../docs/branches-and-merging.md) for the route,
required check, merge methods, optional manual Copilot reviews and later migration
to testing/production. Publish pending application work through its own branch/PR
so gates evaluate that code.

The repository is publicly visible but original Motominator work remains
proprietary: root LICENSE reserves all rights to Julien Duseyau. Own npm manifests
use UNLICENSED and Composer uses proprietary. Third-party licenses remain intact,
including the Expo scaffold notice in apps/mobile/LICENSE.

Licensing and protection documentation were published through PR #13
(https://github.com/judus/motominator/pull/13), squash-merged into dev after all
four hosted quality checks passed. At that stage, the original workspace stayed on
main with pending application changes preserved. No dev-to-main promotion was performed.

## Consolidated setup review

The pending application setup is prepared as one commit on `chore/initial-setup`,
based on current dev (`16efe6f`), for a PR to dev and a manually requested Copilot
review. Repository review instructions emphasize security and privacy. This step
does not promote to main or merge the setup PR.

Fresh local validation passed: maximum-level Larastan, PHPCS, client lint/types/
formatting, 245 server tests (1,328 assertions), 44 browser tests, 53 mobile tests,
browser authentication E2E, browser/server builds and Android/iOS/web exports.
Credential-pattern scanning found no matches in files being published; only example
environments are included. Uploaded private documents, credentials, local diagnostics
and build outputs remain ignored. This scan and green gates do not prove absence
of vulnerabilities; see the security and dependency reviews for limits and residual
tooling advisories. Hosted validation and Copilot review are separate follow-up
evidence on the PR.

## PR #14 review follow-up

The user authorized review remediation and merging PR #14 into dev. Copilot's six
initial findings were reproduced and fixed: four stale verification decisions
now recheck the locked account row, native logout retains credentials/account
state until revocation succeeds, and native OAuth handoff invalidates its temporary
browser session. Regressions cover all four verified writes, failed/retried and
already-revoked logout, concurrent account replacement, and repeated native login.
No initial finding was speculative or deferred; the severity labels do not imply
cross-account access. Quality gates and a follow-up Copilot review validate the
revised single-commit setup before merge. GitHub records final review disposition
and merge status on https://github.com/judus/motominator/pull/14.

A related manual check reproduced a foreground refresh restoring displayed account
state while logout awaited revocation. Logout now reconciles token storage if a
refresh advanced its generation; the regression also verifies the late response
is ignored. The complete native suite passes 57 tests after this addition.

The follow-up Copilot review identified five additional current-authorization
checks: connection testing, evidence attachment, task fulfilment, maintenance
saving and motorcycle saving. All were fixed; garage actions lock and authorize
the fresh actor, including custom-owner creation, and connection testing snapshots
current verification/credentials under locks before releasing the transaction for
the external request. Already-authorized in-flight provider requests are not
cancelled by later key removal; network latency does not hold account locks.
Regressions cover revoked verification and revoked administrator privileges across
five garage write paths, denied paid checks and provider prompting outside the
authorization transaction. All eleven Copilot findings were actionable and fixed,
so none required a deferred low-severity ticket.

Refreshing actors also exposed a native activity-source regression. Activity
recording now reads matching authenticated-request token metadata for attribution,
while authorization uses the locked database actor. Existing native garage and a
new native BYOK activity regression verify that these entries remain mobile.

Final local full suites passed 263 PHP tests (1,452 assertions), 44 browser tests
and 57 mobile tests. Hosted quality gates run on the final amended setup commit
before merge; the PR stays as one commit throughout remediation.
