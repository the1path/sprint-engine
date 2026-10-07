# Contributing to Sprint Engine

Issues and pull requests are welcome. Check existing issues before opening a new
one where practical.

For a bug report, include reproduction steps, expected and actual behaviour, and
the WordPress and PHP versions involved. Include relevant plugin configuration
without credentials, personal data or private site details. Report vulnerabilities
through the private channel described in [SECURITY.md](SECURITY.md).

Keep changes focused on one issue. Explain behavioural changes clearly and add or
update tests where appropriate. Core is a standalone, open-source WordPress plugin:
keep customer branding, hosting assumptions and implementation-specific business
logic out of Core. Preserve backwards compatibility and existing progress data.

Read the [developer documentation](docs/README.md) and [AGENTS.md](AGENTS.md) for
architecture and coding conventions. With development dependencies installed,
run the relevant existing checks before submitting:

```sh
composer check
php tests/release.php
php tests/integration/assets.php
node --test tests/js/*.test.cjs
```

For changes involving WordPress behaviour, also run the relevant integration
suites. `composer test` runs the configured PHP suite against a disposable site;
the [test guide](docs/development.md#verification) explains setup and environment
variables. State which checks you ran and any checks you could not run, with reasons.
