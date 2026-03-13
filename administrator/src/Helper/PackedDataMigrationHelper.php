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

final class PackedDataMigrationHelper
{
    private const TARGET_CHARSET = 'utf8mb4';
    private const TARGET_COLLATION = 'utf8mb4_0900_ai_ci';

    private const PACKED_MIGRATION_TARGETS = [
        [
            'table' => '#__contentbuilderng_elements',
            'primaryKey' => 'id',
            'column' => 'options',
        ],
        [
            'table' => '#__contentbuilderng_forms',
            'primaryKey' => 'id',
            'column' => 'config',
        ],
    ];

    private static function isMigrationCandidate(string $raw): bool
    {
        $decoded = base64_decode($raw, true);

        if ($decoded === false) {
            return false;
        }

        // Already in modern format.
        if (strpos($decoded, 'j:') === 0) {
            return false;
        }

        return true;
    }

    /**
     * Migrate packed payloads in database columns to the JSON-based packed format.
     *
     * @return array{
     *   scanned:int,
     *   candidates:int,
     *   migrated:int,
     *   unchanged:int,
     *   errors:int,
     *   tables:array<int,array{table:string,column:string,scanned:int,candidates:int,migrated:int,unchanged:int,errors:int}>,
     *   repair:array{
     *     target_collation:string,
     *     target_charset:string,
     *     supported:bool,
     *     scanned:int,
     *     converted:int,
     *     unchanged:int,
     *     errors:int,
     *     tables:array<int,array{table:string,from:string,to:string,status:string,error:string}>,
     *     warnings:array<int,string>
     *   },
     *   audit_columns:array{
     *     scanned:int,
     *     issues:int,
     *     repaired:int,
     *     unchanged:int,
     *     errors:int,
     *     tables:array<int,array{
     *       table:string,
     *       storage_id:int,
     *       storage_name:string,
     *       bytable:int,
     *       missing:array<int,string>,
     *       added:array<int,string>,
     *       indexes_added:array<int,string>,
     *       status:string,
     *       error:string
     *     }>,
     *     warnings:array<int,string>
     *   },
     *   plugin_duplicates:array{
     *     scanned:int,
     *     issues:int,
     *     repaired:int,
     *     unchanged:int,
     *     errors:int,
     *     rows_removed:int,
     *     groups:array<int,array{
     *       canonical_folder:string,
     *       canonical_element:string,
     *       keep_id:int,
     *       removed_ids:array<int,int>,
     *       status:string,
     *       error:string
     *     }>,
     *     warnings:array<int,string>
     *   },
     *   historical_menu_entries:array{
     *     scanned:int,
     *     issues:int,
     *     repaired:int,
     *     unchanged:int,
     *     errors:int,
     *     entries:array<int,array{
     *       menu_id:int,
     *       old_title:string,
     *       new_title:string,
     *       link:string,
     *       status:string,
     *       error:string
     *     }>,
     *     warnings:array<int,string>
     *   }
     * }
     */
    public static function migrate(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $summary = self::migratePackedPayloads($db);
        $summary['repair'] = self::repairTableCollations($db);
        $summary['audit_columns'] = StorageAuditColumnsHelper::repair($db);
        $summary['plugin_duplicates'] = PluginExtensionDedupHelper::repair($db);
        $summary['historical_menu_entries'] = self::repairLegacyMenuEntries($db);

        return $summary;
    }

    /**
     * @return array{
     *   scanned:int,
     *   candidates:int,
     *   migrated:int,
     *   unchanged:int,
     *   errors:int,
     *   tables:array<int,array{table:string,column:string,scanned:int,candidates:int,migrated:int,unchanged:int,errors:int}>
     * }
     */
    private static function migratePackedPayloads(DatabaseInterface $db): array
    {
        $summary = [
            'scanned' => 0,
            'candidates' => 0,
            'migrated' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'tables' => [],
        ];

        foreach (self::PACKED_MIGRATION_TARGETS as $target) {
            $tableStats = [
                'table' => (string) $target['table'],
                'column' => (string) $target['column'],
                'scanned' => 0,
                'candidates' => 0,
                'migrated' => 0,
                'unchanged' => 0,
                'errors' => 0,
            ];

            try {
                $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName($target['primaryKey']),
                        $db->quoteName($target['column']),
                    ])
                    ->from($db->quoteName($target['table']))
                    ->where($db->quoteName($target['column']) . ' IS NOT NULL')
                    ->where($db->quoteName($target['column']) . " <> ''");

                $db->setQuery($query);
                $rows = $db->loadAssocList();
            } catch (\Throwable $e) {
                $tableStats['errors']++;
                $summary['errors']++;
                $summary['tables'][] = $tableStats;
                continue;
            }

