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
  WordPress instructions in a short purpose / numbered task / readiness outcome
  format, using native Paragraph, Heading and List blocks. The copy distinguishes the
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

## Initial implementation checks

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
[tutorial Step at 320px](images/se-030/tutorial-step-mobile.png) (updated by the
instructional-design pass below).

## Instructional-design polish — 24 November 2026

Owner-requested follow-up on the same feature branch and PR #22. Runtime changes
are limited to `src/Tutorial/Content.php`: installation, access, transactions,
schema, metadata, routes, templates, styles, versions and the marker contract are
unchanged. Existing installed tutorial posts and administrator edits are not
overwritten; the revised bundled copy is used for new explicit installations.

Every Step now starts with a brief purpose, followed by **Your task** and one
numbered native List, then **You’re ready to continue when…** and an observable
outcome. The first Step has three actions; the others have four. Optional short
supporting paragraphs retain the Core notes/media limits, ordinary Sprint access
rules and later tutorial reference instructions. All six Steps still guide the
administrator to build a separate Sprint from scratch in another tab. There is no
automatic assessment, new block, tutorial framework, external asset or Pro feature.

H3 section headings sit below the normal Runner's H2 Step title. List semantics,
reading order and heading hierarchy do not rely on colour or bold text alone.
There are no authored font, colour or spacing overrides: normal Runner branding
and native content styles supply the presentation. Content serialization uses the
installed native block capabilities for default classes and list-item structure:
WordPress 6.0 gets its inline List items, while current WordPress gets native
`core/list-item` children. Both formats are verified in their actual editors.

The copy is shorter in every Step, including the new headings. English word counts
use tag-separated, decoded text and PHP `str_word_count()`; test budgets preserve
the original upper bounds.

| Step | Previous | Polished |
| --- | ---: | ---: |
| Choose one useful outcome | 134 | 130 |
| Create your own Sprint | 171 | 153 |
| Add and order your Steps | 166 | 163 |
| Write content people can act on | 221 | 213 |
| Publish a ready, Launchable Sprint | 208 | 186 |
| Test the journey and make it yours | 232 | 203 |
| Total | 1,132 | 1,048 |

The integration assertions now check the published native block sequence, purpose
and outcome text, H3 section labels, ordered List/item counts and each Step's
original length budget. The existing lifecycle/access/rollback assertions still
run. Browser checks cover all six Steps at four widths, list bounds, heading
hierarchy/colour inheritance and 44px Runner controls; every Step is opened,
saved through the native editor and reopened with valid Paragraph/Heading/List
blocks and the same structure. Resize measurements wait two animation frames
for browser layout to settle; Settings initialization requests finish before
refresh/navigation so the test does not abort native Media Library requests.

| Follow-up check | Result |
| --- | --- |
| PHPCS, PHP lint for changed PHP, browser JavaScript syntax | PASS |
| Existing JavaScript regressions | PASS, 39 tests |
| Existing PHP regression matrix, WordPress 7.1.4 / PHP 8.3.35 / SQLite | PASS, 19 isolated suites, 1,851 assertions, no skips; includes authoring, Runner (305), REST, Dashboard, structure and release/asset checks |
| Expanded tutorial suite on current WordPress/PHP | PASS, 122 assertions |
| Expanded tutorial suite on WordPress 6.0 / PHP 8.0.30 / MariaDB 10.11.11 InnoDB | PASS, 122 assertions |
| WordPress 6.0 / PHP 8.0 Chromium editor and Runner journey | PASS, all six native editor save/reopen checks and four-width Runner checks |
| Current WordPress Chromium/Firefox/WebKit editor and Runner journeys | PASS, all six Steps at 1440/768/390/320px, list bounds and heading/branding checks; valid native editor save/reopen for every Step in all three engines |
| Local runtime package and exact allowlist verification | PASS, 75 files; no new runtime asset or dependency |

Reviewed Runner evidence: [desktop at 1440px](images/se-030/tutorial-step-desktop.png)
and [mobile at 320px](images/se-030/tutorial-step-mobile.png).
The owner-hosting, real-commerce, screen-reader and physical-device acceptance
limitations below remain. This content-only pass does not change PRO-018's separate
public-discovery integration responsibility or authorise merge/deploy/release.

## SE-030.1 — owner feedback correction, 24 November 2026

