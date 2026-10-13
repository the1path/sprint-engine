# SE-REL-0.2.2 — Sprint Engine Core release preparation

Prepared and validated 13 October 2026. **Not published or deployed.**
The readme synchronization correction below supersedes the initial ZIP evidence;
the Verified ZIP section identifies the current artifact for future publication.

## Baseline and scope

- Repository/origin: `https://github.com/the1path/sprint-engine.git`.
- Confirmed clean `main` at the expected base
  `deb9d86c6a29e4aa1a595739f1189b774350113e` (merged SE-017).
- Created `codex/release-0.2.2` from that confirmed `main`; no unrelated or
  unmerged working-tree changes were present.
- Read AGENTS.md, the architecture baseline, development guide, release/package
  tools/tests and the SE-016/SE-017 validation and environment-recovery evidence.
- Core source/header/runtime version and readme Stable tag: **0.2.2**.
- Schema stays **2**, minimum WordPress **6.0**, minimum PHP **8.0**; slug/text
  domain `sprint-engine`, PHP namespace and bootstrap behavior are unchanged.
- Published stable remains **0.2.0**. Neither 0.2.2 publication nor deployment is
  claimed. No push, PR, merge, Git tag, SVN access/edit/commit/tag or publication
  was performed. The SVN instructions below are a future publisher's manifest.

Only the main plugin's Version header and `SPRINT_ENGINE_VERSION` changed in
executable runtime files. `src/`, `templates/`, `assets/`, `uninstall.php` and
`LICENSE` are unchanged from the base, preserving all SE-017 hooks, services,
authentication/access, REST, admin UI, styling and storage behavior. The readme
has the new version/changelog plus updated current-version explanatory text.
The initial preparation kept its then-current five tags and short description;
the supplied reconciliation below supersedes that copy and restores a 0.2.1
description-update note. Historical validation records were not edited.

This release adds no schema migration, table, option or route. The existing
version-upgrade path still verifies the installed schema and performs its existing
one-time soft rewrite refresh on a version change; no upgrade/rewrite code was
changed. Tests retain progress exactly when advancing existing 0.2.0 or 0.2.1
version markers to 0.2.2/schema 2.

The public changelog describes optional extension hooks and a Login access
screen, with unchanged defaults. It advertises no built-in recommendations,
payments/subscriptions, purchasing, Journeys or commercial-extension features.

## Initial release-preparation validation

This section records the initial build's execution evidence. The later readme
correction's rerun results and replacement archive identity are recorded below.

PHP **8.3.35**, WordPress **7.1.2**, official SQLite Database Integration, and
headless Chrome/Playwright. The ZIP-extracted candidate activated as **0.2.2**,
schema **2**. Fresh disposable database baselines use the non-default
`se017_fixture_` prefix and a new unique normal temporary directory configured
through the SQLite integration's supported `DB_DIR` setting. Original project
database files, production sites, ownership/ACLs and sandbox protections were
not changed. The existing disposable WordPress core/tooling was reused; tests
use loopback `http://127.0.0.1:8101` and isolated fresh database snapshots.

WordPress 7.1.2 execution here and the existing SE-017 record support retaining
readme `Tested up to: 7.1`. This is actual integration/browser evidence, not a
claim that the PHP 8.0/WordPress 6.0 minimum matrix was executed.

Composer CLI was unavailable on PATH. Every PHP test command in its manifest
was executed directly with the configured PHP runtime, plus the package test;
the existing local PHPCS binary ran the repository ruleset. Exit status and
expected PASS counts were checked to catch silent early WordPress bootstrap
exits. Logs/manifests are ignored `.tools/release022-*` files.

