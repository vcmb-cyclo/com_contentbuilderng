# Pass 2 — Pie chart output


> Platform scope is inherited from the repository root `AGENTS.md`: Joomla 6 only, PHP 8.3+, MySQL/MariaDB only. No legacy compatibility layer is required.
## Mission

After Pass 1 is implemented and validated, add:

```text
output=pie
```

Pie must consume the existing normalized field statistics engine. Do not duplicate calculation logic.

## Preconditions

The implementation must already have:

```php
[
    ['label' => '<actual field value>', 'value' => <calculated integer>],
]
```

as a common internal result used by JSON and table-related field statistics.

If Pass 1 is incomplete, stop and report the missing prerequisite rather than creating a second statistics engine.

## Syntax

```text
{CBStats id=IdVue field=NomDuChamp output=pie}
```

Sorting may be applied using:

```text
sort=none|title|value
dir=asc|desc
```

## Genericity

Do not hardcode:

- view IDs;
- field names;
- business values;
- business labels;
- business ordering;
- business-specific counts or percentages.

## Presentation requirements

- Responsive chart.
- Target default maximum chart width: 300 to 320 px.
- Do not force the width of the containing card/container.
- Show percentages inside sectors when readable.
- Do not show raw count in sectors by default.
- Do not systematically show field labels inside sectors.
- Show full details in legend and tooltip.
- Format percentages with exactly one decimal using the active Joomla language locale.
- Center the compact legend below the chart.
- Present the total in a subtle, centered compact capsule.

## Generic legend format

```text
● <actual label> — <calculated value> (<calculated percentage> %)
```

## Generic tooltip format

```text
<actual label>
■ <calculated value> (<calculated percentage> %)
```

The first tooltip line is centered. The second line uses the sector color marker
followed by a visible space and the calculated details.

Sorting is applied once to the normalized common dataset. `sort=title` uses the
active Joomla locale and natural numeric-label ordering; `sort=value` compares
counts numerically. The same order is therefore shared by Table, JSON, Pie and
the future Bar renderer.

## Generic total

```text
Total : <calculated sum>
```

All static wording must come from language strings.

Do not hardcode the business noun `inscrits` in the generic plugin core.

## Renderer and assets

Implementation decision for Pass 2:

- Chart.js 4.5.1 is bundled locally with the plugin;
- percentage labels use the plugin's own lightweight Chart.js extension;
- no `chartjs-plugin-datalabels` dependency is added;
- Joomla's Web Asset Manager registers and loads the assets once per page.

The historical design proposes Chart.js and a data-label plugin. Before adding dependencies:

1. inspect the repository for an existing Chart.js version or asset-loading convention;
2. inspect the plugin manifest/media structure;
3. reuse existing compatible assets where practical;
4. avoid duplicate library loading;
5. do not force historical media paths if the actual repository uses another convention.

CBStats CSS classes must begin with:

```text
cbstats-
```

## Asset loading requirements

With one or many charts on the page:

- Chart library loads once;
- optional data-label plugin loads once;
- CBStats CSS loads once;
- CBStats JavaScript loads once.

## Multiple charts

- Generate unique IDs.
- Do not rely on a single hardcoded canvas ID.
- Two Pie charts on one page must both render.
- A Pie and a Bar on one page must not collide once Bar exists.

## Data transfer safety

- Use safe JSON encoding for the JS payload.
- Escape HTML labels where rendered in HTML.
- Do not build JavaScript data structures by unsafe string concatenation.
- JavaScript must not query ContentBuilder or BreezingForms directly.
- No AJAX is required merely to reconstruct server-side statistics.

## No-data behavior

If the normalized dataset is empty:

- show a clean localized empty-state message consistent with plugin conventions;
- do not initialize an invalid chart;
- do not generate a JS error.

## Compatibility

Verify compatibility with the versions/frameworks currently supported by the repository, including Joomla 6/Cassiopeia/Masonry where applicable.

Do not assume a specific version without checking project declarations and existing CI/tests.

## Debug

Add `pie` to `debug=1` allowed output handling.

## Documentation

Update:

- plugin public syntax/API docs;
- ContentBuilder NG docs/API reference where CBStats is exposed;
- plugin description only if the description should now advertise chart support.

## Tests / acceptance

Verify:

- Pie uses the common engine;
- no duplicate calculation logic exists;
- percentages are correct;
- total is correct;
- one chart works;
- multiple charts work;
- UTF-8 labels work;
- empty data works;
- sorting works where implemented;
- assets load once;
- existing non-chart outputs do not regress.

## Out of scope

Do not implement Bar in this pass.
