# Contributing to ContentBuilder NG

Thank you for helping improve ContentBuilder NG.

## Before You Start

- Use GitHub Discussions or an issue to confirm the scope of substantial
  changes before implementation.
- Search existing issues and pull requests to avoid duplicates.
- Report vulnerabilities privately according to
  [SECURITY.md](SECURITY.md).
- Follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Supported Development Environment

Contributions must target:

- Joomla 6 only;
- PHP 8.3 or later;
- MySQL or MariaDB only.

Use native Joomla 6 APIs and modern PHP. Do not add compatibility code for
older Joomla or PHP versions.

## Development Setup

Install the PHP and frontend development dependencies:

```bash
cd admin
composer install
cd ..
npm ci
```

See [TESTING.md](TESTING.md) for package and Joomla integration testing.

## Making Changes

- Keep changes focused and avoid unrelated refactoring.
- Respect Joomla MVC separation and prefer native Joomla UI patterns.
- Keep custom CSS and JavaScript minimal.
- Build SQL with Joomla's query builder. Raw SQL must use MySQL/MariaDB syntax.
- Route every user-facing Joomla string through a translation key.
- Update `en-GB`, `fr-FR` and `de-DE` together when translations change.
- Add or update tests for behavior changes and regressions.
- Never commit credentials, generated packages, dependency directories or
  local configuration.

## Verification

Run the checks relevant to your change. The main local commands are:

```bash
cd admin
vendor/bin/phpunit -c phpunit.xml.dist
cd ..
admin/vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress
npm run lint:css
python3 scripts/check-translations.py
```

For packaging or installer changes, also run:

```bash
scripts/build-package.sh
scripts/validate-package.sh build/com_contentbuilderng-<version>.zip
scripts/joomla-install-smoke.sh build/com_contentbuilderng-<version>.zip
```

The integration test requires Docker.

## Pull Requests

- Use a clear title and explain the problem and the chosen solution.
- Link related issues.
- Describe how the change was tested.
- Include screenshots for visible UI changes.
- Keep the branch current and ensure all required GitHub checks pass.
- Update documentation when behavior or requirements change.

By contributing, you agree that your contribution is licensed under
`GPL-2.0-or-later`.