| Check | Result |
| --- | --- |
| PHP syntax, bootstrap/uninstall/src/templates/tests/tools | PASS: 55 files |
| PHPCS `--standard=phpcs.xml.dist -s` | PASS: no findings |
| `node --test tests/js/*.test.cjs` | PASS: 35 tests |
| `node --check` for all asset JavaScript | PASS |
| `php tests/release.php` | PASS: 44 checks |
| `php tests/integration/assets.php` | PASS: 12 checks |
| Foundation | PASS: 46 checks |
| Prefixing | PASS: 36 checks |
| Authoring | PASS: 118 checks |
| Structure | PASS: 110 checks |
| Progress | PASS: 145 checks, including concurrent requests/idempotency |
| Migration | PASS: 43 checks |
| Attempts/restart | PASS: 51 checks |
| Attempt history | PASS: 81 checks, including both version-upgrade baselines |
| Runner | PASS: 208 checks, including real HTTP and SE-017 hooks/gateway |
| Runner admin | PASS: 53 checks |
| REST | PASS: 163 checks, including HTTP/auth/nonce/cross-Sprint boundaries |
| Dashboard | PASS: 92 checks, including real HTTP and completed-card hook |
| Branding | PASS: 68 checks |
| Polish | PASS: 78 checks |
| Validation | PASS: 132 checks |
| `php tools/package.php`, `php tests/package.php` | PASS: 44 exact runtime files |
| Additional archive metadata/root/hook/old-header checks | PASS |
| Installed candidate/source byte comparison | PASS: all 44 runtime files |
| Documented SVN manifest/archive path comparison | PASS: all 44 relative paths |
| Final `git diff --check` and scope review | PASS |

The release test explicitly aligns plugin header/runtime constant/readme tag at
0.2.2 and checks its new changelog and newest-first ordering while retaining the
0.2.0/0.2.1 history assertions. Current-version literals in asset, migration,
prefixing and history tests were updated; no security test was weakened.

The unchanged SE-017 integration checks verify anonymous login redirect by
default; authenticated AccessManager denial; exact-boolean opt-in; generic
private/no-store/noindex 403 gateway with canonical Login return; action return
values granting no access; no private content/progress writes; unavailable and
malformed content exclusion; completed Runner/card actions; canonical attempts,
historical retention and normal restart/idempotency.

Browser checks executed against the initial 0.2.2 package:

- Existing Dashboard `read` at 1440/390px: PASS; authentication, denied-content
  isolation, eligible sections/cards, focus, image/progress/layout and Available
  navigation. PHP `verify-read` confirmed no progress mutations.
- Existing Dashboard `journey 390`: PASS; Continue/Save & Exit, completion CTA,
  cancel/failure/retry/restart to Attempt 2 and reclassification. PHP
  `verify-journey` confirmed exact historical progress and lifecycle retention.
- Existing Runner journey at 1440/900/390px: PASS; eight states, two independent
  users/nonces, keyboard/media/resume, completion and restart/second completion.
  PHP `retention` confirmed both completed attempts and six independent Step
  completions each, with exact deactivate/reactivate retention.
- Focused SE-017 browser harness at 1440/390px: PASS; completion/card hooks,
  existing controls, authenticated denial, anonymous private/noindex 403 gateway,
  canonical Login navigation, tampering/unavailable exclusion and no overflow.
  PHP verification confirmed unchanged progress.

Browser fixtures and temporary callbacks were cleaned up, and the loopback
server was stopped after validation. Generated screenshots and evidence stay
ignored. The temporary test databases are retained for reproducibility.

Plugin Check **2.1.0**: all **29 static checks** executed against the ZIP-installed
candidate, **0 errors / 13 existing warnings**, none skipped. Warnings remain
five prefixing conventions and eight database interpolation findings in unchanged
runtime code; no suppression was added. This is not WordPress.org approval.

## Readme synchronization correction — current release evidence

Confirmed clean `main` at the expected correction base
`53db26520cce84c483a8469f526eba5a0cc1a376` and created
`codex/release-0.2.2-readme-correction`. The user supplied
`sprint-engine-readme-0.2.2.txt` to reconcile marketing copy previously maintained
in WordPress.org SVN. The SVN repository was not accessed to obtain or validate
that copy; the provided local file is the source for this correction.

Copied the supplied file into `readme.txt` **byte-for-byte**, without rewriting
the marketing wording, punctuation, use cases or section structure. It restores
guided action platform positioning and the broader journey/workflow examples.
The approved tags, in order, are **training, learning, journeys, workflows,
onboarding**. The exact short description is:

> Turn WordPress into a guided action platform for onboarding, member journeys, training, implementation and more.

