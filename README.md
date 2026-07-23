# ContentBuilder NG

Community-maintained **Joomla 6 rewrite** of ContentBuilder, originally developed by Crosstec. The original project is no longer maintained.

ContentBuilder NG has been fully refactored for native Joomla 6 support: deprecated APIs removed, MVC restructured, user interface modernised, and new features added (Preview mode, drag-and-drop reordering, API endpoint, …).

> ⚠️ **This is NOT an official Crosstec project.**  
> All trademarks and original copyrights remain the property of their respective owners.

---

## Compatibility

| | |
|---|---|
| Joomla | 6.x — tested with and without the Backward Compatibility plugin |
| PHP | 8.3 or later |

---

## Project Status

🚧 Developed on a **best-effort basis**.  
Only **GitHub Releases** should be considered stable and suitable for production use.

---

## Documentation

- **[Documentation utilisateur en français](docs/fr/index.md)**
- **[English user documentation](docs/en/index.md)**
- **[Administrator Migration Guide](MIGRATION_GUIDE.md)**
- **[Testing Guide](TESTING.md)**

---

## Migration Notes

The component installer performs all supported database, extension, plugin and menu migrations automatically. Do not uninstall the legacy component before installing the ContentBuilder NG package.

For the complete operational procedure — backups, validation, DB Repair, rollback and known pitfalls — see the **[Administrator Migration Guide](MIGRATION_GUIDE.md)**.

Manual SQL is not required during a normal migration. It is reserved for diagnosed table collisions or recovery after a failed migration.

---

## Download

1. Go to the **Releases** section
2. Select the latest version
3. Download the release asset named `com_contentbuilderng-<version>.zip`
4. Install via the Joomla Extension Manager

> The automatically generated GitHub **Source code (zip)** archive is a development snapshot, not an installable Joomla package.

---

## Disclaimer

This software is provided **"as is"**, without warranty of any kind.  
The maintainers make no guarantees regarding correctness, stability, security, or fitness for any particular purpose, and accept no liability for damages or data loss arising from its use.
