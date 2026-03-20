# AGENTS.md

## Project scope
This repository targets Joomla 6 only.

## Mandatory rules
- Use only native Joomla 6 APIs and modern conventions.
- Do not add backward compatibility for Joomla 5 or Joomla 4.
- Do not use legacy or deprecated APIs.
- Do not add fallbacks, polyfills, compatibility shims, or runtime version checks.
- Do not use `class_exists()`, `method_exists()`, `version_compare()`, or similar compatibility patterns unless explicitly required for Joomla 6 itself.
- Do not add workarounds for older PHP or older Joomla environments.
- Prefer clean, strict, minimal, production-ready Joomla 6 implementations.

## French translations
- All French translations must use correct French spelling, grammar, typography, and accents.
- Never omit accents in French text.
- Do not replace accented characters with unaccented equivalents.
- Use natural, idiomatic French suitable for a professional Joomla interface.
- Keep terminology consistent across translation files.

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