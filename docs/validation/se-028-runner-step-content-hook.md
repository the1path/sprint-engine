# SE-028 — Runner after-Step-content extension hook

Validated on 18 November 2026. Implemented and locally validated for review;
owner acceptance, merge and release remain separate decisions.

## Source and scope

- Repository: `the1path/sprint-engine` (Core).
- Branch: `codex/se-028-runner-step-content-hook`.
- Base: `813473eeeed6832cb6ccd2b6475727886e8edf3b`, latest `origin/main`
  verified with `git fetch origin main` before creating the branch. The checkout
  was clean; no worktree or pre-existing changes were used.
- Runtime metadata remains **0.3.0**, schema **2**, Stable tag **0.3.0**,
  WordPress **6.0+**, PHP **8.0+**. No runtime/version/readme metadata changed.

| Changed file | Purpose |
| --- | --- |
| `templates/runner.php` | Emit the single presentation action after the Step article for authenticated `in_progress` member renders. |
| `tests/integration/runner.php` | Canonical identity, placement, all Steps, restart, negative states, no-listener parity, read-only invariants and real HTTP saved/autosave Preview checks. |
| `docs/development.md` | Document the contract, callback responsibilities and release dependency; narrowly correct the current published version. |
| `AGENTS.md` | List the hook and narrowly correct the current published version/development status. |
| `docs/validation/se-028-runner-step-content-hook.md` | This validation and handoff record. |

No services, access policy, REST routes, content rendering pipeline, Preview
implementation, persistence, migrations, assets, dependencies or Pro files changed.
The supplied implementation ticket and private planning material were not added
to this repository.

## Contract and acceptance evidence

`sprint_engine/runner_after_step_content` receives one newly constructed array
with exactly positive integer `sprint_id`, `step_id`, `attempt_id` and
`attempt_number`, drawn from Core's already-authorized Runner context. A listener
records the entire argument list, making extra arguments/keys and type changes
fail strict equality checks. Query, POST and request identity tampering cannot
substitute another Sprint, Step, user or attempt.

The integration suite renders all five authored Steps in canonical order,
including the first Task-mode Step and regular Content-mode Steps through the
final Step. It checks Attempt 1 and the first Step after a legitimate restart
into Attempt 2. A temporary HTTP-worker listener checks every Step again in
Attempt 3 after another legitimate restart and advancement. Output occurs exactly
once immediately after `</article>` and before `.se-runner__actions` and the
appropriate Complete & Continue / Complete Sprint control. DOM assertions also
confirm that the safe, escaped test paragraph is a sibling of the article and
adds no focus target.

Ready to start, completed, unavailable, persistence error, access denial, login,
anonymous public gateway and logged-out active-context rendering produce no
after-Step callback. Real authenticated WordPress HTTP requests confirm that both
saved Step Preview and an administrator-owned autosave render their expected
instructional content in Preview Mode without the hook. Preview is never given
a member attempt context. Its exact participant progress rows/timestamps remain
unchanged.

Removing the listener leaves the same normalized Core shell and actions, both
in-process and over HTTP, with no empty wrapper or placeholder. Rendering with
and without the listener executes the same queries after WordPress caches warm.
Exact attempt/Step rows, timestamps and Sprint/Step metadata snapshots remain
unchanged; no database mutations or lifecycle events occur during emission.
Core's existing Runner resolution/resume activity remains separate from this
template-only check. Existing completion/follow-up contexts and order, restart,
navigation, private/no-store/noindex headers, native blocks and audio markup
retain their existing integration coverage. Pro was absent throughout the Core
checks.

## Environment

- Windows build `10.0.26200.0`; PowerShell **7.6.9**.
- WordPress **7.1.4**; PHP **8.3.35** NTS; SQLite Database Integration **3.0.2**,
  SQLite **3.53.4**. Test sites use copied disposable fixtures under ignored
  `.tools/se028`, a fresh SQLite baseline for each suite, and loopback HTTP only.
- `SE_TEST_WP_ROOT` points to that disposable WordPress copy and
  `SE_TEST_DISPOSABLE=yes` is required by the integration scripts.
  `SE_TEST_HTTP_BASE=http://127.0.0.1:8188` enables integration HTTP checks.
- Composer **2.10.3**; PHP_CodeSniffer **3.13.6**; WPCS **3.4.1**;
  Node.js **24.22.3**. Changed PHP also passed syntax checks with PHP **8.0.30**.
- Browser checks use a separate disposable database and loopback port **8189**,
  Playwright **1.62.1** and Chrome **157.0.8103.79**, headless.

## Checks and outcomes

All 19 commands declared in `composer.json`'s test manifest passed, using fresh
disposable databases and real HTTP where supported: **1,851 assertions, zero
skips**. The launcher executes each declared PHP command separately to isolate
destructive fixtures; it does not rely on a shared database across suites.

