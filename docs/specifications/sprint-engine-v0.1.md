<!--
This file is the repository source-of-truth specification for Sprint Engine v0.1.
The formatted DOCX is a companion presentation copy; engineering decisions should be updated here first.
-->

# Sprint Engine

## v0.1 Build Specification

> Architectural baseline for v0.1. Core 0.2.0 is now released; subsequent
> implemented behaviour is documented in [the development guide](../development.md).
> Future version targets below reflect the original plan, not a release commitment.

A focused WordPress engine for outcome-driven Sprints

> **Purpose:** Define the smallest buildable version of Sprint Engine that proves the core user journey: create a Sprint, run one step at a time, save progress, leave, and resume.

**Product:** Sprint Engine

**Specification version:** 0.1

**Status:** Build baseline

**Date:** 22 September 2026

# Executive Summary

Sprint Engine v0.1 is deliberately small. It is not an LMS replacement, a membership system, an email platform, or a visual workflow builder. Its job is to provide a focused guided-action layer for WordPress: structured Sprint content, a distraction-free Sprint Runner, persistent user progress, and a clean foundation for later branching, automation, access integrations, and productisation.

The v0.1 release proves one complete vertical slice. An administrator can create a Sprint containing ordered Steps. A logged-in user can start the Sprint, see one Step at a time, mark Steps complete, leave, return later, and resume from the correct point. Gutenberg provides the authoring surface, so Steps can already include text, images, video, files and native WordPress audio.

> **Scope discipline:** Branching Decision Steps are intentionally deferred to v0.2. However, the v0.1 data model stores explicit next-step relationships so branching can be added without restructuring the core product.

## Definition of done for v0.1

- The plugin installs and activates cleanly on a supported WordPress installation.

- Sprints and Steps appear in WordPress admin and can be authored using the block editor.

- A Sprint can define an ordered sequence of Steps.

- A logged-in user can start a Sprint in the distraction-free Sprint Runner.

- Completing a Step persists to the database and advances the user to the next Step.

- Returning to the Sprint resumes at the correct incomplete Step.

- Completing the final Step marks the Sprint complete.

- Core functionality is covered by repeatable acceptance tests and basic automated tests.

## Guiding principles

| **Principle**                  | **Implication for v0.1**                                                                                               |
|--------------------------------|------------------------------------------------------------------------------------------------------------------------|
| Thin engine                    | Do not rebuild commerce, memberships, CRM, email or LMS features that already exist elsewhere.                         |
| Product-ready foundation       | Use a standalone plugin, namespaced code, migrations, APIs and abstractions rather than implementation-specific shortcuts. |
| Beautiful Runner, simple admin | Prioritise the member experience. The first admin workflow may be functional rather than visually sophisticated.       |
| Own operational data           | Store progress in dedicated plugin tables rather than scattering it across user meta.                                  |
| Progressive enhancement        | Use Gutenberg content now; add richer custom blocks, branching, analytics and automation later.                        |
| Secure by default              | Validate capabilities, nonces/authentication, IDs and permissions at every write boundary.                             |

# Specification Contents

1. Data model

2. Custom post type definitions

3. Database schema

4. Plugin classes and file structure

5. WordPress hooks

6. REST endpoints

7. Authentication and security

8. Sprint Runner routing

9. Progress calculation

10. Resume behaviour

11. Admin editing experience

12. Frontend wireframe

13. Completion rules

14. Installation and upgrade behaviour

15. Acceptance tests

16. Explicitly excluded features

## Target platform and conventions

| **Area**             | **v0.1 decision**                                                                                                  |
|----------------------|--------------------------------------------------------------------------------------------------------------------|
| Platform             | WordPress plugin, developed independently of the active theme.                                                     |
| PHP                  | Use the minimum PHP version supported by the target hosting environment; target modern PHP 8.x during development. |
| JavaScript           | Vanilla JavaScript or minimal bundled JavaScript for Runner interactions. No SPA framework required in v0.1.       |
| Database prefix      | Use `$wpdb->prefix` at runtime; never hard-code wp\_.                                                              |
| Namespace / prefix   | PHP namespace ThePath\SprintEngine\\ database tables use {prefix}se\_\*; REST namespace sprint-engine/v1.                  |
| Repository           | Standalone Git repository containing only the plugin and its development/test tooling.                             |
| Initial access model | Logged-in users only during v0.1 testing; pluggable AccessManager interface is created from day one.               |

