# AGENTS.md

## Project scope
This repository targets Joomla 6 only.
This repository targets PHP 8.1+ only.

## Mandatory rules
- Use only native Joomla 6 APIs and modern conventions.
- Do not add backward compatibility for Joomla 5 or Joomla 4.
- Do not use legacy or deprecated APIs.
- Do not add fallbacks, polyfills, compatibility shims, or runtime version checks.
- Do not use `class_exists()`, `method_exists()`, `version_compare()`, or similar compatibility patterns unless explicitly required for Joomla 6 itself.
- Do not add workarounds for older PHP or older Joomla environments.
- Prefer clean, strict, minimal, production-ready Joomla 6 implementations.

## Joomla 6 implementation rules
- Prefer native Joomla 6 admin patterns before writing custom markup, CSS, or JavaScript.
- For sortable admin lists, use Joomla native search tools and ordering patterns such as `HTMLHelper::_('searchtools.sort', ...)`, `Joomla.tableOrdering(...)`, and `list[fullordering]`.
- For admin filters, search bars, clear buttons, toolbar actions, dropdowns, and tooltips, follow Joomla 6 core behavior and visual conventions.
- Use Joomla 6 Bootstrap and Atum-compatible markup and utility classes for backend UI.
- If a button shows only an icon, keep an accessible label using tooltip text and/or visually hidden text.
- Do not recreate native Joomla behaviors with custom Font Awesome icons or ad hoc JavaScript if Joomla already provides the feature.
- Keep custom CSS and JS minimal and only when native Joomla behavior is insufficient.
- Respect Joomla MVC separation strictly: controllers handle requests, models/services handle business logic, templates/layouts handle rendering.
- Route all user-facing strings through translation keys with `Text::_()` or `Text::sprintf()`. Do not hardcode UI text in PHP or JavaScript unless a technical fallback is absolutely necessary.
- Any new list view should behave like a native Joomla admin list: search, clear, filters, sorting, ordering state, and consistent toolbar behavior.
- Any AJAX enhancement in admin must preserve a correct non-AJAX Joomla fallback whenever possible.
- When a feature depends on Joomla concepts such as internal tables, external tables, save, sync, create, publish, or preview, user messages must clearly explain the exact behavior.

## PHP rules
- Use PHP 8.1+ only.
- Do not add backward compatibility for PHP 8.0, PHP 7.x, or older versions.
- Prefer modern PHP syntax and features supported by PHP 8.1+ when they improve clarity and maintainability.
- Use explicit parameter, property, and return types whenever appropriate.
- Do not keep legacy PHP patterns only for old runtime compatibility.

## French translations
- All French translations must use correct French spelling, grammar, typography, and accents.
- Never omit accents in French text.
- Do not replace accented characters with unaccented equivalents.
- Use natural, idiomatic French suitable for a professional Joomla interface.
- Keep terminology consistent across translation files.

## Translations
- Any new translation key or updated translation string must be provided in all three admin/site languages used by this project: `en-GB`, `fr-FR`, and `de-DE`.
- Do not update only one or two languages when a string is added, removed, or changed.
- Keep the wording aligned across English, French, and German files.

## Output expectations
- Return final code directly when coding is requested.
- Keep explanations concise.
- Do not provide legacy alternatives.
- Do not provide pseudocode unless explicitly requested.

## Acceptance criteria
- Joomla 6 only.
- No backward compatibility.
- No legacy dependencies.
- No fallback logic.
- French translations must include proper accents and correct spelling.