The complete 0.2.2 and 0.2.0 changelogs are unchanged from the correction base;
0.2.1 retains its existing entries and restores the supplied description-update
note. Version/Stable tag remain **0.2.2**, schema remains **2**, and all executable
runtime files, SE-017 hooks, CSS and JavaScript are unchanged. Only these three
files changed in the correction: `readme.txt`, `tests/release.php` and this record.

The release test now requires the exact five tags and exact approved short
description, while retaining the 150-character limit, version alignment,
historical/current changelog, privacy, screenshot and publication checks. No
existing assertion was removed or weakened. The supplied file, repository
readme and ZIP entry each contain **11,554 identical bytes**, UTF-8 without BOM
and with LF line endings. Their common readme SHA-256 is
`192d5d97a469f35e9ce62bf5d0899d32865b20bc6c726dd706a9dfc271c36207`.

Correction checks actually executed with the same PHP 8.3.35 / WordPress 7.1.2 /
official SQLite tooling and fresh disposable database snapshots per suite:

| Check | Correction result |
| --- | --- |
| Release metadata/copy tests | PASS: 45 checks, including exact short description |
| Complete PHP regression | PASS: all 17 Composer-manifest commands plus package test; counts match the initial table except release now 45 |
| PHP syntax | PASS: all 55 files |
| PHPCS repository ruleset | PASS: no findings |
| JavaScript unit tests | PASS: all 35 tests |
| Package build and exact archive/source verification | PASS: all 44 files |
| Supplied/repository/ZIP readme byte comparison | PASS: exact equality |
| Installed ZIP candidate/source comparison | PASS: all 44 files |
| Plugin Check 2.1.0 static checks | PASS: all 29 checks; 0 errors / 13 existing warnings |
| SVN manifest/archive path and base-manifest comparison | PASS: 44 paths; existing SVN instructions unchanged |
| Final diff and runtime protection review | PASS: only the three intended files changed |

The full rerun includes Runner **208**, Dashboard **92**, REST **163**, Progress
**145**, Attempts **51** and History **81** checks; all access/progress/SE-017
assertions remain intact. Evidence is ignored `.tools/readme022-*` logs/manifests.
Browser suites were not rerun for this copy-only correction; the earlier browser
results above remain historical evidence for unchanged executable code and UI.
No production or SVN operation, deployment, publication or Git tag was performed.

The current ZIP below **supersedes** the initial **93,006-byte** ZIP with SHA-256
`3a2e08d8a208b479beb6ae099b40b681c56fb268e16e19dfcfd199ce23c61893`.
Do not use that earlier archive for publication; it predates the reconciled copy.

## Verified ZIP

- Output: ignored `dist/sprint-engine.zip`, not committed.
- Top-level directory: `sprint-engine/`.
- Size: **92,941 bytes**.
- Entry count: **44 file entries**, no unexpected/missing runtime file.
- SHA-256: `b9e72761d398a6345d819c5c21db4b6d6cf9cf0649d243239992644e78798392`.
- Packaged main header/runtime constant: **0.2.2**; schema constant: **2**.
- Packaged readme Stable tag: **0.2.2**; minimum WordPress/PHP: **6.0 / 8.0**.
- Exact archive-to-source byte comparison passed. Archive checks also verify all
  four SE-017 hook names are present and no old 0.2.1 runtime header exists.
- `sprint-engine/readme.txt` exactly matches the user-supplied reconciled file;
  its byte count/hash and correction validation are recorded above.
- Existing allowlist/guards reject extra paths, duplicate entries, symlinks,
  missing runtime files, development fixtures, credentials and local user paths.
  Tools, tests, docs, dependencies and database backups are absent.

## Future WordPress.org SVN release manifest — instructions only

**No SVN repository was accessed during this ticket.** Do not execute the
publication steps without separate release authorization. Required destination
is the Sprint Engine plugin's SVN repository; no Git release tag is created here.

The verified ZIP contains `sprint-engine/`. Copy its **contents**, not that outer
directory, into SVN `/trunk/`. Root files are `sprint-engine.php`, `uninstall.php`,
`readme.txt`, `LICENSE`; directories are `src/`, `templates/`, `assets/` with exactly
the following relative file manifest (44 files):

