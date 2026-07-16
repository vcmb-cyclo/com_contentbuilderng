# CBStats functional and technical specification

## 1. Purpose

CBStats is a generic Joomla content plugin integrated into the ContentBuilder NG repository. It exposes statistics through `{CBStats ...}` tags and must work with arbitrary ContentBuilder views and fields without embedding organization-specific knowledge.

Plugin location:

`plugins/content/contentbuilderng_cbstats`

## 2. Non-negotiable principles

### 2.1 Generic behavior

CBStats must not hardcode:

- a view ID;
- a field name;
- a real field value;
- a business label;
- a business-specific order;
- a business-specific number or percentage.

The caller provides context through tag parameters such as:

```text
id=IdVue
field=NomDuChamp
```

### 2.2 Existing CBStats public API stability

Existing syntax and behavior must remain functional unless a specific approved migration says otherwise.

### 2.3 One calculation engine

Field statistics must be calculated once in PHP and reused by table, JSON and chart outputs.

No visual renderer may implement its own independent filtering/grouping/counting logic.

## 3. Current public outputs to preserve

The plugin already accepts:

```text
output=total
output=form_name
output=table
output=sum
output=min
output=max
```

These outputs are existing public contracts and must not regress.

## 4. Existing filtering behavior to preserve

Existing filters include:

```text
filter[field]=NomDuChamp
filter[value]=Valeur
```

The current behavior to preserve includes:

- trimming/normalization behavior already implemented by the plugin;
- wildcard `*` behavior;
- alternatives separated by `|`;
- permissions for STATS;
- `debug=1` behavior;
- existing empty-value behavior for `output=table`.

Before refactoring, Codex must inspect the implementation to capture exact semantics, including case sensitivity, whitespace handling and wildcard matching details. Do not guess or silently change existing behavior.

## 5. Normalized field statistics engine

### 5.1 Target internal contract

Create or consolidate a common internal method, for example `getFieldStats()`, using the current `output=table` logic as the behavioral reference.

The exact function name may follow the existing code style; the important requirement is a single normalized source of truth.

Target PHP structure:

```php
[
    ['label' => '<actual field value>', 'value' => <calculated integer>],
]
```

### 5.2 Required responsibilities

The engine must:

- read actual values present in `field=NomDuChamp` for `id=IdVue`;
- apply existing `filter[field]` and `filter[value]` behavior;
- preserve trim, wildcard `*` and alternatives `|` behavior;
- preserve STATS permission checks;
- preserve `debug=1` behavior;
- group identical values according to current semantics;
- count occurrences;
- preserve the current treatment of empty values required by `output=table`.

### 5.3 Consumers

The normalized PHP array becomes the source for:

```text
output=table
output=json
output=pie
output=bar
```

The HTML table uses intrinsic content width within a horizontally scrollable
responsive wrapper. Text cells align to the start, numeric cells align to the
end, and the shared Pie/Bar detail legend uses compact, readable row spacing.

### 5.4 External additions

Field-statistics outputs accept external counts through:

```text
add="Label=Number;Other label=Number"
```

The common engine trims labels and numbers, merges an existing label by signed
addition, creates a missing label and combines repeated labels. Accepted values
are strict signed integers (`5`, `+5`, `0`, `-5`). After the final `add` result is
calculated for a label, a negative result is temporarily normalized to `0` in
memory. That effective zero is used by title mappings, sorting, percentages and
all field-statistics renderers; source data and stored configuration are never
changed. As soon as a later calculated result is zero or positive, that result is
used normally. Label matching is exact after trimming. Invalid syntax still
rejects the complete `add` parameter; no partial addition is applied.

Display labels can be mapped with:

```text
titles="Original=Display title;Other original=Other display title"
```

`titles` is applied after `add` and before sorting. Unmapped labels stay unchanged;
two source labels mapped to the same display title remain separate normalized
items. `sort=title` uses final display titles. Semicolons delimit mappings and the
first equals sign separates the original and display title. Empty sides are invalid.

The complete order is filtering, grouping, signed `add`, `titles`, sorting and
output. `add` and `titles` apply only to `table`, `json`, `pie` and `bar`; they do
not change scalar outputs. The URL/API JSON output reuses the same parsers and
normalization path.

The distinct `title=` parameter customizes the localized total label in Table,
Pie and Bar. An empty value uses the translated default; a missing final colon is
added with localized punctuation. `background=` optionally applies a validated
background to those HTML containers. Unicode is preserved and HTML is escaped.

## 6. JSON output

### 6.1 Syntax

```text
{CBStats id=IdVue field=NomDuChamp output=json}
```

### 6.2 Contract

Output is the JSON representation of the normalized PHP array:

```json
[
  {"label":"<actual value>","value":42}
]
```

### 6.3 Constraints

- Use the project's safe JSON encoding approach.
- No HTML in `output=json`.
- Valid UTF-8.
- No JavaScript wrapper.
- No chart rendering.
- No CSS.

## 7. Sorting

### 7.1 Public syntax

