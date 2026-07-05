<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\StorageColumnTypeHelper;
use Joomla\Database\DatabaseInterface;

final class StorageColumnTypeAuditHelper
{
    /**
     * @return array{0:array<int,array<string,mixed>>,1:array<int,string>}
     */
    public static function inspect(DatabaseInterface $db): array
    {
        $issues = [];
        $errors = [];
        $prefix = $db->getPrefix();

        try {
            $storageFieldColumns = $db->getTableColumns('#__contentbuilderng_storage_fields', false);
        } catch (\Throwable $e) {
            return [[], ['Could not inspect #__contentbuilderng_storage_fields columns for storage type audit: ' . $e->getMessage()]];
        }

        if (!is_array($storageFieldColumns) || !array_key_exists('sql_type', $storageFieldColumns)) {
            return [[], ['Could not audit storage column types: #__contentbuilderng_storage_fields.sql_type is missing.']];
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name', 'title', 'bytable']))
                    ->from($db->quoteName('#__contentbuilderng_storages'))
                    ->where($db->quoteName('name') . " <> ''")
                    ->where($db->quoteName('bytable') . ' = 0')
                    ->order($db->quoteName('id') . ' ASC')
            );
            $storages = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect storages for storage type audit: ' . $e->getMessage()]];
        }

        foreach ($storages as $storage) {
            $storageId = (int) ($storage['id'] ?? 0);
            $storageTable = strtolower(trim((string) ($storage['name'] ?? '')));

            if ($storageId <= 0 || $storageTable === '' || !preg_match('/^[a-z0-9_]+$/', $storageTable)) {
                continue;
            }

            $physicalTable = $prefix . $storageTable;

            try {
                $physicalColumns = $db->getTableColumns($physicalTable, true);
            } catch (\Throwable $e) {
                $errors[] = 'Could not inspect storage data table ' . $physicalTable . ' for storage type audit: ' . $e->getMessage();
                continue;
            }

            if (!is_array($physicalColumns) || $physicalColumns === []) {
                continue;
            }

            $physicalColumnsLower = [];
            foreach ($physicalColumns as $columnName => $definition) {
                $physicalColumnsLower[strtolower((string) $columnName)] = $definition;
            }

            try {
                $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName(['id', 'name', 'title', 'sql_type']))
                        ->from($db->quoteName('#__contentbuilderng_storage_fields'))
                        ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId)
                        ->order($db->quoteName('ordering') . ' ASC')
                );
                $fields = $db->loadAssocList() ?: [];
            } catch (\Throwable $e) {
                $errors[] = 'Could not inspect storage fields for storage #' . $storageId . ': ' . $e->getMessage();
                continue;
            }

            foreach ($fields as $field) {
                $fieldName = strtolower(trim((string) ($field['name'] ?? '')));

                if ($fieldName === '' || !isset($physicalColumnsLower[$fieldName])) {
                    continue;
                }

                $expectedType = StorageColumnTypeHelper::normalize((string) ($field['sql_type'] ?? StorageColumnTypeHelper::DEFAULT_TYPE));
                $physicalDefinition = $physicalColumnsLower[$fieldName];

                if (StorageColumnTypeHelper::physicalTypeMatches($expectedType, $physicalDefinition)) {
                    continue;
                }

                $issues[] = [
                    'storage_id' => $storageId,
                    'storage_name' => trim((string) (($storage['title'] ?? '') !== '' ? $storage['title'] : ($storage['name'] ?? ''))),
                    'table' => AuditTableSupportHelper::toAlias($physicalTable, $prefix),
                    'field_id' => (int) ($field['id'] ?? 0),
                    'field_title' => trim((string) (($field['title'] ?? '') !== '' ? $field['title'] : ($field['name'] ?? ''))),
                    'column' => $fieldName,
                    'expected_type' => $expectedType,
                    'expected_label' => StorageColumnTypeHelper::label($expectedType),
                    'expected_sql' => StorageColumnTypeHelper::sqlDefinition($expectedType),
                    'physical_type' => StorageColumnTypeHelper::extractPhysicalType($physicalDefinition),
                ];
            }
        }

        return [$issues, $errors];
    }
}
