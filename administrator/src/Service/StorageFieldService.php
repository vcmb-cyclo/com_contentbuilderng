<?php
namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;

class StorageFieldService
{
    public function addField(int $storageId, array $fieldData): void
    {
        Logger::info("Demande d'ajout du champ $fieldData dont la table a un storageId $storageId.");

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // 1) Charger le storage
        $db->setQuery(
            'SELECT id, name, bytable FROM #__contentbuilderng_storages WHERE id = ' . (int) $storageId
        );
        $storage = $db->loadObject();

        if (!$storage) {
            throw new \RuntimeException('Storage not found: ' . $storageId);
        }

        if ((int) $storage->bytable === 1) {
            throw new \RuntimeException('bytable=1 : ajoute les champs via la synch bytable, pas via addField');
        }

        $storageName = trim((string) $storage->name);
        if ($storageName === '') {
            throw new \RuntimeException('Storage name vide : impossible de créer la colonne');
        }

        // 2) Normalize the field name using the established storage format
        $rawName = trim((string) ($fieldData['name'] ?? ''));
        $name = preg_replace("/[^a-zA-Z0-9_\s]/isU", "_", $rawName);
        $name = str_replace([' ', "\n", "\r", "\t"], ['_'], $name);
        $name = preg_replace("/^([0-9\s])/isU", "field$1$2", $name);
        $name = $name === '' ? ('field' . mt_rand(0, mt_getrandmax())) : $name;

        $title = trim((string) ($fieldData['title'] ?? ''));
        $title = ($title !== '') ? $title : $name;

        $isGroup  = (int) ($fieldData['is_group'] ?? 0);
        $groupDef = (string) ($fieldData['group_definition'] ?? '');

        // 3) Idempotent : si le champ existe déjà dans storage_fields -> stop
        $db->setQuery(
            'SELECT id FROM #__contentbuilderng_storage_fields
             WHERE storage_id = ' . (int) $storageId . '
               AND name = ' . $db->quote($name)
        );
        $existingId = (int) $db->loadResult();

        if ($existingId > 0) {
            return;
        }

        // 4) Calcul ordering (max+1)
        $db->setQuery(
            'SELECT COALESCE(MAX(ordering), 0) + 1
             FROM #__contentbuilderng_storage_fields
             WHERE storage_id = ' . (int) $storageId
        );
        $ordering = (int) $db->loadResult();

        // 5) Insert storage_fields
        $db->setQuery(
            'INSERT INTO #__contentbuilderng_storage_fields
             (ordering, storage_id, name, title, is_group, group_definition, published)
             VALUES (
                ' . (int) $ordering . ',
                ' . (int) $storageId . ',
                ' . $db->quote($name) . ',
                ' . $db->quote($title) . ',
                ' . (int) $isGroup . ',
                ' . $db->quote($groupDef) . ',
                1
             )'
        );
        $db->execute();

        // 6) Ajouter la colonne dans la table data, UNIQUEMENT si la table existe
        $prefixedTable = $db->getPrefix() . $storageName;
        $tables = $db->getTableList();

        if (!in_array($prefixedTable, $tables, true)) {
            // Table pas encore créée : on laisse le champ en metadata,
            // et on pourra créer les colonnes plus tard (via une commande "sync" si tu veux).
            return;
        }

        // 7) Idempotent : si la colonne existe déjà -> stop
        $cols = $db->getTableColumns($prefixedTable, true);
        if (isset($cols[$name])) {
            return;
        }

        // 8) ALTER TABLE (TEXT NULL column, matching the established storage format)
        $db->setQuery('ALTER TABLE `#__' . $storageName . '` ADD `' . $name . '` TEXT NULL');
        $db->execute();
    }
}
