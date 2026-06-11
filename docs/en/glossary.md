# Glossary

## API allowed

Field option permitting exposure through the API, statistics, and some plugin output.

## Joomla article

Joomla content optionally created and linked to a ContentBuilder NG record.

## Audit

Diagnostic on the **About** screen that scans database, menu, collation, plugin, and
field issues before any repairs.

## Backend

The Joomla administrator application.

## BreezingForms / BF

A form component that can serve as the source of a ContentBuilder NG view.

## CB

Historical abbreviation for ContentBuilder.

## CBNG

Abbreviation for ContentBuilder NG.

## Field / element

A source data definition with list, search, editing, API, and rendering options.

## Collation

MySQL or MariaDB rules for comparing and sorting text.

## Datatable Sync

Administrator action synchronizing a storage definition with its SQL table.

## Details

Page displaying one record through the details template.

## Edit by type

Option delegating the editor to the source component, for example BreezingForms.

## Record

One data entry, usually one source row, identified by `record_id`.

## List state

A custom business state assigned to a record, separate from publication.

## Frontend

The public site or user-facing Joomla application.

## Joomla group

User group used by ACL and permission inheritance.

## Layout

Joomla presentation selected by a menu item, such as standard list, cards, tiles, or
compact table.

## List Access

ContentBuilder NG permission controlling access to a list.

## Own

Rule restricting an action to records owned by the current user.

## Content plugin

Joomla plugin transforming a tag inside a template or article, such as `CBStats`,
`CBRating`, `CBDownload`, or `CBImageScale`.

## Preparation

PHP code executed before rendering a details or edit template.

## Publication

State controlling visibility of a view or record. Start and end dates can also apply.

## Rating

A score assigned to a record when the view allows it. Aggregated values are cached and
exposed through the API and the `CBRating` plugin.

## REPAIR DB

Repair workflow offered by the About screen after an audit.

## Sparse fieldset

`fields[resource]` API parameter limiting returned fields and resources.

## Stats

Permission and endpoint providing statistics for a view.

## Source / Type

The origin of a view's data, identified by a `type` (for example `contentbuilderng`
or `breezingforms`) and a `reference_id`.

## Internal storage

Storage whose Joomla-prefixed table is created and managed by ContentBuilder NG.

## External storage / bytable

Storage connected to an existing SQL table.

## Template

HTML/PHP configured in a view to display or edit data.

## Theme

Plugin providing styles, JavaScript, and example templates.

## View

Functional configuration connecting a source to lists, details, editing, permissions,
and behavior.

## Verification

Optional step conditioning permissions or workflows, with plugins such as
Pass-Through or PayPal.
