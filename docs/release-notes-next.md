# Planned Core release notes

Draft material for separately authorised Core **0.4.0** release preparation after
SE-031.4 and owner acceptance. This does not bump plugin version, Stable tag or
schema, or authorise merging, deployment, publication or release. Current
published metadata remains **0.3.0**, schema **2**. Feature freeze otherwise remains.

SE-031 and SE-031.1 are merged [discovery](discovery/se-031-participant-registration.md)
and [security spike](discovery/se-031-1-registration-spike.md) evidence.
SE-031.2/3 are authorised **unreleased** source changes: Participant Accounts
settings, generic branded guest screens, safe provider returns, presentation
classification, conditional native WordPress registration and optional narrow
standard-form closure. Old pending preferences require explicit new security
confirmation; no automatic account creation, entitlement, attempt or Start.
Existing Pro without a classifier remains neutral sign-in. No Pro source changes
or coordinated release are required for existing member functionality.

See [accounts/lifecycle contracts](participant-accounts.md),
[operator security guide](participant-registration-security.md),
[SE-031.2 validation](validation/se-031-2-participant-access.md) and
[SE-031.3 validation](validation/se-031-3-native-registration.md).
Canonical return encoding preserves international percent-encoded slugs,
subdirectory homes and existing provider queries. Settings tabs remain deferred.
PR #31's database follow-up replaces the MariaDB-only transaction variable with
a MySQL/MariaDB savepoint probe and rejects replay-state deletion during the mutex
wait. It retains original-connection ownership and fail-closed storage contracts;
see [executed database evidence](validation/se-031-3-database-compatibility.md).

The existing Pro History retention/table-definition assertion failure remains an
**outstanding SE-031.4 release acceptance investigation**. It is also reproduced
on unchanged baseline; this does not satisfy the gate. Keep the assertion and
obtain investigation/retest evidence before acceptance. A separately approved Pro
classifier adapter, complete host/security/commerce/regression and owner staging
acceptance precede release preparation. Review all unreleased Tutorial and other
source changes, metadata and package together.

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
