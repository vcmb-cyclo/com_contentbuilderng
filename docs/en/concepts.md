# Core concepts

## View

A view is the central functional configuration. It connects a data source to a list,
record details, an edit form, permissions, publication rules, templates,
notifications, and optionally Joomla articles and the API.

The same source can be used by several views with different behavior and permissions.

## Form and source

In the historical interface, “form” can refer to a view configuration. The actual
source can be:

- ContentBuilder NG storage (`com_contentbuilderng`);
- a BreezingForms form (`com_breezingforms`).

**Edit by type** can delegate editing to the source component, particularly
BreezingForms.

## Record

A record is one business-data entry. ContentBuilder NG also stores metadata such as
publication, language, dates, state, ratings, and article links.

The API `record_id` is the business identifier resolved for the source.

## Storage

- **Internal storage:** ContentBuilder NG creates and manages a Joomla-prefixed table.
- **External storage:** the configuration points to an existing table.

External mode requires additional care. Some schema operations are restricted and
the table must already exist.

## Joomla article

A view can create a Joomla article for each record and configure its title field,
category, access level, language, featured state, publication dates, synchronization,
and optional deletion with the record.

## Field or element

Each field has independent properties including publication, list inclusion, search,
details link, API/Stats authorization, editability, ordering, label, sorting type,
and display wrappers.

A field not authorized for the API remains absent even when visible in a list.

## Permissions

Permissions are configured by Joomla group and separately for frontend and backend.
They can also apply only to records owned by the user.

Available actions include view, create, edit, delete, change state, publish, manage
full article settings, change language, rate, use the API, access statistics, and
access the list.

## List

Depending on configuration, a list provides search, filters, sorting, pagination,
multi-selection, publishing, custom states, creation, editing, deletion, XLSX export,
and preview links.

Several list layouts are bundled and selectable on the menu item: `default` (standard
table), `listcompact` (compact), `listcard` (cards), `listtiles` (tiles), plus
`listone`, `listtwo`, and `listthree`. They can be overridden (see
[Templates and customization](templates-customization.md)).

### List states and selection

When the matching options are enabled on the view, ContentBuilder NG stores per-record
and per-user states in `#__contentbuilderng_list_states` and
`#__contentbuilderng_list_records`. These support list actions (trash / restore) and
multi-selection.

## Templates

Templates define HTML for details, editing, articles, and some lists. PHP preparation
areas can modify data before rendering. Restrict changes to qualified users and keep
customizations under version control.

## Plugins

The package includes themes, validations, verifications, list actions, submission
plugins, content plugins for downloads, images, ratings and statistics, and a system
plugin.

## API

The JSON API reuses view permissions, explicitly authorized fields, and visibility
rules. It supports list and detail reads, record updates, statistics, ratings, and
unique values. It does not bypass Joomla ACL.

## Verification (verify)

Some actions (view, create, edit) can require a prior verification step, with a
validity period in days and a redirect URL. Plugins in the `contentbuilderng_verify`
family (for example `passthrough` and `paypal`) handle this flow. Verifications are
stored in `#__contentbuilderng_verifications` and per-user state in
`#__contentbuilderng_users`. *To verify against your configuration.*

## Rating

Views can allow a rating per record (the `rating` action). Aggregated values are
cached in `#__contentbuilderng_rating_cache` and exposed by the API
(`action=rating`) and the rating content plugin.
