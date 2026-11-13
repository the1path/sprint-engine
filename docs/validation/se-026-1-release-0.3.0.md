# SE-026.1 — Sprint Engine Core 0.3.0 release candidate

Validated on 13 November 2026.

## Decision

**GO for Core-only staging validation. NO-GO for WordPress.org publication.**
The Core package passed its regression and upgrade checks. The current Pro
baseline rejects Core 0.3.0 for Journey progress and My Journeys. A separate
Pro change and a passing combined regression run are required before a release
decision. Publication requires separate authorisation.

## Release identity

| Item | Verified identity |
| --- | --- |
| Published baseline | WordPress.org lists 0.2.2. The freshly downloaded 44-file ZIP matches the SE-026 audit ZIP. SHA-256 `4265ed643aa399bcffacaba68317db9b818818dd1bc3200319e5e92c294b58a76`. The audit identified published source commit `550fdafae0714945afc1df0342d9b45bd3c32380`. |
| Starting point | The clean local SE-026 branch and the published `the1path/sprint-engine` branch both pointed to `daddd12f0d6cf94918678a3c46f0d886c72e2322`. |
| Tested and packaged Core source | `60a9a63b658d1c17f6711089be110d398d698c22` on `codex/se-026-1-release-030`. The follow-up documentation commit carries the evidence for these same package bytes. |
| Candidate metadata | Plugin header, `SPRINT_ENGINE_VERSION` and Stable tag **0.3.0**. Schema **2**, WordPress **6.0+**, PHP **8.0+**, Tested up to **7.1**. |
| Pro baseline | `69592744f54340f1131e7e230bfa14ef0e19e18f` from `origin/develop/0.1`, Pro **0.1.0**. A Git archive supplied the verified 112-file Pro package. The Pro repository stayed clean. |