# 1. Data model

Define the minimum domain objects and relationships required to run a linear Sprint now while preserving a clean route to branching later.

## Core domain objects

| **Object**      | **Purpose**                                                                                            | **Storage**                                      |
|-----------------|--------------------------------------------------------------------------------------------------------|--------------------------------------------------|
| Sprint          | The outcome-driven programme the user starts and completes.                                            | Custom post type sprint_engine_sprint                       |
| Step            | One discrete unit shown in Sprint Runner. Content may contain Gutenberg blocks including native audio. | Custom post type sprint_engine_step                         |
| Enrolment       | One user's state within one Sprint.                                                                    | Dedicated table sprint_engine_enrolments                    |
| Step progress   | Completion state for a user/Step pair.                                                                 | Dedicated table sprint_engine_step_progress                 |
| Access provider | Answers whether a user may enter a Sprint.                                                             | PHP interface/service; no database table in v0.1 |

## Relationships

```text
Sprint 1 ──── * Step

User 1 ──── * Enrolment * ──── 1 Sprint

Enrolment 1 ──── * StepProgress * ──── 1 Step

v0.1 navigation:

Step A → Step B → Step C → Complete

future v0.2:

Step A → Decision → Step B / Step C → Step D
```

For linear v0.1 Sprints, Step order is the authoring input. Sprint Engine derives and saves explicit position, next-Step and start-Step metadata from the ordered Step list. Each Step's next_step_id remains the authoritative runtime navigation link; explicit links are retained so future Decision Steps and branching can build upon them in v0.2.

## Identifiers and slugs

- WordPress post IDs are the canonical IDs for Sprint and Step content.

- Database records use unsigned BIGINT-compatible IDs to match WordPress user/post identifiers.

- Sprint permalinks use the Sprint slug; Step posts are not intended as standalone public content.

- Internal code must not infer ownership from URL parameters alone; all IDs are validated against the relevant Sprint.

# 2. Custom post type definitions

Use native WordPress content primitives for authoring while keeping Sprint delivery inside Sprint Runner.

| **Setting**     | **sprint_engine_sprint**                                              | **sprint_engine_step**                                                |
|-----------------|------------------------------------------------------------|------------------------------------------------------------|
| Admin label     | Sprints                                                    | Sprint Steps                                               |
| Public          | No standalone theme-rendered public archive required       | No standalone public Step page                             |
| show_ui         | Yes                                                        | Yes                                                        |
| show_in_rest    | Yes                                                        | Yes                                                        |
| Editor          | Block editor                                               | Block editor                                               |
| Supports        | title, editor, excerpt, thumbnail (optional)               | title, editor                                              |
| Rewrite         | Sprint Runner handles front-end route                      | Disabled or non-public                                     |
| Capability type | Custom mapped capabilities preferred before public release | Custom mapped capabilities preferred before public release |

## Sprint metadata

- Estimated duration in minutes (optional).

- Short outcome/description.

- Start Step ID (`_sprint_engine_start_step_id`, derived from the ordered Step list).

- Status flag for whether the Sprint may be launched.

- Optional display image/icon for future dashboards.

## Step metadata

- Parent Sprint ID.

- Position / sort order (`_sprint_engine_position`, derived from the ordered Step list).

- Stage label (plain metadata in v0.1, not a separate content type).

- Estimated duration in minutes (optional).

- Step mode: content or task. Decision is reserved for v0.2.

- Next Step ID (`_sprint_engine_next_step_id`, derived from the ordered Step list). Null on final Step.

