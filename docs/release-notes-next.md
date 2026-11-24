# Planned Core patch release notes

Draft material for the separately authorised Core **0.3.1** release. This document
does not bump the plugin version, source Stable tag or schema, and does not
authorise packaging for publication, merging, deployment or WordPress.org upload.
Current published metadata remains **0.3.0**, schema **2**.

- SE-028: add the presentation-only after-Step content action for an authorized
  in-progress Runner. See [its contract and validation](validation/se-028-runner-step-content-hook.md).
- SE-029: correct Runner heading hierarchy and native Gutenberg Accordion styling,
  preserving authored typography. See [validation](validation/se-029-runner-heading-accordion-styles.md).
- SE-030: offer an optional first-install Tutorial Sprint with six original,
  actionable Steps for building a separate Sprint. Activation/upgrade creates no
  sample content; Welcome and Settings require explicit opt-in. Installation is
  atomic and idempotent, supports later explicit restoration/reinstall and keeps
  authored edits. The published example is accessible only to signed-in WordPress
  administrators, even with external entitlement grants. See
  [validation and staging checklist](validation/se-030-first-install-tutorial.md).
- SE-030.1: rewrite the bundled tutorial around building, publishing and
  experiencing a first three-Step Sprint, with verified native editor navigation,
  portable new-tab links and a tutorial-only celebratory completion. Existing
  installed tutorials retain their copy; use a fresh disposable site for review.

For release preparation, adapt these bullets into `readme.txt` only after release
approval; retain the public 0.3.0 changelog until then. No schema migration is
required for these source changes. On combined Core/Pro sites, verify the
[tutorial marker contract](tutorial-marker-contract.md) and PRO-018's public
Directory/Collection/Journey exclusions before rollout.
