# SQL Migration Guide - Joomla 6 QueryBuilder Conversion

> **Developer documentation:** this file tracks the modernization of SQL queries in
> the source code. It is not the procedure for migrating an existing Joomla site.
> Administrators should use [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md).

## Overview

This document records the SQL hardening rules applied to ContentBuilder NG:

- SQL injection prevention (`quoteName()`, parameterization)
- Code maintainability and consistency
- Type safety

## Historical Inventory

The initial audit identified 106 raw SQL statements in four primary files:

  - ArticleService.php: 29 queries
  - com_breezingforms.php: 42 queries
  - com_contentbuilderng.php: 28 queries
  - FormSupportService.php: 7 queries

This count is retained only as historical context. It is not the current number of
unsafe or pending queries: the main files and additional production-facing paths have
since been hardened.

## Current Scope

The review covers `SELECT`, `UPDATE`, `INSERT`, `DELETE` and schema operations. Raw SQL
is not automatically considered unsafe. It remains acceptable for reviewed DDL,
MySQL-specific statements and dynamic schema operations when all values are quoted or
cast and all identifiers use `quoteName()`.

## Pattern Examples

### Pattern 1: Simple WHERE Condition
```php
// BEFORE (UNSAFE)
$db->setQuery('SELECT * FROM #__users WHERE id = ' . $userId);
$user = $db->loadObject();

// AFTER (SAFE)
$db = Factory::getContainer()->get(DatabaseInterface::class);
$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__users'))
    ->where($db->quoteName('id') . ' = ' . (int)$userId);
$db->setQuery($query);
$user = $db->loadObject();
```

### Pattern 2: Multiple WHERE Conditions with AND
```php
// BEFORE (UNSAFE)
$db->setQuery(
    'SELECT published, is_future FROM #__contentbuilderng_records'
    . ' WHERE `type` = ' . $db->quote($type)
    . ' AND reference_id = ' . $db->quote($refId)
    . ' AND record_id = ' . $db->quote($recordId)
);

// AFTER (SAFE)
$query = $db->getQuery(true)
    ->select([$db->quoteName('published'), $db->quoteName('is_future')])
    ->from($db->quoteName('#__contentbuilderng_records'))
    ->where($db->quoteName('type') . ' = ' . $db->quote($type))
    ->where($db->quoteName('reference_id') . ' = ' . $db->quote($refId))
    ->where($db->quoteName('record_id') . ' = ' . $db->quote($recordId));
$db->setQuery($query);
```

### Pattern 3: JOIN with Multiple Conditions
```php
// BEFORE (UNSAFE)
$db->setQuery(
    'SELECT articles.article_id, content.alias FROM #__contentbuilderng_articles AS articles'
    . ' JOIN #__content AS content ON content.id = articles.article_id'
    . ' WHERE content.state IN (0, 1)'
    . ' AND articles.form_id = ' . (int)$formId
    . ' AND articles.record_id = ' . $db->quote($recordId)
);

// AFTER (SAFE)
$query = $db->getQuery(true)
    ->select([$db->quoteName('articles.article_id'), $db->quoteName('content.alias')])
    ->from($db->quoteName('#__contentbuilderng_articles', 'articles'))
    ->innerJoin($db->quoteName('#__content', 'content') . ' ON ' 
        . $db->quoteName('content.id') . ' = ' . $db->quoteName('articles.article_id'))
    ->where($db->quoteName('content.state') . ' IN (0, 1)')
    ->where($db->quoteName('articles.form_id') . ' = ' . (int)$formId)
    ->where($db->quoteName('articles.record_id') . ' = ' . $db->quote($recordId));
$db->setQuery($query);
```

### Pattern 4: UPDATE with Multiple WHERE
```php
// BEFORE (UNSAFE - already migrated in UserModel.php)
$db->setQuery('UPDATE #__contentbuilderng_users SET verified_view = 1 WHERE form_id = ' 
    . $this->_form_id . ' AND userid IN (' . implode(',', $items) . ')');

// AFTER (SAFE)
$query = $db->getQuery(true)
    ->update($db->quoteName('#__contentbuilderng_users'))
    ->set($db->quoteName('verified_view') . ' = 1')
    ->where($db->quoteName('form_id') . ' = ' . (int)$this->_form_id)
    ->where($db->quoteName('userid') . ' IN (' . implode(',', array_map('intval', $items)) . ')');
$db->setQuery($query);
```

