# Installation

## Before you begin

Check the following requirements:

- Joomla 6.0 or later (verified by the installer);
- PHP 8.1 minimum (verified by the installer), PHP 8.3 recommended;
- Super User access;
- a recent website and database backup;
- enough disk space;
- Joomla directories writable by the web server.

> ℹ️ **Note:** the installer (`script.php`) aborts the installation if PHP is below
> 8.1 or Joomla is below 6.0. An error message is shown and the operation is cancelled.

For a migration from the former ContentBuilder extension, do not uninstall the old
component before reading the [migration guide](migration-contentbuilder.md).

> ⚠️ **Warning:** never uninstall the old ContentBuilder / BreezingForms component
> before installing ContentBuilder NG. Data migration happens automatically during
> installation.

## Install a release ZIP

Use the installation package published as a project release.

1. Download `com_contentbuilderng-<version>.zip`.
2. In Joomla, open **System > Install > Extensions**.
3. Select the "Upload Package File" option.
4. Upload the installation ZIP.
5. Wait until the process is fully complete.
6. Read all installer messages, especially migration warnings.

Do not use the automatically generated GitHub **Source code (zip)** archive as an
installation package. It is a development snapshot and may not contain assembled
production dependencies.

> 📷 *Screenshot to add: uploading the package in the Joomla 6 Extension Manager — `docs/en/img/installation-upload.png`*

## What installation sets up

- The component and its admin menu **Components > ContentBuilder NG**, with the
  **Data Storages**, **Views**, and **About** submenus.
- The following database tables (Joomla prefix `#__`):
  `#__contentbuilderng_forms`, `#__contentbuilderng_elements`,
  `#__contentbuilderng_storages`, `#__contentbuilderng_storage_fields`,
  `#__contentbuilderng_records`, `#__contentbuilderng_articles`,
  `#__contentbuilderng_users`, `#__contentbuilderng_registered_users`,
  `#__contentbuilderng_list_records`, `#__contentbuilderng_list_states`,
  `#__contentbuilderng_resource_access`, `#__contentbuilderng_verifications`,
  `#__contentbuilderng_rating_cache`.
- The bundled plugins (themes, validations, list actions, submission, verification,
  content plugins, and a system plugin). See
  [Administration](administration.md#plugins) for the detailed list.

## Post-installation checks

In **Components > ContentBuilder NG**, verify:

- the **Data Storages**, **Views**, and **About** entries are present;
- the version and build type shown in **About**;
- the component log contains no critical error;
- ContentBuilder NG plugins are listed in **System > Manage > Extensions**;
- the plugins required by your use case are enabled;
- `#__contentbuilderng_*` tables exist in the database.

Then:

1. open **About > Audit**;
2. review every warning;
3. use **REPAIR DB** only after a backup when repairs are proposed;
4. run the audit again.

- [ ] menu entries present
- [ ] `#__contentbuilderng_*` tables created
- [ ] plugins enabled as needed
- [ ] audit run with no blocking warning

## Updating

Updates use the complete package:

1. back up files and database;
2. install the new ZIP over the existing version;
3. review the update message;
4. run the audit;
5. test a list, details page, creation, and editing workflow.

The installer may update the schema, normalize references, update bundled plugins,
and clean up historical entries.

> ℹ️ **Note:** an update server is declared in the manifest, so Joomla can offer new
> versions through **System > Update > Extensions**. Only published releases are
> considered stable.

## Common installation errors

### Unsupported PHP or Joomla version

The installer requires Joomla 6.0 and PHP 8.1 or later. Change the server version
before retrying.

### Files are not writable

Check file ownership and Joomla directory permissions. Do not make the complete site
globally writable.

### SQL failure

- keep the site offline;
- inspect Joomla logs and `com_contentbuilderng.log`;
- check `CREATE`, `ALTER`, `INDEX`, `UPDATE`, and `RENAME TABLE` privileges;
- look for collisions between historical and NG tables;
- restore the backup before attempting unverified manual SQL changes.

See also `SQL_MIGRATION_GUIDE.md` in the project root for advanced recovery cases.

### Missing plugins

The complete release package installs the bundled plugins. If the installer cannot
find their source, verify that you used the assembled release ZIP.

## Uninstallation

> ⚠️ **Warning:** the uninstall SQL drops the `#__contentbuilderng_*` tables,
> including views, records, storages, permissions, article links, and verifications.
> This removal is irreversible.

Before uninstalling:

- export useful configuration (**About > Export configuration**);
- export business data;
- back up the complete database;
- back up uploaded files;
- identify linked Joomla articles.

Do not uninstall as an update or migration method.

## Release ZIP or source code?

| Use | Release ZIP | Repository source |
| --- | --- | --- |
| Joomla installation | Yes, recommended | No, unless assembled |
| Production PHP dependencies | Included in built package | Must be installed |
| Development tests and tools | Excluded | Present or configurable |
| Administrator use | Suitable | Not recommended |
| Project contribution | No | Yes |

Final checklist:

- [ ] release ZIP used
- [ ] installation completed without critical errors
- [ ] audit completed
- [ ] required plugins present
- [ ] backup retained
- [ ] frontend workflow tested
