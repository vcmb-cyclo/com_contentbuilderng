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
- PHP 8.3 and PHP 8.4 for supported production code;
- PHP 8.4 as the standard development runtime;
- PHP 8.5 as an experimental compatibility target;
- MySQL or MariaDB only.

Use native Joomla 6 APIs and modern PHP. Do not add compatibility code for
older Joomla or PHP versions.

The CI syntax and PHPUnit matrices cover PHP 8.3, 8.4 and 8.5. PHPStan,
code style, coverage, packaging and the Joomla smoke test use PHP 8.4. Do not
use PHP 8.5-only syntax while PHP 8.3 remains supported.

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

## Publishing a Release Candidate

1. Prepare the RC on a dedicated branch and update the component version in
   `contentbuilderng.xml`, `media/joomla.asset.json`, `CHANGELOG.md` and
   `com_contentbuilderng_changelog.xml`.
   Do not update `com_contentbuilderng_update.xml` yet: it must continue to
   advertise the latest version whose GitHub release and installable ZIP
   actually exist.
2. Do not bump a bundled plugin manifest for an RC unless that plugin was
   modified. Synchronize all bundled plugin versions only for a final release
   without an `RC` suffix.
3. Open a pull request to `main`, wait for every required check to pass, review
   the changes and merge the pull request.
4. From GitHub Actions, run **Test and Build Package** on `main` with
   `publish_release=true`.
5. The workflow runs the quality gates, builds and validates the production ZIP,
   smoke-tests installation, update and migration on Joomla, creates or updates
   the `v<version>` GitHub release, marks an `-RCxx` version as a prerelease and
   only then updates `com_contentbuilderng_update.xml` with the published
   version, download URL and generated SHA-256 checksum.
6. Verify that the release is not a draft, is marked as a prerelease, contains
   `com_contentbuilderng-<version>.zip`, and that its asset digest matches the
   checksum in the update manifest. Pull `main` again to receive the automatic
   checksum commit.

Do not create a separate manual tag or upload a second ZIP when this workflow is
used: the workflow owns the official package, tag, prerelease state and checksum.
Merging RC code into `main`, or installing an unpublished RC ZIP manually on a
production site, never authorizes changing the Joomla update stream.

By contributing, you agree that your contribution is licensed under
`GPL-2.0-or-later`.
