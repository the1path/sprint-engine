=== Sprint Engine ===
Contributors: thepath
Tags: workflow, onboarding, training, learning, progress
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.2.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn WordPress into a guided implementation platform with focused Sprints, saved progress and a distraction-free step-by-step Runner.

== Description ==

Sprint Engine helps you create focused, step-by-step experiences that guide users towards a specific outcome.

Instead of simply publishing information, you can build a Sprint: a structured journey made up of ordered Steps that users work through one at a time.

A Sprint might be used to guide someone through:

* An onboarding process
* A training or development programme
* A business improvement exercise
* A planning or implementation process
* A guided workshop
* A repeatable internal process
* Any outcome that benefits from structured, sequential action

Sprint Engine is not intended to be a traditional Learning Management System. There are no quizzes, certificates, grades or course hierarchies in the core plugin.

The focus is simpler: help someone move through a practical process and make progress towards an outcome.

= How Sprint Engine works =

A Sprint contains:

* A Start screen that introduces the Sprint and explains what the user will achieve
* An ordered sequence of Steps
* Optional Content and Task Step modes
* Saved progress for logged-in users
* A distraction-free frontend Runner
* A Completion screen with an optional next-action button

Authors create Sprint and Step content using the normal WordPress block editor.

Steps are organised using the Sprint Structure Manager. The Runner then presents those Steps one at a time while keeping track of the user's progress.

= Key features =

* Create outcome-focused Sprints in WordPress
* Build Sprint content with the native block editor
* Add and order Steps from the Sprint editor
* Content and Task Step modes
* Optional Sprint and Step time estimates
* Featured images for Sprints and individual Steps
* Dedicated distraction-free Sprint Runner
* Start, resume and completion states
* Progress saved for individual logged-in users
* Built-in My Sprints member Dashboard: Available, In Progress and Completed
* Save & Exit returns to the Dashboard to resume later
* Restart completed Sprints while keeping previous attempts
* Configurable Runner logo, colours and corner styles
* Custom completion message and next-action button
* Administrator validation to help prevent invalid Sprint configuration
* Responsive frontend layout
* No external LMS required

= Designed to stay focused =

Sprint Engine deliberately keeps the core experience lightweight.

Version 0.2.0 does not include:

* Quizzes or exams
* Certificates
* Gamification
* Payments or subscriptions
* Built-in email automation
* Cohort management
* Branching or conditional paths
* User-submitted workbook responses

These may be better handled by other WordPress tools or future extensions rather than being built into every Sprint.

= My Sprints member Dashboard =

Visit /sprint-engine/dashboard/ while logged in, or find the link in Sprints → Settings.
No WordPress Page is required. The Dashboard shows Sprints you can access in In Progress,
Available and Completed sections, using the same branding as Runner.
Start Sprint opens the Runner overview; Continue Sprint resumes saved progress.
Restart Sprint confirms before creating a new attempt and keeps previous attempts.
View Completed Sprint reopens the completion screen. Save & Exit returns here.
Viewing the Dashboard creates no progress and stores no additional personal data.

== Installation ==

1. Upload the Sprint Engine plugin to WordPress or install it from the WordPress Plugin Directory.
2. Activate Sprint Engine.
3. Open Sprints in the WordPress administration area.
4. Add a new Sprint.
5. Add an introduction using the normal WordPress editor.
6. Add Steps using the Sprint Structure Manager.
7. Edit each Step and add its content.
8. Order the Steps and click Save order.
9. Publish every Step, then mark the Sprint as Launchable and publish it.
10. Open the Runner URL to test the Sprint.

Logged-in users can then start the Sprint and their progress will be saved as they move through the Steps.

== Frequently Asked Questions ==

= What is a Sprint? =

A Sprint is a focused, structured sequence of Steps designed to help a user reach a specific outcome.

For example, a Sprint could guide someone through planning a project, improving a business process, completing employee onboarding or working through a development exercise.

= Is Sprint Engine an LMS? =

Not in the traditional sense.

Sprint Engine does not currently provide quizzes, grades, certificates, course hierarchies or other common LMS features.

It is designed around guided implementation: helping someone work through a sequence of content and tasks towards an outcome.

= Can I use the WordPress block editor? =

Yes.

Sprint introductions and Step content are authored using the normal WordPress block editor, so you can use standard Gutenberg blocks including text, images, audio and other supported content.

= What is the Sprint Runner? =

The Runner is the frontend experience used by Sprint participants.

It presents one Step at a time, shows progress, saves completion state and allows users to return later and continue.

Each Sprint has a canonical Runner URL based on its WordPress slug.

= Does Sprint Engine save user progress? =

Yes.

