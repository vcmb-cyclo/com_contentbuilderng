# Unit Tests

The PHPUnit suite covers isolated component services, models, helpers, routing and
security regressions. Joomla framework dependencies are simulated by
`tests/bootstrap.php`; these tests do not replace installation tests on a real Joomla
instance.

## Install

```bash
cd admin
composer install
```

## Run

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

## Coverage

PCOV or Xdebug is required:

```bash
vendor/bin/phpunit -c phpunit.xml.dist \
  --coverage-text \
  --coverage-clover=coverage.xml
```

GitHub Actions runs this command and publishes `coverage.xml` as the
`phpunit-coverage` artifact. No minimum percentage is enforced until the first stable
baseline has been reviewed.

Package and Joomla integration tests are documented in the repository
[testing guide](../../TESTING.md).
