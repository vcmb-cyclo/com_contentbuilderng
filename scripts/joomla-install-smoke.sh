#!/usr/bin/env bash

set -euo pipefail

archive="${1:?Usage: scripts/joomla-install-smoke.sh path/to/package.zip}"
joomla_image="${JOOMLA_IMAGE:-joomla:6.0-apache}"
mysql_image="${MYSQL_IMAGE:-mysql:8.4}"
run_id="${GITHUB_RUN_ID:-local}-$$"
network="cbng-smoke-${run_id}"
db_container="cbng-smoke-db-${run_id}"
web_container="cbng-smoke-web-${run_id}"
container_archive="/tmp/com_contentbuilderng.zip"

cleanup() {
    if [[ "${KEEP_SMOKE_CONTAINERS:-0}" == "1" ]]; then
        echo "Smoke containers kept: ${web_container}, ${db_container}; network: ${network}" >&2
        return
    fi

    docker rm -f "${web_container}" "${db_container}" >/dev/null 2>&1 || true
    docker network rm "${network}" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create "${network}" >/dev/null

docker run -d \
    --name "${db_container}" \
    --network "${network}" \
    -e MYSQL_DATABASE=joomla \
    -e MYSQL_USER=joomla \
    -e MYSQL_PASSWORD=joomla \
    -e MYSQL_ROOT_PASSWORD=root \
    "${mysql_image}" >/dev/null

for _ in $(seq 1 60); do
    if docker exec -e MYSQL_PWD=root "${db_container}" mysqladmin ping -uroot --silent >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

docker exec -e MYSQL_PWD=root "${db_container}" mysqladmin ping -uroot --silent >/dev/null

docker run -d \
    --name "${web_container}" \
    --network "${network}" \
    -e JOOMLA_DB_HOST="${db_container}" \
    -e JOOMLA_DB_USER=joomla \
    -e JOOMLA_DB_PASSWORD=joomla \
    -e JOOMLA_DB_NAME=joomla \
    -e JOOMLA_SITE_NAME="ContentBuilder NG Smoke Test" \
    -e JOOMLA_ADMIN_USER="Smoke Administrator" \
    -e JOOMLA_ADMIN_USERNAME=smokeadmin \
    -e JOOMLA_ADMIN_PASSWORD='Smoke-Test-123!' \
    -e JOOMLA_ADMIN_EMAIL=smoke@example.invalid \
    -e JOOMLA_INSTALLATION_DISABLE_LOCALHOST_CHECK=1 \
    "${joomla_image}" >/dev/null

for _ in $(seq 1 90); do
    if docker exec "${web_container}" test -f /var/www/html/configuration.php; then
        break
    fi
    sleep 2
done

docker exec "${web_container}" test -f /var/www/html/configuration.php

for _ in $(seq 1 60); do
    if docker exec "${web_container}" php -r '
        exit(@file_get_contents("http://127.0.0.1/index.php") === false ? 1 : 0);
    '; then
        break
    fi
    sleep 2
done

docker exec "${web_container}" php -r '
    exit(@file_get_contents("http://127.0.0.1/index.php") === false ? 1 : 0);
'

docker cp "${archive}" "${web_container}:${container_archive}" >/dev/null
docker exec -e HTTP_HOST=localhost "${web_container}" php /var/www/html/cli/joomla.php extension:install \
    --path="${container_archive}" \
    --live-site=http://localhost \
    --quiet \
    --no-interaction

table_prefix="$(
    docker exec "${web_container}" php -r '
        require "/var/www/html/configuration.php";
        $config = new JConfig();
        echo $config->dbprefix;
    '
)"

component_count="$(
    docker exec -e MYSQL_PWD=joomla "${db_container}" mysql -N -ujoomla joomla \
        -e "SELECT COUNT(*) FROM \`${table_prefix}extensions\` WHERE type = 'component' AND element = 'com_contentbuilderng';"
)"

if [[ "${component_count}" -ne 1 ]]; then
    echo "ContentBuilder NG component registration was not found." >&2
    docker exec -e MYSQL_PWD=joomla "${db_container}" mysql -N -ujoomla joomla \
        -e "SELECT extension_id, type, element, name FROM \`${table_prefix}extensions\` WHERE element LIKE '%contentbuilder%' OR name LIKE '%ContentBuilder%';" >&2
    exit 1
fi

table_count="$(
    docker exec -e MYSQL_PWD=joomla "${db_container}" mysql -N -ujoomla joomla \
        -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%contentbuilderng%';"
)"

if [[ "${table_count}" -lt 10 ]]; then
    echo "ContentBuilder NG tables were not installed correctly: ${table_count} found." >&2
    exit 1
fi

plugin_count="$(
    docker exec -e MYSQL_PWD=joomla "${db_container}" mysql -N -ujoomla joomla \
        -e "SELECT COUNT(*) FROM \`${table_prefix}extensions\` WHERE type = 'plugin' AND (element LIKE 'contentbuilderng_%' OR folder LIKE 'contentbuilderng_%');"
)"

if [[ "${plugin_count}" -lt 5 ]]; then
    echo "ContentBuilder NG plugins were not installed correctly: ${plugin_count} found." >&2
    exit 1
fi

# Exercise the update path and one supported historical table rename.
docker exec -e MYSQL_PWD=joomla "${db_container}" mysql -ujoomla joomla \
    -e "RENAME TABLE \`${table_prefix}contentbuilderng_list_states\` TO \`${table_prefix}contentbuilder_list_states\`;"
docker exec -e HTTP_HOST=localhost "${web_container}" php /var/www/html/cli/joomla.php extension:install \
    --path="${container_archive}" \
    --live-site=http://localhost \
    --quiet \
    --no-interaction

migrated_table_count="$(
    docker exec -e MYSQL_PWD=joomla "${db_container}" mysql -N -ujoomla joomla \
        -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '${table_prefix}contentbuilderng_list_states';"
)"
legacy_table_count="$(
    docker exec -e MYSQL_PWD=joomla "${db_container}" mysql -N -ujoomla joomla \
        -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '${table_prefix}contentbuilder_list_states';"
)"

if [[ "${migrated_table_count}" -ne 1 || "${legacy_table_count}" -ne 0 ]]; then
    echo "Historical table migration failed during the update test." >&2
    exit 1
fi

api_response="$(
    docker exec "${web_container}" php -r '
        $url = "http://127.0.0.1/index.php?option=com_contentbuilderng&task=api.display&id=999999&format=json";
        $context = stream_context_create(["http" => ["ignore_errors" => true]]);
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            exit(1);
        }
        echo $response;
    '
)"

php -r '
    $payload = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
    if (!array_key_exists("success", $payload) || !array_key_exists("data", $payload)) {
        fwrite(STDERR, "Unexpected API response shape.\n");
        exit(1);
    }
' "${api_response}"

echo "Joomla installation, update, migration and API smoke tests passed."
