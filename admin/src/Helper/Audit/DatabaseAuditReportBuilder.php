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

use Joomla\CMS\Date\Date;

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
     *   content_record_duplicate_issues:array<int,array<string,mixed>>,
     *   bf_content_record_orphans:array<int,array{form_id:int,count:int,records:array<int,array{cb_record_id:int,record_id:int}>}>,
     *   invalid_datetime_sort_issues:array<int,array<string,mixed>>,
     *   legacy_storage_index_issues:array<int,array<string,mixed>>,
     *   upload_directory_protection_issues:array<int,array<string,mixed>>,
     *   generated_article_category_issues:array<int,array<string,mixed>>,
     *   debug_mode_issues:array<int,array<string,mixed>>,
     *   form_audits:array<int,array<string,mixed>>,
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
        $contentRecordDuplicateIssues = (array) ($data['content_record_duplicate_issues'] ?? []);
        $bfContentRecordOrphans = (array) ($data['bf_content_record_orphans'] ?? []);
        $invalidDatetimeSortIssues = (array) ($data['invalid_datetime_sort_issues'] ?? []);
        $legacyStorageIndexIssues = (array) ($data['legacy_storage_index_issues'] ?? []);
        $uploadDirectoryProtectionIssues = (array) ($data['upload_directory_protection_issues'] ?? []);
        $storageColumnTypeIssues = (array) ($data['storage_column_type_issues'] ?? []);
        $generatedArticleCategoryIssues = (array) ($data['generated_article_category_issues'] ?? []);
        $debugModeIssues = (array) ($data['debug_mode_issues'] ?? []);
        $formAudits = (array) ($data['form_audits'] ?? []);
        $staleLanguageFiles = (array) ($data['stale_language_files'] ?? []);
        $staleInstallerTempDirs = (array) ($data['stale_installer_temp_dirs'] ?? []);
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
        $contentRecordDuplicateRowsToRemove = 0;
        foreach ($contentRecordDuplicateIssues as $contentRecordDuplicateIssue) {
            if (!is_array($contentRecordDuplicateIssue)) {
                continue;
            }

            $contentRecordDuplicateRowsToRemove += count((array) ($contentRecordDuplicateIssue['duplicate_ids'] ?? []));
        }
        $bfContentRecordOrphanRows = 0;
        foreach ($bfContentRecordOrphans as $bfContentRecordOrphan) {
            if (is_array($bfContentRecordOrphan)) {
                $bfContentRecordOrphanRows += (int) ($bfContentRecordOrphan['count'] ?? 0);
            }
        }

        $invalidGeneratedArticleCategoryRows = 0;
        foreach ($generatedArticleCategoryIssues as $generatedArticleCategoryIssue) {
            if (!is_array($generatedArticleCategoryIssue)) {
                continue;
            }

            $invalidGeneratedArticleCategoryRows += (int) ($generatedArticleCategoryIssue['invalid_article_count'] ?? 0);
        }

        $formAuditIssueForms = 0;
        $formAuditIssueChecks = 0;
        $formAuditIssueErrors = 0;
        $formAuditIssueWarnings = 0;
        foreach ($formAudits as $formAudit) {
            if (!is_array($formAudit)) {
                continue;
            }

            $hasIssues = false;
            foreach ((array) ($formAudit['checks'] ?? []) as $check) {
                if (!is_array($check) || (string) ($check['status'] ?? '') === 'ok') {
                    continue;
                }

                $hasIssues = true;
                $formAuditIssueChecks++;
                if ((string) ($check['status'] ?? '') === 'error') {
                    $formAuditIssueErrors++;
                } else {
                    $formAuditIssueWarnings++;
                }
            }

            if ($hasIssues) {
                $formAuditIssueForms++;
            }
        }

        $auditWarningCount = count($duplicateIndexes)
            + count($historicalTables)
            + count($historicalMenuEntries)
            + count($tableEncodingIssues)
            + (int) ($packedDataAudit['candidates'] ?? 0)
            + count($columnEncodingIssues)
            + $mixedCollationIssueGroups
            + count($missingAuditColumns)
            + count($missingFormAuditColumns)
            + count($pluginExtensionDuplicates)
            + count($bfFieldSyncIssues)
            + count($menuViewIssues)
            + count($frontendPermissionIssues)
            + count($elementReferenceIssues)
            + count($contentRecordDuplicateIssues)
            + count($bfContentRecordOrphans)
            + count($invalidDatetimeSortIssues)
            + count($legacyStorageIndexIssues)
            + count($uploadDirectoryProtectionIssues)
            + count($storageColumnTypeIssues)
            + count($generatedArticleCategoryIssues)
            + count($debugModeIssues)
            + count($staleLanguageFiles)
            + count($staleInstallerTempDirs)
            + $formAuditIssueWarnings;
        $auditErrorCount = count($errors) + $formAuditIssueErrors;

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
            + count($contentRecordDuplicateIssues)
            + count($bfContentRecordOrphans)
            + count($invalidDatetimeSortIssues)
            + count($legacyStorageIndexIssues)
            + count($uploadDirectoryProtectionIssues)
            + count($storageColumnTypeIssues)
            + count($generatedArticleCategoryIssues)
            + count($debugModeIssues)
            + count($staleLanguageFiles)
            + count($staleInstallerTempDirs)
            + $formAuditIssueForms;

        return [
            'generated_at' => (new Date())->toSql(),
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
            'content_record_duplicate_issues' => $contentRecordDuplicateIssues,
            'bf_content_record_orphans' => $bfContentRecordOrphans,
            'invalid_datetime_sort_issues' => $invalidDatetimeSortIssues,
            'legacy_storage_index_issues' => $legacyStorageIndexIssues,
            'upload_directory_protection_issues' => $uploadDirectoryProtectionIssues,
            'storage_column_type_issues' => $storageColumnTypeIssues,
            'generated_article_category_issues' => $generatedArticleCategoryIssues,
            'debug_mode_issues' => $debugModeIssues,
            'stale_language_files' => $staleLanguageFiles,
            'stale_installer_temp_dirs' => $staleInstallerTempDirs,
            'form_audits' => $formAudits,
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
                'content_record_duplicate_issues' => count($contentRecordDuplicateIssues),
                'content_record_duplicate_rows_to_remove' => $contentRecordDuplicateRowsToRemove,
                'bf_content_record_orphan_forms' => count($bfContentRecordOrphans),
                'bf_content_record_orphan_rows' => $bfContentRecordOrphanRows,
                'invalid_datetime_sort_issues' => count($invalidDatetimeSortIssues),
                'invalid_datetime_sort_rows' => $invalidDatetimeSortRows,
                'legacy_storage_indexes' => array_sum(array_map(
                    static fn(array $issue): int => count((array) ($issue['indexes'] ?? [])),
                    array_filter($legacyStorageIndexIssues, 'is_array')
                )),
                'upload_directory_protection' => count($uploadDirectoryProtectionIssues),
                'storage_column_type_issues' => count($storageColumnTypeIssues),
                'generated_article_category_issues' => count($generatedArticleCategoryIssues),
                'debug_mode_issues' => count($debugModeIssues),
                'generated_article_category_rows' => $invalidGeneratedArticleCategoryRows,
                'stale_language_files' => count($staleLanguageFiles),
                'stale_installer_temp_dirs' => count($staleInstallerTempDirs),
                'form_audits' => count($formAudits),
                'form_audit_issue_forms' => $formAuditIssueForms,
                'form_audit_issue_checks' => $formAuditIssueChecks,
                'audit_errors' => $auditErrorCount,
                'audit_warnings' => $auditWarningCount,
                'issues_total' => $issuesTotal,
            ],
            'errors' => $errors,
        ];
    }
}