> **Audio support:** No custom audio field is required. Authors use WordPress's native Audio block inside Step content. Sprint Runner renders the normal block output. Enhanced listening progress and playback-resume events are deferred.

# 3. Database schema

Store changing user state in dedicated tables so progress queries remain predictable as usage grows.

## Table: {prefix}sprint_engine_enrolments

| **Column**       | **Type / intent**        | **Rules**                             |
|------------------|--------------------------|---------------------------------------|
| id               | BIGINT unsigned          | Primary key, auto increment           |
| user_id          | BIGINT unsigned          | Required; indexed                     |
| sprint_id        | BIGINT unsigned          | Required; indexed                     |
| status           | VARCHAR(20)              | not_started / in_progress / completed |
| current_step_id  | BIGINT unsigned nullable | Step to resume; null after completion |
| started_at       | DATETIME nullable        | UTC                                   |
| last_activity_at | DATETIME nullable        | UTC; updated on meaningful activity   |
| completed_at     | DATETIME nullable        | UTC                                   |
| created_at       | DATETIME                 | UTC                                   |
| updated_at       | DATETIME                 | UTC                                   |

Add a unique key on (user_id, sprint_id) so one user has one canonical enrolment per Sprint in v0.1.

## Table: {prefix}sprint_engine_step_progress

| **Column**   | **Type / intent** | **Rules**                   |
|--------------|-------------------|-----------------------------|
| id           | BIGINT unsigned   | Primary key, auto increment |
| user_id      | BIGINT unsigned   | Required; indexed           |
| sprint_id    | BIGINT unsigned   | Required; indexed           |
| step_id      | BIGINT unsigned   | Required; indexed           |
| status       | VARCHAR(20)       | started / completed         |
| started_at   | DATETIME nullable | UTC                         |
| completed_at | DATETIME nullable | UTC                         |
| updated_at   | DATETIME          | UTC                         |

Add a unique key on (user_id, sprint_id, step_id). The schema deliberately leaves room for an event table later without mixing event history into current-state tables.

## Database rules

- Use WordPress database APIs and dbDelta-compatible CREATE TABLE statements.

- Store dates in UTC and format for the user/site only at presentation time.

- Never delete progress merely because Sprint content changes; migrations must be deliberate.

- Do not use foreign-key constraints in v0.1; WordPress installations commonly avoid them across plugin tables.

# 4. Plugin classes and file structure

Keep responsibilities isolated enough for testing and later integrations without creating unnecessary framework complexity.

```text
sprint-engine/

├── sprint-engine.php

├── uninstall.php

├── src/

│ ├── Plugin.php

│ ├── Content/

│ │ ├── SprintPostType.php

│ │ ├── StepPostType.php

│ │ └── Meta.php

│ ├── Database/

│ │ ├── Installer.php

│ │ └── Migrations.php

│ ├── Progress/

│ │ ├── EnrolmentRepository.php

│ │ ├── StepProgressRepository.php

│ │ └── ProgressService.php

│ ├── Access/

│ │ ├── AccessProvider.php

│ │ ├── AccessManager.php

│ │ └── LoggedInAccessProvider.php

│ ├── Runner/

│ │ ├── Runner.php

│ │ └── Routes.php

│ └── Rest/

│ └── Controller.php

├── templates/

│ └── runner.php

├── assets/

│ ├── css/runner.css

│ └── js/runner.js

└── tests/

├── unit/

└── integration/
```

## Responsibility boundaries

| **Component**   | **Responsibility**                                                                     |
|-----------------|----------------------------------------------------------------------------------------|
| Plugin          | Bootstrap services, hooks and plugin lifecycle.                                        |
| Content         | Register post types and metadata; validate Sprint/Step relationships.                  |
| Database        | Create and upgrade plugin-owned tables.                                                |
| Repositories    | Read/write rows only. No presentation logic.                                           |
| ProgressService | Start, complete, advance, calculate progress, finish Sprint.                           |
| AccessManager   | Single entry point for access decisions.                                               |
| Runner          | Render member experience and coordinate current Sprint/Step.                           |
| REST Controller | Expose authenticated operations using services rather than duplicating business logic. |

