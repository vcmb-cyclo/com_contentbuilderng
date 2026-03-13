<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;

final class DatabaseAuditHelper
{
    private const TARGET_CHARSET = 'utf8mb4';
    private const TARGET_COLLATION = 'utf8mb4_0900_ai_ci';

    /**
     * @return array{
     *   generated_at:string,
     *   scanned_tables:int,
     *   tables:array<int,string>,
     *   duplicate_indexes:array<int,array{table:string,indexes:array<int,string>,keep:string,drop:array<int,string>}>,
     *   historical_tables:array<int,string>,
     *   historical_menu_entries:array<int,array{
     *     menu_id:int,
     *     title:string,
     *     normalized_title:string,
     *     alias:string,
     *     path:string,
     *     link:string,
     *     parent_id:int
     *   }>,
     *   table_encoding_issues:array<int,array{table:string,collation:string,expected:string}>,
     *   column_encoding_issues:array<int,array{table:string,column:string,charset:string,collation:string}>,
     *   mixed_table_collations:array<int,array{collation:string,count:int,tables:array<int,string>}>,
     *   missing_audit_columns_scanned:int,
     *   missing_audit_columns:array<int,array{
     *     table:string,
     *     storage_id:int,
     *     storage_name:string,
     *     bytable:int,
     *     missing:array<int,string>
     *   }>,
     *   plugin_extension_duplicates:array<int,array{
     *     canonical_folder:string,
     *     canonical_element:string,
     *     keep_id:int,
     *     duplicate_ids:array<int,int>,
     *     rows:array<int,array{
     *       extension_id:int,
     *       folder:string,
     *       element:string,
     *       enabled:int,
     *       is_canonical:int
     *     }>
     *   }>,
     *   bf_view_field_sync_issues:array<int,array{
     *     form_id:int,
     *     form_name:string,
     *     type:string,
     *     reference_id:int,
     *     source_name:string,
     *     source_exists:int,
     *     source_total:int,
     *     cb_total:int,
     *     missing_count:int,
     *     orphan_count:int,
     *     missing_in_cb:array<int,string>,
     *     orphan_in_cb:array<int,string>
     *   }>,
     *   cb_tables:array{
     *     summary:array{
     *       tables_total:int,
     *       tables_ng_total:int,
     *       tables_ng_expected:int,
     *       tables_ng_present:int,
     *       tables_ng_missing:int,
     *       tables_historical_total:int,
     *       tables_storage_total:int,
     *       rows_total:int,
     *       data_bytes_total:int,
     *       index_bytes_total:int,
     *       size_bytes_total:int
     *     },
     *     missing_ng_tables:array<int,string>,
     *     tables:array<int,array{
     *       table:string,
     *       rows:int,
     *       data_bytes:int,
     *       index_bytes:int,
     *       size_bytes:int,
     *       engine:string,
     *       collation:string
     *     }>
     *   },
     *   summary:array{
     *     duplicate_index_groups:int,
     *     duplicate_indexes_to_drop:int,
     *     historical_tables:int,
     *     historical_menu_entries:int,
     *     table_encoding_issues:int,
     *     column_encoding_issues:int,
     *     mixed_table_collations:int,
     *     missing_audit_column_tables:int,
     *     missing_audit_columns_total:int,
     *     plugin_duplicate_groups:int,
     *     plugin_duplicate_rows_to_remove:int,
     *     bf_view_field_sync_views:int,
     *     bf_view_field_sync_missing_in_cb:int,
     *     bf_view_field_sync_orphan_in_cb:int,
     *     issues_total:int
     *   },
     *   errors:array<int,string>
     * }
     */
    public static function run(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $prefix = $db->getPrefix();
        $errors = [];

        $tables = self::collectAuditableTables($db, $errors);

        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        [$duplicateIndexes, $duplicateErrors] = self::findDuplicateIndexes($db, $tables, $prefix);
        $errors = array_merge($errors, $duplicateErrors);

        $historicalTables = self::findLegacyContentbuilderTables($tables, $prefix);
        [$historicalMenuEntries, $historicalMenuErrors] = self::findLegacyMenuEntries($db);
        $errors = array_merge($errors, $historicalMenuErrors);
        [$cbTableStats, $cbTableStatsErrors] = self::collectCbTableStats($db, $tables, $prefix, $historicalTables);
        $errors = array_merge($errors, $cbTableStatsErrors);

        [$tableEncodingIssues, $columnEncodingIssues, $mixedTableCollations, $encodingErrors] =
            self::inspectEncodingAndCollation($db, $tables, $prefix);
        $errors = array_merge($errors, $encodingErrors);

        $auditColumnsSummary = StorageAuditColumnsHelper::audit($db);
        $missingAuditColumns = (array) ($auditColumnsSummary['issues'] ?? []);
        $errors = array_merge($errors, (array) ($auditColumnsSummary['warnings'] ?? []));
        $pluginDuplicatesSummary = PluginExtensionDedupHelper::audit($db);
        $pluginExtensionDuplicates = (array) ($pluginDuplicatesSummary['groups'] ?? []);
        $errors = array_merge($errors, (array) ($pluginDuplicatesSummary['warnings'] ?? []));
        $missingAuditColumnsTotal = 0;
        $pluginDuplicateRowsToRemove = (int) ($pluginDuplicatesSummary['rows_to_remove'] ?? 0);
        $bfFieldSyncIssues = [];
        $bfMissingInCbTotal = 0;
        $bfOrphanInCbTotal = 0;

        foreach ($missingAuditColumns as $missingAuditColumn) {
            if (!is_array($missingAuditColumn)) {
                continue;
            }

            $missingAuditColumnsTotal += count((array) ($missingAuditColumn['missing'] ?? []));
        }

        [$bfFieldSyncIssues, $bfFieldSyncErrors] = self::inspectBfViewFieldSync($db);
        $errors = array_merge($errors, $bfFieldSyncErrors);

        foreach ($bfFieldSyncIssues as $bfFieldSyncIssue) {
            if (!is_array($bfFieldSyncIssue)) {
                continue;
            }

            $bfMissingInCbTotal += (int) ($bfFieldSyncIssue['missing_count'] ?? 0);
            $bfOrphanInCbTotal += (int) ($bfFieldSyncIssue['orphan_count'] ?? 0);
        }

        $duplicateToDrop = 0;
        foreach ($duplicateIndexes as $duplicateIndex) {
            $duplicateToDrop += count((array) ($duplicateIndex['drop'] ?? []));
        }

        $issuesTotal = count($duplicateIndexes)
            + count($historicalTables)
            + count($historicalMenuEntries)
            + count($tableEncodingIssues)
            + count($columnEncodingIssues)
            + count($missingAuditColumns)
            + count($pluginExtensionDuplicates)
            + count($bfFieldSyncIssues);

        if (count($mixedTableCollations) > 1) {
            $issuesTotal++;
        }

        return [
            'generated_at' => Factory::getDate()->toSql(),
            'scanned_tables' => count($tables),
            'tables' => array_map(
                static fn(string $tableName): string => self::toAlias($tableName, $prefix),
                $tables
            ),
            'duplicate_indexes' => $duplicateIndexes,
            'historical_tables' => $historicalTables,
            'historical_menu_entries' => $historicalMenuEntries,
            'table_encoding_issues' => $tableEncodingIssues,
            'column_encoding_issues' => $columnEncodingIssues,
            'mixed_table_collations' => $mixedTableCollations,
            'missing_audit_columns_scanned' => (int) ($auditColumnsSummary['scanned'] ?? 0),
            'missing_audit_columns' => $missingAuditColumns,
            'plugin_extension_duplicates' => $pluginExtensionDuplicates,
            'bf_view_field_sync_issues' => $bfFieldSyncIssues,
            'cb_tables' => $cbTableStats,
            'summary' => [
                'duplicate_index_groups' => count($duplicateIndexes),
                'duplicate_indexes_to_drop' => $duplicateToDrop,
                'historical_tables' => count($historicalTables),
                'historical_menu_entries' => count($historicalMenuEntries),
                'table_encoding_issues' => count($tableEncodingIssues),
                'column_encoding_issues' => count($columnEncodingIssues),
                'mixed_table_collations' => count($mixedTableCollations),
                'missing_audit_column_tables' => count($missingAuditColumns),
                'missing_audit_columns_total' => $missingAuditColumnsTotal,
                'plugin_duplicate_groups' => count($pluginExtensionDuplicates),
                'plugin_duplicate_rows_to_remove' => $pluginDuplicateRowsToRemove,
                'bf_view_field_sync_views' => count($bfFieldSyncIssues),
                'bf_view_field_sync_missing_in_cb' => $bfMissingInCbTotal,
                'bf_view_field_sync_orphan_in_cb' => $bfOrphanInCbTotal,
                'issues_total' => $issuesTotal,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int,string> $errors
     * @return array<int,string>
     */
    private static function collectAuditableTables(DatabaseInterface $db, array &$errors): array
    {
        $prefix = $db->getPrefix();
        $tables = [];

        try {
            $tableList = $db->getTableList();
        } catch (\Throwable $e) {
            $errors[] = 'Could not list database tables: ' . $e->getMessage();
            return [];
        }

        foreach ((array) $tableList as $tableName) {
            $tableName = (string) $tableName;

            if ($tableName === '' || strpos($tableName, $prefix) !== 0) {
                continue;
            }

            $withoutPrefix = substr($tableName, strlen($prefix));
            if ($withoutPrefix === false || $withoutPrefix === '') {
                continue;
            }

            if (stripos($withoutPrefix, 'contentbuilder') !== false) {
                $tables[$tableName] = true;
            }
        }

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['name', 'bytable']))
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where($db->quoteName('name') . " <> ''");

            $db->setQuery($query);
            $storages = $db->loadAssocList() ?: [];

            foreach ($storages as $storage) {
                $storageName = strtolower(trim((string) ($storage['name'] ?? '')));
                $bytable = (int) ($storage['bytable'] ?? 0);

                if ($storageName === '' || $bytable === 1) {
                    continue;
                }

                if (!preg_match('/^[a-z0-9_]+$/', $storageName)) {
                    continue;
                }

                $physicalTable = $prefix . $storageName;
                if (in_array($physicalTable, $tableList, true)) {
                    $tables[$physicalTable] = true;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect #__contentbuilderng_storages: ' . $e->getMessage();
        }

        return array_keys($tables);
    }

    /**
     * @param array<int,string> $tables
     * @return array{0:array<int,array{table:string,indexes:array<int,string>,keep:string,drop:array<int,string>}>,1:array<int,string>}
     */
    private static function findDuplicateIndexes(DatabaseInterface $db, array $tables, string $prefix): array
    {
        $duplicates = [];
        $errors = [];

        foreach ($tables as $tableName) {
            try {
                $indexes = self::getTableIndexes($db, $tableName);
            } catch (\Throwable $e) {
                $errors[] = 'Could not inspect indexes on ' . self::toAlias($tableName, $prefix) . ': ' . $e->getMessage();
                continue;
            }

            $signatureMap = [];
            foreach ($indexes as $indexName => $definition) {
                if (strtoupper($indexName) === 'PRIMARY') {
                    continue;
                }

                $signature = (string) ($definition['signature'] ?? '');
                if ($signature === '') {
                    continue;
                }

                $signatureMap[$signature][] = $indexName;
            }

            foreach ($signatureMap as $indexNames) {
                if (count($indexNames) < 2) {
                    continue;
                }

                sort($indexNames, SORT_NATURAL | SORT_FLAG_CASE);
                $keep = (string) array_shift($indexNames);

                $duplicates[] = [
                    'table' => self::toAlias($tableName, $prefix),
                    'indexes' => array_merge([$keep], $indexNames),
                    'keep' => $keep,
                    'drop' => $indexNames,
                ];
            }
        }

        usort(
            $duplicates,
            static fn(array $a, array $b): int => strcmp((string) ($a['table'] ?? ''), (string) ($b['table'] ?? ''))
        );

        return [$duplicates, $errors];
    }

    /**
     * @param array<int,string> $tables
     * @return array<int,string>
     */
    private static function findLegacyContentbuilderTables(array $tables, string $prefix): array
    {
        $historical = [];

        foreach ($tables as $tableName) {
            if (strpos($tableName, $prefix) !== 0) {
                continue;
            }

            $withoutPrefix = strtolower(substr($tableName, strlen($prefix)));
            if ($withoutPrefix === false || $withoutPrefix === '') {
                continue;
            }

            if (strpos($withoutPrefix, 'contentbuilder_') === 0 && strpos($withoutPrefix, 'contentbuilderng_') !== 0) {
                $historical[] = self::toAlias($tableName, $prefix);
            }
        }

        sort($historical, SORT_NATURAL | SORT_FLAG_CASE);

        return $historical;
    }

    /**
     * @return array{0:array<int,array{
     *   menu_id:int,
     *   title:string,
     *   normalized_title:string,
     *   alias:string,
     *   path:string,
     *   link:string,
     *   parent_id:int
     * }>,1:array<int,string>}
     */
    private static function findLegacyMenuEntries(DatabaseInterface $db): array
    {
        $entries = [];
        $errors = [];

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias', 'path', 'link', 'parent_id']))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('title') . ' LIKE ' . $db->quote('COM_CONTENTBUILDER%'))
                ->where(
                    '('
                    . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder%')
                    . ' OR '
                    . $db->quoteName('alias') . ' LIKE ' . $db->quote('contentbuilder%')
                    . ' OR '
                    . $db->quoteName('alias') . ' LIKE ' . $db->quote('com-contentbuilder%')
                    . ' OR '
                    . $db->quoteName('path') . ' LIKE ' . $db->quote('contentbuilder%')
                    . ')'
                )
                ->order($db->quoteName('id') . ' ASC');

            $db->setQuery($query);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect historical menu entries: ' . $e->getMessage();
            return [[], $errors];
        }

        foreach ($rows as $row) {
            $title = strtoupper(trim((string) ($row['title'] ?? '')));

            if ($title === '' || str_starts_with($title, 'COM_CONTENTBUILDERNG')) {
                continue;
            }

            $normalizedTitle = self::normalizeLegacyMenuTitle($title);

            if ($normalizedTitle === $title) {
                continue;
            }

            $entries[] = [
                'menu_id' => (int) ($row['id'] ?? 0),
                'title' => $title,
                'normalized_title' => $normalizedTitle,
                'alias' => trim((string) ($row['alias'] ?? '')),
                'path' => trim((string) ($row['path'] ?? '')),
                'link' => trim((string) ($row['link'] ?? '')),
                'parent_id' => (int) ($row['parent_id'] ?? 0),
            ];
        }

        return [$entries, $errors];
    }

    private static function normalizeLegacyMenuTitle(string $title): string
    {
        $title = strtoupper(trim($title));

        if ($title === 'COM_CONTENTBUILDER' || $title === 'COM_CONTENTBUILDER_NG') {
            return 'COM_CONTENTBUILDERNG';
        }

        if (str_starts_with($title, 'COM_CONTENTBUILDER_NG_')) {
            return 'COM_CONTENTBUILDERNG_' . substr($title, strlen('COM_CONTENTBUILDER_NG_'));
        }

        if (str_starts_with($title, 'COM_CONTENTBUILDER_')) {
            return 'COM_CONTENTBUILDERNG_' . substr($title, strlen('COM_CONTENTBUILDER_'));
        }

        return $title;
    }

    /**
     * @param array<int,string> $tables
     * @param array<int,string> $legacyTables
     * @return array{
     *   0:array{
     *     summary:array{
     *       tables_total:int,
     *       tables_ng_total:int,
     *       tables_ng_expected:int,
     *       tables_ng_present:int,
     *       tables_ng_missing:int,
     *       tables_legacy_total:int,
     *       tables_storage_total:int,
     *       rows_total:int,
     *       data_bytes_total:int,
     *       index_bytes_total:int,
     *       size_bytes_total:int
     *     },
     *     missing_ng_tables:array<int,string>,
     *     tables:array<int,array{
     *       table:string,
     *       rows:int,
     *       data_bytes:int,
     *       index_bytes:int,
     *       size_bytes:int,
     *       engine:string,
     *       collation:string
     *     }>
     *   },
     *   1:array<int,string>
     * }
     */
    private static function collectCbTableStats(
        DatabaseInterface $db,
        array $tables,
        string $prefix,
        array $legacyTables
    ): array {
        $errors = [];
        $aliases = array_map(
            static fn(string $tableName): string => self::toAlias($tableName, $prefix),
            $tables
        );
        $aliasLookup = array_fill_keys($aliases, true);
        $expectedNgTables = self::getExpectedNgCoreTableAliases();
        $legacyLookup = array_fill_keys($legacyTables, true);

        $ngTables = [];
        $storageTables = [];

        foreach ($aliases as $alias) {
            $lowerAlias = strtolower($alias);

            if (strpos($lowerAlias, '#__contentbuilderng_') === 0) {
                $ngTables[] = $alias;
                continue;
            }

            if (!isset($legacyLookup[$alias])) {
                $storageTables[] = $alias;
            }
        }

        sort($ngTables, SORT_NATURAL | SORT_FLAG_CASE);
        sort($storageTables, SORT_NATURAL | SORT_FLAG_CASE);

        $missingNgTables = array_values(
            array_filter(
                $expectedNgTables,
                static fn(string $tableAlias): bool => !isset($aliasLookup[$tableAlias])
            )
        );
        sort($missingNgTables, SORT_NATURAL | SORT_FLAG_CASE);

        $tableStats = [];
        $tableMeta = [];
        $rowsTotal = 0;
        $dataBytesTotal = 0;
        $indexBytesTotal = 0;
        $sizeBytesTotal = 0;

        if ($tables !== []) {
            $quotedTables = array_map(
                static fn(string $tableName): string => $db->quote($tableName),
                $tables
            );
            $inClause = implode(', ', $quotedTables);

            try {
                $db->setQuery(
                    'SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, ENGINE, TABLE_COLLATION'
                    . ' FROM information_schema.TABLES'
                    . ' WHERE TABLE_SCHEMA = DATABASE()'
                    . ' AND TABLE_NAME IN (' . $inClause . ')'
                );
                $rows = $db->loadAssocList() ?: [];

                foreach ($rows as $row) {
                    $tableName = (string) ($row['TABLE_NAME'] ?? $row['table_name'] ?? '');

                    if ($tableName === '') {
                        continue;
                    }

                    $tableMeta[$tableName] = $row;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Could not inspect ContentBuilder table statistics: ' . $e->getMessage();
            }
        }

        foreach ($tables as $tableName) {
            $meta = (array) ($tableMeta[$tableName] ?? []);
            $rows = (int) ($meta['TABLE_ROWS'] ?? $meta['table_rows'] ?? 0);
            $dataBytes = (int) ($meta['DATA_LENGTH'] ?? $meta['data_length'] ?? 0);
            $indexBytes = (int) ($meta['INDEX_LENGTH'] ?? $meta['index_length'] ?? 0);
            $sizeBytes = $dataBytes + $indexBytes;

            $rowsTotal += max(0, $rows);
            $dataBytesTotal += max(0, $dataBytes);
            $indexBytesTotal += max(0, $indexBytes);
            $sizeBytesTotal += max(0, $sizeBytes);

            $tableStats[] = [
                'table' => self::toAlias($tableName, $prefix),
                'rows' => max(0, $rows),
                'data_bytes' => max(0, $dataBytes),
                'index_bytes' => max(0, $indexBytes),
                'size_bytes' => max(0, $sizeBytes),
                'engine' => (string) ($meta['ENGINE'] ?? $meta['engine'] ?? ''),
                'collation' => (string) ($meta['TABLE_COLLATION'] ?? $meta['table_collation'] ?? ''),
            ];
        }

        usort(
            $tableStats,
            static fn(array $a, array $b): int => strcmp((string) ($a['table'] ?? ''), (string) ($b['table'] ?? ''))
        );

        return [[
            'summary' => [
                'tables_total' => count($aliases),
                'tables_ng_total' => count($ngTables),
                'tables_ng_expected' => count($expectedNgTables),
                'tables_ng_present' => count($expectedNgTables) - count($missingNgTables),
                'tables_ng_missing' => count($missingNgTables),
                'tables_legacy_total' => count($legacyTables),
                'tables_storage_total' => count($storageTables),
                'rows_total' => $rowsTotal,
                'data_bytes_total' => $dataBytesTotal,
                'index_bytes_total' => $indexBytesTotal,
                'size_bytes_total' => $sizeBytesTotal,
            ],
            'missing_ng_tables' => $missingNgTables,
            'tables' => $tableStats,
        ], $errors];
    }

    /**
     * @return array<int,string>
     */
    private static function getExpectedNgCoreTableAliases(): array
    {
        return [
            '#__contentbuilderng_articles',
            '#__contentbuilderng_elements',
            '#__contentbuilderng_forms',
            '#__contentbuilderng_list_records',
            '#__contentbuilderng_list_states',
            '#__contentbuilderng_rating_cache',
            '#__contentbuilderng_records',
            '#__contentbuilderng_registered_users',
            '#__contentbuilderng_resource_access',
            '#__contentbuilderng_storages',
            '#__contentbuilderng_storage_fields',
            '#__contentbuilderng_users',
            '#__contentbuilderng_verifications',
        ];
    }

    /**
     * @param array<int,string> $tables
     * @return array{
     *   0:array<int,array{table:string,collation:string,expected:string}>,
     *   1:array<int,array{table:string,column:string,charset:string,collation:string}>,
     *   2:array<int,array{collation:string,count:int,tables:array<int,string>}>,
     *   3:array<int,string>
     * }
     */
    private static function inspectEncodingAndCollation(DatabaseInterface $db, array $tables, string $prefix): array
    {
        $tableIssues = [];
        $columnIssues = [];
        $mixedCollations = [];
        $errors = [];
        $tablesByCollation = [];

        if ($tables === []) {
            return [$tableIssues, $columnIssues, $mixedCollations, $errors];
        }

        $quotedTables = array_map(
            static fn(string $tableName): string => $db->quote($tableName),
            $tables
        );
        $inClause = implode(', ', $quotedTables);

        try {
            $db->setQuery(
                'SELECT TABLE_NAME, TABLE_COLLATION'
                . ' FROM information_schema.TABLES'
                . ' WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND TABLE_NAME IN (' . $inClause . ')'
            );
            $tableRows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect table collations: ' . $e->getMessage();
            $tableRows = [];
        }

        $collationStats = [];

        foreach ($tableRows as $row) {
            $tableName = (string) ($row['TABLE_NAME'] ?? $row['table_name'] ?? '');
            $collation = (string) ($row['TABLE_COLLATION'] ?? $row['table_collation'] ?? '');

            if ($tableName === '') {
                continue;
            }

            if ($collation !== '') {
                $collationStats[$collation] = ($collationStats[$collation] ?? 0) + 1;
                $tablesByCollation[$collation][] = self::toAlias($tableName, $prefix);
            }

            if ($collation === '' || strcasecmp($collation, self::TARGET_COLLATION) !== 0) {
                $tableIssues[] = [
                    'table' => self::toAlias($tableName, $prefix),
                    'collation' => $collation,
                    'expected' => self::TARGET_COLLATION,
                ];
            }
        }

        arsort($collationStats);
        foreach ($collationStats as $collation => $count) {
            $collationTables = array_values(array_unique((array) ($tablesByCollation[$collation] ?? [])));
            sort($collationTables, SORT_NATURAL | SORT_FLAG_CASE);
            $mixedCollations[] = [
                'collation' => (string) $collation,
                'count' => (int) $count,
                'tables' => $collationTables,
            ];
        }

        try {
            $db->setQuery(
                'SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME'
                . ' FROM information_schema.COLUMNS'
                . ' WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND TABLE_NAME IN (' . $inClause . ')'
                . ' AND COLLATION_NAME IS NOT NULL'
            );
            $columnRows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect column collations: ' . $e->getMessage();
            $columnRows = [];
        }

        foreach ($columnRows as $row) {
            $tableName = (string) ($row['TABLE_NAME'] ?? $row['table_name'] ?? '');
            $columnName = (string) ($row['COLUMN_NAME'] ?? $row['column_name'] ?? '');
            $charset = strtolower((string) ($row['CHARACTER_SET_NAME'] ?? $row['character_set_name'] ?? ''));
            $collation = (string) ($row['COLLATION_NAME'] ?? $row['collation_name'] ?? '');

            if ($tableName === '' || $columnName === '') {
                continue;
            }

            if ($charset !== self::TARGET_CHARSET || strcasecmp($collation, self::TARGET_COLLATION) !== 0) {
                $columnIssues[] = [
                    'table' => self::toAlias($tableName, $prefix),
                    'column' => $columnName,
                    'charset' => $charset,
                    'collation' => $collation,
                ];
            }
        }

        usort(
            $tableIssues,
            static fn(array $a, array $b): int => strcmp((string) ($a['table'] ?? ''), (string) ($b['table'] ?? ''))
        );
        usort(
            $columnIssues,
            static fn(array $a, array $b): int => strcmp(
                ((string) ($a['table'] ?? '')) . ':' . ((string) ($a['column'] ?? '')),
                ((string) ($b['table'] ?? '')) . ':' . ((string) ($b['column'] ?? ''))
            )
        );

        return [$tableIssues, $columnIssues, $mixedCollations, $errors];
    }

    /**
     * @return array{
     *   0:array<int,array{
     *     form_id:int,
     *     form_name:string,
     *     type:string,
     *     reference_id:int,
     *     source_name:string,
     *     source_exists:int,
     *     source_total:int,
     *     cb_total:int,
     *     missing_count:int,
     *     orphan_count:int,
     *     missing_in_cb:array<int,string>,
     *     orphan_in_cb:array<int,string>
     *   }>,
     *   1:array<int,string>
     * }
     */
    private static function inspectBfViewFieldSync(DatabaseInterface $db): array
    {
        $issues = [];
        $errors = [];

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'type', 'reference_id']))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where(
                    $db->quoteName('type') . ' IN ('
                    . $db->quote('com_breezingforms') . ','
                    . $db->quote('com_breezingforms_ng') . ')'
                )
                ->where($db->quoteName('reference_id') . ' > 0');

