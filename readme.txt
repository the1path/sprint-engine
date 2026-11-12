=== Sprint Engine ===
Contributors: thepath
Tags: lms, membership, onboarding, coaching, training
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.2.3
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn courses, content and expertise into guided, action-focused journeys in WordPress. Works alongside your LMS or on its own.

== Description ==

**You have the content. Sprint Engine creates the journey.**

Help your members, learners and clients put knowledge to work.

Sprint Engine turns content, expertise and repeatable processes into **guided, step-by-step experiences** in WordPress. Give people a clear starting point and a path to a meaningful outcome.

Each experience is called a **Sprint**. Participants follow focused Steps and return whenever they're ready.

= Make more of the LMS and membership you already have =

**Your LMS delivers the learning. Sprint Engine guides the doing.**

Bring courses, articles, videos and member resources together into a purposeful experience. A Sprint can introduce a goal, link to a course and guide someone through applying what they learn.

* Help new members find their feet and reach their first win.
* Create a clear route through a growing content library.
* Connect courses and resources to practical activities.
* Guide participants from learning to implementation.

Your existing LMS continues to deliver courses and manage course access. Sprint Engine provides the guided path around them.

= A lightweight LMS alternative for action-focused learning =

**Teach less passively. Help people do more.**

Build guided training, onboarding and development programmes directly in WordPress. Combine learning content with tasks, reflection and real-world activities so every Step moves participants closer to their goal.

Ideal for practical programmes where progress and action matter more than traditional course administration.

= Turn your expertise into a guided programme =

**Make your method repeatable.**

Consultants, coaches, membership creators and WordPress agencies can turn a proven process into a consistent experience that clients and members complete at their own pace.

Create client onboarding, coaching, implementation or member success programmes. Build once and guide people through again and again.

= What could you create? =

* **Member onboarding:** Lead new members to their first meaningful result.
* **LMS learning pathways:** Turn scattered courses and resources into a clear journey.
* **Coaching and consulting:** Give clients a practical framework to follow.
* **Training and development:** Connect learning to everyday action.
* **Customer success:** Guide customers from getting started to seeing value.
* **Implementation and workflows:** Make repeatable processes easier to follow.

= Everything you need to create the journey =

* **Build with ease.** Create Sprints and Steps using the familiar WordPress editor.
* **Guide every step.** Keep participants focused on the next action in a dedicated Sprint experience.
* **Keep momentum going.** Save progress and pick up where you left off.
* **Give everyone a home base.** Bring available, active and completed Sprints together in My Sprints.
* **Run it again.** Restart active or completed Sprints and preserve previous attempts.
* **Apply your brand.** Make the participant experience feel part of your website.
* **Make the finish count.** Celebrate completion and guide people towards their next action.

= Coming soon: Sprint Engine Pro =

Take the journey further with a premium extension in development:

* **Turn action into tangible work.** Let participants record answers, ideas, decisions and practical outputs as they go.
* **Create personal Workbooks.** Bring that work together to review, print and export.
* **See progress over time.** Revisit earlier Sprint attempts and saved Workbooks.
* **Connect Sprints into Journeys.** Build longer programmes from multiple Sprints.
* **Monetise your programmes.** Offer paid Sprint access through WooCommerce, with compatible subscription options.

These are planned Pro capabilities. This WordPress.org download is the free Sprint Engine Core.

= Getting started =

Create a Sprint, add and arrange Steps with the WordPress editor, then publish and share its Runner URL.

Logged-in participants can work through the Steps, save their progress, and return through the My Sprints dashboard at `/sprint-engine/dashboard/`.

== Installation ==

1. Install and activate Sprint Engine from the WordPress Plugin Directory. Use a permalink structure other than Plain.
2. Go to **Sprints** in WordPress and create a Sprint.
3. Write an introduction and add Steps using the Sprint Structure Manager.
4. Add your content and activities, then save the Step order.
5. Publish each Step, mark the Sprint **Launchable**, and publish it.
6. Open the Runner URL to experience the Sprint before sharing it.

== Frequently Asked Questions ==

