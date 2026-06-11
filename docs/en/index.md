# ContentBuilder NG User Documentation

[Documentation française](../fr/index.md)

ContentBuilder NG is a Joomla 6 component for building configurable data
applications: forms, lists, record details, editing, permissions, Joomla articles,
notifications, exports, and a JSON API.

The project is a community modernization of the former Crosstec ContentBuilder
extension. It is independent from the historical product and is provided without
warranty.

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

- Joomla 6.0 or later;
- PHP 8.1 or later;
- MySQL or MariaDB compatible with Joomla 6;
- Joomla permissions to install and administer extensions;
- database permissions to create and alter tables during installation;
- a Joomla 6 compatible BreezingForms installation only when views use it as a
  source.

## When to use ContentBuilder NG

Use it to:

- publish searchable, filterable, sortable, and paginated data lists;
- provide record creation and editing forms;
- apply different permissions to Joomla user groups;
- restrict users to their own records;
- link records to Joomla articles;
- create internal storage or use an existing SQL table;
- import CSV, XLSX, or XLS data;
- expose selected fields through a JSON API;
- migrate a historical ContentBuilder installation to Joomla 6.

## When not to use it

Consider another solution or a dedicated assessment when:

- the project requires complex business transactions or a fully custom workflow;
- an external database must never be altered and does not match the component's
  expectations;
- nobody can maintain customized PHP templates;
- the application requires a public API without authentication or ACL checks;
- Joomla 5 or an older PHP version must be supported;
- contractual vendor support is required.

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

> **TODO screenshot:** ContentBuilder NG landing page in the Joomla 6
> administrator.