# 5. WordPress hooks

Use standard WordPress lifecycle hooks and create a small public event surface for future extensions.

## Core WordPress hooks

- register_activation_hook: install/upgrade tables, store schema/plugin version, flush rewrite rules after routes are registered.

- init: register Sprint/Step post types, metadata and rewrite endpoints.

- rest_api_init: register Sprint Engine REST routes.

- template_include or rewrite/template routing hook: divert Sprint Runner requests to the plugin template.

- wp_enqueue_scripts: load Runner assets only on Runner requests.

- admin hooks: add Sprint/Step relationship fields and validation.

## Sprint Engine custom actions

```text
do_action( 'sprint_engine/sprint_started', $user_id, $sprint_id );

do_action( 'sprint_engine/step_started', $user_id, $sprint_id, $step_id );

do_action( 'sprint_engine/step_completed', $user_id, $sprint_id, $step_id );

do_action( 'sprint_engine/sprint_completed', $user_id, $sprint_id );
```

The events exist in v0.1 even when a site does not connect them to CRM or re-engagement workflows. This prevents future integrations from being coupled to database implementation details.

## Filters

- sprint_engine/user_can_access_sprint — final override point for access decisions.

- sprint_engine/runner_template — allow controlled template replacement.

- sprint_engine/progress_percentage — optional future extensibility; default calculation remains deterministic.

# 6. REST endpoints

Provide a stable application boundary so Runner logic can evolve independently from WordPress templates.

| **Method** | **Route**                               | **Purpose**                                                                 |
|------------|-----------------------------------------|-----------------------------------------------------------------------------|
| GET        | /sprint-engine/v1/sprints/{id}          | Return authorised Sprint summary and current user state.                    |
| POST       | /sprint-engine/v1/sprints/{id}/start    | Create/start enrolment and return first/current Step.                       |
| GET        | /sprint-engine/v1/sprints/{id}/progress | Return completed count, total reachable Steps, percentage and current Step. |
| GET        | /sprint-engine/v1/steps/{id}            | Return authorised Step payload for its Sprint.                              |
| POST       | /sprint-engine/v1/steps/{id}/complete   | Mark Step complete and return the next Step or Sprint-complete state.       |

## Response conventions

- JSON responses use a consistent success/error envelope and HTTP status codes.

- Never return private Step content until access has been authorised.

- Complete-step operations are idempotent: repeating the same request does not create duplicate progress.

- REST handlers delegate business logic to ProgressService and AccessManager.

- Include only fields needed by the Runner; do not expose arbitrary post meta.

## Example complete-Step response

```text
{

"success": true,

"sprint_id": 123,

"completed_step_id": 456,

"progress": {

"completed": 3,

"total": 8,

"percentage": 37.5

},

"next_step": {

"id": 457,

"title": "Define the problem"

},

"sprint_completed": false

}
```

# 7. Authentication and security

Treat progress and Step content as user-specific application data, not ordinary public page content.

## Authentication

- Runner requires a logged-in WordPress user in v0.1.

- Same-origin REST requests use WordPress cookie authentication plus REST nonce.

- Unauthenticated requests receive 401/403 and must not reveal restricted Step content.

- Future external/mobile authentication is outside v0.1 but the service layer must not depend on cookies directly.

## Authorisation

- AccessManager validates the user may access the Sprint before any Sprint or Step payload is returned.

- When a Step ID is supplied, verify that it belongs to the requested/derived Sprint.

- Admin mutations require explicit WordPress capabilities; do not rely only on 'is_admin()'.

- Users may write progress only for themselves in v0.1.

## Input/output safety

- Sanitise and validate IDs, enums and metadata on write.

- Escape output according to context. Render Gutenberg content through standard WordPress content filters rather than raw untrusted HTML.

- Use prepared SQL through `$wpdb` for dynamic queries.