            if (!is_array($rows)) {
                $summary['tables'][] = $tableStats;
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $id = (int) ($row[$target['primaryKey']] ?? 0);
                $raw = (string) ($row[$target['column']] ?? '');

                $tableStats['scanned']++;
                $summary['scanned']++;

                if ($id <= 0 || $raw === '') {
                    $tableStats['unchanged']++;
                    $summary['unchanged']++;
                    continue;
                }

                if (!self::isMigrationCandidate($raw)) {
                    $tableStats['unchanged']++;
                    $summary['unchanged']++;
                    continue;
                }

                $tableStats['candidates']++;
                $summary['candidates']++;

                $sentinel = new \stdClass();
                $decoded = PackedDataHelper::decodePackedData($raw, $sentinel, false);

                if ($decoded === $sentinel) {
                    $tableStats['errors']++;
                    $summary['errors']++;
                    continue;
                }

                $encoded = PackedDataHelper::encodePackedData($decoded);

                if ($encoded === $raw) {
                    $tableStats['unchanged']++;
                    $summary['unchanged']++;
                    continue;
                }

                try {
                    $update = $db->getQuery(true)
                        ->update($db->quoteName($target['table']))
                        ->set($db->quoteName($target['column']) . ' = ' . $db->quote($encoded))
                        ->where($db->quoteName($target['primaryKey']) . ' = ' . $id);

                    $db->setQuery($update);
                    $db->execute();
                } catch (\Throwable $e) {
                    $tableStats['errors']++;
                    $summary['errors']++;
                    continue;
                }

                $tableStats['migrated']++;
                $summary['migrated']++;
            }

