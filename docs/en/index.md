# ContentBuilder NG User Documentation

[Documentation française](../fr/index.md)

ContentBuilder NG is a Joomla 6 component for building configurable data
applications: forms, lists, record details, editing, permissions, Joomla articles,
notifications, exports, and a JSON API.

The project is a community modernization of the former Crosstec ContentBuilder
extension. It is independent from the historical product and is provided without
warranty.

> ℹ️ **Note:** this documentation describes component version `6.1.7-RC57` (the
> `<version>` field in `com_contentbuilderng.xml`). Screens and options may change
> between versions.

## Audience

This documentation is intended for:

- Joomla 6 site administrators;
- integrators configuring views and menu items;
- teams migrating from the former ContentBuilder extension;
- advanced users customizing templates;
- teams consuming the JSON API.

Routine administration does not require PHP knowledge. Template customization and
advanced troubleshooting require Joomla, HTML, and PHP skills.

## Requirements

- Joomla 6.0 or later (the component is tested on Joomla 6.x, with or without the
  Backward Compatibility plugin). Joomla 5.4.x should work but is not tested — *to
  verify* in your environment;
- PHP 8.3 (the version tested by the project). PHP 8.1 remains the theoretical
  minimum enforced by the installer — *to verify*;
- MySQL or MariaDB compatible with Joomla 6;
- Joomla permissions to install and administer extensions;
- database permissions to create and alter tables during installation (the component
  creates its own `#__contentbuilderng_*` tables);
- a Joomla 6 compatible BreezingForms installation only when views use it as a
  source.

## When to use ContentBuilder NG

Use it to:

- publish searchable, filterable, sortable, and paginated data lists;
- provide front-end record creation and editing forms;
- apply different permissions to Joomla user groups;
- restrict users to their own records;
- link records to Joomla articles;
- create internal storage or use an existing SQL table;
- import CSV, XLSX, or XLS data;
- expose selected fields through a JSON API (read and update);
- display statistics and ratings;
- migrate a historical ContentBuilder installation to Joomla 6.

## When not to use it

Consider another solution or a dedicated assessment when:

- the project requires complex business transactions or a fully custom workflow;
- an external database must never be altered and does not match the component's
  expectations;
- nobody can maintain customized PHP templates;
- the application requires a public API without authentication or ACL checks (the
  API always enforces the component's permission model);
- Joomla 5 or an older PHP version must be supported;
- contractual vendor support is required.

## Entity overview

| Entity | Role |
| --- | --- |
| **View** (Form) | Configuration unit: source, columns, templates, permissions, options. Appears under the **Views** menu. |
| **Storage** | A data table managed by the component, with its fields. |
| **Element** | A field of a view (from storage or a BreezingForms form). |
| **Record** | A row of data displayed, created, or edited. |
| **Article** | Optional link between a record and a Joomla article. |
| **Plugin** | Theme, validation, list action, submission, verification, or content plugin. |
| **JSON API** | The `task=api.display` endpoint to read and update records. |

See [Core concepts](concepts.md) for full details.

## Recommended path

1. [Install ContentBuilder NG](installation.md).
2. For an existing site, follow the
   [ContentBuilder migration guide](migration-contentbuilder.md).
3. Read the [core concepts](concepts.md).
4. Complete the [Getting started](getting-started.md) tutorial.
5. Configure [permissions and ACL](permissions-acl.md).
6. Use the [Administration](administration.md) reference.
7. Test the [Frontend](frontend.md) workflows.

## All chapters

- [Installation](installation.md)
- [Migration from ContentBuilder](migration-contentbuilder.md)
- [Getting started](getting-started.md)
- [Core concepts](concepts.md)
- [Administration](administration.md)
- [Frontend](frontend.md)
- [Permissions and ACL](permissions-acl.md)
- [JSON API](api-json.md)
- [Templates and customization](templates-customization.md)
- [Maintenance and troubleshooting](maintenance-troubleshooting.md)
- [FAQ](faq.md)
- [Glossary](glossary.md)

## Disclaimer

ContentBuilder NG is a community project, provided "as is", without warranty of any
kind. It is **not** an official Crosstec product. Use it at your own risk. Always take
a full backup (files and database) before installing or migrating.

> 📷 *Screenshot to add: ContentBuilder NG landing page in the Joomla 6 administrator (Components menu) — `docs/en/img/index-home.png`*
