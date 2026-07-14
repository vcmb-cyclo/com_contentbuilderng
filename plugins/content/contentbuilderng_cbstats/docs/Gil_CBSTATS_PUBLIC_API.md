# CBStats public syntax / API reference


> Platform scope is inherited from the repository root `AGENTS.md`: Joomla 6 only, PHP 8.1+, MySQL/MariaDB only. No legacy compatibility layer is required.
> This document is the target public reference for CBStats. In the real repository, Codex must first locate the existing canonical ContentBuilder NG documentation/API reference and merge this information there rather than creating a duplicate documentation island.

## 1. General syntax

```text
{CBStats id=IdVue ...}
```

Common field-based form:

```text
{CBStats id=IdVue field=NomDuChamp output=TYPE}
```

## 2. Existing outputs

The following outputs must remain compatible:

```text
output=total
output=form_name
output=table
output=sum
output=min
output=max
```

## 3. JSON output

Target syntax after Pass 1:

```text
{CBStats id=IdVue field=NomDuChamp output=json}
```

Target response shape:

```json
[
  {"label":"Valeur A","value":12},
  {"label":"Valeur B","value":7}
]
```

The labels come from actual field data. The plugin must not embed business values in code.

## 4. Pie output

Target syntax after Pass 2:

```text
{CBStats id=IdVue field=NomDuChamp output=pie}
```

Behavior:

- uses the same normalized statistics engine as `table` and `json`;
- responsive;
- shows percentages in sectors when readable;
- details are available in legend and tooltip;
- supports multiple charts on one page.

## 5. Bar output

Target syntax after Pass 3:

```text
{CBStats id=IdVue field=NomDuChamp output=bar}
```

Behavior:

- horizontal bar chart;
- uses the same normalized statistics engine;
- uses the active Joomla locale and exactly one decimal for percentages;
- values are displayed inside bars only when readable;
- uses the same compact detail legend, tooltip semantics, colors and total style as Pie;
- supports multiple charts on one page.

## 6. Filters

Existing generic filter syntax includes:

```text
filter[field]=NomDuChamp
filter[value]=Valeur
```

Existing matching semantics to preserve include:

- trim behavior;
- wildcard `*`;
- alternatives separated by `|`.

Examples:

```text
filter[value]="200 km*"
```

```text
filter[value]="FFVelo|FFC"
```

These examples illustrate syntax only. No example value may be hardcoded into plugin logic.

## 7. External additions

External counts can be merged into field-statistics outputs with:

```text
add="Label=Number;Other label=Number"
```

Example:

```text
{CBStats id=25 field=Parcours output=pie add="100 km=5;150 km=3;200 km=2"}
```

If a label already exists, its signed delta is added to its calculated count.
Accepted values are strict integers such as `5`, `+5`, `0` and `-5`. Repeated
labels are cumulative. If the final calculated `add` result is negative, CBStats
temporarily uses `0` for that label before title mappings, sorting, percentages
and rendering. This also applies to a missing label receiving a negative delta.
The source data and `add` configuration remain unchanged, and a later zero or
positive result is used normally. Invalid syntax rejects the complete parameter.

`add` applies to `table`, `json`, `pie` and `bar`. It does not alter scalar outputs
and is accepted by the URL/API endpoint for `output=json`.

## 8. Display titles

Labels can be renamed for display with:

```text
titles="Original=Display title;Other original=Other display title"
```

The mapping is applied after `add` and before sorting. It does not change source
data, filtering or grouping. Unmapped labels remain unchanged. Two categories
renamed to the same display title are not merged. Semicolons delimit mappings and
the first equals sign separates each original label from its non-empty display
title. `sort=title` uses the final display titles.

## 9. Sorting

Target generic syntax:

```text
sort=none|title|value
dir=asc|desc
```

Defaults:

```text
sort=none
dir=asc
```

`sort=title` uses the active Joomla language locale and natural numeric-label
ordering. `sort=value` compares counts numerically. Sorting is performed by the
common normalized engine, so Table, JSON, Pie and Bar share
the same order.

Examples:

```text
{CBStats id=25 field=Parcours output=json sort=title dir=asc}
```

```text
{CBStats id=25 field=Parcours output=bar sort=value dir=desc}
```

The numeric IDs and field names above are documentation examples only.

## 10. JSON contract

Normalized records use:

```json
{
  "label": "<actual field value>",
  "value": 42
}
```

Rules:

- `label` is a string from actual data after existing normalization/grouping semantics;
- `value` is numeric;
- no HTML wrapper in `output=json`;
- valid UTF-8;
- valid JSON.

## 11. Generic chart text

Default generic format:

```text
<label> — <value> (<percentage> %)
```

Total:

```text
Total : <sum>
```

The plugin core must not hardcode domain-specific nouns such as `inscrits`.

## 12. Compatibility and permissions

All outputs must preserve the plugin's existing:

- STATS permissions;
- debug behavior;
- filter semantics;
- security/escaping requirements.

## 13. URL/API data outputs

The existing `action=cbstats` endpoint supports:

```text
output=json|total|sum|min|max|form_name
```

`field` is required for `json`, `sum`, `min` and `max`. It is not required for
`total` or `form_name`. Filters reuse the common CBStats engine. Sorting, signed
`add` and `titles` apply only to `json`. The JSON output remains the raw normalized
array; scalar outputs use the standard ContentBuilder NG API success envelope.
`table`, `pie` and `bar` are not available through the URL endpoint.

## 14. Status tracking

Codex should update this section in the real canonical documentation after each pass:

| Feature | Target pass | Status |
|---|---:|---|
| Existing outputs | Existing | Preserve |
| Common normalized engine | 1 | Implemented and validated |
| `output=json` | 1 | Implemented and validated |
| `output=pie` | 2 | Implemented and validated |
| `output=bar` | 3 | Implemented and validated |
| `add` external counts | Intermediate | Implemented and validated |
| Signed `add` deltas and `titles` | Finalization | Implemented; awaiting prod-test validation |
| URL scalar outputs | 1C | Implemented and validated |
| Security/error hardening | Finalization | Implemented and validated |
| Cross-repository docs/API | 4 | Completed |
