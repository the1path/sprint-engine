# SE-031.3.1 — Registration UX Polish

28 November 2026. [Issue #32](https://github.com/the1path/sprint-engine/issues/32),
[archived specification](../discovery/se-031-3-1-issue.md).
Fetched latest `main` and verified merged SE-031.3 at
`236d2b356d7ee83bf699157e55ceef0c37bf8fc5` before creating
`codex/se-031-3-1-registration-polish`. No worktree or local work was discarded.
Unreleased source; draft PR and owner review only. Core version **0.3.0**, schema
**2**, WP/PHP floors **6.0 / 8.0** unchanged. No Pro, dependency, schema, rewrite,
settings-tab, deployment, merge, publication or release changes.

Read with [accounts contracts](../participant-accounts.md),
[security guidance](../participant-registration-security.md),
[SE-031.3 validation](se-031-3-native-registration.md) and its
[database follow-up](se-031-3-database-compatibility.md).
The [sanitized results](se-031-3-1-results.json) separate executed evidence from
owner observations and outstanding failures.

## Owner-reported staging acceptance

The issue records successful staging observations for open Sprint signup, native
identity/authentication, direct canonical return, explicit start/resume, existing
sign-in, restricted denial and standard native registration closure. The
independent WooCommerce My Account registration form was visible when enabled.
These are **owner-reported observations**, not independent full acceptance.
The exact installed Core archive was not verified at that time. Visibility of
the Woo form is not evidence of an owner-submitted account or checkout. No site,
participant details, private extension source or personal screenshots are recorded.

## Changes and retained boundaries

New participant logins use native `remove_accents(..., 'en_US')`, strict
`sanitize_user`, lowercase ASCII components and dots. One usable component works;
all-unusable/non-Latin names use a compact random fallback. Every candidate uses
native validation and the native disallowed-login filter; denial stops immediately
without trying another identity to evade policy. At most 20 readable candidates
(base, `.2` through `.20`) are checked, trimming the base for the 60-character
limit. Fallback is `participant_` plus 12 random hex digits, at most three attempts.
Its 48 random bits avoid collisions; the login is not an authentication secret.

Selection and fresh collation-aware collision reads stay on the original owned
mysqli connection inside the registration mutex. The final native insertion
guard also rechecks the expected login/email after callbacks. It rejects changed
native login data and a collision introduced after WordPress's early check.
Native registration denial is never retried. WordPress's non-unique users columns
still cannot guarantee global uniqueness against independent uncoordinated creators.
There is no email-derived login, username input, existing-account rename or migration.
Native profile names/display name remain exact; email sign-in/reset remain native.
Readable logins may appear in WordPress public author URLs; operator guidance
explicitly treats supplied names/logins as public identifiers, not secrets.

The transaction/connection checks, bounded state, mutex timing, replay reservation,
generation, browser cookie/receipt, input/password validation, native `wp_signon`,
access checks and native-form closure architecture remain intact. No state-store
or authentication implementation was replaced for this polish.

Names share a desktop row and stack on narrow screens using registration-scoped
CSS. The approved neutral introduction and modest space before existing-account
sign-in inherit Runner branding. Error-summary focus has breathing room.
Only an actual registration form receives the small dependency-free visibility
script. A native keyboard action changes input type and its matching Show/Hide
password accessible label, retaining `aria-controls` and focus. It does not claim
an expanded panel or changing-label pressed state. It never reads the password
value or copies/stores/sends it; submission/page exit hides it again. No-JS keeps
an ordinary password input and hides the unusable optional button. Paste,
autocomplete, labels, DOM order and server error/password clearing remain native.
Privacy/recovery/return links and their existing validation are unchanged.

Administrator confirmation uses a native fieldset, legend, inline notice styling
and associated help. Checkbox default, settings capability/nonce, deliberate save,
prerequisites and generation rotation are unchanged. No-JS/mode disclosures remain.

## Executed environments and results

All sites/databases were marked, disposable and loopback-only, with synthetic
identities, blocked outbound HTTP/mail, locally captured mail, real local TLS and
two independent PHP workers per browser lane. Client-only certificate exceptions
and host resolution did not modify machine DNS/trust. Native mysqli/InnoDB was
used, not database emulation. Current installs use `/wordpress`; floor uses root.

| Check | MariaDB current | MariaDB exact floor | Actual MySQL current |
| --- | --- | --- | --- |
| WP / PHP | 7.1.5 / 8.3.36 | 6.0 / 8.0.30 | 7.1.5 / 8.3.36 |
| Database | 10.11.11 | 10.11.11 | 8.0.45 Community |
| Existing registration/security | 267 PASS | 267 PASS | 267 PASS |
| Restricted-grant storage | 4 PASS | 4 PASS | 4 PASS |
| Transaction/replay-state race | 48 PASS | 48 PASS | 48 PASS |
| New native username checks | 33 PASS | 33 PASS | 33 PASS |
| Access/canonical return | 289 PASS | 289 PASS | 289 PASS |
| Existing Runner integration incl. HTTP | 305 PASS | 305 PASS | CLI suite not rerun |
| Existing HTTPS browser/security | 98 PASS | 98 PASS | 98 PASS |
| New polish/concurrency/browser | 22 PASS | 22 PASS | 22 PASS |
| Additional body/mail/identity boundaries | 11 PASS | 11 PASS | 11 PASS |
| Existing browser accessibility | 6 PASS | 6 PASS | 6 PASS |

Username checks use actual ordinary/native users, case/diacritic comparisons on
the real users-column collation, misleading cache data, exact profile preservation,
Unicode-only account creation/sign-in, 60-character suffix truncation, twenty
occupied readable candidates and three actual random-candidate collisions. A
guarded test callback creates native collision fixtures before the authoritative
read; production randomness is not mocked or weakened. Native validation,
reserved names, login-filter modification, hook denial, final callback collision
and consumed replay are exercised. All pre-existing users/meta remain unchanged.

Independent browser workers submit the same names with distinct emails and get
separate readable logins and native Subscriber identities. Other existing tests
retain duplicate email/token/contention, connection loss, native hash/sign-on/reset,
MFA and exact canonical returns with no attempt until explicit Start. Selected
native MFA remains Two Factor 0.17.0 current and unchanged historical 0.9.1 floor;
the latter is compatibility evidence, not a production recommendation.

Polish browser checks cover 1280px desktop, 320/390px mobile, RTL, long simulated
translations with 200% CSS zoom, keyboard Space/Enter/focus, no visibility request,
password clearing, error-summary autofocus, corrected form to canonical Ready,
no-JS and explicit unchecked administrator confirmation with/without JS.
Chrome 158.0.8127.37 / Playwright 1.62.1 / Node 24.22.3 were used.

Unchanged older Pro passed **18** browser checks in both load orders on current
MariaDB/MySQL, including native sign-in and retained existing member data. Actual
WooCommerce 11.3.1 passed **15** checks on both: My Account account creation,
classic/block Store API HPOS checkout using synthetic offline COD, role/user ID
retention, historic guest-order non-linking, restricted denial and a friendly-login
collision with a real existing Woo customer whose identity stays unchanged.
The separately integrated current, unmodified adapter additionally passed **3**
focused open/signup/restricted browser checks on each current DB without the
diagnostic classifier. No private implementation was copied into Core.

PHPCS, changed PHP lint under 8.0/8.3, changed/browser JS syntax, **43 JS tests**,
release metadata, **13 asset checks** (including the new visibility fingerprint),
exact **92-file** runtime ZIP and diff checks pass. JS tests reject any password
value read/write or network/storage call and verify hide-on-submit/page-exit.
No dependency or version was added. General unrelated Core/Pro suites not listed
above were not rerun; older reports remain historical evidence.

## Fresh installation ZIP and additional compatibility finding

The candidate contains one `sprint-engine/` root and exactly 92 runtime files,
with source-byte equality and no docs/tests, local paths, secrets or dependencies.
The ZIP was extracted into fresh standalone native installations, not over an
existing Core fixture. Native activation/deactivation/retention/failure recovery
foundation **46** checks, registration **267**, database **48** and username **33**
checks passed from the extracted package on both WP 6.0/PHP 8.0/MariaDB 10.11.11
and WP 7.1.5/PHP 8.3/MySQL 8.0.45.

**Additional outstanding host/release investigation:** fresh WP **6.0** with MySQL
**8.0.45** refuses activation in the unchanged Core installer. WP 6.0 `dbDelta()`
continually proposes `bigint unsigned` → `bigint(20) unsigned`; the installer
requires an empty second dry-run plan and safely refuses. Actual activation from
an independent unchanged `main` runtime archive at `236d2b3` reproduces it.
No installer, schema or WordPress code/check was changed or bypassed. This is
separate from the passing MySQL current and MariaDB floor evidence and must be
resolved during SE-031.4 host/floor acceptance. Exact floor WP 6.0 is historical
compatibility testing, not a recommendation for production WordPress maintenance.

The initial installation harness treated WordPress's zero-exit `wp_die` as process
success; positive assertion counts and completion markers are now required. No
fresh-MySQL-floor activation or subsequent registration pass is claimed.
Other harness corrections kept username examples independent of prior accounts,
avoided a native locale-global collision, and regenerated browser fixtures after
destructive baseline suites. Final results above were actually executed.

For test use only, `dist/sprint-engine.zip` is rebuilt from the final committed
candidate and checked again. Exact source SHA, absolute local path, final archive
modification time and SHA-256 are reported with the draft PR handoff. `dist/` is
ignored and no ZIP is committed or published. Owner must rebuild from merged main
after approval; plugin version metadata alone does not identify an unreleased build.

## Visual review, owner retest and release gates

Synthetic screenshots were inspected: [desktop](images/se-031-3-1/desktop.png),
[390px](images/se-031-3-1/390-ltr-1.png), [320px](images/se-031-3-1/320-ltr-1.png),
[RTL/zoom](images/se-031-3-1/320-rtl-2.png), [errors](images/se-031-3-1/errors.png),
[confirmation](images/se-031-3-1/confirmation.png). Labels/controls remain readable
and unclipped, with no horizontal overflow. Stress translations/zoom are simulated,
not complete translations, assistive-device or actual browser 400% certification.

**Existing failures reproduced, unchanged:** Pro History stops after 26 passes at
`PRO-004 install/initialize leaves every existing response/Core row and table
definition unchanged.` Floor Journey fails `Native content autosave cannot change
Journey composition.` Both remain separate SE-031.4 acceptance investigations;
no assertion is suppressed and no full History/floor Journey pass is claimed.

Owner retest before acceptance:

1. Identify the candidate by source SHA/ZIP hash and verify actual host activation,
   especially the new exact-WP6/MySQL finding. Protect all account/login/reset routes.
2. Check the readable-name/public-author privacy choice and existing identity
   retention; create duplicate-name and international-name accounts and sign in by email.
3. Check desktop/mobile, keyboard/password manager/paste, visibility/error/no-JS,
   branding, privacy/recovery links and the explicit confirmation group.
4. Repeat native authentication/MFA and canonical return, explicit Start/resume,
   restricted denial, existing sign-in and native closure. Independently submit
   Woo accounts/checkout on the intended staging stack; owner form visibility alone
   is insufficient evidence of those submissions.
5. Resolve History, floor Journey, fresh-floor MySQL and remaining full SE-031.4
   hosting/security/commerce/regression/owner gates before separate release preparation.

Not rerun/certified: production hosting/CDN/TLS/mail/cron, sustained signup load,
other DB versions/drop-ins/pools/proxies/multiwriter setups, other security providers,
licensed/live gateways, all checkout UIs/themes, physical assistive technology,
actual password-manager products and complete protected-resource/History/Journey
acceptance. Settings tabs remain deferred. No merge, deployment or release.
