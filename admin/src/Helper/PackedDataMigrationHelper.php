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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\EncodingAuditHelper;

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

    private static function detectPackedPayloadType(string $raw): string
    {
        $decoded = base64_decode($raw, true);

        if ($decoded === false) {
            return 'invalid';
        }

        $jsonPayload = null;
        if (strpos($decoded, 'j:') === 0) {
            $jsonPayload = substr($decoded, 2);
        } elseif (strpos(ltrim($decoded), '{') === 0 || strpos(ltrim($decoded), '[') === 0) {
            $jsonPayload = $decoded;
        }

        if ($jsonPayload !== null) {
            try {
                json_decode($jsonPayload, false, 512, JSON_THROW_ON_ERROR);

                return 'json';
            } catch (\Throwable) {
            }
        }

        try {
            $legacyPayload = @unserialize($decoded, ['allowed_classes' => ['stdClass']]);

            if ($legacyPayload !== false || $decoded === 'b:0;') {
                return 'legacy_php';
            }
        } catch (\Throwable) {
        }

        return 'invalid';
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
     *   tables:array<int,array{
     *     table:string,
     *     column:string,
     *     scanned:int,
     *     candidates:int,
     *     migrated:int,
     *     unchanged:int,
     *     errors:int,
     *     rows:array<int,array{
     *       record_id:int,
     *       record_label:string,
     *       form_id:int,
     *       form_label:string,
     *       status:string,
     *       error:string
     *     }>
     *   }>,
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
     *   tables:array<int,array{
     *     table:string,
     *     column:string,
     *     scanned:int,
     *     candidates:int,
     *     migrated:int,
     *     unchanged:int,
     *     errors:int,
     *     rows:array<int,array{
     *       record_id:int,
     *       record_label:string,
     *       form_id:int,
     *       form_label:string,
     *       status:string,
     *       error:string
     *     }>
     *   }>
     * }
     */
    public static function migratePackedPayloadsStep(?DatabaseInterface $db = null): array
    {
        return self::migratePackedPayloads($db ?? Factory::getContainer()->get(DatabaseInterface::class), true);
    }

    /**
     * @return array{
     *   scanned:int,
     *   candidates:int,
     *   migrated:int,
     *   unchanged:int,
     *   errors:int,
     *   tables:array<int,array{
     *     table:string,
     *     column:string,
     *     scanned:int,
     *     candidates:int,
     *     migrated:int,
     *     unchanged:int,
     *     errors:int,
     *     rows:array<int,array{
     *       record_id:int,
     *       record_label:string,
     *       form_id:int,
     *       form_label:string,
     *       status:string,
     *       error:string
     *     }>
     *   }>
     * }
     */
    public static function auditPackedPayloadsStep(?DatabaseInterface $db = null): array
    {
        return self::migratePackedPayloads($db ?? Factory::getContainer()->get(DatabaseInterface::class), false);
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
    public static function repairTableCollationsStep(?DatabaseInterface $db = null): array
    {
        return self::repairTableCollations($db ?? Factory::getContainer()->get(DatabaseInterface::class));
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
    public static function repairLegacyMenuEntriesStep(?DatabaseInterface $db = null): array
    {
        return self::repairLegacyMenuEntries($db ?? Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * @return array{
     *   scanned:int,
     *   candidates:int,
     *   migrated:int,
     *   unchanged:int,
     *   errors:int,
     *   tables:array<int,array{
     *     table:string,
     *     column:string,
     *     scanned:int,
     *     candidates:int,
     *     migrated:int,
     *     unchanged:int,
     *     errors:int,
     *     rows:array<int,array{
     *       record_id:int,
     *       record_label:string,
     *       form_id:int,
     *       form_label:string,
     *       status:string,
     *       error:string
     *     }>
     *   }>
     * }
     */
    private static function migratePackedPayloads(DatabaseInterface $db, bool $applyChanges = true): array
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
                'rows' => [],
            ];

            try {
                $query = $db->getQuery(true);

                if ($target['table'] === '#__contentbuilderng_elements') {
                    $query
                        ->select([
                            $db->quoteName('e.id'),
                            $db->quoteName('e.form_id'),
                            $db->quoteName('e.label'),
                            $db->quoteName('e.reference_id'),
                            $db->quoteName('e.options'),
                            $db->quoteName('f.name', 'form_name'),
                            $db->quoteName('f.title', 'form_title'),
                        ])
                        ->from($db->quoteName($target['table'], 'e'))
                        ->join(
                            'LEFT',
                            $db->quoteName('#__contentbuilderng_forms', 'f')
                            . ' ON ' . $db->quoteName('f.id') . ' = ' . $db->quoteName('e.form_id')
                        )
                        ->where($db->quoteName('e.options') . ' IS NOT NULL')
                        ->where($db->quoteName('e.options') . " <> ''");
                } else {
                    $query
                        ->select([
                            $db->quoteName('f.id'),
                            $db->quoteName('f.name'),
                            $db->quoteName('f.title'),
                            $db->quoteName('f.config'),
                        ])
                        ->from($db->quoteName($target['table'], 'f'))
                        ->where($db->quoteName('f.config') . ' IS NOT NULL')
                        ->where($db->quoteName('f.config') . " <> ''");
                }

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
                $formId = $target['table'] === '#__contentbuilderng_elements'
                    ? (int) ($row['form_id'] ?? 0)
                    : $id;
                $formName = trim((string) ($row['form_name'] ?? ($row['name'] ?? '')));
                $formTitle = trim((string) ($row['form_title'] ?? ($row['title'] ?? '')));
                $formLabel = $formName !== '' ? $formName : ($formTitle !== '' ? $formTitle : '#'.$formId);
                $recordLabel = $target['table'] === '#__contentbuilderng_elements'
                    ? trim((string) ($row['label'] ?? ''))
                    : trim((string) ($row['name'] ?? ''));
                if ($recordLabel === '') {
                    $recordLabel = trim((string) ($row['reference_id'] ?? ''));
                }
                if ($recordLabel === '') {
                    $recordLabel = '#' . $id;
                }

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
                $tableStats['rows'][] = [
                    'record_id' => $id,
                    'record_label' => $recordLabel,
                    'form_id' => $formId,
                    'form_label' => $formLabel,
                    'payload_type' => self::detectPackedPayloadType($raw),
                    'status' => 'pending',
                    'error' => '',
                ];
                $rowIndex = array_key_last($tableStats['rows']);

                $sentinel = new \stdClass();
                $decoded = PackedDataHelper::decodePackedData($raw, $sentinel, false);

                if ($decoded === $sentinel) {
                    $tableStats['errors']++;
                    $summary['errors']++;
                    if ($rowIndex !== null) {
                        $tableStats['rows'][$rowIndex]['status'] = 'error';
                        $tableStats['rows'][$rowIndex]['error'] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_PACKED_DATA_DECODE_FAILED',
                            (string) $target['table'],
                            (string) $target['column']
                        );
                    }
                    continue;
                }

                $encoded = PackedDataHelper::encodePackedData($decoded);

                if ($encoded === $raw) {
                    $tableStats['unchanged']++;
                    $summary['unchanged']++;
                    if ($rowIndex !== null) {
                        $tableStats['rows'][$rowIndex]['status'] = 'unchanged';
                    }
                    continue;
                }

                if ($applyChanges) {
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
                        if ($rowIndex !== null) {
                            $tableStats['rows'][$rowIndex]['status'] = 'error';
                            $tableStats['rows'][$rowIndex]['error'] = $e->getMessage();
                        }
                        continue;
                    }
                }

                $tableStats['migrated']++;
                $summary['migrated']++;
                if ($rowIndex !== null) {
                    $tableStats['rows'][$rowIndex]['status'] = 'migrated';
                }
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
     *   issues:int,
     *   table_issues:int,
     *   column_issues:int,
     *   mixed_collation_groups:int,
     *   converted:int,
     *   unchanged:int,
     *   errors:int,
     *   tables:array<int,array{
     *     table:string,
     *     from:string,
     *     to:string,
     *     status:string,
     *     table_issues:int,
     *     column_issues:int,
     *     error:string
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    private static function repairTableCollations(DatabaseInterface $db): array
    {
        $targetCollation = EncodingAuditHelper::resolveTargetCollation($db);
        $summary = [
            'target_collation' => $targetCollation,
            'target_charset' => self::TARGET_CHARSET,
            'supported' => false,
            'scanned' => 0,
            'issues' => 0,
            'table_issues' => 0,
            'column_issues' => 0,
            'mixed_collation_groups' => 0,
            'converted' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'tables' => [],
            'warnings' => [],
        ];

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

        [$tableIssues, $columnIssues, $mixedTableCollations, $encodingErrors] =
            EncodingAuditHelper::inspect(
                $db,
                $tables,
                $db->getPrefix(),
                static fn(string $tableName, string $prefix): string => self::toAlias($tableName, $prefix)
            );

        if ($encodingErrors !== []) {
            $summary['warnings'] = array_merge($summary['warnings'], $encodingErrors);
            $summary['errors'] += count($encodingErrors);
        }

        $summary['table_issues'] = count($tableIssues);
        $summary['column_issues'] = count($columnIssues);
        $summary['mixed_collation_groups'] = max(0, count($mixedTableCollations) - 1);
        $summary['issues'] = $summary['table_issues'] + $summary['column_issues'] + $summary['mixed_collation_groups'];

        $issueTables = [];
        $columnIssueCounts = [];

        foreach ($tableIssues as $tableIssue) {
            if (!is_array($tableIssue)) {
                continue;
            }

            $alias = trim((string) ($tableIssue['table'] ?? ''));
            if ($alias !== '') {
                $issueTables[$alias] = true;
            }
        }

        foreach ($columnIssues as $columnIssue) {
            if (!is_array($columnIssue)) {
                continue;
            }

            $alias = trim((string) ($columnIssue['table'] ?? ''));
            if ($alias === '') {
                continue;
            }

            $issueTables[$alias] = true;
            $columnIssueCounts[$alias] = ($columnIssueCounts[$alias] ?? 0) + 1;
        }

        $prefix = $db->getPrefix();

        foreach ($tables as $tableName) {
            $currentCollation = (string) ($tableCollations[$tableName] ?? '');
            $tableAlias = self::toAlias($tableName, $prefix);
            $tableIssueCount = isset($issueTables[$tableAlias]) ? 1 : 0;
            $columnIssueCount = (int) ($columnIssueCounts[$tableAlias] ?? 0);

            $summary['scanned']++;

            if (
                $tableIssueCount === 0
                && $columnIssueCount === 0
                && $currentCollation !== ''
                && strcasecmp($currentCollation, $targetCollation) === 0
            ) {
                $summary['unchanged']++;
                $summary['tables'][] = [
                    'table' => $tableAlias,
                    'from' => $currentCollation,
                    'to' => $targetCollation,
                    'status' => 'unchanged',
                    'table_issues' => $tableIssueCount,
                    'column_issues' => $columnIssueCount,
                    'error' => '',
                ];
                continue;
            }

            try {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName($tableName)
                    . ' CONVERT TO CHARACTER SET ' . self::TARGET_CHARSET
                    . ' COLLATE ' . $targetCollation
                );
                $db->execute();
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['tables'][] = [
                    'table' => $tableAlias,
                    'from' => $currentCollation,
                    'to' => $targetCollation,
                    'status' => 'error',
                    'table_issues' => $tableIssueCount,
                    'column_issues' => $columnIssueCount,
                    'error' => $e->getMessage(),
                ];
                continue;
            }

            $summary['converted']++;
            $summary['tables'][] = [
                'table' => $tableAlias,
                'from' => $currentCollation,
                'to' => $targetCollation,
                'status' => 'converted',
                'table_issues' => $tableIssueCount,
                'column_issues' => $columnIssueCount,
                'error' => '',
            ];
        }

        return $summary;
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
