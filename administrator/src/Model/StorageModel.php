<?php

/**
 * ContentBuilder NG Storage Model.
 *
 * Handles CRUD and publish state for storage in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Model
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 */


namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Helper\VendorHelper;
use CB\Component\Contentbuilderng\Administrator\Service\DatatableService;

class StorageModel extends AdminModel
{
    /** @var object|null */
    protected ?object $oldItem = null;

    /** @var array{from:string,to:string}|null */
    private ?array $lastDataTableRename = null;

    /** Required for CSV file */
    private string $target_table = '';
    private int $storageId = 0;
    /** @var array<string,mixed> */
    private array $lastImportSummary = [];

    private function getApp(): CMSApplication
    {
        return Factory::getApplication();
    }

    private function getInput()
    {
        return $this->getApp()->input;
    }

    private function getCurrentIdentity()
    {
        return $this->getApp()->getIdentity();
    }

    private function normalizeFieldIdentifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Some CSV headers may arrive in older single-byte encodings.
        if (preg_match('//u', $value) !== 1 && function_exists('iconv')) {
            foreach (['Windows-1252', 'ISO-8859-1'] as $legacyEncoding) {
                $converted = iconv($legacyEncoding, 'UTF-8//IGNORE', $value);
                if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
                    $value = $converted;
                    break;
                }
            }
        }

        // Normalize combining marks first when intl is available.
        if (class_exists('\Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized) && $normalized !== '') {
                $value = preg_replace('/\p{Mn}+/u', '', $normalized) ?? $normalized;
            }
        }

        // Map frequent non-ASCII letters that iconv may not always transliterate consistently.
        $value = strtr($value, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ă' => 'A', 'Ą' => 'A',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
            'Ç' => 'C', 'Ć' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Č' => 'C',
            'ç' => 'c', 'ć' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'č' => 'c',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ę' => 'E', 'Ě' => 'E',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ĩ' => 'I', 'Ī' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ĩ' => 'i', 'ī' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i',
            'Ñ' => 'N', 'Ń' => 'N', 'Ņ' => 'N', 'Ň' => 'N',
            'ñ' => 'n', 'ń' => 'n', 'ņ' => 'n', 'ň' => 'n',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ō' => 'O', 'Ŏ' => 'O', 'Ő' => 'O',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ũ' => 'U', 'Ū' => 'U', 'Ŭ' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'Ų' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ũ' => 'u', 'ū' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
            'Ý' => 'Y', 'Ÿ' => 'Y', 'Ŷ' => 'Y',
            'ý' => 'y', 'ÿ' => 'y', 'ŷ' => 'y',
            'Ž' => 'Z', 'Ź' => 'Z', 'Ż' => 'Z',
            'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
            'ß' => 'ss', 'ẞ' => 'SS',
            'Æ' => 'AE', 'æ' => 'ae',
            'Œ' => 'OE', 'œ' => 'oe',
            'Ø' => 'O',  'ø' => 'o',
            'Ð' => 'D',  'ð' => 'd',
            'Þ' => 'TH', 'þ' => 'th',
            'Ł' => 'L',  'ł' => 'l',
            'Đ' => 'D',  'đ' => 'd',
        ]);

        if (function_exists('iconv')) {
            $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($translit) && $translit !== '') {
                $value = $translit;
            }
        }

        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value) ?? $value;
        $value = trim($value, '_');
        $value = preg_replace('/_+/', '_', $value) ?? $value;

        if ($value === '') {
            return '';
        }
        if (preg_match('/^[0-9]/', $value)) {
            $value = 'field_' . $value;
        }

        return $value;
    }

    private function makeFallbackFieldIdentifier(): string
    {
        return 'field' . mt_rand(0, mt_getrandmax());
    }

    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        // IMPORTANT : on transmet factory/app/input à AdminModel
        parent::__construct($config, $factory);
        $this->option = 'com_contentbuilderng';
    }

    public function getTable($type = 'Storage', $prefix = 'Administrator', $config = [])
    {
        // Méthode moderne via MVCFactory du composant
        $table = $this->getMVCFactory()->createTable($type, $prefix, $config);

        // Fallback (déprécié mais utile en dépannage)
        // if (!$table) {
        //    $table = Table::getInstance($type, 'CB\\Component\\Contentbuilderng\\Administrator\\Table\\', $config);
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
        $app  = $this->getApp();
        return $app->getUserState($this->option . '.edit.storage.data', $data);
    }

    protected function populateState()
    {
        parent::populateState();
        $id = (int) $this->getInput()->getInt('id', 0);
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
        $input = $this->getInput();
        $jform = $input->post->get('jform', [], 'array');

        $fieldname = trim((string) ($jform['fieldname'] ?? ''));
        if ($fieldname === '') {
            return false;
        }

        $fieldtitle = trim((string) ($jform['fieldtitle'] ?? ''));
        $isGroup    = (int) ($jform['is_group'] ?? 0);
        $groupDef   = (string) ($jform['group_definition'] ?? '');

        // Normalisation
        $newfieldname = $this->normalizeFieldIdentifier($fieldname);
        $newfieldname = $newfieldname === '' ? $this->makeFallbackFieldIdentifier() : $newfieldname;

        $newfieldtitle = $fieldtitle !== '' ? $fieldtitle : $newfieldname;

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Unicité
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId)
            ->where($db->quoteName('name') . ' = ' . $db->quote($newfieldname));
        $db->setQuery($query);
        if ($db->loadResult()) {
            return false;
        }

        // Ordering max+1
        $query = $db->getQuery(true)
            ->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0) + 1')
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($query);
        $max = (int) $db->loadResult();

        // Insert field
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__contentbuilderng_storage_fields'))
            ->columns($db->quoteName(['ordering', 'storage_id', 'name', 'title', 'is_group', 'group_definition']))
            ->values(
                (int) $max . ', '
                . (int) $storageId . ', '
                . $db->quote($newfieldname) . ', '
                . $db->quote($newfieldtitle) . ', '
                . (int) $isGroup . ', '
                . $db->quote($groupDef)
            );
        $db->setQuery($query);
        $db->execute();

        // Assurer l’existence de la table data puis ajouter la colonne
        // (si ta table data existe toujours, tu peux garder juste l’ALTER)
        if (!empty($storage->name)) {
            try {
                $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $storage->name) . ' ADD ' . $db->quoteName($newfieldname) . ' TEXT NULL');
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
        $jform = $this->getInput()->post->get('jform', [], 'array');
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
        $user = $this->getCurrentIdentity();
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
     * Toute la logique de table/champs/rename/sync APRES le save core
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

        $input = $this->getInput();
        $jform = $input->post->get('jform', [], 'array');

        $fieldname = trim((string)($jform['fieldname'] ?? ''));
        if ($fieldname === '') {
            return;
        }

        $fieldtitle = trim((string)($jform['fieldtitle'] ?? ''));
        $isGroup    = (int)($jform['is_group'] ?? 0);
        $groupDef   = (string)($jform['group_definition'] ?? '');

        // Normalisation.
        $newfieldname = $this->normalizeFieldIdentifier($fieldname);
        $newfieldname = $newfieldname === '' ? $this->makeFallbackFieldIdentifier() : $newfieldname;

        $newfieldtitle = $fieldtitle !== '' ? $fieldtitle : $newfieldname;

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // ordering max+1
        $query = $db->getQuery(true)
            ->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0) + 1')
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($query);
        $max = (int) $db->loadResult();

        // unicité
        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('name') . ' = ' . $db->quote($newfieldname))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($query);
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

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__contentbuilderng_storage_fields'))
            ->columns($db->quoteName(['ordering', 'storage_id', 'name', 'title', 'is_group', 'group_definition']))
            ->values(
                (int) $max . ', '
                . (int) $storageId . ', '
                . $db->quote($newfieldname) . ', '
                . $db->quote($newfieldtitle) . ', '
                . (int) $isGroup . ', '
                . $db->quote($groupDef)
            );
        $db->setQuery($query);
        $db->execute();

        // Colonne dans table data (si table existe déjà)
        $storage = $this->getItem($storageId);
        if (!empty($storage->name)) {
            try {
                $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $storage->name) . ' ADD ' . $db->quoteName($newfieldname) . ' TEXT NULL');
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
                        $db->setQuery('RENAME TABLE ' . $db->quoteName('#__' . $oldName) . ' TO ' . $db->quoteName('#__' . $name));
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
                    $db->setQuery(
                        'CREATE TABLE ' . $db->quoteName('#__' . $name) . ' ('
                        . $db->quoteName('id') . ' INT NOT NULL AUTO_INCREMENT PRIMARY KEY,'
                        . $db->quoteName('storage_id') . ' INT NOT NULL DEFAULT ' . (int) $storageId . ','
                        . $db->quoteName('user_id') . ' INT NOT NULL DEFAULT 0,'
                        . $db->quoteName('created') . ' DATETIME NOT NULL DEFAULT ' . $db->quote($last_update) . ','
                        . $db->quoteName('created_by') . " VARCHAR(255) NOT NULL DEFAULT '',"
                        . $db->quoteName('modified_user_id') . ' INT NOT NULL DEFAULT 0,'
                        . $db->quoteName('modified') . ' DATETIME NULL DEFAULT NULL,'
                        . $db->quoteName('modified_by') . " VARCHAR(255) NOT NULL DEFAULT ''"
                        . ')'
                    );
                    $db->execute();

                    $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $name) . ' ADD INDEX (' . $db->quoteName('storage_id') . ')');
                    $db->execute();
                    $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $name) . ' ADD INDEX (' . $db->quoteName('user_id') . ')');
                    $db->execute();
                    $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $name) . ' ADD INDEX (' . $db->quoteName('created') . ')');
                    $db->execute();
                    $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $name) . ' ADD INDEX (' . $db->quoteName('modified_user_id') . ')');
                    $db->execute();
                    $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $name) . ' ADD INDEX (' . $db->quoteName('modified') . ')');
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
        $query = $db->getQuery(true)
            ->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0) + 1')
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($query);
        $max = (int) $db->loadResult();

        if ($fieldin !== '') {
            $query = $db->getQuery(true)
                ->select($db->quoteName('name'))
                ->from($db->quoteName('#__contentbuilderng_storage_fields'))
                ->where($db->quoteName('name') . ' IN (' . $fieldin . ')')
                ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
            $db->setQuery($query);
            $existingNames = $db->loadColumn() ?: [];

            foreach ($fields as $field => $type) {
                if (!in_array($field, $existingNames, true) && !in_array($field, $system_fields, true)) {
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__contentbuilderng_storage_fields'))
                        ->columns($db->quoteName(['ordering', 'storage_id', 'name', 'title', 'is_group', 'group_definition']))
                        ->values(
                            (int) $max . ', '
                            . (int) $storageId . ', '
                            . $db->quote($field) . ', '
                            . $db->quote($field) . ', '
                            . '0, ' . $db->quote('')
                        );
                    $db->setQuery($query);
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
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($name) . ' ADD ' . $db->quoteName('id') . ' INT NOT NULL AUTO_INCREMENT PRIMARY KEY');
                        $db->execute();
                        break;

                    case 'storage_id':
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($name) . ' ADD ' . $db->quoteName('storage_id') . ' INT NOT NULL DEFAULT ' . (int) $storageId . ', ADD INDEX (' . $db->quoteName('storage_id') . ')');
                        $db->execute();
                        break;

                    case 'user_id':
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($name) . ' ADD ' . $db->quoteName('user_id') . ' INT NOT NULL DEFAULT 0, ADD INDEX (' . $db->quoteName('user_id') . ')');
                        $db->execute();
                        break;

                    case 'created':
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($name) . ' ADD ' . $db->quoteName('created') . ' DATETIME NOT NULL DEFAULT ' . $db->quote($last_update) . ', ADD INDEX (' . $db->quoteName('created') . ')');
                        $db->execute();
                        break;

                    case 'created_by':
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($name) . ' ADD ' . $db->quoteName('created_by') . " VARCHAR(255) NOT NULL DEFAULT ''");
                        $db->execute();
                        break;

                    case 'modified_user_id':
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($name) . ' ADD ' . $db->quoteName('modified_user_id') . ' INT NOT NULL DEFAULT 0, ADD INDEX (' . $db->quoteName('modified_user_id') . ')');
                        $db->execute();
                        break;

                    case 'modified':
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($name) . ' ADD ' . $db->quoteName('modified') . ' DATETIME NULL DEFAULT NULL, ADD INDEX (' . $db->quoteName('modified') . ')');
                        $db->execute();
                        break;

                    case 'modified_by':
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($name) . ' ADD ' . $db->quoteName('modified_by') . " VARCHAR(255) NOT NULL DEFAULT ''");
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
                $query = $db->getQuery(true)
                    ->update($db->quoteName($name))
                    ->set($db->quoteName('storage_id') . ' = ' . (int) $storageId);
                $db->setQuery($query);
                $db->execute();

                $query = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName($name));
                $db->setQuery($query);
                $thirdPartyIds = $db->loadColumn() ?: [];

                foreach ($thirdPartyIds as $thirdPartyId) {
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__contentbuilderng_records'))
                        ->columns($db->quoteName(['type', 'last_update', 'is_future', 'lang_code', 'sef', 'published', 'record_id', 'reference_id']))
                        ->values(
                            $db->quote('com_contentbuilderng') . ', '
                            . $db->quote($last_update) . ', '
                            . '0, '
                            . $db->quote('*') . ', '
                            . $db->quote('') . ', '
                            . '1, '
                            . (int) $thirdPartyId . ', '
                            . (int) $storageId
                        );
                    $db->setQuery($query);
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
        $input = $this->getInput();
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
                $query = $db->getQuery(true)
                    ->select($db->quoteName('name'))
                    ->from($db->quoteName('#__contentbuilderng_storage_fields'))
                    ->where($db->quoteName('id') . ' = ' . (int) $field_id);
                $db->setQuery($query);
                $old_name = (string) $db->loadResult();

                // update storage_fields
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__contentbuilderng_storage_fields'))
                    ->set([
                        $db->quoteName('group_definition') . ' = ' . $db->quote($groupDef),
                        $db->quoteName('is_group') . ' = ' . (int) $isGroup,
                        $db->quoteName('name') . ' = ' . $db->quote($name),
                        $db->quoteName('title') . ' = ' . $db->quote($title),
                    ])
                    ->where($db->quoteName('id') . ' = ' . (int) $field_id);
                $db->setQuery($query);
                $db->execute();

                // rename column if needed
                if ($old_name !== '' && $old_name !== $name) {
                    try {
                        $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $storageTable->name) . ' CHANGE ' . $db->quoteName($old_name) . ' ' . $db->quoteName($name) . ' TEXT');
                        $db->execute();
                    } catch (\Throwable $e) {
                        Logger::exception($e);
                    }
                }
            } else {
                // bytable => pas de rename colonne
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__contentbuilderng_storage_fields'))
                    ->set([
                        $db->quoteName('group_definition') . ' = ' . $db->quote($groupDef),
                        $db->quoteName('is_group') . ' = ' . (int) $isGroup,
                        $db->quoteName('title') . ' = ' . $db->quote($title),
                    ])
                    ->where($db->quoteName('id') . ' = ' . (int) $field_id);
                $db->setQuery($query);
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
        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($query);
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

            $db->setQuery("DELETE FROM #__contentbuilderng_storage_fields WHERE storage_id = " . (int)$pk);
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
        $this->lastImportSummary = [];
        $start = microtime(true);
        $data = $this->getInput()->post->getArray();
        $fileName = (string) ($file['name'] ?? '');

        $resolvedStorageId = (int) ($storageId ?? 0);
        if ($resolvedStorageId <= 0) {
            $resolvedStorageId = (int) ($data['id'] ?? 0);
        }
        if ($resolvedStorageId <= 0) {
            $resolvedStorageId = (int) $this->getState($this->getName() . '.id', 0);
        }
        if ($resolvedStorageId <= 0) {
            $this->setError('Missing storage id for import');
            return false;
        }

        $storage = $this->getItem($resolvedStorageId);
        if (!$storage) {
            $this->setError('Storage not found');
            return false;
        }
        if (!empty($storage->bytable)) {
            $this->setError(Text::_('COM_CONTENTBUILDERNG_CANNOT_USE_CSV_WITH_FOREIGN_TABLE'));
            return false;
        }

        $this->storageId = $resolvedStorageId;
        $this->target_table = trim((string) ($storage->name ?? ''));
        if ($this->target_table === '') {
            $this->setError('Storage target table is empty');
            return false;
        }

        $data['id'] = $this->storageId;
        if (isset($data['bytable'])) {
            unset($data['bytable']);
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $supportedExtensions = ['csv', 'xlsx', 'xls'];
        if (!in_array($extension, $supportedExtensions, true)) {
            $this->setError(Text::_('COM_CONTENTBUILDERNG_STORAGE_IMPORT_UNSUPPORTED_FORMAT'));
            return false;
        }

        $dest = JPATH_SITE . '/tmp/' . md5(mt_rand(0, mt_getrandmax())) . '_' . $file['name'];
        $uploaded = File::upload($file['tmp_name'], $dest, false, true);

        if (!$uploaded) {
            $this->setError('Could not upload the import file to tmp');
            return false;
        }

        $sourceFile = $dest;
        $tmpFiles = [$dest];

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $delimiter = $this->getInput()->get('csv_delimiter', ',', 'string');
            $converted = $this->convertSpreadsheetFileToCsv($dest, $delimiter);
            if ($converted === null) {
                foreach ($tmpFiles as $tmpFile) {
                    if (is_file($tmpFile)) {
                        File::delete($tmpFile);
                    }
                }
                $this->setError('Could not convert spreadsheet to CSV');
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
            $this->setError($retval);
            return false;
        }

        if (!$retval) {
            $this->setError(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));
            return false;
        }

        if (!empty($this->lastImportSummary)) {
            $this->lastImportSummary['file_name'] = $fileName;
            $this->lastImportSummary['file_format'] = strtoupper($extension);
            $this->lastImportSummary['duration_ms'] = (int) round((microtime(true) - $start) * 1000);
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function getLastImportSummary(): array
    {
        return $this->lastImportSummary;
    }

    /**
     * Helper used by csv_file_to_table().
     * Creates a storage field definition if missing and ensures the data table column exists.
     *
     * @param array<string,mixed> $data
     */
    private function store(array $data): string
    {
        $storageId = (int) ($data['id'] ?? $this->storageId);
        if ($storageId <= 0) {
            throw new \RuntimeException('Missing storage id for CSV import field creation');
        }

        $fieldname = trim((string) ($data['fieldname'] ?? ''));
        if ($fieldname === '') {
            throw new \RuntimeException('Missing field name for CSV import');
        }

        $fieldtitle = trim((string) ($data['fieldtitle'] ?? $fieldname));
        $isGroup = (int) (!empty($data['is_group']));
        $groupDef = (string) ($data['group_definition'] ?? '');

        $newfieldname = $this->normalizeFieldIdentifier($fieldname);
        $newfieldname = $newfieldname === '' ? $this->makeFallbackFieldIdentifier() : $newfieldname;

        $newfieldtitle = $fieldtitle !== '' ? $fieldtitle : $newfieldname;

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $db->setQuery(
            "SELECT id FROM #__contentbuilderng_storage_fields"
            . " WHERE storage_id = " . (int) $storageId
            . " AND `name` = " . $db->quote($newfieldname)
        );
        $fieldId = (int) $db->loadResult();

        if ($fieldId <= 0) {
            $db->setQuery(
                "SELECT COALESCE(MAX(ordering), 0) + 1"
                . " FROM #__contentbuilderng_storage_fields"
                . " WHERE storage_id = " . (int) $storageId
            );
            $ordering = (int) $db->loadResult();

            $db->setQuery(
                "INSERT INTO #__contentbuilderng_storage_fields"
                . " (ordering, storage_id, `name`, `title`, `is_group`, `group_definition`)"
                . " VALUES ("
                . (int) $ordering . ", "
                . (int) $storageId . ", "
                . $db->quote($newfieldname) . ", "
                . $db->quote($newfieldtitle) . ", "
                . (int) $isGroup . ", "
                . $db->quote($groupDef)
                . ")"
            );
            $db->execute();
        }

        if ($this->target_table !== '') {
            $tableColumns = [];
            $prefixedTableName = $db->replacePrefix('#__' . $this->target_table);

            try {
                $tableColumns = $db->getTableColumns($prefixedTableName, true);
            } catch (\Throwable $e) {
                Logger::exception($e);
            }

            $columnExists = false;
            if (is_array($tableColumns) && !empty($tableColumns)) {
                foreach (array_keys($tableColumns) as $columnName) {
                    if (strtolower((string) $columnName) === strtolower($newfieldname)) {
                        $columnExists = true;
                        break;
                    }
                }
            }

            if (!$columnExists) {
                $db->setQuery("ALTER TABLE `#__" . $this->target_table . "` ADD `" . $newfieldname . "` TEXT NULL");
                $db->execute();
            }
        }

        return $db->quoteName($newfieldname);
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

    /**
     * Extract header columns from an uploaded CSV/XLS/XLSX file.
     *
     * @param array<string,mixed> $file
     * @return array<int,string>
     */
    public function extractHeaderColumnsFromUpload(array $file, string $delimiter = ',', string $repairEncoding = ''): array
    {
        if (empty($file['name']) || empty($file['tmp_name'])) {
            return [];
        }

        $safeName = File::makeSafe((string) $file['name']);
        $extension = strtolower((string) pathinfo($safeName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            return [];
        }

        $dest = JPATH_SITE . '/tmp/' . md5(mt_rand(0, mt_getrandmax())) . '_' . $safeName;
        $uploaded = File::upload((string) $file['tmp_name'], $dest, false, true);
        if (!$uploaded) {
            return [];
        }

        $columns = [];

        try {
            if ($extension === 'csv') {
                $handle = null;
                $encoding = $this->resolveCsvRepairEncoding($repairEncoding);
                if ($encoding !== '') {
                    if (!function_exists('iconv')) {
                        return [];
                    }
                    $handle = $this->utf8_fopen_read($dest, $encoding);
                } else {
                    $handle = fopen($dest, 'rb');
                }
                if ($handle !== false) {
                    $sep = ($delimiter !== '') ? $delimiter : ',';
                    $sep = $sep[0] ?? ',';
                    $columns = fgetcsv($handle, 1000000, $sep, '"') ?: [];
                    fclose($handle);
                }
            } else {
                VendorHelper::load();
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($dest);
                $sheet = $spreadsheet->getActiveSheet();
                $highestColumn = $sheet->getHighestColumn();
                $row = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false);
                if (!empty($row) && isset($row[0]) && is_array($row[0])) {
                    $columns = $row[0];
                }
                $spreadsheet->disconnectWorksheets();
            }
        } catch (\Throwable $e) {
            Logger::exception($e);
            $columns = [];
        } finally {
            if (is_file($dest)) {
                File::delete($dest);
            }
        }

        $normalized = [];
        foreach ($columns as $column) {
            $value = trim((string) $column);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        if (!empty($normalized)) {
            $normalized[0] = preg_replace('/^\xEF\xBB\xBF/', '', $normalized[0]);
        }

        return $normalized;
    }

    function utf8_fopen_read($fileName, $encoding)
    {
        $fc = iconv($encoding, 'UTF-8//TRANSLIT', file_get_contents($fileName));
        $handle = fopen("php://memory", "rw");
        fwrite($handle, $fc);
        fseek($handle, 0);
        return $handle;
    }

    private function resolveCsvRepairEncoding(string $requested): string
    {
        $allowed = [
            'WINDOWS-1250', 'WINDOWS-1251', 'WINDOWS-1252', 'WINDOWS-1253', 'WINDOWS-1254', 'WINDOWS-1255', 'WINDOWS-1256',
            'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8',
            'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-11', 'ISO-8859-12', 'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16',
            'UTF-8-MAC', 'UTF-16', 'UTF-16BE', 'UTF-16LE', 'UTF-32', 'UTF-32BE', 'UTF-32LE', 'ASCII', 'BIG-5', 'HEBREW',
            'CYRILLIC', 'ARABIC', 'GREEK', 'CHINESE', 'KOREAN', 'KOI8-R', 'KOI8-U', 'KOI8-RU', 'EUC-JP',
        ];

        $requested = strtoupper(trim($requested));
        return in_array($requested, $allowed, true) ? $requested : '';
    }


    function csv_file_to_table($source_file, $data, $max_line_length = 1000000)
    {

        $encoding = $this->resolveCsvRepairEncoding(
            $this->getInput()->get('csv_repair_encoding', '', 'string')
        );

        $handle = null;

        if ($encoding) {
            if (!function_exists('iconv')) {
                return Text::_('COM_CONTENTBUILDERNG_CSV_IMPORT_REPAIR_NO_ICONV');
            }
            $handle = $this->utf8_fopen_read("$source_file", $encoding);
        } else {
            $handle = fopen("$source_file", "rb");
        }

        if ($handle === FALSE) {
            return Text::_('JLIB_FILESYSTEM_ERROR_FILE_NOT_FOUND');
        }

        if ($handle !== FALSE) {

            $last_update = Factory::getDate()->toSql();

            $fieldnames = array();
            $rowReadCount = 0;
            $rowImportedCount = 0;
            $rowSkippedEmptyCount = 0;
            $droppedDataRecords = 0;
            $droppedMetaRecords = 0;
            $droppedArticleLinks = 0;

            $columns = fgetcsv($handle, $max_line_length, $this->getInput()->get('csv_delimiter', ',', 'string'), '"');
            if ($columns === false || !is_array($columns) || empty($columns)) {
                fclose($handle);
                return Text::_('COM_CONTENTBUILDERNG_CSV_IMPORT_COLUMN_COUNT_ERROR');
            }

            $colCheck = array();
            foreach ($columns as &$column) {
                $col = str_replace(".", "", trim($column));
                if (in_array($col, $colCheck)) {
                    fclose($handle);
                    return Text::_('COM_CONTENTBUILDERNG_CSV_IMPORT_COLUMN_NOT_UNIQUE');
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

            if ($this->getInput()->getBool('csv_drop_records', false)) {
                $this->getDatabase()->setQuery("Select Count(*) From #__" . $this->target_table);
                $droppedDataRecords = (int) $this->getDatabase()->loadResult();
                $this->getDatabase()->setQuery("Select Count(*) From #__contentbuilderng_records Where `type` = 'com_contentbuilderng' And reference_id = " . $this->getDatabase()->quote($this->storageId));
                $droppedMetaRecords = (int) $this->getDatabase()->loadResult();
                $this->getDatabase()->setQuery("Select Count(*) From #__contentbuilderng_articles Where `type` = 'com_contentbuilderng' And reference_id = " . $this->getDatabase()->quote($this->storageId));
                $droppedArticleLinks = (int) $this->getDatabase()->loadResult();

                $this->getDatabase()->setQuery("Truncate Table #__" . $this->target_table);
                $this->getDatabase()->execute();
                $this->getDatabase()->setQuery("Delete From #__contentbuilderng_records Where `type` = 'com_contentbuilderng' And reference_id = " . $this->getDatabase()->quote($this->storageId));
                $this->getDatabase()->execute();
                $this->getDatabase()->setQuery("Delete a.*, c.* From #__contentbuilderng_articles As a, #__content As c Where c.id = a.article_id And a.`type` = 'com_contentbuilderng' And a.reference_id = " . $this->getDatabase()->quote($this->storageId));
                $this->getDatabase()->execute();
            }

            $insert_query_prefix = "INSERT INTO #__" . $this->target_table . " (" . join(",", $fieldnames) . ")\nVALUES";

            while (($data = fgetcsv($handle, $max_line_length, $this->getInput()->get('csv_delimiter', ',', 'string'), '"')) !== FALSE) {
                $rowReadCount++;
                while (count($data) < count($columns))
                    array_push($data, NULL);

                $isEmptyRow = true;
                foreach ($data as $value) {
                    if (trim((string) $value) !== '') {
                        $isEmptyRow = false;
                        break;
                    }
                }
                if ($isEmptyRow) {
                    $rowSkippedEmptyCount++;
                    continue;
                }

                $query = "$insert_query_prefix (" . join(", ", $this->quote_all_array($data)) . ")";
                $this->getDatabase()->setQuery($query);
                $this->getDatabase()->execute();
                $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_records (`type`,last_update,is_future,lang_code, sef, published, record_id, reference_id) Values ('com_contentbuilderng'," . $this->getDatabase()->quote($last_update) . ",0,'*',''," . $this->getInput()->getInt('csv_published', 0) . ", " . $this->getDatabase()->quote(intval($this->getDatabase()->insertid())) . ", " . $this->getDatabase()->quote($this->storageId) . ")");
                $this->getDatabase()->execute();
                $rowImportedCount++;
            }
            fclose($handle);

            $this->lastImportSummary = [
                'storage_id' => $this->storageId,
                'columns' => count($columns),
                'created_fields' => count($fieldnames),
                'rows_read' => $rowReadCount,
                'rows_imported' => $rowImportedCount,
                'rows_skipped_empty' => $rowSkippedEmptyCount,
                'published' => $this->getInput()->getInt('csv_published', 0),
                'drop_records' => $this->getInput()->getBool('csv_drop_records', false),
                'dropped_data_records' => $droppedDataRecords,
                'dropped_meta_records' => $droppedMetaRecords,
                'dropped_article_links' => $droppedArticleLinks,
            ];
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
