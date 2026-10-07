# Sprint Engine

**Turn WordPress into a guided action platform.**

Sprint Engine is an open-source WordPress plugin for building guided, step-by-step
experiences that move people from start to outcome. Create a Sprint, arrange its
Steps, and give participants a focused place to work through them with saved progress.

[Install from WordPress.org](https://wordpress.org/plugins/sprint-engine/) ·
[Developer documentation](docs/README.md) · [Contribute](CONTRIBUTING.md)

## What is Sprint Engine?

A Sprint is a structured journey towards a defined outcome. Site owners author
content and tasks in the WordPress block editor; participants work through the
ordered Steps one at a time in the Sprint Runner. They can leave and return to
their saved position, then complete the journey when the work is done.

The focus is action and implementation: helping people put guidance into practice.

## What can you build?

- Onboarding flows and customer success journeys.
- Implementation guides and programmes.
- Assessments and guided reviews using authored prompts and tasks.
- Action plans and coaching journeys.
- Training experiences and structured programmes.
- Internal processes and step-by-step workflows.

Core provides a linear journey with explicit Step completion. Assessments can guide
self-review; Core does not collect workbook responses or score quizzes.

## Core features

- Sprint and Step content types with native WordPress blocks, including audio.
- An ordered Sprint Structure Manager with Quick Add, drag and keyboard reordering,
  and Step publication checks.
- A responsive, distraction-free Runner that displays one Step at a time.
- Individual progress for logged-in users, with save, resume and completion states.
- A My Sprints Dashboard for available, in-progress and completed Sprints.
- Restart for completed Sprints, preserving previous attempts.
- Site-wide Runner logo, colours and corner styles.
- Sprint and Step featured images and optional duration estimates.
- A configurable completion message and optional next-action button.
- Authenticated WordPress REST operations and documented extension hooks.

## Installation

Install the released plugin from the
[WordPress Plugin Directory](https://wordpress.org/plugins/sprint-engine/), or search
for **Sprint Engine** in **Plugins → Add New** in your WordPress administration area.
Activate it, then open **Sprints** to create your first journey.

Core **0.2.0** requires WordPress **6.0+** and PHP **8.0+**. Participants need a
WordPress account; the Runner requires pretty permalinks and JavaScript for progress
actions. Publish every Step, save the order, then publish and mark the Sprint
**Launchable**. Use its **Runner URL** to try the journey.

For manual source installation or packaging, see the
[development guide](docs/development.md#developer-installation-and-packaging).

## Development

Sprint Engine Core is a standalone WordPress plugin. PHP classes live in `src/`,
templates in `templates/`, and frontend assets in `assets/`. Runtime installation
requires no Composer dependencies.

With PHP, Composer and Node.js available, the existing development checks are:

```sh
composer install
composer check
php tests/release.php
php tests/integration/assets.php
node --test tests/js/*.test.cjs
```

WordPress integration suites require a disposable installation and database.
See the [development and test guide](docs/development.md#verification) before
running `composer test`; those suites create fixtures and deliberately test database
failures. The [documentation index](docs/README.md) links architecture, service
contracts and historical validation evidence.

## Contributing

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for
bug reports, focused changes and relevant checks.

## Security

Please report potential vulnerabilities privately. See [SECURITY.md](SECURITY.md)
for the reporting channel.

## Licence

Sprint Engine is licensed under the **GNU General Public License v2 or later**.
See [LICENSE](LICENSE) for the licence text and grant.
