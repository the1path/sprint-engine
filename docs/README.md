# Sprint Engine developer documentation

Core **0.3.0** is the published WordPress.org release. See the
[historical candidate validation](validation/se-026-1-release-0.3.0.md).
SE-028, SE-029, SE-030, SE-031.2 and SE-031.3 are unreleased source changes and require separate publication
authorisation. The root [README](../README.md)
introduces the product and installation. This directory preserves technical
guidance and validation evidence.

- [Development guide](development.md): authoring, service boundaries, attempts,
  Dashboard, REST integration, branding, installation and test commands.
- [0.3.0 migration guide](migration-0.3.0.md) for Complete Step, Restart and
  attempt history integrations.
- [v0.1 architecture specification](specifications/sprint-engine-v0.1.md): the
  original linear architecture and acceptance criteria. It is a baseline, not a
  current feature list or a commitment to future release versions.
- [AGENTS.md](../AGENTS.md): engineering conventions and ticket workflow.
- [Tutorial marker contract](tutorial-marker-contract.md): Core/PRO-018 access and discovery rules.
- [Planned release notes](release-notes-next.md): source changes awaiting release approval.
- [SE-031 participant registration discovery](discovery/se-031-participant-registration.md):
  account/access journey research and proposed tickets for owner review; no runtime
  implementation or release authorisation. Includes the archived issue specification.
- [SE-031.1 registration security spike](discovery/se-031-1-registration-spike.md):
  disposable current/floor account, security, MFA, Pro and real WooCommerce evidence;
  evidence underlying the conditional SE-031.3 production architecture.
- [Participant Accounts](participant-accounts.md): unreleased SE-031.2/3 settings,
  administrator/participant guidance, option lifecycle and guest classification contract.
- [SE-031.2 validation](validation/se-031-2-participant-access.md): actual settings,
  current/floor browser/native dispatch and Pro/commerce evidence; remaining gates.
- [Registration security setup](participant-registration-security.md): concise operator
  prerequisites, native closure consequences, fallback and reconfirmation.
- [SE-031.3 validation](validation/se-031-3-native-registration.md): native account,
  HTTPS, security, browser, compatibility and regression evidence; release gates.
- [SE-031.3 database follow-up](validation/se-031-3-database-compatibility.md):
  MySQL/MariaDB transaction preservation and replay-state deletion race evidence.
- [Contributing](../CONTRIBUTING.md) and [security reporting](../SECURITY.md).

## Implementation and validation records

Reports in `validation/` record what was tested for each ticket at that time.
Unrun checks and historical release stages remain evidence of those tickets;
they do not describe the current publication status. Hosting and customer names
have been generalised without changing the recorded test outcomes.

| Topic | Records |
| --- | --- |
| Foundation and lifecycle | [SE-001](validation/se-001.md) |
| Optional first-install Tutorial Sprint (unreleased) | [SE-030](validation/se-030-first-install-tutorial.md) |
| Content and ordered structure | [SE-002](validation/se-002.md), [SE-002.1](validation/se-002-1.md) |
| Progress and recovery | [SE-003](validation/se-003.md) |
| Runner and access | [SE-004](validation/se-004.md), [SE-004.1](validation/se-004-1.md) |
| REST writes and Runner UX | [SE-005](validation/se-005.md), [SE-006](validation/se-006.md) |
| Hardening and acceptance matrix | [SE-007](validation/se-007.md) |
| Runner branding | [SE-008](validation/se-008.md) |
| Runner headings and Gutenberg block compatibility (unreleased) | [SE-029](validation/se-029-runner-heading-accordion-styles.md) |
| Completion message and CTA | [SE-009](validation/se-009.md) |
| Authoring and validation | [SE-010](validation/se-010.md), [SE-010.1](validation/se-010-1.md), [SE-010.2](validation/se-010-2.md) |
| Directory review and identifiers | [SE-011](validation/se-011.md), [review](validation/wporg-review-001.md), [inventory](validation/wporg-review-001-inventory.md) |
| Attempts and restart | [SE-012](validation/se-012.md) |
| Read-only attempt history and Dashboard header hook | [SE-016](validation/se-016.md) |
| Member Dashboard | [SE-013](validation/se-013.md) |
| Step publication and onboarding | [SE-014](validation/se-014.md) |
| Initial public release preparation | [SE-015](validation/se-015.md) |
| Core presentation extension points | [SE-017](validation/se-017.md) |
| Core 0.2.2 release preparation | [Release 0.2.2](validation/release-0.2.2.md) |
| Post-completion follow-up hook (unreleased) | [SE-018](validation/se-018.md) |
| Core 0.2.3 development version (not release preparation) | [SE-018.1](validation/se-018-1.md) |
| Core 0.2.3 release review and candidate package | [SE-026](validation/se-026-release-0.2.3.md) |
| Core 0.3.0 release candidate and publication decision | [SE-026.1](validation/se-026-1-release-0.3.0.md) |
