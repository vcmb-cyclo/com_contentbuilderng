# AGENTS.md — CBStats scoped instructions

## Scope

These instructions apply to:

`plugins/content/contentbuilderng_cbstats`

This plugin is the ContentBuilder NG statistics content plugin, commonly called **CBStats**.

## Inherited repository requirements

The repository root `AGENTS.md` is authoritative and applies in full. In particular, CBStats must follow these repository-wide constraints:

- Joomla 6 only;
- PHP 8.1+ only;
- MySQL / MariaDB only;
- native Joomla 6 APIs and modern conventions only;
- no legacy or deprecated APIs;
- no fallbacks, polyfills, shims, runtime version checks or compatibility workarounds;
- minimal, targeted, production-ready changes only;
- update `en-GB`, `fr-FR` and `de-DE` together for every translation change.

These scoped instructions add CBStats-specific rules; they do not weaken or replace the repository root rules.

## Required reading before changes

Before modifying CBStats:

1. Read the repository root `AGENTS.md`.
2. Read this file.
3. Read `docs/Gil_CBSTATS_SPECIFICATION.md`.
4. Read the mission document for the requested pass.
5. Inspect the current implementation, manifest, language files, assets, tests and existing documentation.

## Core design principle: CBStats must remain generic

### Absolute rule: no business hardcoding

Never hardcode:

- a ContentBuilder view ID;
- a field name;
- a real business value;
- a business label such as a route name, federation, category or organization-specific word;
- a business-specific display order;
- a business-specific count or percentage.

Values such as `id=IdVue` and `field=NomDuChamp` must come from the `{CBStats ...}` tag or from documented generic defaults.

Presentation defaults such as a maximum chart width may exist, but they must not encode business data and should be centralized/configurable where practical.

## Single calculation engine

Field statistics must have one normalized internal source of truth.

Target normalized PHP structure:

```php
[
    ['label' => '<actual field value>', 'value' => <calculated integer>],
]
```

This normalized structure is the source for:

- `output=table`;
- `output=json`;
- `output=pie`;
- `output=bar`;
- future visual outputs that use the same field statistics.

Do not duplicate filtering, grouping or counting logic inside visual outputs.

## Existing CBStats public API stability

This section concerns **no regression of the existing CBStats public syntax and behavior**. It does not require compatibility with older Joomla or PHP versions.


Existing public outputs must continue to work without regression:

- `total`
- `form_name`
- `table`
- `sum`
- `min`
- `max`

Existing filter behavior must be preserved unless a mission explicitly changes it:

- `filter[field]`
- `filter[value]`
- trimming behavior
- wildcard `*`
- alternatives with `|`
- current empty-value behavior for `output=table`
- STATS permission behavior
- `debug=1` behavior

## Public syntax

The public `{CBStats ...}` syntax is an API contract. Any new output or parameter requires:

- implementation;
- debug support where relevant;
- localization;
- regression tests or explicit test coverage;
- local plugin documentation;
- ContentBuilder NG documentation/API reference update where applicable.

## Sorting contract

The target generic sorting syntax is:

```text
sort=none|title|value
dir=asc|desc
```

Defaults:

```text
sort=none
dir=asc
```

Rules:

- `sort=none` preserves the engine's natural/current order.
- `sort=title` sorts by actual label.
- `sort=value` sorts by calculated numeric value.
- `dir` changes direction only.
- Do not introduce `sort=custom` without a separately approved, fully generic syntax and clear backward-compatible semantics.

## Generic chart wording

Do not hardcode the French business word `inscrits` or an equivalent domain noun into the generic core output.

Preferred generic chart text:

```text
<label> — <value> (<percentage> %)
Total : <sum>
```

User-facing labels must come from language strings. If a configurable noun is later introduced, it must be generic and documented.

## Front-end separation

JavaScript must consume normalized data produced by PHP.

JavaScript must not:

- query ContentBuilder directly;
- query BreezingForms directly;
- perform database access;
- contain duplicated counting/filtering logic;
- make an AJAX request merely to reconstruct data already available server-side.

## Assets

Before creating new asset paths, inspect the real plugin manifest and current media structure.

Do not assume that a historical suggested path is correct. Reuse the repository's actual naming and asset conventions.

Assets must be loaded once per page even when multiple CBStats charts exist on the same page.

All CBStats-specific CSS classes must begin with:

`cbstats-`

## HTML/JSON safety

- Escape labels when rendering HTML.
- Use proper JSON encoding for JSON output and JavaScript payloads.
- Do not construct JavaScript object literals by unsafe string concatenation.
- Multiple charts on one page must have unique identifiers.
- No-data output must not produce JavaScript errors.

## Documentation obligations

After each completed pass:

1. update the plugin's existing documentation;
2. update the public syntax/API reference;
3. update ContentBuilder NG's existing main documentation/API section when CBStats is referenced there;
4. update plugin description/manifest language strings if the public description is outdated;
5. avoid creating duplicate documentation if canonical files already exist.

See `docs/Gil_04_DOCUMENTATION_AND_API.md`.

## Scope protection

Prefer changes inside `plugins/content/contentbuilderng_cbstats`.

Any change elsewhere in ContentBuilder NG must be:

- technically necessary;
- minimal;
- explicitly justified in the final report.
