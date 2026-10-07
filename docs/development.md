# Sprint Engine development guide

This guide documents Core **0.2.0**, schema **2**. Start with the
[documentation index](README.md) for architecture and validation records.
The [contribution guide](../CONTRIBUTING.md) covers focused issues and pull requests.

## Author a linear Sprint

The main Sprint editor content is the introduction shown **before Start Sprint**;
an excerpt, when supplied, takes precedence. Your first Step normally contains
the first piece of work, not another welcome. **Sprint setup** explains this and
shows a saved-state checklist; save and reload to refresh it.

1. Create and save a Sprint using its native title, overview, excerpt and image.
   Sprint Structure is visible but disabled until saved. In Gutenberg, the first
   successful explicit save activates it automatically without a browser refresh.
2. In **Sprint structure**, enter a title and click **Add Step**. Each new Step
   is a draft assigned to this Sprint and appended to its saved order. Repeat
   to create the whole Sprint without leaving the editor.
3. Drag the handles or use **Move Step up/down**, then click **Save order**.
   Positions, next-Step links, the start Step and the terminal Step are calculated
   and saved together. The list shows stage/mode, Start/Final and calculated links.
   Save pending reorder changes before using Quick Add or Trash.
4. Use each Step's **Edit** link to add Gutenberg content, stage, optional minutes
   and Content/Task mode. Position and Next Step are read-only. Changing the parent
   Sprint in this metabox appends the Step to the destination and rebuilds both
   lists. Reload after saving to see the calculated values. Publish every Step;
   the Structure list shows WordPress status badges and one unpublished count.
5. Enable **Launchable** and save the Sprint when its complete chain is valid and
   every associated active Step is Published. Draft, Pending, Private and Scheduled
   Steps can be ordered but block launch and member access until published.
   The start Step is automatically the first Step; there is no manual selector.

Stage Label is free text with optional phase suggestions. Save a Step, then use
**Back to Sprint** to return to its parent editor. The native **Sprint Steps**
list shows Parent Sprint and Stage, and can be filtered by Sprint.

Sprint **Estimated duration** accepts a positive value with up to two decimals
and Minutes, Hours or Days. It is presentation metadata, not an active-work-time
conversion. Existing minute values remain minutes until edited. Clearing the
new field also retires old Sprint minute metadata. Step estimates use positive
whole minutes; blank removes the estimate. Invalid input such as `4-6`, `60 mins`
or zero retains the previous saved estimate and produces a validation notice.

Each Structure row offers **Trash** as a red text action, with confirmation. It moves the Step to
WordPress Trash and rebuilds the remaining order, start and final links together.
The last removal also clears Launchable. Restoring the Step retains its authored
content and images but leaves it unassigned; progress history is never deleted.
WordPress Trash must be enabled. Save any pending reorder before removing a Step.

Use native **Featured Image** on either editor: the Sprint image appears on Start
and Completion; a Step image appears only on that Step. A Step without an image
has no banner or Sprint-image fallback. Banners respect attachment alt text and
Runner corners, crop to 16:7 (16:10 on narrow screens), and require no custom upload
control. See [se-010 validation](validation/se-010.md) for validation and target host checks.

In **Completion screen**, optionally enter a plain multiline message and a CTA
label plus full HTTP(S) URL. Both CTA fields are required to show the button.
Clear both to remove it. An incomplete or invalid replacement retains the previous
CTA and produces a validation notice.
Blank messages retain “You have completed this Sprint. Your progress is saved.”
Completion text does not execute HTML, shortcodes or embeds. The CTA is a normal
link; Back to My Sprints stays available and progress remains completed.
See [se-009 validation](validation/se-009.md) for verification and deferred product ideas.

Structure operations save immediately through authenticated admin AJAX, separately
from Gutenberg's normal content/metabox save. Watch the local success/error message.
Stale browser lists, omitted/duplicate/foreign Steps and invalid IDs are rejected.
After an error or uncertain connection failure, reload before retrying. Deleted
or Steps trashed outside the Structure Manager may leave stale links; review the remaining list and **Save order**
to repair it. An empty saved order clears start and launchable.

