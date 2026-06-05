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

final class StorageAuditColumnsHelper
{
    /**
     * Required audit/system columns for storage record tables.
     */
    private const REQUIRED_COLUMNS = [
        'id',
        'storage_id',
        'user_id',
        'created',
        'created_by',
        'modified_user_id',
        'modified',
        'modified_by',
    ];

    /**
     * Performance indexes expected on storage record tables.
     */
    private const INDEX_COLUMNS = [
        'storage_id',
        'user_id',
        'created',
        'modified_user_id',
        'modified',
    ];

    /**
     * @return array{
     *   scanned:int,
     *   missing_tables:int,
     *   missing_columns_total:int,
     *   issues:array<int,array{
     *     table:string,
     *     storage_id:int,
     *     storage_name:string,
     *     bytable:int,
     *     missing:array<int,string>
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    public static function audit(DatabaseInterface $db): array
    {
        $summary = [
            'scanned' => 0,
            'missing_tables' => 0,
            'missing_columns_total' => 0,
            'issues' => [],
            'warnings' => [],
        ];

        // Audit only internal storage tables managed by ContentBuilder NG.
        // External mapped tables (bytable=1), e.g. #__users, are excluded.
        [$storageTables, $warnings] = self::collectStorageTables($db, false);

        if ($warnings !== []) {
            $summary['warnings'] = array_merge($summary['warnings'], $warnings);
        }

        $summary['scanned'] = count($storageTables);

        foreach ($storageTables as $storageTable) {
            $physicalTable = (string) ($storageTable['table_name'] ?? '');

            if ($physicalTable === '') {
                continue;
            }

            try {
                $columns = self::loadColumns($db, $physicalTable);
            } catch (\Throwable $e) {
                $summary['warnings'][] = 'Could not inspect columns on ' . (string) ($storageTable['table_alias'] ?? $physicalTable)
                    . ': ' . $e->getMessage();
                continue;
            }

            $missing = self::computeMissingColumns($columns);

            if ($missing === []) {
                continue;
            }

            $summary['missing_tables']++;
            $summary['missing_columns_total'] += count($missing);
            $summary['issues'][] = [
                'table' => (string) ($storageTable['table_alias'] ?? $physicalTable),
                'storage_id' => (int) ($storageTable['storage_id'] ?? 0),
                'storage_name' => (string) ($storageTable['storage_name'] ?? ''),
                'bytable' => (int) ($storageTable['bytable'] ?? 0),
                'missing' => $missing,
            ];
        }

        usort(
            $summary['issues'],
            static fn(array $a, array $b): int => strcmp(
                ((string) ($a['table'] ?? '')) . ':' . ((int) ($a['storage_id'] ?? 0)),
                ((string) ($b['table'] ?? '')) . ':' . ((int) ($b['storage_id'] ?? 0))
            )
        );

        return $summary;
    }

    /**
     * @return array{
     *   scanned:int,
     *   issues:int,
     *   repaired:int,
     *   unchanged:int,
     *   errors:int,
     *   tables:array<int,array{
     *     table:string,
     *     storage_id:int,
     *     storage_name:string,
     *     bytable:int,
     *     missing:array<int,string>,
     *     added:array<int,string>,
     *     indexes_added:array<int,string>,
     *     status:string,
     *     error:string
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    public static function repair(DatabaseInterface $db): array
    {
        $summary = [
            'scanned' => 0,
            'issues' => 0,
            'repaired' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'tables' => [],
            'warnings' => [],
        ];

        // Repair is limited to internal storage tables only.
        // External mapped tables (bytable=1) are intentionally excluded.
        [$storageTables, $warnings] = self::collectStorageTables($db, false);

        if ($warnings !== []) {
            $summary['warnings'] = array_merge($summary['warnings'], $warnings);
            $summary['errors'] += count($warnings);
        }

        $summary['scanned'] = count($storageTables);
        $now = Factory::getDate()->toSql();

        foreach ($storageTables as $storageTable) {
            $physicalTable = (string) ($storageTable['table_name'] ?? '');
            $tableAlias = (string) ($storageTable['table_alias'] ?? $physicalTable);
            $storageId = (int) ($storageTable['storage_id'] ?? 0);
            $storageName = (string) ($storageTable['storage_name'] ?? '');
            $bytable = (int) ($storageTable['bytable'] ?? 0);

            if ($physicalTable === '') {
                continue;
            }

            $tableSummary = [
                'table' => $tableAlias,
                'storage_id' => $storageId,
                'storage_name' => $storageName,
                'bytable' => $bytable,
                'missing' => [],
                'added' => [],
                'indexes_added' => [],
                'status' => 'unchanged',
                'error' => '',
            ];

            try {
                $columns = self::loadColumns($db, $physicalTable);
            } catch (\Throwable $e) {
                $summary['errors']++;
                $tableSummary['status'] = 'error';
                $tableSummary['error'] = $e->getMessage();
                $summary['tables'][] = $tableSummary;
                continue;
            }

            $missingColumns = self::computeMissingColumns($columns);
            $tableSummary['missing'] = $missingColumns;

            if ($missingColumns !== []) {
                $summary['issues']++;
            }

            if ($missingColumns === []) {
                $summary['unchanged']++;
                $summary['tables'][] = $tableSummary;
                continue;
            }

            $tableQN = $db->quoteName($physicalTable);
            $tableErrors = [];
            $addedColumns = [];

            foreach ($missingColumns as $missingColumn) {
                try {
                    $db->setQuery(self::buildAddColumnSql($db, $tableQN, $missingColumn, $storageId, $now));
                    $db->execute();
                    $addedColumns[] = $missingColumn;
                    $columns[$missingColumn] = true;
                } catch (\Throwable $e) {
                    $tableErrors[] = '[' . $missingColumn . '] ' . $e->getMessage();
                }
            }

            $tableSummary['added'] = $addedColumns;

            $normalizationErrors = self::normalizeAuditValues($db, $tableQN, $columns, $storageId, $now);
            if ($normalizationErrors !== []) {
                $tableErrors = array_merge($tableErrors, $normalizationErrors);
            }

            [$addedIndexes, $indexErrors] = self::ensureAuditIndexes($db, $tableQN, $columns);
            $tableSummary['indexes_added'] = $addedIndexes;
            if ($indexErrors !== []) {
                $tableErrors = array_merge($tableErrors, $indexErrors);
            }

            if ($tableErrors === []) {
                $summary['repaired']++;
                $tableSummary['status'] = 'repaired';
            } elseif ($addedColumns !== [] || $addedIndexes !== []) {
                $summary['errors']++;
                $tableSummary['status'] = 'partial';
                $tableSummary['error'] = implode(' | ', $tableErrors);
            } else {
                $summary['errors']++;
                $tableSummary['status'] = 'error';
                $tableSummary['error'] = implode(' | ', $tableErrors);
            }

            $summary['tables'][] = $tableSummary;
        }

        return $summary;
    }

    /**
     * @return array{0:array<int,array{table_name:string,table_alias:string,storage_id:int,storage_name:string,bytable:int}>,1:array<int,string>}
     */
    private static function collectStorageTables(DatabaseInterface $db, bool $includeExternalBytable = false): array
    {
        $warnings = [];
        $result = [];
        $prefix = $db->getPrefix();

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'bytable']))
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where($db->quoteName('name') . " <> ''");

            $db->setQuery($query);
            $storages = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $warnings[] = 'Could not inspect #__contentbuilderng_storages: ' . $e->getMessage();
            return [[], $warnings];
        }

        try {
            $tableList = $db->getTableList();
        } catch (\Throwable $e) {
            $warnings[] = 'Could not list database tables: ' . $e->getMessage();
            return [[], $warnings];
        }

        $availableTables = [];
        foreach ((array) $tableList as $tableName) {
            $tableName = (string) $tableName;
            if ($tableName !== '') {
                $availableTables[$tableName] = true;
            }
        }

        foreach ($storages as $storage) {
            if (!is_array($storage)) {
                continue;
            }

            $storageId = (int) ($storage['id'] ?? 0);
            $storageName = trim((string) ($storage['name'] ?? ''));
            $bytable = (int) ($storage['bytable'] ?? 0);

            if ($storageName === '') {
                continue;
            }

            if (!$includeExternalBytable && $bytable === 1) {
                continue;
            }

            $resolvedTable = '';

            if ($bytable === 1) {
                if (isset($availableTables[$storageName])) {
                    $resolvedTable = $storageName;
                } else {
                    $prefixedStorageName = $prefix . $storageName;
                    if (isset($availableTables[$prefixedStorageName])) {
                        $resolvedTable = $prefixedStorageName;
                    }
                }
            } else {
                $prefixedStorageName = $prefix . $storageName;
                if (isset($availableTables[$prefixedStorageName])) {
                    $resolvedTable = $prefixedStorageName;
                } elseif (isset($availableTables[$storageName])) {
                    // Fallback for unexpected configurations.
                    $resolvedTable = $storageName;
                }
            }

            if ($resolvedTable === '') {
                $warnings[] = 'Storage #' . $storageId . ' (' . $storageName . ') table not found.';
                continue;
            }

            $result[] = [
                'table_name' => $resolvedTable,
                'table_alias' => self::toAlias($resolvedTable, $prefix),
                'storage_id' => $storageId,
                'storage_name' => $storageName,
                'bytable' => $bytable,
            ];
        }

        usort(
            $result,
            static fn(array $a, array $b): int => strcmp(
                ((string) ($a['table_alias'] ?? '')) . ':' . ((int) ($a['storage_id'] ?? 0)),
                ((string) ($b['table_alias'] ?? '')) . ':' . ((int) ($b['storage_id'] ?? 0))
            )
        );

        return [$result, $warnings];
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadColumns(DatabaseInterface $db, string $physicalTable): array
    {
        $rawColumns = $db->getTableColumns($physicalTable, true);
        $columns = [];

        foreach ((array) $rawColumns as $columnName => $columnDefinition) {
            $safeColumnName = strtolower((string) $columnName);
            if ($safeColumnName !== '') {
                $columns[$safeColumnName] = $columnDefinition;
            }
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $columns
     * @return array<int,string>
     */
    private static function computeMissingColumns(array $columns): array
    {
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $requiredColumn) {
            if (!isset($columns[$requiredColumn])) {
                $missing[] = $requiredColumn;
            }
        }

        return $missing;
    }

    private static function buildAddColumnSql(
        DatabaseInterface $db,
        string $tableQN,
        string $column,
        int $storageId,
        string $now
    ): string {
        switch ($column) {
            case 'id':
                return 'ALTER TABLE ' . $tableQN
                    . ' ADD ' . $db->quoteName('id') . ' INT NOT NULL AUTO_INCREMENT PRIMARY KEY';

            case 'storage_id':
                return 'ALTER TABLE ' . $tableQN
                    . ' ADD ' . $db->quoteName('storage_id') . ' INT NOT NULL DEFAULT ' . $storageId;

            case 'user_id':
                return 'ALTER TABLE ' . $tableQN
                    . ' ADD ' . $db->quoteName('user_id') . ' INT NOT NULL DEFAULT 0';

            case 'created':
                return 'ALTER TABLE ' . $tableQN
                    . ' ADD ' . $db->quoteName('created') . ' DATETIME NOT NULL DEFAULT ' . $db->quote($now);

            case 'created_by':
                return 'ALTER TABLE ' . $tableQN
                    . ' ADD ' . $db->quoteName('created_by') . " VARCHAR(255) NOT NULL DEFAULT ''";

            case 'modified_user_id':
                return 'ALTER TABLE ' . $tableQN
                    . ' ADD ' . $db->quoteName('modified_user_id') . ' INT NOT NULL DEFAULT 0';

            case 'modified':
                return 'ALTER TABLE ' . $tableQN
                    . ' ADD ' . $db->quoteName('modified') . ' DATETIME NULL DEFAULT NULL';

            case 'modified_by':
                return 'ALTER TABLE ' . $tableQN
                    . ' ADD ' . $db->quoteName('modified_by') . " VARCHAR(255) NOT NULL DEFAULT ''";

            default:
                throw new \InvalidArgumentException('Unsupported audit column: ' . $column);
        }
    }

    /**
     * @param array<string,mixed> $columns
     * @return array<int,string>
     */
    private static function normalizeAuditValues(
        DatabaseInterface $db,
        string $tableQN,
        array $columns,
        int $storageId,
        string $now
    ): array {
        $errors = [];

        $updates = [
            'storage_id' =>
                'UPDATE ' . $tableQN
                . ' SET ' . $db->quoteName('storage_id') . ' = ' . $storageId
                . ' WHERE ' . $db->quoteName('storage_id') . ' IS NULL OR ' . $db->quoteName('storage_id') . ' = 0',
            'user_id' =>
                'UPDATE ' . $tableQN
                . ' SET ' . $db->quoteName('user_id') . ' = 0'
                . ' WHERE ' . $db->quoteName('user_id') . ' IS NULL',
            'created' =>
                'UPDATE ' . $tableQN
                . ' SET ' . $db->quoteName('created') . ' = ' . $db->quote($now)
                . ' WHERE ' . $db->quoteName('created') . ' IS NULL',
            'created_by' =>
                'UPDATE ' . $tableQN
                . ' SET ' . $db->quoteName('created_by') . " = ''"
                . ' WHERE ' . $db->quoteName('created_by') . ' IS NULL',
            'modified_user_id' =>
                'UPDATE ' . $tableQN
                . ' SET ' . $db->quoteName('modified_user_id') . ' = 0'
                . ' WHERE ' . $db->quoteName('modified_user_id') . ' IS NULL',
            'modified_by' =>
                'UPDATE ' . $tableQN
                . ' SET ' . $db->quoteName('modified_by') . " = ''"
                . ' WHERE ' . $db->quoteName('modified_by') . ' IS NULL',
        ];

        foreach ($updates as $column => $sql) {
            if (!isset($columns[$column])) {
                continue;
            }

            try {
                $db->setQuery($sql);
                $db->execute();
            } catch (\Throwable $e) {
                $errors[] = '[normalize:' . $column . '] ' . $e->getMessage();
            }
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $columns
     * @return array{0:array<int,string>,1:array<int,string>}
     */
    private static function ensureAuditIndexes(DatabaseInterface $db, string $tableQN, array $columns): array
    {
        $added = [];
        $errors = [];

        try {
            $indexedColumns = self::getIndexedColumns($db, $tableQN);
        } catch (\Throwable $e) {
            return [[], ['[indexes] ' . $e->getMessage()]];
        }

        foreach (self::INDEX_COLUMNS as $column) {
            if (!isset($columns[$column])) {
                continue;
            }

            if (isset($indexedColumns[$column])) {
                continue;
            }

            try {
                $db->setQuery(
                    'ALTER TABLE ' . $tableQN
                    . ' ADD INDEX (' . $db->quoteName($column) . ')'
                );
                $db->execute();
                $indexedColumns[$column] = true;
                $added[] = $column;
            } catch (\Throwable $e) {
                $message = strtolower((string) $e->getMessage());

                if (strpos($message, 'duplicate') !== false || strpos($message, 'already exists') !== false) {
                    $indexedColumns[$column] = true;
                    continue;
                }

                $errors[] = '[index:' . $column . '] ' . $e->getMessage();
            }
        }

        return [$added, $errors];
    }

    /**
     * @return array<string,bool>
     */
    private static function getIndexedColumns(DatabaseInterface $db, string $tableQN): array
    {
        $indexedColumns = [];

        $db->setQuery('SHOW INDEX FROM ' . $tableQN);
        $rows = $db->loadAssocList() ?: [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $columnName = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));
            if ($columnName !== '') {
                $indexedColumns[$columnName] = true;
            }
        }

        return $indexedColumns;
    }

    private static function toAlias(string $tableName, string $prefix): string
    {
        if ($prefix !== '' && strpos($tableName, $prefix) === 0) {
            return '#__' . substr($tableName, strlen($prefix));
        }

        return $tableName;
    }
}
