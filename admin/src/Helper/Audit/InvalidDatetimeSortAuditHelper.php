<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use Joomla\Database\DatabaseInterface;

final class InvalidDatetimeSortAuditHelper
{
    /**
     * @return array{0:array<int,array{
     *   form_id:int,
     *   form_name:string,
     *   storage_id:int,
     *   storage_name:string,
     *   table:string,
     *   element_id:int,
     *   element_label:string,
     *   reference_id:int,
     *   column:string,
     *   invalid_count:int,
     *   sample_values:array<int,string>
     * }>,1:array<int,string>}
     */
    public static function inspect(DatabaseInterface $db): array
    {
        $issues = [];
        $errors = [];
        $prefix = $db->getPrefix();

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name', 'type', 'reference_id']))
                    ->from($db->quoteName('#__contentbuilderng_forms'))
                    ->where($db->quoteName('type') . ' IN (' . $db->quote('com_contentbuilderng') . ', ' . $db->quote('com_contentbuilder') . ')')
                    ->order($db->quoteName('id') . ' ASC')
            );
            $forms = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect forms for DATETIME order audit: ' . $e->getMessage()]];
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'form_id', 'label', 'reference_id', 'order_type']))
                    ->from($db->quoteName('#__contentbuilderng_elements'))
                    ->where($db->quoteName('order_type') . ' = ' . $db->quote('DATETIME'))
                    ->where('COALESCE(' . $db->quoteName('published') . ', 1) = 1')
                    ->where('COALESCE(' . $db->quoteName('list_include') . ', 0) = 1')
                    ->order($db->quoteName('ordering') . ' ASC')
            );
            $elements = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect list elements for DATETIME order audit: ' . $e->getMessage()]];
        }

        if ($forms === [] || $elements === []) {
            return [[], []];
        }

        $formsById = [];
        $storageIds = [];

        foreach ($forms as $form) {
            $formId = (int) ($form['id'] ?? 0);
            $storageId = (int) ($form['reference_id'] ?? 0);

            if ($formId <= 0 || $storageId <= 0) {
                continue;
            }

            $formsById[$formId] = $form;
            $storageIds[$storageId] = true;
        }

        if ($formsById === [] || $storageIds === []) {
            return [[], []];
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name', 'title', 'bytable']))
                    ->from($db->quoteName('#__contentbuilderng_storages'))
                    ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', array_keys($storageIds))) . ')')
            );
            $storages = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect storages for DATETIME order audit: ' . $e->getMessage()]];
        }

        $storagesById = [];
        foreach ($storages as $storage) {
            $storageId = (int) ($storage['id'] ?? 0);
            if ($storageId > 0) {
                $storagesById[$storageId] = $storage;
            }
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'storage_id', 'name']))
                    ->from($db->quoteName('#__contentbuilderng_storage_fields'))
                    ->where($db->quoteName('storage_id') . ' IN (' . implode(',', array_map('intval', array_keys($storageIds))) . ')')
            );
            $storageFields = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect storage fields for DATETIME order audit: ' . $e->getMessage()]];
        }

        $storageFieldsById = [];
        foreach ($storageFields as $storageField) {
            $storageFieldId = (int) ($storageField['id'] ?? 0);
            if ($storageFieldId > 0) {
                $storageFieldsById[$storageFieldId] = $storageField;
            }
        }

        try {
            $knownTables = array_fill_keys($db->getTableList(), true);
        } catch (\Throwable $e) {
            return [[], ['Could not list tables for DATETIME order audit: ' . $e->getMessage()]];
        }

        foreach ($elements as $element) {
            $formId = (int) ($element['form_id'] ?? 0);
            $referenceId = (int) ($element['reference_id'] ?? 0);

            if ($formId <= 0 || $referenceId <= 0 || !isset($formsById[$formId])) {
                continue;
            }

            $form = $formsById[$formId];
            $storageId = (int) ($form['reference_id'] ?? 0);
            $storage = $storagesById[$storageId] ?? null;
            $storageField = $storageFieldsById[$referenceId] ?? null;

            if (!is_array($storage) || !is_array($storageField)) {
                continue;
            }

            $storageTableName = trim((string) ($storage['name'] ?? ''));
            $sourceColumn = trim((string) ($storageField['name'] ?? ''));

            if ($storageTableName === '' || $sourceColumn === '') {
                continue;
            }

            $physicalTableName = ((int) ($storage['bytable'] ?? 0) === 1) ? $storageTableName : $prefix . $storageTableName;

            if (!isset($knownTables[$physicalTableName])) {
                $errors[] = 'Could not inspect DATETIME order audit table ' . $physicalTableName . ' for form #' . $formId . ': table not found.';
                continue;
            }

            $tableAlias = AuditTableSupportHelper::toAlias($physicalTableName, $prefix);
            $tableQuoted = $db->quoteName($physicalTableName);
            $columnQuoted = $db->quoteName($sourceColumn);
            $stringValueExpr = 'CAST(' . $columnQuoted . ' AS CHAR)';
            $condition = 'NULLIF(TRIM(' . $stringValueExpr . "), '') IS NOT NULL"
                . ' AND CAST(' . $columnQuoted . ' AS DATETIME) = ' . $db->quote('0000-00-00 00:00:00');

            try {
                $countQuery = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($tableQuoted)
                    ->where($condition);
                $db->setQuery($countQuery);
                $invalidCount = (int) $db->loadResult();
            } catch (\Throwable $e) {
                $errors[] = 'Could not count DATETIME cast failures for ' . $tableAlias . '.' . $sourceColumn . ': ' . $e->getMessage();
                continue;
            }

            if ($invalidCount <= 0) {
                continue;
            }

            try {
                $sampleQuery = $db->getQuery(true)
                    ->select('DISTINCT ' . $columnQuoted)
                    ->from($tableQuoted)
                    ->where($condition)
                    ->order($columnQuoted . ' ASC');
                $db->setQuery($sampleQuery, 0, 5);
                $sampleValues = array_values(array_filter(array_map(
                    static fn($value): string => trim((string) $value),
                    $db->loadColumn() ?: []
                ), static fn(string $value): bool => $value !== ''));
            } catch (\Throwable $e) {
                $sampleValues = [];
                $errors[] = 'Could not load DATETIME cast failure samples for ' . $tableAlias . '.' . $sourceColumn . ': ' . $e->getMessage();
            }

            $issues[] = [
                'form_id' => $formId,
                'form_name' => trim((string) ($form['name'] ?? '')),
                'storage_id' => $storageId,
                'storage_name' => trim((string) (($storage['title'] ?? '') !== '' ? $storage['title'] : ($storage['name'] ?? ''))),
                'table' => $tableAlias,
                'element_id' => (int) ($element['id'] ?? 0),
                'element_label' => trim((string) ($element['label'] ?? '')),
                'reference_id' => $referenceId,
                'column' => $sourceColumn,
                'invalid_count' => $invalidCount,
                'sample_values' => $sampleValues,
            ];
        }

        return [$issues, $errors];
    }
}