Invalid duration, Step minutes and CTA input now shows inline feedback immediately.
Gutenberg manual saving is locked until all known invalid fields are corrected,
with one editor notice and accessible field descriptions. Open Meta Boxes to see
the affected controls. Classic Editor uses the same validation with a submit guard.
Server validation still retains previous metadata when JavaScript is bypassed;
the next visible editor load maps that error to its field. Invalid native REST
metadata requests receive HTTP 400 before content or metadata is written.
See [se-010-2 validation](validation/se-010-2.md) for feedback behaviour, verification and remaining target host checks, and [se-010-1 validation](validation/se-010-1.md) for the server rules.
Invalid branding replacements also retain previous safe values
and show Settings API errors; only a valid zero intentionally removes a logo.
Moving a Step through its metabox repairs the source and destination in one
transaction. Removing the last Step clears the source Sprint's launchable flag.

The protected `_sprint_engine_*` metadata keys and representation decisions are documented
in [se-002 validation](validation/se-002.md), with workflow changes in [se-002-1 validation](validation/se-002-1.md).
Native REST metadata remains available in `context=edit` to administrators for
compatibility. PHP linear-authoring callers should use `Content\StructureManager`
(`apply_linear_order`, `quick_add`, `save_step`). Order is the authoring input;
explicit position/next/start metadata remains the stored representation.
`Content\Meta::save()` and the native editor REST API retain their SE-002 field
validation and require a complete ordered chain with every active Step Published
when setting launchable. Changing a Step back to an unpublished status blocks
Runner, Dashboard and member REST operations without deleting attempts/progress;
republishing restores availability. Publication changes do not alter structure.
Direct WordPress metadata calls remain trusted persistence APIs, not a relationship
validation boundary. Member writes use the separate SE-005 routes.

## Internal progress service

After WordPress loads the plugin, instantiate `ThePath\SprintEngine\Progress\ProgressService`.
Its methods return canonical state arrays or a safe `WP_Error`:

```php
$progress = new \ThePath\SprintEngine\Progress\ProgressService();
$state = $progress->start_sprint( $user_id, $sprint_id );
$state = $progress->get_state( $user_id, $sprint_id );
$state = $progress->complete_step( $user_id, $sprint_id, $step_id );
$state = $progress->get_progress( $user_id, $sprint_id );
$state = $progress->resume_sprint( $user_id, $sprint_id );
$state = $progress->restart_sprint( $user_id, $sprint_id );
```

This is an internal application API. Member callers must authenticate,
authorize through AccessManager and bind the user ID to the authenticated member
before invoking it. SE-003 checks data integrity and existence, not entitlements.

Start requires a launchable, valid linear Sprint. Repeated starts and completed
Step retries preserve progress/timestamps and do not repeat transition hooks.
Navigation follows explicit links. Reads do not create or repair progress;
resume preserves the stored current Step or repairs a stale pointer against a
valid remaining chain. Broken links require an author to review and Save order.
Removed-Step history is retained. Completed Sprints can be restarted explicitly.

State contains status, Sprint/current Step IDs, attempt_id and attempt_number,
completed/total valid Step counts,
percentage and UTC start/activity/completion times. The service owns its database
transaction; do not call it inside an existing transaction. It requires InnoDB
content and progress tables on MySQL/MariaDB (official SQLite integration is
supported for local checks), serializes on the Sprint row, and fires the four
specified lifecycle actions only after commit. See [se-003 validation](validation/se-003.md)
for API behaviour, recovery limits, concurrency and event-delivery assumptions.

## Attempts and restart (SE-012)