- Do not expose stack traces, SQL or internal filesystem paths to users.

# 8. Sprint Runner routing

Give each Sprint one stable launch URL and let Sprint Engine decide which Step to display.

## Canonical route

`/sprint/{sprint-slug}/`

A member always returns to the Sprint URL rather than navigating public Step permalinks. The Runner resolves the Sprint, access, enrolment and current Step, then renders the correct Step.

## Request flow

1.  Resolve Sprint slug to published/launchable sprint_engine_sprint.

2.  Require authentication; redirect to the site's login flow while preserving the return URL if not logged in.

3.  Ask AccessManager whether the user can access the Sprint.

4.  Load the enrolment. If none exists, show a Start Sprint state.

5.  If in progress, load current_step_id.

6.  If completed, show the completion state rather than reopening an arbitrary Step by default.

7.  Render through the plugin Runner template, not the active theme's normal single-post template.

> **Stable route:** The URL must remain useful after the user closes the browser. The user should not need to bookmark a Step-specific URL to resume.

# 9. Progress calculation

Make progress predictable and explainable in v0.1.

## Calculation

```text
completed_steps = count(completed StepProgress rows for Sprint)

total_steps = count(valid Steps assigned to Sprint)

percentage = (completed_steps / total_steps) * 100
```

For v0.1 the Sprint is linear, so total_steps is the number of valid assigned Steps. In v0.2, branching may require 'reachable/selected path' progress semantics; that change is explicitly deferred.

## State transitions

| **Action**          | **Enrolment**                                                      | **Step progress**                            |
|---------------------|--------------------------------------------------------------------|----------------------------------------------|
| Start Sprint        | status → in_progress; started_at set; current_step_id → start Step | Start Step row may be created when displayed |
| Open current Step   | last_activity_at updated conservatively                            | status → started if no row exists            |
| Complete Step       | current_step_id → next Step                                        | status → completed; completed_at set         |
| Complete final Step | status → completed; current_step_id → null; completed_at set       | final Step → completed                       |

## Idempotency

Completing an already-completed Step returns the current canonical state without incrementing counts or altering timestamps unnecessarily. This protects against double clicks, browser retries and repeated API calls.

# 10. Resume behaviour

Ensure 'come back and continue' is a core product behaviour, not an afterthought.

## Rules

- When a Sprint starts, current_step_id is set to its configured start Step.

- After a Step completes, current_step_id is set to that Step's next_step_id.

- When the member returns to /sprint/{slug}/, current_step_id is authoritative.

- If current_step_id is invalid because content was deleted or detached, ProgressService attempts a safe recovery to the first incomplete valid Step and records/logs the anomaly.

- If every valid Step is complete but the enrolment is not marked complete, repair the enrolment to completed.

- Completed Sprints show a completed state; restart/reset is not offered in v0.1.

## User experience

For returning users, Sprint Runner should visibly acknowledge continuity without adding clutter: e.g. 'Welcome back' and the same progress indicator. The main content remains the current Step.

# 11. Admin editing experience

Make Sprint creation workable for site authors without delaying the member-facing product for a visual builder.

## Linear authoring model

`order = authoring input`

`explicit links = runtime representation`

For linear v0.1 Sprints, authors arrange the ordered Step list. Sprint Engine automatically derives and saves `_sprint_engine_position`, `_sprint_engine_next_step_id` and `_sprint_engine_start_step_id` from that list. These remain explicit persisted runtime metadata; they are not replaced by order-based runtime navigation. Explicit links also provide the foundation for future branching.

The normal linear authoring UI does not expose editable Position, Next Step or Start Step controls.

> **SE-002.1 refinement:** This workflow was refined after real-world authoring validation.

## Sprint edit screen

- Normal WordPress title and block editor for Sprint overview/description.

- Admin exposes the canonical Runner URL through the existing route helper and the editable native WordPress Sprint slug (`post_name`). Saved Sprints show their URL even when unavailable; an active View Runner action requires published, non-password-protected, launchable, structurally valid content. Member authentication/access checks remain separate.

