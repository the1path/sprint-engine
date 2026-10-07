# AGENTS.md — Sprint Engine

This file contains standing engineering instructions for Codex and any other coding agent working in this repository.

## 1. Project identity

- Product name: **Sprint Engine**.
- Sprint Engine Core is the **open-source WordPress plugin**; the current stable version is **0.2.0**.
- Sprint Engine is a standalone WordPress plugin and must not contain customer-specific business logic, branding, copy, URLs, payment logic, or assumptions unless a ticket explicitly requires an integration layer.
- Core is independently packageable. Keep customer-specific integrations and commercial extension logic separate from Core unless the current ticket explicitly defines an integration boundary.

## 2. Source of truth

Before implementing a ticket, read:

1. `docs/specifications/sprint-engine-v0.1.md`
2. This `AGENTS.md`
3. The current ticket/issue or user instruction

The v0.1 specification remains authoritative for the baseline architecture. Read `docs/development.md` and relevant validation records for subsequent implemented changes, including 0.2.0 attempts/restart and the member Dashboard. The current ticket/issue defines the work; do not silently expand its scope.

If a ticket appears to conflict with the specification, stop and report the conflict before changing architecture.

## 3. Cardinal scope rule

**Do not implement future features merely because the specification mentions them.**

The core journey established by v0.1 remains:

`create -> start -> work one Step at a time -> save -> resume -> complete`

Features explicitly deferred from v0.1 include branching/Decision Steps, visual Sprint Builder, WooCommerce access integrations, automation/re-engagement, outbound webhooks, custom audio tracking/player, AI features, analytics dashboards, import/export, quizzes, certificates, gamification, and SaaS/multi-tenant features.

These features remain outside Core 0.2.0 unless an explicit subsequent ticket authorises them. Design clean extension points where required, but do not build speculative features behind them. Completed-Sprint restart and the member Dashboard are already implemented; preserve their documented behaviour.

## 4. Architecture rules

