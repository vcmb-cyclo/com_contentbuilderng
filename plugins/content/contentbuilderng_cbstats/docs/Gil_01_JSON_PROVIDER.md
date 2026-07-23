# Pass 1 — Generic field statistics engine + JSON output


> Platform scope is inherited from the repository root `AGENTS.md`: Joomla 6 only, PHP 8.3+, MySQL/MariaDB only. No legacy compatibility layer is required.
## Mission

Refactor the existing CBStats field-statistics logic into one normalized PHP source of truth and add:

```text
output=json
```

Do not implement Pie, Bar, Chart.js, JavaScript chart rendering or chart CSS in this pass.

## Preconditions

Before changes:

- read root `AGENTS.md`;
- read plugin `AGENTS.md`;
- read `Gil_CBSTATS_SPECIFICATION.md`;
- inspect current `output=table` behavior;
- inspect current filter, permissions and debug behavior;
- inspect existing tests and language files.

## Existing outputs to preserve

```text
output=total
output=form_name
output=table
output=sum
output=min
output=max
```

No regression is acceptable.

## Absolute genericity rule

Do not hardcode:

- view IDs;
- field names;
- business values;
- business labels;
- business-specific numeric values;
- business-specific ordering.

`id=IdVue` and `field=NomDuChamp` come from the `{CBStats ...}` tag.

## Common internal engine

Create or consolidate a common internal function, for example `getFieldStats()`, based on the current `output=table` calculation behavior.

Use the repository's naming/style conventions; the exact function name may differ.

The engine must:

- read all actual values present in the requested field for the requested view;
- apply existing `filter[field]` and `filter[value]` behavior;
- preserve trim behavior;
- preserve wildcard `*` behavior;
- preserve alternatives `|` behavior;
- preserve STATS permissions;
- preserve `debug=1` behavior;
- group identical values using existing semantics;
- count occurrences;
- preserve current empty-value handling required by `output=table`.

## Normalized PHP result

Target structure:

```php
[
    ['label' => '<actual field value>', 'value' => <calculated integer>],
]
```

This becomes the shared source for:

- `output=table`;
- `output=json`;
- future `output=pie`;
- future `output=bar`.

## JSON syntax

Add:

```text
{CBStats id=IdVue field=NomDuChamp output=json}
```

Expected shape:

```json
[
  {"label":"<actual value>","value":42}
]
```

## JSON constraints

- Valid JSON.
- Valid UTF-8.
- No HTML wrapper.
- No JavaScript wrapper.
- No Chart.js.
- No CSS.
- No Pie or Bar rendering.

## Sorting

Implement or prepare the normalized engine so the generic syntax is supported consistently:

```text
sort=none|title|value
dir=asc|desc
```

Defaults:

```text
sort=none
dir=asc
```

Do not add `custom` ordering in this pass.

## Debug

Update `debug=1` so `json` is recognized as an allowed output and is reported consistently with current debug conventions.

## Language files

Inspect and update the actual language files used by the plugin, including the maintained locales:

- `fr-FR`
- `en-GB`
- `de-DE`

Only add keys actually needed by this pass.

## Tests

At minimum verify:

- all existing outputs still work;
- `output=table` output is unchanged for equivalent input;
- `output=json` matches the normalized engine;
- filters still work;
- trim, `*` and `|` still work;
- permissions still work;
- empty values preserve table behavior;
- UTF-8 labels encode correctly;
- no business value is hardcoded.

## Documentation

After implementation, update:

- plugin-local public syntax documentation;
- existing ContentBuilder NG documentation/API reference where CBStats is described;
- plugin description only if it can accurately mention the newly implemented JSON capability.

Follow `Gil_04_DOCUMENTATION_AND_API.md`.

## Out of scope

Do not:

- add Pie;
- add Bar;
- add Chart.js;
- add chart assets;
- redesign unrelated plugin code;
- modify other plugins/components unless strictly necessary and justified.

## Definition of done

The pass is complete only when:

- common normalized engine exists;
- `table` uses the common engine where applicable without regression;
- `json` uses the same engine;
- tests/checks have been run;
- debug and language support are updated;
- documentation for JSON is updated;
- changed files and test results are reported.
