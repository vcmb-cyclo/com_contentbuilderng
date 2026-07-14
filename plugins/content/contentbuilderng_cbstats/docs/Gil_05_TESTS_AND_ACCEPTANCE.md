# CBStats tests and acceptance matrix

## Goal

Prevent regressions while evolving CBStats from independent outputs toward a common normalized field-statistics engine consumed by Table, JSON, Pie and Bar.

Codex must first inspect and reuse the repository's actual test framework. Do not invent a parallel test framework unless no usable test infrastructure exists.

## A. Existing outputs regression

Verify existing behavior for:

- `output=total`
- `output=form_name`
- `output=table`
- `output=sum`
- `output=min`
- `output=max`

Expected result: unchanged unless explicitly specified.

## B. Filters

Verify:

- one `filter[field]` + `filter[value]` pair;
- trim behavior;
- wildcard `*` at beginning;
- wildcard `*` at end;
- wildcard `*` on both sides if currently supported;
- alternatives with `|`;
- combinations that already work in the current plugin.

Do not redefine semantics through tests; capture existing behavior first.

## C. Normalized field statistics engine

Verify:

- actual field values are discovered dynamically;
- identical values group correctly;
- counts are correct;
- empty-value handling matches current table behavior;
- no view ID is hardcoded;
- no field name is hardcoded;
- no business value is hardcoded;
- no business order is hardcoded.

### External additions

Verify:

- an existing label receives its calculated count plus the external count;
- a missing label creates a normalized item;
- repeated labels in `add` are combined;
- labels and values are trimmed and UTF-8 labels are preserved;
- label matching is exact after trimming;
- signed, zero and leading-zero integer values are accepted;
- decimals, missing or malformed values reject the complete parameter;
- a negative final `add` result, including for a missing label, is normalized to `0` before titles, sorting, percentages and rendering;
- zero and positive final `add` results remain unchanged, including after a previously negative result recovers;
- repeated positive and negative deltas are cumulative;
- additions are applied before title mappings and `sort=title|value`;
- `titles` preserves UTF-8, trims both sides and leaves unmapped labels unchanged;
- identical display titles do not merge source categories;
- `sort=title` uses final display titles;
- `sort=none` appends new labels in the supplied order;
- Table, JSON, Pie and Bar receive the same enriched normalized data;
- filters, STATS permissions and debug behavior remain unchanged;
- scalar outputs remain unaffected and URL/API JSON reuses the common parsers.

## D. JSON

Verify:

- valid JSON;
- exact normalized schema with `label` and numeric `value`;
- no HTML wrapper;
- UTF-8 values;
- quotes and special characters encode safely;
- empty dataset behavior;
- sorting by title/value if implemented in Pass 1;
- permissions and debug behavior.

## E. Sorting

Test:

```text
sort=none
sort=title dir=asc
sort=title dir=desc
sort=value dir=asc
sort=value dir=desc
```

Verify:

- default is `sort=none`, `dir=asc`;
- numeric sorting is numeric, not lexicographic;
- title sorting follows the project's chosen locale/case semantics;
- sorting does not change counts.

## F. Pie

Verify:

- common engine reused;
- percentages sum correctly subject to display rounding;
- total equals sum of normalized values;
- sectors display percentages only when configured/readable;
- legend/tooltip show generic details;
- no hardcoded business noun;
- one Pie chart;
- multiple Pie charts;
- UTF-8 labels;
- empty data;
- no JS errors.

## G. Bar

Verify:

- common engine reused;
- horizontal orientation;
- values correct;
- sorting correct;
- one Bar chart;
- multiple Bar charts;
- Pie + Bar on same page;
- UTF-8 labels;
- empty data;
- no JS errors.

## H. Assets

With multiple charts on one page verify:

- chart library loaded once;
- data-label plugin loaded once if used;
- CBStats CSS loaded once;
- CBStats JS loaded once;
- unique chart IDs;
- no global initialization collision.

## I. Security / escaping

Test labels containing:

- accented characters;
- quotes;
- apostrophes;
- angle brackets;
- ampersands;
- non-ASCII UTF-8.

Expected result:

- safe HTML rendering;
- valid JSON;
- no broken JavaScript;
- no executable injected markup.

## J. Permissions

Verify existing STATS permission behavior for:

- authorized user;
- unauthorized user;
- debug behavior as currently defined.

## K. Documentation acceptance

After each pass verify:

- public syntax docs updated;
- CB/API reference updated;
- plugin description accurate;
- language files synchronized;
- examples match actual implementation.

## L. Codex final report format

For each pass report:

```text
Files changed:
- ...

Behavior implemented:
- ...

Tests/checks run:
- command/check → result

Existing CBStats public API stability:
- verified items

Documentation updated:
- ...

Remaining risks/follow-up:
- ...
```
