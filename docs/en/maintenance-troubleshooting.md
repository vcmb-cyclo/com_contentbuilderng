# Maintenance and troubleshooting

## Maintenance routine

- back up files and database before updates;
- install complete release packages;
- run the About-screen audit after updates;
- review `com_contentbuilderng.log`;
- test list, details, create, edit, publish, and delete workflows;
- test permissions with representative accounts;
- keep custom templates and overrides under version control.

## Logs

The component writes to `com_contentbuilderng.log` in the Joomla log path. Depending
on hosting configuration, this is usually the configured Joomla log directory or the
site `logs` directory.

The **About** screen can display component log information. Joomla, PHP-FPM, web
server, and database logs may also be required for installation or fatal errors.

Do not publish logs containing personal data, file paths, signatures, session values,
or SQL details.

## View Debug mode

Enable Debug on one view for a limited diagnosis. It can show the CBNG and
BreezingForms identifiers, calculated permissions, active filters, sorting,
pagination, and request logs.

Disable it after testing.

## Audit and REPAIR DB

The audit is diagnostic. **REPAIR DB** can change schema and data, including some
BreezingForms field synchronization.

Before repair:

- [ ] complete backup
- [ ] staging test when possible
- [ ] audit messages recorded
- [ ] no active imports or submissions
- [ ] rollback procedure available

After repair, rerun the audit and test the affected views.

## Migration problems

### Historical tables remain

Check whether both historical and NG tables contain data. The installer does not
merge two non-empty competing tables. Do not delete either side before identifying
relationships.

### Missing BreezingForms fields

REPAIR DB can automatically add some source fields missing from a linked CB view.
Review field publication, list inclusion, editing, and API permission afterwards.

### Incorrect menus

Verify `option=com_contentbuilderng`, the view ID, menu type, and cached SEF routes.
Rebuild Joomla menu routing or clear cache after correcting entries.

## Storage problems

### Missing internal table

Check the storage technical name, table prefix, database permissions, and Datatable
Sync result.

### Missing external table

External mode does not create the source table. Verify the exact table name and
database connection.

### Incomplete import

Check delimiter, encoding, headers, row column count, PHP upload limits, memory,
execution time, and database errors. Retry with a small sample.

## Permission problems

Check frontend versus backend context, direct and inherited groups, View and List
Access separately, own-record rules, limits, verification, publication, and menu
overrides.

See the [ACL checklist](permissions-acl.md).

## API problems

### HTTP 403

Check operation-specific permissions and whether the request carries the expected
Joomla identity/session.

### Empty sparse-fieldset result

Confirm the resource name and requested field names. Resources omitted from
`fields[...]` are intentionally removed.

### Field not allowed

Publish the field and enable **API allowed**. Use its technical name, label, or
reference only where supported by the endpoint.

## Upload problems

Check PHP `upload_max_filesize` and `post_max_size`, the field maximum, destination
directory, web-server write permissions, and directory protection.

Upload-directory behavior on Windows hosting is **To verify**.

## Email problems

Check Joomla mail settings, recipient syntax, field variables, sender restrictions,
HTML/text mode, upload attachment paths, and server mail logs. Test with a simple
template before adding attachments.

## Compatibility

The supported scope is Joomla 6 and PHP 8.1 or later. The SQL manifest targets MySQL.
MariaDB is expected in the migration guidance, but the exact production version
matrix is **To verify**.

## Diagnostic procedure

1. reproduce with one view and one test record;
2. record the exact URL, account, and time;
3. enable view Debug only if required;
4. inspect CBNG, Joomla, PHP, web server, and database logs;
5. compare with a known working account;
6. check source, publication, language, filters, and ACL;
7. reproduce on staging;
8. back up before REPAIR DB or manual SQL;
9. disable Debug and remove sensitive diagnostics.