            $summary['tables'][] = $tableStats;
        }

        return $summary;
    }

    /**
     * @return array{
     *   target_collation:string,
     *   target_charset:string,
     *   supported:bool,
     *   scanned:int,
     *   converted:int,
     *   unchanged:int,
     *   errors:int,
     *   tables:array<int,array{table:string,from:string,to:string,status:string,error:string}>,
     *   warnings:array<int,string>
     * }
     */
    private static function repairTableCollations(DatabaseInterface $db): array
    {
        $summary = [
            'target_collation' => self::TARGET_COLLATION,
            'target_charset' => self::TARGET_CHARSET,
            'supported' => false,
            'scanned' => 0,
            'converted' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'tables' => [],
            'warnings' => [],
        ];

        if (!self::isCollationSupported($db, self::TARGET_COLLATION)) {
            return $summary;
        }

        $summary['supported'] = true;

        [$tables, $discoveryWarnings] = self::collectRepairableTables($db);

        if ($discoveryWarnings !== []) {
            $summary['warnings'] = array_merge($summary['warnings'], $discoveryWarnings);
            $summary['errors'] += count($discoveryWarnings);
        }

        if ($tables === []) {
            return $summary;
        }

        [$tableCollations, $collationWarnings] = self::loadCurrentTableCollations($db, $tables);

        if ($collationWarnings !== []) {
            $summary['warnings'] = array_merge($summary['warnings'], $collationWarnings);
            $summary['errors'] += count($collationWarnings);
        }

        $prefix = $db->getPrefix();

        foreach ($tables as $tableName) {
            $currentCollation = (string) ($tableCollations[$tableName] ?? '');
            $tableAlias = self::toAlias($tableName, $prefix);

            $summary['scanned']++;

            if ($currentCollation !== '' && strcasecmp($currentCollation, self::TARGET_COLLATION) === 0) {
                $summary['unchanged']++;
                $summary['tables'][] = [
                    'table' => $tableAlias,
                    'from' => $currentCollation,
                    'to' => self::TARGET_COLLATION,
                    'status' => 'unchanged',
                    'error' => '',
                ];
                continue;
            }

            try {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName($tableName)
                    . ' CONVERT TO CHARACTER SET ' . self::TARGET_CHARSET
                    . ' COLLATE ' . self::TARGET_COLLATION
                );
                $db->execute();
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['tables'][] = [
                    'table' => $tableAlias,
                    'from' => $currentCollation,
                    'to' => self::TARGET_COLLATION,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                continue;
            }

            $summary['converted']++;
            $summary['tables'][] = [
                'table' => $tableAlias,
                'from' => $currentCollation,
                'to' => self::TARGET_COLLATION,
                'status' => 'converted',
                'error' => '',
            ];
        }

        return $summary;
    }

    private static function isCollationSupported(DatabaseInterface $db, string $collation): bool
    {
        try {
            $db->setQuery(
                'SELECT COUNT(*)'
                . ' FROM information_schema.COLLATIONS'
                . ' WHERE COLLATION_NAME = ' . $db->quote($collation)
            );
            return ((int) $db->loadResult()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{0:array<int,string>,1:array<int,string>}
     */
    private static function collectRepairableTables(DatabaseInterface $db): array
    {
        $prefix = $db->getPrefix();
        $tables = [];
        $warnings = [];

        try {
            $tableList = $db->getTableList();
        } catch (\Throwable $e) {
            $warnings[] = 'Could not list database tables: ' . $e->getMessage();
            return [[], $warnings];
        }

        $physicalTables = [];
        foreach ((array) $tableList as $tableName) {
            $tableName = (string) $tableName;

            if ($tableName !== '') {
                $physicalTables[$tableName] = true;
            }

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
                if (isset($physicalTables[$physicalTable])) {
                    $tables[$physicalTable] = true;
                }
            }
        } catch (\Throwable $e) {
            $warnings[] = 'Could not inspect #__contentbuilderng_storages: ' . $e->getMessage();
        }

        $result = array_keys($tables);
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);

        return [$result, $warnings];
    }

    /**
     * @param array<int,string> $tables
     * @return array{0:array<string,string>,1:array<int,string>}
     */
    private static function loadCurrentTableCollations(DatabaseInterface $db, array $tables): array
    {
        if ($tables === []) {
            return [[], []];
        }

        $collationMap = [];
        $warnings = [];

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
                . ' AND TABLE_TYPE = ' . $db->quote('BASE TABLE')
                . ' AND TABLE_NAME IN (' . $inClause . ')'
            );
            $rows = $db->loadAssocList() ?: [];

            foreach ($rows as $row) {
                $tableName = (string) ($row['TABLE_NAME'] ?? $row['table_name'] ?? '');
                $tableCollation = (string) ($row['TABLE_COLLATION'] ?? $row['table_collation'] ?? '');

                if ($tableName !== '') {
                    $collationMap[$tableName] = $tableCollation;
                }
            }
        } catch (\Throwable $e) {
            $warnings[] = 'Could not inspect table collations: ' . $e->getMessage();
        }

        return [$collationMap, $warnings];
    }

    private static function toAlias(string $tableName, string $prefix): string
    {
        if ($prefix !== '' && strpos($tableName, $prefix) === 0) {
            return '#__' . substr($tableName, strlen($prefix));
        }

        return $tableName;
    }

    /**
     * @return array{
     *   scanned:int,
     *   issues:int,
     *   repaired:int,
     *   unchanged:int,
     *   errors:int,
     *   entries:array<int,array{
     *     menu_id:int,
     *     old_title:string,
     *     new_title:string,
     *     link:string,
     *     status:string,
     *     error:string
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    private static function repairLegacyMenuEntries(DatabaseInterface $db): array
    {
        $summary = [
            'scanned' => 0,
            'issues' => 0,
            'repaired' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'entries' => [],
            'warnings' => [],
        ];

        [$legacyEntries, $warnings] = self::collectLegacyMenuEntries($db);

        if ($warnings !== []) {
            $summary['warnings'] = $warnings;
            $summary['errors'] += count($warnings);
        }

        if ($legacyEntries === []) {
            return $summary;
        }

        $summary['scanned'] = count($legacyEntries);
        $summary['issues'] = count($legacyEntries);

        foreach ($legacyEntries as $legacyEntry) {
            $menuId = (int) ($legacyEntry['menu_id'] ?? 0);
            $oldTitle = (string) ($legacyEntry['title'] ?? '');
            $newTitle = (string) ($legacyEntry['normalized_title'] ?? '');
            $link = trim((string) ($legacyEntry['link'] ?? ''));

            if ($menuId < 1 || $oldTitle === '' || $newTitle === '' || $newTitle === $oldTitle) {
                $summary['unchanged']++;
                $summary['entries'][] = [
                    'menu_id' => $menuId,
                    'old_title' => $oldTitle,
                    'new_title' => $newTitle,
                    'link' => $link,
                    'status' => 'unchanged',
                    'error' => '',
                ];
                continue;
            }

            try {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('title') . ' = ' . $db->quote($newTitle))
                    ->where($db->quoteName('id') . ' = ' . $menuId);

                $db->setQuery($query);
                $db->execute();

                $summary['repaired']++;
                $summary['entries'][] = [
                    'menu_id' => $menuId,
                    'old_title' => $oldTitle,
                    'new_title' => $newTitle,
                    'link' => $link,
                    'status' => 'repaired',
                    'error' => '',
                ];
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['entries'][] = [
                    'menu_id' => $menuId,
                    'old_title' => $oldTitle,
                    'new_title' => $newTitle,
                    'link' => $link,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * @return array{0:array<int,array{menu_id:int,title:string,normalized_title:string,link:string}>,1:array<int,string>}
     */
    private static function collectLegacyMenuEntries(DatabaseInterface $db): array
    {
        $warnings = [];

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'link', 'alias', 'path']))
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
            $warnings[] = 'Could not inspect historical menu entries: ' . $e->getMessage();
            return [[], $warnings];
        }

        $entries = [];

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
                'link' => trim((string) ($row['link'] ?? '')),
            ];
        }

        return [$entries, $warnings];
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
}