```text
LICENSE
assets/css/dashboard.css
assets/css/runner-settings.css
assets/css/runner.css
assets/css/structure-admin.css
assets/js/authoring-validation.js
assets/js/dashboard.js
assets/js/runner-admin.js
assets/js/runner-settings.js
assets/js/runner.js
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
src/Progress/EnrolmentRepository.php
src/Progress/ProgressService.php
src/Progress/StepProgressRepository.php
src/Progress/Transaction.php
src/Rest/Controller.php
src/Runner/Availability.php
src/Runner/Routes.php
src/Runner/Runner.php
src/Settings/RunnerBranding.php
src/Settings/SettingsPage.php
templates/dashboard.php
templates/runner.php
uninstall.php
```

Do not copy `.git/`, `.tools/`, `dist/` or the ZIP itself, `docs/`, `tests/`,
`tools/`, `vendor/`, Composer files, root GitHub `README.md`, AGENTS.md,
CONTRIBUTING.md, SECURITY.md, IDE/OS/cache files, local configuration, database
files/backups, uploads, fixtures, screenshots or credentials into trunk/tag.
Existing SVN repository-level `/assets/` and old tags are outside this payload
and must remain untouched; plugin runtime `trunk/assets/` is included above.

Future authorized publisher checklist:

1. Verify the ZIP hash above before extracting. Start from a clean SVN working
   copy: `svn info` must identify the intended Sprint Engine repository;
   `svn status` must be empty. If unrelated changes exist, stop and preserve
   them. Update the clean working copy before preparing trunk.
2. Check the remote tags listing, for example
   `svn list https://plugins.svn.wordpress.org/sprint-engine/tags/`, and confirm
   **`0.2.2/` does not exist**. A failed network/authentication/listing operation
   does not prove absence. If the tag exists, stop; do not replace or retag it.
3. Make trunk's release files match the 44-file verified payload exactly using
   reviewed SVN additions/deletions only inside trunk. Review `svn status` and
   `svn diff`; no repository assets, older tags or unrelated content may change.
   Check main-plugin header/constant **0.2.2**, readme Stable tag **0.2.2**,
   schema **2**, minimum WordPress **6.0** and minimum PHP **8.0** again.
4. Compare trunk's sorted relative paths and SHA-256 for every release file with
   the verified ZIP payload; no extra or missing file is acceptable. After
   separate publication approval, commit the verified trunk and record its
   committed revision **R**. Export trunk at R (using `svn export --native-eol LF`
   when native line-ending properties apply) and repeat the 44-file/byte checks.
5. Create required **`/tags/0.2.2/` by SVN copy from that verified committed
   `/trunk/` at revision R**. Never manually populate the tag from another
   checkout, ZIP build or working tree. Pin the source revision so later trunk
   changes cannot enter the release tag. This copy is a publication action and
   was not executed in this preparation ticket.
6. Export the final tag and compare its sorted relative paths and every file
   hash to exported trunk at R and to the verified release payload. Review
   `svn diff --summarize` between those pinned trunk/tag revisions: there should
   be no release-file difference. Resolve any line-ending/property difference
   explicitly rather than silently accepting differing bytes. Recheck both
   version headers and schema, then confirm a clean SVN working copy.

Whether `/tags/0.2.2/` already exists remains deliberately unverified here.

## Initial release-preparation files and remaining limitations

- Metadata: `sprint-engine.php`, `readme.txt`.
- Current documentation: `AGENTS.md`, `README.md`, `docs/README.md`,
  `docs/development.md`, this `docs/validation/release-0.2.2.md`.
- Test expectations/coverage: `tests/release.php`, `tests/integration/assets.php`,
  `tests/integration/attempt-history.php`, `tests/integration/migration.php`,
  `tests/integration/prefixing.php`.

NOT RUN: MySQL/MariaDB-specific migration/locking/race execution, PHP 8.0 /
WordPress 6.0 minimum-runtime matrix, Plugin Check runtime harness, physical
devices/screen readers, and Hostinger/host/CDN cache acceptance. Local database
and concurrency results use official SQLite integration. Deployment, SVN
publication and Git release tagging are intentionally outside this ticket.
