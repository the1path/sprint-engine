# PUBLIC-001 — Public repository preparation

Validation date: **7 October 2026**. Branch: `codex/public-001-repo-tidy`.
The branch already existed and the working tree was clean before this ticket.
The changes are ready for diff review; no merge, push or release operation was
performed.

## Changes and sanitisation

The public README introduces the guided-action product before implementation
details and links to WordPress.org installation, development, contribution and
security guidance. Feature claims were checked against the existing content,
progress, Runner, Dashboard, REST and branding implementation. No suitable tracked
logo or screenshots existed, so no imagery was added.

The WordPress.org listing was verified via its
[official regional directory listing](https://en-au.wordpress.org/plugins/sprint-engine/),
which identifies the same plugin, author and 0.2.0 release. The README uses the
canonical directory URL. Direct access to that canonical page was unavailable to
the browser verification tool; the regional listing confirms the plugin slug.

Detailed authoring instructions, internal service contracts, attempts, Dashboard,
branding, compatibility limits and test setup were preserved in the development
guide. Named customer/hosting references and absolute machine-specific archive
paths were generalised. Agent instructions now describe stable open-source Core
and current-ticket development rather than the first foundation ticket.

Older validation records have a visible historical notice. Their test outcomes,
dates, hashes, changelog quotations, pre-release stages and unrun checks remain
historical evidence. The v0.1 specification is explicitly a baseline; its original
future-version targets are not current release commitments.

## Every changed file

All paths below are repository-relative. Only Markdown files changed.

| File | Change |
| --- | --- |
| `README.md` | Replace the engineering front page with product, installation and contributor guidance. |
| `AGENTS.md` | Generalise identity/deployment, update stable version and ticket workflow, retain architecture and compatibility guidance. |
| `CONTRIBUTING.md` (new) | Add lightweight issue, pull request and test guidance. |
| `SECURITY.md` (new) | Add private reporting guidance that also covers the feature being unavailable. |
| `docs/README.md` (new) | Index current developer guidance and historical evidence. |
| `docs/development.md` (new) | Preserve and sanitise technical content from the old README. |
| `docs/specifications/sprint-engine-v0.1.md` | Generalise customer context, wireframe and architecture rationale; label the baseline and historical first ticket. |
| `docs/validation/se-001.md` | Clarify historical scope. |
| `docs/validation/se-002.md` | Generalise deployment references and clarify historical scope. |
| `docs/validation/se-002-1.md` | Generalise deployment references and clarify historical scope. |
| `docs/validation/se-003.md` | Generalise deployment/backend references and clarify historical scope. |
| `docs/validation/se-004.md` | Generalise deployment/cache references and clarify historical scope. |
| `docs/validation/se-004-1.md` | Generalise deployed-site authoring references and clarify historical scope. |
| `docs/validation/se-005.md` | Generalise deployment references and clarify historical scope. |
| `docs/validation/se-006.md` | Generalise deployment references and clarify historical scope. |
| `docs/validation/se-007.md` | Generalise hosting/cache/acceptance references and clarify historical scope. |
| `docs/validation/se-008.md` | Generalise deployment references and clarify historical scope. |
| `docs/validation/se-009.md` | Generalise deployment/rendering references and clarify historical scope. |
| `docs/validation/se-010.md` | Generalise deployment references and clarify historical scope. |
| `docs/validation/se-010-1.md` | Generalise deployment references and clarify historical scope. |
| `docs/validation/se-010-2.md` | Generalise deployment references and clarify historical scope. |
| `docs/validation/se-011.md` | Generalise customer/deployment references and clarify the historical release candidate. |
| `docs/validation/se-012.md` | Generalise manual acceptance/deployment references and clarify historical release status. |
| `docs/validation/se-013.md` | Generalise manual acceptance/deployment references and clarify historical release status. |
| `docs/validation/se-014.md` | Generalise deployment/backend references and clarify historical scope. |
| `docs/validation/se-015.md` | Generalise deployment and archive-path references; clarify historical release preparation. |
| `docs/validation/wporg-review-001.md` | Generalise deployment and archive-path references; clarify historical review scope. |
| `docs/validation/wporg-review-001-inventory.md` | Clarify that the generated inventory is a historical review snapshot. |
| `docs/validation/public-001.md` (new) | Record this ticket's changes, verification and owner follow-ups. |

## Verification

| Check | Outcome |
| --- | --- |
| Final diff scope and whitespace | Only Markdown modifications/additions; `git diff --check` passed. |
| Local Markdown links | All local file targets resolve, including README links; new README anchor targets were checked against headings. |
| Release metadata, `php tests/release.php` | PASS: 38 assertions; plugin/header/Stable tag 0.2.0, schema 2, licence and directory readme unchanged. |
| Assets, `php tests/integration/assets.php` | PASS: 12 assertions. |
| PHP lint | PASS: all 54 tracked PHP source/test/tool files. |
| PHPCS | PASS: zero errors/warnings with `phpcs.xml.dist`. |
| Node, `node --test tests/js/*.test.cjs` | PASS: 35 tests, zero failures/skips. |
| Final source-tree privacy/credential scan | No named customer/hosting references, real developer paths, private domains or obvious credentials found; reviewed exceptions below. |
| Ignore exclusions | `git check-ignore` confirms `.env`, `wp-config.php`, `dist/sprint-engine.zip`, `.tools` helpers and `vendor` remain excluded. |

PHP checks used the existing local PHP runtime/extensions. PHPCS used the already
available official Plugin Check bundle's PHPCS/WPCS installation with this
repository's ruleset, equivalent to `composer check`. No dependencies were added
or changed and no Composer installation was required.

WordPress database integration suites, browser acceptance, activation/deactivation,
package building, package verification and deployment were **not run** for this
documentation-only ticket. Runtime/lifecycle code did not change, and this ticket
requires leaving build/release artefacts and operational data untouched. Historical
results in earlier reports are not claimed as new execution.

## Remaining references and secret scan

The scan covers tracked files and non-ignored untracked source files in the current
working tree. Git history, ignored local tools/test sites, dependencies and ZIPs
are excluded from the public source-tree scan and remain outside the deliverable.
No historical Git/PR cleanup was attempted.

Scanned categories include GitHub tokens, Stripe secret keys, AWS access keys,
private-key headers, database passwords, WordPress keys/salts, assigned API/auth
secrets, bearer credentials, JWTs and credentials embedded in URLs. Four assigned
password candidates are deliberate WordPress **post-password** fixture values in
`tests/integration/dashboard.php`, `tests/integration/runner-admin.php` and
`tests/integration/runner.php`; they are not account/database credentials. No plugin
or test code was changed to silence these matches.

One email address remains in `composer.lock`: a public third-party dependency
author's open-source contact metadata. It is retained because it belongs to the
dependency lockfile, not private project correspondence. Public plugin attribution,
the namespace and repository-owner identifiers also remain appropriate. All URL
hosts were reviewed: official documentation/source/funding services and intentional
example, invalid-URL and cross-origin test fixtures; no private domain was found.
Generic paths and loopback addresses describe disposable test setup, not a real
developer's machine. A broad path-pattern match in a test assertion was descriptive
text about WordPress query flags, not an absolute filesystem path.

### Final strict privacy pass

A subsequent read-only pass covered **all 104 tracked files** and **all five new
public files awaiting commit**. Every file was readable text; no binary file was
skipped. Case-insensitive checks included singular/plural customer-name variants,
spaces, hyphens, underscores, concatenated names and domain suffixes, with matching
across line breaks. Filenames were checked as well as file contents. Checks for
customer/implementation provenance wording, the named hosting
provider, developer identity and absolute personal/local paths returned zero
matches. All extracted URL hosts were reviewed again; no private domain was found.
Independent `git grep` checks also returned no matches. No historical report was
exempted, and no additional sanitisation was needed. The protected-file diff check
confirmed runtime, version, dependency, build and release files remain unchanged.

## Runtime and release confirmation

No PHP, JS, plugin CSS, templates, database schema, REST endpoints, Sprint/Step data
structures, version numbers, licensing, Composer configuration/dependency versions,
build or release behaviour changed. `readme.txt` remains byte-for-byte unchanged
in Git. Core remains **0.2.0**, schema **2**. No build artefact, tag, branch deletion,
history rewrite, force push, merge or publication operation was performed.

## Suggested repository-owner actions

These are suggestions only; none were performed:

- Set the repository description to “Turn WordPress into a guided action platform.”
- Set the website to the WordPress.org plugin listing.
- Add relevant topics such as `wordpress`, `wordpress-plugin`, `workflow`,
  `onboarding`, `training` and `open-source`.
- Enable GitHub private vulnerability reporting and sensible `main` branch protection.
- Review and optionally delete stale merged branches.
- Review this diff, merge when approved, and change visibility from Private to Public
  when ready.