- Build a **standalone plugin**. Do not place product logic in a theme or `functions.php`.
- Use the PHP namespace `ThePath\SprintEngine\` for plugin classes.
- Use custom post types `sprint_engine_sprint` and `sprint_engine_step` as defined in the specification.
- Use dedicated plugin tables for enrolment and Step progress. Never hard-code the `wp_` database prefix; always use `$wpdb->prefix`.
- Keep repositories responsible for persistence only. Put state-transition/business rules in services such as `ProgressService`.
- All access checks go through `AccessManager`; do not scatter membership/plugin-specific access calls through Runner, REST, or templates.
- REST controllers must call services rather than duplicating business logic.
- The canonical member route is `/sprint/{sprint-slug}/`; Step posts are not public member-facing pages.
- Store an explicit `next_step_id` even though v0.1 is linear. This is an architectural compatibility point for later branching.
- Use Gutenberg/native WordPress blocks for Step content. Native WordPress audio must work without a custom audio implementation in v0.1.
- Prefer simple WordPress-native solutions over adding frameworks or dependencies unless a ticket explicitly justifies them.

## 5. Coding standards

- Follow WordPress coding conventions unless a documented project rule overrides them.
- Write modern, readable PHP compatible with the project's documented PHP floor; do not raise the minimum PHP or WordPress version without an explicit decision.
- Use strict, descriptive class and method names. Avoid abbreviations that obscure intent.
- Keep classes focused; do not create a single monolithic plugin class.
- Escape output according to context and sanitise/validate input at write boundaries.
- Use prepared SQL via `$wpdb` for dynamic queries.
- Do not expose internal SQL, filesystem paths, stack traces, secrets, nonces, or sensitive debug data to end users.
- Do not commit secrets, credentials, `.env` files containing secrets, `wp-config.php`, production database dumps, or user uploads.
- Avoid unrelated refactors in feature tickets. Preserve backwards compatibility for persisted data, routes, metadata and documented hooks; report any necessary breaking change before implementation.

## 6. Security and permissions

- Treat Sprint content and progress as authenticated application data.
- Runner and member REST operations require an authenticated user in v0.1.
- Same-origin REST writes must use WordPress authentication and appropriate REST nonce handling.
- Verify access to the Sprint before returning private Step content.
- Verify every supplied Step belongs to the expected Sprint; reject cross-Sprint tampering.
- Users may mutate only their own progress unless an explicit administrative feature says otherwise.
- Admin mutations require capabilities; `is_admin()` alone is never authorisation.
- Validate IDs, enum-like values, Sprint/Step relationships, and state transitions server-side. Never trust the browser to enforce them.

## 7. Data and migration rules

- Plugin activation creates or upgrades plugin-owned tables using versioned, repeatable migration logic.
- Store plugin version and schema version separately.
- Migrations must be idempotent.
- Deactivation must not delete Sprint content or progress.
- Upgrades must never silently discard progress.
- Store operational timestamps in UTC.
- Do not add foreign-key constraints in v0.1.
- Do not repurpose or mutate historical progress data to make a new feature convenient.

## 8. Progress behaviour

- Completing a Step is explicit: it occurs only after the server successfully processes **Complete & Continue**.
- Viewing, scrolling, playing audio, or waiting does not complete a Step.
- Step completion must be idempotent; retries/double-clicks must not duplicate or overcount progress.
- `current_step_id` is the canonical resume position while an enrolment is in progress.
- Completing the final valid Step completes the Sprint and sets `current_step_id` to null.
- Scope progress to the canonical attempt. Restarting a completed Sprint creates a new attempt and retains previous progress; never overwrite historical attempts.
- If data becomes inconsistent, fail safely and prefer a recoverable state over a fatal error.

## 9. WordPress integration rules

- Register post types/meta on appropriate WordPress hooks.
- Register REST routes on `rest_api_init`.
- Load Runner CSS/JS only on Runner requests.
- Flush rewrite rules only when necessary, such as activation or a deliberate route-version change; never on every request.
- Render Gutenberg content through normal WordPress content/block rendering mechanisms rather than bypassing WordPress sanitisation/rendering.
- Do not require WooCommerce, LearnDash, LifterLMS, ACF, or another third-party plugin for Sprint Engine core v0.1.

## 10. Public extension surface

Where implemented by the current ticket/specification, preserve the documented public actions and filters, including:

- `sprint_engine/sprint_started`
- `sprint_engine/step_started`
- `sprint_engine/step_completed`
- `sprint_engine/sprint_completed`
- `sprint_engine/user_can_access_sprint`
- `sprint_engine/runner_template`

Do not invent a large public API speculatively. Add public extension points only when required and document them.

## 11. Tests and verification

Every implementation ticket must include appropriate verification.

At minimum:

- Run existing automated tests and static/coding-standard checks that are available in the repository.
- Add or update tests for changed business logic where practical.
- Verify activation/deactivation for lifecycle changes.
- Verify authentication, authorisation, idempotency, and cross-Sprint validation for relevant REST/progress changes.
- Do not claim a test passed unless it was actually run.
- If a required test cannot be run in the environment, state exactly which test was not run and why.

The v0.1 acceptance criteria in `docs/specifications/sprint-engine-v0.1.md` define the release-level behaviour.

## 12. Ticket workflow

For each ticket:

1. Read the spec sections relevant to the ticket.
2. Inspect the existing implementation before editing.
3. State any material assumption only if the repository/spec does not already resolve it.
4. Make the smallest coherent change that satisfies the ticket.
5. Add/update tests.
6. Run relevant checks.
7. Review the diff for accidental scope expansion, secrets, debug code, or unrelated changes.
8. Summarise:
   - what changed;
   - files changed;
   - tests/checks run and outcomes;
   - any remaining known limitation directly related to the ticket.

Do not begin the next ticket automatically unless explicitly instructed.

## 13. Git discipline

- `main` should remain in a deployable state.
- Prefer small, reviewable commits with meaningful messages.
- Do not force-push shared branches unless explicitly instructed.
- Do not rewrite published history as part of ordinary ticket work.
- Do not commit generated ZIP releases, local IDE metadata, dependencies/vendor output, test caches, or OS files unless the repository policy explicitly calls for them.
- Before committing, inspect `git status` and the diff.

## 14. Deployment safety

Deploy to a development or staging WordPress site first and verify the relevant acceptance behaviour before production deployment.

- Repository deployment must target only the Sprint Engine plugin directory, not the whole WordPress document root, unless the deployment architecture is deliberately changed.
- Do not modify `wp-config.php`, WordPress core, unrelated plugins, themes, uploads, or the database outside Sprint Engine's owned schema/content without explicit instruction.
- A successful Git deployment is not equivalent to a successful release: verify WordPress activation and the relevant acceptance behaviour after deployment.
- Never add production credentials to GitHub to work around deployment issues.

## 15. Current development target

Stable Core is **0.2.0**, schema **2**. Work from the current ticket/issue rather
than a historical implementation sequence. Keep changes tightly scoped and use
the existing implementation and relevant documentation to determine what is
already supported. Version, schema and release changes require explicit ticket
authorisation; documentation-only changes must not alter runtime behaviour.

## 16. Definition of a good change

A good Sprint Engine change is:

- inside the agreed ticket scope;
- secure at its trust boundaries;
- testable and tested where practical;
- independent of customer branding and theme code;
- understandable by the next developer/agent;
- compatible with the architectural extension points already locked by v0.1;
- no more complex than the current requirement needs.