[Published listing](https://wordpress.org/plugins/sprint-engine/) ·
[Versioned 0.2.2 download](https://downloads.wordpress.org/plugin/sprint-engine.0.2.2.zip) ·
[SE-026 historical audit](se-026-release-0.2.3.md)

## Why 0.3.0 and how to migrate

The historical SE-026 audit rejected publishing as 0.2.3 without a decision on
the breaking write contract. Core 0.3.0 signals that change before 1.0. The
candidate keeps the attempt check so a stale action cannot affect a newer run,
even if the same Step ID appears in both attempts. Since the audited source,
only the plugin version and readme changed in the runtime package. Core compares
the schema and minimum runtimes independently of the plugin version. The
attempt logic, ownership and access checks, transaction locking and REST v1
route paths are identical to the audit.

On 0.2.2, authorised bodyless Complete Step and Restart requests were valid.
On 0.3.0, both require the displayed attempt's positive `expected_attempt_id`.
An authorised request for an available resource gets HTTP 400 for a missing or
invalid identity and HTTP 409 for a stale identity or progress conflict. Direct
`ProgressService` callers must supply the captured ID as the final argument.
`get_attempt_history()` can return `restarted` for a superseded active attempt.
Refresh tabs opened before the upgrade. On conflict, reload or re-render and
let the participant choose again. Keep the identity with the displayed view
through submission. Looking up the latest attempt at write time defeats the
protection. See the [developer migration guide](../migration-0.3.0.md) for the
route table, payload and PHP examples.

## Upgrade and data retention

The native WordPress `Plugin_Upgrader` installed and activated the published
0.2.2 ZIP and replaced it with the 0.3.0 ZIP using `overwrite_package`. Both
disposable environments passed 56 checks each.

| Runtime | Result |
| --- | --- |
| WordPress 7.1.4 / PHP 8.3.35 / official SQLite integration | Native replacement retained activation. First bootstrap recorded Core 0.3.0 and schema 2. |
| WordPress 6.0 / PHP 8.0.30 / MariaDB 10.11.11 InnoDB | Same native upgrade and retention result at the supported floors. |

Exact before/after snapshots matched Sprint and Step posts and metadata,
attempt and Step progress rows, timestamps, table columns and indexes, and
saved branding. Two completed attempts and the active resume position survived.
The Dashboard kept its member states and private cache headers. Rejected
legacy, malformed and stale writes preserved the rows and timestamps. The
Core REST suite also checked both protected operations over real cookie/nonce
requests, cross-Sprint validation and access boundaries.

After a Step insertion, reordering and removal, a stale view received 409 and
resume kept its attempt and found the earliest unfinished Step. A valid restart
changed only the old run's status to `restarted` and allocated a replacement.
A stale restart could not allocate another attempt. The replacement completed
normally. Deactivation and reactivation preserved the resulting data.

## Core and Pro compatibility

| Area | Result on Core 0.3.0 with the archived Pro baseline |
| --- | --- |
| Workspace | Response persistence, attempt isolation, read-only history, stale writes and the native HTTP save/restart sequence passed. Concurrent save and restart serialised in both orders. The optional Core/Pro Step recovery suite passed all nine checks. |
| Sprint History and Workbook | Owned current and historical records rendered, including `restarted` Workbooks. Privacy, native XLSX HTTP download, print and retained response checks passed. |
| Journey progress and My Journeys | **Blocked.** Pro `Journeys\Progress::compatible()` rejects `>= 0.3.0` before inspecting the Core schema/API. The progress integration returned `sprint_engine_pro_journey_compatibility`. The Hub progress and My Journeys suites failed, including the expected My Journeys rewrite registration. Public Hub views still render. |
| Dashboard | Core's extension seams, Sprint History actions and contextual Pro `View Journey` card links passed. My Journeys' personalised Dashboard integration shares the Journey progress gate and is unavailable. |
| Navigation and unsaved Workspace warnings | The live Pro browser fixture exercised 11 Hub, History and Workbook views/states, with and without a logo at 320–1440 px and a separate subdirectory home URL. It confirmed shared Core navigation, keyboard and same-tab behaviour, exact saved rows, and the unsaved Workspace warning on both Runner website links. The fixture omits My Journeys when its capability is unavailable. |
| Recommendations | The live Gutenberg, Classic and member browser tests passed with Core 0.3.0, including one Runner card after the original completion controls. The unmodified Pro PHP integration test rejects versions outside its explicit `0.2.2`/`0.2.3` fixture allowlist. The runtime feature floor is `>= 0.2.2`, and it selects the follow-up hook for `>= 0.2.3`. Keep that introduction threshold when extending Pro's PHP test coverage to 0.3.0. |

The global Pro minimum remains Core 0.2.0. History uses capability detection,
Workspace writes require Core 0.2.1 and the attempt-history API, and the shared
navigation adapter detects the Core helper by callable. With the same Pro ZIP,
eight integration scripts passed on published Core 0.2.2, including Workspace,
History, XLSX generation, recommendations and the expected Journey fallback.
Core 0.2.1 passed activation, Workspace, History, and the recommendation/Journey
fallbacks. Core 0.2.0 passed activation, Journey fallback and all three Workspace
read-only families. The 0.2.0 recommendation fallback fixture also asserts
History availability, a capability introduced in 0.2.1. That assertion failed
on 0.2.0, consistent with the separate History feature floor.

## Readme and Plugin Check

The marketing sections and the 0.2.2, 0.2.1 and 0.2.0 changelog entries are
byte-identical to the SE-026 readme. The 0.3.0 changelog names the breaking REST
and PHP integration change. The official online validator returned only an
informational note for the absent donate link. The WordPress.org Directory
parser and Markdown renderer rendered the 190-character `0.3.0` Upgrade
Notice verbatim, within the 300-character notice budget. They also verified
the 126-character short description, headings, lists, installation, all ten
FAQs, five screenshot captions, privacy section and changelog. Release
metadata checks passed. The notice's display on the live Updates screen
depends on the published update offer and remains a post-publication check.

Plugin Check 2.2.0 via WP-CLI 2.12.0 reported **0 errors and 14 warnings**.
The five explicit runtime performance checks reported no findings. The static
warnings match SE-026 by path and code. There were eight dynamic SQL identifier
warnings (Installer three, EnrolmentRepository two, StepProgressRepository
three), three shared `DONOTCACHEPAGE` constant warnings, a fixed buffered
lifecycle-hook warning in ProgressService, and two warnings in Runner
Presentation for `the_content` and a temporary global selected from a fixed
list. The existing WordPress 6.0 SQL compatibility and warning review are
documented in the [SE-026 audit](se-026-release-0.2.3.md#plugin-check-and-security-review).

[Readme Validator](https://wordpress.org/plugins/developers/readme-validator/) ·
[WordPress.org readme example](https://wordpress.org/plugins/readme.txt) ·
[Directory parser](https://meta.svn.wordpress.org/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme/class-parser.php)

## Regression results

| Check | Result |
| --- | --- |
| Core package and release | `php tools/package.php`, `php tests/package.php` and `php tests/release.php` passed. 69 exact runtime files and 48 release assertions. |
| Core Composer test manifest | All 19 commands passed with a fresh disposable database per WordPress suite. 1,799 passing assertions, zero skips. Includes Runner/Dashboard HTTP, authoring, structure, progress, upgrades, attempts, restart, recovery, History, REST, branding, validation and prefixing. |
| Core coding and JavaScript checks | Composer strict validation and `composer check` passed. All 68 tracked PHP files and 21 JavaScript files passed syntax checks. Node reported 39 passing unit tests. |
| Core Chromium | All tracked suites passed on the current runtime. Covered Runner lifecycle, four Dashboard modes, branding, Gutenberg and Classic authoring, private Step Preview, typography including Classic fallback, completion hooks with and without an extension, in-progress restart and Step change recovery. The additional shared-navigation fixture passed. |
| Minimum runtime | Native replacement, REST (163 checks), Step change recovery (145), mid-Sprint restart (53), History (82), and Gutenberg Step Preview and retention all passed. |
| Pro standalone and package | All 16 Composer standalone scripts passed. Package verification matched all 112 files to archived source. |
| Pro coding and JavaScript checks | Composer strict validation, PHPCS, syntax checks for 141 PHP files and 30 JavaScript files passed. |
| Pro WordPress integration | 11 of 15 declared scripts passed on WordPress 7.1.2/PHP 8.3.35 with SQLite. Four failed as described above (`recommendations`, `journey-progress`, `journey-hub`, `my-journeys`). The targeted native restart and XLSX HTTP reruns also passed. |
| Pro Chromium | Shared navigation on WordPress 7.1.4 passed. Access admin fixtures, Workspace and editor fixtures, tables and table editor fixtures, History, typed fields and field editor fixtures, native Directory and Journey authoring, live recommendations and Journey links on the Core Dashboard passed. History HTTP header and routing checks passed with controlled data. The full personalised Hub and My Journeys browser suites could not start because their fixture setup requires working Journey progress. |

Native Pro Workspace, table and field editor Gutenberg acceptance is separate
from their passing editor API fixtures. Live commerce was outside the fixture
environment because WooCommerce, Subscriptions and Memberships were absent.
The browser run used Chromium on disposable localhost sites. Physical
devices, other engines, assistive technology, physical printing and production
cache behaviour remain outside this run.

## Runtime package manifest

`php tools/package.php` builds `dist/sprint-engine.zip`. The tested ZIP is
**536,386 bytes** with SHA-256
`3b61f6ff50cea87a24fba8e5ed5caca25bc2b1bb67d0d47bd18f74b7a89ca2b7`.
All 69 entries matched the tested source and all six installed copies byte for
byte. Paths below are relative to the single `sprint-engine/` ZIP directory.
Compare extracted paths and hashes when rebuilding or distributing the ZIP.
Container timestamps or compression can change its outer hash.

~~~text
edaef632cbb643e4e7a221717a6c441a4c1a7c918e6e4d56debc3d8739b233f6  LICENSE
4ee0097d6898d0f525f45cbf83c84a466710d72e3ce91fe05047a15fadc7e4de  assets/css/dashboard.css
320d9586caca6172ae8ea9ce011e130069039ff40bd24a1583f30bf748b17942  assets/css/runner-settings.css
3778530ee3923214fa6c5dd66c8d95c027e340b5261a2fbf6afae7442a61c718  assets/css/runner.css
78a770cb80066e26244cff3c962a9334a19b8417c9411d78b18c0b007ad946ea  assets/css/structure-admin.css
29e7e7506e120f2f9e78d248cd5b06a6ec48ed54937a4bcff87fe760400d5838  assets/css/website-navigation.css
3b0a5fca3d17942cde889069889dedbbbd075e9b599968c82a95f4d944e9b345  assets/fonts/inter-OFL.txt
7291b5970da2237441273c03b424a504b70b18f09791473fab99687dcc314720  assets/fonts/inter-latin-wght-italic.woff2
3100e775e8616cd2611beecfa23a4263d7037586789b43f035236a2e6fbd4c62  assets/fonts/inter-latin-wght-normal.woff2
9648253641ec1d1f8ba2fbe8997909f7bfc48cc38537946f6272321788465b84  assets/fonts/lato-OFL.txt
bca1d88ada544d9c80872d4da27133fab6d347361fa26e932b47ec9559088fd0  assets/fonts/lato-latin-400-italic.woff2
918b7dc3e2e2d015c16ce08b57bcb64d2253bafc1707658f361e72865498e537  assets/fonts/lato-latin-400-normal.woff2
6c84348296ebe2e2a0830c3962eb02156419d9bc76371c2eadaf7329d827d550  assets/fonts/lato-latin-700-italic.woff2
c447dd7677b419db7b21dbdfc6277c7816a913ffda76fd2e52702df538de0e49  assets/fonts/lato-latin-700-normal.woff2
9e7f12fafee50a9b4f7b3004c0fa58990805620d9debb0daf7f75de0d3ad5548  assets/fonts/open-sans-OFL.txt
186836b74ceac07b2764c07c0379420e3014efb30fe918461c235e0ef6cbc4a2  assets/fonts/open-sans-latin-wght-italic.woff2
d8e4fe0452aa2076429a9bb5d8757d00a994dd95986cf950e9a1a371b9a072a0  assets/fonts/open-sans-latin-wght-normal.woff2
5c4a92c8f8ce0ab211f4c72889d82383c9644546115d9ba39a4f2d5b8f37014b  assets/fonts/poppins-OFL.txt
50d0c1742d80ac71f4cde20e8c04d41a24806af342831f479938b527fbff0972  assets/fonts/poppins-latin-400-italic.woff2
7d93459d86585bfcdbb7e0376056226adb25821ee54b96236fe2123e9560929f  assets/fonts/poppins-latin-400-normal.woff2
4b0864712c6e7ca75f8c003f7bc1a9270af33d6becd4119463771593274c48d2  assets/fonts/poppins-latin-700-italic.woff2
9338e65fc077355c7a87ae0d64cc101e23b9bf8ad78ae65f0f319c857311b526  assets/fonts/poppins-latin-700-normal.woff2
84044c09058803d87a241bd28abb4ce30a433d7db9c27a04da3efad6a0ca539c  assets/fonts/roboto-OFL.txt
14f23757c6b41b4b6c0b967ca3fa74ff47a868728158373461c4e6e4b6368f19  assets/fonts/roboto-latin-wght-italic.woff2
1404ca348bd75ef836f4dd8b6f2cc719458642d1237c368296b2fc652dca47dc  assets/fonts/roboto-latin-wght-normal.woff2
91a347f3ac48206e1e7ce4c59b3fdbe20ffb4eba00946b59227d6c5ffef3f3b0  assets/js/authoring-validation.js
dc4d17ea72dd6abf8e5c3149982a91bd6026cc15fde5cc4069c5ace1de83e73e  assets/js/dashboard.js
624edc0d1d8af60453bbd036557a45182c0c022a5bddf64acc064f34bd8b1deb  assets/js/runner-admin.js
fe260654d6174381c65bd5ea4cda4a9b50ab0f46f7700178334c966013bc1493  assets/js/runner-settings.js
8eb301bdc7f9cfc0e37a588e5c27dfd92b1f41d95546c2c4d5dc228aa236e80e  assets/js/runner.js
54fa10e58c19d19a3e5d0d60ccb3f35da9f9973edeaa62b1bdfd0cb1bfc73d12  assets/js/step-preview.js
7a37c00d57194027c6de3032cba00dd0ceed304c05c054b048f6fc5b203344dc  assets/js/structure-admin.js
b792d083b3c2348a747bdb62a1ae6cdd8fc04fb7bed6f4a64a6ad70771cf3dca  readme.txt
e93400a0a32da8f2776960c57e4eadc3d6756088386d60a535346664b52f1f18  sprint-engine.php
03429429d87c691cddb3fdf22511e8773d933f53104fe3d255fa58e05ed53421  src/Access/AccessManager.php
8a77cedeceba58293d6d502c808cbd36b264f6471b467e4b790b5334dfd1a773  src/Access/AccessProvider.php
b0bbdb6365d9d6ec19d706b7b98a898e8226a4f753c682c90b385f14c9e42159  src/Access/LoggedInAccessProvider.php
12c8078be38ebae96ef68bcf2d442b0caf14c978bc7e7fe6b591efe35ef96619  src/Assets.php
c87cf42ae6ccf29c126c8b72215ded36f488207a76f9b35ee3fdbc47ed946466  src/Content/Authoring.php
6c692ce59650a01ad193c46398708177f4033225d25485f55cb88ca4d3ded26b  src/Content/EditorRestController.php
efba493bcb4dc58bd249e4850192d244ab5859ecc93d578cb023beaae999272d  src/Content/Meta.php
f4b6eefe1dd9a4afb589c6167e16e3d2400704ce65499db972b81f85ab98104a  src/Content/RunnerAdmin.php
fc28d05d628eb47afe6dc7bc37ec3e125f5114e2fa7fbc8043e8690cd23aebed  src/Content/SprintPostType.php
0d94135bce9d045f8ff16d865970c188ad04f2332495fd4de2709daf036f89ea  src/Content/StepListAdmin.php
c89e2aa570f05972aa72f679eb455a45f857d74e36ed3d59e7f38ce9551b9c7f  src/Content/StepPostType.php
580e73ea048850eb0dc25ccc290862df51e004a432d460d9f4b091440ecb6042  src/Content/StructureAdmin.php
907161a14abc6c94bd3ba68d27b27c6653519272da76147b9f5298bd0afcc80b  src/Content/StructureManager.php
4a32cead18de86358a1c2d947ae3f961c8c00d313295e93c84f0c5d6351d1716  src/Dashboard/Dashboard.php
f46f2f7c13d29cd722855fb5fd699aa77f0b0c77c31a4f255a4fff8f51d5f402  src/Dashboard/Routes.php
adff32e7bd81a26d7978b03c3c032eb15c2cdd8794033ba2d63e1dc55827e4d2  src/Database/Installer.php
80f025ba8719c7dc5bab0371f3f478796729016e05b726434a185faf4fc5362a  src/Database/Migrations.php
cd64457d4d4b8141a056c2d76e8f1054d7a0c6901d7d76b763425b53004b9144  src/Plugin.php
cf2a43d5c8140715155bdc1c87b1ba5d466a47e2d16d190658e17d17942f53cb  src/Presentation/WebsiteNavigation.php
1271296b7a9d76d07a61f61d537cf69c72cf178d2e54e3133d8c57f96cac18d9  src/Progress/EnrolmentRepository.php
54ccc07ef2faa8ef3ca7ce9c4210a9edb0e6fce5d11fb8f60d855db192fc56e6  src/Progress/ProgressService.php
7df1356e7033ef2ca181b33675cc2c34c99665ff4ff1ed8c27b13449ab1dad6b  src/Progress/StepProgressRepository.php
83279d284d0af4a1c1ea7a9bd4bffe09e0ff5b88acf21823dafb33471edf1153  src/Progress/Transaction.php
ee8bfadf79fc9c0892540d377266d86cf2fdbbb310fc197ead178ce9ba019912  src/Rest/Controller.php
92ab0d0997425fe1ad55c3482b9c2a803f5ac6ed5d21d62ab41dfb2bee67a174  src/Runner/Availability.php
7b7bd9aa01ef421df7b96e63598cbc0c254774bdd8d9f6edb281c10245394500  src/Runner/Presentation.php
7def61ae6b98be31b63a43336fd2236bacad63cec2482e630b78fb54e309a402  src/Runner/Routes.php
2771044dce6ce654cf7e965117d7411a640b61236c714d535eeb818abb5f2550  src/Runner/Runner.php
a11f6d91072880ab9e9bc47f91ef6c9708e09a8be0eeb3878ff36b3f455361a3  src/Runner/StepPreview.php
9e44c0457af569128d3b4c098817ea8216ef2b13d70673e13400facf5569b7d4  src/Settings/RunnerBranding.php
fb7e01249aa3e6e3c33d67bb138a63d905271a33207ad8d0e78b5d36c3f67bf2  src/Settings/RunnerTypography.php
85c8a3f3dd3f2c08aec11c8b2dc84e97894b6b16ed793b4cd7e6d9494dc9eaec  src/Settings/SettingsPage.php
b79eb9b2cd2b04abfc51306b000200dfeb503c72fabdf42b91d491ba26a24a64  templates/dashboard.php
1df8183beb3d0c2a0be2d479f7237860e1b6da330aa293d3129aba437806ef7b  templates/runner.php
abdae47533251be5974fea0d2ab3e6971e599d2e35ebd216e718cc328c771224  uninstall.php
~~~

## Outstanding staging checks

1. Resolve the Pro Journey capability gate and cover 0.3.0 in the Pro tests,
   including the recommendations fixture. Recheck older supported Core versions.
   Run the combined Journey Hub, My Journeys, Core Dashboard and Workspace
   regression with the resulting exact Pro commit.
2. Update site-specific REST and PHP callers using the migration guide. On
   staging, exercise previously opened tabs, stale requests, fresh authenticated
   actions and Dashboard/Runner resume through the site's actual cache and
   integration configuration.
3. Verify WordPress activation and the upgrade on staging with the intended
   Core/Pro combination and configured access providers. Include paid access
   and subscriptions where those integrations are installed. Confirm the
   published update notice through WordPress's normal update check after any
   authorised publication.

## WordPress.org SVN publication instructions

Use this plan after a GO decision and explicit publication approval.

1. Check out a clean [Sprint Engine SVN working copy](https://plugins.svn.wordpress.org/sprint-engine/).
   Confirm the current stable version, commit access and the absence of
   `tags/0.3.0/`. Confirm the account's Release Management recipients and
   approval requirements if Release Confirmation is enabled.
2. Put the contents of the approved ZIP's `sprint-engine/` directory directly
   in `trunk/`, including runtime CSS, JS, fonts and licences under `trunk/assets/`.
   Compare the sorted relative paths and SHA-256 values with this manifest.
   Review `svn status` and `svn diff`, including any added or removed paths.
   Preserve the repository-level `assets/` listing banners, icons and five
   screenshots in their existing order.
3. In the working copy, use `svn copy trunk tags/0.3.0`. Confirm both trees
   match the approved runtime manifest, including the `Stable tag: 0.3.0`,
   changelog, Upgrade Notice, PHP header and schema. Commit the trunk update
   and tag together. If Release Confirmation is enabled, the authorised
   committer completes it in Release Management.
4. Verify the directory listing, versioned download, changelog and plugin
   information. Compare the downloaded ZIP's extracted files to this manifest.
   Check that a disposable 0.2.2 site receives the 0.3.0 update offer and
   displays the notice. Smoke test install, upgrade and the accepted Core/Pro
   combination. Preserve a database and plugin snapshot before real-site
   updates. Any rollback plan must account for work recorded after upgrade,
   including replacement attempts and the `restarted` status under schema 2.

[Using Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/) ·
[Directory assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/) ·
[Release Confirmation](https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/)
