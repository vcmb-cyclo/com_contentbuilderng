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

CBStats inserts dynamic statistics from a ContentBuilder NG view into Joomla
content. Its general syntax is:

```text
{CBStats id=ViewID ...}
```

Examples:

```text
{CBStats id=3 output=total}
{CBStats id=3 output=form_name}
{CBStats id=3 field=FieldName output=table}
{CBStats id=3 field=FieldName output=json sort=title dir=asc}
{CBStats id=3 field=FieldName output=pie sort=value dir=desc}
{CBStats id=3 field=FieldName output=bar sort=value dir=desc}
{CBStats id=25 field=Route output=pie title="👥 Total registrations" export=manual}
{CBStats id=3 field=Category output=pie add="Existing=-2;External=3"}
{CBStats id=3 field=Category output=table titles="1=Group 1;2=Group 2"}
{CBStats id=3 field=Category output=bar add="1=-2;2=3" titles="1=Group 1;2=Group 2" sort=value dir=desc}
{CBStats id=3 field=FieldName output=sum}
{CBStats id=3 field=FieldName output=min}
{CBStats id=3 field=FieldName output=max}
{CBStats id=3 filter[field]=Status filter[value]="Open" output=total}
{CBStats id=3 filter[field]=Status filter[value]="Open*" output=total}
{CBStats id=3 filter[field]=Status filter[value]="Open* | Pending" output=total}
```

### Frozen manual export

Add `export=manual` to a Pie, Bar or Table tag to show the final labels, values and total together with a visible `source=manual` tag. Filters, additions, renamed titles and sorting are already incorporated in the frozen data. The centered copy button copies exactly the displayed syntax, which can be pasted into another article without depending on the original view.

| Output | Result | `field` required |
| --- | --- | --- |
| `total` | Number of matching records | No |
| `form_name` | View title, or its name when the title is empty | No |
| `table` | Static HTML value/count table | Yes |
| `json` | Raw JSON array of `{label,value}` objects | Yes |
| `pie` | Responsive Pie chart | Yes |
| `bar` | Responsive horizontal bar chart | Yes |
| `sum` | Count-weighted sum of numeric field values | Yes |
| `min`, `max` | Smallest and largest numeric value | Yes |

`table`, `json`, `pie` and `bar` consume the same normalized PHP data. An empty
table displays `0`; empty charts display a localized no-data message. JSON has no
HTML or JavaScript wrapper:

```json
[
  {"label":"Value A","value":12},
  {"label":"Value B","value":7}
]
```

Use `filter[field]=FieldName` and `filter[value]="Value"` together. Without a
wildcard, `filter[value]="Open"` matches only the exact value. With
`filter[value]="Open*"`, values such as `Open` and `Open (external)` can
match. The `|` character separates alternatives and surrounding spaces are
trimmed. In article tags, `field=FieldName value="Value"` is also accepted as a
filter shorthand when `filter[field]` is absent.

The grouped field and filtered field may differ:

```text
{CBStats id=15 field=Element-1 filter[field]=Element-2 filter[value]="Dét* | 3 | 4" output=bar}
```

Here, `field=Element-1` is grouped and displayed, while
`filter[field]=Element-2` is used only to select records. `*` is a wildcard,
`|` separates alternatives, and surrounding spaces are trimmed. Without a
wildcard, matching is exact.

When the displayed field is also filtered, the following shorthand is strictly
equivalent to the complete filter on `Element-2`:

```text
{CBStats id=15 field=Element-2 value="Dét* | 3 | 4" output=bar}
```

`value=` is reserved for this same-field shorthand. Do not confuse it with
`values=`, which is used exclusively by `source=manual`.

Field-statistics outputs support `sort=none|title|value` and `dir=asc|desc`.
The defaults are `sort=none` and `dir=asc`. `sort=none` preserves the engine's
natural order; `sort=title` uses locale-aware natural label ordering;
`sort=value` compares counts numerically. `dir` changes the chosen sort direction.

For `table`, `json`, `pie` and `bar`, `add="Label=SignedInteger"` applies
cumulative deltas: positive adds, zero changes nothing and negative removes
occurrences. If the final calculated result is negative, CBStats temporarily
uses `0` for that label before sorting, percentage calculation and rendering;
source data remains unchanged, and a later zero or positive result is used
normally. `titles="Original=Display title"` changes display labels
without changing source data or merging categories. Unmapped labels stay
unchanged. Processing order is data, filters, grouping, `add`, `titles`, sorting,
then output; `sort=title` uses final display titles. Semicolons delimit entries
and the first equals sign separates each pair.

Pie and Bar use localized percentages with one decimal, tooltips, a compact
detailed legend and a total. Charts are responsive, can coexist in any Pie/Bar
combination on one page, and reuse the same locally bundled chart assets.

`sum`, `min` and `max` return `0` when the matching field values are empty or not
all numeric. Date fields may provide chronological `min` and `max`, while `sum`
remains `0`. All field-based outputs enforce the field's API/Stats availability.

CBStats always enforces the view's STATS permission. For URL/API use, check the
view's **API + Rights** settings, API/Stats field availability and the **API** tab.
The supported URL outputs are `json`, `total`, `sum`, `min`, `max` and
`form_name`; JSON also accepts `add`, `titles`, `sort` and `dir`, while Table,
Pie and Bar remain content-only. Public errors
remain generic. `debug=1` requests diagnostics only when DEBUG is enabled on the
target ContentBuilder NG view; it never grants access or changes view, field or
STATS permissions.

The complete syntax of every other content plugin is not exhaustively documented
in the repository: **To verify** from its installed plugin help and templates.

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
