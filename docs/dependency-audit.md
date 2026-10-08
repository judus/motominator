# npm dependency audit — 2026-10-08

After a clean `npm ci`, `npm audit --json` reports **65 affected packages**:
49 high, 16 moderate, zero critical. These are propagated dependency findings,
not 65 distinct vulnerabilities. The five root advisories are below. Registry
results changed temporarily to 66 during installation; the final clean-install
snapshot is 65. Audit data is time-dependent: rerun before release.

`npm audit fix --ignore-scripts` found no compatible remediation. No `--force`,
major Expo/React Native upgrade or speculative dependency overrides were applied.
Expo remains SDK 57 with its supported Jest 29 integration. SecureStore/Crypto
were installed with Expo's version-matching installer; Playwright is development tooling.

| Root advisory | Installed path / exposure | Upstream blocker |
| --- | --- | --- |
| [braces stack exhaustion](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm) | Jest 29 → micromatch 4 → braces 3.0.3; test/Metro pattern processing, no application endpoint invokes brace expansion | Advisory lists no patched version. npm proposes a Jest major change; that does not establish a compatible SDK 57 fix. |
| [decode-uri-component denial of service](https://github.com/advisories/GHSA-vcc3-ghjq-m6fr) | Expo Router 57 → query-string 7.1.3 → decode-uri-component 0.2.2; **runtime URL/query parsing**, including externally opened routes | Patched 0.5.0 is outside the parent's declared range. npm proposes Router 58, outside this SDK. Do not treat this as development-only. |
| [node-forge signature verification](https://github.com/advisories/GHSA-86w9-cpqp-85rv) | Expo CLI / code-signing-certificates → node-forge 1.4.0; development/build signing tooling | Advisory lists no patched version. npm proposes an Expo 44 downgrade, which is not a valid remediation. |
| [sprintf-js precision denial of service](https://github.com/advisories/GHSA-hp3w-g68c-fv3c) | jest-expo → babel-jest → Istanbul → js-yaml → argparse → sprintf-js 1.0.3; test/coverage configuration processing | Advisory lists no patched version. npm proposes jest-expo 58, outside this SDK. |
| [uuid buffer bounds](https://github.com/advisories/GHSA-w5hq-g745-h8pq) | Expo config plugins → xcode 3.0.1 → uuid 7.0.3; native project generation/build tooling | Patched versions start at 11.1.1. Parent requires an older major. The advisory concerns v3/v5/v6 with supplied output buffers; an actual affected application call has not been demonstrated. |

Paths come from the installed tree (`npm ls braces decode-uri-component node-forge
sprintf-js uuid --all`) and the root lockfile. Exposure descriptions are source/path
inferences, not exploit tests. The React browser application has no path to these
five packages in its bundled application code; this does not excuse Expo's runtime
URL parser finding. Provider callback URLs are validated by our app, but that does
not remove the router's upstream parser exposure.

## Continue

Keep the dependency ticket open. Monitor the linked maintainer advisories and SDK 57
patches; recheck safe parent upgrades as they become available. Plan an SDK upgrade
separately if compatible fixes never arrive. Before distributing the native app,
resolve or explicitly review the Router runtime finding and do not claim a clean audit.
After dependency changes run `npm ci`, audit, tests, lint/type/format checks, browser
and server builds, and Android/iOS/web Expo exports. Successful exports do not prove
native binaries, signing, devices or vulnerability remediation.
