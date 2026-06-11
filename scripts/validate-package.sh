#!/usr/bin/env bash

set -euo pipefail

archive="${1:?Usage: scripts/validate-package.sh path/to/package.zip}"

if [[ ! -f "${archive}" ]]; then
    echo "Package not found: ${archive}" >&2
    exit 1
fi

entries="$(unzip -Z1 "${archive}")"

required=(
    "com_contentbuilderng.xml"
    "script.php"
    "admin/services/provider.php"
    "admin/sql/install.sql"
    "site/src/Controller/ApiController.php"
    "media/joomla.asset.json"
    "plugins/system/contentbuilderng_system/contentbuilderng_system.xml"
)

for path in "${required[@]}"; do
    if ! grep -Fxq "${path}" <<<"${entries}"; then
        echo "Required package entry is missing: ${path}" >&2
        exit 1
    fi
done

forbidden_patterns=(
    '^admin/tests/'
    '^admin/vendor/bin/'
    '^admin/vendor/phpunit/'
    '^admin/vendor/sebastian/'
    '^admin/\.phpunit'
    '/\.git/'
)

for pattern in "${forbidden_patterns[@]}"; do
    if grep -Eq "${pattern}" <<<"${entries}"; then
        echo "Forbidden development artifact found in package: ${pattern}" >&2
        exit 1
    fi
done

if ! unzip -p "${archive}" com_contentbuilderng.xml | grep -Fq '<extension type="component" method="upgrade" version="6.0">'; then
    echo "Invalid Joomla component manifest." >&2
    exit 1
fi

echo "Package validation passed: ${archive}"