## Key Principles

1. **Always use `quoteName()`** for table and column names
   - Protects against reserved word conflicts
   - Properly escapes identifiers

2. **Use `quote()` for string values, cast for numeric**
   - Strings: `$db->quote($stringValue)`
   - Integers: `(int)$numericValue`
   - Arrays in IN clause: `array_map('intval', $array)`

3. **Use fluent chain methods**
   - `->select()`, `->from()`, `->where()`, `->orderBy()`, `->groupBy()`
   - Each method returns the query object for chaining

4. **Avoid embedding variables directly**
   - DON'T: `$db->quoteName('col') . ' = ' . $value` (only partly safe)
   - DO: Use `quote()` for strings, cast for numbers

5. **Test after migration**
   - Ensure identical query results
   - Run unit tests if available
   - Test with MySQL STRICT_TRANS_TABLES mode

## Production Security Status

The initial inventory counted raw SQL syntax, not confirmed vulnerabilities. A raw
query is acceptable when Joomla's query object cannot express the operation cleanly,
provided every value is quoted or cast and every dynamic identifier uses
`quoteName()`.

The production-facing hardening pass now covers:

- article, user and form support services;
- frontend record creation, verification and administrator notifications;
- download counters and article lookup in content plugins;
- frontend form, filter, public-form and export selectors;
- internal storage unique-value lookups and upload-field cleanup;
- storage and CSV table/column identifiers;
- regression tests for the unsafe interpolation patterns removed by this pass.

The remaining raw SQL is limited to reviewed categories:

- schema DDL such as `ALTER`, `DROP`, `RENAME`, `TRUNCATE` and `SHOW INDEX`;
- MySQL-specific statements such as `SQL_CALC_FOUND_ROWS`, `FOUND_ROWS()`,
  session variables and `GROUP_CONCAT`;
- dynamic storage-table queries whose identifiers come from stored schema metadata
  and are passed through `quoteName()`;
- legacy CRUD statements whose interpolated values are explicitly cast or quoted;
- static queries with no external value interpolation.

Do not mechanically convert these statements solely to reduce the raw-query count.
Any new query must still use QueryBuilder for normal CRUD, cast integer lists, quote
string values and quote dynamic identifiers.

## Automated Tooling

### SQL Migration Script (To Be Created)
```
python3 scripts/sql_migration_helper.py --analyze          # Identify all queries
python3 scripts/sql_migration_helper.py --file ArticleService.php  # Suggest migrations
python3 scripts/sql_migration_helper.py --apply --file ArticleService.php  # Auto-migrate
```

## Validation Checklist

After migrating each file:
- [ ] PHP syntax check: `php -l filename.php`
- [ ] No hardcoded SQL strings remain in critical paths
- [ ] All `$db->setQuery()` calls use `getQuery(true)` builder
- [ ] All table/column names wrapped in `quoteName()`
- [ ] All string values wrapped in `quote()`
- [ ] All numeric values cast appropriately
- [ ] Unit tests pass (if available)
- [ ] Functional testing of affected features

## Files Already Migrated
- ✅ **administrator/src/Model/UserModel.php** - 6 UPDATE queries (reference implementation)
- ✅ **administrator/src/Service/ArticleService.php** - fully migrated
- ✅ **administrator/src/Service/FormSupportService.php** - 6 queries migrated
- ✅ **administrator/src/types/com_contentbuilderng.php** - ~19 queries migrated (dynamic-column queries in getRecord/getListRecords/saveRecord kept as raw SQL — values already properly quoted, migration would require deep refactor)
- ✅ **administrator/src/types/com_breezingforms.php** - ~35 queries migrated (SET SESSION, GROUP_CONCAT dynamic selectors, and SELECT FOUND_ROWS() kept as raw SQL — MySQL-specific or dynamic-column constructs)

## Ongoing Review

- [x] Remove known direct value interpolation in public request paths.
- [x] Quote dynamic storage table and column identifiers.
- [x] Add a regression test for removed unsafe patterns.
- [ ] Re-review MySQL-specific queries whenever database portability becomes a project
      requirement.

## References
- [Joomla DatabaseDriver getQuery()](https://docs.joomla.org/Selecting_data_using_JDatabase)
- [QueryBuilder Pattern](https://docs.joomla.org/Inserting_Updating_Deleting_data_using_JDatabase)
- SQL Injection Prevention: Always use quoteName() and quote()
