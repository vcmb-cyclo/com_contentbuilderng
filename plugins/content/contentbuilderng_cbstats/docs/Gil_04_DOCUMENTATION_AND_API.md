# Pass 4 — Documentation, plugin description and ContentBuilder NG API/reference

## Purpose

The CBStats evolution is not complete when code works. The documentation, plugin description and ContentBuilder NG public/API reference must reflect the actual implemented capabilities.

This pass applies after each feature pass and again as a final consolidation.

## Fundamental rule: find canonical documentation first

Before creating or editing documentation:

1. search the repository for existing CBStats documentation;
2. search for ContentBuilder NG user documentation;
3. search for API/reference pages or syntax indexes;
4. search the plugin manifest and description language keys;
5. update canonical files rather than creating duplicate parallel documentation.

Potential search terms:

```text
CBStats
ContentBuilder NG - CBStats - Content - Statistiques
contentbuilderng_cbstats
{CBStats
output=table
output=total
API
shortcode
syntax
plugin description
```

## Documentation targets

### A. Plugin-local documentation

Document:

- purpose of CBStats;
- generic design;
- complete syntax;
- existing outputs;
- filters;
- wildcard `*`;
- alternatives `|`;
- sorting;
- JSON contract;
- Pie behavior;
- Bar behavior;
- debug behavior when relevant;
- permissions when relevant;
- multiple-chart behavior;
- no-data behavior;
- examples.

Only document features that are actually implemented in the current code version.

### B. Public syntax/API reference

Treat `{CBStats ...}` as a public API contract.

The canonical API/reference must include:

```text
id
field
output
filter[field]
filter[value]
sort
dir
debug
```

and supported values/semantics as implemented.

For `output=json`, document the exact response schema.

For graphical outputs, document that rendering consumes the same normalized statistics engine and does not change filter/count semantics.

### C. Main ContentBuilder NG documentation

Find where plugins/integrations are listed and update the CBStats section so users can discover:

- what the plugin does;
- where it is used;
- basic syntax;
- supported outputs;
- link/reference to detailed syntax if the documentation system supports cross-links.

### D. Plugin manifest description

Inspect the real plugin manifest XML and description keys.

Update:

- short description if outdated;
- long description/help text if present;
- `fr-FR`, `en-GB`, `de-DE` strings as maintained by the plugin.

Use `Gil_PLUGIN_DESCRIPTION.md` as proposed wording, adapted to the actual implementation state.

Do not advertise JSON/Pie/Bar before implementation and validation.

### E. API documentation in CB

The user's requirement is explicit: CBStats must also be represented in **CB / API** documentation.

Codex must locate the existing ContentBuilder NG API/reference documentation and add/update the CBStats public contract there.

If there is no existing CB API/reference file:

- report that fact clearly;
- propose the best canonical location based on repository documentation structure;
- do not create a new top-level API system without justification.

## Recommended public examples

Use generic examples and clearly identify sample-only values.

```text
{CBStats id=25 output=total}
```

```text
{CBStats id=25 field=Parcours output=table}
```

```text
{CBStats id=25 field=Parcours output=json sort=title dir=asc}
```

```text
{CBStats id=25 field=Parcours output=pie}
```

```text
{CBStats id=25 field=Parcours output=bar sort=value dir=desc}
```

The plugin code must never depend on those example values.

## Language and terminology

Preferred generic wording:

```text
<label> — <value> (<percentage> %)
Total : <sum>
```

Avoid generic-core wording such as:

```text
45 inscrits
Total des inscrits
```

because CBStats may count any type of records, not only registrations.

## Release-aware documentation

After each pass:

- mark only implemented capabilities as available;
- remove “planned” status only when code and tests are validated;
- ensure examples correspond to real supported syntax;
- update changelog/release notes if the repository has a canonical change log and project policy calls for it.

## Documentation acceptance checklist

A release is not documentation-complete until:

- plugin description is accurate;
- local CBStats syntax documentation is accurate;
- main ContentBuilder NG docs mention CBStats appropriately;
- CB/API reference contains the public CBStats contract;
- JSON schema is documented after Pass 1;
- Pie syntax/behavior is documented after Pass 2;
- Bar syntax/behavior is documented after Pass 3;
- FR/EN/DE descriptions/help keys are synchronized where maintained;
- no duplicate documentation island was created unnecessarily.