- All Sprints includes a Runner status column and an available View Runner row action. Quick Edit exposes the native slug using WordPress's normal save and uniqueness handling. No separate slug metadata, public CPT permalink, slug history or redirect system is introduced. Changing the slug needs no rewrite flush because the generic `/sprint/{slug}/` rule is unchanged.

- Editable estimated duration and Launchable control, with Start Step displayed read-only.

- A lightweight Sprint Structure Manager with an ordered draggable list of associated Steps and Edit links.

- The list visibly shows calculated position, Start/Final status and next-Step relationships.

- A Quick Add action creates a titled draft Step directly from the Sprint edit screen, associates it with the current Sprint and appends it to the linear structure.

## Step edit screen

- Normal title and Gutenberg editor.

- Editable parent Sprint selector.

- Calculated Position displayed read-only.

- Editable stage label text field.

- Editable estimated minutes.

- Editable Step mode selector: Content / Task.

- Calculated Next Step displayed read-only (none for the final Step).

## Validation

- A Step cannot point to a Step in another Sprint.

- A Sprint cannot be launchable without a valid start Step.

- Warn on obvious loops in a supposedly linear v0.1 Sprint.

- Warn if more than one Step is left orphaned from the start-to-finish chain.

> **Intentional compromise:** The v0.1 admin may feel like WordPress. The member-facing Runner should feel like Sprint Engine. The lightweight linear Sprint Structure Manager is not the future visual Sprint Builder; that remains a later product feature.

# 12. Frontend wireframe

Define the minimum distraction-free Sprint Runner experience across desktop and mobile.

```text
┌──────────────────────────────────────────────────────────────┐

│ SPRINT ENGINE 3 of 8 Save & Exit │

│ ████████████░░░░░░░░░░░░░░░░░░ 37% │

├──────────────────────────────────────────────────────────────┤

│ Stage 2 — Diagnose │

│ │

│ WHAT ISN'T WORKING? │

│ │

│ [ Gutenberg content: text / image / audio / video / file ] │

│ │

│ YOUR TASK │

│ [ task instructions / worksheet link / reflection ] │

│ │

│ [ Complete & Continue → ] │

└──────────────────────────────────────────────────────────────┘
```

## Runner requirements

- Responsive from small mobile screens upward.

- No normal site header/footer/sidebar in Runner mode unless deliberately added later.

- Clear Sprint title, stage, Step title and progress.

- Primary action is Complete & Continue.

- Secondary action is Save & Exit / return to dashboard or defined safe destination.

- Native Gutenberg blocks render cleanly inside a constrained readable content width.

- Keyboard focus states and semantic controls must remain usable.

- Audio blocks must remain playable without custom JavaScript in v0.1.

## Loading and errors

- Disable/debounce Complete & Continue while the write request is in flight.

- If completion fails, keep the member on the Step and show a recoverable error; never pretend progress saved.

- If the next Step cannot be loaded, show a safe retry/exit state and log the technical error.

# 13. Completion rules

Keep completion explicit and deterministic.

## Step completion

A Step becomes complete only when the member activates Complete & Continue and the server successfully persists the state. Merely viewing, scrolling, playing audio or waiting for a timer does not complete the Step.

## Sprint completion

- If the completed Step has a valid next_step_id, advance to it.

- If next_step_id is null and the Step is the valid terminal Step in the Sprint chain, mark the enrolment completed.

- Set completed_at exactly once.

- Fire sprint_engine/sprint_completed after the transaction/state change succeeds.

- Show a completion screen with a clear done state and a return destination.

## Content changes after enrolment

v0.1 does not attempt sophisticated versioning of in-flight Sprints. Admins should avoid restructuring a live Sprint with active users. The code must nevertheless fail safely if a Step is removed. Formal content versioning is a later product feature.

# 14. Installation and upgrade behaviour

Make the plugin safe to install, update and move between development, staging and production.

## Activation

