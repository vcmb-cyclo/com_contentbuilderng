#!/usr/bin/env bash

set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output_dir="${1:-${root_dir}/build}"

if [[ "${output_dir}" != /* ]]; then
    output_dir="${root_dir}/${output_dir}"
fi

package_dir="${output_dir}/package"
version="$(sed -n 's:.*<version>\([^<]*\)</version>.*:\1:p' "${root_dir}/com_contentbuilderng.xml" | head -n 1)"

if [[ -z "${version}" ]]; then
    echo "Unable to resolve the component version." >&2
    exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
    echo "Composer is required to build the production package." >&2
    exit 1
fi

rm -rf "${package_dir}"
mkdir -p "${package_dir}" "${output_dir}"

cp -R "${root_dir}/admin" "${package_dir}/admin"
cp -R "${root_dir}/site" "${package_dir}/site"
cp -R "${root_dir}/media" "${package_dir}/media"
cp -R "${root_dir}/plugins" "${package_dir}/plugins"
cp "${root_dir}/com_contentbuilderng.xml" "${package_dir}/"
cp "${root_dir}/script.php" "${package_dir}/"

rm -rf \
    "${package_dir}/admin/tests" \
    "${package_dir}/admin/vendor" \
    "${package_dir}/admin/.phpunit.cache" \
    "${package_dir}/admin/.phpunit.result.cache"
rm -f "${package_dir}/admin/phpunit.xml" "${package_dir}/admin/phpunit.xml.dist"

composer install \
    --working-dir="${package_dir}/admin" \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --quiet

archive="${output_dir}/com_contentbuilderng-${version}.zip"
rm -f "${archive}"

(
    cd "${package_dir}"
    zip -qr "${archive}" .
)

printf '%s\n' "${archive}"
