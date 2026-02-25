<?php

/**
 * ContentBuilder NG Storage Model.
 *
 * Handles CRUD and publish state for storage in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Model
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright (C) 2011–2026 by XDA+GIL
 * @license     GNU/GPL v2 or later
 * @link        https://breezingforms.vcmb.fr
 * @since       6.0.0  Joomla 6 compatibility rewrite.
 */


namespace CB\Component\Contentbuilder_ng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use CB\Component\Contentbuilder_ng\Administrator\Helper\Logger;
use CB\Component\Contentbuilder_ng\Administrator\Helper\VendorHelper;
use CB\Component\Contentbuilder_ng\Administrator\Service\DatatableService;

class StorageModel extends AdminModel
{
    /** @var object|null */
    protected ?object $oldItem = null;

    /** @var array{from:string,to:string}|null */
    private ?array $lastDataTableRename = null;

    /** Required for CSV file */
    private string $target_table = '';
    private int $storageId = 0;

    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        // IMPORTANT : on transmet factory/app/input à AdminModel
        parent::__construct($config, $factory);
        $this->option = 'com_contentbuilder_ng';
    }

    public function getTable($type = 'Storage', $prefix = 'Administrator', $config = [])
    {
        // Méthode moderne via MVCFactory du composant
        $table = $this->getMVCFactory()->createTable($type, $prefix, $config);

        // Fallback (déprécié mais utile en dépannage)
        // if (!$table) {
        //    $table = Table::getInstance($type, 'CB\\Component\\Contentbuilder_ng\\Administrator\\Table\\', $config);
        // }

        if (!$table) {
            throw new \RuntimeException(
                'Table introuvable: ' . $type . ' / ' . $prefix . ' (vérifie src/Table et le mapping <namespace> du manifest)',
                500
            );
        }

        return $table;
    }


    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            $this->option . '.storage',
            'storage',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        return $form ?: false;
    }

    protected function loadFormData()
    {
        $data = $this->getItem();
        $app  = Factory::getApplication();
        return $app->getUserState($this->option . '.edit.storage.data', $data);
    }

    protected function populateState()
    {
        parent::populateState();
        $id = (int) Factory::getApplication()->input->getInt('id', 0);
        $this->setState($this->getName() . '.id', $id);
    }

    public function addFieldFromRequest(int $storageId): bool
    {
        // Recharger l’item pour connaître name/bytable
        $storage = $this->getItem($storageId);
        if (!$storage) {
            return false;
        }

        // Pas d’ajout en mode bytable
        if (!empty($storage->bytable)) {
            return false;
        }

        // Lire le POST
        $input = Factory::getApplication()->input;
        $jform = $input->post->get('jform', [], 'array');

        $fieldname = trim((string) ($jform['fieldname'] ?? ''));
        if ($fieldname === '') {
            return false;
        }

        $fieldtitle = trim((string) ($jform['fieldtitle'] ?? ''));
        $isGroup    = (int) ($jform['is_group'] ?? 0);
        $groupDef   = (string) ($jform['group_definition'] ?? '');

        // Normalisation
        $newfieldname = preg_replace("/[^a-zA-Z0-9_\s]/isU", "_", $fieldname);
        $newfieldname = str_replace([' ', "\n", "\r", "\t"], ['_'], $newfieldname);
        $newfieldname = preg_replace("/^([0-9\s])/isU", "field$1$2", $newfieldname);
        $newfieldname = $newfieldname === '' ? ('field' . mt_rand(0, mt_getrandmax())) : $newfieldname;

        $newfieldtitle = $fieldtitle !== '' ? $fieldtitle : $newfieldname;

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Unicité
        $db->setQuery(
            "SELECT id FROM #__contentbuilder_ng_storage_fields
            WHERE storage_id = " . (int) $storageId . " AND `name` = " . $db->quote($newfieldname)
        );
        if ($db->loadResult()) {
            return false;
        }

        // Ordering max+1
        $db->setQuery(
            "SELECT COALESCE(MAX(ordering), 0) + 1
            FROM #__contentbuilder_ng_storage_fields
            WHERE storage_id = " . (int) $storageId
        );
        $max = (int) $db->loadResult();

        // Insert field
        $db->setQuery(
            "INSERT INTO #__contentbuilder_ng_storage_fields
            (ordering, storage_id, `name`, `title`, `is_group`, `group_definition`)
            VALUES (" . (int) $max . ", " . (int) $storageId . ", " . $db->quote($newfieldname) . ", " . $db->quote($newfieldtitle) . ", " . (int) $isGroup . ", " . $db->quote($groupDef) . ")"
        );
        $db->execute();

        // Assurer l’existence de la table data puis ajouter la colonne
        // (si ta table data existe toujours, tu peux garder juste l’ALTER)
        if (!empty($storage->name)) {
            try {
                $db->setQuery("ALTER TABLE `#__" . $storage->name . "` ADD `" . $newfieldname . "` TEXT NULL");
                $db->execute();
            } catch (\Throwable $e) {
                // Si la colonne existe déjà ou table absente, on log et on renvoie false
                Logger::exception($e);
                return false;
            }
        }

        // Optionnel: vider le champ dans la session / form
        return true;
    }


    /**
     * Normalisation name/title/bytable.
     */
    protected function prepareTable($table)
    {
        parent::prepareTable($table);

        // "bytable" is a flag in DB, but the form sends the table name.
        $jform = Factory::getApplication()->input->post->get('jform', [], 'array');
        $rawBytable = isset($jform['bytable']) ? trim((string) $jform['bytable']) : '';

        $bytable = (string) ($table->bytable ?? '');
        $name    = (string) ($table->name ?? '');
        $title   = (string) ($table->title ?? '');

        // forcing to lower
        $name = strtolower($name);

        // Resolve the actual bytable name from the raw form value when present.
        $bytableName = '';
        if ($rawBytable !== '' && $rawBytable !== '0' && $rawBytable !== '1') {
            $bytableName = $rawBytable;
        } elseif (!empty($bytable) && $bytable !== '0' && $bytable !== '1') {
            $bytableName = $bytable;
        }

        if ($bytableName !== '') {
            // External table chosen: store flag + keep the table name in "name".
            $table->bytable = 1;
            $table->name = $bytableName;
            $table->title = trim($title) !== '' ? trim($title) : $bytableName;
        } elseif (!empty($bytable)) {
            // Flag set without a table name in the request: keep current name.
            $table->bytable = 1;
            $table->name = $name;
            $table->title = trim($title) !== '' ? trim($title) : $name;
        } else {
            $table->bytable = 0;

            $newname = preg_replace("/[^a-zA-Z0-9_\s]/isU", "_", trim($name));
            $newname = str_replace([' ', "\n", "\r", "\t"], [''], $newname);
            $newname = preg_replace("/^([0-9\s])/isU", "field$1$2", $newname);
            $newname = $newname === '' ? ('field' . mt_rand(0, mt_getrandmax())) : $newname;

            $this->target_table = $newname; // csv helper si besoin
            $table->name  = $newname;
            $table->title = trim($title) !== '' ? trim($title) : $newname;
        }

        // Standard Joomla-style audit fields for storages.
        $now = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();
        $actor = trim((string) (($user->username ?? '') !== '' ? $user->username : ($user->name ?? '')));
        if ($actor === '') {
            $actor = 'system';
        }

        $isNew = (int) ($table->id ?? 0) === 0;
        if (
            $isNew &&
            (
                empty($table->created) ||
                str_starts_with((string) $table->created, '0000-00-00')
            )
        ) {
            $table->created = $now;
        }
        if ($isNew && trim((string) ($table->created_by ?? '')) === '') {
            $table->created_by = $actor;
        }

        $table->modified = $now;
        $table->modified_by = $actor;

        Logger::info('StorageModel prepareTable', [
            'name' => $table->name,
            'title' => $table->title,
            'bytable' => (int) $table->bytable,
        ]);
    }

    /**
     * Toute la logique legacy (table, champs, rename, sync...) APRES le save core
     */

    /*
    protected function postSaveHook(\Joomla\CMS\Table\Table $table, $validData = [])
    {
        parent::postSaveHook($table, $validData);

        $storageId = (int) $table->id;
        $isNew     = empty($validData['id']) || (int) $validData['id'] === 0;
        $bytable   = (int) ($table->bytable ?? 0);

        Logger::info('StorageModel postSaveHook', [
            'storageId' => $storageId,
            'isNew' => $isNew,
            'name' => $table->name ?? null,
            'bytable' => $bytable,
        ]);

        // 1) Créer/renommer la table de données (si !bytable) OU sync bytable (si bytable)
        $this->syncStorageDataTableOrBytable($storageId, $isNew, $table);

        // 2) Ajouter un nouveau champ si demandé (jform[fieldname], etc.)
        $this->maybeAddNewField($storageId, $bytable);

        // 3) Appliquer les modifs sur les champs existants (itemNames/itemTitles/itemIsGroup/itemGroupDefinitions)
        $this->syncEditedFields($storageId, $bytable, $table);

        // 4) Reorder
        try {
            $this->getTable('Storage')->reorder();
        } catch (\Throwable $e) {
            Logger::exception($e);
        }

        try {
            $fieldsTable = $this->getTable('StorageFields');
            $fieldsTable->reorder('storage_id = ' . (int) $storageId);
        } catch (\Throwable $e) {
            Logger::exception($e);
        }
    }*/



    /**
     * Ajout d’un nouveau champ si jform[fieldname] rempli (ancienne logique "case of new field")
     */
    private function maybeAddNewField(int $storageId, int $bytable): void
    {
        if ($bytable) {
            return;
        }

        $input = Factory::getApplication()->input;
        $jform = $input->post->get('jform', [], 'array');

        $fieldname = trim((string)($jform['fieldname'] ?? ''));
        if ($fieldname === '') {
            return;
        }

        $fieldtitle = trim((string)($jform['fieldtitle'] ?? ''));
        $isGroup    = (int)($jform['is_group'] ?? 0);
        $groupDef   = (string)($jform['group_definition'] ?? '');

        // Normalisation.
        $newfieldname = preg_replace("/[^a-zA-Z0-9_\s]/isU", "_", trim($fieldname));
        $newfieldname = str_replace([' ', "\n", "\r", "\t"], ['_'], $newfieldname);
        $newfieldname = preg_replace("/^([0-9\s])/isU", "field$1$2", $newfieldname);
        $newfieldname = $newfieldname === '' ? ('field' . mt_rand(0, mt_getrandmax())) : $newfieldname;

        $newfieldtitle = $fieldtitle !== '' ? $fieldtitle : $newfieldname;

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // ordering max+1
        $db->setQuery("SELECT COALESCE(MAX(ordering), 0) + 1 FROM #__contentbuilder_ng_storage_fields WHERE storage_id = " . (int) $storageId);
        $max = (int) $db->loadResult();

        // unicité
        $db->setQuery(
            "SELECT `name` FROM #__contentbuilder_ng_storage_fields WHERE `name` = " . $db->quote($newfieldname) .
            " AND storage_id = " . (int) $storageId
        );
        $exists = $db->loadResult();

        if ($exists) {
            Logger::info('maybeAddNewField skipped (exists)', ['storageId' => $storageId, 'field' => $newfieldname]);
            return;
        }

        Logger::info('maybeAddNewField insert', [
            'storageId' => $storageId,
            'name' => $newfieldname,
            'title' => $newfieldtitle,
            'is_group' => $isGroup,
        ]);

        $db->setQuery(
            "INSERT INTO #__contentbuilder_ng_storage_fields (ordering, storage_id, `name`, `title`, `is_group`, `group_definition`)
             VALUES (" . (int)$max . "," . (int)$storageId . "," . $db->quote($newfieldname) . "," . $db->quote($newfieldtitle) . "," . (int)$isGroup . "," . $db->quote($groupDef) . ")"
        );
        $db->execute();

        // Colonne dans table data (si table existe déjà)
        $storage = $this->getItem($storageId);
        if (!empty($storage->name)) {
            try {
                $db->setQuery("ALTER TABLE `#__" . $storage->name . "` ADD `" . $newfieldname . "` TEXT NULL");
                $db->execute();
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }
    }

    // Crée une table #__<storage.name> ou synchronise une table externe (bytable).
    public function ensureDataTable(int $storageId, bool $isNew = false, ?string $oldName = null): void
    {
        $this->lastDataTableRename = null;

        $table = $this->getTable('Storage');
        $table->load($storageId);

        $this->syncStorageDataTableOrBytable($storageId, $isNew, $table, $oldName);
    }

    /**
     * Returns info about the last successful data table rename in this request.
     *
     * @return array{from:string,to:string}|null
     */
    public function getLastDataTableRename(): ?array
    {
        return $this->lastDataTableRename;
    }


    /**
     * Crée/rename la table #__<storage.name> si !bytable
     * OU sync bytable => créer storage_fields depuis colonnes + ajouter colonnes system + import records si new
     */
    private function syncStorageDataTableOrBytable(int $storageId, bool $isNew, \Joomla\CMS\Table\Table $table, ?string $oldName = null): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $name   = (string) ($table->name ?? '');
        $bytable = (int) ($table->bytable ?? 0);

        $last_update = Factory::getDate()->toSql();

        // map table list => colonnes
        $tableList = $db->getTableList();
        $tables = array_combine(
            $tableList,
            array_map(static fn($t) => $db->getTableColumns($t, true), $tableList)
        );

        if (!$bytable) {
            // data table = #__<name>
            $prefixedName = $db->getPrefix() . $name;

            $exists = isset($tables[$prefixedName]);
            if ($oldName === null) {
                $oldName = $this->oldItem->name ?? null;
            }

            if (!$exists) {
                // rename si l'ancienne existait
                if (!empty($oldName) && $oldName !== $name) {
                    $oldPrefixed = $db->getPrefix() . $oldName;
                    if (isset($tables[$oldPrefixed])) {
                        Logger::info('Rename data table', ['from' => $oldName, 'to' => $name]);
                        $db->setQuery("RENAME TABLE `#__" . $oldName . "` TO `#__" . $name . "`");
                        $db->execute();
                        $this->lastDataTableRename = [
                            'from' => $oldName,
                            'to' => $name,
                        ];

                        try {
                            (new DatatableService())->ensureInternalAuditColumns($storageId);
                        } catch (\Throwable $e) {
                            Logger::exception($e);
                        }

                        return;
                    }
                }

                // create
                Logger::info('Create data table', ['name' => $name, 'storageId' => $storageId]);

                try {
                    $db->setQuery("
                        CREATE TABLE `#__{$name}` (
                            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            `storage_id` INT NOT NULL DEFAULT " . (int)$storageId . ",
                            `user_id` INT NOT NULL DEFAULT 0,
                            `created` DATETIME NOT NULL DEFAULT " . $db->quote($last_update) . ",
                            `created_by` VARCHAR(255) NOT NULL DEFAULT '',
                            `modified_user_id` INT NOT NULL DEFAULT 0,
                            `modified` DATETIME NULL DEFAULT NULL,
                            `modified_by` VARCHAR(255) NOT NULL DEFAULT ''
                        )
                    ");
                    $db->execute();

                    $db->setQuery("ALTER TABLE `#__{$name}` ADD INDEX (`storage_id`)");
                    $db->execute();
                    $db->setQuery("ALTER TABLE `#__{$name}` ADD INDEX (`user_id`)");
                    $db->execute();
                    $db->setQuery("ALTER TABLE `#__{$name}` ADD INDEX (`created`)");
                    $db->execute();
                    $db->setQuery("ALTER TABLE `#__{$name}` ADD INDEX (`modified_user_id`)");
                    $db->execute();
                    $db->setQuery("ALTER TABLE `#__{$name}` ADD INDEX (`modified`)");
                    $db->execute();
                } catch (\Throwable $e) {
                    Logger::exception($e);
                }
            }

            try {
                (new DatatableService())->ensureInternalAuditColumns($storageId);
            } catch (\Throwable $e) {
                Logger::exception($e);
            }

            return;
        }

        // BYTABLE = table externe (nom dans $name)
        if (!isset($tables[$name])) {
            // attention: getTableList() retourne souvent les noms déjà préfixés (selon driver)
            // on tente aussi avec prefix
            $name2 = $db->getPrefix() . $name;
            if (isset($tables[$name2])) {
                $name = $name2;
            } else {
                Logger::info('Bytable not found in database table list', ['bytable' => $name]);
                return;
            }
        }

        Logger::info('Sync bytable', ['bytable' => $name, 'storageId' => $storageId, 'isNew' => $isNew]);

        $system_fields = ['id', 'storage_id', 'user_id', 'created', 'created_by', 'modified_user_id', 'modified', 'modified_by'];

        $fields = $tables[$name]; // colonne => type
        $allfields = [];
        $fieldin = '';

        foreach ($fields as $field => $type) {
            $allfields[] = $field;
            $fieldin .= $db->quote($field) . ',';
        }
        $fieldin = rtrim($fieldin, ',');

        // ordering max+1
        $db->setQuery("SELECT COALESCE(MAX(ordering), 0) + 1 FROM #__contentbuilder_ng_storage_fields WHERE storage_id = " . (int) $storageId);
        $max = (int) $db->loadResult();

        if ($fieldin !== '') {
            $db->setQuery(
                "SELECT `name` FROM #__contentbuilder_ng_storage_fields
                 WHERE `name` IN ($fieldin) AND storage_id = " . (int) $storageId
            );
            $existingNames = $db->loadColumn() ?: [];

            foreach ($fields as $field => $type) {
                if (!in_array($field, $existingNames, true) && !in_array($field, $system_fields, true)) {
                    $db->setQuery(
                        "INSERT INTO #__contentbuilder_ng_storage_fields (ordering, storage_id, `name`, `title`, `is_group`, `group_definition`)
                         VALUES (" . (int)$max . "," . (int)$storageId . "," . $db->quote($field) . "," . $db->quote($field) . ",0,'')"
                    );
                    $db->execute();
                }
            }
        }

        // Ajouter colonnes system manquantes dans la table externe
        foreach ($system_fields as $missing) {
            if (in_array($missing, $allfields, true)) {
                continue;
            }

            try {
                switch ($missing) {
                    case 'id':
                        $db->setQuery("ALTER TABLE `{$name}` ADD `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
                        $db->execute();
                        break;

                    case 'storage_id':
                        $db->setQuery("ALTER TABLE `{$name}` ADD `storage_id` INT NOT NULL DEFAULT " . (int)$storageId . ", ADD INDEX (`storage_id`)");
                        $db->execute();
                        break;

                    case 'user_id':
                        $db->setQuery("ALTER TABLE `{$name}` ADD `user_id` INT NOT NULL DEFAULT 0, ADD INDEX (`user_id`)");
                        $db->execute();
                        break;

                    case 'created':
                        $db->setQuery("ALTER TABLE `{$name}` ADD `created` DATETIME NOT NULL DEFAULT " . $db->quote($last_update) . ", ADD INDEX (`created`)");
                        $db->execute();
                        break;

                    case 'created_by':
                        $db->setQuery("ALTER TABLE `{$name}` ADD `created_by` VARCHAR(255) NOT NULL DEFAULT ''");
                        $db->execute();
                        break;

                    case 'modified_user_id':
                        $db->setQuery("ALTER TABLE `{$name}` ADD `modified_user_id` INT NOT NULL DEFAULT 0, ADD INDEX (`modified_user_id`)");
                        $db->execute();
                        break;

                    case 'modified':
                        $db->setQuery("ALTER TABLE `{$name}` ADD `modified` DATETIME NULL DEFAULT NULL, ADD INDEX (`modified`)");
                        $db->execute();
                        break;

                    case 'modified_by':
                        $db->setQuery("ALTER TABLE `{$name}` ADD `modified_by` VARCHAR(255) NOT NULL DEFAULT ''");
                        $db->execute();
                        break;
                }
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        // Import records si nouveau storage
        if ($isNew) {
            try {
                // set default + set storage_id
                $db->setQuery("UPDATE `{$name}` SET `storage_id` = " . (int)$storageId);
                $db->execute();

                $db->setQuery("SELECT id FROM `{$name}`");
                $thirdPartyIds = $db->loadColumn() ?: [];

                foreach ($thirdPartyIds as $thirdPartyId) {
                    $db->setQuery(
                        "INSERT INTO #__contentbuilder_ng_records
                        (`type`, last_update, is_future, lang_code, sef, published, record_id, reference_id)
                        VALUES
                        ('com_contentbuilder_ng', " . $db->quote($last_update) . ", 0, '*', '', 1, " . (int)$thirdPartyId . ", " . (int)$storageId . ")"
                    );
                    $db->execute();
                }
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }
    }

    /**
     * Applique les modifications sur les fields existants (édition inline)
     * + rename colonnes si !bytable
     */
    private function syncEditedFields(int $storageId, int $bytable, \Joomla\CMS\Table\Table $storageTable): void
    {
        $input = Factory::getApplication()->input;
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $listnames  = $input->post->get('itemNames', [], 'array');
        if (empty($listnames)) {
            return;
        }

        $listtitles = $input->post->get('itemTitles', [], 'array');
        $listisgroup = $input->post->get('itemIsGroup', [], 'array');
        $listgroupdefinitions = $input->post->get('itemGroupDefinitions', [], 'array');

        Logger::info('syncEditedFields', [
            'storageId' => $storageId,
            'count' => count($listnames),
            'bytable' => $bytable,
        ]);

        foreach ($listnames as $field_id => $name) {
            $field_id = (int) $field_id;

            $name = preg_replace("/[^a-zA-Z0-9_\s]/isU", "_", trim((string)$name));
            $name = str_replace([' ', "\n", "\r", "\t"], [''], $name);
            $name = preg_replace("/^([0-9\s])/isU", "field$1$2", $name);
            $name = $name === '' ? ('field' . mt_rand(0, mt_getrandmax())) : $name;

            $title = trim((string)($listtitles[$field_id] ?? ''));
            $title = $title !== '' ? $title : $name;

            $isGroup = (int)($listisgroup[$field_id] ?? 0);
            $groupDef = (string)($listgroupdefinitions[$field_id] ?? '');

            if (!$bytable) {
                // old name
                $db->setQuery("SELECT `name` FROM #__contentbuilder_ng_storage_fields WHERE id = " . (int)$field_id);
                $old_name = (string) $db->loadResult();

                // update storage_fields
                $db->setQuery(
                    "UPDATE #__contentbuilder_ng_storage_fields
                     SET group_definition = " . $db->quote($groupDef) . ",
                         is_group = " . (int)$isGroup . ",
                         `name` = " . $db->quote($name) . ",
                         `title` = " . $db->quote($title) . "
                     WHERE id = " . (int)$field_id
                );
                $db->execute();

                // rename column if needed
                if ($old_name !== '' && $old_name !== $name) {
                    try {
                        $db->setQuery("ALTER TABLE `#__" . $storageTable->name . "` CHANGE `" . $old_name . "` `" . $name . "` TEXT");
                        $db->execute();
                    } catch (\Throwable $e) {
                        Logger::exception($e);
                    }
                }
            } else {
                // bytable => pas de rename colonne
                $db->setQuery(
                    "UPDATE #__contentbuilder_ng_storage_fields
                     SET group_definition = " . $db->quote($groupDef) . ",
                         is_group = " . (int)$isGroup . ",
                         `title` = " . $db->quote($title) . "
                     WHERE id = " . (int)$field_id
                );
                $db->execute();
            }
        }

        // Reorder fields
        try {
            $fieldsTable = $this->getTable('StorageFields');
            $fieldsTable->reorder('storage_id = ' . (int)$storageId);
        } catch (\Throwable $e) {
            Logger::exception($e);
        }

        // Synchroniser colonnes manquantes si !bytable
        if (!$bytable) {
            $this->ensureMissingColumnsFromFields($storageId, (string)$storageTable->name);
        }
    }

    /**
     * Applique les champs inline postés depuis la vue Storage (save/apply).
     */
    public function syncEditedFieldsFromRequest(int $storageId): void
    {
        if ($storageId < 1) {
            return;
        }

        $storageTable = $this->getTable('Storage');
        if (!$storageTable->load($storageId)) {
            return;
        }

        $bytable = (int) ($storageTable->bytable ?? 0);
        $this->syncEditedFields($storageId, $bytable, $storageTable);
    }

    /**
     * Ajoute les colonnes manquantes dans la table data (ancienne synch)
     */
    private function ensureMissingColumnsFromFields(int $storageId, string $dataTableName): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Liste des champs définis
        $db->setQuery("SELECT `name` FROM #__contentbuilder_ng_storage_fields WHERE storage_id = " . (int)$storageId);
        $fieldNames = $db->loadColumn() ?: [];

        // Colonnes existantes
        try {
            $cols = $db->getTableColumns($db->getPrefix() . $dataTableName, true);
        } catch (\Throwable $e) {
            Logger::exception($e);
            return;
        }

        foreach ($fieldNames as $fieldname) {
            if ($fieldname === '' || isset($cols[$fieldname])) {
                continue;
            }

            try {
                $db->setQuery("ALTER TABLE `#__{$dataTableName}` ADD `{$fieldname}` TEXT NULL");
                $db->execute();
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }
    }

     public function delete(&$pks)
    {
        $pks = (array) $pks;
        ArrayHelper::toInteger($pks);

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $row = $this->getTable('Storage');

        foreach ($pks as $pk) {
            $pk = (int)$pk;

            // charger storage
            $storage = $this->getItem($pk);

            $db->setQuery("DELETE FROM #__contentbuilder_ng_storage_fields WHERE storage_id = " . (int)$pk);
            $db->execute();

            try {
                $fieldsTable = $this->getTable('StorageFields');
                $fieldsTable->reorder('storage_id = ' . (int) $pk);
            } catch (\Throwable $e) {
                Logger::exception($e);
            }

            if (!$row->delete($pk)) {
                return false;
            }

            if ($storage && empty($storage->bytable) && !empty($storage->name)) {
                try {
                    $db->setQuery("DROP TABLE `#__" . $storage->name . "`");
                    $db->execute();
                } catch (\Throwable $e) {
                    Logger::exception($e);
                }
            }
        }

        try {
            $row->reorder();
        } catch (\Throwable $e) {
            Logger::exception($e);
        }

        return true;
    }


    function storeCsv($file, ?int $storageId = null)
    {
        $data = Factory::getApplication()->input->post->getArray();

        $resolvedStorageId = (int) ($storageId ?? 0);
        if ($resolvedStorageId <= 0) {
            $resolvedStorageId = (int) ($data['id'] ?? 0);
        }
        if ($resolvedStorageId <= 0) {
            $resolvedStorageId = (int) $this->getState($this->getName() . '.id', 0);
        }
        if ($resolvedStorageId <= 0) {
            return false;
        }

        $storage = $this->getItem($resolvedStorageId);
        if (!$storage) {
            return false;
        }
        if (!empty($storage->bytable)) {
            return false;
        }

        $this->storageId = $resolvedStorageId;
        $this->target_table = trim((string) ($storage->name ?? ''));
        if ($this->target_table === '') {
            return false;
        }

        $data['id'] = $this->storageId;
        if (isset($data['bytable'])) {
            unset($data['bytable']);
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $supportedExtensions = ['csv', 'xlsx', 'xls'];
        if (!in_array($extension, $supportedExtensions, true)) {
            return false;
        }

        $dest = JPATH_SITE . '/tmp/' . md5(mt_rand(0, mt_getrandmax())) . '_' . $file['name'];
        $uploaded = File::upload($file['tmp_name'], $dest, false, true);

        if (!$uploaded) {
            return false;
        }

        $sourceFile = $dest;
        $tmpFiles = [$dest];

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $delimiter = Factory::getApplication()->input->get('csv_delimiter', ',', 'string');
            $converted = $this->convertSpreadsheetFileToCsv($dest, $delimiter);
            if ($converted === null) {
                foreach ($tmpFiles as $tmpFile) {
                    if (is_file($tmpFile)) {
                        File::delete($tmpFile);
                    }
                }
                return false;
            }
            $sourceFile = $converted;
            $tmpFiles[] = $converted;
        }

        @ini_set('auto_detect_line_endings', TRUE);
        $retval = $this->csv_file_to_table($sourceFile, $data);

        foreach ($tmpFiles as $tmpFile) {
            if (is_file($tmpFile)) {
                File::delete($tmpFile);
            }
        }

        if (is_string($retval)) {
            return false;
        }

        return $retval ?: false;
    }

    private function convertSpreadsheetFileToCsv(string $sourceFile, string $delimiter = ','): ?string
    {
        try {
            VendorHelper::load();
        } catch (\Throwable $e) {
            Logger::exception($e);
            return null;
        }

        $spreadsheet = null;
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($sourceFile);
            $csvPath = JPATH_SITE . '/tmp/' . md5(mt_rand(0, mt_getrandmax())) . '_import.csv';

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $writer->setSheetIndex(0);
            $writer->setDelimiter($delimiter !== '' ? $delimiter : ',');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\n");
            $writer->setUseBOM(false);
            $writer->save($csvPath);

            return $csvPath;
        } catch (\Throwable $e) {
            Logger::exception($e);
            return null;
        } finally {
            if ($spreadsheet !== null) {
                $spreadsheet->disconnectWorksheets();
            }
        }
    }

    function utf8_fopen_read($fileName, $encoding)
    {
        $fc = iconv($encoding, 'UTF-8//TRANSLIT', file_get_contents($fileName));
        $handle = fopen("php://memory", "rw");
        fwrite($handle, $fc);
        fseek($handle, 0);
        return $handle;
    }


    function csv_file_to_table($source_file, $data, $max_line_length = 1000000)
    {

        $encoding = '';

        switch (Factory::getApplication()->input->get('csv_repair_encoding', '', 'string')) {
            case 'WINDOWS-1250':
            case 'WINDOWS-1251':
            case 'WINDOWS-1252':
            case 'WINDOWS-1253':
            case 'WINDOWS-1254':
            case 'WINDOWS-1255':
            case 'WINDOWS-1256':
            case 'ISO-8859-1':
            case 'ISO-8859-2':
            case 'ISO-8859-3':
            case 'ISO-8859-4':
            case 'ISO-8859-5':
            case 'ISO-8859-6':
            case 'ISO-8859-7':
            case 'ISO-8859-8':
            case 'ISO-8859-9':
            case 'ISO-8859-10':
            case 'ISO-8859-11':
            case 'ISO-8859-12':
            case 'ISO-8859-13':
            case 'ISO-8859-14':
            case 'ISO-8859-15':
            case 'ISO-8859-16':
            case 'UTF-8-MAC':
            case 'UTF-16':
            case 'UTF-16BE':
            case 'UTF-16LE':
            case 'UTF-32':
            case 'UTF-32BE':
            case 'UTF-32LE':
            case 'ASCII':
            case 'BIG-5':
            case 'HEBREW':
            case 'CYRILLIC':
            case 'ARABIC':
            case 'GREEK':
            case 'CHINESE':
            case 'KOREAN':
            case 'KOI8-R':
            case 'KOI8-U':
            case 'KOI8-RU':
            case 'EUC-JP':
                $encoding = Factory::getApplication()->input->get('csv_repair_encoding', '', 'string');
                break;
        }

        $handle = null;

        if ($encoding) {
            if (!function_exists('iconv')) {
                return Text::_('COM_CONTENTBUILDER_NG_CSV_IMPORT_REPAIR_NO_ICONV');
            }
            $handle = $this->utf8_fopen_read("$source_file", $encoding);
        } else {
            $handle = fopen("$source_file", "rb");
        }

        if ($handle !== FALSE) {

            $last_update = Factory::getDate()->toSql();

            $fieldnames = array();

            $columns = fgetcsv($handle, $max_line_length, Factory::getApplication()->input->get('csv_delimiter', ',', 'string'), '"');

            $colCheck = array();
            foreach ($columns as &$column) {
                $col = str_replace(".", "", trim($column));
                if (in_array($col, $colCheck)) {
                    return Text::_('COM_CONTENTBUILDER_NG_CSV_IMPORT_COLUMN_NOT_UNIQUE');
                }
                $colCheck[] = $col;
            }

            foreach ($columns as &$column) {
                $column = str_replace(".", "", trim($column));
                $data['fieldname'] = $column;
                $data['fieldtitle'] = $column;
                $data['is_group'] = false;
                $fieldnames[] = $this->store($data);
                $data['id'] = $this->storageId;
            }

            if (Factory::getApplication()->input->getBool('csv_drop_records', false)) {
                $this->getDatabase()->setQuery("Truncate Table #__" . $this->target_table);
                $this->getDatabase()->execute();
                $this->getDatabase()->setQuery("Delete From #__contentbuilder_ng_records Where `type` = 'com_contentbuilder_ng' And reference_id = " . $this->getDatabase()->quote($this->storageId));
                $this->getDatabase()->execute();
                $this->getDatabase()->setQuery("Delete a.*, c.* From #__contentbuilder_ng_articles As a, #__content As c Where c.id = a.article_id And a.`type` = 'com_contentbuilder_ng' And a.reference_id = " . $this->getDatabase()->quote($this->storageId));
                $this->getDatabase()->execute();
            }

            $insert_query_prefix = "INSERT INTO #__" . $this->target_table . " (" . join(",", $fieldnames) . ")\nVALUES";

            while (($data = fgetcsv($handle, $max_line_length, Factory::getApplication()->input->get('csv_delimiter', ',', 'string'), '"')) !== FALSE) {
                while (count($data) < count($columns))
                    array_push($data, NULL);
                $query = "$insert_query_prefix (" . join(", ", $this->quote_all_array($data)) . ")";
                $this->getDatabase()->setQuery($query);
                $this->getDatabase()->execute();
                $this->getDatabase()->setQuery("Insert Into #__contentbuilder_ng_records (`type`,last_update,is_future,lang_code, sef, published, record_id, reference_id) Values ('com_contentbuilder_ng'," . $this->getDatabase()->quote($last_update) . ",0,'*',''," . Factory::getApplication()->input->getInt('csv_published', 0) . ", " . $this->getDatabase()->quote(intval($this->getDatabase()->insertid())) . ", " . $this->getDatabase()->quote($this->storageId) . ")");
                $this->getDatabase()->execute();
            }
            fclose($handle);
        }
        return $this->storageId;
    }

    function quote_all_array($values)
    {
        foreach ($values as $key => $value)
            if (is_array($value))
                $values[$key] = $this->quote_all_array($value);
            else
                $values[$key] = $this->quote_all($value);
        return $values;
    }

    function quote_all($value)
    {
        if (is_null($value))
            return "''";

        $value = $this->getDatabase()->quote($value);
        return $value;
    }

    // Give the database tables list.
    function getDbTables()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tables = $db->getTableList();
        return $tables;
    }

}