```text
sort=none|title|value
dir=asc|desc
```

### 7.2 Defaults

```text
sort=none
dir=asc
```

### 7.3 Semantics

- `sort=none`: preserve natural/current result order.
- `sort=title`: sort by actual label.
- `sort=value`: sort by numeric count/value.
- `dir=asc`: ascending.
- `dir=desc`: descending.

Sorting must be generic and usable by table, JSON and graphical consumers when applicable.

Do not add `custom` ordering until a separate generic contract defines how custom order is supplied without embedding business values in code.

## 8. Pie output

### 8.1 Syntax

```text
{CBStats id=IdVue field=NomDuChamp output=pie}
```

### 8.2 Data source

Pie must consume the normalized field statistics engine. It must not recalculate statistics.

### 8.3 Presentation

- Responsive chart.
- Target default maximum chart width: 300 px, without fixing the containing card width.
- Show percentage only inside sectors when labels fit/readable.
- Do not show raw count inside sectors by default.
- Do not systematically show field labels inside sectors.
- Show details in legend and tooltip.

Generic legend format:

```text
● <actual label> — <calculated value> (<calculated percentage> %)
```

Generic tooltip format:

```text
<actual label> : <calculated value> (<calculated percentage> %)
```

Generic total format:

```text
Total : <calculated sum>
```

All static text must use language strings. Do not hardcode a business noun such as `inscrits`.

## 9. Bar output

### 9.1 Syntax

```text
{CBStats id=IdVue field=NomDuChamp output=bar}
```

### 9.2 Data source

Bar must consume the same normalized field statistics engine.

### 9.3 Presentation

- Chart.js bar chart if Chart.js is the selected existing/project-approved renderer.
- Horizontal orientation using `indexAxis: 'y'` when compatible with the installed Chart.js version.
- Same generic detail format as Pie.
- Same tooltip semantics as Pie.
- Display numeric values on bars only when readable and without clutter.

## 10. Front-end architecture

Target flow:

```text
ContentBuilder NG data
        ↓
CBStats PHP statistics engine
        ↓
Normalized PHP array
        ↓
JSON-safe payload
        ↓
Pie / Bar / Table renderers
```

The browser layer is a renderer, not a data source.

No AJAX is required merely to reconstruct statistics already available server-side.

## 11. Asset loading

Assets must be loaded once per page, including when multiple charts exist.

Potential assets include:

- Chart.js;
- chart data-label plugin if needed and compatible;
- CBStats CSS;
- CBStats JavaScript.

Codex must inspect the actual plugin manifest and media structure before selecting exact paths or filenames. Do not blindly force a historical suggested path when the repository already uses another convention.

All CBStats CSS classes must start with:

```text
cbstats-
```

## 12. Multiple charts per page

Requirements:

- unique HTML IDs;
- no duplicated global initialization;
- assets loaded once;
- each chart receives only its own dataset/configuration;
- no collision between two Pie charts, two Bar charts, or mixed chart types.

## 13. No-data behavior

When no data is available:

- render a clean localized message or empty-state output consistent with current plugin conventions;
- do not create an invalid chart;
- do not throw a JavaScript error;
- do not emit malformed JSON.

## 14. Escaping and encoding

- Escape field labels and values for HTML context.
- Use proper JSON encoding for JSON and JavaScript payloads.
- Never interpolate unescaped values into JavaScript source.
- Preserve UTF-8 characters.

## 15. Localization

Public labels, errors, empty-state messages, totals and help text must use language keys.

At minimum, preserve/update the language families already maintained by the plugin, including:

- `fr-FR`
- `en-GB`
- `de-DE`

Codex must inspect the actual repository language files and naming conventions before adding keys.

## 16. Debug mode

`debug=1` must recognize newly supported outputs as they are implemented:

- JSON in Pass 1;
- Pie in Pass 2;
- Bar in Pass 3.

Debug output must remain safe and must not expose secrets or unnecessary internals.

## 17. Documentation contract

Every public syntax change requires updates to:

- plugin-local documentation;
- public shortcode/API syntax reference;
- main ContentBuilder NG documentation/API reference where CBStats is exposed;
- language files/help text when relevant;
- plugin manifest description if the old description no longer reflects capabilities.

Before creating documentation files in the real repository, find and update existing canonical files.

## 18. Implementation phases

### Pass 1 — normalized engine + JSON

Implement the common engine and `output=json`. Do not implement charts.

### Pass 2 — Pie

After Pass 1 is validated, implement `output=pie` using the common engine.

### Pass 3 — Bar

After Pie is validated, implement `output=bar` using the same engine.

### Pass 4 — documentation/API consolidation

Ensure all descriptions, syntax references, examples and API documentation reflect the implemented state.

## 19. Definition of done

A pass is complete only when:

- implementation matches this specification;
- existing behavior remains compatible;
- relevant tests/checks pass;
- debug mode is updated as needed;
- language keys are updated as needed;
- documentation for the implemented public behavior is updated;
- the final Codex report lists changed files and test results.
