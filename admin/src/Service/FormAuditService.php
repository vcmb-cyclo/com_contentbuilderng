<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceDiagnosticHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use CB\Component\Contentbuilderng\Administrator\types\contentbuilderng_com_breezingformsng;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;

final class FormAuditService
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Audits a form configuration.
     *
     * @return array{
     *   info:array<string,string>,
     *   checks:array<int,array{status:string,message:string,reference?:string,code?:string}>,
     *   performance:array<string,string>,
     *   data:array<string,mixed>,
     *   form?:array{id:int,name:string,title:string}
     * }
     */
    public function audit(int $formId): array
    {
        $componentParams = ComponentHelper::getParams('com_contentbuilderng');
        $auditDetailsTemplateEmpty = (bool) $componentParams->get('audit_details_template_empty', 0);
        $auditFieldMissingInEdit = (bool) $componentParams->get('audit_field_missing_in_edit', 0);
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'id', 'name', 'title', 'type', 'reference_id', 'details_template', 'editable_template', 'theme_plugin',
                'created', 'modified', 'created_by', 'modified_by', 'published', 'debug_mode', 'config',
                'new_button', 'edit_button',
            ]))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . $formId);
        $db->setQuery($query, 0, 1);
        $form = $db->loadAssoc();

        if (!is_array($form)) {
            return [
                'info' => [],
                'checks' => [[
                    'status' => self::STATUS_ERROR,
                    'message' => Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'),
                    'reference' => 'CBNG-AUDIT-FORM-NOT-FOUND',
                ]],
                'performance' => [],
                'data' => [],
            ];
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(['reference_id', 'label', 'published', 'detail_include', 'editable', 'type', 'list_include', 'search_include']))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . $formId)
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $elements = $db->loadAssocList() ?: [];

        $sourceNames = [];
        $sourceTitle = '';
        $sourceAvailable = false;
        try {
            $source = FormSourceFactory::getForm((string) $form['type'], (string) $form['reference_id']);
            // The type classes always return an instance, even when the storage
            // row (or BF form) behind reference_id is gone; only "exists" tells
            // the two apart. Without this check the element list below would be
            // compared against an empty name map and every single field would be
            // reported as an orphan reference, hiding the actual root cause.
            $sourceResolved = is_object($source)
                && (!property_exists($source, 'exists') || (bool) ($source->exists ?? false));
            if ($sourceResolved && method_exists($source, 'getElementNames')) {
                $sourceNames = (array) $source->getElementNames();
                $sourceAvailable = true;
            }
            if (is_object($source) && method_exists($source, 'getTitle')) {
                $sourceTitle = trim((string) $source->getTitle());
            }
        } catch (\Throwable $e) {
            $sourceAvailable = false;
        }

        $recordsTotal = 0;
        $recordsCountUnavailable = false;
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__contentbuilderng_records'))
                ->where($db->quoteName('type') . ' = ' . $db->quote((string) $form['type']))
                ->where($db->quoteName('reference_id') . ' = ' . $db->quote((string) $form['reference_id']));
            $db->setQuery($query);
            $recordsTotal = (int) $db->loadResult();
        } catch (\Throwable $e) {
            // Records table unavailable: keep the count at zero, the audit stays usable,
            // but surface the failure instead of silently reporting zero records.
            $recordsCountUnavailable = true;
        }

        $published = array_values(array_filter($elements, static fn(array $row): bool => (int) $row['published'] === 1));
        $editable = array_values(array_filter($published, static fn(array $row): bool => (int) $row['editable'] === 1));

        $modified = trim((string) ($form['modified'] ?? ''));
        [$groupPermissions, $ownerPermissions, $permissionChecks, $rawGroupPermissions, $rawOwnerPermissions] = $this->auditFrontendPermissions($form);
        $hasFrontendViewPermission = $this->hasFrontendPermission($rawGroupPermissions, $rawOwnerPermissions, 'view');
        $hasFrontendEditPermission = $this->hasFrontendPermission($rawGroupPermissions, $rawOwnerPermissions, 'edit');
        [$performanceInfo, $performanceChecks] = $this->auditPerformance($form, $published, $sourceNames);

        $info = [
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_ID') => (string) (int) $form['id'],
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_FORM') => trim((string) $form['name']) . ' (#' . (int) $form['id'] . ')',
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_PUBLISHED') => (int) ($form['published'] ?? 0) === 1
                ? Text::_('JYES')
                : Text::_('JNO'),
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_DEBUG') => (int) ($form['debug_mode'] ?? 0) === 1
                ? Text::_('JYES')
                : Text::_('JNO'),
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_FRONTEND_PERMISSIONS') => $groupPermissions,
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_FRONTEND_OWNER_PERMISSIONS') => $ownerPermissions,
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_CREATED') => $this->formatAuditDate((string) ($form['created'] ?? '')),
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_CREATED_BY') => trim((string) ($form['created_by'] ?? '')) !== ''
                ? (string) $form['created_by']
                : Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_UNAVAILABLE'),
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_MODIFIED') => $modified !== ''
                ? $this->formatAuditDate($modified)
                : Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_UNAVAILABLE'),
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_MODIFIED_BY') => trim((string) ($form['modified_by'] ?? '')) !== ''
                ? (string) $form['modified_by']
                : Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_UNAVAILABLE'),
        ] + $performanceInfo;

        $checks = array_merge(
            $recordsCountUnavailable ? [[
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_RECORDS_COUNT_UNAVAILABLE'),
                'reference' => 'CBNG-AUDIT-RECORDS-COUNT-UNAVAILABLE',
            ]] : [],
            $this->checkTheme((string) ($form['theme_plugin'] ?? '')),
            $this->checkSourceSync($elements, $sourceNames, $sourceAvailable, (string) $form['type'], (string) $form['reference_id']),
            $this->checkElementReferences($elements),
            $this->checkTemplates(
                $published,
                $sourceNames,
                (string) $form['type'],
                (string) $form['details_template'],
                (string) $form['editable_template'],
                $hasFrontendViewPermission,
                $hasFrontendEditPermission,
                $auditDetailsTemplateEmpty,
                $auditFieldMissingInEdit
            ),
            $permissionChecks,
            $performanceChecks
        );

        if ($checks === []) {
            $checks[] = [
                'status' => self::STATUS_OK,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_ALL_OK'),
            ];
        }

        return [
            'info' => $info,
            'checks' => $checks,
            'performance' => $performanceInfo,
            'data' => [
                'id' => (int) $form['id'],
                'name' => trim((string) $form['name']),
                'source_type' => (string) $form['type'],
                'source_reference_id' => (int) $form['reference_id'],
                'source_title' => $sourceTitle,
                'elements_total' => count($elements),
                'elements_published' => count($published),
                'elements_editable' => count($editable),
                'records_total' => $recordsTotal,
                'records_count_available' => !$recordsCountUnavailable,
                'published' => (int) ($form['published'] ?? 0) === 1,
                'debug_mode' => (int) ($form['debug_mode'] ?? 0) === 1,
            ],
            'form' => [
                'id' => (int) $form['id'],
                'name' => trim((string) $form['name']),
                'title' => trim((string) $form['title']),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $form
     * @return array{0:string,1:string,2:array<int,array{status:string,message:string,reference?:string}>,3:array<string,mixed>,4:array<string,mixed>}
     */
    private function auditFrontendPermissions(array $form): array
    {
        $rawConfig = (string) ($form['config'] ?? '');
        $config = $rawConfig === ''
            ? []
            : PackedDataHelper::decodePackedData($rawConfig, null, true);

        if (!is_array($config)) {
            return [
                Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_UNAVAILABLE'),
                Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_UNAVAILABLE'),
                [[
                    'status' => self::STATUS_ERROR,
                    'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_FRONTEND_PERMISSIONS_INVALID'),
                    'reference' => 'CBNG-AUDIT-FRONTEND-PERMISSIONS-INVALID',
                ]],
                [],
                [],
            ];
        }

        $actionLabels = [
            'listaccess' => Text::_('COM_CONTENTBUILDERNG_PERM_LIST_ACCESS'),
            'view' => Text::_('COM_CONTENTBUILDERNG_PERM_VIEW'),
            'new' => Text::_('COM_CONTENTBUILDERNG_PERM_NEW'),
            'edit' => Text::_('COM_CONTENTBUILDERNG_PERM_EDIT'),
            'delete' => Text::_('COM_CONTENTBUILDERNG_PERM_DELETE'),
            'state' => Text::_('COM_CONTENTBUILDERNG_PERM_STATE'),
            'publish' => Text::_('COM_CONTENTBUILDERNG_PUBLISH'),
            'api' => Text::_('COM_CONTENTBUILDERNG_PERM_API'),
            'stats' => Text::_('COM_CONTENTBUILDERNG_PERM_STATS'),
            'fullarticle' => Text::_('COM_CONTENTBUILDERNG_PERM_FULL_ARTICLE'),
            'language' => Text::_('COM_CONTENTBUILDERNG_PERM_CHANGE_LANGUAGE'),
            'rating' => Text::_('COM_CONTENTBUILDERNG_PERM_RATING'),
        ];
        $permissions = (array) ($config['permissions_fe'] ?? []);
        $ownerPermissions = (array) ($config['own_fe'] ?? []);
        $groupTitles = $this->loadUserGroupTitles();
        $groupSummaries = [];

        foreach ($permissions as $groupId => $groupPermission) {
            if (!is_array($groupPermission)) {
                continue;
            }

            $granted = $this->getGrantedPermissionLabels($groupPermission, $actionLabels);
            if ($granted === []) {
                continue;
            }

            $numericGroupId = (int) $groupId;
            $groupTitle = $groupTitles[$numericGroupId]
                ?? Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_INFO_FRONTEND_UNKNOWN_GROUP', $numericGroupId);
            $groupSummaries[] = $groupTitle . ': ' . implode(', ', $granted);
        }

        $grantedOwnerPermissions = $this->getGrantedPermissionLabels($ownerPermissions, $actionLabels);
        $checks = [];

        if ($groupSummaries === [] && $grantedOwnerPermissions === []) {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_FRONTEND_PERMISSIONS_EMPTY'),
                'reference' => 'CBNG-AUDIT-FRONTEND-PERMISSIONS-EMPTY',
            ];
        }

        if (!empty($form['new_button']) && !$this->hasFrontendPermission($permissions, $ownerPermissions, 'new')) {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_FRONTEND_NEW_WITHOUT_PERMISSION'),
                'reference' => 'CBNG-AUDIT-FRONTEND-NEW-WITHOUT-PERMISSION',
            ];
        }

        if (!empty($form['edit_button']) && !$this->hasFrontendPermission($permissions, $ownerPermissions, 'edit')) {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_FRONTEND_EDIT_WITHOUT_PERMISSION'),
                'reference' => 'CBNG-AUDIT-FRONTEND-EDIT-WITHOUT-PERMISSION',
            ];
        }

        return [
            $groupSummaries !== []
                ? implode(' ; ', $groupSummaries)
                : Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_NONE'),
            $grantedOwnerPermissions !== []
                ? implode(', ', $grantedOwnerPermissions)
                : Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_NONE'),
            $checks,
            $permissions,
            $ownerPermissions,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function loadUserGroupTitles(): array
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'title']))
                ->from($this->db->quoteName('#__usergroups'))
                ->order($this->db->quoteName('lft') . ' ASC');
            $this->db->setQuery($query);
            $rows = $this->db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $titles = [];
        foreach ($rows as $row) {
            $groupId = (int) ($row['id'] ?? 0);
            if ($groupId > 0) {
                $titles[$groupId] = (string) ($row['title'] ?? $groupId);
            }
        }

        return $titles;
    }

    /**
     * @param array<string,mixed> $permissions
     * @param array<string,string> $actionLabels
     * @return array<int,string>
     */
    private function getGrantedPermissionLabels(array $permissions, array $actionLabels): array
    {
        $labels = [];

        foreach ($actionLabels as $action => $label) {
            if (!empty($permissions[$action])) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $groupPermissions
     * @param array<string,mixed> $ownerPermissions
     */
    private function hasFrontendPermission(array $groupPermissions, array $ownerPermissions, string $action): bool
    {
        if (!empty($ownerPermissions[$action])) {
            return true;
        }

        foreach ($groupPermissions as $permissions) {
            if (is_array($permissions) && !empty($permissions[$action])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Volumétrie et temps de requête sur la table physique du storage
     * (uniquement pour les formulaires adossés à un Storage CBNG : la source
     * BreezingForms utilise un stockage EAV normalisé pour lequel une mesure
     * de table unique n'aurait pas de sens comparable).
     *
     * @param array<string,mixed> $form
     * @param array<int,array<string,mixed>> $publishedElements
     * @param array<int|string,string> $sourceNames
     * @return array{0:array<string,string>,1:array<int,array{status:string,message:string,reference?:string,code?:string}>}
     */
    private function auditPerformance(array $form, array $publishedElements, array $sourceNames): array
    {
        if ((string) ($form['type'] ?? '') !== 'com_contentbuilderng') {
            return [[], []];
        }

        $db = $this->db;
        $storageId = (int) ($form['reference_id'] ?? 0);

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['name', 'bytable']))
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where($db->quoteName('id') . ' = ' . $storageId);
            $db->setQuery($query);
            $storageRow = $db->loadAssoc();
        } catch (\Throwable $e) {
            $storageRow = null;
        }

        $storageName = trim((string) ($storageRow['name'] ?? ''));
        if (!is_array($storageRow) || $storageName === '') {
            return [[], [[
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_PERFORMANCE_STORAGE_UNAVAILABLE'),
                'reference' => 'CBNG-AUDIT-PERFORMANCE-STORAGE-UNAVAILABLE',
                'code' => 'performance',
            ]]];
        }

        $bytable = (int) ($storageRow['bytable'] ?? 0);
        $tableName = ($bytable > 0 ? '' : '#__') . $storageName;

        $rowCount = null;
        $rowCountMs = null;
        try {
            $start = microtime(true);
            $countQuery = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName($tableName));
            $db->setQuery($countQuery);
            $rowCount = (int) $db->loadResult();
            $rowCountMs = (microtime(true) - $start) * 1000;
        } catch (\Throwable $e) {
            // Table missing/unreadable: surfaced via the checks below.
        }

        if ($rowCount === null) {
            return [[], [[
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_PERFORMANCE_TABLE_UNAVAILABLE'),
                'reference' => 'CBNG-AUDIT-PERFORMANCE-TABLE-UNAVAILABLE',
                'code' => 'performance',
            ]]];
        }

        $listQueryMs = null;
        try {
            $start = microtime(true);
            $listQuery = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName($tableName))
                ->order($db->quoteName('id') . ' DESC');
            $db->setQuery($listQuery, 0, 20);
            $db->loadColumn();
            $listQueryMs = (microtime(true) - $start) * 1000;
        } catch (\Throwable $e) {
            // Keep $listQueryMs null: reported as unavailable below.
        }

        $info = [
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_PERFORMANCE_TABLE_ROWS') => Text::plural(
                'COM_CONTENTBUILDERNG_AUDIT_INFO_PERFORMANCE_ROWS_VALUE',
                $rowCount,
                number_format($rowCount, 0, ',', ' '),
                number_format($rowCountMs ?? 0, 1)
            ),
            Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_PERFORMANCE_LIST_QUERY') => $listQueryMs !== null
                ? Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_INFO_PERFORMANCE_QUERY_VALUE', number_format($listQueryMs, 1))
                : Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_UNAVAILABLE'),
        ];

        $checks = [];
        $slowQueryThresholdMs = 250.0;
        if ($listQueryMs !== null && $listQueryMs > $slowQueryThresholdMs) {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::sprintf(
                    'COM_CONTENTBUILDERNG_AUDIT_CHECK_PERFORMANCE_SLOW_LIST_QUERY',
                    number_format($listQueryMs, 1)
                ),
                'reference' => 'CBNG-AUDIT-PERFORMANCE-SLOW-LIST-QUERY',
                'code' => 'performance',
            ];
        }

        $checks = array_merge($checks, $this->checkStorageIndexes($tableName, $publishedElements, $sourceNames));

        return [$info, $checks];
    }

    /**
     * Signale les colonnes utilisées pour le tri/la recherche frontend
     * (list_include/search_include) qui n'ont pas d'index en base : ce sont
     * les candidates les plus probables à un ralentissement des listes sur
     * une volumétrie importante.
     *
     * @param array<int,array<string,mixed>> $publishedElements
     * @param array<int|string,string> $sourceNames
     * @return array<int,array{status:string,message:string,reference?:string,code?:string}>
     */
    private function checkStorageIndexes(string $tableName, array $publishedElements, array $sourceNames): array
    {
        $db = $this->db;

        try {
            $realTableName = $db->replacePrefix($tableName);
            $indexQuery = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('COLUMN_NAME'))
                ->from($db->quoteName('INFORMATION_SCHEMA.STATISTICS'))
                ->where($db->quoteName('TABLE_SCHEMA') . ' = DATABASE()')
                ->where($db->quoteName('TABLE_NAME') . ' = :tableName')
                ->bind(':tableName', $realTableName);
            $db->setQuery($indexQuery);
            $indexedColumns = array_map('strtolower', array_map('strval', $db->loadColumn() ?: []));
        } catch (\Throwable $e) {
            // Index metadata unavailable: skip this check rather than guessing.
            return [];
        }

        $unindexed = [];
        foreach ($publishedElements as $element) {
            if (empty($element['list_include']) && empty($element['search_include'])) {
                continue;
            }

            $referenceId = (string) ($element['reference_id'] ?? '');
            $columnName = trim((string) ($sourceNames[$referenceId] ?? ''));
            if ($columnName === '' || in_array(strtolower($columnName), $unindexed, true)) {
                continue;
            }

            if (!in_array(strtolower($columnName), $indexedColumns, true)) {
                $unindexed[] = $columnName;
            }
        }

        if ($unindexed === []) {
            return [];
        }

        return [[
            'status' => self::STATUS_WARNING,
            'message' => Text::sprintf(
                'COM_CONTENTBUILDERNG_AUDIT_CHECK_PERFORMANCE_UNINDEXED_COLUMNS',
                implode(', ', $unindexed)
            ),
            'reference' => 'CBNG-AUDIT-PERFORMANCE-UNINDEXED-COLUMNS',
            'code' => 'unindexed_columns',
        ]];
    }

    private function formatAuditDate(string $value): string
    {
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return Text::_('COM_CONTENTBUILDERNG_AUDIT_INFO_UNAVAILABLE');
        }

        return HTMLHelper::_('date', $value, Text::_('DATE_FORMAT_LC5'));
    }

    /**
     * @param array<int,array<string,mixed>> $elements
     * @param array<int|string,string> $sourceNames
     * @return array<int,array{status:string,message:string,reference?:string,code?:string}>
     */
    private function checkSourceSync(array $elements, array $sourceNames, bool $sourceAvailable, string $sourceType, string $sourceReferenceId): array
    {
        $checks = [];
        $referenced = [];

        if (!$sourceAvailable) {
            $checks[] = [
                'status' => self::STATUS_ERROR,
                'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_SOURCE_UNAVAILABLE', $sourceType, $sourceReferenceId)
                    . ' ' . FormSourceDiagnosticHelper::describe($sourceType, $sourceReferenceId),
                'reference' => 'CBNG-AUDIT-SOURCE-UNAVAILABLE',
            ];

            return $checks;
        }

        foreach ($elements as $element) {
            $referenceId = (string) $element['reference_id'];
            $referenced[$referenceId] = true;

            if (!isset($sourceNames[$referenceId])) {
                $checks[] = [
                    'status' => self::STATUS_ERROR,
                    'message' => Text::sprintf(
                        'COM_CONTENTBUILDERNG_AUDIT_CHECK_SOURCE_MISSING',
                        (string) $element['label'],
                        $referenceId
                    ),
                    'reference' => 'CBNG-AUDIT-SOURCE-MISSING',
                    'code' => 'element_reference',
                ];
            }
        }

        foreach ($sourceNames as $referenceId => $name) {
            if ($this->isIgnoredUnsyncedSourceField($sourceType, (string) $name)) {
                continue;
            }

            if (!isset($referenced[(string) $referenceId])) {
                $checks[] = [
                    'status' => self::STATUS_WARNING,
                    'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_SOURCE_UNSYNCED', (string) $name),
                    'reference' => 'CBNG-AUDIT-SOURCE-UNSYNCED',
                ];
            }
        }

        return $checks;
    }

    private function isIgnoredUnsyncedSourceField(string $sourceType, string $name): bool
    {
        if ($sourceType !== 'com_breezingformsng') {
            return false;
        }

        $fieldName = trim($name);
        if ($fieldName === '') {
            return false;
        }

        return $this->isBreezingFormsSystemFieldName($fieldName);
    }

    private function isBreezingFormsSystemFieldName(string $fieldName): bool
    {
        return array_key_exists($fieldName, $this->getBreezingFormsSystemFieldDefinitionsByName());
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function getBreezingFormsSystemFieldDefinitionsByName(): array
    {
        static $definitionsByName = null;

        if (is_array($definitionsByName)) {
            return $definitionsByName;
        }

        if (!class_exists(contentbuilderng_com_breezingformsng::class)) {
            $file = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/types/com_breezingformsng.php';
            if (is_file($file)) {
                require_once $file;
            }
        }

        if (!class_exists(contentbuilderng_com_breezingformsng::class)) {
            return $definitionsByName = [
                'bf_viewed' => ['label' => 'bf_viewed', 'name' => 'bf_viewed'],
                'bf_exported' => ['label' => 'bf_exported', 'name' => 'bf_exported'],
                'bf_archived' => ['label' => 'bf_archived', 'name' => 'bf_archived'],
            ];
        }

        $definitionsByName = [];
        foreach (contentbuilderng_com_breezingformsng::getSystemFieldDefinitions() as $definition) {
            $fieldName = trim((string) ($definition['name'] ?? ''));
            if ($fieldName !== '') {
                $definitionsByName[$fieldName] = $definition;
            }
        }

        return $definitionsByName;
    }

    /**
     * @return array<int,array{status:string,message:string,reference?:string,code?:string}>
     */
    private function checkTheme(string $themePlugin): array
    {
        $themePlugin = trim($themePlugin);
        if ($themePlugin === '') {
            return [[
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_THEME_EMPTY'),
                'reference' => 'CBNG-AUDIT-THEME-EMPTY',
                'code' => 'theme_empty',
            ]];
        }

        if (in_array($themePlugin, ['joomla3', 'joomla6'], true)) {
            return [[
                'status' => self::STATUS_ERROR,
                'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_THEME_LEGACY', $themePlugin),
                'reference' => 'CBNG-AUDIT-THEME-LEGACY',
            ]];
        }

        if (!PluginHelper::isEnabled('contentbuilderng_themes', $themePlugin)) {
            return [[
                'status' => self::STATUS_ERROR,
                'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_THEME_DISABLED', $themePlugin),
                'reference' => 'CBNG-AUDIT-THEME-DISABLED',
            ]];
        }

        return [];
    }

    /**
     * @param array<int,array<string,mixed>> $elements
     * @return array<int,array{status:string,message:string,reference?:string,code?:string}>
     */
    private function checkElementReferences(array $elements): array
    {
        $checks = [];
        $labelsByReference = [];

        foreach ($elements as $element) {
            $referenceId = trim((string) ($element['reference_id'] ?? ''));
            if ($referenceId === '') {
                $checks[] = [
                    'status' => self::STATUS_ERROR,
                    'message' => Text::sprintf(
                        'COM_CONTENTBUILDERNG_AUDIT_CHECK_ELEMENT_REFERENCE_EMPTY',
                        (string) ($element['label'] ?? '')
                    ),
                    'reference' => 'CBNG-AUDIT-ELEMENT-REFERENCE-EMPTY',
                    'code' => 'element_reference',
                ];
                continue;
            }

            $labelsByReference[$referenceId][] = (string) ($element['label'] ?? $referenceId);
        }

        foreach ($labelsByReference as $referenceId => $labels) {
            if (count($labels) < 2) {
                continue;
            }

            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::sprintf(
                    'COM_CONTENTBUILDERNG_AUDIT_CHECK_ELEMENT_REFERENCE_DUPLICATE',
                    $referenceId,
                    implode(', ', $labels)
                ),
                'reference' => 'CBNG-AUDIT-ELEMENT-REFERENCE-DUPLICATE',
                'code' => 'element_reference',
            ];
        }

        return $checks;
    }

    /**
     * @param array<int,array<string,mixed>> $published
     * @param array<int|string,string> $sourceNames
     * @return array<int,array{status:string,message:string,reference?:string}>
     */
    private function checkTemplates(
        array $published,
        array $sourceNames,
        string $sourceType,
        string $detailsTemplate,
        string $editableTemplate,
        bool $hasFrontendViewPermission,
        bool $hasFrontendEditPermission,
        bool $auditDetailsTemplateEmpty,
        bool $auditFieldMissingInEdit
    ): array {
        $checks = [];
        $hasPublishedDetailElement = false;
        $hasPublishedEditableElement = false;
        foreach ($published as $element) {
            $hasPublishedDetailElement = $hasPublishedDetailElement || (int) ($element['detail_include'] ?? 0) === 1;
            $hasPublishedEditableElement = $hasPublishedEditableElement || (int) ($element['editable'] ?? 0) === 1;
        }

        $detailsEmpty = $hasPublishedDetailElement && trim($detailsTemplate) === '' && $hasFrontendViewPermission;
        $editableEmpty = $hasPublishedEditableElement && trim($editableTemplate) === '' && $hasFrontendEditPermission;
        $detailsTemplateCheckEnabled = $detailsEmpty && $auditDetailsTemplateEmpty;

        if ($detailsTemplateCheckEnabled && $editableEmpty) {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_TEMPLATES_EMPTY'),
                'reference' => 'CBNG-AUDIT-TEMPLATES-EMPTY',
                'code' => 'templates_empty',
            ];
        } elseif ($detailsTemplateCheckEnabled) {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_DETAILS_TEMPLATE_EMPTY'),
                'reference' => 'CBNG-AUDIT-DETAILS-TEMPLATE-EMPTY',
                'code' => 'details_template_empty',
            ];
        } elseif ($editableEmpty) {
            $checks[] = [
                'status' => self::STATUS_WARNING,
                'message' => Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_EDITABLE_TEMPLATE_EMPTY'),
                'reference' => 'CBNG-AUDIT-EDITABLE-TEMPLATE-EMPTY',
                'code' => 'editable_template_empty',
            ];
        }

        foreach ($published as $element) {
            $referenceId = (string) $element['reference_id'];
            $name = (string) ($sourceNames[$referenceId] ?? '');
            if ($name === '') {
                continue;
            }
            $isSystemField = $this->isBreezingFormsSourceType($sourceType) && $this->isBreezingFormsSystemFieldName($name);
            $auditLabel = $this->formatSourceFieldAuditLabel($sourceType, $name);

            $quoted = preg_quote($name, '/');
            $inDetails = (bool) preg_match('/\\{' . $quoted . ':(label|value|item)\\}/i', $detailsTemplate);
            $hasEditItem = (bool) preg_match('/\\{' . $quoted . ':item\\}/i', $editableTemplate);
            $hasEditAny = $hasEditItem || preg_match('/\\{' . $quoted . ':(label|value)\\}/i', $editableTemplate);
            $isEditable = (int) $element['editable'] === 1;

            if ($detailsTemplate !== '' && !$inDetails && $auditDetailsTemplateEmpty) {
                $checks[] = [
                    'status' => self::STATUS_WARNING,
                    'message' => Text::sprintf(
                        $isSystemField ? 'COM_CONTENTBUILDERNG_AUDIT_CHECK_SYSTEM_MISSING_IN_DETAILS' : 'COM_CONTENTBUILDERNG_AUDIT_CHECK_MISSING_IN_DETAILS',
                        $auditLabel
                    ),
                    'reference' => $isSystemField ? 'CBNG-AUDIT-SYSTEM-FIELD-MISSING-IN-DETAILS' : 'CBNG-AUDIT-FIELD-MISSING-IN-DETAILS',
                ];
            }

            if ($editableTemplate !== '' && !$hasEditAny && $auditFieldMissingInEdit) {
                $checks[] = [
                    'status' => self::STATUS_WARNING,
                    'message' => Text::sprintf(
                        $isSystemField ? 'COM_CONTENTBUILDERNG_AUDIT_CHECK_SYSTEM_MISSING_IN_EDIT' : 'COM_CONTENTBUILDERNG_AUDIT_CHECK_MISSING_IN_EDIT',
                        $auditLabel
                    ),
                    'reference' => $isSystemField ? 'CBNG-AUDIT-SYSTEM-FIELD-MISSING-IN-EDIT' : 'CBNG-AUDIT-FIELD-MISSING-IN-EDIT',
                ];
            }

            if ($editableTemplate !== '' && $isEditable && $hasEditAny && !$hasEditItem) {
                $checks[] = [
                    'status' => self::STATUS_ERROR,
                    'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_EDITABLE_WITHOUT_ITEM', $name),
                    'reference' => 'CBNG-AUDIT-EDITABLE-FIELD-WITHOUT-ITEM',
                    'code' => 'editable_field_without_item',
                    'field' => $name,
                ];
            }

            if ($editableTemplate !== '' && !$isEditable && $hasEditItem) {
                $checks[] = [
                    'status' => self::STATUS_WARNING,
                    'message' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_CHECK_NONEDITABLE_WITH_ITEM', $name),
                    'reference' => 'CBNG-AUDIT-NONEDITABLE-FIELD-WITH-ITEM',
                ];
            }
        }

        $lowerNames = array_map(
            static fn($name): string => function_exists('mb_strtolower') ? mb_strtolower((string) $name, 'UTF-8') : strtolower((string) $name),
            array_values($sourceNames)
        );

        foreach (
            [
                'COM_CONTENTBUILDERNG_AUDIT_CHECK_UNKNOWN_MARKER_DETAILS' => [
                    'template' => $detailsTemplate,
                    'code' => 'unknown_marker_details',
                    'template_type' => 'details',
                ],
                'COM_CONTENTBUILDERNG_AUDIT_CHECK_UNKNOWN_MARKER_EDIT' => [
                    'template' => $editableTemplate,
                    'code' => 'unknown_marker_edit',
                    'template_type' => 'edit',
                ],
            ] as $key => $definition
        ) {
            $template = $definition['template'];

            foreach ($this->extractMarkerNames($template) as $markerName) {
                $needle = function_exists('mb_strtolower') ? mb_strtolower($markerName, 'UTF-8') : strtolower($markerName);
                if (!in_array($needle, $lowerNames, true)) {
                    $checks[] = [
                        'status' => self::STATUS_ERROR,
                        'message' => Text::sprintf($key, $markerName),
                        'reference' => $key === 'COM_CONTENTBUILDERNG_AUDIT_CHECK_UNKNOWN_MARKER_DETAILS'
                            ? 'CBNG-AUDIT-UNKNOWN-MARKER-DETAILS'
                            : 'CBNG-AUDIT-UNKNOWN-MARKER-EDIT',
                        'code' => $definition['code'],
                        'field' => $markerName,
                        'template_type' => $definition['template_type'],
                    ];
                }
            }
        }

        return $checks;
    }

    private function isBreezingFormsSourceType(string $sourceType): bool
    {
        return $sourceType === 'com_breezingformsng';
    }

    private function formatSourceFieldAuditLabel(string $sourceType, string $name): string
    {
        if (!$this->isBreezingFormsSourceType($sourceType)) {
            return $name;
        }

        $definition = $this->getBreezingFormsSystemFieldDefinitionsByName()[$name] ?? null;
        if (!is_array($definition)) {
            return $name;
        }

        $label = trim((string) ($definition['label'] ?? ''));
        if ($label === '') {
            return $name;
        }

        return $label . ' (' . $name . ')';
    }

    /**
     * @return array<int,string>
     */
    private function extractMarkerNames(string $template): array
    {
        if ($template === '' || !preg_match_all('/\\{([^}:{]+):(label|value|item)\\}/i', $template, $matches)) {
            return [];
        }

        return array_values(array_unique(array_map(static fn($name): string => trim((string) $name), $matches[1])));
    }
}
