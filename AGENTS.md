# AGENTS.md

## Scope
- Joomla 6 only.
- PHP 8.3+ only.
- Database: MySQL / MariaDB only. Raw SQL fragments (outside the Joomla Query
  Builder) must use MySQL/MariaDB syntax and grammar — no PostgreSQL/SQL
  Server/SQLite constructs. In particular, `GROUP_CONCAT(... SEPARATOR ...)`
  requires the separator to be a string literal, not a function call
  (`SEPARATOR CHAR(31)` is invalid; use a quoted literal, e.g.
  `$db->quote(chr(31))`, instead).

## Core rules
- Use native Joomla 6 APIs and modern conventions only.
- No backward compatibility for older Joomla or PHP versions.
- No legacy/deprecated APIs.
- No fallbacks, polyfills, shims, runtime version checks, or compatibility workarounds.
- Prefer clean, strict, minimal, production-ready implementations.

## Efficiency
- Do only what is explicitly requested.
- Do not assume missing requirements.
- Only inspect and modify files strictly necessary for the task.
- Keep changes minimal and targeted.
- Stop after completing the requested task.

## Local Git branches
- Current development branch: `gil_6.1.16`. Version 6.1.15 is published.
- Before editing files, check the active local branch and working tree status.
- Work on a local branch named `gil_<development-version>`, for example
  `gil_6.1.15` for version 6.1.15 under development. Use the development version
  established with Gilles; do not invent or increment it.
- Create or switch to that branch before making changes, and keep using it
  for subsequent work on the same development version.
- Do not work on `main`. Creating a branch only at commit or push time does
  not satisfy this workflow.
- When commit and push are requested, commit on the working branch and push
  it to the remote branch of the same name, configuring upstream tracking.
- Preserve existing uncommitted changes when switching branches.

## Joomla
- For frontend action buttons, follow `docs/specifications/frontend-graphic-charter.md`.
  Edit/Print are the typography and spacing reference. New, Edit, Print, Save,
  Apply and Article Settings use neutral outline-secondary styling and grey hover;
  preserve destructive Delete and export colours.
- Build local RC and test ZIP files with `scripts/build-package.sh`, never by
  archiving source directories directly. Validate them with
  `scripts/validate-package.sh`; reject packages larger than 10 MB until
  development dependencies or artifacts have been removed.
- Keep local package ZIP files in `build/`. Store intermediate screenshots,
  previews and diagnostic scripts in ignored `qa-artifacts/`, not in `build/`.
- Prefer native Joomla 6 admin patterns before custom markup, CSS, or JavaScript.
- Keep custom CSS and JavaScript minimal.
- Respect MVC separation strictly.
- Route all user-facing strings through translation keys.
- New admin UI should follow native Joomla behavior when applicable.
- Preserve a non-AJAX fallback when practical.

## Validated Joomla menu baseline
- ContentBuilder NG `6.1.10-RC06` is the validated UX baseline for Joomla menu
  parameters.
- Preserve the native Joomla 6 look and feel. Use Joomla fields, layouts and
  interactions instead of imitating Joomla with component-specific styling.
- Preserve every already validated menu behavior unless a task explicitly asks
  to change it.
- Before rebuilding an RC after a menu change, check for regressions in menu
  type ordering, selected-view persistence, Reset inheritance, translations,
  section layout, field sizing and required-field validation.
- Never describe lint, XML parsing or archive inspection as complete functional
  validation. Clearly distinguish automated checks from manual Joomla UI tests.
- Treat regressions reported by manual Joomla testing as missing regression
  coverage and add a focused automated check when practical.

## CBList examples
- Use ContentBuilder NG view ID `15` as the default placeholder in CBList
  documentation, descriptions, examples, tests and AI-generated instructions,
  unless a specific scenario explicitly requires another ID.
- Quote values containing `|` or spaces, for example
  `fields="Nom|Prenom|Email"` and `sort="Nom|Prenom"`.

## Translations
- Update `en-GB`, `fr-FR`, and `de-DE` together for every translation change.
- Keep wording aligned across languages.
- French must use correct spelling, grammar, typography, and accents.
- In translation conflicts, apply explicit instructions from Gilles and this
  repository's `AGENTS.md` before the translation skill, Joomla conventions and
  upstream wording. Preserve keys and placeholders.

## Output
- Return final code directly when coding is requested.
- Keep explanations concise.
- No legacy alternatives.
- No pseudocode unless requested.
