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

final class DuplicateIndexAuditHelper
{
    /**
     * @param array<int,string> $tables
     * @param callable(DatabaseInterface,string):array<string,array{non_unique:int,index_type:string,columns:array<int,array{name:string,sub_part:string,collation:string}>,signature:string}> $getTableIndexes
     * @param callable(string,string):string $toAlias
     * @return array{0:array<int,array{table:string,indexes:array<int,string>,keep:string,drop:array<int,string>}>,1:array<int,string>}
     */
    public static function find(DatabaseInterface $db, array $tables, string $prefix, callable $getTableIndexes, callable $toAlias): array
    {
        $duplicates = [];
        $errors = [];

        foreach ($tables as $tableName) {
            try {
                $indexes = $getTableIndexes($db, $tableName);
            } catch (\Throwable $e) {
                $errors[] = 'Could not inspect indexes on ' . $toAlias($tableName, $prefix) . ': ' . $e->getMessage();
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
                    'table' => $toAlias($tableName, $prefix),
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
}
