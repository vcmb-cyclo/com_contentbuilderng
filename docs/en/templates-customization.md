# Templates and customization

ContentBuilder NG stores view-specific templates for record details, editing,
generated Joomla articles, emails, and some list output.

## Start from a generated example

The Details and Edit tabs can generate an example from the selected theme. Use that
output as the starting point instead of building the complete markup from memory.

Recommended workflow:

1. select a theme;
2. generate an example;
3. save and test it unchanged;
4. make one change at a time;
5. retain a copy outside the production database.

## Field variables

Templates and email settings reference fields by their technical name. The rendering
service (`TemplateRenderService`) replaces the following placeholders:

```text
{fieldname:label}     the field label
{fieldname:value}     the field value
{fieldname:item}      the editable field control inside an edit template
{value}               the raw value inside a column wrapper
{value_inline}        the raw value inside an article wrapper
{webpath fieldname}   absolute web path of an uploaded file
{CBSite} / {cbsite}   the site root URL
{hide-if-empty fieldname} ... {/hide}   hides a block when the field is empty
{hide-if-matches fieldname value} ... {/hide-if-matches}   hides a block when the field exactly matches that value
```

Use technical field names that remain stable when labels are translated.

## Display Conditions

`{hide-if-empty fieldname} ... {/hide}` hides the block when the field value is
empty. `{hide-if-matches fieldname value} ... {/hide-if-matches}` hides the block
when the current field value exactly matches `value`.

In details templates, these conditions apply to displayed values. In edit templates,
they also apply to read-only blocks using `{fieldname:value}`. A block containing
`{fieldname:item}` stays visible even when the value is empty or matches
`hide-if-matches`, so the user can enter or correct the field.

## Simple email example

```html
<p>New request:</p>
<p><strong>{name:label}</strong>: {name:value}</p>
{hide-if-empty message}
<p><strong>{message:label}</strong>: {message:value}</p>
{/hide}
```

Test both user and administrator templates and verify HTML/text mode.

## PHP preparation

Details and edit templates can run PHP preparation code before rendering. This is
powerful and carries the same risks as custom code:

- syntax errors can break the page;
- unescaped output can introduce XSS;
- SQL built manually can introduce injection vulnerabilities;
- upgrades do not validate custom business logic.

Restrict editing to trusted administrators. Prefer Joomla APIs, escape output, and
keep the code under version control.

## Column wrappers

Field options can wrap or transform display values. The language help describes
plain HTML wrappers, PHP transformations, and content-plugin tags.

Example:

```html
<strong>{value}</strong>
```

Do not inject untrusted values into raw attributes or scripts without escaping.

## Content plugins

Bundled content plugins include:

- `CBDownload`;
- `CBImageScale`;
- `CBRating`;
- `CBStats`;
- verification and permission-related output.

Confirmed statistics examples include:

```text
{CBStats id=3 output=total}
{CBStats id=3 field=FieldName output=table}
{CBStats id=3 filter[field]=Route filter[value]="200 km* | 300 km*" output=total}
```

Supported `CBStats` outputs include `total`, `table`, and `form_name`. Field
permissions for API/Stats are enforced. The complete syntax of every other content
plugin is not exhaustively documented in the repository: **To verify** from its
installed plugin help and templates.

## Joomla overrides

Frontend layouts live in `site/tmpl/<view>/` in the source (installed under
`components/com_contentbuilderng/tmpl/`). Use Joomla template overrides for component
layout changes that should remain outside the extension package.

Bundled list layouts (the `list` view):

- `default` (table);
- `listcompact`;
- `listcard`;
- `listtiles`;
- `listone`, `listtwo`, `listthree`.

The standard Joomla override path is:

```text
templates/<your_template>/html/com_contentbuilderng/list/default.php
```

> ℹ️ **Note:** the Joomla **System > Site Templates > [your template] > Create
> Overrides** screen lists the component views and copies the chosen layout to the
> right location. The exact path depends on the view name (`list`, `details`, `edit`,
> `latest`, `publicforms`) and the layout — *to verify* in your installation.

## Do not edit directly

Avoid direct changes to:

- files under `components/com_contentbuilderng`;
- files under `administrator/components/com_contentbuilderng`;
- bundled plugin files;
- generated release dependencies.

An update can replace them. Use stored view templates, Joomla overrides, a custom
plugin, or a maintained project patch instead.

## Good practices

- keep templates small;
- escape user-controlled data;
- avoid direct SQL;
- test guest and authenticated contexts;
- test empty values and uploads;
- keep a versioned copy;
- review custom PHP after updates;
- disable Debug after diagnosis.

> 📷 *Screenshot to add: generating a template example and opening the PHP preparation editor — `docs/en/img/templates-preparation.png`*
