<?php

/**
 * Service servant à créer la table décrite par Storage.
 * @package     ContentBuilder NG
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;

class DatatableService
{
    /** Valide un identifiant SQL simple (table/col) */
    private function assertSafeIdentifier(string $value, string $label): string
    {
        $value = strtolower(trim($value));

        if ($value === '' || !preg_match('/^[a-z0-9_]+$/', $value)) {
            throw new \RuntimeException("$label invalide: " . $value);
        }

        return $value;
    }

    /** Retourne l’object storage (ou throw) */
    private function loadStorage(int $storageId): object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'name', 'bytable']))
            ->from($db->quoteName('#__contentbuilderng_storages'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $storageId, ParameterType::INTEGER);

        $db->setQuery($query);
        $storage = $db->loadObject();

        if (!$storage) {
            throw new \RuntimeException('Storage not found: ' . $storageId);
        }

        return $storage;
    }

    /** Test robuste d’existence de table (via getTableColumns) */
    private function tableExists(string $prefixedTableName): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $columns = $db->getTableColumns($prefixedTableName, true);

            return is_array($columns) && !empty($columns);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Retourne les colonnes déjà indexées (quel que soit le nom d’index). */
    private function getIndexedColumns(string $tableQN): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $indexedColumns = [];

        try {
            $db->setQuery('SHOW INDEX FROM ' . $tableQN);
            $rows = $db->loadAssocList() ?: [];

            foreach ($rows as $row) {
                $columnName = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));
                if ($columnName !== '') {
                    $indexedColumns[$columnName] = true;
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not inspect existing indexes', [
                'table' => $tableQN,
                'error' => $e->getMessage(),
            ]);
        }

        return $indexedColumns;
    }

    /**
     * Retourne la définition des index présents sur la table.
     *
     * @return array<string,array{non_unique:int,index_type:string,columns:array<int,array{name:string,sub_part:string,collation:string}>,signature:string}>
     */
    private function getTableIndexes(string $tableQN): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $indexMap = [];

        $db->setQuery('SHOW INDEX FROM ' . $tableQN);
        $rows = $db->loadAssocList() ?: [];

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
            $indexDefinition['signature'] = $this->indexDefinitionSignature($indexDefinition);
        }
        unset($indexDefinition);

        return $indexMap;
    }

    private function indexDefinitionSignature(array $indexDefinition): string
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

    private function removeDuplicateIndexes(string $tableQN, string $tableName, int $storageId): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $removed = 0;

        try {
            $tableIndexes = $this->getTableIndexes($tableQN);
        } catch (\Throwable $e) {
            Logger::warning('Could not inspect indexes for duplicate cleanup', [
                'table' => $tableName,
                'storageId' => $storageId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $signatures = [];

        foreach ($tableIndexes as $keyName => $indexDefinition) {
            if (strtoupper((string) $keyName) === 'PRIMARY') {
                continue;
            }

            $signature = (string) ($indexDefinition['signature'] ?? '');
            if ($signature === '') {
                continue;
            }

            $signatures[$signature][] = (string) $keyName;
        }

        foreach ($signatures as $duplicateNames) {
            if (count($duplicateNames) < 2) {
                continue;
            }

            usort(
                $duplicateNames,
                static fn(string $a, string $b): int => strcasecmp($a, $b)
            );

            $keptIndex = array_shift($duplicateNames);

            foreach ($duplicateNames as $duplicateName) {
                try {
                    $db->setQuery(
                        "ALTER TABLE $tableQN DROP INDEX " . $db->quoteName($duplicateName)
                    );
                    $db->execute();
                    $removed++;
                } catch (\Throwable $e) {
                    Logger::warning('Failed removing duplicate index', [
                        'table' => $tableName,
                        'storageId' => $storageId,
                        'keptIndex' => $keptIndex,
                        'dropIndex' => $duplicateName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($removed > 0) {
            Logger::info('Removed duplicate indexes on storage table', [
                'table' => $tableName,
                'storageId' => $storageId,
                'removed' => $removed,
            ]);
        }

        return $removed;
    }

    /**
     * Ensure standard audit columns + indexes exist for an internal storage data table.
     */
    public function ensureInternalAuditColumns(int $storageId): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $storage = $this->loadStorage($storageId);

        if ((int) $storage->bytable === 1) {
            // External tables are managed separately.
            return;
        }

        $name = $this->assertSafeIdentifier((string) $storage->name, 'Nom de table storage');
        $prefixed = $db->getPrefix() . $name;

        if (!$this->tableExists($prefixed)) {
            return;
        }

        $this->ensureInternalAuditColumnsAndIndexes($name, $storageId);
    }

    /**
     * Adds missing audit columns and indexes to the internal data table.
     */
    private function ensureInternalAuditColumnsAndIndexes(string $tableName, int $storageId): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $prefixed = $db->getPrefix() . $tableName;
        $tableQN = $db->quoteName('#__' . $tableName);
        $now = Factory::getDate()->toSql();

        $rawCols = $db->getTableColumns($prefixed, true);
        $cols = [];

        foreach ((array) $rawCols as $colName => $colDef) {
            $safeColName = strtolower((string) $colName);
            $cols[$safeColName] = $colDef;
        }

        $this->removeDuplicateIndexes($tableQN, '#__' . $tableName, $storageId);
        $indexedColumns = $this->getIndexedColumns($tableQN);

        $addColumn = static function (string $sql) use ($db): void {
            $db->setQuery($sql);
            $db->execute();
        };

        if (!isset($cols['id'])) {
            try {
                $addColumn(
                    "ALTER TABLE $tableQN ADD " . $db->quoteName('id') . " INT NOT NULL AUTO_INCREMENT PRIMARY KEY"
                );
                $cols['id'] = true;
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (!isset($cols['storage_id'])) {
            try {
                $addColumn(
                    "ALTER TABLE $tableQN ADD " . $db->quoteName('storage_id') . " INT NOT NULL DEFAULT " . (int) $storageId
                );
                $cols['storage_id'] = true;
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (!isset($cols['user_id'])) {
            try {
                $addColumn("ALTER TABLE $tableQN ADD " . $db->quoteName('user_id') . " INT NOT NULL DEFAULT 0");
                $cols['user_id'] = true;
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (!isset($cols['created'])) {
            try {
                $addColumn(
                    "ALTER TABLE $tableQN ADD " . $db->quoteName('created') . " DATETIME NOT NULL DEFAULT " . $db->quote($now)
                );
                $cols['created'] = true;
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (!isset($cols['created_by'])) {
            try {
                $addColumn("ALTER TABLE $tableQN ADD " . $db->quoteName('created_by') . " VARCHAR(255) NOT NULL DEFAULT ''");
                $cols['created_by'] = true;
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (!isset($cols['modified_user_id'])) {
            try {
                $addColumn("ALTER TABLE $tableQN ADD " . $db->quoteName('modified_user_id') . " INT NOT NULL DEFAULT 0");
                $cols['modified_user_id'] = true;
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (!isset($cols['modified'])) {
            try {
                $addColumn("ALTER TABLE $tableQN ADD " . $db->quoteName('modified') . " DATETIME NULL DEFAULT NULL");
                $cols['modified'] = true;
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (!isset($cols['modified_by'])) {
            try {
                $addColumn("ALTER TABLE $tableQN ADD " . $db->quoteName('modified_by') . " VARCHAR(255) NOT NULL DEFAULT ''");
                $cols['modified_by'] = true;
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        // Keep storage_id consistent for internal tables.
        if (isset($cols['storage_id'])) {
            try {
                $db->setQuery(
                    "UPDATE $tableQN SET " . $db->quoteName('storage_id') . " = " . (int) $storageId
                    . " WHERE " . $db->quoteName('storage_id') . " IS NULL OR " . $db->quoteName('storage_id') . " = 0"
                );
                $db->execute();
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (isset($cols['created_by'])) {
            try {
                $db->setQuery(
                    "UPDATE $tableQN SET " . $db->quoteName('created_by') . " = ''"
                    . " WHERE " . $db->quoteName('created_by') . " IS NULL"
                );
                $db->execute();
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        if (isset($cols['modified_by'])) {
            try {
                $db->setQuery(
                    "UPDATE $tableQN SET " . $db->quoteName('modified_by') . " = ''"
                    . " WHERE " . $db->quoteName('modified_by') . " IS NULL"
                );
                $db->execute();
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        foreach (['storage_id', 'user_id', 'created', 'modified_user_id', 'modified'] as $indexCol) {
            if (!isset($cols[$indexCol])) {
                continue;
            }
            if (isset($indexedColumns[$indexCol])) {
                continue;
            }

            try {
                $db->setQuery("ALTER TABLE $tableQN ADD INDEX (" . $db->quoteName($indexCol) . ")");
                $db->execute();
                $indexedColumns[$indexCol] = true;
            } catch (\Throwable $e) {
                // Ignore duplicate index errors; keep only unexpected ones in logs.
                $message = strtolower((string) $e->getMessage());
                if (strpos($message, 'too many keys') !== false) {
                    Logger::warning('Max index count reached, skipping remaining audit index additions', [
                        'table' => '#__' . $tableName,
                        'storageId' => $storageId,
                    ]);
                    break;
                }
                if (strpos($message, 'duplicate') === false && strpos($message, 'already exists') === false) {
                    Logger::exception($e);
                } else {
                    $indexedColumns[$indexCol] = true;
                }
            }
        }
    }

    public function createForStorage(int $storageId): bool
    {
        Logger::info("Demande de création de la table dont l'ID STORAGE vaut $storageId.");
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $storage = $this->loadStorage($storageId);

        if ((int) $storage->bytable === 1) {
            throw new \RuntimeException('bytable=1 : pas de datatable à créer (table externe).');
        }

        $name = $this->assertSafeIdentifier((string) $storage->name, 'Nom de table storage');

        $prefixed = $db->getPrefix() . $name;

        // Idempotent : si existe, on ne fait rien
        if ($this->tableExists($prefixed)) {
            $this->ensureInternalAuditColumns($storageId);
            Logger::info("La table '$prefixed' existe déjà dont l'ID STORAGE vaut $storageId.");
            return false;
        }

        $now = Factory::getDate()->toSql();

        // Create
        $sql = "
            CREATE TABLE " . $db->quoteName('#__' . $name) . " (
                " . $db->quoteName('id') . " INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                " . $db->quoteName('storage_id') . " INT NOT NULL DEFAULT " . (int) $storageId . ",
                " . $db->quoteName('user_id') . " INT NOT NULL DEFAULT 0,
                " . $db->quoteName('created') . " DATETIME NOT NULL DEFAULT " . $db->quote($now) . ",
                " . $db->quoteName('created_by') . " VARCHAR(255) NOT NULL DEFAULT '',
                " . $db->quoteName('modified_user_id') . " INT NOT NULL DEFAULT 0,
                " . $db->quoteName('modified') . " DATETIME NULL DEFAULT NULL,
                " . $db->quoteName('modified_by') . " VARCHAR(255) NOT NULL DEFAULT ''
            )
        ";

        $db->setQuery($sql);
        $db->execute();

        // Indexes (idempotence: MySQL ignore si déjà là ? non -> on les ajoute juste après la création)
        $tableQN = $db->quoteName('#__' . $name);

        $db->setQuery("ALTER TABLE $tableQN ADD INDEX (" . $db->quoteName('storage_id') . ")");
        $db->execute();
        $db->setQuery("ALTER TABLE $tableQN ADD INDEX (" . $db->quoteName('user_id') . ")");
        $db->execute();
        $db->setQuery("ALTER TABLE $tableQN ADD INDEX (" . $db->quoteName('created') . ")");
        $db->execute();
        $db->setQuery("ALTER TABLE $tableQN ADD INDEX (" . $db->quoteName('modified_user_id') . ")");
        $db->execute();
        $db->setQuery("ALTER TABLE $tableQN ADD INDEX (" . $db->quoteName('modified') . ")");
        $db->execute();

        $this->ensureInternalAuditColumns($storageId);

        return true;
    }

    /**
     * Ajoute les colonnes manquantes dans la table data à partir de #__contentbuilderng_storage_fields
     * Idempotent : ajoute seulement ce qui manque.
     */
    public function syncColumnsFromFields(int $storageId): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $storage = $this->loadStorage($storageId);

        if ((int) $storage->bytable === 1) {
            throw new \RuntimeException('bytable=1 : sync colonnes non applicable ici (table externe).');
        }

        $tableName = $this->assertSafeIdentifier((string) $storage->name, 'Nom de table Storage');
        $prefixed  = $db->getPrefix() . $tableName;

        if (!$this->tableExists($prefixed)) {
            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_DATATABLE_SYNC_INTERNAL_TABLE_MISSING',
                '#__' . $tableName
            );

            if ($message === 'COM_CONTENTBUILDERNG_DATATABLE_SYNC_INTERNAL_TABLE_MISSING') {
                $message = 'La table de données "#__' . $tableName . '" n\'existe pas encore. '
                    . 'Pour un storage interne, cliquez d\'abord sur Save pour la créer, puis relancez Sync Data Table.';
            }

            throw new \RuntimeException(
                $message
            );
        }

        $this->ensureInternalAuditColumns($storageId);

        // ✅ Colonnes système à ignorer
        $systemColumns = [
            'id', 'storage_id', 'user_id',
            'created', 'created_by',
            'modified_user_id', 'modified', 'modified_by'
        ];

        // ✅ Query Joomla standard
        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = :sid')
            ->bind(':sid', $storageId, ParameterType::INTEGER);

        $db->setQuery($query);
        $fieldNames = $db->loadColumn() ?: [];

        if (!$fieldNames) {
            return;
        }

        // Colonnes existantes
        $rawCols = $db->getTableColumns($prefixed, true);
        $cols = [];

        foreach ($rawCols as $colName => $colDef) {
            $safeColName = $this->assertSafeIdentifier((string) $colName, 'Nom de champ');
            $cols[$safeColName] = $colDef;
        }

        $tableQN = $db->quoteName('#__' . $tableName);

        foreach ($fieldNames as $field) {
            $field = $this->assertSafeIdentifier((string) $field, 'Nom de champ');

            // ✅ Ignore les colonnes système
            if (in_array($field, $systemColumns, true)) {
                continue;
            }

            // Déjà existante
            if (isset($cols[$field])) {
                continue;
            }

            $db->setQuery("ALTER TABLE $tableQN ADD " . $db->quoteName($field) . " TEXT NULL");
            $db->execute();
        }
    }
}