Each enrolment row is a stable attempt ID. The highest positive attempt_number
for a user/Sprint is the canonical runtime attempt; every Step query is scoped to
that attempt. Schema 2 adds attempt_number with DEFAULT 1 to both tables and
replaces lifetime unique keys with attempt-aware keys. Existing progress becomes
Attempt 1 without resetting IDs, pointers, status or timestamps. Installation
verifies schema and indexes before advancing the version; failed/partial DDL can
be retried and progress operations fail safely while installation is incomplete.

The corrected pre-release schema-1 baseline uses `sprint_engine_enrolments` and
`sprint_engine_step_progress` under the site's database prefix. These corrected
tables upgrade in place. Existing pre-release corrected-0.1 installations remain
supported by the schema-2 migration. Obsolete private `se_*` tables are not migrated.
Prior manual MySQL and lifecycle evidence is recorded in SE-012/SE-013.
That clean-install evidence does not establish real-backend schema-1 migration coverage.

Start stays idempotent even after completion. Restart is a separate operation:
POST /wp-json/sprint-engine/v1/sprints/{id}/restart, authenticated with the REST
nonce and authorized through AccessManager. A completed attempt creates Attempt
N+1 under the existing Sprint lock, immediately at the current first Step with
zero completions. Previous attempts stay intact. Retried restart during an active
attempt returns its unchanged state and publishes no additional events.

The completed Runner retains its message, optional CTA and Back to My Sprints, with a
secondary Restart Sprint button. Native confirmation explains retained history;
cancel sends no request. Success reloads the same canonical URL. Start, Complete
and Restart responses include attempt_id and attempt_number, allocated server-side.
The four lifecycle actions keep their leading user/Sprint(/Step) arguments and
append attempt_id, attempt_number after successful commit. Existing listeners
accepting fewer arguments remain compatible.

In-progress Start Over, history UI, content snapshots and branching
are deferred. No attempt deletion, new status or client-selected attempt exists.

## My Sprints member Dashboard (SE-013)

The built-in core/free Dashboard is at `/sprint-engine/dashboard/` and needs no
WordPress Page. Sprints → Settings shows its canonical link. Anonymous visitors
go to WordPress login and return here. The standalone page shares Runner branding.
Save & Exit returns to My Sprints without a write; Start and Completion offer
Back to My Sprints. The author's completion CTA stays separate.

Only published, password-free, launchable, structurally valid Sprints accepted by
Runner Availability and the current member's AccessManager appear. In Progress,
Available and Completed sections appear in that order when populated. Cards show
authored excerpts (no generated descriptions), optional featured images and duration.
Continue opens saved progress. Start opens the Runner overview without enrolling.
Completed cards can reopen completion or restart after explicit confirmation using
the existing authenticated REST nonce-protected endpoint. Prior attempts are kept.

Viewing My Sprints calls ProgressService::get_state() once per accessible Sprint;
it never resumes/repairs or changes progress. This intentionally favours canonical
state correctness over batch queries. Any unsafe state read gives a generic 503.
The latest attempt determines classification; historical attempts are never shown.
In Progress sorts by activity descending, Completed by completion descending,
Available by title A–Z; all ties use title then Sprint ID. Invalid/null UTC times
sort last. The Dashboard introduces no new stored personal data or settings.

The `sprint_engine/dashboard_items` filter receives a flat list of authorized
normalized items and the current user ID, after all eligibility and canonical state
checks. Each item contains sprint_id (int), title/excerpt (plain strings), runner_url
(canonical URL), featured_image (core attachment HTML or empty), duration (the
Meta::sprint_duration array, empty when absent), state (not_started/in_progress/completed),
completed_steps/total_steps (int), percentage (number), started_at/last_activity_at/
completed_at (nullable UTC datetime strings), attempt_id/attempt_number (nullable
ints, internal only). Trusted extensions must preserve this shape and member access;
the core groups/sorts filtered items. This is not a public member REST read API.

The `sprint_engine/dashboard_template` filter receives the default template path
and authorized context (state/status/sections, or generic error). Paths use the same
Runner safety checks: readable local PHP under plugin/mu-plugin/theme code, with
realpath containment and no wrappers/uploads/null bytes. No other Pro API is added.

