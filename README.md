<!-- ABOUT THE PROJECT -->
## About the Project

This repository provides **community-maintained updates and fixes for ContentBuilder**, 
a Joomla extension originally developed by **Crosstec**. The original Crosstec project 
is no longer maintained or supported.

This ContentBuilerng (new gen) has been highly refactored for Joomla 6 native support. 
New Gen releases releases will be named: "com_contentBuilerng-6.1.xx.zip" and above.

This initiative aims to keep the extension usable on modern Joomla installations by:
- fixing compatibility issues
- removing deprecated APIs
- adapting the codebase to 6.x
- adding Preview mode
- improving user interface behaviors, ...

⚠️ **This is NOT an official Crosstec project.**  
All trademarks and original copyrights remain the property of their respective owners.

---

## Compatibility

- ✅ Joomla 5.4.x (not tested, shoudl be OK **without** the Backward Compatibility plugin)
- ✅ Joomla 6.x. (tested **with and witout** the Backward Compatibility plugin)
- ✅ PHP 8.3 

⚠️ **This is NOT an official ContentBuilder repository.**  
This project is maintained by volunteers and provided *as-is*.

---

## Project Status

🚧 This project is developed on a **best-effort basis**.  
Only **GitHub Releases** should be considered stable and suitable for production use.

---

## Migration Notes

The component installer performs the supported database, extension, plugin and menu
migrations automatically. Do not uninstall the legacy component before installing the
ContentBuilder NG package.

For the complete operational procedure, including backups, validation, DB Repair,
rollback and known pitfalls, see the
**[Administrator Migration Guide](MIGRATION_GUIDE.md)**.

Manual SQL is not required during a normal migration. It is reserved for diagnosed
table collisions or recovery after a failed migration.

Test execution, measured coverage, package validation and the Joomla installation
smoke test are documented in the **[Testing Guide](TESTING.md)**.

### AJAX Endpoint Migration

The old component-specific AJAX stack has been removed.

Removed:
- `task=ajax.display`

Replacement:
- use the component API endpoint with JSON responses
- endpoint format:
  - `index.php?option=com_contentbuilderng&task=api.display&format=json&action=...`

Current migrated actions:
- `action=rating`
- `action=get-unique-values`
- `action=stats`

URL migration example:

Old:

```text
index.php?option=com_contentbuilder&task=ajax.display&id=25&subject=rating&record_id=16
```

New:

```text
index.php?option=com_contentbuilderng&task=api.display&format=json&action=rating&id=25&record_id=16
```

Form statistics:

```text
index.php?option=com_contentbuilderng&task=api.display&format=json&action=stats&id=25
```

Requires the `Stats` permission for the selected view.

Response format:

```json
{
  "success": true,
  "messages": [],
  "data": {}
}
```

Sparse fieldsets are supported on `GET` requests:

```text
index.php?option=com_contentbuilderng&task=api.display&id=25&fields[items]=record_id,title,slug
index.php?option=com_contentbuilderng&task=api.display&id=25&action=stats&fields[records]=total,published
```

`fields[items]` accepts both item properties such as `record_id` and allowed business field names stored in `values`.
When sparse fieldsets are used, top-level resources not named in `fields[...]` are omitted. Request several resources with several parameters, for example `fields[records]=total&fields[ratings]=average`.

There is no backward compatibility for `task=ajax.display`.

---

## Download

For stable versions:
1. Go to the **Tags** or **Releases** section
2. Select the latest version
3. Download **Source code (zip)**
4. Install via the Joomla Extension Manager

---

## Disclaimer

This software is provided **"as is"**, without warranty of any kind, express or implied.

The maintainers provide this code on a **best-effort basis** and make no guarantees
regarding correctness, stability, security, or fitness for any particular purpose.

In no event shall the maintainers be held liable for any damages, data loss, or other
issues arising from the use of this software.

Use this code **at your own risk**.