SE-030 merged in [PR #22](https://github.com/the1path/sprint-engine/pull/22) at
`1fde529ec9a4332d7f3bd999eae08cd97e3a62d3`; its first-install functionality was tested
and accepted. The owner then found the instructional journey inadequate in real
use. The earlier technical and word-count checks above established block validity,
access and Runner behavior; **they did not establish instructional quality or that
a first-time administrator could finish their own Sprint without extra help**.

This follow-up uses the owner's supplied conversation attachment,
`SE-030-1-tutorial-journey-rewrite.md`, as its specification. It is not a repository
ticket file. Work started from latest fetched `main` at the merge above on the
normal branch `codex/se-030-1-tutorial-journey-rewrite`, without a worktree.

### Delivered journey and scope

The approved welcome, six purposes/numbered tasks/outcomes and completion copy
replace the earlier bundled wording. One person plans an outcome, creates their
own Start screen, adds three draft Steps, opens the first Step and writes/publishes
all three, sets completion, publishes/marks Launchable, then experiences the
finished Sprint. The final tutorial Step explains Save & Exit and returning through
the Runner URL or My Sprints. The copy uses “Write down” and actual control labels;
it omits permission tests, restart, internal links/IDs and other QA objectives.

Each Step keeps the native Paragraph / H3 Heading / numbered List / H3 Heading /
Paragraph sequence. Task counts are 3/5/4/4/5/4. Links are generated from `admin_url()`
and Core Dashboard Routes, saved site-relative, escaped, and restricted to normal
anchor markup. Each has an explicit “opens in a new tab” label, `_blank` and
`noopener noreferrer`. No asset, Pro block, tutorial framework or branding override
is added. The four plain-text completion paragraphs celebrate the author's Sprint
before the understated Pro Coming Soon sentence, with no promotional button/link.

Only `src/Tutorial/Content.php` and the installed completion value in
`src/Tutorial/Installer.php` change at runtime. The latter replaces the old literal
with `Content::completion()`; the install sequence, transaction, identity, access,
onboarding, schema, progress and Core/PRO-018 marker contract are unchanged.
Plugin version/Stable tag stay **0.3.0**, schema **2**. Supporting changes are the
focused integration/browser tests, development/source-only release notes and this
existing validation record/evidence. No Core ticket Markdown file is added.

### Verified native navigation and minimal wording corrections

- Current WordPress 7.1.4/PHP 8.3.35 has a native **Meta Boxes** disclosure, initially
  closed for the new test administrator. Expanding it reveals **Sprint setup**,
  **Sprint structure** and **Runner URL**; it can cover the writing canvas and be
  collapsed while writing. WordPress 6.0/PHP 8.0.30 displays these panels directly
  below the editor, without that parent disclosure. The wording handles both.
- The menu is **Add New Sprint** on current WordPress and **Add Sprint** at the
  minimum; both use the verified `post-new.php?post_type=sprint_engine_sprint` route.
  WordPress's own welcome guide can cover a first editor visit; close it to write.
- The introduction goes beneath the title in the main block editor.
  **Start screen / Sprint introduction** is guidance in **Sprint setup**;
  **Completion screen** is inside **Sprint structure**. The native sidebar offers
  **Set featured image**, within **Featured image** when that panel is collapsed.
  No Excerpt instructions or mandatory image upload are needed.
- **New Step title → Add Step → Save order → Edit** leaves the author in Step 1's
  editor. **Step mode** offers **Content / Task**, with optional **Stage label** and
  **Estimated minutes**. **Publish** can require WordPress's second confirmation;
  **← Back to Sprint** returns to the parent's structure after each Step.
- A first saved draft can retain an empty **Slug**, and the server-rendered Runner
  URL panel can still ask for a save. The copy therefore promises the link after
  publication, with an explicit editor reload. After publishing/Launchable and
  reloading, **View Runner** is available. Saving/reloading after a slug edit is
  retained. No URL-generation behavior was changed to fit the prose.
- The optional 1600 × 700 (16:7) recommendation matches the unchanged Runner's
  computed 16:7 desktop / 16:10 mobile banner ratios and `object-fit: cover`.
  Keeping the subject centred is appropriate; the complete first build omits an
  image successfully.

### Journey evidence and automated validation

A manual browser walkthrough on WordPress 6.0/PHP 8.0 used a new administrator,
installed the tutorial explicitly, and kept it open while creating **Plan my next
week** in another tab. The author wrote the Start screen, added **List what needs
doing**, **Choose three priorities**, and **Make time for the first priority**,
saved their order and opened the first Step before continuing to tutorial Step 4.
Each Step received practical instructions, Task mode and publication through the
native confirmation. The author returned through Back to Sprint, wrote the
completion message, left both CTA fields empty, selected Launchable and published.
Reloading exposed View Runner. The finished Sprint showed the authored Start
screen, all three actions in order, saved/exited after the first action, resumed
at action 2 from My Sprints and reached its own completion message at 100%. Only
then was tutorial Step 6 completed, showing the new tutorial congratulations and
Coming Soon paragraphs. This demonstrates the actual new Sprint, beyond merely
advancing the tutorial's progress.

Current-version manual inspection verified the panels, sidebar, link opening,
first save, Step creation/order and opening the first editor. The in-app browser's
current Gutenberg iframe then appeared blank while surrounding controls worked;
that manual run did not complete. Standalone browser-driven native UI authoring
provides the full current-version evidence below; no product workaround was added.
The new `tutorial-build.cjs` performs all authoring through native controls and
checks the resulting Runner content/completion, including exit/resume. It uses
read-only editor state to wait for saves, rather than pre-creating the result via
REST or editor dispatches. Native editor initialization/welcome overlays are
awaited and dismissed before interacting.

| SE-030.1 check | Result |
| --- | --- |
| Existing PHP regressions, WordPress 7.1.4 / PHP 8.3.35 / SQLite, non-default prefix | PASS, 19 isolated suites / 1,851 assertions / no skips; includes authoring, structure, Runner (305), REST, Dashboard, progress/attempts, migration, branding and release/assets |
| Tutorial integration, current WordPress/PHP | PASS, 164 assertions; journey controls, native blocks/order, portable admin/home paths, plain completion, install choices/rollback/concurrency, access and retained authored completion |
| Tutorial integration, WordPress 6.0 / PHP 8.0.30 / MariaDB 10.11.11 InnoDB | PASS, 164 assertions |
| Core plus unchanged Pro policy | PASS, 6 assertions: real Pro entitlement cannot bypass tutorial access, REST or Core Dashboard privacy; administrator still allowed |
| Manual new-administrator build on minimum WordPress/PHP | PASS, published and completed the authored three-Step Sprint alongside the tutorial |
| Current native UI new-administrator builds | PASS, Chromium 158 / Firefox 153 / WebKit 26.5 each authored, published, resumed and completed a real three-Step Sprint; initially closed Meta Boxes were opened, and no image was required |
| Current Chromium 158 / Firefox 153 / WebKit 26.5 tutorial editor/Runner | PASS, all six Steps save/reopen as valid native blocks, four widths (1440/768/390/320), keyboard, normal branding, resume/restart and guest/non-admin privacy |
| Minimum WordPress/PHP Chromium editor/Runner | PASS, all six native Step editor save/reopen checks, four-width Runner journey, live links, resume/restart and guest/non-admin privacy |
| Actual `/academy/` WordPress installation, Chromium | PASS, admin/Create Sprint/My Sprints links open the correct subdirectory destination in a new tab and retain the tutorial tab; native block save/reopen and four-width journey checks also pass |
| Existing Runner browser regression | PASS, eight states/four widths, computed image crop/ratios, keyboard/focus/touch targets, native completion/CTA, independent users, exit/resume/restart |
| PHPCS, changed PHP lint, browser JS syntax | PASS |
| Existing JavaScript regression tests | PASS, 39 tests |
| Local package / exact runtime allowlist | PASS, 75 files; artifact stays ignored/local and is not published |

Revised-content visual evidence: [desktop](images/se-030-1/tutorial-step-desktop.png),
[320px mobile](images/se-030-1/tutorial-step-mobile.png),
[the authored Start screen](images/se-030-1/own-sprint-start.png),
[its completion](images/se-030-1/own-sprint-completion.png), and
[tutorial completion](images/se-030-1/tutorial-completion.png).

### Safe owner testing and human acceptance checklist

**Updating the plugin alone must not overwrite the tutorial already installed on
sprintenginepro.com.** There is no overwrite migration. Existing copy, authored
edits, completion, separately authored Steps and historical attempts remain owned
by the site. The owner site has not been changed by this work.

Prefer a fresh disposable WordPress site/database with the reviewed Core source.
Activate Core, follow Welcome and explicitly install the tutorial. To test on an
existing backed-up disposable/staging copy, first preserve a restorable database
backup and any authored tutorial content. Trash/restore reuses the old copy; it
does not load new bundled text. To get the replacement copy there, **explicitly
permanently delete the marked tutorial Sprint**, then choose Install Tutorial
Sprint in Settings. Existing policy retains its old Step posts and historical
attempts; do not expect those to disappear or silently delete/reassign them.
Review retained old Steps separately in Sprint Steps. Do not perform this test on
the owner's live site as part of this PR.

Owner acceptance remains a human decision; use the new tutorial alone:

1. Start as a first-time administrator. Read the welcome and write down one
   outcome and three action titles. Open WordPress admin using the tutorial link;
   the tutorial must remain open in its original tab.
2. Use Create a new Sprint or the native Sprints menu. Close the native welcome
   guide if shown. Write the title and introduction beneath it, find/open the
   panels and save a draft. Skip the optional image without being blocked.
3. Add the three planned titles in Sprint structure, save their order and select
   Edit beside the first Step. Remain in that editor when continuing the tutorial.
4. Write actionable content and what done looks like, choose Task or Content,
   publish/confirm and use Back to Sprint. Repeat until all three are published.
5. Set a meaningful completion message; leave the optional CTA fields blank or
   supply both. Select Launchable, publish/confirm, reload and find View Runner.
6. Experience the authored Start screen and actions. Save & Exit partway through,
   return from My Sprints, finish and recognise the completion message you wrote.
   Complete the tutorial only after this result; its final message should fit.
7. Record where extra explanation was needed, if anywhere. Confirm root and
   subdirectory links, narrow-screen readability and normal site Runner branding.
   Owner acceptance, actual hosting/plugin-stack checks, screen-reader and physical
   mobile-device testing remain outstanding; no release or rollout is authorised.

## Original SE-030 files and implementation boundaries

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
# Use another fresh activated database/manifest for the new-administrator build.
node tests/browser/tutorial-build.cjs tutorial-build-fixture.json
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
- Owner-led subject-specific tutorial acceptance, assistive technology/screen-reader
  review and native mobile-device testing. SE-030.1's manual first-build walkthrough
  and automated native authoring evidence are recorded above; they do not replace
  the owner's acceptance on the intended hosting/plugin stack.
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
