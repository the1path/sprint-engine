# WPORG-001 — WordPress.org prefixing and Guideline 11 audit

> Historical validation record: results, release stages and remaining checks below
> refer to this ticket at the time it was recorded. Core 0.2.0 is now released.

Validation date: 2 October 2026. Branch: `codex/wporg-review-001-prefixing`.
Base/merge base with `main`: `dbc1498b05fc0529d956d790f03f93acef0d4c23`.
Initial HEAD equals that base; initial working tree was clean. No fetch, merge,
SE-012 branch change, deployment, push or WordPress.org upload was performed.

## Result

**PASS:** correct the initial review's plugin-owned prefix/collision issues across
the entire shipped codebase and audit admin dashboard behaviour. Release stays
**0.1.0**, schema **1**, WordPress minimum **6.0**, PHP minimum **8.0**, author
**ThePath**, text domain **sprint-engine**, licence **GPLv2 or later**.
The public readme/marketing copy is unchanged. The specification and AGENTS naming
references are updated under the ticket's explicit naming direction.

No compatibility aliases or migration layer are shipped for private pre-release
identifiers. Old private content/tables are not deleted, moved or converted.
Disposable fixtures use corrected identifiers from the start. Existing private
sites require a separately planned reconciliation; this branch was not deployed.

Guidance applied: [WordPress best practices](https://developer.wordpress.org/plugins/plugin-basics/best-practices/),
[common review issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/),
and [Guideline 11](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).

## Canonical identifiers — PASS

PHP: `SprintEngine\...` → `ThePath\SprintEngine\...`, including declarations,
imports, fully qualified references, activation/deactivation callback strings,
custom bootstrap autoloader, template calls, tests and fixtures. Composer has no
autoload map; no new Composer runtime dependency or alias was added.
`@package SprintEngine` remains a documentation label, not a PHP declaration.

| Before | After |
| --- | --- |
| `se_sprint` | `sprint_engine_sprint` (20 characters) |
| `se_step` | `sprint_engine_step` |
| `{$wpdb->prefix}se_enrolments` | `{$wpdb->prefix}sprint_engine_enrolments` |
| `{$wpdb->prefix}se_step_progress` | `{$wpdb->prefix}sprint_engine_step_progress` |

CPT registration, native authoring REST, dynamic core save/meta/list hooks, queries,
parent checks, capability checks, content/meta validation, structure, Runner,
deactivation and fixtures all use the corrected CPTs. Operational SQL definitions,
repositories, transactional engine checks and test queries use the corrected table
suffixes. Schema definitions/columns/indexes remain the original single-attempt
model with no foreign keys; no table-prefix hardcoding was introduced.

Every persisted protected meta key is mapped below; leading underscores remain:

| Before | After |
| --- | --- |
| `_se_estimated_duration_value` | `_sprint_engine_estimated_duration_value` |
| `_se_estimated_duration_unit` | `_sprint_engine_estimated_duration_unit` |
| `_se_estimated_minutes` | `_sprint_engine_estimated_minutes` |
| `_se_start_step_id` | `_sprint_engine_start_step_id` |
| `_se_launchable` | `_sprint_engine_launchable` |
| `_se_completion_message` | `_sprint_engine_completion_message` |
| `_se_completion_cta_label` | `_sprint_engine_completion_cta_label` |
| `_se_completion_cta_url` | `_sprint_engine_completion_cta_url` |
| `_se_sprint_id` | `_sprint_engine_sprint_id` |
| `_se_position` | `_sprint_engine_position` |
| `_se_stage_label` | `_sprint_engine_stage_label` |
| `_se_mode` | `_sprint_engine_mode` |
| `_se_next_step_id` | `_sprint_engine_next_step_id` |

`_se_completion_cta` / `_se_completion_cta_` are validation grouping/prefix strings,
not additional persisted keys; these become `_sprint_engine_completion_cta` /
`_sprint_engine_completion_cta_` too. The existing duration fallback remains under
the corrected key; no short-key fallback was added.

## Storage, registrations and public surface — PASS

All option/transient/setting, registration, nonce/action, hook and global APIs were
inspected directly, alongside repository-wide searches.

* Options already compliant and unchanged: `sprint_engine_version`,
  `sprint_engine_schema_version`, `sprint_engine_installation_failed`,
  `sprint_engine_runner_branding`.
* Authoring transient: `se_authoring_error_{user}_{post}` →
  `sprint_engine_authoring_error_{user}_{post}`. Remains user/post-bound, five-minute
  expiry, consumed only in the appropriate editor context.
* Settings group: `se_branding` → `sprint_engine_branding`; submenu slug:
  `se-settings` → `sprint-engine-settings`.
* Script/style handles: `se-runner`, `se-structure`, `se-runner-admin`,
  `se-runner-settings`, `se-branding-runner`, `se-authoring-validation` → the same
  suffixes under `sprint-engine-`. Dependencies/localization/inline styles updated.
* JavaScript globals: `seRunner`, `seRunnerAdmin`, `seStructure`, `seBranding`,
  `seAuthoringValidation` → `sprintEngineRunner`, `sprintEngineRunnerAdmin`,
  `sprintEngineStructure`, `sprintEngineBranding`, `sprintEngineAuthoringValidation`.
* AJAX action: `wp_ajax_se_structure` → `wp_ajax_sprint_engine_structure`;
  payload action `se_structure` → `sprint_engine_structure`.
* Branding action: `admin_post_se_reset_branding` →
  `admin_post_sprint_engine_reset_branding`; payload/nonce use
  `sprint_engine_reset_branding`.
* Authoring nonce action/field: `se_authoring_{post}` / `se_authoring_nonce` →
  `sprint_engine_authoring_{post}` / `sprint_engine_authoring_nonce`;
  structure nonce `se_structure_{sprint}` → `sprint_engine_structure_{sprint}`.
  Core `wp_rest` and Settings API nonce conventions remain untouched.
* Metabox IDs: `se_setup`, `se_structure`, `se_runner_url` → `sprint_engine_setup`,
  `sprint_engine_structure`, `sprint_engine_runner_url`.
* Form envelope/query selectors/column keys: `se_meta`, `se_sprint`,
  `se_parent_sprint`, `se_parent`, `se_stage`, `se_runner` → the respective
  `sprint_engine_` names. Editor-saving lock already uses a compliant full prefix.
* Query vars: `se_sprint_slug` / `se_runner_context` →
  `sprint_engine_sprint_slug` / `sprint_engine_runner_context`.
  Rewrite remains `^sprint/([^/]+)/?$`; member URL remains `/sprint/{slug}/`.
* REST namespace remains `sprint-engine/v1`; start/complete operations unchanged.
  Core `wp/v2` authoring endpoints follow renamed CPTs.
* Custom hooks unchanged: `sprint_engine/sprint_started`,
  `sprint_engine/step_started`, `sprint_engine/step_completed`,
  `sprint_engine/sprint_completed`, `sprint_engine/user_can_access_sprint`,
  `sprint_engine/runner_template`.
* No taxonomy, shortcode, cron, block-type or unauthenticated AJAX registration
  exists to rename. No new registration/features were introduced.

Core/API/interoperability identifiers intentionally unchanged include `ABSPATH`,
`WP_UNINSTALL_PLUGIN`, `DONOTCACHEPAGE`, `the_content`, `admin_notices`, `init`,
`rest_api_init`, `wp_rest`, core editor/media dependencies, core post fields/meta
and WordPress's rendering globals. WordPress hook prefixes remain core spelling;
their plugin-owned CPT/action fragments are corrected.

## Guideline 11 — PASS

No promotional/marketing/upgrade/Pro notice, dashboard widget, dashboard redirect,
admin takeover or remote service was found or introduced.

Authoring errors already require `manage_options`, the matching Sprint/Step editor,
the matching user/post transient and appropriate Gutenberg/metabox placement.
Inline browser feedback/save locks are limited to those fields; unrelated notices,
Posts and Pages remain independent. Settings feedback uses native Settings API
messages on the plugin settings screen. Metaboxes are authoring controls, not
dashboard widgets.

The persistent installation-failure notice was broader than necessary. It now
requires both an administrator capability and Plugins management, a Sprint/Step
screen, or the uniquely named Sprint Engine settings page. It remains actionable
and disappears when installation succeeds. It is absent on Dashboard, Posts, Pages,
unrelated screens and for anonymous/non-administrator users. The new integration
suite exercises related/unrelated contexts, capability and recovery output.

## Security/behaviour review — PASS

Changes are identifier substitutions plus the narrower installation notice.
Existing admin-only/private CPT authoring, REST nonce verification, current-user
binding, AccessManager, Sprint/Step membership checks, Runner availability,
no-store/noindex responses, escaped output, validated/sanitized meta, prepared
dynamic SQL values, transaction/rollback rules and retained-data behaviour remain.
Integration/browser suites exercise unauthorized access, cross-Sprint tampering,
duplicate requests, failed writes, recovery and user isolation. No sensitive debug
output, telemetry, external service or third-party runtime dependency was added.

## Executed regression — PASS

Runtime: local disposable WordPress **7.1.2**, PHP **8.3.35**, official SQLite
integration, non-default `review_fixture_` prefix, loopback HTTP `127.0.0.1:8097`,
Node/Playwright/Chrome. All plugin runtime files come from the exact corrected ZIP;
test scripts remain outside it. Suites are sequential against corrected identifiers.

| Check | Result |
| --- | --- |
| PHP lint | PASS: 47 PHP files, zero failures |
| PHPCS | PASS: zero errors/warnings with repository WordPress ruleset |
| JS syntax | PASS: all five runtime scripts |
| Node tests | PASS: 16/16 |
| Release metadata/readme | PASS |
| Package | PASS: 39 exact runtime files, byte equality and exclusions |
| Assets / fresh foundation | PASS: 10 / 46 assertions |
| Authoring / structure / progress | PASS: 110 / 87 / 140 assertions |
| Runner / Runner admin / REST | PASS: 118 / 53 / 115 assertions, including real HTTP |
| Branding / polish / validation | PASS: 68 / 78 / 132 assertions |
| Prefixing/notice integration | PASS: 33 assertions |
| Admin browser | PASS: Gutenberg/Classic saves, duration/minutes/CTA, save locks, notice isolation, structure/reordering/removal, Runner admin, Posts/Pages isolation |
| Member browser | PASS: eight Runner states, 1440/900/390px, two users/nonces, browser restart, keyboard completion, save/exit/resume, canonical route, media containment and completion CTA |
| Branding browser | PASS: defaults, dark/light branding, live colours/corners/logo, save/reset nonce, mobile/focus/contrast feedback |
| Fresh installation | PASS: fresh isolated database, WordPress installation and plugin activation without plugin output/warnings/fatal errors |
| Branding HTTP retention | PASS: fresh snapshot before browser save/reset; exact content/progress/version/rewrite snapshot retained and branding option deleted |
| Lifecycle/retention | PASS: repeat dbDelta, deactivate/reactivate, exact content/meta/settings/progress retention and no-op uninstall; two-user completed/in-progress browser state retained |

Fixture repair notes: the first bootstrap omitted the core themes directory,
which caused WordPress `wp_is_block_theme` notices and invalid subprocess JSON.
Copied the standard themes and reran progress successfully. A second fresh database
installation/foundation run then passed cleanly. New notice-test bootstrap initially
omitted WP_Screen; fixed the test dependency. Its settings-screen assertion caught
a missing settings context; fixed production scoping and rebuilt/re-extracted the
candidate before final Plugin Check/browser runs. Initial PHPCS alignment warning
from a lengthened column key was fixed; final PHPCS is clean. An additional branding retention run exposed a pre-existing lazy-image timing race in the browser assertion; the test now waits for the actual logo load before checking dimensions. Final branding browser and exact snapshot retention both pass. No notices were
suppressed to make these checks pass. External update requests are blocked only in
the ignored disposable WordPress configuration, not in the plugin.

## Plugin Check 2.1.0 — PASS with WARNING

All **29 static checks**, `new` submission mode, executed via the official checker
APIs against `.tools/wporg001-wordpress/wp-content/plugins/sprint-engine`, extracted
from `dist/sprint-engine.zip`. Final installed bytes are verified against all 39
ZIP entries. **0 errors, 8 warnings, no static checks NOT RUN.** SE-011 had 0 errors
and 10 warnings; the two short template-variable warnings disappeared. No new
warning, suppression directive or speculative compatibility rewrite was added.

| Retained warning | Count | Explanation |
| --- | ---: | --- |
| Dynamic lifecycle hook dispatch, ProgressService:192 | 1 | Internal fixed lifecycle event list; compliant names and once-after-commit delivery covered by integration tests. |
| `DONOTCACHEPAGE`, Runner:150 | 1 | Intentional cache interoperability constant. |
| `the_content`, Runner:271 | 1 | Required WordPress block/content rendering filter. |
| Dynamic rendering-global restoration, Runner:275 | 1 | Fixed allowlist of WordPress globals restored in finally. |
| `$table` interpolation, EnrolmentRepository:27; StepProgressRepository:28/72/87 | 4 | Trusted `$wpdb->prefix` plus literal compliant suffixes; submitted values remain prepared. `%i` would raise the WP 6.0 floor. |

These are reviewed warnings, not claims of WordPress.org approval.

## Remaining short patterns / contamination — PASS

See [complete occurrence inventory](wporg-review-001-inventory.md). Searches include
`se_`, `_se_`, `se-`, `seRunner`, `SprintEngine`, `SPRINT_ENGINE`, `sprint_engine`,
`sprint-engine` and direct registration/storage API inspection. **Zero category F
(unresolved collision risk)** and no remaining short plugin-owned stored/global
registration identifier in shipped code.

Scoped `.se-*` classes/IDs, `--se-*` CSS custom properties, `data-se-error` and its
local DOM `dataset.seError` accessor remain implementation selectors. Runner CSS
is scoped to its standalone document; settings preview variables belong to its
container; editor/structure styles and data selectors are scoped to the plugin's
metaboxes/controls and only loaded on matching screens. They are not WordPress
registrations or stored identifiers. `$response->...` and `wp_parse_url` are broad
substring matches, not short plugin declarations. Historical validation reports
and test assertions retain old names as prose/negative expectations; they are not
shipped. Template locals now use `$sprint_engine_context` / `$sprint_engine_completion`.

**PASS:** runtime/assets/templates/bootstrap contain no `attempt_number`,
`restart_sprint`, restart endpoint, `Restart Sprint`, 0.2.0 metadata or schema 2.
The complete original schema remains version one. Historical specification prose
defers restart; it does not implement it. No Dashboard/SE-012 implementation files
are added. Branch ancestry remains the specified main commit.

## Package — PASS

Corrected archive: `<repo>/dist/sprint-engine.zip`.
Root: `sprint-engine/`. 39 files: four root files (`sprint-engine.php`,
`uninstall.php`, `readme.txt`, `LICENSE`), 26 class files, one template and eight
runtime assets. All entries equal source bytes. No Git, tests, docs, developer
README, AGENTS, Composer/vendor, tools, fixtures, screenshots, reports, secrets or
development outputs are packaged. Generated ZIP is ignored and not committed.

Commands: `php tools/package.php`, `php tests/package.php`, `php tests/release.php`;
all integration files under `tests/integration/` with disposable environment;
Node syntax and `node --test tests/js/*.test.cjs`; three repository browser suites;
official Plugin Check static API harness; repository PHPCS ruleset; `git diff --check`.
Ignored `.tools/wporg001-*` manifests/logs/checker JSON retain execution evidence.

## NOT RUN / limitations

* WordPress 6.0 runtime and PHP 8.0 execution: only WP 7.1.2/PHP 8.3.35 installed;
  Plugin Check compatibility scan against declared floor passes.
* MySQL/MariaDB execution: no disposable service available; SQLite/SQL schema
  review does not establish real MySQL engine compatibility.
* Plugin Check runtime harness: direct static API invocation does not prepare it;
  runtime behaviour is covered separately by integration/HTTP/browser tests.
* WordPress Upload Plugin UI: candidate was extracted directly, then activated and
  exercised with WordPress APIs/HTTP/browser; no claim of an upload-UI run.
* target host deployment, live-site checks and WordPress.org upload: prohibited by
  this ticket; human branch review remains before publication/deployment.

## Complete additional renamed-token mapping

The table below includes transient prefixes, nonce/action/form/query/column/error
codes, derived core-hook CPT fragments and template variables as well as the
persisted identifiers above. A trailing underscore denotes an assembled prefix.

| Before | After |
| --- | --- |
| `_se_completion_cta` | `_sprint_engine_completion_cta` |
| `_se_completion_cta_` | `_sprint_engine_completion_cta_` |
| `_se_completion_cta_label` | `_sprint_engine_completion_cta_label` |
| `_se_completion_cta_url` | `_sprint_engine_completion_cta_url` |
| `_se_completion_message` | `_sprint_engine_completion_message` |
| `_se_estimated_duration_unit` | `_sprint_engine_estimated_duration_unit` |
| `_se_estimated_duration_value` | `_sprint_engine_estimated_duration_value` |
| `_se_estimated_minutes` | `_sprint_engine_estimated_minutes` |
| `_se_launchable` | `_sprint_engine_launchable` |
| `_se_mode` | `_sprint_engine_mode` |
| `_se_next_step_id` | `_sprint_engine_next_step_id` |
| `_se_position` | `_sprint_engine_position` |
| `_se_sprint_id` | `_sprint_engine_sprint_id` |
| `_se_stage_label` | `_sprint_engine_stage_label` |
| `_se_start_step_id` | `_sprint_engine_start_step_id` |
| `se_authoring_` | `sprint_engine_authoring_` |
| `se_authoring_error_` | `sprint_engine_authoring_error_` |
| `se_authoring_nonce` | `sprint_engine_authoring_nonce` |
| `se_branding` | `sprint_engine_branding` |
| `se_branding_` | `sprint_engine_branding_` |
| `se_branding_corner_style` | `sprint_engine_branding_corner_style` |
| `se_branding_invalid` | `sprint_engine_branding_invalid` |
| `se_branding_logo_id` | `sprint_engine_branding_logo_id` |
| `se_completion` | `sprint_engine_completion` |
| `se_context` | `sprint_engine_context` |
| `se_enrolments` | `sprint_engine_enrolments` |
| `se_invalid_content` | `sprint_engine_invalid_content` |
| `se_meta` | `sprint_engine_meta` |
| `se_parent` | `sprint_engine_parent` |
| `se_parent_sprint` | `sprint_engine_parent_sprint` |
| `se_progress_not_launchable` | `sprint_engine_progress_not_launchable` |
| `se_progress_out_of_order` | `sprint_engine_progress_out_of_order` |
| `se_progress_persistence` | `sprint_engine_progress_persistence` |
| `se_progress_sprint` | `sprint_engine_progress_sprint` |
| `se_progress_state` | `sprint_engine_progress_state` |
| `se_progress_step` | `sprint_engine_progress_step` |
| `se_progress_structure` | `sprint_engine_progress_structure` |
| `se_progress_user` | `sprint_engine_progress_user` |
| `se_reset_branding` | `sprint_engine_reset_branding` |
| `se_runner` | `sprint_engine_runner` |
| `se_runner_context` | `sprint_engine_runner_context` |
| `se_runner_url` | `sprint_engine_runner_url` |
| `se_setup` | `sprint_engine_setup` |
| `se_sprint` | `sprint_engine_sprint` |
| `se_sprint_posts_columns` | `sprint_engine_sprint_posts_columns` |
| `se_sprint_posts_custom_column` | `sprint_engine_sprint_posts_custom_column` |
| `se_sprint_slug` | `sprint_engine_sprint_slug` |
| `se_stage` | `sprint_engine_stage` |
| `se_step` | `sprint_engine_step` |
| `se_step_posts_columns` | `sprint_engine_step_posts_columns` |
| `se_step_posts_custom_column` | `sprint_engine_step_posts_custom_column` |
| `se_step_progress` | `sprint_engine_step_progress` |
| `se_structure` | `sprint_engine_structure` |
| `se_structure_` | `sprint_engine_structure_` |
| `se_structure_error` | `sprint_engine_structure_error` |
| `se_write_` | `sprint_engine_write_` |
| `seAuthoringValidation` | `sprintEngineAuthoringValidation` |
| `seBranding` | `sprintEngineBranding` |
| `seRunner` | `sprintEngineRunner` |
| `seRunnerAdmin` | `sprintEngineRunnerAdmin` |
| `seStructure` | `sprintEngineStructure` |

## Exact changed/new files

Modified:

* `AGENTS.md`
* `README.md`
* `assets/js/authoring-validation.js`
* `assets/js/runner-admin.js`
* `assets/js/runner-settings.js`
* `assets/js/runner.js`
* `assets/js/structure-admin.js`
* `composer.json`
* `docs/specifications/sprint-engine-v0.1.md`
* `sprint-engine.php`
* `src/Access/AccessManager.php`
* `src/Access/AccessProvider.php`
* `src/Access/LoggedInAccessProvider.php`
* `src/Assets.php`
* `src/Content/Authoring.php`
* `src/Content/EditorRestController.php`
* `src/Content/Meta.php`
* `src/Content/RunnerAdmin.php`
* `src/Content/SprintPostType.php`
* `src/Content/StepListAdmin.php`
* `src/Content/StepPostType.php`
* `src/Content/StructureAdmin.php`
* `src/Content/StructureManager.php`
* `src/Database/Installer.php`
* `src/Database/Migrations.php`
* `src/Plugin.php`
* `src/Progress/EnrolmentRepository.php`
* `src/Progress/ProgressService.php`
* `src/Progress/StepProgressRepository.php`
* `src/Progress/Transaction.php`
* `src/Rest/Controller.php`
* `src/Runner/Availability.php`
* `src/Runner/Routes.php`
* `src/Runner/Runner.php`
* `src/Settings/RunnerBranding.php`
* `src/Settings/SettingsPage.php`
* `templates/runner.php`
* `tests/browser/authoring-fixture.php`
* `tests/browser/authoring.cjs`
* `tests/browser/branding-fixture.php`
* `tests/browser/branding.cjs`
* `tests/browser/fixture.php`
* `tests/browser/runner.cjs`
* `tests/integration/assets.php`
* `tests/integration/authoring.php`
* `tests/integration/branding.php`
* `tests/integration/foundation.php`
* `tests/integration/polish.php`
* `tests/integration/progress.php`
* `tests/integration/rest.php`
* `tests/integration/runner-admin.php`
* `tests/integration/runner.php`
* `tests/integration/structure.php`
* `tests/integration/validation.php`
* `tests/js/authoring-validation.test.cjs`
* `tests/js/runner.test.cjs`
* `tests/package.php`

New:

* `tests/integration/prefixing.php`
* `docs/validation/wporg-review-001.md`
* `docs/validation/wporg-review-001-inventory.md`

## Git status

**WARNING:** The single normal stage/commit attempt failed at git add: unable to create `.git/index.lock`, Permission denied. No commit was created and no retry/reset/discard was performed. All 57 modified files and three new files remain intact for GitHub Desktop. No merge/push/deployment/directory upload.

ZIP SHA-256: `1c9c304495cb97c5764cdb352987c1b3284f8329ef5d067bcb7ff75a64153105`.