Responses set DONOTCACHEPAGE, private/no-store/no-cache and noindex/nofollow headers
and robots metadata. Configure upstream caches to bypass the Dashboard route.
Upgrading a corrected pre-release 0.1.0 installation to 0.2.0 registers both member
routes and softly refreshes rewrite rules once after successful installation.
No Permalinks save or reactivation is required. Failed installation leaves rewrites
unchanged; ordinary requests do
not flush them. Version remains 0.2.0/schema 2.
See [SE-013 validation](validation/se-013.md) for evidence and limitations.

## Sprint Runner

Publish a valid, launchable Sprint and visit `/sprint/{sprint-slug}/` using
WordPress pretty permalinks. Logged-out visitors go to WordPress login with this
URL as their return destination. Logged-in users see Ready to start, their one
canonical current Step, or Sprint complete. Viewing Ready to start creates no
progress. Returning in progress invokes the existing service's resume/recovery
operation. Start Sprint and Complete & Continue use authenticated POST requests;
successful writes reload this same canonical URL. JavaScript is required for writes.
See [se-005 validation](validation/se-005.md) for routes, responses, security and deployment checks.

The plugin owns the template and responsive stylesheet; it uses normal WordPress
content filters for Gutenberg and native audio. Member responses send private,
no-store cache headers. Configure any upstream cache to bypass `/sprint/*`.
Member REST responses, including authentication and validation errors, also send
private/no-store headers. Upstream caches must respect these headers or bypass
the Sprint Engine REST namespace; plugin code cannot configure the host/CDN.

Plugin CSS/JS URLs use `Assets::version()` (plugin version plus SHA-256 of file
bytes). Changed assets get new URLs even when deployment preserves timestamps;
unchanged files remain cacheable. This applies to Runner and Sprint admin assets,
including packaged installations. If a file cannot be read, the plugin version
is the safe fallback. Deploy PHP/templates/assets together, and verify asset
requests return CSS/JavaScript rather than a cached error page. After deployment,
compare the `ver` query parameter and computed button styles in a private browser.
The fallback cannot fingerprint an unreadable file; correct file permissions.

**Existing schema-1 development installations:** a successful plugin version upgrade
registers both Runner and Dashboard routes before a one-time soft rewrite flush.
Fresh activation also registers both before flushing. No manual Permalinks save or reactivation is
needed for the corrected pre-release 0.1.0 → 0.2.0 upgrade, and ordinary requests
do not flush rewrites.

Access integrations can supply an `AccessProvider` to `AccessManager` or use
`sprint_engine/user_can_access_sprint` with decision, user ID and Sprint ID.
`sprint_engine/runner_template` receives the default path and authorized context;
replacements must be readable PHP files under installed plugin/theme directories.
See [se-004 validation](validation/se-004.md) for the context/API contract and remaining checks.

## Runner Branding

Administrators can open **Sprints → Settings → Runner Branding** to choose an
optional Media Library logo, primary/background/surface/text/muted colours and
Square, Subtle, Soft or Rounded corners. The live preview updates before saving.
Branding is **site-wide for every Sprint**. Primary-button text contrast is
automatic by default. Optional **Primary button text colour** accepts a six-digit
hex value; clear it to restore Automatic. A preview warning flags poor button or
text/background contrast without blocking saving. Arbitrary colours and authored block
colours still need an accessibility review.

The default Runner appearance is the default, with no saved option required.
Save applies on the next Runner request. **Reset to defaults** deletes only
`sprint_engine_runner_branding`; it never changes Sprint content or progress.
No custom CSS, external fonts, per-Sprint themes or schema migration are added.
See [se-008 validation](validation/se-008.md) for schema, CSS properties, validation and the
remaining target host deployment checks.

## Compatibility decisions and limitations

