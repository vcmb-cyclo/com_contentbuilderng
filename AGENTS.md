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

## Joomla
- Prefer native Joomla 6 admin patterns before custom markup, CSS, or JavaScript.
- Keep custom CSS and JavaScript minimal.
- Respect MVC separation strictly.
- Route all user-facing strings through translation keys.
- New admin UI should follow native Joomla behavior when applicable.
- Preserve a non-AJAX fallback when practical.

## Translations
- Update `en-GB`, `fr-FR`, and `de-DE` together for every translation change.
- Keep wording aligned across languages.
- French must use correct spelling, grammar, typography, and accents.

## Output
- Return final code directly when coding is requested.
- Keep explanations concise.
- No legacy alternatives.
- No pseudocode unless requested.
