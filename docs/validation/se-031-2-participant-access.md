# SE-031.2 — Participant Access Settings and Branded Guest Screens

Validation: **27 November 2026**, [issue #28](../discovery/se-031-2-issue.md).
Branch `codex/se-031-2-participant-access`, created from fetched merged-main
`1ec6786eed4aa9d22d4ca72383762da5d21fd25d` (PR #27). Prior discovery branches and
local experiments were retained. Read AGENTS.md, baseline, development/validation
records and both merged discovery reports. This explicitly authorised presentation
exception is recorded in the baseline specification. Stop for owner review.

## Implemented boundary

One non-autoloaded, non-REST option saves participant mode, provider destinations
and a dormant native-form preference. Settings API capability/nonce plus an explicit
POST-only guard protect complete-value saves. Unset preserves existing behaviour;
notices are scoped/persistent and distinguish invalid setup from implementation
pending. Invitation-only resolves setup. Branding/Tutorial stores remain independent.

Configured ready guests get generic native/provider links in the existing branded
Runner shell. Same-origin/subdirectory/HTTPS URL validation runs at save and render.
The additive AccessManager classifier never calls member policy with a fake user;
unknown/colliding/invalid integrations cannot advertise signup. The SE-017 gate is
still once, default false, exact true, after readiness, with its 403 marker and
once-per-render denial action. Tutorial, authenticated authority, progress, REST,
Directory output and existing saved identity are preserved.

No Core account creation/authentication handler, account fields, active native
interception, abuse store, schema/rewrite/version/dependency change, Pro source edit,
merge, deployment or publication is included. Version/Stable tag remain 0.3.0,
schema 2, PHP 8.0+/WP 6.0+. See [guidance, lifecycle and integration contract](../participant-accounts.md).

## Disposable environment and reproducibility

New marker-guarded temporary root, new MariaDB data directory/database pair, loopback
HTTP workers (8251/8253), database port 33320 and `sp32_` prefix. Existing sites were
not reused or reset. Synthetic `.invalid` users, local captured mail and blocked
outgoing WordPress HTTP/mail only; loopback test HTTP explicitly allowed. No real
credentials, orders, payments, mail, production or deployment service was used.
Database snapshots were restored between independent integration suites. Native
registration/account fixtures deliberately use WordPress APIs or its existing UI,
never a new Core account endpoint.

| Lane | Executed software |
| --- | --- |
| Current WP | WordPress 7.1.5, PHP 8.3.36, MariaDB 10.11.11/InnoDB |
| Exact declared floor | WordPress 6.0, PHP 8.0.30, same engine/separate database |
| Browser | Installed Chrome 158 through Playwright; JavaScript disabled for guest/settings journey; 320/390/1440px |
| Pro | Unmodified current integration, both plugin load orders; final full runtime copy 164 files, zero byte differences; original checkout remained clean |
| Commerce | Actual WooCommerce 11.3.1 on current WP, actual HPOS, virtual synthetic item/offline COD; no premium commerce or real gateway |

The WP update API was rechecked during investigation; this does not raise Core's
floor or tested-up-to metadata. PHP 8.3 is the available supported current-WP lane,
not a claim of newest PHP coverage. Current Woo requires WP 7.0+, so it was not
loaded on the floor lane.

Committed reproducible test entry points (after installing the candidate on an
explicitly disposable site, with outbound mail blocked):

```powershell
$env:SE_TEST_WP_ROOT = '<fresh-disposable-wordpress>'
$env:SE_TEST_DISPOSABLE = 'yes'
$env:SE_TEST_HTTP_BASE = 'http://127.0.0.1:<task-port>'
php tests/integration/participant-access.php
# Browser fixture additionally requires a loopback home and WP environment local.
# Save the manifest only under an ignored, private local directory: it has cookies.
php tests/browser/participant-access-fixture.php > .tools/participant-fixture.json
node tests/browser/participant-access.cjs .tools/participant-fixture.json
node tests/browser/participant-native-registration.cjs .tools/participant-fixture.json
```

Use the same PHP/ini for browser CLI control (`SE_TEST_PHP`/`PHPRC`), and make
Playwright/Chrome available. Set `SE_TEST_SCREENSHOTS=yes` to regenerate screenshots.
The fixture creates a guarded test-only MU plugin and marker in that disposable
site. It must never be installed on a real site or packaged. Its native operational
readiness is a local fixture option, not a production API. Run native experiments
after browser checks; remove that MU fixture before unextended Core/Pro suites.
Use separate/reset database snapshots for suites with destructive lifecycle tests.
Tutorial acceptance requires fresh inactive Core without its existing schema/options.

Ignored `.tools/se0312/` contains this session's provisioning, reset orchestration,
private Pro/Woo adapters and raw logs. None is packaged/committed. Public evidence
is limited to the [sanitised results](se-031-2-results.json), case names and screenshots;
cookies, captured mail, raw paths/configs/database and private source stay local.
Pro's original tests were executed read-only using `SE_PRO_TEST_WP_ROOT` /
`SE_PRO_TEST_DISPOSABLE=yes`; independent workers received the same PHP ini through
their existing `SE_PRO_PHP_ARGS` support.

## Actual results

Counts are assertions or named assertion groups, not exhaustive combinations.
No failed exploratory run is counted as a complete passing suite.

| Check | Final result |
| --- | --- |
| Participant settings/classification/Runner integration | PASS: 171 per lane, current and exact floor |
| Actual settings/guest/native login/recovery browser journey | PASS: 39 per lane, no JS, including POST/nonce/capabilities and rejected valid-nonce GET |
| Native registration-control experiments | PASS: 29 per lane, including actual native POST/admin creation and dispatch variants; production remains dormant |
| Core foundation/authoring/structure/progress | PASS: 46 / 118 / 110 / 145 |
| Core migration/attempts/mid-Sprint restart/recovery/history | PASS: 43 / 51 / 53 / 145 / 82 |
| Core Runner/Dashboard/Runner admin/REST | PASS: 305 / 149 / 53 / 163; actual loopback HTTP included |
| Core branding/polish/validation/prefixing | PASS: 82 / 78 / 132 / 36 |
| Tutorial / focused Core+Pro recovery / tutorial+Pro | PASS: 164 / 9 / 7 |
| Floor existing Runner suite | PASS: 305 |
| Changed Core + unmodified Pro, both load orders | PASS: 26; missing-classifier fallback, actual gate/denial counts, explicit diagnostic open, restricted/unknown/hidden/tutorial, existing identities and exact retained Core/Pro rows |
| Existing Pro access / Workspace / Notes suites | PASS: 52 / 113 / 141, including actual independent Notes workers |
| Existing Pro Directory/Collections / Journey / resource suites | PASS: Directory with 144 Sprints/14 Collections and access-independent output; 77 Journey checks; 57 resource checks with isolated non-web storage |
| Actual Woo My Account + classic/block checkout | PASS: 11 groups with test-only native closure active, real HPOS and the same existing Subscriber ID/role, separate Customer role retained, same-email pending guest order unlinked, unmapped purchase grants nothing |
| Broader existing Pro History suite | **FAIL** after 26 checks at retained rows/table-definition assertion; identical failure on unchanged merged-main base. Not a passing full History/release regression |
| PHP syntax | PASS: 81 PHP files; separate PHP 8.0 lint of all 43 runtime PHP files |
| PHPCS / JS / release metadata / asset checks | PASS: no PHPCS findings; 39 JS tests; release checks; 12 asset assertions |
| Package | PASS: 79 exact shipping runtime files; no tests, fixtures, docs, local paths, private source or dependencies added |
| Documentation/scope | PASS: 107 local links, exact archived issue, sanitised evidence counts, whitespace and public-data review |

Focused cases include whole-value tampering/types, destination encodings/origins/
ports/subdirectory/query actions/page publication/password, native flag/role
invalidation, dormant checked/unchecked/switch persistence, scoped notices, safe
optional login fallback and provider-ignored return guidance. Guest requests
contain no private content/nonce/attempt and retain actual completed history plus
a restarted attempt. Member access never uses the presentation classification.
Fresh native email sign-in returns to the exact server-resolved Sprint. Separate
sessions and private/no-store/noindex headers were observed. No guest/Ready GET
creates an enrolment or Start write.

The final Pro matrix retained real saved Workspace responses and Notes, completed
history/restarted current attempt, native Collection/Journey drafts and an actual
private resource record/file identity. Whole owned-table and existing-user snapshots
matched. Directory component hashes matched for guest/member before/after. These
are focused compatibility checks, not a universal deployment/security certification.

## Native registration investigation and exact remaining work

Inspected pinned current/floor `wp-login.php` dispatch and native registration URL
rendering. [`login_form_{$action}`](https://developer.wordpress.org/reference/hooks/login_form_action/)
runs before the resolved action's switch, including GET and POST registration.
`$_REQUEST['action']` dispatch, query key/checkemail overrides and fallback actions
must remain native. Tests blocked resolved register via GET, direct POST, body-only
action, POST overriding query login, encoded action and interim/reauth variants.
Login, recovery/reset, logout, overrides that resolve away from registration,
invalid actions and actual native admin creation were unaffected. Malformed action
arrays produced HTTP 200/no account on both lanes; floor source lacks the current
string guard. This is native dispatch evidence, not a new input policy in Core.

The [native `register_url` filter](https://developer.wordpress.org/reference/hooks/register_url/)
changes the URL, not whether native login renders its Register link. The sidebar
registration markup filter does not cover all native login/reset links. Returning
an empty URL would create a dead end; changing global `users_can_register` or
rewriting all login output is inappropriate. Retain the link with accessible
blocked-route guidance when closure becomes operational, unless a narrowly scoped
working destination exists with trustworthy context. An independent system that
uses the native registration endpoint would also be affected; administrators must
be told and make the explicit choice.

SE-031.3 must implement active interception only alongside working built-in signup:
require saved built-in mode + explicit checkbox + valid policy + actual operational
readiness, fail back to native policy on invalidation/mode change/deactivation,
single-site only. Re-resolve any originating Sprint hint with exact canonical URL
and fresh Availability checks; no arbitrary return or fabricated Sprint. Render
sign-in/recovery and a safe optional original link, private/no-store/noindex.
Repeat all GET/POST variants and actual Woo/security-provider tests on the final
patch. Do not transplant the fixture's readiness option or test route list into
production. No public enable/bypass hook exists in this implementation.

## Corrections and limitations

- The initial settings storage seed ran after registering validation. It was moved
  before registration so an ordinary first admin visit does not treat safe unset
  defaults as an invalid submission. CLI tests also needed native admin includes
  and server variables on the floor; these were fixture corrections.
- A valid-nonce GET to native `options.php` followed a native redirect loop rather
  than enforcing POST. Complete-value validation retained the old configuration;
  the shipping controller now explicitly rejects non-POST participant saves, tested
  through real HTTP on both lanes.
- A no-JS style-injection helper waited indefinitely; synchronous DOM style
  insertion replaced it. A navigation assertion needed an explicit load wait.
  Actual 400% text enlargement exposed guest-link overflow, fixed by guest-scoped
  wrapping. Final narrow/RTL/keyboard checks passed.
- The first Pro copy omitted block JSON metadata; the full 164-file copy was then
  byte-compared and affected Workspace/matrix checks repeated. Native Pro workers
  needed the same PHP ini; Notes/Journey runs then passed. Resource tests initially
  correctly refused unconfigured storage, then passed with separate guarded,
  explicitly configured non-web test storage. No Pro source was changed.
- The existing Core optional tutorial-Pro test assumed Pro would first allow a
  tutorial. Current Pro also denies it. The updated test accepts that stronger
  policy and separately proves Core vetoes an explicit final integration grant.
- Pro History's broader repeated-install retention assertion fails identically on
  candidate and baseline. It remains a separate Pro/environment investigation;
  no assertion was suppressed or Pro/schema code changed to force a pass.

**NOT RUN / not established:** full multisite network install/embedded journey
(both test sites are single-site; embedded signup remains unsupported), PHP 8.6 or
maintained WP 6.0.19 lane, other database/hosting/proxy versions, actual premium
Subscriptions/Memberships/payment gateways, legacy non-HPOS checkout, complete
resource delivery over a real protected web server/CDN, all Journey/Collection UI
workflows, full Pro release regression, third-party SSO/approval/CAPTCHA/MFA stacks
on this slice, physical devices/screen readers/complete translations/actual browser
400% zoom. RTL/long translation and CSS 400% text enlargement were fixture stress
tests. Real TLS/mail/cache/CDN/provider redirect acceptance is not established on
loopback. Native form closure and all account-creation security gates remain future
implementation work, not enabled protection in this patch.

## Screenshots and owner review gates

Inspected captured PNGs:

- [External settings](images/se-031-2/settings.png),
  [built-in pending/checked preference](images/se-031-2/settings-pending.png).
- Guest [320px](images/se-031-2/guest-320.png), [390px](images/se-031-2/guest-390.png),
  [desktop](images/se-031-2/guest-1440.png), [long-label RTL stress](images/se-031-2/guest-rtl.png).

Before approving this patch, review mode language, pending/dormant clarity,
invitation guidance and the required-names decision; test a real provider's signup,
sign-in/recovery/original-link return; confirm branded guest screens and existing
Pro member/gate behaviour on staging. Resolve or accept the recorded existing
History-suite limit explicitly before release acceptance.

Recommended next ticket: **SE-031.3 native account creation and active native-form
control**, with the spike's atomic abuse/CSRF/replay/session/policy requirements,
required international first/last names and unsupported-provider delegation.
The optional Pro presentation adapter remains separately approved and cannot be
required for existing functionality. SE-031.4 must cover complete Core/Pro/security/
commerce regression and owner staging acceptance. Only then may a separate release-
preparation ticket review all unreleased work, including Tutorial, metadata,
readme/changelog, WP/PHP/tested-up-to evidence, package and publication checklist.
No merge, deployment, version bump or release is authorised by this record.

At completion both recorded task HTTP workers and the isolated database were
stopped; none of their three ports remained listening. Earlier disposable sites
and unrelated services were retained.
