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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class ConfigImportService
{
    public const MODE_MERGE = 'merge';
    public const MODE_REPLACE = 'replace';

    public function filterPayload(
        array $payload,
        array $selectedSections,
        array $selectedFormNames,
        array $selectedStorageNames,
        array $selectedStorageContentNames = []
    ): array {
        $dataSections = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (in_array('forms', $selectedSections, true) && $selectedFormNames !== []) {
            $formRows = is_array($dataSections['forms']['rows'] ?? null) ? $dataSections['forms']['rows'] : [];
            $selectedFormMap = array_fill_keys($selectedFormNames, true);
            $selectedFormIds = [];
            $filteredFormRows = [];

            foreach ($formRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $formName = trim((string) ($row['name'] ?? ''));
                if ($formName === '' || !isset($selectedFormMap[$formName])) {
                    continue;
                }
                $filteredFormRows[] = $row;
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId > 0) {
                    $selectedFormIds[$rowId] = true;
                }
            }

            if (isset($dataSections['forms']) && is_array($dataSections['forms'])) {
                $dataSections['forms']['rows'] = array_values($filteredFormRows);
                $dataSections['forms']['row_count'] = count($filteredFormRows);
            }

            $sourceFormIds = array_keys($selectedFormIds);
            foreach (ConfigExportService::FORM_DEPENDENT_SECTIONS as $sectionKey) {
                $rows = is_array($dataSections[$sectionKey]['rows'] ?? null) ? $dataSections[$sectionKey]['rows'] : [];
                $filteredRows = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $formId = (int) ($row['form_id'] ?? 0);
                    if ($formId > 0 && isset($selectedFormIds[$formId])) {
                        $filteredRows[] = $row;
                    }
                }
                if (isset($dataSections[$sectionKey]) && is_array($dataSections[$sectionKey])) {
                    $dataSections[$sectionKey]['rows'] = array_values($filteredRows);
                    $dataSections[$sectionKey]['row_count'] = count($filteredRows);
                }
            }

            if (isset($payload['filters']) && is_array($payload['filters'])) {
                $payload['filters']['form_ids'] = array_map('intval', $sourceFormIds);
            }
        }

        if (in_array('storages', $selectedSections, true) && $selectedStorageNames !== []) {
            $storageRows = is_array($dataSections['storages']['rows'] ?? null) ? $dataSections['storages']['rows'] : [];
            $selectedStorageMap = array_fill_keys($selectedStorageNames, true);
            $selectedStorageIds = [];
            $filteredStorageRows = [];

            foreach ($storageRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $storageName = trim((string) ($row['name'] ?? ''));
                if ($storageName === '' || !isset($selectedStorageMap[$storageName])) {
                    continue;
                }
                $filteredStorageRows[] = $row;
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId > 0) {
                    $selectedStorageIds[$rowId] = true;
                }
            }

            if (isset($dataSections['storages']) && is_array($dataSections['storages'])) {
                $dataSections['storages']['rows'] = array_values($filteredStorageRows);
                $dataSections['storages']['row_count'] = count($filteredStorageRows);
            }

            $storageFieldRows = is_array($dataSections['storage_fields']['rows'] ?? null) ? $dataSections['storage_fields']['rows'] : [];
            $filteredFieldRows = [];
            foreach ($storageFieldRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $storageId = (int) ($row['storage_id'] ?? 0);
                if ($storageId > 0 && isset($selectedStorageIds[$storageId])) {
                    $filteredFieldRows[] = $row;
                }
            }

            if (isset($dataSections['storage_fields']) && is_array($dataSections['storage_fields'])) {
                $dataSections['storage_fields']['rows'] = array_values($filteredFieldRows);
                $dataSections['storage_fields']['row_count'] = count($filteredFieldRows);
            }

            if (isset($payload['filters']) && is_array($payload['filters'])) {
                $payload['filters']['storage_ids'] = array_map('intval', array_keys($selectedStorageIds));
            }

            $storageContentStorages = is_array($dataSections['storage_content']['storages'] ?? null)
                ? $dataSections['storage_content']['storages']
                : [];
            $filteredContentStorages = [];
            foreach ($storageContentStorages as $storageContentEntry) {
                if (!is_array($storageContentEntry)) {
                    continue;
                }
                $storageId = (int) ($storageContentEntry['storage_id'] ?? 0);
                if ($storageId > 0 && isset($selectedStorageIds[$storageId])) {
                    $filteredContentStorages[] = $storageContentEntry;
                }
            }

            if (isset($dataSections['storage_content']) && is_array($dataSections['storage_content'])) {
                if ($selectedStorageContentNames !== []) {
                    $selectedStorageContentMap = array_fill_keys($selectedStorageContentNames, true);
                    $filteredContentStorages = array_values(array_filter(
                        $filteredContentStorages,
                        static fn(array $entry): bool => isset($selectedStorageContentMap[(string) ($entry['storage_name'] ?? '')])
                    ));
                } else {
                    $filteredContentStorages = [];
                }

                $dataSections['storage_content']['storages'] = array_values($filteredContentStorages);
                $dataSections['storage_content']['row_count'] = array_sum(array_map(
                    static fn(array $entry): int => (int) ($entry['row_count'] ?? 0),
                    $filteredContentStorages
                ));
            }
        }

        $payload['data'] = $dataSections;
        return $payload;
    }

    public function applyPayload(array $payload, array $selectedSections, string $importMode): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableRowsImported = 0;
        $tablesImported = 0;
        $details = [];

        $dataSections = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        // Backward compatibility with older export format.
        if ($dataSections === []) {
            if (isset($payload['component_params']) && is_array($payload['component_params'])) {
                $dataSections['component_params'] = [
                    'type' => 'component_params',
                    'params' => $payload['component_params'],
                ];
            }

            $legacyTables = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];
            foreach ($legacyTables as $tableEntry) {
                if (!is_array($tableEntry)) {
                    continue;
                }
                $legacyTableAlias = (string) ($tableEntry['table'] ?? '');
                $legacyRows = is_array($tableEntry['rows'] ?? null) ? $tableEntry['rows'] : [];
                foreach (ConfigExportService::EXPORT_SECTIONS as $sectionKey => $sectionConfig) {
                    if (($sectionConfig['type'] ?? '') !== 'table') {
                        continue;
                    }
                    if ((string) ($sectionConfig['table'] ?? '') === $legacyTableAlias) {
                        $dataSections[$sectionKey] = [
                            'type' => 'table',
                            'table' => $legacyTableAlias,
                            'rows' => $legacyRows,
                        ];
                    }
                }
            }
        }

        $db->transactionStart();

        try {
            foreach ($selectedSections as $sectionKey) {
                if ($sectionKey === 'component_params') {
                    $sectionPayload = $dataSections['component_params'] ?? null;
                    if (!is_array($sectionPayload)) {
                        $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'component_params');
                        continue;
                    }

                    $params = is_array($sectionPayload['params'] ?? null) ? $sectionPayload['params'] : [];
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'));
                    $db->setQuery($query)->execute();
                    $details[] = Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_PARAMS_UPDATED');
                    continue;
                }

                if ($sectionKey === 'forms') {
                    $summary = $this->importFormsConfiguration($db, $dataSections, $importMode);
                    $tableRowsImported += (int) ($summary['rows'] ?? 0);
                    $tablesImported += (int) ($summary['tables'] ?? 0);
                    foreach ((array) ($summary['details'] ?? []) as $detail) {
                        $details[] = (string) $detail;
                    }
                    continue;
                }

                if ($sectionKey === 'storages') {
                    $summary = $this->importStoragesConfiguration($db, $dataSections, $importMode);
                    $tableRowsImported += (int) ($summary['rows'] ?? 0);
                    $tablesImported += (int) ($summary['tables'] ?? 0);
                    foreach ((array) ($summary['details'] ?? []) as $detail) {
                        $details[] = (string) $detail;
                    }
                }
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }

        return [
            'status' => 'ok',
            'tables' => $tablesImported,
            'rows' => $tableRowsImported,
            'details' => $details,
            'highlights' => array_values(array_filter(
                array_map('strval', $details),
                static fn(string $detail): bool => str_starts_with($detail, '[UPDATED] ')
            )),
        ];
    }

    public function logReport(array $summary, array $selectedSections, string $importMode): void
    {
        $details = array_values(array_filter(
            array_map('strval', (array) ($summary['details'] ?? [])),
            static fn(string $detail): bool => trim($detail) !== ''
        ));

        Logger::info('Configuration import completed', [
            'mode' => $importMode,
            'status' => (string) ($summary['status'] ?? 'ok'),
            'sections' => array_values($selectedSections),
            'tables' => (int) ($summary['tables'] ?? 0),
            'rows' => (int) ($summary['rows'] ?? 0),
            'details_count' => count($details),
            'highlights_count' => count((array) ($summary['highlights'] ?? [])),
        ]);

        foreach ($details as $detail) {
            Logger::info('Configuration import detail', [
                'mode' => $importMode,
                'detail' => $detail,
            ]);
        }

        foreach ((array) ($summary['highlights'] ?? []) as $highlight) {
            $highlight = trim((string) $highlight);
            if ($highlight === '') {
                continue;
            }
            Logger::warning('Configuration import updated template/script', [
                'mode' => $importMode,
                'detail' => $highlight,
            ]);
        }
    }

    private function importFormsConfiguration(DatabaseInterface $db, array $dataSections, string $importMode): array
    {
        $details = [];
        $tables = 0;
        $rows = 0;

        $formsPayload = $dataSections['forms'] ?? null;
        if (!is_array($formsPayload)) {
            return [
                'tables' => 0,
                'rows' => 0,
                'details' => [Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'forms')],
            ];
        }

        $formRows = is_array($formsPayload['rows'] ?? null) ? $formsPayload['rows'] : [];
        [$formIdMap, $formsImported, $formHighlights] = $this->importRowsByNaturalKey($db, '#__contentbuilderng_forms', $formRows, ['name'], [], $importMode, true);
        $tables++;
        $rows += $formsImported;
        $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED', '#__contentbuilderng_forms', $formsImported);
        foreach ($formHighlights as $formHighlight) {
            $details[] = (string) $formHighlight;
        }

        if ($formIdMap === []) {
            return ['tables' => $tables, 'rows' => $rows, 'details' => $details];
        }

        $targetFormIds = array_values(array_unique(array_map('intval', array_values($formIdMap))));
        if ($importMode === self::MODE_REPLACE && $targetFormIds !== []) {
            $this->deleteRowsByIds($db, '#__contentbuilderng_elements', 'form_id', $targetFormIds);
            $this->deleteRowsByIds($db, '#__contentbuilderng_list_states', 'form_id', $targetFormIds);
            $this->deleteRowsByIds($db, '#__contentbuilderng_resource_access', 'form_id', $targetFormIds);
        }

        $elementsPayload = $dataSections['elements'] ?? null;
        if (is_array($elementsPayload)) {
            $elementRows = $this->remapRowsForeignKey(
                is_array($elementsPayload['rows'] ?? null) ? $elementsPayload['rows'] : [],
                'form_id',
                $formIdMap
            );
            [$elementIdMap, $elementsImported] = $this->importRowsByNaturalKey($db, '#__contentbuilderng_elements', $elementRows, ['form_id', 'reference_id'], [], $importMode, true);
            $tables++;
            $rows += $elementsImported;
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED', '#__contentbuilderng_elements', $elementsImported);
        } else {
            $elementIdMap = [];
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'elements');
        }

        $listStatesPayload = $dataSections['list_states'] ?? null;
        if (is_array($listStatesPayload)) {
            $listStateRows = $this->remapRowsForeignKey(
                is_array($listStatesPayload['rows'] ?? null) ? $listStatesPayload['rows'] : [],
                'form_id',
                $formIdMap
            );
            [, $listStatesImported] = $this->importRowsByNaturalKey($db, '#__contentbuilderng_list_states', $listStateRows, ['form_id', 'title'], [], $importMode, true);
            $tables++;
            $rows += $listStatesImported;
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED', '#__contentbuilderng_list_states', $listStatesImported);
        } else {
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'list_states');
        }

        $resourceAccessPayload = $dataSections['resource_access'] ?? null;
        if (is_array($resourceAccessPayload)) {
            $resourceRows = $this->remapRowsForeignKey(
                is_array($resourceAccessPayload['rows'] ?? null) ? $resourceAccessPayload['rows'] : [],
                'form_id',
                $formIdMap
            );
            $resourceRows = $this->remapRowsForeignKey($resourceRows, 'element_id', $elementIdMap);
            [, $resourceImported] = $this->importRowsByNaturalKey($db, '#__contentbuilderng_resource_access', $resourceRows, ['type', 'element_id', 'resource_id'], ['form_id', 'hits'], $importMode, false);
            $tables++;
            $rows += $resourceImported;
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED', '#__contentbuilderng_resource_access', $resourceImported);
        } else {
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'resource_access');
        }

        return ['tables' => $tables, 'rows' => $rows, 'details' => $details];
    }

    private function importStoragesConfiguration(DatabaseInterface $db, array $dataSections, string $importMode): array
    {
        $details = [];
        $tables = 0;
        $rows = 0;

        $storagesPayload = $dataSections['storages'] ?? null;
        if (!is_array($storagesPayload)) {
            return [
                'tables' => 0,
                'rows' => 0,
                'details' => [Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'storages')],
            ];
        }

        $storageRows = is_array($storagesPayload['rows'] ?? null) ? $storagesPayload['rows'] : [];
        [$storageIdMap, $storagesImported] = $this->importRowsByNaturalKey($db, '#__contentbuilderng_storages', $storageRows, ['name'], [], $importMode, true);
        $tables++;
        $rows += $storagesImported;
        $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED', '#__contentbuilderng_storages', $storagesImported);

        if ($storageIdMap === []) {
            return ['tables' => $tables, 'rows' => $rows, 'details' => $details];
        }

        $targetStorageIds = array_values(array_unique(array_map('intval', array_values($storageIdMap))));
        if ($importMode === self::MODE_REPLACE && $targetStorageIds !== []) {
            $this->deleteRowsByIds($db, '#__contentbuilderng_storage_fields', 'storage_id', $targetStorageIds);
        }

        $storageFieldsPayload = $dataSections['storage_fields'] ?? null;
        if (is_array($storageFieldsPayload)) {
            $fieldRows = $this->remapRowsForeignKey(
                is_array($storageFieldsPayload['rows'] ?? null) ? $storageFieldsPayload['rows'] : [],
                'storage_id',
                $storageIdMap
            );
            [, $fieldsImported] = $this->importRowsByNaturalKey($db, '#__contentbuilderng_storage_fields', $fieldRows, ['storage_id', 'name'], [], $importMode, true);
            $tables++;
            $rows += $fieldsImported;
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED', '#__contentbuilderng_storage_fields', $fieldsImported);
        } else {
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'storage_fields');
        }

        $storageContentPayload = $dataSections['storage_content'] ?? null;
        if (is_array($storageContentPayload)) {
            $contentImported = $this->importStorageContent($db, $storageContentPayload, $importMode);
            $rows += $contentImported;
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED', 'storage_content', $contentImported);
        }

        return ['tables' => $tables, 'rows' => $rows, 'details' => $details];
    }

    private function importStorageContent(DatabaseInterface $db, array $storageContentPayload, string $importMode): int
    {
        $entries = is_array($storageContentPayload['storages'] ?? null) ? $storageContentPayload['storages'] : [];
        $imported = 0;
        $existingTables = array_map('strtolower', (array) $db->getTableList());
        $exportService = new ConfigExportService();

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $sourceStorageName = trim((string) ($entry['storage_name'] ?? ''));
            if ($sourceStorageName === '') {
                continue;
            }

            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('name'), $db->quoteName('bytable')])
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($sourceStorageName));
            $db->setQuery($query, 0, 1);
            $storage = (array) $db->loadAssoc();

            $storageName = trim((string) ($storage['name'] ?? ''));
            $isBytable = (int) ($storage['bytable'] ?? 0) === 1;
            if ($storageName === '' || $isBytable) {
                continue;
            }

            $tableAlias = $exportService->resolveStorageTableAlias($db, $existingTables, $storageName);
            if ($tableAlias === null) {
                continue;
            }

            $rows = is_array($entry['rows'] ?? null) ? $entry['rows'] : [];
            if ($rows === []) {
                continue;
            }

            $imported += $this->importConfigTableRows($db, $tableAlias, $rows, $importMode);
        }

        return $imported;
    }

    private function importConfigTableRows(DatabaseInterface $db, string $tableAlias, array $rows, string $importMode): int
    {
        $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
        if ($columns === []) {
            return 0;
        }

        if ($importMode === self::MODE_REPLACE) {
            $db->setQuery($db->getQuery(true)->delete($db->quoteName($tableAlias)))->execute();
        }

        $imported = 0;
        $hasIdColumn = in_array('id', $columns, true);

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $filtered = [];
            foreach ($columns as $columnName) {
                if (array_key_exists($columnName, $row)) {
                    $filtered[$columnName] = $row[$columnName];
                }
            }

            if ($filtered === []) {
                continue;
            }

            try {
                if ($importMode === self::MODE_MERGE && $hasIdColumn && array_key_exists('id', $filtered)) {
                    $rowId = (int) $filtered['id'];
                    if ($rowId > 0) {
                        $existsQuery = $db->getQuery(true)->select('1')->from($db->quoteName($tableAlias))->where($db->quoteName('id') . ' = ' . $rowId);
                        $db->setQuery($existsQuery, 0, 1);
                        $exists = (int) $db->loadResult() === 1;

                        if ($exists) {
                            $updateQuery = $db->getQuery(true)->update($db->quoteName($tableAlias));
                            $setCount = 0;
                            foreach ($filtered as $columnName => $value) {
                                if ($columnName === 'id') {
                                    continue;
                                }
                                $updateQuery->set($db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value)));
                                $setCount++;
                            }
                            if ($setCount > 0) {
                                $updateQuery->where($db->quoteName('id') . ' = ' . $rowId);
                                $db->setQuery($updateQuery)->execute();
                            }
                            $imported++;
                            continue;
                        }
                    }
                }

                $insertQuery = $db->getQuery(true)
                    ->insert($db->quoteName($tableAlias))
                    ->columns(array_map([$db, 'quoteName'], array_keys($filtered)));
                $values = [];
                foreach ($filtered as $value) {
                    $values[] = $value === null ? 'NULL' : $db->quote((string) $value);
                }
                $insertQuery->values(implode(',', $values));
                $db->setQuery($insertQuery)->execute();
                $imported++;
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_ROW_ERROR', $tableAlias, ((int) $rowIndex) + 1, $e->getMessage())
                );
            }
        }

        return $imported;
    }

    private function importRowsByNaturalKey(
        DatabaseInterface $db,
        string $tableAlias,
        array $rows,
        array $keyColumns,
        array $extraUpdateColumns,
        string $importMode,
        bool $trackSourceIds
    ): array {
        $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
        if ($columns === []) {
            return [[], 0, []];
        }

        $trackedIds = [];
        $imported = 0;
        $highlights = [];
        $hasIdColumn = in_array('id', $columns, true);

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $filtered = [];
            foreach ($columns as $columnName) {
                if (array_key_exists($columnName, $row)) {
                    $filtered[$columnName] = $row[$columnName];
                }
            }

            if ($filtered === []) {
                continue;
            }

            $keyValues = [];
            foreach ($keyColumns as $keyColumn) {
                $keyValue = $filtered[$keyColumn] ?? null;
                if ($keyValue === null || $keyValue === '') {
                    throw new \RuntimeException(
                        Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_ROW_ERROR', $tableAlias, ((int) $rowIndex) + 1, 'Missing natural key "' . $keyColumn . '"')
                    );
                }
                $keyValues[$keyColumn] = $keyValue;
            }

            try {
                $existingRow = $this->findRowByColumns($db, $tableAlias, $keyValues);
                $existingId = $this->findRowIdByColumns($db, $tableAlias, $keyValues);
                $sourceId = (int) ($filtered['id'] ?? 0);

                if ($existingId > 0) {
                    $updateData = $filtered;
                    unset($updateData['id']);
                    $updateData = $this->stripManagedAuditColumns($tableAlias, $updateData);

                    if ($importMode === self::MODE_MERGE && $extraUpdateColumns !== []) {
                        $allowedUpdateColumns = array_fill_keys(array_merge($keyColumns, $extraUpdateColumns), true);
                        $updateData = array_intersect_key($updateData, $allowedUpdateColumns);
                    }

                    if ($trackSourceIds && $sourceId > 0 && $hasIdColumn) {
                        $trackedIds[$sourceId] = $existingId;
                    }

                    if ($this->rowHasDifferences($existingRow, $updateData)) {
                        $rowHighlights = $this->collectTemplateScriptHighlights($tableAlias, $existingRow, $updateData, $keyValues);
                        $updateData = $this->applyAuditColumns($tableAlias, $updateData, false);
                        if ($hasIdColumn) {
                            $this->updateRowById($db, $tableAlias, $existingId, $updateData);
                        } else {
                            $this->updateRowByColumns($db, $tableAlias, $keyValues, $updateData);
                        }
                        $imported++;
                        foreach ($rowHighlights as $rowHighlight) {
                            $highlights[] = $rowHighlight;
                        }
                    }

                    continue;
                }

                unset($filtered['id']);
                $filtered = $this->stripManagedAuditColumns($tableAlias, $filtered);
                $filtered = $this->applyAuditColumns($tableAlias, $filtered, true);
                $insertedId = $this->insertRow($db, $tableAlias, $filtered);
                if ($trackSourceIds && $sourceId > 0 && $insertedId > 0) {
                    $trackedIds[$sourceId] = $insertedId;
                }
                $imported++;
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_ROW_ERROR', $tableAlias, ((int) $rowIndex) + 1, $e->getMessage())
                );
            }
        }

        return [$trackedIds, $imported, array_values(array_unique($highlights))];
    }

    private function collectTemplateScriptHighlights(string $tableAlias, array $existingRow, array $incomingRow, array $keyValues): array
    {
        $trackedColumns = $this->getTrackedTemplateScriptColumns($tableAlias);
        if ($trackedColumns === []) {
            return [];
        }

        $entityLabel = $this->buildTrackedEntityLabel($tableAlias, $keyValues, $incomingRow, $existingRow);
        if ($entityLabel === '') {
            $entityLabel = $tableAlias;
        }

        $highlights = [];
        foreach ($trackedColumns as $columnName => $labelKey) {
            if (!array_key_exists($columnName, $incomingRow)) {
                continue;
            }
            $existingValue = $existingRow[$columnName] ?? null;
            $incomingValue = $incomingRow[$columnName] ?? null;
            $normalizedExisting = $existingValue === null ? '' : trim((string) $existingValue);
            $normalizedIncoming = $incomingValue === null ? '' : trim((string) $incomingValue);

            if ($normalizedExisting === $normalizedIncoming) {
                continue;
            }

            $highlights[] = '[UPDATED] ' . Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TEMPLATE_SCRIPT_UPDATED', $entityLabel, Text::_($labelKey));
        }

        return $highlights;
    }

    private function getTrackedTemplateScriptColumns(string $tableAlias): array
    {
        if ($tableAlias !== '#__contentbuilderng_forms') {
            return [];
        }

        return [
            'intro_text'          => 'COM_CONTENTBUILDERNG_IMPORT_TRACKED_FIELD_INTRO_TEXT',
            'details_template'    => 'COM_CONTENTBUILDERNG_IMPORT_TRACKED_FIELD_DETAILS_TEMPLATE',
            'details_prepare'     => 'COM_CONTENTBUILDERNG_IMPORT_TRACKED_FIELD_DETAILS_PREPARE',
            'editable_template'   => 'COM_CONTENTBUILDERNG_IMPORT_TRACKED_FIELD_EDITABLE_TEMPLATE',
            'editable_prepare'    => 'COM_CONTENTBUILDERNG_IMPORT_TRACKED_FIELD_EDITABLE_PREPARE',
            'email_admin_template'=> 'COM_CONTENTBUILDERNG_IMPORT_TRACKED_FIELD_EMAIL_ADMIN_TEMPLATE',
            'email_template'      => 'COM_CONTENTBUILDERNG_IMPORT_TRACKED_FIELD_EMAIL_TEMPLATE',
        ];
    }

    private function buildTrackedEntityLabel(string $tableAlias, array $keyValues, array $incomingRow, array $existingRow): string
    {
        if ($tableAlias === '#__contentbuilderng_forms') {
            $name = trim((string) ($keyValues['name'] ?? $incomingRow['name'] ?? $existingRow['name'] ?? ''));
            return $name !== '' ? $name : '#form';
        }

        if ($tableAlias === '#__contentbuilderng_storages') {
            $name = trim((string) ($keyValues['name'] ?? $incomingRow['name'] ?? $existingRow['name'] ?? ''));
            return $name !== '' ? $name : '#storage';
        }

        return trim((string) ($keyValues['name'] ?? ''));
    }

    private function remapRowsForeignKey(array $rows, string $columnName, array $idMap): array
    {
        $remapped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (array_key_exists($columnName, $row)) {
                $sourceId = (int) $row[$columnName];
                if ($sourceId > 0 && isset($idMap[$sourceId])) {
                    $row[$columnName] = (int) $idMap[$sourceId];
                }
            }
            $remapped[] = $row;
        }
        return $remapped;
    }

    private function deleteRowsByIds(DatabaseInterface $db, string $tableAlias, string $columnName, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }
        $db->setQuery($db->getQuery(true)->delete($db->quoteName($tableAlias))->where($db->quoteName($columnName) . ' IN (' . implode(',', $ids) . ')'))->execute();
    }

    private function findRowIdByColumns(DatabaseInterface $db, string $tableAlias, array $columnValues): int
    {
        $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
        $query = $db->getQuery(true)
            ->select(in_array('id', $columns, true) ? $db->quoteName('id') : '1')
            ->from($db->quoteName($tableAlias));
        foreach ($columnValues as $columnName => $value) {
            $query->where($db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value)));
        }
        $db->setQuery($query, 0, 1);
        return (int) $db->loadResult();
    }

    private function findRowByColumns(DatabaseInterface $db, string $tableAlias, array $columnValues): array
    {
        $query = $db->getQuery(true)->select('*')->from($db->quoteName($tableAlias));
        foreach ($columnValues as $columnName => $value) {
            $query->where($db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value)));
        }
        $db->setQuery($query, 0, 1);
        $row = $db->loadAssoc();
        return is_array($row) ? $row : [];
    }

    private function rowHasDifferences(array $existingRow, array $updateData): bool
    {
        foreach ($updateData as $columnName => $value) {
            $existingValue = $existingRow[$columnName] ?? null;
            $normalizedExisting = $existingValue === null ? null : (string) $existingValue;
            $normalizedIncoming = $value === null ? null : (string) $value;
            if ($normalizedExisting !== $normalizedIncoming) {
                return true;
            }
        }
        return false;
    }

    private function stripManagedAuditColumns(string $tableAlias, array $row): array
    {
        foreach ($this->getManagedAuditColumns($tableAlias) as $columnName) {
            unset($row[$columnName]);
        }
        return $row;
    }

    private function applyAuditColumns(string $tableAlias, array $row, bool $isNew): array
    {
        $now = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if ($tableAlias === '#__contentbuilderng_forms') {
            if ($isNew) {
                if (empty($row['created']) || str_starts_with((string) $row['created'], '0000-00-00')) {
                    $row['created'] = $now;
                }
                if (empty($row['created_by'])) {
                    $row['created_by'] = (int) ($user->id ?? 0);
                }
            }
            $row['modified'] = $now;
            $row['modified_by'] = (int) ($user->id ?? 0);
            $row['last_update'] = $now;
            return $row;
        }

        if ($tableAlias === '#__contentbuilderng_storages') {
            $actor = trim((string) (($user->username ?? '') !== '' ? $user->username : ($user->name ?? '')));
            if ($actor === '') {
                $actor = 'system';
            }
            if ($isNew) {
                if (empty($row['created']) || str_starts_with((string) $row['created'], '0000-00-00')) {
                    $row['created'] = $now;
                }
                if (trim((string) ($row['created_by'] ?? '')) === '') {
                    $row['created_by'] = $actor;
                }
            }
            $row['modified'] = $now;
            $row['modified_by'] = $actor;
        }

        return $row;
    }

    private function getManagedAuditColumns(string $tableAlias): array
    {
        if ($tableAlias === '#__contentbuilderng_forms') {
            return ['modified', 'modified_by', 'last_update'];
        }
        if ($tableAlias === '#__contentbuilderng_storages') {
            return ['modified', 'modified_by'];
        }
        return [];
    }

    private function updateRowById(DatabaseInterface $db, string $tableAlias, int $id, array $row): void
    {
        if ($id <= 0 || $row === []) {
            return;
        }
        $query = $db->getQuery(true)->update($db->quoteName($tableAlias));
        $setCount = 0;
        foreach ($row as $columnName => $value) {
            $query->set($db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value)));
            $setCount++;
        }
        if ($setCount === 0) {
            return;
        }
        $query->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query)->execute();
    }

    private function updateRowByColumns(DatabaseInterface $db, string $tableAlias, array $columnValues, array $row): void
    {
        if ($columnValues === [] || $row === []) {
            return;
        }
        $query = $db->getQuery(true)->update($db->quoteName($tableAlias));
        $setCount = 0;
        foreach ($row as $columnName => $value) {
            $query->set($db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value)));
            $setCount++;
        }
        if ($setCount === 0) {
            return;
        }
        foreach ($columnValues as $columnName => $value) {
            $query->where($db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value)));
        }
        $db->setQuery($query)->execute();
    }

    private function insertRow(DatabaseInterface $db, string $tableAlias, array $row): int
    {
        if ($row === []) {
            return 0;
        }
        $query = $db->getQuery(true)
            ->insert($db->quoteName($tableAlias))
            ->columns(array_map([$db, 'quoteName'], array_keys($row)));
        $values = [];
        foreach ($row as $value) {
            $values[] = $value === null ? 'NULL' : $db->quote((string) $value);
        }
        $query->values(implode(',', $values));
        $db->setQuery($query)->execute();
        return (int) $db->insertid();
    }
}