- Check minimum WordPress/PHP requirements and fail with an actionable admin message if unmet.

- Register required content structures.

- Create/upgrade database tables using a stored schema version.

- Store plugin version and schema version separately.

- Flush rewrite rules once on activation, not on every request.

## Upgrade

- Migrations are versioned and idempotent.

- Upgrading never deletes user progress.

- Database changes execute before code paths that depend on the new schema.

- Log migration failure and surface an admin notice rather than silently continuing with a half-upgraded schema.

## Deactivation and uninstall

- Deactivation must not delete Sprints, Steps or progress.

- uninstall.php should default to retaining data unless an explicit destructive-cleanup policy is added later.

- No automatic deletion of operational data in v0.1.

## Deployment model

Develop in Git, deploy to a non-production WordPress environment first, verify, then promote to production. The plugin repository should be deployable independently of the site theme and unrelated plugins.

# 15. Acceptance tests

Define behaviour in plain language so a human, Codex or another developer can verify the release without interpreting intent.

| **ID** | **Area**               | **Acceptance criterion**                                                                                                     |
|--------|------------------------|------------------------------------------------------------------------------------------------------------------------------|
| AT-01  | Activation             | Fresh install activates without PHP fatal error and creates both progress tables.                                            |
| AT-02  | Admin CPTs             | Administrator can see Sprints and Sprint Steps in WordPress admin.                                                           |
| AT-03  | Author Sprint          | Administrator can create a Sprint and at least five linked Steps using Gutenberg.                                            |
| AT-04  | Native audio           | A Step containing the native WordPress Audio block renders and plays in Sprint Runner.                                       |
| AT-05  | Start                  | Logged-in authorised user can start the Sprint; enrolment becomes in_progress and current Step is the configured start Step. |
| AT-06  | Single-Step view       | Runner displays one Step's content rather than the full Sprint stacked on one page.                                          |
| AT-07  | Complete               | Complete & Continue persists the current Step as completed and opens the correct next Step.                                  |
| AT-08  | Persistence            | Refresh/browser restart does not lose completed progress.                                                                    |
| AT-09  | Resume                 | Returning to the canonical Sprint URL resumes at current_step_id.                                                            |
| AT-10  | Progress               | Progress count and percentage match completed Steps for a linear Sprint.                                                     |
| AT-11  | Idempotency            | Double-submitting Step completion does not duplicate records or overcount progress.                                          |
| AT-12  | Final Step             | Completing the terminal Step marks enrolment completed and displays the completion state.                                    |
| AT-13  | Access                 | Unauthorised/anonymous users cannot retrieve restricted Step content via Runner or REST.                                     |
| AT-14  | Cross-Sprint tampering | Supplying a Step ID belonging to another Sprint is rejected.                                                                 |
| AT-15  | Invalid current Step   | If current_step_id becomes invalid, the user receives a safe recoverable experience rather than a fatal error.               |
| AT-16  | Responsive             | Runner remains usable on desktop and a narrow mobile viewport.                                                               |
| AT-17  | Hooks                  | Start, Step-complete and Sprint-complete custom actions fire once at the correct lifecycle points.                           |
| AT-18  | Deactivation           | Deactivating/reactivating the plugin preserves Sprint content and progress data.                                             |

## Recommended automated coverage

- Unit tests for ProgressService state transitions and progress percentage.

- Unit/integration tests for AccessManager decisions.

- REST tests for authentication, completion idempotency and cross-Sprint tampering.

- Database migration test from an empty install.

- Basic end-to-end browser smoke test for start → complete → resume → finish where tooling allows.

# 16. Explicitly excluded features

Prevent v0.1 from turning into a general-purpose LMS or automation platform before the core behaviour is proven.

- Decision Steps and branching pathways (v0.2 target).

- Rule-based or score-based routing.

- Visual drag-and-drop Sprint Builder (the lightweight linear Step list and its drag-to-reorder controls are included in v0.1).

- WooCommerce Memberships / Subscriptions access integration.

- Payments, checkout or subscription management.