- Release minimums: PHP 8.0+ and WordPress 6.0+, with the repository readme recording Tested up to WordPress 7.1.
  PHP 8 string APIs are used at runtime. See [SE-011 validation](validation/se-011.md) for compatibility
  evidence and target-host checks; metadata alone is not runtime test evidence.
- Administrators author content using existing `manage_options` capabilities.
  Custom author roles/capabilities are deferred until explicitly specified.
- Activate per site. Network-wide activation is rejected with an actionable
  message; automatic multisite provisioning is not included in Core 0.2.0.
- Runner requires pretty permalinks and a published, launchable, valid Sprint.
- Structure writes require transactional WordPress `posts` and `postmeta` tables
  (InnoDB on MySQL/MariaDB). Unsupported storage engines are rejected before writes;
  the plugin does not convert or change core table schemas. The official SQLite
  integration is used for local testing. Do not invoke structure operations inside
  an existing database transaction; they own their transaction boundary.
- Distribution licence: GPLv2 or later; canonical GPL version 2 text is in LICENSE.

## Developer installation and packaging

Copy the plugin source into `wp-content/plugins/sprint-engine`, then activate
**Sprint Engine**. Composer dependencies are development tools only; the plugin
does not need them at runtime.

To build and verify an uploadable archive, enable PHP's ZIP extension and run:

```sh
php tools/package.php
php tests/package.php
php tests/release.php
```

The archive is `dist/sprint-engine.zip`. Generated ZIPs, dependencies and disposable
test installations are ignored and must not be committed.

## Verification

```sh
composer install
composer check
find src -name '*.php' -exec php -l {} \;
php -l sprint-engine.php
php -l uninstall.php
```

The class filenames intentionally match the specification; PHPCS excludes only
WordPress's filename convention. Runtime code otherwise uses WordPress standards.

For integration checks, first install a **fresh disposable** WordPress site with
a non-default database prefix and administrator user ID 1. Copy this plugin into
its plugins directory, but leave it inactive. Then run:

```sh
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/foundation.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/authoring.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/structure.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/progress.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/attempts.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/migration.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/runner.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/dashboard.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/runner-admin.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/rest.php
SE_TEST_WP_ROOT=/path/to/wordpress SE_TEST_DISPOSABLE=yes php tests/integration/prefixing.php
node --check assets/js/runner.js
node --test tests/js/*.test.cjs
```

**Never point this test at a real site.** It creates content/users, deliberately
drops the Step progress table, and simulates a database failure. Start with a
fresh database for each test run. It supports WordPress's normal MySQL/MariaDB
connection and the official SQLite integration for a local smoke test.

Checks cover clean activation, admin/editor registration, private native REST
reads, table writes/unique constraints, repeatable `dbDelta`, version upgrades,
database failure/recovery, downgrade refusal, and data retention. PHP warnings
and notices during plugin testing are promoted to exceptions.

`composer test` runs release metadata, the asset regression check and the WordPress suites
when these environment variables are set.
The authoring suite covers native REST creation of a five-Step chain, metadata
round trips, validation, metabox nonce/capability checks, warnings, and Gutenberg
registration/content preservation. The structure suite exercises the PHP service,
authenticated AJAX, exact membership checks, stale edits, transaction rollback,
parent changes, read-only controls and editor asset scoping. The progress suite
covers repositories, the complete linear lifecycle, idempotency, UTC, stale-state
recovery, rollback, hooks and duplicate requests from independent PHP processes.
It requires PHP CLI `proc_open` for the concurrency checks.

The Runner suite covers routing, access, state coordination, content rendering,
template validation, assets and lifecycle retention. Optionally set
`SE_TEST_HTTP_BASE` to a running loopback server serving the same disposable site
to check actual login redirects, status/cache headers and all three Runner states.

See [se-001 validation](validation/se-001.md), [se-002 validation](validation/se-002.md),
[se-002-1 validation](validation/se-002-1.md), [se-003 validation](validation/se-003.md) and
[se-004 validation](validation/se-004.md) for executed and
remaining checks.
