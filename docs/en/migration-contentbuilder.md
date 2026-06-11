# Migration from the former ContentBuilder

ContentBuilder NG reuses historical ContentBuilder/Crosstec structures and modernizes
them for Joomla 6. The installer performs the main migration.

## Essential rule

Do not uninstall the former ContentBuilder extension before installing
ContentBuilder NG. Its tables are the migration source, and an old uninstaller may
delete required data.

## What the installer can migrate

The repository documents and implements:

- renaming `#__contentbuilder_*` tables to `#__contentbuilderng_*`;
- normalizing extension and menu entries;
- normalizing historical source types;
- updating schema, dates, indexes, and expected columns;
- installing and enabling NG plugins;
- carefully disabling and cleaning up historical plugins;
- mapping old themes to supported themes;
- retaining views, elements, records, storages, and article links when no collision
  occurs.

The installer does not merge two competing non-empty tables. That case requires
manual analysis.

## Required backups

Before migration, create:

- a complete database backup;
- a complete Joomla file backup;
- a copy of upload directories;
- a ContentBuilder configuration export, when available;
- a record of Joomla, PHP, ContentBuilder, and BreezingForms versions;
- an inventory of custom plugins and templates;
- an export or inventory of ContentBuilder menu items;
- an inventory of external scripts calling historical URLs.

Test backup restoration in a separate environment.

## Recommended sequence

1. Clone the site.
2. Stop new submissions.
3. Disable old ContentBuilder plugins, especially the system plugin, before the
   Joomla core migration.
4. Migrate Joomla to Joomla 6.
5. Migrate BreezingForms when views depend on it.
6. Keep the historical ContentBuilder tables.
7. Install the complete ContentBuilder NG package.
8. Review the installation log.
9. Run **Audit**, then **REPAIR DB** when required.
10. Test workflows with several user accounts.

## URL changes

Replace:

```text
option=com_contentbuilder
```

with:

```text
option=com_contentbuilderng
```

Known Joomla menu entries are normally updated automatically. URLs stored in
articles, modules, templates, JavaScript, or external applications must be reviewed
manually.

## Removed AJAX endpoint

The former endpoint is no longer supported:

```text
index.php?option=com_contentbuilder&task=ajax.display
```

Migrated actions use:

```text
index.php?option=com_contentbuilderng&task=api.display&format=json&action=...
```

Confirmed actions:

- `rating`
- `get-unique-values`
- `stats`

Example:

```text
index.php?option=com_contentbuilderng&task=api.display&format=json&action=stats&id=25
```

See [JSON API](api-json.md) for permissions and parameters.

## Points requiring attention

### Table collisions

If both a historical table and its NG target contain data, the installer does not
merge them automatically. Do not rename a single table without checking identifier
relationships across all ContentBuilder tables.

### BreezingForms

A view linked to a missing BreezingForms form reports a missing BF source or view.
**REPAIR DB** can synchronize some missing fields into the view, but the BF source
must exist.

### Custom plugins and templates

Historical custom plugins are not automatically ported to Joomla 6. Review and test
templates containing PHP.

### Joomla articles

Check categories, languages, publication states, and record/article associations.

### API and fields

The API requires view permissions and explicit authorization for each field. Existing
integrations that expected every field must be updated.

## Pre-migration checklist

- [ ] staging clone available
- [ ] restorable file and database backup
- [ ] historical tables retained
- [ ] old plugins inventoried
- [ ] BreezingForms compatible with Joomla 6
- [ ] customizations and old URLs inventoried
- [ ] maintenance window planned

## Post-migration checklist

- [ ] no critical installation log error
- [ ] audit completed
- [ ] table collisions absent or resolved
- [ ] views and storages present
- [ ] Joomla menus corrected
- [ ] list, details, creation, and editing tested
- [ ] publishing and deletion tested
- [ ] permissions tested with several roles
- [ ] linked articles checked
- [ ] imports, exports, and uploads checked
- [ ] API and external scripts updated
- [ ] Debug mode disabled after diagnosis

The root `MIGRATION_GUIDE.md` contains the detailed operational recovery and rollback
procedure.