            $db->setQuery($query);
            $forms = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect CB views linked to BF sources: ' . $e->getMessage();
            return [[], $errors];
        }

        foreach ($forms as $formRow) {
            $formId = (int) ($formRow['id'] ?? 0);
            $formName = trim((string) ($formRow['name'] ?? ''));
            $type = trim((string) ($formRow['type'] ?? ''));
            $referenceId = (int) ($formRow['reference_id'] ?? 0);

            if ($formId < 1 || $referenceId < 1 || $type === '') {
                continue;
            }

            $cbByReference = [];

            try {
                $query = $db->getQuery(true)
                    ->select($db->quoteName(['reference_id', 'label']))
                    ->from($db->quoteName('#__contentbuilderng_elements'))
                    ->where($db->quoteName('form_id') . ' = ' . $formId);

                $db->setQuery($query);
                $cbElements = $db->loadAssocList() ?: [];

                foreach ($cbElements as $cbElement) {
                    $refId = trim((string) ($cbElement['reference_id'] ?? ''));
                    if ($refId === '') {
                        continue;
                    }

                    $label = trim((string) ($cbElement['label'] ?? ''));
                    $cbByReference[$refId] = $label !== '' ? $label : $refId;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Could not inspect CB elements for view #' . $formId . ': ' . $e->getMessage();
                continue;
            }

            try {
                $sourceForm = FormSourceFactory::getForm($type, (string) $referenceId);
            } catch (\Throwable $e) {
                $errors[] = 'Could not load source form for view #' . $formId . ': ' . $e->getMessage();
                continue;
            }

            if (!is_object($sourceForm) || empty($sourceForm->exists)) {
                $issues[] = [
                    'form_id' => $formId,
                    'form_name' => $formName,
                    'type' => $type,
                    'reference_id' => $referenceId,
                    'source_name' => '',
                    'source_exists' => 0,
                    'source_total' => 0,
                    'cb_total' => count($cbByReference),
                    'missing_count' => 0,
                    'orphan_count' => 0,
                    'missing_in_cb' => [],
                    'orphan_in_cb' => [],
                ];
                continue;
            }

            $sourceName = '';
            if (isset($sourceForm->properties) && isset($sourceForm->properties->name)) {
                $sourceName = trim((string) $sourceForm->properties->name);
            }

            $sourceElements = (array) $sourceForm->getElementLabels();
            $sourceByReference = [];

            foreach ($sourceElements as $reference => $label) {
                $refId = trim((string) $reference);
                if ($refId === '') {
                    continue;
                }

                $sourceLabel = trim((string) $label);
                $sourceByReference[$refId] = $sourceLabel !== '' ? $sourceLabel : $refId;
            }

            $missingRefs = array_diff_key($sourceByReference, $cbByReference);
            $orphanRefs = array_diff_key($cbByReference, $sourceByReference);

            if ($missingRefs === [] && $orphanRefs === []) {
                continue;
            }

            $missingLabels = array_values(array_unique(array_values($missingRefs)));
            $orphanLabels = array_values(array_unique(array_values($orphanRefs)));
            sort($missingLabels, SORT_NATURAL | SORT_FLAG_CASE);
            sort($orphanLabels, SORT_NATURAL | SORT_FLAG_CASE);

            $issues[] = [
                'form_id' => $formId,
                'form_name' => $formName,
                'type' => $type,
                'reference_id' => $referenceId,
                'source_name' => $sourceName,
                'source_exists' => 1,
                'source_total' => count($sourceByReference),
                'cb_total' => count($cbByReference),
                'missing_count' => count($missingLabels),
                'orphan_count' => count($orphanLabels),
                'missing_in_cb' => $missingLabels,
                'orphan_in_cb' => $orphanLabels,
            ];
        }

        usort(
            $issues,
            static fn(array $a, array $b): int => (int) ($a['form_id'] ?? 0) <=> (int) ($b['form_id'] ?? 0)
        );

        return [$issues, $errors];
    }

    /**
     * @return array<string,array{non_unique:int,index_type:string,columns:array<int,array{name:string,sub_part:string,collation:string}>,signature:string}>
     */
    private static function getTableIndexes(DatabaseInterface $db, string $tableName): array
    {
        $tableQN = $db->quoteName($tableName);
        $db->setQuery('SHOW INDEX FROM ' . $tableQN);
        $rows = $db->loadAssocList() ?: [];
        $indexMap = [];

        foreach ($rows as $row) {
            $keyName = (string) ($row['Key_name'] ?? $row['key_name'] ?? '');
            if ($keyName === '') {
                continue;
            }

            $seqInIndex = (int) ($row['Seq_in_index'] ?? $row['seq_in_index'] ?? 0);
            if ($seqInIndex < 1) {
                $seqInIndex = count($indexMap[$keyName]['columns'] ?? []) + 1;
            }

            $columnName = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));
            if ($columnName === '') {
                $columnName = strtolower(trim((string) ($row['Expression'] ?? $row['expression'] ?? '')));
            }

            if ($columnName === '') {
                continue;
            }

            if (!isset($indexMap[$keyName])) {
                $indexMap[$keyName] = [
                    'non_unique' => (int) ($row['Non_unique'] ?? $row['non_unique'] ?? 1),
                    'index_type' => strtoupper((string) ($row['Index_type'] ?? $row['index_type'] ?? 'BTREE')),
                    'columns' => [],
                    'signature' => '',
                ];
            }

            $indexMap[$keyName]['columns'][$seqInIndex] = [
                'name' => $columnName,
                'sub_part' => (string) ($row['Sub_part'] ?? $row['sub_part'] ?? ''),
                'collation' => strtoupper((string) ($row['Collation'] ?? $row['collation'] ?? 'A')),
            ];
        }

        foreach ($indexMap as &$indexDefinition) {
            ksort($indexDefinition['columns'], SORT_NUMERIC);
            $indexDefinition['columns'] = array_values($indexDefinition['columns']);
            $indexDefinition['signature'] = self::indexDefinitionSignature($indexDefinition);
        }
        unset($indexDefinition);

        return $indexMap;
    }

    private static function indexDefinitionSignature(array $indexDefinition): string
    {
        $columnParts = [];

        foreach ((array) ($indexDefinition['columns'] ?? []) as $columnDefinition) {
            $columnParts[] = implode(':', [
                (string) ($columnDefinition['name'] ?? ''),
                (string) ($columnDefinition['sub_part'] ?? ''),
                (string) ($columnDefinition['collation'] ?? ''),
            ]);
        }

        return implode('|', [
            (string) ($indexDefinition['non_unique'] ?? 1),
            strtoupper((string) ($indexDefinition['index_type'] ?? 'BTREE')),
            implode(',', $columnParts),
        ]);
    }

    private static function toAlias(string $tableName, string $prefix): string
    {
        if ($prefix !== '' && strpos($tableName, $prefix) === 0) {
            return '#__' . substr($tableName, strlen($prefix));
        }

        return $tableName;
    }
}
