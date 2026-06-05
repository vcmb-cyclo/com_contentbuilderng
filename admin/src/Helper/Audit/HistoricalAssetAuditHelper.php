<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use Joomla\Database\DatabaseInterface;

final class HistoricalAssetAuditHelper
{
    /**
     * @param array<int,string> $tables
     * @return array<int,string>
     */
    public static function findLegacyContentbuilderTables(array $tables, string $prefix, callable $toAlias): array
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
                $historical[] = $toAlias($tableName, $prefix);
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
    public static function findLegacyMenuEntries(DatabaseInterface $db): array
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
    public static function collectCbTableStats(
        DatabaseInterface $db,
        array $tables,
        string $prefix,
        array $legacyTables,
        callable $toAlias
    ): array {
        $errors = [];
        $aliases = array_map(
            static fn(string $tableName): string => $toAlias($tableName, $prefix),
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
                'table' => $toAlias($tableName, $prefix),
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
}
