# SE-026 — Sprint Engine Core 0.2.3 release review

Reviewed on 12 November 2026.

## Recommendation

**NO-GO for publication as 0.2.3 until the public API and version decision is made.**
The candidate installs, upgrades from the published 0.2.2 package, and preserves
existing records. SE-019 breaks previously valid Complete Step and Restart
requests on the existing REST v1 routes. Direct PHP integrations using the old
arguments also receive an error. The new attempt check prevents actions from
stale tabs writing to a replacement attempt. Keep that check.

## Release identity

| Item | Verified identity |
| --- | --- |
| Published release | WordPress.org reported **0.2.2**. The audit downloaded its versioned ZIP, which contains 44 runtime files. SHA-256 `4265ed643aa399bcffacaba68317db9b818818dd1bc3200319e5e92c294b58a76`. |
| Published source | All 44 published files matched Git `550fdafae0714945afc1df0342d9b45bd3c32380` byte for byte. The public SVN `tags/0.2.2/` listing was observed at repository revision 3799329. Its readme matched the download. |
| Latest main and executable candidate | `01aa04bf992735dd901e7613c83c99ff323a98cb`, including SE-025.1. Local `main` and `origin/main` matched. The working tree contained only the supplied replacement readme. |
| Packaged candidate source | `91470778d3bbb358b199bd6c8613063c7f9c5714` on `codex/se-026-core-023-release-preparation`. The later audit-only commit changes no package file. |
| Candidate metadata | Plugin header, runtime version and Stable tag **0.2.3**. Schema **2**. WordPress **6.0+**, PHP **8.0+**, Tested up to **7.1**. |
| Pro tested | `69592744f54340f1131e7e230bfa14ef0e19e18f` from `origin/develop/0.1`, version 0.1.0. An isolated Git archive supplied the 112-file Pro test ZIP. |

