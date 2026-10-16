<?php

/**
 * ContentBuilder NG Storage Model.
 *
 * Handles CRUD and publish state for storage in the admin interface.
 *
 * @package     ContentBuilderNG
 * @subpackage  Administrator.Model
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @copyright   Copyright © 2024–2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Date\Date;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Helper\StorageColumnTypeHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\StorageSystemFieldHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\VendorHelper;
use CB\Component\Contentbuilderng\Administrator\Service\DatatableService;
use CB\Component\Contentbuilderng\Administrator\Dto\CsvImportOptions;
use CB\Component\Contentbuilderng\Administrator\Service\ExternalTableService;

class StorageModel extends AdminModel
{
    /** @var object|null */
    protected ?object $oldItem = null;

    /** @var array{from:string,to:string}|null */
    private ?array $lastDataTableRename = null;

    /** Required for CSV file */
    private string $target_table = '';
    private int $storageId = 0;

    private function getComponent(): ContentbuilderngComponent
    {
        $component = RuntimeContextHelper::getApplication()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private function getDatatableService(): DatatableService
    {
        return $this->getComponent()->getContainer()->get(DatatableService::class);
    }
    /** @var array<string,mixed> */
    private array $lastImportSummary = [];

    private function getApp(): CMSApplication
    {
        $app = $this->getComponent()->getContainer()->get(CMSApplicationInterface::class);

        if (!$app instanceof CMSApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    private function getInput()
    {
        return $this->getApp()->getInput();
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
        array $config = [],
        ?MVCFactoryInterface $factory = null
    ) {
        // IMPORTANT : on transmet factory/app/input à AdminModel
        parent::__construct($config, $factory);
        $this->option = 'com_contentbuilderng';
    }

    public function getTable($type = 'Storage', $prefix = 'Administrator', $config = [])
    {
        $table = $this->getMVCFactory()->createTable($type, $prefix, $config);

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
        // Intelephense may not resolve inherited AdminModel::loadForm() in this workspace.
        $loadForm = 'loadForm';
        $form = $this->{$loadForm}(
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

    #[\Override]
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
        $sqlType = StorageColumnTypeHelper::normalize((string) ($jform['sql_type'] ?? StorageColumnTypeHelper::DEFAULT_TYPE));
        $fieldSize = StorageColumnTypeHelper::normalizeSize($sqlType, $jform['field_size'] ?? null);
        $required   = !empty($jform['required']);
        $isGroup    = (int) ($jform['is_group'] ?? 0);
        $groupDef   = (string) ($jform['group_definition'] ?? '');

        // Normalisation
        $newfieldname = $this->normalizeFieldIdentifier($fieldname);
        $newfieldname = $newfieldname === '' ? $this->makeFallbackFieldIdentifier() : $newfieldname;

        $newfieldtitle = $fieldtitle !== '' ? $fieldtitle : $newfieldname;

        $db = $this->getDatabase();

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
            ->columns($db->quoteName(['ordering', 'storage_id', 'name', 'title', 'sql_type', 'field_size', 'required', 'is_group', 'group_definition']))
            ->values(
                (int) $max . ', '
                . (int) $storageId . ', '
                . $db->quote($newfieldname) . ', '
                . $db->quote($newfieldtitle) . ', '
                . $db->quote($sqlType) . ', '
                . ($fieldSize === null ? 'NULL' : (int) $fieldSize) . ', '
                . (int) $required . ', '
                . (int) $isGroup . ', '
                . $db->quote($groupDef)
            );
        // Assurer l’existence de la table data puis ajouter la colonne avant
        // de persister ses métadonnées. MySQL valide ainsi tout le DDL sans
        // laisser de définition orpheline si le type ou la contrainte échoue.
        if (!empty($storage->name)) {
            $quotedTable = $db->quoteName('#__' . $storage->name);
            $quotedColumn = $db->quoteName($newfieldname);
            $columnAdded = false;

            try {
                $db->setQuery(
                    'ALTER TABLE ' . $quotedTable
                    . ' ADD ' . $quotedColumn . ' ' . StorageColumnTypeHelper::sqlDefinition($sqlType, $fieldSize)
                );
                $db->execute();
                $columnAdded = true;

                if ($required) {
                    StorageColumnTypeHelper::enforceRequired(
                        $db,
                        $quotedTable,
                        $quotedColumn,
                        $sqlType,
                        $fieldSize
                    );
                }
            } catch (\Throwable $e) {
                Logger::exception($e);

                if ($columnAdded) {
                    try {
                        $db->setQuery('ALTER TABLE ' . $quotedTable . ' DROP COLUMN ' . $quotedColumn);
                        $db->execute();
                    } catch (\Throwable $cleanupException) {
                        Logger::exception($cleanupException);
                    }
                }

                return false;
            }
        }

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            Logger::exception($e);

            if (!empty($storage->name)) {
                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName('#__' . $storage->name)
                        . ' DROP COLUMN ' . $db->quoteName($newfieldname)
                    );
                    $db->execute();
                } catch (\Throwable $cleanupException) {
                    Logger::exception($cleanupException);
                }
            }

            return false;
        }

        // Optionnel: vider le champ dans la session / form
        return true;
    }

    /**
     * Index existants de la table physique d'un storage interne (clé
     * primaire incluse, en lecture seule). Aucune donnée renvoyée pour un
     * storage bytable (externe) : on ne modifie jamais une table qui
     * n'appartient pas au composant.
     *
     * @return array<int,array{name:string,columns:array<int,string>,unique:bool,primary:bool}>
     */
    public function getPhysicalIndexes(int $storageId): array
    {
        $storage = $this->getItem($storageId);
        if (!$storage || !empty($storage->bytable) || empty($storage->name)) {
            return [];
        }

        $db = $this->getDatabase();
        $tableQN = $db->quoteName('#__' . $storage->name);

        try {
            // Ensure the table metadata exists, but leave legacy index names
            // untouched until the database audit repair is explicitly run.
            $this->getDatatableService()->ensureInternalAuditColumns($storageId);
            $db->setQuery('SHOW INDEX FROM ' . $tableQN);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            Logger::exception($e);
            return [];
        }

        $indexes = [];
        foreach ($rows as $row) {
            $keyName = (string) ($row['Key_name'] ?? '');
            $columnName = (string) ($row['Column_name'] ?? '');
            if ($keyName === '' || $columnName === '') {
                continue;
            }

            if (!isset($indexes[$keyName])) {
                $indexes[$keyName] = [
                    'name' => $keyName,
                    'columns' => [],
                    'unique' => ((int) ($row['Non_unique'] ?? 1)) === 0,
                    'primary' => $keyName === 'PRIMARY',
                ];
            }

            $indexes[$keyName]['columns'][] = $columnName;
        }

        // La clé primaire en tête, puis le reste dans l'ordre naturel.
        usort(
            $indexes,
            static fn(array $a, array $b): int => ($b['primary'] <=> $a['primary'])
        );

        return array_values($indexes);
    }

    /**
     * Ajoute un index mono-colonne sur la table physique d'un storage
     * interne (bytable=0 uniquement : ALTER TABLE sûr sur une table dont le
     * composant est propriétaire).
     */
    public function addIndexFromRequest(int $storageId): bool
    {
        $storage = $this->getItem($storageId);
        if (!$storage || !empty($storage->bytable) || empty($storage->name)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_INDEX_ADD_FAILED_STORAGE'));
        }

        $input = $this->getInput();
        $jform = $input->post->get('jform', [], 'array');
        $column = trim((string) ($jform['index_column'] ?? ''));

        if ($column === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_INDEX_ADD_FAILED_NO_COLUMN'));
        }

        $db = $this->getDatabase();
        $prefixedTable = $db->getPrefix() . $storage->name;

        try {
            $columns = (array) $db->getTableColumns($prefixedTable, true);
        } catch (\Throwable $e) {
            Logger::exception($e);
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_INDEX_ADD_FAILED_COLUMN', $column, $e->getMessage()));
        }

        $columnsLower = array_change_key_case($columns, CASE_LOWER);
        if (!isset($columnsLower[strtolower($column)])) {
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_INDEX_ADD_FAILED_UNKNOWN_COLUMN', $column));
        }

        foreach ($this->getPhysicalIndexes($storageId) as $index) {
            if (count($index['columns']) === 1 && strcasecmp($index['columns'][0], $column) === 0) {
                throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_INDEX_ADD_FAILED_ALREADY_EXISTS', $column));
            }
        }

        $tableQN = $db->quoteName('#__' . $storage->name);
        $indexName = 'idx_' . strtolower($column);

        try {
            $db->setQuery(
                'ALTER TABLE ' . $tableQN
                . ' ADD INDEX ' . $db->quoteName($indexName) . ' (' . $db->quoteName($column) . ')'
            );
            $db->execute();
        } catch (\Throwable $e) {
            Logger::exception($e);
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_INDEX_ADD_FAILED_COLUMN', $column, $e->getMessage()));
        }

        return true;
    }

    /**
     * Supprime un index de la table physique d'un storage interne
     * (bytable=0 uniquement). La clé primaire n'est jamais une cible valide.
     */
    public function deleteIndex(int $storageId, string $indexName): bool
    {
        $storage = $this->getItem($storageId);
        if (!$storage || !empty($storage->bytable) || empty($storage->name)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_INDEX_DELETE_FAILED_STORAGE'));
        }

        $requestedIndexName = trim($indexName);
        $indexName = $requestedIndexName;
        if ($indexName === '' || $indexName === 'PRIMARY' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $indexName)) {
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_INDEX_DELETE_FAILED_INVALID', $requestedIndexName !== '' ? $requestedIndexName : '?'));
        }

        $db = $this->getDatabase();
        $tableQN = $db->quoteName('#__' . $storage->name);

        try {
            $db->setQuery('ALTER TABLE ' . $tableQN . ' DROP INDEX ' . $db->quoteName($indexName));
            $db->execute();
        } catch (\Throwable $e) {
            Logger::exception($e);
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_INDEX_DELETE_FAILED_NAME', $indexName, $e->getMessage()));
        }

        return true;
    }


    /**
     * Normalisation name/title/bytable.
     */
    /**
     * Normalise un nom de storage en identifiant de table exploitable, à
     * l'identique de ce que prepareTable() persiste réellement. Exposée pour
     * que les contrôles effectués en amont (unicité, longueur maximale de
     * l'identifiant MySQL/MariaDB) portent sur la valeur finale et non sur la
     * saisie brute : la normalisation peut allonger le nom, par exemple en
     * préfixant "field" devant un nom commençant par un chiffre.
     *
     * Retourne une chaîne vide si la saisie ne produit aucun identifiant :
     * prepareTable() lui substitue alors un nom aléatoire, volontairement non
     * reproduit ici pour que la fonction reste déterministe.
     */
    public static function normalizeStorageName(string $name): string
    {
        $normalized = preg_replace("/[^a-zA-Z0-9_\s]/isU", "_", trim(strtolower($name)));
        $normalized = str_replace([' ', "\n", "\r", "\t"], [''], (string) $normalized);
        $normalized = preg_replace("/^([0-9\s])/isU", "field$1$2", $normalized);

        return (string) $normalized;
    }

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
        } elseif (!empty($bytable) && $bytable !== '1') {
            $bytableName = $bytable;
        }

        if ($bytableName !== '') {
            // External table chosen: store flag + keep the table name in "name".
            $table->bytable = $this->getComponent()->getContainer()
                ->get(ExternalTableService::class)
                ->getBytableMode($bytableName);
            $table->name = $bytableName;
            $table->title = trim($title) !== '' ? trim($title) : $bytableName;
        } elseif (!empty($bytable)) {
            // Flag set without a table name in the request: keep current name.
            $table->bytable = 1;
            $table->name = $name;
            $table->title = trim($title) !== '' ? trim($title) : $name;
        } else {
            $table->bytable = 0;

            $newname = self::normalizeStorageName($name);
            $newname = $newname === '' ? ('field' . mt_rand(0, mt_getrandmax())) : $newname;

            $maxNameLength = 64 - strlen($this->getDatabase()->getPrefix());
            if (strlen($newname) > $maxNameLength) {
                throw new \RuntimeException(
                    Text::sprintf('COM_CONTENTBUILDERNG_STORAGE_NAME_TOO_LONG', $maxNameLength)
                );
            }

            $this->target_table = $newname; // csv helper si besoin
            $table->name  = $newname;
            $table->title = trim($title) !== '' ? trim($title) : $newname;
        }

        // Standard Joomla-style audit fields for storages.
        $now = (new Date())->toSql();
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

    // Crée une table #__<storage.name> ou synchronise une table externe (bytable).
    public function ensureDataTable(int $storageId, bool $isNew = false, ?string $oldName = null): void
    {
        $this->lastDataTableRename = null;

        $table = $this->getTable('Storage');
        $table->load($storageId);

        $this->syncStorageDataTableOrBytable($storageId, $isNew, $table, $oldName);
        $this->ensureSystemFieldMetadata($storageId, $table);
    }

    /**
     * Ensures that the reserved physical columns have stable field metadata.
     *
     * System fields are deliberately created unpublished. This keeps them
     * available for ordering and normal publish/unpublish handling while
     * preventing them from appearing in the data views until explicitly
     * enabled by an administrator.
     */
    public function ensureSystemFieldMetadata(
        int $storageId,
        ?\Joomla\CMS\Table\Table $storageTable = null
    ): void {
        if ($storageId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $table = $storageTable ?? $this->getTable('Storage');

        if ($storageTable === null) {
            $table->load($storageId);
        }

        $name = trim((string) ($table->name ?? ''));
        if ($name === '') {
            return;
        }

        $physicalTable = (int) ($table->bytable ?? 0) > 0
            ? $name
            : $db->getPrefix() . $name;

        try {
            $columns = (array) $db->getTableColumns($physicalTable, false);
        } catch (\Throwable $e) {
            if ((int) ($table->bytable ?? 0) <= 0) {
                Logger::exception($e);
                return;
            }

            try {
                $columns = (array) $db->getTableColumns($db->getPrefix() . $name, false);
            } catch (\Throwable $fallbackException) {
                Logger::exception($fallbackException);
                return;
            }
        }

        if ($columns === [] && (int) ($table->bytable ?? 0) > 0) {
            try {
                $columns = (array) $db->getTableColumns($db->getPrefix() . $name, false);
            } catch (\Throwable $e) {
                Logger::exception($e);
            }
        }

        $columnNames = array_fill_keys(
            array_map('strtolower', array_map('strval', array_keys($columns))),
            true
        );

        if ($columnNames === []) {
            return;
        }

        $existingQuery = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($existingQuery);
        $existingNames = array_fill_keys(
            array_map('strtolower', array_map('strval', (array) $db->loadColumn())),
            true
        );

        $orderingQuery = $db->getQuery(true)
            ->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0)')
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($orderingQuery);
        $ordering = (int) $db->loadResult();

        foreach (StorageSystemFieldHelper::definitions() as $fieldName => $definition) {
            $normalizedName = strtolower($fieldName);

            if (!isset($columnNames[$normalizedName]) || isset($existingNames[$normalizedName])) {
                continue;
            }

            $sqlType = StorageColumnTypeHelper::normalize((string) $definition['sql_type']);
            $fieldSize = StorageColumnTypeHelper::defaultSize($sqlType);
            $ordering++;

            $insert = $db->getQuery(true)
                ->insert($db->quoteName('#__contentbuilderng_storage_fields'))
                ->columns($db->quoteName([
                    'ordering',
                    'storage_id',
                    'name',
                    'title',
                    'sql_type',
                    'field_size',
                    'required',
                    'is_group',
                    'group_definition',
                    'published',
                ]))
                ->values(implode(', ', [
                    $ordering,
                    $storageId,
                    $db->quote($fieldName),
                    $db->quote((string) $definition['label']),
                    $db->quote($sqlType),
                    $fieldSize === null ? 'NULL' : (string) $fieldSize,
                    (int) $definition['required'],
                    '0',
                    $db->quote(''),
                    '0',
                ]));
            $db->setQuery($insert);
            $db->execute();
        }
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
     * Crée/renomme la table #__<storage.name> si !bytable.
     * Pour une table externe, synchronise uniquement les métadonnées CBNG :
     * la structure et les données de la table existante restent intouchables.
     */
    private function syncStorageDataTableOrBytable(int $storageId, bool $isNew, \Joomla\CMS\Table\Table $table, ?string $oldName = null): void
    {
        $db = $this->getDatabase();

        $name   = (string) ($table->name ?? '');
        $bytable = (int) ($table->bytable ?? 0);

        $last_update = (new Date())->toSql();

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
                            $this->getDatatableService()->ensureInternalAuditColumns($storageId);
                        } catch (\Throwable $e) {
                            Logger::exception($e);
                        }

                        return;
                    }
                }

                // create
                Logger::info('Create data table', ['name' => $name, 'storageId' => $storageId]);

                // Ne pas avaler l'exception ici : une table non créée alors que
                // l'enregistrement du storage lui-même a réussi (ex. nom trop
                // long pour MySQL/MariaDB une fois préfixé) doit remonter comme
                // une vraie erreur au contrôleur, pas rester silencieuse.
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
                    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
                );
                $db->execute();

                // Seul user_id est réellement filtré en WHERE (ownership,
                // own_only/own_only_fe) : storage_id est constant sur la
                // table d'un storage interne (cardinalité 1), created/modified
                // ne sont jamais triés/filtrés directement (seulement via un
                // COALESCE non indexable pour colLastModification), et
                // modified_user_id n'est ni filtré ni trié.
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__' . $name)
                    . ' ADD INDEX ' . $db->quoteName('idx_user_id')
                    . ' (' . $db->quoteName('user_id') . ')'
                );
                $db->execute();
            }

            try {
                $this->getDatatableService()->ensureInternalAuditColumns($storageId);
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
                        ->columns($db->quoteName(['ordering', 'storage_id', 'name', 'title', 'sql_type', 'is_group', 'group_definition']))
                        ->values(
                            (int) $max . ', '
                            . (int) $storageId . ', '
                            . $db->quote($field) . ', '
                            . $db->quote($field) . ', '
                            . $db->quote(StorageColumnTypeHelper::DEFAULT_TYPE) . ', '
                            . '0, ' . $db->quote('')
                        );
                    $db->setQuery($query);
                    $db->execute();
                }
            }
        }

        // Mode bytable=1 : comportement historique explicitement accepté.
        // La table peut recevoir les colonnes système manquantes.
        if ($bytable === 1) {
            foreach ($system_fields as $missing) {
                if (in_array($missing, $allfields, true)) {
                    continue;
                }

                // Seul user_id est indexé : voir la note dans le bloc
                // !$bytable ci-dessus (storage_id constant, created/modified
                // non exploitables tels quels, modified_user_id jamais filtré).
                $definition = match ($missing) {
                    'id' => ' INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
                    'storage_id' => ' INT NOT NULL DEFAULT ' . (int) $storageId,
                    'user_id' => ' INT NOT NULL DEFAULT 0, ADD INDEX ' . $db->quoteName('idx_user_id') . ' (' . $db->quoteName('user_id') . ')',
                    'created' => ' DATETIME NOT NULL DEFAULT ' . $db->quote($last_update),
                    'created_by' => " VARCHAR(255) NOT NULL DEFAULT ''",
                    'modified_user_id' => ' INT NOT NULL DEFAULT 0',
                    'modified' => ' DATETIME NULL DEFAULT NULL',
                    'modified_by' => " VARCHAR(255) NOT NULL DEFAULT ''",
                };

                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName($name)
                    . ' ADD ' . $db->quoteName($missing) . $definition
                );
                $db->execute();
            }

            if ($isNew) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName($name))
                    ->set($db->quoteName('storage_id') . ' = ' . (int) $storageId);
                $db->setQuery($query);
                $db->execute();
            }
        }

        // bytable=2 ne fait que lire la colonne id existante. Aucun ALTER ou
        // UPDATE n'est exécuté sur une table Joomla/BreezingForms connue.
        if ($isNew && ($bytable === 1 || in_array('id', $allfields, true))) {
            try {
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
        } elseif ($isNew) {
            // bytable=2 (table externe connue, protégée) exige une colonne id
            // existante : contrairement à bytable=1, aucun ALTER n'est jamais
            // exécuté dessus, donc le CRUD des enregistrements (basé sur id)
            // ne peut pas fonctionner sans elle.
            throw new \RuntimeException(
                Text::sprintf('COM_CONTENTBUILDERNG_STORAGE_BYTABLE2_MISSING_ID', $db->replacePrefix($name))
            );
        }
    }

    /**
     * Applique les modifications sur les fields existants (édition inline)
     * + rename colonnes si !bytable
     */
    private function syncEditedFields(int $storageId, int $bytable, \Joomla\CMS\Table\Table $storageTable): void
    {
        $input = $this->getInput();
        $db = $this->getDatabase();

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
                    ->select($db->quoteName(['name', 'sql_type', 'field_size', 'required']))
                    ->from($db->quoteName('#__contentbuilderng_storage_fields'))
                    ->where($db->quoteName('id') . ' = ' . (int) $field_id);
                $db->setQuery($query);
                $oldField = $db->loadAssoc() ?: [];
                $old_name = (string) ($oldField['name'] ?? '');
                $oldSqlType = StorageColumnTypeHelper::normalize((string) ($oldField['sql_type'] ?? StorageColumnTypeHelper::DEFAULT_TYPE));
                $oldFieldSize = StorageColumnTypeHelper::normalizeSize($oldSqlType, $oldField['field_size'] ?? null);
                $oldRequired = !empty($oldField['required']);

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

                // Renommer la colonne si besoin. Ne pas avaler l'exception ici :
                // les métadonnées (#__contentbuilderng_storage_fields.name) ont
                // déjà été mises à jour juste au-dessus, donc un ALTER échoué
                // silencieusement laisserait le champ déclaré sous un nom que
                // la table physique ne porte pas — la remontée fait échouer
                // toute l'action de sauvegarde plutôt que de rester invisible.
                if ($old_name !== '' && $old_name !== $name) {
                    $db->setQuery('ALTER TABLE ' . $db->quoteName('#__' . $storageTable->name) . ' CHANGE ' . $db->quoteName($old_name) . ' ' . $db->quoteName($name) . ' ' . StorageColumnTypeHelper::sqlDefinition($oldSqlType, $oldFieldSize, $oldRequired));
                    $db->execute();
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
        $db = $this->getDatabase();

        // Liste des champs définis
        $query = $db->getQuery(true)
            ->select($db->quoteName(['name', 'sql_type', 'field_size', 'required']))
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($query);
        $fields = $db->loadAssocList() ?: [];

        // Colonnes existantes
        try {
            $cols = $db->getTableColumns($db->getPrefix() . $dataTableName, true);
        } catch (\Throwable $e) {
            Logger::exception($e);
            return;
        }

        // Chaque colonne manquante est traitée indépendamment (une seule
        // erreur ne doit pas empêcher la synchronisation des autres champs),
        // mais un échec ne doit jamais rester invisible : il est journalisé
        // ET remonté en fin de méthode pour faire échouer l'action de
        // sauvegarde plutôt que de laisser croire que tout est synchronisé.
        $failedFields = [];
        foreach ($fields as $field) {
            $fieldname = (string) ($field['name'] ?? '');
            if ($fieldname === '' || isset($cols[$fieldname])) {
                continue;
            }

            $sqlType = StorageColumnTypeHelper::normalize((string) ($field['sql_type'] ?? StorageColumnTypeHelper::DEFAULT_TYPE));
            $fieldSize = StorageColumnTypeHelper::normalizeSize($sqlType, $field['field_size'] ?? null);
            $required = !empty($field['required']);

            try {
                // Toujours ajoutée nullable d'abord : la colonne n'existe pas
                // encore, donc aucun backfill n'est possible avant qu'elle
                // existe. Si le champ est obligatoire, enforceRequired()
                // bascule ensuite en NOT NULL après avoir comblé les lignes
                // existantes (backfill), ce qui fonctionne même sur une
                // table déjà peuplée.
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__' . $dataTableName)
                    . ' ADD ' . $db->quoteName($fieldname) . ' ' . StorageColumnTypeHelper::sqlDefinition($sqlType, $fieldSize)
                );
                $db->execute();

                if ($required) {
                    StorageColumnTypeHelper::enforceRequired(
                        $db,
                        $db->quoteName('#__' . $dataTableName),
                        $db->quoteName($fieldname),
                        $sqlType,
                        $fieldSize
                    );
                }
            } catch (\Throwable $e) {
                Logger::exception($e);
                $failedFields[] = $fieldname;
            }
        }

        if ($failedFields !== []) {
            throw new \RuntimeException(
                Text::sprintf('COM_CONTENTBUILDERNG_STORAGE_MISSING_COLUMNS_FAILED', implode(', ', $failedFields))
            );
        }
    }

    /**
     * @param array<int,int|string>|int|string $pks
     */
    #[\Override]
    public function delete(&$pks)
    {
        $pks = (array) $pks;
        ArrayHelper::toInteger($pks);

        $db = $this->getDatabase();
        $row = $this->getTable('Storage');
        $failedDrops = [];

        foreach ($pks as $pk) {
            $pk = (int)$pk;

            // charger storage
            $storage = $this->getItem($pk);

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_storage_fields'))
                ->where($db->quoteName('storage_id') . ' = :storageId')
                ->bind(':storageId', $pk, ParameterType::INTEGER);
            $db->setQuery($query);
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

            // Le DROP TABLE arrive après la suppression des métadonnées : un
            // échec ici laisserait une table orpheline, sans plus aucune ligne
            // pour la référencer. Ne pas avaler l'exception — elle est
            // journalisée puis remontée en fin de méthode pour que
            // l'utilisateur soit informé au lieu de croire tout nettoyé.
            if ($storage && empty($storage->bytable) && !empty($storage->name)) {
                try {
                    $db->setQuery('DROP TABLE ' . $db->quoteName('#__' . (string) $storage->name));
                    $db->execute();
                } catch (\Throwable $e) {
                    Logger::exception($e);
                    $failedDrops[] = '#__' . (string) $storage->name;
                }
            }
        }

        try {
            $row->reorder();
        } catch (\Throwable $e) {
            Logger::exception($e);
        }

        if ($failedDrops !== []) {
            throw new \RuntimeException(
                Text::sprintf('COM_CONTENTBUILDERNG_STORAGE_DROP_TABLE_FAILED', implode(', ', $failedDrops))
            );
        }

        return true;
    }


    public function storeCsv(array $file, ?int $storageId = null): bool
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
            $this->setError(Text::_('COM_CONTENTBUILDERNG_STORAGE_NOT_FOUND'));
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
        $uploaded = File::upload($file['tmp_name'], $dest, false);

        if (!$uploaded) {
            $this->setError('Could not upload the import file to tmp');
            return false;
        }

        $sourceFile = $dest;
        $tmpFiles = [$dest];

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $options = CsvImportOptions::fromPostData($data);
            $converted = $this->convertSpreadsheetFileToCsv($dest, $options->delimiter);
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

        @ini_set('auto_detect_line_endings', true);
        $retval = $this->csvFileToTable($sourceFile, $data);

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
     * Helper used by csvFileToTable().
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

        $db = $this->getDatabase();

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
                . " (ordering, storage_id, `name`, `title`, `sql_type`, `is_group`, `group_definition`)"
                . " VALUES ("
                . (int) $ordering . ", "
                . (int) $storageId . ", "
                . $db->quote($newfieldname) . ", "
                . $db->quote($newfieldtitle) . ", "
                . $db->quote(StorageColumnTypeHelper::DEFAULT_TYPE) . ", "
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
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__' . $this->target_table)
                    . ' ADD ' . $db->quoteName($newfieldname) . ' ' . StorageColumnTypeHelper::sqlDefinition(StorageColumnTypeHelper::DEFAULT_TYPE)
                );
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
        $uploaded = File::upload((string) $file['tmp_name'], $dest, false);
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
                    $handle = $this->utf8FopenRead($dest, $encoding);
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

    /**
     * @return resource|false
     */
    private function utf8FopenRead(string $fileName, string $encoding)
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


    private function csvFileToTable(string $source_file, array $data, int $max_line_length = 1000000): int|string
    {
        $options = CsvImportOptions::fromPostData($data);

        $encoding = $this->resolveCsvRepairEncoding($options->repairEncoding);

        $handle = null;

        if ($encoding) {
            if (!function_exists('iconv')) {
                return Text::_('COM_CONTENTBUILDERNG_CSV_IMPORT_REPAIR_NO_ICONV');
            }
            $handle = $this->utf8FopenRead("$source_file", $encoding);
        } else {
            $handle = fopen("$source_file", "rb");
        }

        if ($handle === false) {
            return Text::_('COM_CONTENTBUILDERNG_FILE_NOT_FOUND');
        }

        $last_update = (new Date())->toSql();

        $fieldnames = array();
        $rowReadCount = 0;
        $rowImportedCount = 0;
        $rowSkippedEmptyCount = 0;
        $droppedDataRecords = 0;
        $droppedMetaRecords = 0;
        $droppedArticleLinks = 0;

        $columns = fgetcsv($handle, $max_line_length, $options->delimiter, '"');
        if ($columns === false || empty($columns)) {
            fclose($handle);
            return Text::_('COM_CONTENTBUILDERNG_CSV_IMPORT_COLUMN_COUNT_ERROR');
        }

        foreach ($columns as &$column) {
            $column = str_replace('.', '', trim($column));
        }
        unset($column);

        $importAllColumns = $options->selectedColumns === null;
        $selectedColumnIndexes = array();
        if (!$importAllColumns) {
            foreach ($options->selectedColumns as $rawIndex) {
                $selectedColumnIndexes[(int) $rawIndex] = true;
            }
        }

        $includedColumnIndexes = array();
        $normalizedColumns = [];
        foreach ($columns as $index => $column) {
            if (!$importAllColumns && !isset($selectedColumnIndexes[$index])) {
                continue;
            }

            if ($column === '') {
                fclose($handle);
                return Text::sprintf('COM_CONTENTBUILDERNG_CSV_IMPORT_EMPTY_COLUMN_HEADER', $index + 1);
            }

            $normalizedColumn = $this->normalizeFieldIdentifier($column);
            $normalizedKey = strtolower($normalizedColumn);
            if ($normalizedKey !== '' && isset($normalizedColumns[$normalizedKey])) {
                fclose($handle);
                return Text::_('COM_CONTENTBUILDERNG_CSV_IMPORT_COLUMN_NOT_UNIQUE');
            }
            if ($normalizedKey !== '') {
                $normalizedColumns[$normalizedKey] = true;
            }

            $data['fieldname'] = $column;
            $data['fieldtitle'] = $column;
            $data['is_group'] = false;
            $fieldnames[] = $this->store($data);
            $includedColumnIndexes[] = $index;
            $data['id'] = $this->storageId;
        }

        if ($options->dropRecords) {
            $targetTable = $this->getDatabase()->quoteName('#__' . $this->target_table);
            $this->getDatabase()->setQuery(
                $this->getDatabase()->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($targetTable)
            );
            $droppedDataRecords = (int) $this->getDatabase()->loadResult();
            $db = $this->getDatabase();
            $storageIdValue = (string) $this->storageId;

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__contentbuilderng_records'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('com_contentbuilderng'))
                ->where($db->quoteName('reference_id') . ' = :referenceId')
                ->bind(':referenceId', $storageIdValue);
            $db->setQuery($query);
            $droppedMetaRecords = (int) $db->loadResult();

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__contentbuilderng_articles'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('com_contentbuilderng'))
                ->where($db->quoteName('reference_id') . ' = :referenceId')
                ->bind(':referenceId', $storageIdValue);
            $db->setQuery($query);
            $droppedArticleLinks = (int) $db->loadResult();

            $db->setQuery('TRUNCATE TABLE ' . $targetTable);
            $db->execute();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_records'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('com_contentbuilderng'))
                ->where($db->quoteName('reference_id') . ' = :referenceId')
                ->bind(':referenceId', $storageIdValue);
            $db->setQuery($query);
            $db->execute();

            // DELETE multi-tables (a.*, c.*) : non exprimable via le Query Builder Joomla,
            // fragment MySQL brut conservé avec valeurs quotées.
            $db->setQuery(
                'DELETE a.*, c.* FROM ' . $db->quoteName('#__contentbuilderng_articles', 'a')
                . ' INNER JOIN ' . $db->quoteName('#__content', 'c') . ' ON c.id = a.article_id'
                . ' WHERE a.' . $db->quoteName('type') . ' = ' . $db->quote('com_contentbuilderng')
                . ' AND a.reference_id = ' . $db->quote($storageIdValue)
            );
            $db->execute();
        }

        if ($options->importData && !empty($fieldnames)) {
            $insert_query_prefix = 'INSERT INTO '
                . $this->getDatabase()->quoteName('#__' . $this->target_table)
                . ' (' . implode(',', $fieldnames) . ")\nVALUES";
            $publishedValue = $options->published;

            while (($data = fgetcsv($handle, $max_line_length, $options->delimiter, '"')) !== false) {
                $rowReadCount++;
                while (count($data) < count($columns)) {
                    array_push($data, null);
                }

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

                $rowValues = $importAllColumns
                    ? $data
                    : array_map(static fn ($colIndex) => $data[$colIndex] ?? null, $includedColumnIndexes);

                $query = "$insert_query_prefix (" . join(", ", $this->quoteAllArray($rowValues)) . ")";
                $this->getDatabase()->setQuery($query);
                $this->getDatabase()->execute();
                $db = $this->getDatabase();
                $recordIdValue = (string) intval($db->insertid());
                $referenceIdValue = (string) $this->storageId;
                $insertQuery = $db->getQuery(true)
                    ->insert($db->quoteName('#__contentbuilderng_records'))
                    ->columns($db->quoteName(['type', 'last_update', 'is_future', 'lang_code', 'sef', 'published', 'record_id', 'reference_id']))
                    ->values($db->quote('com_contentbuilderng') . ", :lastUpdate, 0, '*', '', :published, :recordId, :referenceId")
                    ->bind(':lastUpdate', $last_update)
                    ->bind(':published', $publishedValue, ParameterType::INTEGER)
                    ->bind(':recordId', $recordIdValue)
                    ->bind(':referenceId', $referenceIdValue);
                $db->setQuery($insertQuery);
                $db->execute();
                $rowImportedCount++;
            }
        }
        fclose($handle);

        $this->lastImportSummary = [
            'storage_id' => $this->storageId,
            'columns' => count($columns),
            'created_fields' => count($fieldnames),
            'rows_read' => $rowReadCount,
            'rows_imported' => $rowImportedCount,
            'rows_skipped_empty' => $rowSkippedEmptyCount,
            'published' => $options->published,
            'drop_records' => $options->dropRecords,
            'import_data' => $options->importData,
            'dropped_data_records' => $droppedDataRecords,
            'dropped_meta_records' => $droppedMetaRecords,
            'dropped_article_links' => $droppedArticleLinks,
        ];
        return $this->storageId;
    }

    private function quoteAllArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->quoteAllArray($value);
            } else {
                $values[$key] = $this->quoteAll($value);
            }
        }
        return $values;
    }

    private function quoteAll(mixed $value): string
    {
        if (is_null($value)) {
            return "''";
        }

        $value = $this->getDatabase()->quote($value);
        return $value;
    }

    // Give the database tables list.
    public function getDbTables()
    {
        return $this->getComponent()->getContainer()->get(ExternalTableService::class)->getSelectableTables();
    }
}
