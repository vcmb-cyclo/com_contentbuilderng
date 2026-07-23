# Pass 3 — Bar chart output


> Platform scope is inherited from the repository root `AGENTS.md`: Joomla 6 only, PHP 8.3+, MySQL/MariaDB only. No legacy compatibility layer is required.
## Mission

After the common normalized engine and Pie output are validated, add:

```text
output=bar
```

Bar must reuse the same normalized data source as Table, JSON and Pie.

## Syntax

```text
{CBStats id=IdVue field=NomDuChamp output=bar}
```

Sorting:

```text
sort=none|title|value
dir=asc|desc
```

## Genericity

No hardcoded:

- view ID;
- field name;
- business value;
- business label;
- business-specific order;
- business-specific count/percentage.

## Presentation

- Horizontal bar chart.
- Use `indexAxis: 'y'` when compatible with the actual Chart.js version used by the project.
- Reuse the same generic legend/detail semantics as Pie.
- Reuse the same tooltip semantics as Pie.
- Display numeric values on bars only when readable and without clutter.
- Reuse the Pie presentation service for stable colors and locale-aware percentages.
- Use exactly one decimal with the active Joomla locale.
- Size chart height from the item count within a practical responsive limit.
- Reuse the compact Pie legend and translated total capsule.

Generic detail format:

```text
<label> — <value> (<percentage> %)
```

Tooltip:

```text
<label>
■ <value> (<percentage> %)
```

The tooltip title is centered and the second line retains the matching bar color
marker. Values are drawn inside a bar only when its rendered width can contain
the text.

Total:

```text
Total : <sum>
```

Static text must use language strings.

## Architecture

Do not add a second statistics engine.

Expected flow:

```text
Common PHP field statistics engine
        ↓
Normalized array
        ↓
Safe JSON payload
        ↓
Bar renderer
```

## Assets

Reuse the chart and CBStats asset pipeline created/confirmed during Pass 2.

Do not load duplicate copies of Chart.js, plugins, CSS or CBStats JavaScript.

All CBStats classes must use the `cbstats-` prefix.

## Multiple charts

Verify all combinations:

- two Bar charts;
- Pie + Bar;
- multiple mixed charts in one article/page.

Requirements:

- unique IDs;
- isolated configuration per chart;
- assets loaded once;
- no global state collision.

The Bar preset depends on the existing local Chart.js asset and shared Pie detail
styles, so mixed Pie/Bar pages load each underlying asset once through Joomla's
Web Asset Manager.

## No-data behavior

Empty data must show a clean localized state and must not cause JavaScript errors.

## Debug

Add `bar` to `debug=1` allowed output handling.

## Documentation

Update:

- plugin public syntax/API docs;
- main ContentBuilder NG docs/API reference where CBStats is documented;
- plugin description if chart support should be advertised.

## Tests / acceptance

Verify:

- common engine is reused;
- horizontal rendering works;
- sorting works where supported;
- values/percentages/totals are correct;
- multiple charts coexist;
- UTF-8 labels are safe;
- empty data is safe;
- assets load once;
- non-chart outputs do not regress.
