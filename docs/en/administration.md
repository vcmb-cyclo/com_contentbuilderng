# Administration

The main interface is available from **Components > ContentBuilder NG**.

## Data Storages screen

This screen lists known data structures. Available actions include creating, editing,
deleting, publishing, unpublishing, searching, filtering, sorting, and opening help.

Each column heading displays a descriptive tooltip on hover. Sortable headings keep
their normal behavior: click the heading to change the ordering.

Deleting internal storage may delete its data table. According to the confirmation
message in the interface, deleting an external-storage definition does not delete the
external table.

Recommended practices:

- back up before deletion;
- use a readable title and stable technical name;
- keep each storage focused on one coherent data structure;
- do not treat display order as a business rule.

## Editing storage

### Storage tab

Main settings:

- **Name:** technical name of the internal table;
- **Title:** administrator label;
- **Published:** enables or disables the storage;
- **Ordering**;
- **Internal** or **external table** mode.

Internal mode can create or rename the table. External mode requires an existing
table and restricts some column creation or renaming operations.

### Fields

Fields can be added, edited, ordered, published, unpublished, and deleted. After
schema changes, use **Datatable Sync** to align the definition and SQL table.

### File import

Confirmed formats:

- CSV;
- XLSX;
- XLS.

Visible options include CSV delimiter, encoding repair when `iconv` is available,
creating fields from headers, deleting existing records first, and column preview.
Some operations are refused for external tables.

Import checklist:

- [ ] backup completed
- [ ] small sample tested
- [ ] unique column names
- [ ] consistent column count
- [ ] encoding checked
- [ ] delete-existing-data option understood

> 📷 *Screenshot to add: Storage, Information, and file import tabs — `docs/en/img/admin-storage-edit.png`*

## Views screen

The view list supports create, copy, edit, publish, unpublish, delete, text/state/tag
filters, preview, and enabling or disabling Debug for selected views through
**Actions > Debug** and **Actions > No debug**.

The **Debug** column, immediately before **Published**, also toggles Debug for one
view. A green bug icon indicates that Debug is enabled. Each column heading displays
a descriptive tooltip on hover.

A view must be published and linked to a valid source to work outside administrator
preview.

## Editing a view

### View tab

This tab configures the name, tag, theme, publication, view Debug mode, source and
type, frontend/backend context, fields, and columns.

Main field-column options include:

- list inclusion;
- search inclusion;
- details link;
- API/Stats authorization;
- editable state;
- word wrapping;
- publication;
- ordering.

View Debug mode is independent from Joomla global Debug mode.
When enabled, the Debug control on this screen also uses a green bug icon. Clicking
it disables Debug without changing the publication state.

### Advanced options tab

Observed options include:

- automatically publishing new records;
- showing only published records;
- technical columns and metadata;
- New, Edit, Export, Print, and Back buttons;
- button bar or fixed list header;
- preview;
- view name and filters in the page title;
- filter and pagination selector;
- initial page size;
- external filter;
- exact matching;
- up to three sorting criteria;
- ratings and number of rating levels;
- alternative Save and Apply labels.

Joomla menu-item options can override some view values.

### Article tab

This tab configures Joomla article creation:

- enable creation;
- delete the article with its record;
- title field;
- category;
- access level;
- featured state;
- language;
- article language impact on the record;
- publish and unpublish delays;
- article publication impact on the record.

Test the category and Joomla permissions before enabling this in production.

### List intro text tab

Content displayed above the frontend list.

### List states tab

Custom states have a title, color, publication state, and optional plugin action.
Bundled actions include Trash and Untrash.

### Details tab

Contains the details template, top and bottom bars, Back button, PHP preparation code,
and theme-based example generation.

### Edit tab

Contains the editable template, top and bottom bars, upload directory, directory
protection, PHP preparation code, and **Edit by type**.

For a BreezingForms source, **Edit by type** delegates the form to BreezingForms and
replaces the native ContentBuilder NG editable template.

### Debug tab

This tab is visible when Debug is enabled for the view. Options include:

- show the BreezingForms ID column;
- show the internal CBNG record ID;
- enable CBNG logs;
- show request logs;
- show calculated permissions;
- show active filters, sorting, and pagination.

Disable Debug after diagnosis because its information is visible to users who can
access the view.

### API tab

Displays endpoints and examples for the current view. Administrator preview links use
a temporary signature and must not be reused as permanent application credentials.

### Emails tab

Configures creation and update notifications:

- enable notifications;
- subject;
- recipients;
- alternative sender address and name;
- upload attachments;
- HTML or text format;
- user template;
- administrator template.

Field variables can be used in some values, for example `{email}`. Test templates
with realistic, non-sensitive data.

### Permissions tab

Separates frontend and backend permissions, per-user settings, Joomla group matrices,
creation/editing limits, verification, and profile or registration behavior.

See [Permissions and ACL](permissions-acl.md).

## Users screen

This view-specific screen manages individual verification, per-user limits, and
publication of user access.

The exact meaning of every column depends on the configured verification workflow:
**To verify** for the target configuration.

<a id="plugins"></a>

## Joomla plugin management

The package installs several plugin groups. Enable and configure them from
**System > Manage > Plugins** only when required.

Bundled validation plugins:

- not empty;
- email;
- equal values;
- valid date;
- date not before a reference.

Other bundled families:

- `Joomla 6`, `Dark`, `Blank`, and `Khepri` themes;
- Trash and Untrash list actions;
- Pass-Through and PayPal verification;
- a submission sample;
- content plugins for downloads, images, ratings, verification, permissions, and
  statistics.

System-plugin options include synchronization limit per pass, disabling Joomla's
built-in article submission, disabling `com_content` caching for plugin execution,
automatic Joomla group assignment after verification, selected groups, and a list of
view IDs limiting that assignment.

Automatic group assignment changes user permissions directly. Test it with a
non-administrator account and limit it to required views.

The image scaling plugin sets a maximum file size in MB. The PayPal verification
plugin provides production credentials, test credentials, and Sandbox mode.
Compatibility with the current PayPal account and APIs is **To verify** before live
use.

> 📷 *Screenshot to add: filtering Joomla plugins by "ContentBuilder NG" — `docs/en/img/admin-plugins.png`*

## About screen

This screen displays version, date and build type, detected libraries, database
audit, component log, configuration transfer, and the **REPAIR DB** workflow.

The audit checks areas including duplicate indexes, historical tables and menu
entries, encoding/collation, old compacted data, missing audit columns, duplicate
plugins, BreezingForms field differences, invalid menu view references, frontend
permission inconsistencies, invalid element references, invalid generated-article
categories, and old language files.

Run repairs outside peak hours and only after a backup.

## Configuration transfer

The screen exports and imports selected JSON sections:

- views;
- storages;
- storage content when requested.

Import modes include at least merge and replace. Prefer merge for routine transfers.
Use replace only on a tested copy.

## Templates

Templates control details, editing, articles, and some list output. PHP preparation
areas can transform data before rendering. Restrict changes to trusted maintainers
and version them.

## API

The JSON API reuses view action permissions, authorized fields, visibility rules,
list/detail reads, record updates, statistics, ratings, and unique values. It is not
an ACL bypass.
