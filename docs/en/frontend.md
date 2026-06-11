# Frontend use

The frontend is built from a published view and a Joomla menu item, or from a routed
URL to a ContentBuilder NG controller.

## Available menu types

- **List View**;
- card, tile, compact, or custom list layouts;
- **Create** a record;
- display specific **Record Details**;
- display the user's **Latest** record;
- display a **Public Views List**.

Menu options can override category, pagination, action bars, author, Back button, and
filters.

## Viewing a list

Depending on configuration, users can search authorized fields, apply filters, sort
columns, choose page size, open details, select several records, export XLSX, and
create, edit, delete, publish, or change state.

Actions are visible only when both display configuration and calculated permissions
allow them.

> 📷 *Screenshot to add: frontend list with search bar, filters, and pagination — `docs/en/img/frontend-list.png`*

## Search and filters

A field must be marked **Include in search** before it can be searched.

Menu items can impose field filters. `|` represents alternatives in configured
filters, while exact filtering changes matching behavior. The page title can show the
active field filter; the language help states that free-text search is not included
in that title.

## Record details

Details may include template fields, creation/modification information, action bars,
Back and Print buttons, previous/next links, and content plugins embedded in the
template.

## Creation

Users must access a published view, have **New** permission, remain below their
creation limit, satisfy any verification, and pass configured validation or captcha.
New records may be published automatically or remain unpublished.

## Editing

**Edit** permission is required. With own-record restrictions, the user must also be
recognized as the owner, or use the same session for some anonymous workflows.

Editing can use either the native ContentBuilder NG template or the source editor,
including BreezingForms.

## Deletion

Deletion is available from list and edit controllers and requires the corresponding
permission. Depending on view settings, the linked Joomla article can also be
deleted.

Before granting deletion to non-administrators, test list deletion, editor deletion,
linked articles, and uploaded-file behavior. Uploaded-file behavior is **To verify**
for the source and template in use.

## Publication, state, and language

Separate permissions control publication, custom state, language, and full article
settings.

A record can be hidden by publication state, future start date, end date, language,
filters, published-only mode, or own-record restrictions.

## Latest record

The **Latest** menu type opens the user's latest entry. When none exists, the language
strings indicate a redirect to record creation.

## Public views list

This menu displays selected views and can show the identifier, tag, permissions to
view/create/edit, and introductory text.

## Debug mode

When enabled on the view, a DEBUG badge appears. A collapsible panel can show
identifiers, permissions, filters, and request logs. It is a diagnostic tool and
should not remain enabled for the public.

## Common messages

### “View not found”

The view is missing, unpublished, or the menu uses the wrong ID.

### “BF View not found”

The view points to a missing or invalid BreezingForms source.

### “Record not found”

The record is missing or hidden by publication, language, ownership, or a filter.

### “List access not allowed”

The group does not have frontend **List Access**.

### “You are not allowed to edit”

Check Edit permission, ownership, editing limits, and verification.

### “This view is not exportable”

XLSX export is disabled or unavailable in the current context.

Frontend test checklist:

- [ ] guest
- [ ] registered user
- [ ] record owner
- [ ] non-owner
- [ ] editor or moderator
- [ ] another language
- [ ] published and unpublished record
- [ ] filters applied and reset