| Command / suite | PASS assertions |
| --- | ---: |
| `php tests/release.php` | 48 |
| `php tests/integration/assets.php` | 12 |
| `php tests/integration/foundation.php` | 46 |
| `php tests/integration/authoring.php` | 118 |
| `php tests/integration/structure.php` | 110 |
| `php tests/integration/progress.php` | 145 |
| `php tests/integration/migration.php` | 43 |
| `php tests/integration/attempts.php` | 51 |
| `php tests/integration/mid-sprint-restart.php` | 53 |
| `php tests/integration/step-change-recovery.php` | 145 |
| `php tests/integration/attempt-history.php` | 82 |
| `php tests/integration/runner.php` | 305 |
| `php tests/integration/dashboard.php` | 149 |
| `php tests/integration/runner-admin.php` | 53 |
| `php tests/integration/rest.php` | 163 |
| `php tests/integration/branding.php` | 82 |
| `php tests/integration/polish.php` | 78 |
| `php tests/integration/validation.php` | 132 |
| `php tests/integration/prefixing.php` | 36 |

Additional checks:

- `composer validate --strict` and `composer check`: **PASS**.
- `php -l` for all **68 tracked PHP files** and `node --check` for all
  **21 tracked JavaScript files**: **PASS**.
- PHP **8.0.30** `-n -l` on both changed PHP files: **PASS** (syntax only).
- `node --test tests/js/*.test.cjs`: **39 passed**, no failures or skips.
- Core `tests/package.php` verifier: **PASS**, **69 exact runtime files**,
  including the changed template, with no extra files or development/secret
  markers. The ignored local harness `.tools/se028/package-check.php` constructs
  a temporary verification archive, runs the unchanged verifier with only its
  root/archive paths redirected, then deletes the fixture. It does not run
  `tools/package.php`, create a release ZIP or overwrite `dist/sprint-engine.zip`.
- `php tests/browser/fixture.php`,
  `node tests/browser/runner.cjs <fixture>` and
  `php tests/browser/fixture.php <fixture> retention`: **PASS**. Eight Runner
  states at 1440/768/390/320 px; two users, authenticated REST writes, keyboard
  focus, touch targets, layout/media containment, restart cancellation/acceptance,
  Attempt 2, exit/resume, completion and deactivate/reactivate retention.
- `php tests/browser/completion-followup-fixture.php` and
  `node tests/browser/completion-followup.cjs <fixture>`, repeated with
  `no-extension` and the fixture's `verify-read` checks: **PASS**. Both completion
  hooks retain exact context/control/focus order and layout at 1440/390 px;
  both members' rows/timestamps remain unchanged and rendering sends no writes.
  With no extension the same Core controls remain usable.
- `git diff --check` and review for scope, secrets, debug code, runtime metadata
  and backward compatibility: **PASS**.

## Findings and unrun checks

- **Final FAIL:** none in the completed checks above. No confirmed product
  regression or unresolved implementation question was found.
- **Resolved test-harness problems:** the initial launcher loaded SQLite twice
  in contention workers and omitted it from the HTTP worker. Correcting the
  per-process PHP configuration made the existing contention and HTTP suites
  pass. The new autosave fixture initially omitted WordPress's required
  `post_type`; supplying it made the saved/autosave Preview checks pass. The
  temporary package harness also needed a directory-containment check instead
  of an overly strict generated-filename prefix check. These were harness
  failures, not changes needed in Core runtime code. A preliminary CLI-only
  Runner run passed 247 assertions and explicitly skipped HTTP; the final
  305-assertion HTTP-enabled run supersedes that limited result.
- **NOT RUN:** full integration on WordPress 6.0/PHP 8.0 or MySQL/MariaDB;
  this run used the exact WordPress/PHP/SQLite environment above. Minimum-PHP
  syntax checks do not establish full minimum-runtime integration coverage.
- **NOT RUN:** Pro runtime/integration, production or HR Sprints testing,
  host/CDN cache behaviour, other browser engines, physical devices and
  assistive-technology testing. Pro is outside this Core hook ticket; no
  non-disposable WordPress installation was used. In-process assertions alone
  are not represented as browser/HTTP evidence.

## Release and integration handoff

[WordPress.org lists Core 0.3.0](https://wordpress.org/plugins/sprint-engine/)
as checked on 18 November 2026. That published version does **not** contain
SE-028; an installation labelled 0.3.0 cannot be assumed to support the action.
The historical SE-026.1 candidate validation is unchanged.

A separately approved patch-release task must choose a version, validate,
package and publish this hook before public consumers can depend on it. PRO-013
must independently decide compatibility/capability handling after the release
strategy is settled. This ticket introduces no capability constant or Pro
dependency. Extension callbacks remain trusted PHP and must independently
authorize private data, current Step/attempt and ownership, enforce nonce and
transaction safety for their operations, and escape their own output.

The feature branch is for review only. No merge, deployment, version increment,
tag, release ZIP or WordPress.org/SVN publication is part of SE-028. Owner and
ChatGPT review precede acceptance; neither PRO-013 nor SE-027 is started here.
