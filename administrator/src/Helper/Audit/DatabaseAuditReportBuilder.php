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

use Joomla\CMS\Factory;

final class DatabaseAuditReportBuilder
{
    /**
     * @param array{
     *   tables:array<int,string>,
     *   prefix:string,
     *   duplicate_indexes:array<int,array<string,mixed>>,
     *   historical_tables:array<int,string>,
     *   historical_menu_entries:array<int,array<string,mixed>>,
     *   table_encoding_issues:array<int,array<string,mixed>>,
     *   packed_data_audit:array<string,mixed>,
     *   column_encoding_issues:array<int,array<string,mixed>>,
     *   mixed_table_collations:array<int,array<string,mixed>>,
     *   missing_audit_columns_scanned:int,
     *   missing_audit_columns:array<int,array<string,mixed>>,
     *   missing_form_audit_columns_scanned:int,
     *   missing_form_audit_columns:array<int,array<string,mixed>>,
     *   plugin_extension_duplicates:array<int,array<string,mixed>>,
     *   bf_view_field_sync_issues:array<int,array<string,mixed>>,
     *   menu_view_issues:array<int,array<string,mixed>>,
     *   frontend_permission_issues:array<int,array<string,mixed>>,
     *   element_reference_issues:array<int,array<string,mixed>>,
     *   invalid_datetime_sort_issues:array<int,array<string,mixed>>,
     *   generated_article_category_issues:array<int,array<string,mixed>>,
     *   cb_tables:array<string,mixed>,
     *   errors:array<int,string>
     * } $data
     * @param callable(string,string):string $toAlias
     * @return array<string,mixed>
     */
    public static function build(array $data, callable $toAlias): array
    {
        $tables = (array) ($data['tables'] ?? []);
        $prefix = (string) ($data['prefix'] ?? '');
        $duplicateIndexes = (array) ($data['duplicate_indexes'] ?? []);
        $historicalTables = (array) ($data['historical_tables'] ?? []);
        $historicalMenuEntries = (array) ($data['historical_menu_entries'] ?? []);
        $tableEncodingIssues = (array) ($data['table_encoding_issues'] ?? []);
        $packedDataAudit = (array) ($data['packed_data_audit'] ?? []);
        $columnEncodingIssues = (array) ($data['column_encoding_issues'] ?? []);
        $mixedTableCollations = (array) ($data['mixed_table_collations'] ?? []);
        $missingAuditColumns = (array) ($data['missing_audit_columns'] ?? []);
        $missingFormAuditColumns = (array) ($data['missing_form_audit_columns'] ?? []);
        $pluginExtensionDuplicates = (array) ($data['plugin_extension_duplicates'] ?? []);
        $bfFieldSyncIssues = (array) ($data['bf_view_field_sync_issues'] ?? []);
        $menuViewIssues = (array) ($data['menu_view_issues'] ?? []);
        $frontendPermissionIssues = (array) ($data['frontend_permission_issues'] ?? []);
        $elementReferenceIssues = (array) ($data['element_reference_issues'] ?? []);
        $invalidDatetimeSortIssues = (array) ($data['invalid_datetime_sort_issues'] ?? []);
        $generatedArticleCategoryIssues = (array) ($data['generated_article_category_issues'] ?? []);
        $cbTableStats = (array) ($data['cb_tables'] ?? []);
        $errors = (array) ($data['errors'] ?? []);

        $missingAuditColumnsTotal = 0;
        foreach ($missingAuditColumns as $missingAuditColumn) {
            if (!is_array($missingAuditColumn)) {
                continue;
            }

            $missingAuditColumnsTotal += count((array) ($missingAuditColumn['missing'] ?? []));
        }

        $missingFormAuditColumnsTotal = 0;
        foreach ($missingFormAuditColumns as $missingFormAuditColumn) {
            if (!is_array($missingFormAuditColumn)) {
                continue;
            }

            $missingFormAuditColumnsTotal += count((array) ($missingFormAuditColumn['missing'] ?? []));
        }

        $bfMissingInCbTotal = 0;
        $bfOrphanInCbTotal = 0;
        foreach ($bfFieldSyncIssues as $bfFieldSyncIssue) {
            if (!is_array($bfFieldSyncIssue)) {
                continue;
            }

            $bfMissingInCbTotal += (int) ($bfFieldSyncIssue['missing_count'] ?? 0);
            $bfOrphanInCbTotal += (int) ($bfFieldSyncIssue['orphan_count'] ?? 0);
        }

        $duplicateToDrop = 0;
        foreach ($duplicateIndexes as $duplicateIndex) {
            $duplicateToDrop += count((array) ($duplicateIndex['drop'] ?? []));
        }

        $pluginDuplicateRowsToRemove = 0;
        foreach ($pluginExtensionDuplicates as $pluginExtensionDuplicate) {
            if (!is_array($pluginExtensionDuplicate)) {
                continue;
            }

            $pluginDuplicateRowsToRemove += count((array) ($pluginExtensionDuplicate['duplicate_ids'] ?? []));
        }

        $mixedCollationIssueGroups = count($mixedTableCollations) > 1 ? 1 : 0;
        $invalidDatetimeSortRows = 0;
        foreach ($invalidDatetimeSortIssues as $invalidDatetimeSortIssue) {
            if (!is_array($invalidDatetimeSortIssue)) {
                continue;
            }

            $invalidDatetimeSortRows += (int) ($invalidDatetimeSortIssue['invalid_count'] ?? 0);
        }
        $invalidGeneratedArticleCategoryRows = 0;
        foreach ($generatedArticleCategoryIssues as $generatedArticleCategoryIssue) {
            if (!is_array($generatedArticleCategoryIssue)) {
                continue;
            }

            $invalidGeneratedArticleCategoryRows += (int) ($generatedArticleCategoryIssue['invalid_article_count'] ?? 0);
        }

        $issuesTotal = count($duplicateIndexes)
            + count($historicalTables)
            + count($historicalMenuEntries)
            + count($tableEncodingIssues)
            + ((int) ($packedDataAudit['candidates'] ?? 0) > 0 ? 1 : 0)
            + count($columnEncodingIssues)
            + $mixedCollationIssueGroups
            + count($missingAuditColumns)
            + count($missingFormAuditColumns)
            + count($pluginExtensionDuplicates)
            + count($bfFieldSyncIssues)
            + count($menuViewIssues)
            + count($frontendPermissionIssues)
            + count($elementReferenceIssues)
            + count($invalidDatetimeSortIssues)
            + count($generatedArticleCategoryIssues);

        return [
            'generated_at' => Factory::getDate()->toSql(),
            'scanned_tables' => count($tables),
            'tables' => array_map(
                static fn(string $tableName): string => $toAlias($tableName, $prefix),
                $tables
            ),
            'duplicate_indexes' => $duplicateIndexes,
            'historical_tables' => $historicalTables,
            'historical_menu_entries' => $historicalMenuEntries,
            'table_encoding_issues' => $tableEncodingIssues,
            'packed_data_audit' => $packedDataAudit,
            'column_encoding_issues' => $columnEncodingIssues,
            'mixed_table_collations' => $mixedTableCollations,
            'missing_audit_columns_scanned' => (int) ($data['missing_audit_columns_scanned'] ?? 0),
            'missing_audit_columns' => $missingAuditColumns,
            'missing_form_audit_columns_scanned' => (int) ($data['missing_form_audit_columns_scanned'] ?? 0),
            'missing_form_audit_columns' => $missingFormAuditColumns,
            'plugin_extension_duplicates' => $pluginExtensionDuplicates,
            'bf_view_field_sync_issues' => $bfFieldSyncIssues,
            'menu_view_issues' => $menuViewIssues,
            'frontend_permission_issues' => $frontendPermissionIssues,
            'element_reference_issues' => $elementReferenceIssues,
            'invalid_datetime_sort_issues' => $invalidDatetimeSortIssues,
            'generated_article_category_issues' => $generatedArticleCategoryIssues,
            'cb_tables' => $cbTableStats,
            'summary' => [
                'duplicate_index_groups' => count($duplicateIndexes),
                'duplicate_indexes_to_drop' => $duplicateToDrop,
                'historical_tables' => count($historicalTables),
                'historical_menu_entries' => count($historicalMenuEntries),
                'table_encoding_issues' => count($tableEncodingIssues),
                'packed_data_candidates' => (int) ($packedDataAudit['candidates'] ?? 0),
                'column_encoding_issues' => count($columnEncodingIssues),
                'mixed_table_collations' => $mixedCollationIssueGroups,
                'missing_audit_column_tables' => count($missingAuditColumns),
                'missing_audit_columns_total' => $missingAuditColumnsTotal,
                'missing_form_audit_column_tables' => count($missingFormAuditColumns),
                'missing_form_audit_columns_total' => $missingFormAuditColumnsTotal,
                'plugin_duplicate_groups' => count($pluginExtensionDuplicates),
                'plugin_duplicate_rows_to_remove' => $pluginDuplicateRowsToRemove,
                'bf_view_field_sync_views' => count($bfFieldSyncIssues),
                'bf_view_field_sync_missing_in_cb' => $bfMissingInCbTotal,
                'bf_view_field_sync_orphan_in_cb' => $bfOrphanInCbTotal,
                'menu_view_issues' => count($menuViewIssues),
                'frontend_permission_issues' => count($frontendPermissionIssues),
                'element_reference_issues' => count($elementReferenceIssues),
                'invalid_datetime_sort_issues' => count($invalidDatetimeSortIssues),
                'invalid_datetime_sort_rows' => $invalidDatetimeSortRows,
                'generated_article_category_issues' => count($generatedArticleCategoryIssues),
                'generated_article_category_rows' => $invalidGeneratedArticleCategoryRows,
                'issues_total' => $issuesTotal,
            ],
            'errors' => $errors,
        ];
    }
}
