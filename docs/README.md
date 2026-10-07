# Sprint Engine developer documentation

Core **0.2.0** is the stable open-source release. The root
[README](../README.md) introduces the product and installation; this directory
preserves technical guidance and validation evidence.

- [Development guide](development.md): authoring, service boundaries, attempts,
  Dashboard, REST integration, branding, installation and test commands.
- [v0.1 architecture specification](specifications/sprint-engine-v0.1.md): the
  original linear architecture and acceptance criteria. It is a baseline, not a
  current feature list or a commitment to future release versions.
- [AGENTS.md](../AGENTS.md): engineering conventions and ticket workflow.
- [Contributing](../CONTRIBUTING.md) and [security reporting](../SECURITY.md).

## Implementation and validation records

Reports in `validation/` record what was tested for each ticket at that time.
Unrun checks and historical release stages remain evidence of those tickets;
they do not describe the current publication status. Hosting and customer names
have been generalised without changing the recorded test outcomes.

| Topic | Records |
| --- | --- |
| Foundation and lifecycle | [SE-001](validation/se-001.md) |
| Content and ordered structure | [SE-002](validation/se-002.md), [SE-002.1](validation/se-002-1.md) |
| Progress and recovery | [SE-003](validation/se-003.md) |
| Runner and access | [SE-004](validation/se-004.md), [SE-004.1](validation/se-004-1.md) |
| REST writes and Runner UX | [SE-005](validation/se-005.md), [SE-006](validation/se-006.md) |
| Hardening and acceptance matrix | [SE-007](validation/se-007.md) |
| Runner branding | [SE-008](validation/se-008.md) |
| Completion message and CTA | [SE-009](validation/se-009.md) |
| Authoring and validation | [SE-010](validation/se-010.md), [SE-010.1](validation/se-010-1.md), [SE-010.2](validation/se-010-2.md) |
| Directory review and identifiers | [SE-011](validation/se-011.md), [review](validation/wporg-review-001.md), [inventory](validation/wporg-review-001-inventory.md) |
| Attempts and restart | [SE-012](validation/se-012.md) |
| Member Dashboard | [SE-013](validation/se-013.md) |
| Step publication and onboarding | [SE-014](validation/se-014.md) |
| Initial public release preparation | [SE-015](validation/se-015.md) |
