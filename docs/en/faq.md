# FAQ

## Is ContentBuilder NG the former official Crosstec product?

No. It is a community modernization based on ContentBuilder and is not an official
Crosstec product.

## Which versions are supported?

Joomla 6.0 or later and PHP 8.3 or later.

## Can I install GitHub's “Source code” ZIP?

It is not recommended. Use the assembled
`com_contentbuilderng-<version>.zip` release asset.

## Should I uninstall the former ContentBuilder before migration?

No. Keep its tables and install ContentBuilder NG over the migrated site after a
backup.

## Are historical tables migrated automatically?

Recognized names are migrated when no two competing non-empty tables collide.

## What does “BF View not found” mean?

The ContentBuilder NG view points to a missing or invalid BreezingForms form.

## What is the difference between a view and storage?

Storage defines the physical data structure. A view defines display, editing,
permissions, and behavior for a source.

## Can I use an existing SQL table?

Yes, through external storage. Back it up and check its schema. Some field creation
and renaming operations are restricted.

## Why is a new record missing from the list?

Check automatic publication, published-only mode, language, filters, ownership, and
View permission.

## Why can a user see the list but not details?

**List Access** controls the list while **View** controls record details. Check both.

## Why is the Edit button missing?

The button must be enabled, fields must be editable, an edit template must exist, and
the user must have Edit permission.

## Can users be restricted to their own data?

Yes, with own-record filters and permissions. Test authenticated and anonymous
submission workflows separately.

## Can ContentBuilder NG create Joomla articles?

Yes. A view can create and synchronize articles with configurable title, category,
language, access, and publication.

## Which import formats are supported?

CSV, XLSX, and XLS for storages. Test a small file first.

## Which export format is supported?

The frontend list can provide XLSX export when enabled.

## How do I expose a field through the API?

Publish it, enable **API allowed**, and grant the required API permissions.

## Why does `fields[records]=total` remove `ratings`?

Sparse fieldsets remove resources that were not requested. Add, for example,
`fields[ratings]=average`.

## Is API permission enough for statistics?

No. `action=stats` uses **Stats** permission. Grouping or filter fields must also be
API-authorized.

## What does “Field is not allowed for API/Stats” mean?

The field is unpublished, is not API-authorized, or the supplied name/label does not
match a view field.

## Can the API create a record?

The current controller requires `record_id` for `POST`, `PUT`, and `PATCH`. Creation
without it is **To verify** and must not be assumed.

## What is view Debug mode for?

It displays a badge and, depending on settings, identifiers, permissions, filters,
and request logs. Disable it after diagnosis.

## Where are the logs?

In `com_contentbuilderng.log` under the Joomla log path or site `logs` directory. The
**About** screen can display them.

## Can I uninstall without losing data?

No. Uninstall SQL removes ContentBuilder NG tables. Export and back up first.

## Is REPAIR DB risk-free?

No. It can modify schema and data. Back up, run it outside peak hours, and review each
step.
