<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
*/

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\FormDisplayColumnsHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

final class SchemaService
{
    public function __construct(
        private readonly \Closure $dbProvider,
        private readonly \Closure $logger,
        private readonly \Closure $safeRunner,
    ) {
    }

    public function updateDateColumns(): void
    {
        $db = $this->db();

        $queries = [
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_forms') . ' MODIFY ' . $db->quoteName('created') . ' DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_forms'))->set($db->quoteName('created') . ' = NULL')->where($db->quoteName('created') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_forms') . ' MODIFY ' . $db->quoteName('modified') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_forms'))->set($db->quoteName('modified') . ' = NULL')->where($db->quoteName('modified') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_forms') . ' MODIFY ' . $db->quoteName('last_update') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_forms'))->set($db->quoteName('last_update') . ' = NULL')->where($db->quoteName('last_update') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_forms') . ' MODIFY ' . $db->quoteName('rand_date_update') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_forms'))->set($db->quoteName('rand_date_update') . ' = NULL')->where($db->quoteName('rand_date_update') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_records') . ' MODIFY ' . $db->quoteName('publish_up') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_records'))->set($db->quoteName('publish_up') . ' = NULL')->where($db->quoteName('publish_up') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_records') . ' MODIFY ' . $db->quoteName('publish_down') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_records'))->set($db->quoteName('publish_down') . ' = NULL')->where($db->quoteName('publish_down') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_records') . ' MODIFY ' . $db->quoteName('last_update') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_records'))->set($db->quoteName('last_update') . ' = NULL')->where($db->quoteName('last_update') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_records') . ' MODIFY ' . $db->quoteName('rand_date') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_records'))->set($db->quoteName('rand_date') . ' = NULL')->where($db->quoteName('rand_date') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_articles') . ' MODIFY ' . $db->quoteName('last_update') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_articles'))->set($db->quoteName('last_update') . ' = NULL')->where($db->quoteName('last_update') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_users') . ' MODIFY ' . $db->quoteName('verification_date_view') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_users'))->set($db->quoteName('verification_date_view') . ' = NULL')->where($db->quoteName('verification_date_view') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_users') . ' MODIFY ' . $db->quoteName('verification_date_new') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_users'))->set($db->quoteName('verification_date_new') . ' = NULL')->where($db->quoteName('verification_date_new') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_users') . ' MODIFY ' . $db->quoteName('verification_date_edit') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_users'))->set($db->quoteName('verification_date_edit') . ' = NULL')->where($db->quoteName('verification_date_edit') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_rating_cache') . ' MODIFY COLUMN ' . $db->quoteName('date') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_rating_cache'))->set($db->quoteName('date') . ' = NULL')->where($db->quoteName('date') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_verifications') . ' MODIFY ' . $db->quoteName('start_date') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_verifications'))->set($db->quoteName('start_date') . ' = NULL')->where($db->quoteName('start_date') . ' = ' . $db->quote('0000-00-00')),
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_verifications') . ' MODIFY ' . $db->quoteName('verification_date') . ' DATETIME NULL DEFAULT NULL',
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_verifications'))->set($db->quoteName('verification_date') . ' = NULL')->where($db->quoteName('verification_date') . ' = ' . $db->quote('0000-00-00')),
        ];

        foreach ($queries as $sql) {
            try {
                $db->setQuery($sql);
                $db->execute();
            } catch (\Throwable $e) {
                $this->log('[WARNING] Could not alter date column: ' . $e->getMessage() . '.', Log::WARNING);
            }
        }

        $this->migrateStoragesAuditColumns();
        $this->migrateInternalStorageDataTablesAuditColumns();

        $this->log('[OK] Date fields updated to support NULL correctly, if necessary.');
    }

    public function ensureFormsDisplayColumns(): void
    {
        $db = $this->db();

        try {
            $cols = $db->getTableColumns('#__contentbuilderng_forms', false);

            if (!is_array($cols)) {
                return;
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect #__contentbuilderng_forms columns: ' . $e->getMessage(), Log::WARNING);

            return;
        }

        $requiredColumns = FormDisplayColumnsHelper::requiredColumns();
        $knownColumns = [];

        foreach ((array) $cols as $columnName => $_definition) {
            $knownColumns[strtolower((string) $columnName)] = true;
        }

        $added = [];

        foreach ($requiredColumns as $columnName => $definition) {
            if (isset($knownColumns[$columnName])) {
                continue;
            }

            try {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_forms')
                    . ' ADD COLUMN ' . $db->quoteName($columnName) . ' ' . $definition
                );
                $db->execute();
                $added[] = $columnName;
                $this->log('[OK] Added #__contentbuilderng_forms.' . $columnName . ' column.');
            } catch (\Throwable $e) {
                $this->log('[WARNING] Failed adding #__contentbuilderng_forms.' . $columnName . ' column: ' . $e->getMessage(), Log::WARNING);
            }
        }

        if ($added === []) {
            $this->log('[OK] #__contentbuilderng_forms already contains all display columns.');
        }
    }

    public function ensureElementsLinkableDefault(): void
    {
        $db = $this->db();

        try {
            $cols = $db->getTableColumns('#__contentbuilderng_elements', false);

            if (!is_array($cols) || !array_key_exists('linkable', $cols)) {
                return;
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect #__contentbuilderng_elements columns: ' . $e->getMessage(), Log::WARNING);

            return;
        }

        try {
            $db->setQuery(
                'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_elements')
                . ' MODIFY ' . $db->quoteName('linkable') . " TINYINT(1) NOT NULL DEFAULT '0'"
            );
            $db->execute();
            $this->log('[OK] Ensured #__contentbuilderng_elements.linkable default is 0.');
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed to set #__contentbuilderng_elements.linkable default: ' . $e->getMessage(), Log::WARNING);
        }
    }

    public function normalizeStoragesOrdering(): void
    {
        $db = $this->db();

        if (!$this->tableExists('contentbuilderng_storages')) {
            return;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__contentbuilderng_storages'))
                    ->where($db->quoteName('ordering') . ' = 0')
            );
            $needFix = (int) $db->loadResult();

            if ($needFix <= 0) {
                return;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0)')
                    ->from($db->quoteName('#__contentbuilderng_storages'))
            );
            $max = (int) $db->loadResult();

            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__contentbuilderng_storages'))
                    ->where($db->quoteName('ordering') . ' = 0')
                    ->order($db->quoteName('id') . ' ASC')
            );
            $ids = $db->loadColumn() ?: [];

            $order = $max;

            foreach ($ids as $id) {
                $order++;
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__contentbuilderng_storages'))
                        ->set($db->quoteName('ordering') . ' = ' . (int) $order)
                        ->where($db->quoteName('id') . ' = ' . (int) $id)
                );
                $db->execute();
            }

            $this->log("[OK] Normalized storages ordering for {$needFix} row(s).");
        } catch (\Throwable $e) {
            $this->log('[ERROR] Failed to normalize storages ordering: ' . $e->getMessage(), Log::ERROR);
        }
    }

    private function storageAuditColumnDefinition(string $column): string
    {
        return match ($column) {
            'created' => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
            'modified' => 'DATETIME NULL DEFAULT NULL',
            'created_by' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'modified_by' => "VARCHAR(255) NOT NULL DEFAULT ''",
            default => 'TEXT NULL',
        };
    }

    private function getStoragesTableColumnsLower(): array
    {
        $db = $this->db();

        try {
            $columns = $db->getTableColumns('#__contentbuilderng_storages', false);

            return array_change_key_case($columns ?: [], CASE_LOWER);
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect #__contentbuilderng_storages columns: ' . $e->getMessage(), Log::WARNING);

            return [];
        }
    }

    private function migrateStoragesAuditColumns(): void
    {
        $db = $this->db();
        $columns = $this->getStoragesTableColumnsLower();

        if ($columns === []) {
            return;
        }

        $legacyToStandard = [
            'last_update' => 'modified',
            'last_updated' => 'modified',
            'createdby' => 'created_by',
            'modifiedby' => 'modified_by',
            'updated_by' => 'modified_by',
        ];

        foreach ($legacyToStandard as $legacy => $target) {
            if (!array_key_exists($legacy, $columns)) {
                continue;
            }

            $targetDef = $this->storageAuditColumnDefinition($target);

            if (!array_key_exists($target, $columns)) {
                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages')
                        . ' CHANGE ' . $db->quoteName($legacy) . ' ' . $db->quoteName($target) . ' ' . $targetDef
                    );
                    $db->execute();
                    $this->log("[OK] Renamed storage audit column {$legacy} to {$target}.");
                    $columns = $this->getStoragesTableColumnsLower();
                    continue;
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Failed renaming storage audit column {$legacy} to {$target}: " . $e->getMessage(), Log::WARNING);
                }
            }

            try {
                if ($target === 'modified' || $target === 'created') {
                    $db->setQuery(
                        'UPDATE ' . $db->quoteName('#__contentbuilderng_storages')
                        . ' SET ' . $db->quoteName($target) . ' = ' . $db->quoteName($legacy)
                        . ' WHERE (' . $db->quoteName($target) . ' IS NULL OR ' . $db->quoteName($target) . " IN ('0000-00-00', '0000-00-00 00:00:00'))"
                        . ' AND ' . $db->quoteName($legacy) . ' IS NOT NULL'
                        . ' AND ' . $db->quoteName($legacy) . " NOT IN ('0000-00-00', '0000-00-00 00:00:00')"
                    );
                    $db->execute();
                } else {
                    $db->setQuery(
                        'UPDATE ' . $db->quoteName('#__contentbuilderng_storages')
                        . ' SET ' . $db->quoteName($target) . ' = ' . $db->quoteName($legacy)
                        . ' WHERE (' . $db->quoteName($target) . " = '' OR " . $db->quoteName($target) . ' IS NULL)'
                        . ' AND ' . $db->quoteName($legacy) . ' IS NOT NULL'
                        . ' AND ' . $db->quoteName($legacy) . " <> ''"
                    );
                    $db->execute();
                }
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed copying data from {$legacy} to {$target}: " . $e->getMessage(), Log::WARNING);
            }

            if ($legacy !== $target) {
                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages')
                        . ' DROP COLUMN ' . $db->quoteName($legacy)
                    );
                    $db->execute();
                    $this->log("[OK] Removed legacy storage audit column {$legacy}.");
                    $columns = $this->getStoragesTableColumnsLower();
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Failed removing legacy storage audit column {$legacy}: " . $e->getMessage(), Log::WARNING);
                }
            }
        }

        foreach (['created', 'modified', 'created_by', 'modified_by'] as $column) {
            if (array_key_exists($column, $columns)) {
                continue;
            }

            try {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages')
                    . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $this->storageAuditColumnDefinition($column)
                );
                $db->execute();
                $this->log("[OK] Added storage audit column {$column}.");
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed adding storage audit column {$column}: " . $e->getMessage(), Log::WARNING);
            }
        }

        $normalize = [
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages') . ' MODIFY ' . $db->quoteName('created') . ' DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages') . ' MODIFY ' . $db->quoteName('modified') . ' DATETIME NULL DEFAULT NULL',
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages') . ' MODIFY ' . $db->quoteName('created_by') . " VARCHAR(255) NOT NULL DEFAULT ''",
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages') . ' MODIFY ' . $db->quoteName('modified_by') . " VARCHAR(255) NOT NULL DEFAULT ''",
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_storages'))->set($db->quoteName('created') . ' = NULL')->where($db->quoteName('created') . ' IN (' . $db->quote('0000-00-00') . ', ' . $db->quote('0000-00-00 00:00:00') . ')'),
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_storages'))->set($db->quoteName('modified') . ' = NULL')->where($db->quoteName('modified') . ' IN (' . $db->quote('0000-00-00') . ', ' . $db->quote('0000-00-00 00:00:00') . ')'),
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_storages'))->set($db->quoteName('created_by') . " = ''")->where($db->quoteName('created_by') . ' IS NULL'),
            $db->getQuery(true)->update($db->quoteName('#__contentbuilderng_storages'))->set($db->quoteName('modified_by') . " = ''")->where($db->quoteName('modified_by') . ' IS NULL'),
        ];

        foreach ($normalize as $sql) {
            $this->safe(function () use ($db, $sql): void {
                $db->setQuery($sql);
                $db->execute();
            });
        }
    }

    private function getIndexedColumns(DatabaseInterface $db, string $tableQN): array
    {
        $indexed = [];

        try {
            $db->setQuery('SHOW INDEX FROM ' . $tableQN);
            $rows = $db->loadAssocList() ?: [];

            foreach ($rows as $row) {
                $column = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));

                if ($column !== '') {
                    $indexed[$column] = true;
                }
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect existing indexes on ' . $tableQN . ': ' . $e->getMessage(), Log::WARNING);
        }

        return $indexed;
    }

    private function getTableIndexes(DatabaseInterface $db, string $tableQN): array
    {
        $indexMap = [];

        $db->setQuery('SHOW INDEX FROM ' . $tableQN);
        $rows = $db->loadAssocList() ?: [];

        foreach ($rows as $row) {
            $keyName = (string) ($row['Key_name'] ?? $row['key_name'] ?? '');

            if ($keyName === '') {
                continue;
            }

            $seq = (int) ($row['Seq_in_index'] ?? $row['seq_in_index'] ?? 0);

            if ($seq < 1) {
                $seq = count($indexMap[$keyName]['columns'] ?? []) + 1;
            }

            $column = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));

            if ($column === '') {
                $column = strtolower(trim((string) ($row['Expression'] ?? $row['expression'] ?? '')));
            }

            if ($column === '') {
                continue;
            }

            if (!isset($indexMap[$keyName])) {
                $indexMap[$keyName] = [
                    'non_unique' => (int) ($row['Non_unique'] ?? $row['non_unique'] ?? 1),
                    'index_type' => strtoupper((string) ($row['Index_type'] ?? $row['index_type'] ?? 'BTREE')),
                    'columns' => [],
                ];
            }

            $indexMap[$keyName]['columns'][$seq] = [
                'name' => $column,
                'sub_part' => (string) ($row['Sub_part'] ?? $row['sub_part'] ?? ''),
                'collation' => strtoupper((string) ($row['Collation'] ?? $row['collation'] ?? 'A')),
            ];
        }

        foreach ($indexMap as &$definition) {
            ksort($definition['columns'], SORT_NUMERIC);
            $definition['columns'] = array_values($definition['columns']);
            $definition['signature'] = $this->indexDefinitionSignature($definition);
        }
        unset($definition);

        return $indexMap;
    }

    private function indexDefinitionSignature(array $indexDefinition): string
    {
        $parts = [];

        foreach ((array) ($indexDefinition['columns'] ?? []) as $columnDefinition) {
            $parts[] = implode(':', [
                (string) ($columnDefinition['name'] ?? ''),
                (string) ($columnDefinition['sub_part'] ?? ''),
                (string) ($columnDefinition['collation'] ?? ''),
            ]);
        }

        return implode('|', [
            (string) ($indexDefinition['non_unique'] ?? 1),
            strtoupper((string) ($indexDefinition['index_type'] ?? 'BTREE')),
            implode(',', $parts),
        ]);
    }

    private function removeDuplicateIndexes(DatabaseInterface $db, string $tableQN, string $tableAlias, int $storageId = 0): int
    {
        $removed = 0;
        $tableIndexes = $this->safe(fn() => $this->getTableIndexes($db, $tableQN), []);

        if (!$tableIndexes) {
            return 0;
        }

        $signatures = [];

        foreach ($tableIndexes as $keyName => $definition) {
            if (strtoupper((string) $keyName) === 'PRIMARY') {
                continue;
            }

            $signature = (string) ($definition['signature'] ?? '');

            if ($signature !== '') {
                $signatures[$signature][] = (string) $keyName;
            }
        }

        foreach ($signatures as $names) {
            if (count($names) < 2) {
                continue;
            }

            usort($names, static fn($left, $right) => strcasecmp($left, $right));
            $kept = array_shift($names);

            foreach ($names as $duplicate) {
                try {
                    $db->setQuery('ALTER TABLE ' . $tableQN . ' DROP INDEX ' . $db->quoteName($duplicate));
                    $db->execute();
                    $removed++;
                    $suffix = $storageId > 0 ? " (storage {$storageId})" : '';
                    $this->log("[OK] Removed duplicate index {$duplicate} on {$tableAlias}{$suffix}; kept {$kept}.");
                } catch (\Throwable $e) {
                    $suffix = $storageId > 0 ? " (storage {$storageId})" : '';
                    $this->log("[WARNING] Failed removing duplicate index {$duplicate} on {$tableAlias}{$suffix}: " . $e->getMessage(), Log::WARNING);
                }
            }
        }

        return $removed;
    }

    private function migrateInternalStorageDataTablesAuditColumns(): void
    {
        $db = $this->db();
        $now = Factory::getDate()->toSql();

        $storages = $this->safe(function () use ($db) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name']))
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where('(' . $db->quoteName('bytable') . ' = 0 OR ' . $db->quoteName('bytable') . ' IS NULL)')
                ->where($db->quoteName('name') . " <> ''");
            $db->setQuery($query);

            return $db->loadAssocList() ?: [];
        }, []);

        if ($storages === []) {
            return;
        }

        $processed = 0;
        $updated = 0;
        $missingTables = 0;
        $duplicatesRemoved = 0;

        foreach ($storages as $storage) {
            $processed++;
            $storageId = (int) ($storage['id'] ?? 0);
            $name = strtolower(trim((string) ($storage['name'] ?? '')));

            if ($storageId < 1 || $name === '' || !preg_match('/^[a-z0-9_]+$/', $name)) {
                continue;
            }

            $tableAlias = '#__' . $name;
            $tableQN = $db->quoteName($tableAlias);

            try {
                $columns = $db->getTableColumns($tableAlias, false);
            } catch (\Throwable $e) {
                $message = strtolower((string) $e->getMessage());

                if (strpos($message, "doesn't exist") !== false || strpos($message, 'does not exist') !== false) {
                    $this->log("[INFO] Data table {$tableAlias} (storage {$storageId}) is missing; skipping.", Log::INFO);
                } else {
                    $this->log("[WARNING] Could not inspect data table {$tableAlias} (storage {$storageId}): " . $e->getMessage(), Log::WARNING);
                }

                $missingTables++;
                continue;
            }

            if (empty($columns) || !is_array($columns)) {
                $missingTables++;
                continue;
            }

            $columnsLower = array_change_key_case($columns, CASE_LOWER);
            $tableChanged = false;

            $removedDuplicateIndexes = $this->removeDuplicateIndexes($db, $tableQN, $tableAlias, $storageId);

            if ($removedDuplicateIndexes > 0) {
                $duplicatesRemoved += $removedDuplicateIndexes;
                $tableChanged = true;
            }

            $indexedColumns = $this->getIndexedColumns($db, $tableQN);
            $requiredColumns = [
                'id' => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
                'storage_id' => 'INT NOT NULL DEFAULT ' . $storageId,
                'user_id' => 'INT NOT NULL DEFAULT 0',
                'created' => 'DATETIME NOT NULL DEFAULT ' . $db->quote($now),
                'created_by' => "VARCHAR(255) NOT NULL DEFAULT ''",
                'modified_user_id' => 'INT NOT NULL DEFAULT 0',
                'modified' => 'DATETIME NULL DEFAULT NULL',
                'modified_by' => "VARCHAR(255) NOT NULL DEFAULT ''",
            ];

            foreach ($requiredColumns as $column => $definition) {
                if (array_key_exists($column, $columnsLower)) {
                    continue;
                }

                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $tableQN
                        . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $definition
                    );
                    $db->execute();
                    $columnsLower[$column] = true;
                    $tableChanged = true;
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Failed adding audit column {$column} on {$tableAlias} (storage {$storageId}): " . $e->getMessage(), Log::WARNING);
                }
            }

            if (array_key_exists('storage_id', $columnsLower)) {
                $this->safe(function () use ($db, $tableQN, $storageId): void {
                    $db->setQuery(
                        'UPDATE ' . $tableQN
                        . ' SET ' . $db->quoteName('storage_id') . ' = ' . $storageId
                        . ' WHERE ' . $db->quoteName('storage_id') . ' IS NULL OR ' . $db->quoteName('storage_id') . ' = 0'
                    );
                    $db->execute();
                });
            }

            foreach (['created_by', 'modified_by'] as $actorColumn) {
                if (!array_key_exists($actorColumn, $columnsLower)) {
                    continue;
                }

                $this->safe(function () use ($db, $tableQN, $actorColumn): void {
                    $db->setQuery(
                        'UPDATE ' . $tableQN
                        . ' SET ' . $db->quoteName($actorColumn) . " = ''"
                        . ' WHERE ' . $db->quoteName($actorColumn) . ' IS NULL'
                    );
                    $db->execute();
                });
            }

            foreach (['storage_id', 'user_id', 'created', 'modified_user_id', 'modified'] as $indexColumn) {
                if (!array_key_exists($indexColumn, $columnsLower) || isset($indexedColumns[$indexColumn])) {
                    continue;
                }

                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $tableQN
                        . ' ADD INDEX (' . $db->quoteName($indexColumn) . ')'
                    );
                    $db->execute();
                    $indexedColumns[$indexColumn] = true;
                    $tableChanged = true;
                } catch (\Throwable $e) {
                    $message = strtolower((string) $e->getMessage());

                    if (strpos($message, 'too many keys') !== false) {
                        $this->log("[WARNING] Max index count reached on {$tableAlias} (storage {$storageId}); skipping remaining index additions.", Log::WARNING);
                        break;
                    }
                }
            }

            if ($tableChanged) {
                $updated++;
            }
        }

        $this->log("[OK] Internal storage audit migration complete. Processed: {$processed}, updated: {$updated}, missing tables: {$missingTables}, duplicate indexes removed: {$duplicatesRemoved}.");
    }

    private function tableExists(string $tableNoPrefix): bool
    {
        $db = $this->db();
        $tables = $this->safe(fn() => $db->getTableList(), []);
        $expected = $db->getPrefix() . ltrim($tableNoPrefix, '#__');

        return is_array($tables) && in_array($expected, $tables, true);
    }

    private function db(): DatabaseInterface
    {
        return ($this->dbProvider)();
    }

    private function log(string $message, int $priority = Log::INFO): void
    {
        ($this->logger)($message, $priority);
    }

    private function safe(callable $callback, mixed $fallback = null): mixed
    {
        return ($this->safeRunner)($callback, $fallback);
    }
}
