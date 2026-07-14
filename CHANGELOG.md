# Changelog

## Unreleased

### Fixed

- CBStats tables now use compact intrinsic-width columns with aligned numeric values, while the shared Pie/Bar detail legend uses tighter readable row spacing.
- Front-end Edit form: a non-group editable field entirely absent from the submitted data (for example rendered read-only by a stale `{name:value}` marker left in the editable template) no longer has its stored value silently wiped on save. Only a field genuinely posted empty by the user still clears it.

### Added

- CBStats now provides one normalized field-statistics engine shared by HTML tables, raw JSON, responsive Pie charts and horizontal Bar charts, with generic filters (`*`, `|`), locale-aware sorting, signed external `add=` deltas, display-label mappings through `titles=`, multi-chart pages and synchronized EN/FR/DE help.
- CBStats now normalizes a negative final `add=` result to `0` in memory before sorting, percentage calculation and rendering, without changing source data or blocking independent statistics.
- The ContentBuilder NG API now exposes CBStats data through `action=cbstats` with `json`, `total`, `sum`, `min`, `max` and `form_name` outputs, while preserving STATS and field permissions and concise production errors.
- New **Audit** button on the admin form edit screen (`view=form&layout=edit`), disabled while the form has unsaved changes. Reports form/source/element/record counts and consistency checks: elements out of sync with the data source, an unavailable data source, theme plugin issues, fields missing from the Details or Edit template, editable fields lacking an `{name:item}` marker (and the reverse), and unknown template markers.
- Debug-mode warning when an editable field's Edit template only uses `{name:value}`/`{name:label}` instead of `{name:item}`, surfacing the root cause of the save issue above instead of leaving it silent.

### Changed

- Renamed the default theme plugin from `joomla6` to `thoth` (`contentbuilderng_themes/thoth`). Existing form references to `joomla3` or `joomla6` are migrated to `thoth` on update, and the old `joomla6` plugin is uninstalled.
- Added a per-form "Show title breadcrumb" option (`show_title_breadcrumb`, enabled by default): the page title on the front-end Details and Edit views renders as a breadcrumb linking back to the list.
- Removed the Field/Value column headers from the front-end Edit screen and the dead `cb_filter_calendar_format` parameter.
- Removed the old component-specific AJAX stack based on `task=ajax.display`.
- Moved former AJAX actions to the component API endpoint with JSON responses.
- Migrated `rating` and `get_unique_values` to:
  - `index.php?option=com_contentbuilderng&task=api.display&format=json&action=rating`
  - `index.php?option=com_contentbuilderng&task=api.display&format=json&action=get-unique-values`
- Added `action=stats` for form-level statistics:
  - `index.php?option=com_contentbuilderng&task=api.display&format=json&action=stats&id=25`

### Migration Note

Old URL example:

```text
index.php?option=com_contentbuilder&task=ajax.display&id=25&subject=rating&record_id=16
```

New URL example:

```text
index.php?option=com_contentbuilderng&task=api.display&format=json&action=rating&id=25&record_id=16
```

There is no backward compatibility for `task=ajax.display`.