[Published listing](https://wordpress.org/plugins/sprint-engine/) ·
[Published package](https://downloads.wordpress.org/plugin/sprint-engine.0.2.2.zip) ·
[Public SVN tag](https://plugins.svn.wordpress.org/sprint-engine/tags/0.2.2/)

## Complete release change inventory

The candidate adds 25 runtime paths and changes 17, including `readme.txt`,
with 27 byte-identical paths and no removals. The executable changes trace to
the following work. The version bump and a final Step button label correction
within SE-020 were also checked.

| Change | Effect and boundary |
| --- | --- |
| [SE-018](se-018.md), `b55966a` | Adds `sprint_engine/runner_completed_followup_actions` after the completion Dashboard link. The earlier completion action, CTA and restart remain in order. The new hook receives the same authorised canonical attempt payload and is presentation-only. |
| `de9b392` | Advances plugin version and source Stable tag to 0.2.3. Schema and minimum WordPress/PHP versions stay the same. Asset URLs continue to use file fingerprints as well as the plugin version. |
| [SE-019](se-019.md), `f5788c7` | Adds in-progress restart with confirmation and an atomic replacement attempt. Historical status gains `restarted`. Complete and Restart require captured attempt identity in PHP and REST. Runner and Dashboard send it. REST repeats authentication, entitlement and readiness under the write lock. |
| [SE-020](se-020.md), `da549d6` | Active resume selects the earliest incomplete Step in the current validated order. Completion skips already finished Steps and rejects stale/out-of-order views. Removed-Step history stays stored. The final outstanding requirement says **Complete Sprint**, even when it precedes completed Steps. Runner repeats access and readiness under the resume lock. |
| [SE-021](se-021.md), `baec331` | Adds Dashboard section output, exact-true empty-state suppression and per-card context hooks on authorised ready renders. |
| PRO-008.1.1 Core seam, `866e85d` | Adds `sprint_engine/dashboard_card_actions` and `Dashboard::SUPPORTS_CARD_ACTIONS`. Secondary controls wrap with the primary action. Earlier hooks retain their placement and payloads. |
| [SE-023](se-023.md), `ec3c71e` | Adds the private administrator Step Preview and factors shared block presentation out of Runner. Draft/orphan Steps and drafts under unpublished Sprints can preview. The editor uses WordPress autosaves for title/content and the last saved Step settings. |
| [SE-024](se-024.md), `657a26d` | Adds site, system and five bundled font choices to branding. Existing options without a font use the site selection with system fallback. Runner, Dashboard and preview use scoped styles. Adds 14 Latin-subset WOFF2 files and five SIL OFL notices. |
| [SE-025](se-025.md), `44abd36` | Links the configured Runner logo and adds Back to website on member and rendered error pages. |
| [SE-025.1](se-025-1.md), `01aa04b` | Shares the links and stylesheet between Runner and My Sprints and exposes an optional helper Pro can detect. Author and settings previews are excluded. |
| SE-026 | Adopts the supplied marketing readme, corrects feature and operational details, adds the release notes and checks the resulting format. |

The baseline diff also confirms that `src/Database/`, the repositories,
`uninstall.php`, the authoring endpoints and AccessManager code are unchanged.
The additional access checks sit at the existing transaction boundary. Core
ships no Pro blocks, export, commerce or Journey implementation.

## Public write API compatibility

All routes below are POSTs under `/wp-json/sprint-engine/v1` (or the WordPress
REST equivalent). Existing same-origin cookie authentication and REST nonce
requirements apply. URL IDs select the resource. The service derives the
member from the REST session and validates the Step/Sprint relationship.

| Operation | Published 0.2.2 | Proposed 0.2.3 |
| --- | --- | --- |
| `/sprints/{id}/start` | No body required. Starts or returns canonical state. | Same contract. Member access and availability are repeated under the lock. |
| `/steps/{id}/complete` | No body required. Completes the current Step of the current attempt. A retry against a Step already completed on that attempt can succeed idempotently. | Requires `expected_attempt_id` with the displayed attempt's positive ID. Example JSON `{"expected_attempt_id":123}`. Missing/invalid gives `400 sprint_engine_write_400`. Mismatch gives `409 sprint_engine_write_409`. A correct identity can still receive 409 when the Step order changes. Same-attempt retries remain idempotent. |
| `/sprints/{id}/restart` | No body required. A completed run gets a replacement. An active run returns its existing state (200, no-op). An unstarted Sprint conflicts. | Same required ID and 400/409 behavior as Complete. A matching active run becomes historical and a new attempt starts. A matching completed run starts a replacement and retains its completed record. An unstarted Sprint still conflicts. |
| `ProgressService::complete_step()` / `restart_sprint()` | Three / two arguments, respectively. The development guide describes the service as an internal application API, but extensions can call these public PHP methods. | A trailing `$expected_attempt_id = null` preserves call syntax. Omitting it now returns `WP_Error(sprint_engine_progress_attempt)`. A mismatch returns `sprint_engine_progress_stale_attempt`. The optional mutation guard is an additive constructor argument for trusted callers. |
| `get_attempt_history()` | Canonical read-only payload contains attempt status. | Same shape. Consumers must recognise the new `restarted` status. Strict status allowlists in older extensions can fail after an in-progress restart. |

The REST controller reads `expected_attempt_id` via
`WP_REST_Request::get_param()`. The service accepts an integer or a string that
passes PHP's positive `FILTER_VALIDATE_INT` check. Booleans, floats, arrays,
missing values, leading-zero strings such as `"01"` and overflow fail. The
success response still carries `attempt_id`, `attempt_number`, `state`,
`current_step_id`, progress counts and the canonical Runner URL. Complete
also returns the existing `completed_step_id` and `sprint_completed` fields.
There is no public member REST state GET in the shipped route set. An extension
should bind the ID to its authorised view or to the preceding successful
response for that same interaction. On conflict it should reload/re-render.

The 0.2.2 scripts made bodyless requests. The candidate's Runner binds the
ID from its rendered context, and Dashboard uses the card's `data-attempt-id`.
An already-open 0.2.2 tab or a stale custom template can receive 400 until the
view is reloaded or updated. A WordPress REST nonce proves the session, and
the same Step ID can recur in successive attempts. Neither identifies the run
the participant meant to change. Resolving the latest attempt at write time,
even under a lock, would let a stale request complete the same Step or
restart the wrong run. A retry after a restart is a practical example.

The comparison used actual dispatch against both installed ZIPs. The published
package accepted bodyless completion and restart. The candidate returned 400
for those shapes and the old PHP calls returned errors. Missing, malformed
and stale writes preserved exact rows and timestamps.

| Option for the maintainer | Trade-off |
| --- | --- |
| **Recommended:** plan an explicitly breaking pre-1.0 release, for example 0.3.0, with upgrade notes and a client migration window. | Retains protection. Integrations still need updates because a new plugin version alone does not change the REST route namespace. Recheck Pro's version/capability gates and rebuild/retest metadata and packages. |
| Approve a documented compatibility exception and keep 0.2.3. | Keeps the same protected code and still breaks old clients. Site owners need advance notice, updated integrations and a reload of open member views. Treat approval as an explicit breaking-release decision. |
| Commission a versioned transition or defer the restart feature. | A new guarded route gives clients a migration target. Any legacy mutating route would need verifiable identity, a narrowly proven safe case, or a fail-closed response. A first-attempt-only exception cannot cover existing multi-attempt users or general restart. Simply retaining an unguarded v1 path undermines the protection. Deferring the feature requires a separate safety review because completed restarts can also race with stale requests. |

SemVer permits instability before 1.0. The recommendation is to signal this
specific contract break clearly and give integrators time to migrate.
[Semantic Versioning 2.0.0](https://semver.org/)

## Upgrade and data integrity

The disposable upgrade probe used WordPress's native `Plugin_Upgrader::install()`
with the published 0.2.2 ZIP. It activated Core, completed a Sprint twice with
a completed-Sprint restart in between, and left a separate Sprint in progress.
It then used the admin ZIP replacement flow's `overwrite_package` option. The active
plugin remained active. The first candidate bootstrap updated the version marker
to 0.2.3 and retained schema 2. The upgrade runs the existing idempotent schema
verification and one-time rewrite refresh. It requires no new data migration.

On both WordPress 7.1.4/PHP 8.3.35 with SQLite and WordPress 6.0/PHP 8.0.30
with MariaDB 10.11.11/InnoDB, exact snapshots covered posts, post metadata,
attempts, Step progress, timestamps, table columns/indexes and saved branding.
My Sprints preserved the three states and returned private/no-store headers.
The existing active attempt kept its resume Step. The original branding option
remained byte-equivalent and acquired `website` as a default on read.

After inserting, reordering and removing Steps through the Structure Manager,
an outdated completion conflicted, and resume moved to the earliest remaining
Step on the same attempt. Restart marked only the old run status `restarted`,
preserved historical pointers, timestamps and Step rows, and created one
replacement. A stale retry could not allocate another attempt. The replacement
completed normally. Deactivate/reactivate retained the resulting data.

## Core and Pro

Core standalone passed separately. The archived Pro revision uses the same
canonical History API, understands `restarted` and checks captured attempt
identity at the Workspace write boundary. Core and Pro's compatible lock and
identity checks prevent late Workspace saves from affecting a replacement run.
The combined tests cover old attempt isolation, response persistence, History,
Workbook and XLSX, recommendation/denial hooks, My Sprints hooks, Journeys,
access providers, and the new navigation adapter. The Pro navigation browser
fixture used an actual subdirectory home, retained the unsaved Workspace
warning on both Runner links, and compared exact Core and Pro rows.

Pro Workspace Field Preview and standalone Pro typography consistency remain
separate work. Some Pro blocks currently show their unavailable state in
Administrator Step Preview. The standalone Pro screens keep their existing
typography, while the shared link inherits the font of its containing page.
The tested Pro revision presents no additional release blocker. The test
entitlement fixtures do not substitute for paid order/subscription acceptance
on an installed WooCommerce site. Older Pro revisions were outside this audit.

## Marketing readme and directory validation

The supplied readme was carried to the feature branch unchanged before edits.
The opening, headlines, use cases, benefit-led structure, five discovery tags
and planned Pro section were retained. The edits cover active restart, pretty
permalinks, branding font choices, self-hosted font privacy, retention and the
0.2.3 changelog. Historical release notes remain. The API change is stated
briefly in the changelog and expanded above.

Checked the contributor against the existing directory listing, the five tags
against Directory tag pages, the 126-character plain-text short description,
title, license, stable tag, minimums and Tested up to 7.1 on WordPress 7.1.4.
The marketing comparison describes linking to learning content, with course
access and completion still handled by the LMS. The planned paid features are
clearly labelled and delivered separately. The five captions align with the
existing directory screenshots in authoring, structure, Step editor, Runner
and My Sprints order.

The official validator returned only informational notes for the absent donate
link and Upgrade Notice. A local render with the WordPress.org parser and
Markdown renderer verified the short description, Description headings and
lists, all ten FAQs, five screenshot captions and the new changelog. The
parser includes the custom Privacy and Data section within the Description.
Release tests check the new readme's metadata,
ordered changelog, section uniqueness, five numbered captions, minimums, and
prerequisites without pinning the old marketing text or tags.
[Readme rules](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) ·
[Official validator](https://wordpress.org/plugins/developers/readme-validator/) ·
[Plugin Directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

## Plugin Check and security review

Plugin Check **2.2.0** on the installed candidate via WP-CLI **2.12.0**
reported **0 errors and 14 warnings**. The five runtime performance checks
were also invoked explicitly with the early CLI bootstrap and completed
without reported findings. These standard checks use their own WordPress
fixture pages. The Runner, Dashboard and editor asset boundaries were also
checked in the separate integration/browser tests.

| Warnings | Review and disposition |
| --- | --- |
| 3 prefix warnings for `DONOTCACHEPAGE` in Dashboard, Runner and Step Preview | Justified shared cache opt-out convention. Private/no-store and noindex headers are set separately. Pre-WordPress caches still need their existing bypass configuration. |
| 1 dynamic hook warning in ProgressService | The event buffer contains fixed Sprint Engine hooks produced by the transition logic and emitted after commit. No requester-selected hook name. Justified. |
| 2 in Runner Presentation for `the_content` and a dynamic global | The native WordPress content hook is intentional. Temporary post globals come from a fixed key list and restore in `finally`. The same rendering path runs only after access or administrator preview authorisation. Justified. |
| 8 SQL warnings: Installer 3, EnrolmentRepository 2, StepProgressRepository 3 | Reviewed interpolation and arguments. Table names use the trusted WordPress prefix and fixed suffixes. Member, Sprint, Step and attempt values use prepared placeholders, and history query fragments use prepared values with fixed ordering. The minimum WordPress 6.0 lacks `%i` support. Existing warnings are scanner limitations for these statements. Identifier placeholders can be revisited with a compatible approach. |

Review also covered session/nonce and capability checks, positive IDs,
cross-Sprint attempts, prepared SQL, generic error mapping, no-store headers,
Step Preview's per-Step/current-author autosave check, translated/escaped
navigation, scoped styles and same-origin font source validation. The bundled
faces ship with their five original SIL OFL notices. Core makes no font-service
requests and scopes font loading to its presentation. Host themes, authored
embeds and extension callbacks can make their own requests. The test ZIP
contains no vendor, tests, credentials or development paths. No Plugin Check
warning was suppressed in this audit.

## Independently executed in SE-026

| Check | Result |
| --- | --- |
| Core Composer manifest, fresh disposable SQLite database per test | 19 scripts, 1,796 passing assertions, no skips. Includes HTTP REST, Runner and Dashboard, authentication and cross-Sprint validation, progress, attempts, migration, authoring, access and retention. |
| PHP lint, PHPCS and Composer strict validation | 68 tracked PHP files linted. PHPCS and Composer validation passed. |
| JS syntax and unit tests | 21 syntax checks and 39 passing tests. |
| Native 0.2.2 → 0.2.3 ZIP replacement | Pass on both current SQLite and minimum InnoDB runtimes. Exact data comparison and rejected legacy requests described above. |
| Core browser coverage | Chromium Runner journey, Dashboard read/journey/branding/extensions, shared website navigation, branding save/reset, author preview, fonts including classic fallback, completion hooks with and without callbacks, Gutenberg and Classic authoring, in-progress restart and Step change recovery. |
| Minimum runtime | WordPress 6.0/PHP 8.0.30/MariaDB InnoDB. Native replacement, REST (163), Step change recovery (145), mid-Sprint restart (53), History (82), and the Gutenberg Step Preview browser and retention checks. |
| Pro integration and browser checks | 16 standalone scripts and 15 WordPress integration scripts passed. Core's optional Pro Step change recovery passed all nine assertions. Combined browser inventory covered 15 Pro views/states with and without a logo, navigation at 320–1440 px, print/Excel and existing Workspace warnings. Pro package/metadata checks passed. |
| Candidate ZIP and readme | All 69 files installed byte-for-byte on both upgrade sites, 45 release checks, official online Readme Validator and local render. |

Earlier ticket records supply additional coverage such as older Pro/Core
fallbacks, Elementor font detection, and detailed screenshots. Those results
are historical. The SE-026 run used disposable localhost sites. Physical
devices, other browser engines, live commerce and production cache behavior
are outside this run. After the compatibility decision, use the approved
candidate for staging acceptance with installed integrations and the actual
site cache. No production account, data or deployment was used.

## Runtime package manifest

`php tools/package.php` builds `dist/sprint-engine.zip`.
`php tests/package.php` verifies paths and byte equality to source and checks
for unwanted entries and local paths. `php tests/release.php` verifies metadata
and readme. Compare per-file hashes after extracting any rebuilt or distributed
ZIP, since container timestamps/compression can differ. The tested ZIP is
**536,288 bytes**, SHA-256
`794890ce0536dce905c3ad0576bbe05fb001a2909b6eb290112fd65a0f74bb19`.
The exact readme SHA-256 is
`68836b1be20c28be7b468e43e403f538e6d4cb843025f030ce33d3f30b0ba9b2`.
All 69 paths below are under one `sprint-engine/` directory in the ZIP.

~~~text
LICENSE
assets/css/dashboard.css
assets/css/runner-settings.css
assets/css/runner.css
assets/css/structure-admin.css
assets/css/website-navigation.css
assets/fonts/inter-OFL.txt
assets/fonts/inter-latin-wght-italic.woff2
assets/fonts/inter-latin-wght-normal.woff2
assets/fonts/lato-OFL.txt
assets/fonts/lato-latin-400-italic.woff2
assets/fonts/lato-latin-400-normal.woff2
assets/fonts/lato-latin-700-italic.woff2
assets/fonts/lato-latin-700-normal.woff2
assets/fonts/open-sans-OFL.txt
assets/fonts/open-sans-latin-wght-italic.woff2
assets/fonts/open-sans-latin-wght-normal.woff2
assets/fonts/poppins-OFL.txt
assets/fonts/poppins-latin-400-italic.woff2
assets/fonts/poppins-latin-400-normal.woff2
assets/fonts/poppins-latin-700-italic.woff2
assets/fonts/poppins-latin-700-normal.woff2
assets/fonts/roboto-OFL.txt
assets/fonts/roboto-latin-wght-italic.woff2
assets/fonts/roboto-latin-wght-normal.woff2
assets/js/authoring-validation.js
assets/js/dashboard.js
assets/js/runner-admin.js
assets/js/runner-settings.js
assets/js/runner.js
assets/js/step-preview.js
assets/js/structure-admin.js
readme.txt
sprint-engine.php
src/Access/AccessManager.php
src/Access/AccessProvider.php
src/Access/LoggedInAccessProvider.php
src/Assets.php
src/Content/Authoring.php
src/Content/EditorRestController.php
src/Content/Meta.php
src/Content/RunnerAdmin.php
src/Content/SprintPostType.php
src/Content/StepListAdmin.php
src/Content/StepPostType.php
src/Content/StructureAdmin.php
src/Content/StructureManager.php
src/Dashboard/Dashboard.php
src/Dashboard/Routes.php
src/Database/Installer.php
src/Database/Migrations.php
src/Plugin.php
src/Presentation/WebsiteNavigation.php
src/Progress/EnrolmentRepository.php
src/Progress/ProgressService.php
src/Progress/StepProgressRepository.php
src/Progress/Transaction.php
src/Rest/Controller.php
src/Runner/Availability.php
src/Runner/Presentation.php
src/Runner/Routes.php
src/Runner/Runner.php
src/Runner/StepPreview.php
src/Settings/RunnerBranding.php
src/Settings/RunnerTypography.php
src/Settings/SettingsPage.php
templates/dashboard.php
templates/runner.php
uninstall.php
~~~

## WordPress.org publication plan

Run this plan after a separate publication authorisation and a GO decision.
The commands assume the approved version is still 0.2.3. A different version
requires corresponding metadata, tests, package hashes and tag name.

1. Obtain a clean working copy of the [Sprint Engine SVN repository](https://plugins.svn.wordpress.org/sprint-engine/).
   Verify SVN status, current stable version, committer access and whether
   `tags/0.2.3/` already exists. Check the committer's Release Management
   dashboard and confirm the recipients and approval requirements if Release
   Confirmation is enabled. The audit verified the documented process. The
   plugin's account-specific setting still requires committer access to check.
2. Put the contents of the verified `sprint-engine/` folder directly into
   `trunk/`. This includes the plugin's runtime `trunk/assets/` with CSS, JS,
   WOFF2 and font licenses. Compare sorted relative paths and SHA-256 hashes
   against the approved archive. Stage additions and removals under `trunk/`
   and review `svn status` and `svn diff`. If the tag already exists, investigate
   before proceeding.
3. Keep the existing repository-level `/assets/` for the listing:
   `banner-1544x500.png`, `banner-772x250.png`, `icon-128x128.png`,
   `icon-256x256.png` and `screenshot-1.png` through `screenshot-5.png`.
   These are not runtime files. Preserve the screenshot order and verify
   the captions against the listing assets.
4. From the SVN working copy, create the tag with
   `svn copy trunk tags/0.2.3` and compare the tag's file paths and hashes with
   the approved archive. Confirm the tagged `readme.txt` contains the approved
   marketing text, 0.2.3 changelog and Stable tag, and tagged `sprint-engine.php`
   contains the matching plugin header, runtime constant and schema 2. Review
   `svn status` and commit trunk with `Stable tag: 0.2.3` and the tag together
   so the stable pointer and its target arrive in the same revision. Updating only
   `trunk/readme.txt` may leave the public listing on the readme in the
   existing stable tag. If release confirmation is enabled, the authorised
   committer completes it in Release Management.
5. Verify the directory listing and plugin-information API show the version,
   marketing headline, changelog, tested version and updated download link.
   Download the versioned ZIP and compare every extracted file's path and
   SHA-256 with the approved manifest. Allow for WordPress.org to regenerate
   the archive container. Confirm an existing disposable 0.2.2 installation
   receives the 0.2.3 update offer through WordPress's normal update check.
6. Smoke test installation and upgrade, existing Dashboard/Runner resume,
   Start/Complete/Restart, Step Preview privacy, website navigation and the
   supported Pro combinations. Before any real-site update, retain a database
   and plugin snapshot. If a live release fails, coordinate the response
   with the Plugins Team and use a verified fix under a new version as
   appropriate. Do not assume rolling back code reverses writes: 0.2.3 can
   create historical `restarted` statuses and replacement attempts under
   schema 2. Assess History consumers and preserve work created since upgrade
   before restoring a snapshot or offering a downgrade.

[Using Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/) ·
[Directory assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/) ·
[Release Confirmation](https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/) ·
[Plugin developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
