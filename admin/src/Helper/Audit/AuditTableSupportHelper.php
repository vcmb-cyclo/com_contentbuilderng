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

final class AuditTableSupportHelper
{
    /**
     * @param array<int,string> $errors
     * @return array<int,string>
     */
    public static function collectAuditableTables(DatabaseInterface $db, array &$errors): array
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
     * @return array<string,array{non_unique:int,index_type:string,columns:array<int,array{name:string,sub_part:string,collation:string}>,signature:string}>
     */
    public static function getTableIndexes(DatabaseInterface $db, string $tableName): array
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

    public static function toAlias(string $tableName, string $prefix): string
    {
        if ($prefix !== '' && strpos($tableName, $prefix) === 0) {
            return '#__' . substr($tableName, strlen($prefix));
        }

        return $tableName;
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
}