= What is a Sprint? =

A Sprint is a sequence of Steps guiding someone towards an outcome, such as onboarding, developing a skill or implementing a process.

= Can I use Sprint Engine with my existing LMS? =

Yes. Add links to courses, lessons and resources within your Steps. Participants can follow those links and return to continue their Sprint. Your LMS handles its course access and completion records, while participants mark their Sprint Steps complete in Sprint Engine.

= Can Sprint Engine work as an LMS alternative? =

Yes, for guided training, onboarding and action-based programmes. Sprint Engine focuses on the participant journey and progress. For specialised functions such as graded quizzes or certificates, you can continue using a dedicated LMS alongside it.

= Can I use the WordPress block editor? =

Yes. Build Sprint introductions and Steps with the native block editor, including supported text, media, links and blocks.

= Can participants leave and resume later? =

Yes. Progress is saved for logged-in users, who can resume from the My Sprints dashboard.

= Can participants restart a Sprint? =

Yes. Participants can restart an active or completed Sprint. Restarting creates a fresh attempt at the first Step and keeps previous attempt records.

= Can I customise the experience? =

Yes. In **Sprints → Settings → Runner Branding**, apply your logo, colours and typography. Choose the website font, the system default or one of five locally hosted fonts. You can also choose how to celebrate completion and direct participants to their next action.

= Who can create Sprints? =

Sprint authoring in Core is intended for WordPress administrators.

= Why won't my Sprint launch? =

Make sure the Sprint is published and marked Launchable, has at least one Step, has a valid saved Step order and has every associated Step published. After a permalink change, saving WordPress Permalink settings can rebuild the Runner routes.

= What happens to my data if I deactivate or uninstall? =

Sprint Engine retains Sprint content, settings and progress records on deactivation and uninstall. Back up your database before manually deleting stored data.

== Screenshots ==

1. **Create a guided programme.** Turn content or a proven process into a focused Sprint.
2. **Shape the journey.** Arrange Steps into a clear path towards an outcome.
3. **Make every Step meaningful.** Combine instructions, resources and activities.
4. **Keep participants moving.** Deliver a focused, step-by-step experience in the Sprint Runner.
5. **Make progress visible.** Give participants one place to start, continue and revisit Sprints.

== Privacy and Data ==

Sprint and Step content is stored as WordPress content and metadata. Progress records are associated with logged-in WordPress user IDs and may include Sprint and attempt IDs, current Step, Step completion state, Sprint status, and start, activity and completion timestamps.

The My Sprints dashboard presents existing content and progress data. Core authoring, Runner and progress functionality run within WordPress. Bundled fonts are served by your site. With the website font selected, Sprint Engine can use the theme's locally hosted font. Content you embed from other services may make its own requests.

Core retains content, settings and progress on deactivation and uninstall. Site owners should address this in their privacy and data-retention policies.

== Changelog ==

= 0.2.3 =

* Preview Steps in the block editor, including draft Steps and Steps in unpublished Sprints.
* Use your website font, the system default or one of five locally hosted fonts.
* Return to the website from the Sprint Runner and My Sprints using the linked logo or the Back to website link.
* Restart an in-progress Sprint while preserving previous attempts.
* Resume at the next incomplete Step when a Sprint's Steps are added, removed or reordered.
* Add Dashboard and completion hooks for integrations.
* Integrations that complete Steps or restart Sprints must now submit the attempt ID captured when the participant opened the view.
* Keep database schema 2. Existing installations require no data migration.

= 0.2.2 =

* Add extension hooks for completed Sprint actions and completed Dashboard cards.
* Add presentation hooks for access-denied Runner screens.
* Allow extensions to opt into a public access screen with a Login link for eligible Sprints.
* Preserve existing login, progress and access-control behaviour by default.
* No database changes or migrations.

= 0.2.1 =

* Add a read-only canonical attempt-history PHP API for extensions.
* Add a generic Dashboard header action hook.
* Keep storage schema 2 unchanged; no database migration.
* Update plugin directory description and positioning around guided journeys.

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
