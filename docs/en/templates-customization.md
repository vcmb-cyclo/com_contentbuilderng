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

Templates and email settings can use field variables. The language help documents
forms such as:

```text
{email}
{first_name}
{RECORD_ID}
```

The available variable name depends on the source field name. Use technical names
that remain stable when labels are translated.

## Simple email example

```html
<h2>New request</h2>
<p>Name: {name}</p>
<p>Email: {email}</p>
<p>Record: {RECORD_ID}</p>
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

Use Joomla template overrides for component layout changes that should remain outside
the extension package. Create overrides through the Joomla template manager where
possible.

The exact path proposed by Joomla's **Create Overrides** screen for each layout is
**To verify** on the installed site.

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

> **TODO screenshot:** generating a template example and opening the preparation
> editor.
