# Planned Core patch release notes

Draft material for the separately authorised Core **0.3.1** release. This document
does not bump the plugin version, source Stable tag or schema, and does not
authorise packaging for publication, merging, deployment or WordPress.org upload.
Current published metadata remains **0.3.0**, schema **2**.

SE-031 is [completed discovery](discovery/se-031-participant-registration.md),
not an implemented feature or an addition to this patch's release scope. Account
forms and authentication changes require separate approval;
the feature freeze otherwise remains in effect.

SE-031.1's [disposable security/feasibility evidence](discovery/se-031-1-registration-spike.md)
is also documentation only. Its conditional recommendation does not add account
registration to this release scope; implementation, complete regression, owner
staging acceptance and separate release preparation remain required.

SE-031.2 is now an authorised, **unreleased** source slice: Participant Accounts
settings, scoped setup notices, generic branded guest screens, validated existing-
provider links and additive presentation classification. Built-in preference and
native-form checkbox are explicitly pending/dormant; native GET/POST account
creation is not intercepted. No new Core account/authentication endpoint exists.
See [guidance/contract](participant-accounts.md) and
[validation](validation/se-031-2-participant-access.md). This does not approve
including the registration journey in a release. After approved SE-031.3 and
SE-031.4 regression/owner staging acceptance, a separate release-preparation ticket
must review **all** unreleased changes, including Tutorial, metadata and package.

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
