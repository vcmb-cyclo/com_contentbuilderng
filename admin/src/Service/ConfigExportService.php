<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

class ConfigExportService
{
    public const ROOT_SECTIONS = ['forms', 'storages'];

    public const FORM_DEPENDENT_SECTIONS = ['elements', 'list_states', 'resource_access'];

    public const STORAGE_DEPENDENT_SECTIONS = ['storage_fields'];

    public const EXPORT_SECTIONS = [
        'component_params' => ['type' => 'component_params'],
        'forms'            => ['type' => 'table', 'table' => '#__contentbuilderng_forms'],
        'elements'         => ['type' => 'table', 'table' => '#__contentbuilderng_elements'],
        'list_states'      => ['type' => 'table', 'table' => '#__contentbuilderng_list_states'],
        'storages'         => ['type' => 'table', 'table' => '#__contentbuilderng_storages'],
        'storage_fields'   => ['type' => 'table', 'table' => '#__contentbuilderng_storage_fields'],
        'resource_access'  => ['type' => 'table', 'table' => '#__contentbuilderng_resource_access'],
        'storage_content'  => ['type' => 'storage_content'],
    ];

    public function resolveEffectiveSections(array $selectedSections, array $selectedFormIds, array $selectedStorageIds): array
    {
        $effective = [];

        foreach ($selectedSections as $sectionKey) {
            if ($sectionKey === 'forms' && $selectedFormIds !== []) {
                $effective[] = $sectionKey;
                continue;
            }

            if ($sectionKey === 'storages' && $selectedStorageIds !== []) {
                $effective[] = $sectionKey;
            }
        }

        return array_values(array_unique($effective));
    }

    public function buildPayload(array $selectedSections, array $selectedFormIds, array $selectedStorageIds, bool $includeStorageContent, int $generatedByUserId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $existingTables = array_map('strtolower', (array) $db->getTableList());
        $exportSections = [];

        foreach ($this->expandSections($selectedSections) as $sectionKey) {
            $sectionConfig = self::EXPORT_SECTIONS[$sectionKey] ?? null;
            if (!is_array($sectionConfig)) {
                continue;
            }

            if (($sectionConfig['type'] ?? '') === 'component_params') {
                $exportSections[$sectionKey] = [
                    'type' => 'component_params',
                    'params' => $this->loadComponentParams($db),
                ];
                continue;
            }

            $tableAlias = (string) ($sectionConfig['table'] ?? '');
            if ($tableAlias === '') {
                continue;
            }

            $tableName = $db->replacePrefix($tableAlias);
            if (!in_array(strtolower($tableName), $existingTables, true)) {
                continue;
            }

            $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
            $query = $db->getQuery(true)->select('*')->from($db->quoteName($tableAlias));
            $this->applyFilters($db, $query, $sectionKey, $columns, $selectedSections, $selectedFormIds, $selectedStorageIds);

            if (in_array('id', $columns, true)) {
                $query->order($db->quoteName('id') . ' ASC');
            }

            $db->setQuery($query);
            $rows = (array) $db->loadAssocList();

            $exportSections[$sectionKey] = [
                'type' => 'table',
                'table' => $tableAlias,
                'row_count' => count($rows),
                'rows' => $rows,
            ];
        }

        if ($includeStorageContent && in_array('storages', $selectedSections, true) && $selectedStorageIds !== []) {
            $exportSections['storage_content'] = $this->buildStorageContentSection($db, $existingTables, $selectedStorageIds);
        }

        return [
            'meta' => [
                'generated_at' => Factory::getDate()->toSql(),
                'generated_by' => $generatedByUserId,
                'component' => 'com_contentbuilderng',
                'format' => 'cbng-config-export-v1',
            ],
            'sections' => $selectedSections,
            'filters' => [
                'form_ids' => $selectedFormIds,
                'storage_ids' => $selectedStorageIds,
                'include_storage_content' => $includeStorageContent ? 1 : 0,
            ],
            'data' => $exportSections,
        ];
    }

    public function buildSummary(array $payload, array $selectedSections, array $selectedFormIds, array $selectedStorageIds, bool $includeStorageContent): array
    {
        $dataSections = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $details = [];
        $rows = 0;

        foreach ($dataSections as $sectionKey => $sectionPayload) {
            if (!is_array($sectionPayload)) {
                continue;
            }
            $rowCount = (int) ($sectionPayload['row_count'] ?? 0);
            $rows += $rowCount;
            $details[] = (string) $sectionKey . ': ' . $rowCount;
        }

        return [
            'status' => 'ok',
            'tables' => count($dataSections),
            'rows' => $rows,
            'sections' => array_values($selectedSections),
            'form_ids' => array_values($selectedFormIds),
            'storage_ids' => array_values($selectedStorageIds),
            'include_storage_content' => $includeStorageContent ? 1 : 0,
            'details' => $details,
        ];
    }

