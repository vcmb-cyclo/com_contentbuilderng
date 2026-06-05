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

final class DatabaseRepairHelper
{
    /**
     * @return array{
     *   scanned:int,
     *   issues:int,
     *   repaired:int,
     *   unchanged:int,
     *   errors:int,
     *   dropped:int,
     *   groups:array<int,array{
     *     table:string,
     *     keep:string,
     *     drop:array<int,string>,
     *     removed:array<int,string>,
     *     status:string,
     *     error:string
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    public static function repairDuplicateIndexesStep(?DatabaseInterface $db = null): array
    {
        $db ??= Factory::getContainer()->get(DatabaseInterface::class);
        $report = DatabaseAuditHelper::run();
        $groups = (array) ($report['duplicate_indexes'] ?? []);

        $summary = [
            'scanned' => count($groups),
            'issues' => count($groups),
            'repaired' => 0,
            'unchanged' => 0,
            'errors' => count((array) ($report['errors'] ?? [])),
            'dropped' => 0,
            'groups' => [],
            'warnings' => array_values((array) ($report['errors'] ?? [])),
        ];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $tableAlias = trim((string) ($group['table'] ?? ''));
            $keep = trim((string) ($group['keep'] ?? ''));
            $drop = array_values(
                array_filter(
                    array_map(static fn($value): string => trim((string) $value), (array) ($group['drop'] ?? [])),
                    static fn(string $value): bool => $value !== ''
                )
            );

            $entry = [
                'table' => $tableAlias,
                'keep' => $keep,
                'drop' => $drop,
                'removed' => [],
                'status' => 'unchanged',
                'error' => '',
            ];

            if ($tableAlias === '' || $drop === []) {
                $summary['unchanged']++;
                $summary['groups'][] = $entry;
                continue;
            }

            $tableName = self::aliasToPhysicalTable($db, $tableAlias);
            $tableQN = $db->quoteName($tableName);
            $errors = [];

            foreach ($drop as $indexName) {
                try {
                    $db->setQuery('ALTER TABLE ' . $tableQN . ' DROP INDEX ' . $db->quoteName($indexName));
                    $db->execute();
                    $entry['removed'][] = $indexName;
                    $summary['dropped']++;
                } catch (\Throwable $e) {
                    $errors[] = $indexName . ': ' . $e->getMessage();
                    $summary['errors']++;
                }
            }

            if ($errors === []) {
                $entry['status'] = 'repaired';
                $summary['repaired']++;
            } elseif ($entry['removed'] !== []) {
                $entry['status'] = 'partial';
                $entry['error'] = implode(' | ', $errors);
                $summary['repaired']++;
            } else {
                $entry['status'] = 'error';
                $entry['error'] = implode(' | ', $errors);
            }

            $summary['groups'][] = $entry;
        }

        return $summary;
    }

    /**
     * @return array{
     *   scanned:int,
     *   issues:int,
     *   repaired:int,
     *   unchanged:int,
     *   errors:int,
     *   renames:array<int,array{
     *     from:string,
     *     to:string,
     *     status:string,
     *     error:string
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    public static function repairHistoricalTablesStep(?DatabaseInterface $db = null): array
    {
        $db ??= Factory::getContainer()->get(DatabaseInterface::class);
        $report = DatabaseAuditHelper::run();
        $historicalTables = array_values((array) ($report['historical_tables'] ?? []));
        $knownTables = [];

        try {
            foreach ((array) $db->getTableList() as $tableName) {
                $tableName = trim((string) $tableName);

                if ($tableName !== '') {
                    $knownTables[$tableName] = true;
                }
            }
        } catch (\Throwable $e) {
            $knownTables = [];
        }

        $summary = [
            'scanned' => count($historicalTables),
            'issues' => count($historicalTables),
            'repaired' => 0,
            'unchanged' => 0,
            'errors' => count((array) ($report['errors'] ?? [])),
            'renames' => [],
            'warnings' => array_values((array) ($report['errors'] ?? [])),
        ];

        foreach ($historicalTables as $historicalAlias) {
            $historicalAlias = trim((string) $historicalAlias);
            $targetAlias = preg_replace('/^#__contentbuilder_/', '#__contentbuilderng_', $historicalAlias, 1) ?: $historicalAlias;
            $entry = [
                'from' => $historicalAlias,
                'to' => $targetAlias,
                'status' => 'unchanged',
                'error' => '',
            ];

            if ($historicalAlias === '' || $historicalAlias === $targetAlias) {
                $summary['unchanged']++;
                $summary['renames'][] = $entry;
                continue;
            }

            $sourceTable = self::aliasToPhysicalTable($db, $historicalAlias);
            $targetTable = self::aliasToPhysicalTable($db, $targetAlias);

            if (isset($knownTables[$targetTable])) {
                $entry['status'] = 'skipped';
                $entry['error'] = 'Target table already exists.';
                $summary['unchanged']++;
                $summary['renames'][] = $entry;
                continue;
            }

            try {
                $db->setQuery(
                    'RENAME TABLE ' . $db->quoteName($sourceTable) . ' TO ' . $db->quoteName($targetTable)
                );
                $db->execute();
                unset($knownTables[$sourceTable]);
                $knownTables[$targetTable] = true;
                $entry['status'] = 'repaired';
                $summary['repaired']++;
            } catch (\Throwable $e) {
                $entry['status'] = 'error';
                $entry['error'] = $e->getMessage();
                $summary['errors']++;
            }

            $summary['renames'][] = $entry;
        }

        return $summary;
    }

    private static function aliasToPhysicalTable(DatabaseInterface $db, string $alias): string
    {
        if (str_starts_with($alias, '#__')) {
            return $db->getPrefix() . substr($alias, 3);
        }

        return $alias;
    }
}
