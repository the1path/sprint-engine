# SE-030 — Optional first-install Tutorial Sprint

Implementation and local validation: **24 November 2026**.
[Approved issue #21](https://github.com/the1path/sprint-engine/issues/21).
Normal feature branch `codex/se-030-first-install-tutorial`, based on latest fetched
`main` at `b46c068` (SE-029). No worktree was used. Plugin version/Stable tag remain
**0.3.0**, schema **2**, minimum WordPress **6.0**, minimum PHP **8.0**.

This is development source for a separately approved patch release. No merge,
deployment, WordPress.org/SVN publication or version bump forms part of this work.

## Delivered behavior

- Successful first site installation records a pending choice and shows a native
  administrator notice linking to **Sprints → Welcome**. There is no activation
  redirect and no activation-created content. The screen offers **Install Tutorial
  Sprint** and **I know how to use Sprint Engine**, with the approved visibility
  explanation. Failed first installation records retryable intent and offers no
  welcome until schema installation succeeds. Network-wide activation still fails.
- Per-site `sprint_engine_onboarding` records `installing`, `pending`, `skipped`,
  `existing` or `installed`. Existing version/schema markers, Sprint/Step posts or
  either owned table suppress fresh-install solicitation independently of a version
  change. Updates and reactivation retain a saved decision. Skip uses an atomic
  pending-to-skipped compare-and-set, so it cannot erase a concurrent Installed
  decision. Successful installation likewise uses an installing-to-pending
  compare-and-set so an activation retry with stale cache cannot reset a choice.
  All administrator choices require POST, `manage_options` and an action nonce.
- **Sprints → Settings** offers later opt-in, a canonical **Open Tutorial Sprint**
  link following the current saved slug, and an editor link. Repeated calls reuse
  the marked post. Explicit Trash restoration reuses it, restoring previous
  publication when appropriate. Permanent deletion permits a new explicit install.
  Neither restoration nor upgrades replace authored copy. Intentionally unready
  edited content is linked for editing and remains subject to normal readiness.
- Six original, bundled native Gutenberg Steps guide the administrator to define
  an outcome, create a **different** Sprint, add/order its Steps, author actionable
  content, publish/enable Launchable, and test the Runner. Each provides concrete
  WordPress instructions and a finish condition. The copy distinguishes the
  Sprint introduction from Step work, describes Core's notes/media limits, and
  makes no promise to validate the other Sprint. No external content, screenshot,
  hard-coded site URL/ID, Pro block or commerce dependency is shipped.
- The installed Sprint and six Steps are published and passwordless; Quick Add,
  `StructureManager::save_step()`, `Meta::save()` and Availability establish a valid
  linear, Launchable Sprint. The installer never writes derived order/start/next
  metadata by hand. Native editor saves/reopen and all ordinary progress rules work.
- The protected marker restricts Core access **after external access filters**.
  Signed-in administrators can use the example with Pro active; anonymous users,
  subscribers and editors cannot read its Runner, discover it on Core My Sprints,
  or mutate its REST progress. The native Sprint list and persistent classic/native
  Gutenberg editor notices identify only marked content.

The shared [marker contract](../tutorial-marker-contract.md) specifies exact
presence semantics and the separate PRO-018 discovery obligations. Planned public
copy is in [release-notes-next.md](../release-notes-next.md); the published
`readme.txt` release history/Stable tag were not changed to advertise unshipped code.

## Transaction and recovery guarantees

`sprint_engine_tutorial_id` is a non-autoloaded per-site option initialized to zero.
Its unique WordPress option-name row is the permanent mutex, **not** an expiring
lease. Installation checks transaction support for WordPress `posts`, `postmeta`
and `options`, starts one transaction, takes a no-op UPDATE lock on that row, and
reads the identity fresh after obtaining the lock. No custom table/schema is added.

The extracted internal `Content\Transaction` lets existing StructureManager
operations join that transaction rather than issuing nested START/COMMIT commands.
A composed error marks the unit failed. Normal StructureManager calls still own
one transaction and retain their two-content-table engine requirement, Sprint-row
locking, validation and error envelope. Tutorial installation alone also requires
transactional options. No progress service or attempt creation is involved.

The Sprint is initially a marked draft; Steps are created through Quick Add and
saved/published normally. Launchable is validated after all six Steps are ready;
the Sprint is published and Availability rechecked before the single COMMIT.
Posts, Step metadata, protected marker, installation pointer and Installed decision
commit together. Any reported write/validation error, caught exception or failed
COMMIT attempts ROLLBACK. Touched post/meta/query caches and relevant option caches
are invalidated after the boundary, including rollback. The initial zero lock row
can remain after failure, but there is no persisted partial tutorial to advertise.

Tests fail after multiple Step writes and fail the final COMMIT: both leave no
Sprint, Step, tutorial metadata or Installed decision. A subsequent pair of real
independent PHP workers returns the same one Sprint/six-Step installation. If a
connection fails after an uncertain commit, a retry resolves the committed marked
identity instead of creating another copy. Existing canonical tagged content is
adopted/reused without rewriting edits; conflicting live pointers, multiple tagged
Sprints or noncanonical marker rows are refused with a safe retry/admin message.

As with existing StructureManager, trusted WordPress/plugin callbacks run during
writes. Their emails, remote calls, external storage and independent transactions
cannot be rolled back by Core; callbacks must not commit or replace Core's database
transaction. No such external effects are introduced by this feature. Unsupported
engines are rejected; Core never converts WordPress table engines.

Permanent Sprint deletion retains separately authored Step posts and historical
progress under the existing data-retention policy. Reinstallation does not reuse
those historical attempts or silently delete old Steps. Native Sprint Steps can
be used to manage retained content. Trash restoration retains the original Steps.

## Executed checks

All database tests used explicitly disposable installations and non-default table
prefixes. Warnings/notices in integration suites are promoted to exceptions.
The intentional failed-schema test emits Core's generic installation diagnostic.

| Check | Result |
| --- | --- |
| PHPCS using repository WordPress rules | PASS, zero findings |
| PHP lint: runtime/templates/tests and plugin entry/uninstall | PASS, 76 files |
| JavaScript syntax for new editor/browser scripts | PASS |
| Existing Node tests | PASS, 39 tests |
| Release metadata / asset regression | PASS, 48 / 12 assertions |
| Current WordPress 7.1.4, PHP 8.3.35, official SQLite integration | PASS, 20 isolated PHP suites, 1,954 assertions, no skips |
| Final SE-030 tutorial acceptance after stale-activation fix | PASS, 104 assertions (rerun separately after the full matrix) |
| WordPress 6.0, PHP 8.0.30, MariaDB 10.11.11/InnoDB | PASS, same 104 tutorial assertions, including independent concurrent installers |
| Actual unchanged Pro 0.1.0 installation, Core 7.1.4 test site | PASS, 6 access/Runner/Dashboard/REST assertions |
| Tutorial native forms/editor/Runner browser journeys | PASS, Chromium, Firefox and WebKit; all six Steps at four widths |
| Existing SE-029 heading/Accordion browser matrix | PASS, 72 engine/theme/surface/viewport cases, LTR/RTL and 200% text reflow; read-only snapshot verification passed |
| Runtime packaging plus byte-for-byte allowlist verification | PASS, 75 runtime files, including all bundled tutorial source and editor asset |

The current PHP matrix consists of release, assets, foundation, tutorial,
authoring, structure, progress, migration, attempts, mid-sprint-restart,
step-change-recovery, attempt-history, Runner, Dashboard, Runner admin, REST,
branding, polish, validation and prefixing. Each starts from its own fresh
database; foundation/tutorial start with Core inactive. Runner/Dashboard/REST use
a loopback HTTP server for real headers/redirects/authentication. Foundation checks
network-activation refusal and lifecycle retention. Runner's 305 assertions include
SE-028 hook eligibility/payload and preview exclusion. Structure's 110 assertions
were also rerun after the final transaction error-envelope compatibility adjustment.

Tutorial assertions cover failed first activation/retry, both choices, GET/nonce/
capability rejection, stale-cache activation retry, failed partial writes/COMMIT, concurrent and repeated installs,
canonical publication/content, conflicting identity/duplicate markers, original
and second administrator identities, anonymous/two subscribers/editor, permissive
and denying last-priority access filters, Dashboard-filter reinsertion, direct REST
start/complete/restart, no denied progress writes, native editor REST reopen,
six-Step completion and retained restart attempts, rename, content edit, update,
reactivation, empty/false/malformed/duplicate markers, Trash restoration and deletion/
reinstallation without historical-data changes.

The Pro test activates/boots an unchanged sandbox Pro installation and registers
its actual `AccessPolicy` with an actual `EntitlementManager` and a granting
`EntitlementProvider` test double. It proves that Pro grants the member before
Core's final veto, then checks Runner, Dashboard and all three REST operations,
plus administrator access. It does not claim a real payment/subscription transaction.
The tested Pro AccessPolicy SHA-256 is
`56a94e41a9473f10c91ccf0197a8e8bb30c8a77156955209fb722b6c7e8e3996`.

### Browser evidence

The tutorial journey uses actual WordPress admin forms, cookie sessions, native
Gutenberg editors and member REST/Runner navigation. Three separate administrators
and a subscriber exercise welcome/skip/later install, stable Settings links,
native non-dismissible notice, valid editable blocks, six-Step keyboard completion,
Dashboard resume, restart, guest redirect, member HTTP 403 and REST denial. All six
Steps are checked at 1440, 768, 390 and 320 CSS pixels with no horizontal overflow
and 44px Runner controls. Browser versions: Chrome/Chromium **158.0.8127.37**,
Firefox **153.0**, WebKit **26.5**.

Browser fixture update transients prevent unrelated WordPress update-check traffic
in the offline sandbox. No update behavior is changed in shipped Core code.

Reviewed screenshots: [native Welcome choices](images/se-030/welcome.png) and
[tutorial Step at 320px](images/se-030/tutorial-step-mobile.png).

## Files and implementation boundaries

| Files | Change |
| --- | --- |
| `src/Tutorial/Content.php`, `Installer.php`, `Marker.php`, `Onboarding.php` | Bundled copy, atomic opt-in/recovery, protected identity, native administrator controls |
| `src/Content/Transaction.php`, `StructureManager.php` | Internal transaction composition and retained structure/error behavior |
| `src/Database/Installer.php`, `src/Plugin.php`, `src/Settings/SettingsPage.php` | Lifecycle decision capture and screen registration |
| `src/Access/AccessManager.php`, `src/Dashboard/Dashboard.php` | Final tutorial policy and post-filter discovery guard |
| `assets/js/tutorial-editor.js` | Non-dismissible native Gutenberg notice, scoped to marked Sprint editors |
| `tests/integration/tutorial.php`, `tutorial-pro.php`, `tests/browser/tutorial-fixture.php`, `tutorial.cjs` | Reproducible Core/Pro/browser acceptance |
| `composer.json`, `tests/package.php` | Dedicated fresh-install test command and explicit tutorial packaging assertions |
| `README.md`, `docs/README.md`, `docs/development.md`, marker contract, planned release notes, this record/images | Source-only documentation and owner handoff |

No Pro source, Runner stylesheet/template, progress schema, version constant,
published readme metadata, unrelated site content, deployment or publication files
are changed.

## Reproduce

Use a new disposable WordPress database with administrator ID 1 and a non-default
prefix for **each** integration suite. Copy current Core runtime into its plugins
directory. `tutorial.php` must start without any Core installation evidence and
with the plugin inactive; it intentionally tests failed initial installation.

```sh
composer check
node --test tests/js/*.test.cjs
SE_TEST_WP_ROOT=/disposable/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/tutorial.php
php tools/package.php
php tests/package.php
php tests/release.php
```

`composer test:tutorial` aliases the tutorial command when its environment is set;
it is separate from `composer test` so it does not run after foundation on an
already-initialized database. For concurrent workers, configure PHP so the worker
command can enable the applicable SQLite/MySQL extensions without loading them
twice. Run optional `tests/integration/tutorial-pro.php` on a separate active Core
disposable site containing unchanged Pro. Never point these suites at a real site.

For browser checks, start a loopback server serving that disposable WordPress,
set `SE_TEST_HTTP_BASE`, activate Core only, and run:

```sh
php tests/browser/tutorial-fixture.php > tutorial-fixture.json
node tests/browser/tutorial.cjs tutorial-fixture.json
```

Set `NODE_PATH` to a local Playwright install and `SE_BROWSER_OUTPUT` to an ignored
evidence directory; provide Chromium/Chrome, Firefox and WebKit test binaries.
Browser fixture manifests contain disposable login cookies and must not be committed.
The existing `blocks-fixture.php` / `blocks.cjs` / fixture `verify` workflow checks
SE-029's heading/Accordion matrix without changing content or progress.

## NOT RUN / separate acceptance

- Owner staging on the actual hosting stack, cache/CDN and installed plugin set.
- PRO-018's public Directory/search/count, Collection and Journey non-disclosure
  changes. That Pro-side integration is a **combined-rollout dependency**; it is
  not implemented or certified in this Core ticket.
- Actual WooCommerce purchase/subscription/membership transactions. Core's final
  veto is tested with actual Pro policy and a deterministic entitlement provider.
- A human building their own subject-specific Sprint entirely by hand, assistive
  technology/screen-reader review, and native mobile-device testing. Automated
  authoring/Runner/native-block and desktop-engine responsive evidence is separate.
- A complete Core regression/browser matrix on every PHP/WordPress combination.
  The floor pair ran SE-030's full acceptance suite on real InnoDB; the full matrix
  and three-browser presentation/journey checks ran on WordPress 7.1.4/PHP 8.3.
- Release, deployment and publication, as expressly excluded by the issue.

## Owner staging checklist

1. Review this branch and the marker contract alongside PRO-018. Keep the public
   tutorial rollout gated until Pro's public discovery exclusions are verified.
2. On a backed-up staging clone with existing members, update Core: no sample
   content or first-install nag. Confirm an ordinary member's existing Sprint,
   progress, attempt history and unmarked Directory/Collection/Journey behavior.
3. On a fresh site, activate individually and follow the welcome notice. Try Skip;
   confirm zero content. Install explicitly through Settings, refresh/use two tabs,
   and confirm one published, Launchable six-Step example. Network activation must
   still refuse without provisioning.
4. As an administrator, keep the example open and build a separate Sprint through
   the six actions. Test publish/readiness, native editor reopen, save/resume,
   final completion/restart, narrow screen and keyboard. Edit/rename the tutorial,
   reactivate/update, and confirm the copy and Open link remain correct.
5. With anonymous, non-entitled and entitled member sessions, request the exact
   tutorial URL/Step IDs and mutation endpoints. Confirm no content/progress or
   Core Dashboard listing; with PRO-018, confirm no public search/card/count or
   indirect Collection/Journey disclosure. Administrator access must still work.
6. Trash/restore and permanently delete/reinstall explicitly. Confirm old content
   and attempts follow the documented retention behavior, no duplicate live example,
   and cache bypass/private headers work on the actual host.
7. Record owner acceptance. Authorise versioning/release and any later deployment
   separately; this branch itself performs none of those operations.
