<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die('Restricted access');

use Joomla\Database\DatabaseInterface;

final class FormAuditColumnsHelper
{
    /**
     * Columns managed by the list/detail display defaults.
     *
     * @return array<string,string>
     */
    public static function requiredColumns(): array
    {
        return [
            'button_bar_sticky' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'list_header_sticky' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'show_preview_link' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'list_last_modification' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'cb_show_author' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_show_top_bar' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_show_bottom_bar' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_show_details_top_bar' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_show_details_bottom_bar' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'show_back_button' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_filter_in_title' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'cb_prefix_in_title' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];
    }

    /**
     * @return array{
     *   scanned:int,
     *   missing_tables:int,
     *   missing_columns_total:int,
     *   issues:array<int,array{
     *     table:string,
     *     missing:array<int,string>
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    public static function audit(DatabaseInterface $db): array
    {
        $tableName = $db->getPrefix() . 'contentbuilderng_forms';
        $summary = [
            'scanned' => 1,
            'missing_tables' => 0,
            'missing_columns_total' => 0,
            'issues' => [],
            'warnings' => [],
        ];

        try {
            $columns = $db->getTableColumns($tableName, true);
        } catch (\Throwable $e) {
            $summary['warnings'][] = 'Could not inspect form table columns: ' . $e->getMessage();
            $summary['missing_tables'] = 1;

            return $summary;
        }

        $knownColumns = [];
        foreach ((array) $columns as $columnName => $_type) {
            $knownColumns[strtolower((string) $columnName)] = true;
        }

        $missing = [];
        foreach (array_keys(self::requiredColumns()) as $columnName) {
            if (!isset($knownColumns[$columnName])) {
                $missing[] = $columnName;
            }
        }

        if ($missing !== []) {
            $summary['missing_tables'] = 1;
            $summary['missing_columns_total'] = count($missing);
            $summary['issues'][] = [
                'table' => '#__contentbuilderng_forms',
                'missing' => $missing,
            ];
        }

        return $summary;
    }

    /**
     * @return array{
     *   scanned:int,
     *   issues:int,
     *   repaired:int,
     *   unchanged:int,
     *   errors:int,
     *   tables:array<int,array{
     *     table:string,
     *     missing:array<int,string>,
     *     added:array<int,string>,
     *     status:string,
     *     error:string
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    public static function repair(DatabaseInterface $db): array
    {
        $summary = [
            'scanned' => 1,
            'issues' => 0,
            'repaired' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'tables' => [],
            'warnings' => [],
        ];

        $tableName = $db->getPrefix() . 'contentbuilderng_forms';
        $tableAlias = '#__contentbuilderng_forms';
        $tableSummary = [
            'table' => $tableAlias,
            'missing' => [],
            'added' => [],
            'status' => 'unchanged',
            'error' => '',
        ];

        try {
            $columns = $db->getTableColumns($tableName, true);
        } catch (\Throwable $e) {
            $summary['warnings'][] = 'Could not inspect form table columns: ' . $e->getMessage();
            $summary['errors'] = 1;
            $tableSummary['status'] = 'error';
            $tableSummary['error'] = $e->getMessage();
            $summary['tables'][] = $tableSummary;

            return $summary;
        }

        $knownColumns = [];
        foreach ((array) $columns as $columnName => $_type) {
            $knownColumns[strtolower((string) $columnName)] = true;
        }

        $missingColumns = [];
        foreach (self::requiredColumns() as $columnName => $definition) {
            if (isset($knownColumns[$columnName])) {
                continue;
            }

            $missingColumns[] = $columnName;

            try {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_forms')
                    . ' ADD ' . $db->quoteName($columnName) . ' ' . $definition
                );
                $db->execute();
                $tableSummary['added'][] = $columnName;
                $knownColumns[$columnName] = true;
                $summary['repaired']++;
            } catch (\Throwable $e) {
                $summary['errors']++;
                $tableSummary['error'] = trim($tableSummary['error'] . ($tableSummary['error'] !== '' ? ' | ' : '') . $columnName . ': ' . $e->getMessage());
            }
        }

        $tableSummary['missing'] = $missingColumns;

        if ($missingColumns !== []) {
            $summary['issues'] = 1;
        }

        if ($tableSummary['added'] === [] && $tableSummary['error'] === '') {
            $summary['unchanged'] = 1;
            $tableSummary['status'] = 'unchanged';
        } elseif ($tableSummary['error'] === '') {
            $tableSummary['status'] = 'repaired';
        } elseif ($tableSummary['added'] !== []) {
            $tableSummary['status'] = 'partial';
        } else {
            $tableSummary['status'] = 'error';
        }

        $summary['tables'][] = $tableSummary;

        return $summary;
    }
}
