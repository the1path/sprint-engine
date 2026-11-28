# SE-031.3 — native participant registration and native form control

Work started 27 November; final validation/review preparation 28 November 2026.
**Unreleased; draft PR / owner review only.**
Issue [#30](https://github.com/the1path/sprint-engine/issues/30),
[archived specification](../discovery/se-031-3-issue.md).
Branch `codex/se-031-3-native-registration`, base
`2215c9cab2c3cf9aaa885a49ced73dd1f6226529` (merged PR #29 verified on origin/main).
Core metadata **0.3.0**, schema **2**, WP floor **6.0**, PHP floor **8.0** unchanged.
No Pro source, deployment, merge, release or runtime dependency changes.
The subsequent [PR #31 database compatibility review](se-031-3-database-compatibility.md)
supersedes the initial MariaDB-only database evidence and records actual MySQL
execution, transaction preservation and the health-to-mutex missing-state fix.

Read with the merged [SE-031 discovery](../discovery/se-031-participant-registration.md),
[SE-031.1 spike](../discovery/se-031-1-registration-spike.md),
[SE-031.2 contract/encoding review](se-031-2-participant-access.md),
[current accounts contract](../participant-accounts.md) and
[operator guide](../participant-registration-security.md).

## Implementation decisions

Use a small controller in the actual existing Runner preparation boundary,
native four-field HTML, native WP profile fields, applicable native registration
hooks, a fixed `wp_insert_user()` structure and `wp_signon()`. The LifterLMS/native
architecture findings inform separation of form, validation, account creation and
authentication; no LMS dependency or wholesale experimental MU code transplant.
`register_new_user()` cannot fulfil the approved chosen-password/no-user-setup-email
journey. No global temporary password/role override or direct auth-cookie/current-
user call substitutes for the native lifecycle.

Explicit operator confirmation and objective prerequisites protect built-in mode.
SE-031.2 four-field preferences read as unconfirmed; checked native closure cannot
activate them. Confirmation contains a random 256-bit generation signed against
current configuration. Every deliberate checked save rotates it; each form MAC
binds that generation. Fresh checks under the registration mutex reject a changed
generation. Thus clearing temporary replay state and reconfirming cannot revive
an old used form with a different email. Browser cookies and sign-on receipts are
separate from that form generation, so pending native authentication is not an
alternative identity system.

Explicit confirmation first proves state creation/write/read-back under the mutex.
Missing live replay state revokes operation instead of silently resetting it.
Raw writes clear WordPress's positive, negative and autoload option caches.
The fingerprint uses configured URL options, not request-dependent `site_url()`;
insecure transport neither enables built-in signup nor reopens a confirmed HTTPS
native gate. These boundaries have regression coverage.

Native `wpdb`/mysqli, autocommit, no active transaction and multiple owned named
locks are required. One bounded non-autoloaded JSON option and one per-site mutex
serialize reservation, final identity uniqueness and native insertion. No external
transaction spans callbacks. Native wpdb auto-reconnect is disabled within that
critical section, and the original handle/connection/lock is rechecked before
writes and at final native insertion. Native retry/error policy is restored and
the lock released before Core notification/authentication. Fresh role/options,
Sprint and Step caches are cleared at insertion checks; availability is not
inferred from the earlier guest render.

Launch budgets are 100/site and 40/actual peer per 10-minute fixed window,
6/browser per 10 minutes, 3/submitted normalised email per hour, 20-minute replay
records, 2,048 entries and 262,144 bytes maximum. These are actual launch constants,
not the spike's tiny diagnostic settings. They allow modest launches while bounding
form-driven writes. Shared proxies/IPs share a budget; fixed-window boundary bursts
and costly trusted callbacks remain possible. Core ignores forwarded IP headers;
platform signup/login protection and host load acceptance remain required.

Native form closure uses only `login_form_register`, for explicitly confirmed,
valid built-in configuration. It keeps an already-valid gate closed under transient
rate/lock/capacity rejection. Structural invalidation/deactivation revokes consent;
native policy resumes and must remain protected. Native Register links remain with
accessible blocked-route guidance. No global registration flag/link rewriting or
login takeover. WooCommerce and independent forms remain independent.

Settings shows relevant selected-mode guidance through progressive enhancement;
native disclosures work without JavaScript. URLs/preferences survive switching.
Runner removes the repeated guest subheading and uses plain Sign in for neutral
unknown/restricted access. New controls inherit Runner branding and visible focus.
**Settings tabs are deferred.**

## Executed environments

| Component | Current | Exact floor |
| --- | --- | --- |
| WordPress | 7.1.5 | 6.0 |
| PHP | 8.3.36 | 8.0.30 |
| Database | MariaDB 10.11.11, InnoDB, native mysqli | Same separate disposable DB |
| URL | Real loopback TLS, `/wordpress` subdirectory | Real loopback TLS, root installation |
| Browser | Chrome 158.0.8127.37 / Playwright 1.62.1 / Node 24.22.3 | Same |
| Selected native MFA | Two Factor 0.17.0 | Unchanged historical Two Factor 0.9.1 |

Two Factor 0.17.0 declares WP 7.0 minimum and was **not forced** onto WP 6.0.
Its rejected floor activation was a harness selection failure, corrected by an
unchanged compatible historical package, not a plugin/header bypass. 0.9.1 is
historical compatibility evidence, not a production security recommendation.
Sources: [0.9.1 source](https://github.com/WordPress/two-factor/blob/0.9.1/two-factor.php),
[official package](https://downloads.wordpress.org/plugin/two-factor.0.9.1.zip)
(SHA-256 `ebe02c4bb1a910c93539419b796ccd6bde2176c0ca11b061c17bac4fdef65933`).

Separate fresh task-owned databases used prefix `sp33_`, synthetic users, blocked
outbound HTTP/mail and local captured mail only. Four independent loopback PHP
workers (two per installation) sat behind an isolated TLS listener; native PHP
login/admin files also received correct HTTPS server configuration. A self-signed
test certificate was accepted only by the test browser/HTTP client; no machine
trust-store changes. Production TLS/certificate/proxy acceptance is **not** implied.
General existing regression suites used isolated loopback HTTP configuration;
registration/security/browser suites used TLS. No live site or real order/payment.

## Results

The accompanying [sanitised results](se-031-3-results.json) contain executed counts
and individual focused assertions. Passwords, addresses, cookies, tokens, native
MFA/reset codes, mail bodies, credentials, private Pro source and local paths are
excluded from committed evidence.

- Native/security integration: **267 assertions on each stack**. Required Unicode
  names, apostrophes/slashes, whitespace, blank/control/structured/overlong values;
  native password byte fidelity through 4,096 bytes (ASCII and multibyte), rejection
  above bounds, native email sign-on/hash/reset, safe Subscriber identity, hooks,
  altered/denied native identity, post-insert privilege denial without deletion,
  uniqueness/replay, production site/peer/browser/email budgets, real capacity,
  privacy/non-autoload storage, expiry/purge, mutex loss/exception/retry restoration,
  provenance and signed expiry, receipts and generation rotation after cleanup.
- Actual restricted-grant persistence: **4 assertions**, using a throwaway options
  clone and separate database user with SELECT/UPDATE but no INSERT. Read/lock
  probes pass, initial persistence fails, consent stays disabled with setup guidance.
  The clone/user are removed; original options, users and Pro data are untouched.
- Actual HTTPS browser/HTTP journeys: **98 checks per stack**, normal and no-JS signup,
  canonical international slugs/subdirectory returns, fresh Ready, no attempt
  before Start, forged fields/actions/origin/referrer/fetch metadata, sibling-domain
  prefix-cookie injection and sibling-origin login CSRF, independent workers for
  duplicate token/email/contention, multi-tab/stale authenticated POST, native
  registration denial/auth rejection/cookie suppression/mail failure, interrupted
  insertion, connection kills before/during insertion, cached Step unpublication,
  operator reconfirmation during validation, selected real MFA challenge/completion,
  SE-017 exact counts/403/action placement, unsupported content, native dispatch
  variants, setup invalidation, real Settings API saves, no-JS admin and lifecycle.
- Additional boundaries: **11 checks per stack**, oversized/JSON/PUT rejection, exactly one
  initial native admin notification with no Core participant setup/welcome email,
  no duplicate notification on retry, used-token rejection after replay-state
  cleanup/reconfirmation, and an actual different native identity denied before
  member resume/progress can run.
- Browser accessibility: **6 checks**, native labels/focus, error-summary autofocus measured
  after layout, anchor-to-field focus, cleared password/preserved safe values,
  computed default text/button contrast ≥4.5:1 and field boundary ≥3:1, 360/320px
  reflow, RTL, long simulated labels and 200% CSS zoom. Native autocomplete/paste
  support is retained; actual password-manager software/assistive devices were not
  available. These checks do not claim complete WCAG certification.

All general Core suites passed on **both** stacks. Registration, browser and
compatibility checks were rerun for the later cache, persistence and generation
hardening; general regression behavior remains outside those account boundaries.

| Suite | Current / floor assertions |
| --- | --- |
| Foundation / authoring / structure | 46 / 118 / 110 each |
| Progress / migration / attempts | 145 / 43 / 51 each |
| Mid-Sprint restart / Step-change recovery / attempt history | 53 / 145 / 82 each |
| Runner / Dashboard / Runner admin / REST | 305 / 149 / 53 / 163 each |
| Branding | 82 current; 81 floor (version-specific native block coverage) |
| Polish / validation / prefixing / Tutorial | 78 / 132 / 36 / 164 each |
| Existing Participant Accounts / exact rendered return encoding | 289 each |
| Focused unchanged-Pro recovery / Tutorial | 9 / 7 on current |

PHPCS, PHP syntax, changed JavaScript syntax, all 39 JS tests, release metadata,
12 asset checks and exact runtime package verification passed. The verification
ZIP is ignored; it is not a release artifact or committed output. Version/schema/
minimums, routes and runtime dependencies remain unchanged.

### Pro and commerce

Private Pro source remained clean at `6cef901a29460ca8fbd6fbd37b31dd5998cd9363`.
All **162 copied runtime files**, including block metadata, matched original bytes.
No Pro edits or failing-assertion suppression. Both actual plugin load orders
passed (**18 focused checks**): absent classifier remains neutral, diagnostic known-open classification
enables only eligible signup, public gate/denial counts and status stay exact,
normal native existing-member email sign-in and actual Pro entitlement checks
remain authoritative. Snapshot checks preserve all Core/Pro table rows and
retained Workspace, Notes, attempts/history, resources, Collection/Journey objects
and existing profile identity. Native session tokens are excluded from profile
comparison because native sign-on legitimately creates sessions.

Broader unchanged Pro: access **52**, Workspace **113**, Notes **141**, complete
Directory (**144 Sprints / 14 Collections**), Journeys **77**, Resources **57** passed.
Their legacy summary wording is not a claim of SQLite testing in this run; the
actual DB was MariaDB. Complete protected-resource HTTP/CDN/export/Journey UI
acceptance is separate.

**Outstanding failure — SE-031.4 release acceptance investigation:** broader Pro
History still fails after **26 passing assertions** at
`PRO-004 install/initialize leaves every existing response/Core row and table definition unchanged.`
This is the existing unchanged-baseline failure recorded in SE-031.1/SE-031.2 and
PR #29's encoding review, not a passing full History suite. Its assertion remains
unchanged. No new History fix or further Pro investigation is included here;
baseline reproduction does not satisfy release acceptance.

Actual **WooCommerce 11.3.1 / HPOS** (**13 focused checks**): My Account registration, normal authenticated
customer account, Core native participant registration with Woo/Pro loaded,
classic checkout HTTP handler and block Store API checkout succeeded using a
synthetic virtual item and offline COD. Both orders retain the exact new Subscriber
ID/role; an existing customer retains Customer; a same-email historical pending
guest order remains unlinked. Unmapped purchases grant no restricted Sprint.
Core's actual native-form closure stayed active throughout; no substitute MU gate.
Licensed Memberships/Subscriptions and real gateways were not installed or tested.

## Reproduction

Never use a real site. The tests mutate accounts, settings, content, owned tables
and native hooks. General Core tests deliberately test failures/drop tables; use
fresh snapshots/databases for each suite, not a shared fixture after other tests.

1. Create isolated current and exact-floor WordPress/PHP installations, each with
   an administrator ID 1, non-default prefix and own disposable MariaDB database.
   Install final Core runtime normally. Mark each WordPress root with
   `SE0313-DISPOSABLE` and define `SE0313_DISPOSABLE=true` plus `SE0313_ROOT` pointing
   to a private test evidence directory. This is a **test fixture guard only**;
   no corresponding enable/bypass flag exists in shipping Core.
2. Serve `https://signup.se0313.test:8461/wordpress` and
   `https://signup.se0313.test:8463` through isolated loopback TLS, with WordPress
   correctly recognising HTTPS for front-controller **and native PHP files**.
   Use two independent PHP workers per DB for race tests. Resolve test hosts to
   loopback in the client; also allow `evil.se0313.test` at that isolated listener
   for browser origin/cookie tests. Do not add a production HTTPS bypass.
3. Block all outbound HTTP/mail in guarded test MU code. Capture `pre_wp_mail`
   locally to `<evidence>/mail/captured.jsonl` and return true without sending;
   allow only explicit loopback test ports for general regression HTTP checks.
   Copy `tests/fixtures/participant-registration-hooks.php` to test MU plugins.
   Set `WP_HOME`/`WP_SITEURL`, disable automatic updates and unattended cron in
   these fixtures. Native mail/MFA codes and synthetic admin credentials remain
   outside Git. Save synthetic admin login/password to `<lane>-admin.json` there
   for the real administrator browser tests.
4. Set environment below, run integration, then feed `{"action":"setup"}` on
   stdin to `tests/fixtures/participant-registration.php`, saving its JSON to a
   private fixture file. Set `SE_TEST_FIXTURE_JSON` to that file. For floor MFA
   use the historical unchanged package under an independent plugin directory and
   set `SE_TEST_MFA_PLUGIN=two-factor-floor/two-factor.php`; current uses 0.17.0.
5. Optional unchanged Pro tests require the actual private Pro runtime, its normal
   activation and private verified Resource storage **outside all document roots**.
   Copy the separately guarded `participant-registration-pro-hooks.php` MU
   diagnostics for the load-order/classifier tests. They are not a production
   Pro adapter. Install actual WooCommerce for commerce tests. Do not edit Pro.

```sh
# Use the matching PHP executable/INI per lane; all paths are private test paths.
export SE_TEST_DISPOSABLE=yes
export SE_TEST_WP_ROOT=/isolated/wordpress
export SE_TEST_PHP=/matching/php
export SE_TEST_PHP_INI=/matching/php.ini
export SE_TEST_LANE=current # or floor
export SE_TEST_EVIDENCE_DIR=/private/evidence
export SE_TEST_FIXTURE_JSON=/private/current-fixtures.json
php -c /matching/php.ini tests/integration/participant-registration.php
# Optional database-administrator fixture; clone/user only, never on a real DB:
SE_TEST_DB_ADMIN=yes php -c /matching/php.ini tests/integration/participant-registration-storage.php
node tests/browser/participant-registration.cjs
node tests/browser/participant-registration-boundaries.cjs
node tests/browser/participant-registration-accessibility.cjs # current
node tests/browser/participant-registration-pro.cjs           # current + actual Pro
node tests/browser/participant-registration-commerce.cjs      # current + actual Woo

# Fresh snapshot per existing integration suite:
composer check
composer test
composer test:tutorial
node --test tests/js/*.test.cjs
php tools/package.php
php tests/package.php
```

Install Playwright/Chrome in the external test environment, not Core dependencies.
The browser script resolves its fixture host to loopback and uses a self-signed
certificate exception only inside its disposable browser. Core still requires
real HTTPS. See the committed fixture scripts for the exact JSON control actions.
Run original Pro `tests/integration/{access,workspace,notes,directory,journeys,resources,history}.php`
with `SE_PRO_TEST_WP_ROOT`, `SE_PRO_TEST_DISPOSABLE=yes` and matching
`SE_PRO_PHP_ARGS` on independent disposable snapshots.

Harness corrections during execution: WP 6.0 CLI needed native server-name/admin
post-helper bootstrap (the prefixing test now explicitly requires that helper);
general regressions needed unset participant configuration in their initial
snapshot; native TLS files needed the proxy HTTPS setup; native logout without a
nonce legitimately returns WP's confirmation 403; MFA verification must submit
hidden controls plus code, not the resend submit button. These were corrected and
affected checks rerun. The resumed run restarted only the recorded disposable
services; per-database diagnostic filenames prevent concurrent lane log collisions.
Native administrator input waits for the login scripts to settle.
Product findings corrected and covered include stale Step/
option caches, raw option negative-cache coherence, initial write/read-back and
missing-state failure, replay generation after cleanup, and request-independent
URL fingerprints/HTTP closure. No production failure assertion was hidden.

## Screenshots and remaining gates

See [desktop](images/se-031-3/current-registration-desktop.png),
[mobile](images/se-031-3/current-registration-mobile.png),
[RTL/zoom](images/se-031-3/current-registration-rtl-zoom.png),
[error focus](images/se-031-3/registration-errors.png), and
[selected-mode settings](images/se-031-3/current-settings-external.png).
They contain synthetic fixture data only. Floor images and long-label reflow are
also retained alongside them. Visual review verified the existing Runner layout,
readable fields/actions, no clipped controls and no horizontal overflow.

**NOT RUN / not certified:** other database versions/engines/drop-ins,
persistent/multiplexed/multiwriter databases, production
TLS/proxy/CDN/cache/mail, sustained signup load/DDoS, a full multisite installation,
other native/provider MFA/approval/verification/CAPTCHA/SSO combinations, maintained
6.0.x/PHP 8.6 variants, licensed commerce or live gateways, every native Woo block
checkout UI/theme, actual password-manager software, physical phones/screen readers,
complete translations, forced-colour modes and real browser 400% zoom. The 320px
viewport and 200% CSS zoom checks are not a claim of that last test.

Initial validation used only the stated single-writer MariaDB stack; the
[database follow-up](se-031-3-database-compatibility.md) adds executed MySQL 8.0.45
evidence and retained MariaDB current/floor regression. The portable savepoint
transaction-state probe must succeed; engines lacking it stay disabled
with setup guidance. A successful lock probe cannot certify a transparent DB proxy
or multiwriter topology. **Do not enable built-in on an unproven stack or weaken
the guard.** Other versions and actual hosting still require proof before acceptance;
use existing-provider mode where unsupported. Arbitrary trusted PHP can exit,
change data or run its own external side effects. Native/other uncoordinated
creators can still race WP's non-unique email column. These are not global
uniqueness, DDoS, mailbox-verification or universal plugin-compatibility claims.

Owner review remains required for the administrator/Runner UX and operator guide,
all exposed signup-route protection, real native/security-provider policy, HTTPS/
mail/cache/DB/cron and shared-IP limits. Reconfirm after host/security changes or
backup restoration. The separate Pro classifier adapter and **SE-031.4 full
acceptance**, including the unchanged Pro History failure investigation, precede
a separately approved **Core 0.4.0 release-preparation** ticket. No settings tabs,
version/schema bump, merge, deployment, publication or release in this ticket.