Sprint Engine stores progress for logged-in WordPress users, including the Sprint they have started, their current Step, completed Steps and completion status.

= Can users leave a Sprint and continue later? =

Yes.

Progress is saved against the logged-in WordPress user. Returning to the same Sprint allows the user to resume their progress.

= Does Sprint Engine include memberships, subscriptions or payments? =

No.

Sprint Engine focuses on the Sprint authoring and Runner experience.

Access control is separated from the Sprint content and progress system so additional membership, commerce or access integrations can be added separately.

= Can I customise the Runner? =

Yes.

Sprint Engine includes site-wide Runner branding settings for:

* Logo
* Primary colour
* Primary button text colour
* Background colour
* Surface colour
* Text colour
* Muted text colour
* Corner style

= Can individual Steps have featured images? =

Yes.

A Sprint featured image can appear on the Start and Completion screens.

A Step can also have its own featured image. A Step without an image does not automatically inherit the Sprint image.

= Can I add a next action after somebody completes a Sprint? =

Yes.

Each Sprint can have a custom completion message and an optional button linking to a full HTTP or HTTPS URL.

This can be used to direct users to another Sprint, a feedback form, a booking page or another next step.

= Can I create branching or conditional Sprints? =

Not in version 0.2.0.

The current release intentionally supports a single linear Step sequence.

= Who can create and manage Sprints? =

In version 0.2.0, Sprint authoring is intended for WordPress administrators.

Custom authoring roles and capabilities are not currently included.

= What happens if I deactivate Sprint Engine? =

Deactivation does not delete your Sprint content, progress records or Sprint Engine settings.

Reactivating the plugin allows that data to be used again.

= What happens if I uninstall Sprint Engine? =

Version 0.2.0 intentionally retains Sprint content, progress data and plugin settings when the plugin is uninstalled.

If you need to permanently remove stored Sprint Engine data, make an appropriate database backup and remove the data manually.

= What data does Sprint Engine store? =

Sprint Engine stores Sprint and Step content using WordPress content and metadata.

It also stores progress records associated with WordPress user IDs, including enrolment status, current Step, Step completion state and relevant timestamps.

Sprint Engine does not require an external service to provide its core Sprint and progress functionality.

= Why is my Sprint not available in the Runner? =

Check that:

* The Sprint is published
* The Sprint contains at least one Step
* The Steps form a valid saved order
* Every associated Step is Published (Draft, Pending, Private and Scheduled Steps prevent launch)
* The Sprint is marked Launchable

If you have recently changed permalink settings or are seeing a Runner 404, saving WordPress Permalink settings once may rebuild the required rewrite rules.

= Can I restart a completed Sprint? =

Yes. Choose Restart Sprint on the Completion screen and confirm. A new attempt starts at the first current Step, and your previous completed attempt is kept. Starting over during an active attempt and viewing attempt history are not included yet.

== Screenshots ==

1. Create a Sprint using the WordPress block editor, with introduction content, estimated duration and setup guidance.
2. Build and organise the journey using Sprint Structure, with Step ordering and publication status visible.
3. Edit individual Steps using the WordPress block editor, including stage labels, duration and Step settings.
4. Guide participants through one Step at a time using the distraction-free Sprint Runner.
5. Track available and in-progress Sprints from the My Sprints Dashboard, with saved progress and resume actions.

== Privacy and Data ==

Sprint Engine stores progress against logged-in WordPress user IDs.

Progress records may include:

* Sprint ID
* Attempt ID and sequential attempt number
* Current Step ID
* Sprint status
* Step completion status
* Start time
* Last activity time
* Completion time

The member Dashboard derives its display from existing content and progress. It introduces no new stored personal data.

Sprint Engine also stores Sprint and Step configuration as WordPress content and metadata.

The core plugin does not require a third-party service to provide Sprint authoring, Runner or progress functionality.

Sprint Engine retains its content, settings and progress data when deactivated and when uninstalled in version 0.2.0.

Site owners are responsible for determining how their use of Sprint Engine relates to their own privacy policy, data-retention obligations and applicable laws.

== Changelog ==

= 0.2.0 =

* Initial public release.
* Create guided Sprints and ordered Steps using the WordPress block editor.
* Manage Step order using Sprint Structure.
* Show Step publication status and prevent unpublished Steps from launching.
* Run Sprints through the distraction-free Runner.
* Save and resume logged-in user progress.
* Use the My Sprints Dashboard for Available, In Progress and Completed Sprints.
* Save & Exit back to the Dashboard.
* Restart completed Sprints while retaining previous attempts.
* Configure Runner branding.
* Configure a completion message and optional next-action button.
* Use Sprint and Step durations and featured images.
* Retain content, settings and progress on deactivation and uninstall.