- CRM connectors, outbound webhooks and Zapier/Make recipes.

- Inactivity detection and re-engagement scheduling.

- Action Scheduler jobs other than any strictly necessary internal maintenance.

- Custom audio player, remembered playback position or audio completion tracking.

- Video completion tracking.

- Quizzes, grades, assignments, SCORM, certificates, points or badges.

- Community/forum features.

- AI-generated routes, coaching or content.

- Sprint import/export packages.

- Multi-tenant SaaS behaviour.

- Advanced analytics dashboards.

- Restart/reset/versioning of completed or in-flight Sprints.

> **Rule for scope decisions:** If a proposed feature is not required to prove create → start → work one Step at a time → save → resume → complete, it does not belong in v0.1.

# Implementation Sequence

The specification above defines the release. The following build order is recommended because each increment leaves the plugin in a testable state.

| **Increment**        | **Deliverable**                                                   | **Exit check**                                            |
|----------------------|-------------------------------------------------------------------|-----------------------------------------------------------|
| 1\. Skeleton         | Installable plugin, bootstrap, namespaces, version constants.     | Activates/deactivates cleanly.                            |
| 2\. Content          | Register Sprints and Steps plus metadata.                         | Can author a five-Step Sprint.                            |
| 3\. Database         | Create enrolment/progress tables and repositories.                | Rows can be written/read in tests.                        |
| 4\. Progress service | Start/complete/advance/finish business logic.                     | State transitions pass automated tests.                   |
| 5\. Runner route     | Canonical /sprint/{slug}/ request and template.                   | Authorised user sees Start state/current Step.            |
| 6\. REST writes      | Start and complete endpoints with security.                       | Runner can persist progress without full page form hacks. |
| 7\. Runner UX        | Progress bar, Step content, Complete & Continue, Save & Exit.     | Linear Sprint can be completed on desktop/mobile.         |
| 8\. Hardening        | Error states, invalid IDs, idempotency, logging, acceptance pass. | All v0.1 acceptance criteria pass.                        |
| 9\. Package          | Versioned ZIP/release plus repository documentation.              | Fresh WordPress install can reproduce the result.         |

## Suggested repository baseline

```text
main production-ready history

develop optional integration branch (use only if useful)

feature/* short-lived implementation branches

README.md

CHANGELOG.md

LICENSE GPLv2 or later licence text and grant

composer.json autoloading + development tooling

phpcs.xml.dist WordPress coding standards

phpunit.xml.dist automated tests

.github/workflows/ CI checks and, later, controlled deployment
```

## Historical first coding ticket

> **SE-001 — Plugin foundation:** Create an installable `sprint-engine.zip` that activates successfully, registers Sprints and Sprint Steps, creates the two progress tables, stores plugin/schema versions, and deactivates without deleting data.

# Architecture Decisions Locked by v0.1

| **Decision**                | **Reason**                                                                                                   |
|-----------------------------|--------------------------------------------------------------------------------------------------------------|
| Standalone WordPress plugin | Keeps plugin logic independent of site themes and allows standalone distribution.             |
| Gutenberg for Step content  | Immediately supports rich text, embeds, native audio and files without building an editor.                   |
| Dedicated progress tables   | Progress is operational data that must be queried reliably and scaled independently from post/user metadata. |
| Canonical Sprint URL        | Users return to the Sprint, not to brittle Step URLs; Sprint Engine controls resume behaviour.               |
| REST/service boundary       | Lets the Runner evolve toward a richer app without rewriting progress logic.                                 |
| AccessManager abstraction   | Allows separate access integrations while Core remains independent of membership plugins.           |
| Explicit next_step_id       | Supports today's linear flow and tomorrow's branching without changing the core content model.               |
| No visual Builder in v0.1   | Real Sprint authoring will teach us what a future Builder actually needs.                                    |

**Build gate**

Sprint Engine v0.1 is ready for productisation work only after the complete linear journey is reliable on a clean WordPress install and all acceptance criteria above pass.