    public function logReport(array $payload, array $selectedSections, array $selectedFormIds, array $selectedStorageIds): void
    {
        $dataSections = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $details = [];
        $rows = 0;

        foreach ($dataSections as $sectionKey => $sectionPayload) {
            if (!is_array($sectionPayload)) {
                continue;
            }
            $type = (string) ($sectionPayload['type'] ?? '');
            if ($type === 'component_params') {
                $details[] = 'component_params: 1';
                continue;
            }
            $rowCount = (int) ($sectionPayload['row_count'] ?? 0);
            $rows += $rowCount;
            $details[] = (string) $sectionKey . ': ' . $rowCount;
        }

        Logger::info('Configuration export completed', [
            'sections' => array_values($selectedSections),
            'form_ids' => array_values($selectedFormIds),
            'storage_ids' => array_values($selectedStorageIds),
            'data_sections' => count($dataSections),
            'rows' => $rows,
            'details' => $details,
        ]);
    }

    private function expandSections(array $selectedSections): array
    {
        $expanded = [];

        foreach ($selectedSections as $sectionKey) {
            if (!in_array($sectionKey, self::ROOT_SECTIONS, true)) {
                continue;
            }

            $expanded[] = $sectionKey;

            if ($sectionKey === 'forms') {
                foreach (self::FORM_DEPENDENT_SECTIONS as $dependentSection) {
                    $expanded[] = $dependentSection;
                }
            }

            if ($sectionKey === 'storages') {
                foreach (self::STORAGE_DEPENDENT_SECTIONS as $dependentSection) {
                    $expanded[] = $dependentSection;
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    private function applyFilters(
        DatabaseInterface $db,
        QueryInterface $query,
        string $sectionKey,
        array $columns,
        array $selectedSections,
        array $selectedFormIds,
        array $selectedStorageIds
    ): void {
        if (in_array('forms', $selectedSections, true) && $selectedFormIds !== []) {
            if ($sectionKey === 'forms' && in_array('id', $columns, true)) {
                $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedFormIds)) . ')');
            }

            if (in_array($sectionKey, self::FORM_DEPENDENT_SECTIONS, true) && in_array('form_id', $columns, true)) {
                $query->where($db->quoteName('form_id') . ' IN (' . implode(',', array_map('intval', $selectedFormIds)) . ')');
            }
        }

        if (in_array('storages', $selectedSections, true) && $selectedStorageIds !== []) {
            if ($sectionKey === 'storages' && in_array('id', $columns, true)) {
                $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedStorageIds)) . ')');
            }

            if ($sectionKey === 'storage_fields' && in_array('storage_id', $columns, true)) {
                $query->where($db->quoteName('storage_id') . ' IN (' . implode(',', array_map('intval', $selectedStorageIds)) . ')');
            }
        }
    }

    private function buildStorageContentSection(DatabaseInterface $db, array $existingTables, array $selectedStorageIds): array
    {
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('name'), $db->quoteName('title'), $db->quoteName('bytable')])
            ->from($db->quoteName('#__contentbuilderng_storages'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedStorageIds)) . ')')
            ->order($db->quoteName('title') . ' ASC, ' . $db->quoteName('name') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($query);
        $storageRows = (array) $db->loadAssocList();

        $storages = [];
        $totalRows = 0;

        foreach ($storageRows as $storageRow) {
            $storageId = (int) ($storageRow['id'] ?? 0);
            $storageName = trim((string) ($storageRow['name'] ?? ''));
            $isBytable = (int) ($storageRow['bytable'] ?? 0) === 1;

            if ($storageId <= 0 || $storageName === '' || $isBytable) {
                continue;
            }

            $tableAlias = $this->resolveStorageTableAlias($db, $existingTables, $storageName);
            if ($tableAlias === null) {
                continue;
            }

            $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
            $contentQuery = $db->getQuery(true)->select('*')->from($db->quoteName($tableAlias));

            if (in_array('id', $columns, true)) {
                $contentQuery->order($db->quoteName('id') . ' ASC');
            }

            $db->setQuery($contentQuery);
            $rows = (array) $db->loadAssocList();
            $rowCount = count($rows);
            $totalRows += $rowCount;

            $storages[] = [
                'storage_id' => $storageId,
                'storage_name' => $storageName,
                'storage_title' => (string) ($storageRow['title'] ?? ''),
                'table' => $tableAlias,
                'row_count' => $rowCount,
                'rows' => $rows,
            ];
        }

        return [
            'type' => 'storage_content',
            'row_count' => $totalRows,
            'storages' => $storages,
        ];
    }

    public function resolveStorageTableAlias(DatabaseInterface $db, array $existingTables, string $storageName): ?string
    {
        $storageName = trim($storageName);
        if ($storageName === '') {
            return null;
        }

        $prefixedAlias = '#__' . $storageName;
        $prefixedName = strtolower($db->replacePrefix($prefixedAlias));
        if (in_array($prefixedName, $existingTables, true)) {
            return $prefixedAlias;
        }

        $plainName = strtolower($storageName);
        if (in_array($plainName, $existingTables, true)) {
            return $storageName;
        }

        return null;
    }

    private function loadComponentParams(DatabaseInterface $db): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'));
        $db->setQuery($query);
        $rawParams = (string) $db->loadResult();

        if ($rawParams === '') {
            return [];
        }

        $decoded = json_decode($rawParams, true);
        return is_array($decoded) ? $decoded : [];
    }
}
