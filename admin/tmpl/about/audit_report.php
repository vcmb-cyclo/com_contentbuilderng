<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
?>

<div class="card mt-3" id="cb-audit-section">
    <div class="card-body">
        <h3 class="h6 card-title mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TITLE'); ?></h3>

        <?php if (!$hasAuditReport) : ?>
            <div class="alert alert-info mb-0">
                <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_EMPTY'); ?>
            </div>
        <?php else : ?>
            <p class="text-muted small mb-2">
                <?php echo Text::sprintf(
                    'COM_CONTENTBUILDERNG_ABOUT_AUDIT_LAST_RUN',
                    $auditGeneratedAtDisplay,
                    (int) ($auditReport['scanned_tables'] ?? 0)
                ); ?>
            </p>

            <div class="table-responsive mb-3">
                <table id="cb-audit-summary-table" class="table table-sm table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COUNT'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="<?php echo $hasAuditIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('issues_total'); ?></td>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ISSUES_TOTAL'); ?></th>
                        <td><?php echo (int) ($auditSummary['issues_total'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasDuplicateIndexIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('duplicate_indexes'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('duplicate_indexes', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS')); ?></th>
                        <td><?php echo (int) ($auditSummary['duplicate_index_groups'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasDuplicateIndexDropIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('duplicate_indexes_to_drop'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('duplicate_indexes', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_TO_DROP')); ?></th>
                        <td><?php echo (int) ($auditSummary['duplicate_indexes_to_drop'] ?? 0); ?></td>
                    </tr>
                    <tr<?php if ($hasLegacyStorageIndexIssues) : ?> class="table-warning"<?php endif; ?>>
                        <td class="text-muted text-end pe-2"><?php echo (int) $auditSummaryRowNumber('legacy_storage_indexes'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('legacy_storage_indexes', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_STORAGE_INDEXES')); ?></th>
                        <td><?php echo (int) $legacyStorageIndexCount; ?></td>
                    </tr>
                    <tr class="<?php echo htmlspecialchars($hasUploadDirectoryProtectionIssues ? 'table-warning' : '', ENT_QUOTES, 'UTF-8'); ?>">
                        <td class="text-muted text-end pe-2"><?php echo (int) $auditSummaryRowNumber('upload_directory_protection'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('upload_directory_protection', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_UPLOAD_DIRECTORY_PROTECTION')); ?></th>
                        <td><?php echo (int) $uploadDirectoryProtectionCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasLegacyTableIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('historical_tables'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('historical_tables', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_TABLES')); ?></th>
                        <td><?php echo (int) ($auditSummary['historical_tables'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasLegacyMenuIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('historical_menu_entries'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('historical_menu_entries', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_MENU_ENTRIES')); ?></th>
                        <td><?php echo $historicalMenuEntriesCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasTableEncodingIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('table_encoding'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('table_encoding', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE_ENCODING_ISSUES')); ?></th>
                        <td><?php echo (int) ($auditSummary['table_encoding_issues'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasPackedDataIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('packed_data'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('packed_data', Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_PACKED_DATA_TITLE')); ?></th>
                        <td><?php echo (int) ($auditSummary['packed_data_candidates'] ?? ($packedDataAudit['candidates'] ?? 0)); ?></td>
                    </tr>
                    <tr class="<?php echo $hasColumnEncodingIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('column_encoding'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('column_encoding', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN_ENCODING_ISSUES')); ?></th>
                        <td><?php echo (int) ($auditSummary['column_encoding_issues'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasMixedCollationIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('mixed_collations'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('mixed_collations', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MIXED_COLLATIONS')); ?></th>
                        <td><?php echo max(0, count($mixedTableCollations) - 1); ?></td>
                    </tr>
                    <tr class="<?php echo $missingAuditColumnsTableCount > 0 ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('audit_columns'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('audit_columns', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS')); ?></th>
                        <td><?php echo $missingAuditColumnsTableCount; ?></td>
                    </tr>
                    <tr class="<?php echo $missingAuditColumnsTotal > 0 ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('audit_columns_total'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('audit_columns', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS_TOTAL')); ?></th>
                        <td><?php echo $missingAuditColumnsTotal; ?></td>
                    </tr>
                    <tr class="<?php echo $missingFormAuditColumnsTableCount > 0 ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('form_audit_columns'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('form_audit_columns', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_FORM_AUDIT_COLUMNS')); ?></th>
                        <td><?php echo $missingFormAuditColumnsTableCount; ?></td>
                    </tr>
                    <tr class="<?php echo $missingFormAuditColumnsTotal > 0 ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('form_audit_columns_total'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('form_audit_columns', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_FORM_AUDIT_COLUMNS_TOTAL')); ?></th>
                        <td><?php echo $missingFormAuditColumnsTotal; ?></td>
                    </tr>
                    <tr class="<?php echo $hasInvalidDatetimeSortIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('invalid_datetime_sort'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('invalid_datetime_sort', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INVALID_DATETIME_SORT')); ?></th>
                        <td><?php echo $invalidDatetimeSortIssueCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasInvalidDatetimeSortIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('invalid_datetime_sort_rows'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('invalid_datetime_sort', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INVALID_DATETIME_SORT_ROWS')); ?></th>
                        <td><?php echo $invalidDatetimeSortRowCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasStorageColumnTypeIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('storage_column_types'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('storage_column_types', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_COLUMN_TYPES')); ?></th>
                        <td><?php echo $storageColumnTypeIssueCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasPluginDuplicateIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('plugin_duplicates'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('plugin_duplicates', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATE_GROUPS')); ?></th>
                        <td><?php echo $pluginDuplicateGroups; ?></td>
                    </tr>
                    <tr class="<?php echo $hasPluginDuplicateIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('plugin_duplicate_rows'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('plugin_duplicates', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATE_ROWS_TO_REMOVE')); ?></th>
                        <td><?php echo $pluginDuplicateRowsToRemove; ?></td>
                    </tr>
                    <tr class="<?php echo $hasBfFieldSyncIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('bf_field_sync'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('bf_field_sync', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEWS')); ?></th>
                        <td><?php echo $bfFieldSyncViews; ?></td>
                    </tr>
                    <tr class="<?php echo $hasBfFieldSyncIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('bf_field_sync_missing'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('bf_field_sync', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_MISSING_IN_CB')); ?></th>
                        <td><?php echo $bfFieldSyncMissingTotal; ?></td>
                    </tr>
                    <tr class="<?php echo $hasBfFieldSyncIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('bf_field_sync_orphan'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('bf_field_sync', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_EXTRA_IN_CB')); ?></th>
                        <td><?php echo $bfFieldSyncOrphanTotal; ?></td>
                    </tr>
                    <tr class="<?php echo $hasMenuViewIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('menu_view_consistency'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('menu_view_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_VIEW_CONSISTENCY')); ?></th>
                        <td><?php echo (int) ($auditSummary['menu_view_issues'] ?? count($menuViewIssues)); ?></td>
                    </tr>
                    <tr class="<?php echo $hasFrontendPermissionIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('frontend_permission_consistency'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('frontend_permission_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FRONTEND_PERMISSION_CONSISTENCY')); ?></th>
                        <td><?php echo (int) ($auditSummary['frontend_permission_issues'] ?? count($frontendPermissionIssues)); ?></td>
                    </tr>
                    <tr class="<?php echo $hasElementReferenceIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('element_reference_consistency'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('element_reference_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_CONSISTENCY')); ?></th>
                        <td><?php echo (int) ($auditSummary['element_reference_issues'] ?? count($elementReferenceIssues)); ?></td>
                    </tr>
                    <tr class="<?php echo $hasFormAuditIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('form_audits'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('form_audits', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FORM_AUDITS')); ?></th>
                        <td><?php echo count($formAudits); ?></td>
                    </tr>
                    <tr class="<?php echo $hasContentRecordDuplicateIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('content_record_duplicates'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('content_record_duplicates', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CONTENT_RECORD_DUPLICATES')); ?></th>
                        <td><?php echo (int) ($auditSummary['content_record_duplicate_issues'] ?? count($contentRecordDuplicateIssues)); ?></td>
                    </tr>
                    <tr class="<?php echo $hasBfContentRecordOrphans ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('bf_content_record_orphans'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('bf_content_record_orphans', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_CONTENT_RECORD_ORPHANS')); ?></th>
                        <td><?php echo (int) ($auditSummary['bf_content_record_orphan_forms'] ?? count($bfContentRecordOrphans)); ?></td>
                    </tr>
                    <tr class="<?php echo $hasBfContentRecordOrphans ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('bf_content_record_orphan_rows'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('bf_content_record_orphans', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_CONTENT_RECORD_ORPHAN_ROWS')); ?></th>
                        <td><?php echo $bfContentRecordOrphanRows; ?></td>
                    </tr>
                    <tr class="<?php echo $hasContentRecordDuplicateIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('content_record_duplicate_rows'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('content_record_duplicates', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CONTENT_RECORD_DUPLICATE_ROWS')); ?></th>
                        <td><?php echo $contentRecordDuplicateRowsToRemove; ?></td>
                    </tr>
                    <tr class="<?php echo $hasGeneratedArticleCategoryIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('generated_article_categories'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('generated_article_categories', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES')); ?></th>
                        <td><?php echo $generatedArticleCategoryIssueCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasGeneratedArticleCategoryIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('generated_article_category_rows'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('generated_article_categories', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORY_ROWS')); ?></th>
                        <td><?php echo $generatedArticleCategoryRowCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasDebugModeIssues ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('debug_mode'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('debug_mode', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE')); ?></th>
                        <td><?php echo $debugModeIssueCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasStaleLanguageFiles ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('stale_language_files'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('stale_language_files', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_LANGUAGE_FILES')); ?></th>
                        <td><?php echo $staleLanguageFilesCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasStaleInstallerTempDirs ? 'table-warning' : ''; ?>">
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('stale_installer_temp'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('stale_installer_temp', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP')); ?></th>
                        <td><?php echo $staleInstallerTempDirsCount; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('cb_tables_total'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('cb_table_stats', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_TABLES_TOTAL')); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_total'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('cb_ng_tables'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('cb_table_stats', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES')); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_ng_total'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('cb_ng_tables_expected'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('cb_table_stats', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES_EXPECTED')); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_ng_expected'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('cb_ng_tables_missing'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('cb_ng_tables_missing', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES_MISSING')); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_ng_missing'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('cb_storage_tables'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('cb_table_stats', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_STORAGE_TABLES')); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_storage_total'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('cb_estimated_rows'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('cb_table_stats', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_ESTIMATED_ROWS')); ?></th>
                        <td><?php echo number_format((int) ($cbTableSummary['rows_total'] ?? 0), 0, '.', ' '); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted text-end pe-2"><?php echo $auditSummaryRowNumber('cb_estimated_size'); ?></td>
                        <th scope="row"><?php echo $renderAuditSummaryLink('cb_table_stats', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_ESTIMATED_SIZE')); ?></th>
                        <td><?php echo $formatBytes((int) ($cbTableSummary['size_bytes_total'] ?? 0)); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <?php if ((int) ($auditSummary['issues_total'] ?? 0) === 0 && empty($auditErrors)) : ?>
                <div class="alert cb-audit-ok-alert mb-3">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_ISSUES'); ?></span>
                    </span>
                </div>
            <?php endif; ?>

            <div class="cb-audit-detail-sections">
            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('duplicate_indexes'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 2;">
                <h4 class="h6 mt-3<?php echo $hasDuplicateIndexIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('duplicate_indexes', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'), $hasDuplicateIndexIssues); ?></h4>
                <?php if (empty($duplicateIndexes)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_DUPLICATE_INDEXES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <?php echo LayoutHelper::render('about.audit_repair_button', [
                        'issue' => 'duplicate_indexes',
                        'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'),
                    ]); ?>
                    <div class="table-responsive">
                        <table id="cb-audit-duplicate-indexes-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_KEEP'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_DROP'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEXES'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $duplicateIndexRowNumber = 1; ?>
                            <?php foreach ($duplicateIndexes as $duplicateIndex) : ?>
                                <tr>
                                    <td><?php echo $duplicateIndexRowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($duplicateIndex['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($duplicateIndex['keep'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(implode(', ', (array) ($duplicateIndex['drop'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(implode(', ', (array) ($duplicateIndex['indexes'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('legacy_storage_indexes'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 4;">
                <h4 class="h6 mt-3<?php echo $hasLegacyStorageIndexIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('legacy_storage_indexes', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_STORAGE_INDEXES'), $hasLegacyStorageIndexIssues); ?></h4>
                <?php if (empty($legacyStorageIndexIssues)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_LEGACY_STORAGE_INDEXES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <p class="text-muted small"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_STORAGE_INDEXES_DETAIL'); ?></p>
                    <div class="table-responsive">
                        <table id="cb-audit-legacy-storage-indexes-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo htmlspecialchars($auditRowNumberLabel, ENT_QUOTES, 'UTF-8'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_NAME'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_PROPOSED_NAME'); ?></th>
                                <th scope="col"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $legacyStorageIndexRowNumber = 1; ?>
                            <?php foreach ($legacyStorageIndexIssues as $legacyStorageIndexIssue) : ?>
                                <?php
                                $legacyIndexes = array_values(array_filter(
                                    (array) ($legacyStorageIndexIssue['indexes'] ?? []),
                                    'is_array'
                                ));
                                $legacyStorageId = (int) ($legacyStorageIndexIssue['storage_id'] ?? 0);
                                // The repair renames every legacy index of the storage in one pass,
                                // so its button is shown once and spans the storage's rows.
                                foreach ($legacyIndexes as $legacyIndexPosition => $legacyIndex) :
                                ?>
                                    <tr>
                                        <td><?php echo (int) ($legacyStorageIndexRowNumber++); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($legacyStorageIndexIssue['storage_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($legacyStorageIndexIssue['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($legacyIndex['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($legacyIndex['suggested_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php if ($legacyIndexPosition === 0) : ?>
                                            <td class="text-end align-middle" rowspan="<?php echo count($legacyIndexes); ?>">
                                                <?php if ($legacyStorageId > 0) : ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-warning"
                                                        data-cb-audit-ajax-task="about.repairLegacyStorageIndexes"
                                                        data-cb-audit-ajax-field="repair_storage_id"
                                                        data-cb-audit-ajax-value="<?php echo (int) $legacyStorageId; ?>"
                                                        title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_STORAGE_INDEXES_REPAIR_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_STORAGE_INDEXES_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-bs-toggle="tooltip"
                                                        data-bs-placement="top"
                                                    ><span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_STORAGE_INDEXES_REPAIR'); ?></button>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('upload_directory_protection'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 5;">
                <h4 class="h6 mt-3<?php echo $hasUploadDirectoryProtectionIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('upload_directory_protection', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_UPLOAD_DIRECTORY_PROTECTION'), $hasUploadDirectoryProtectionIssues); ?></h4>
                <?php if (empty($uploadDirectoryProtectionIssues)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_UPLOAD_PROTECTION_NONE'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <p class="text-muted small"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_UPLOAD_DIRECTORY_PROTECTION_DESC'); ?></p>
                    <div class="d-flex justify-content-end mb-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-warning"
                            data-cb-audit-ajax-task="about.repairUploadDirectoryProtection"
                            data-cb-audit-ajax-field="repair_upload_directories"
                            data-cb-audit-ajax-value="1"
                            title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_UPLOAD_DIRECTORY_PROTECTION_DESC'), ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                        ><span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIR'); ?></button>
                    </div>
                    <div class="table-responsive">
                        <table id="cb-audit-upload-directory-protection-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo htmlspecialchars($auditRowNumberLabel, ENT_QUOTES, 'UTF-8'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_PATH'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_FORM_SOURCE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FORM'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $uploadDirectoryProtectionRowNumber = 1; ?>
                            <?php foreach ($uploadDirectoryProtectionIssues as $uploadDirectoryProtectionIssue) : ?>
                                <?php if (!is_array($uploadDirectoryProtectionIssue)) { continue; } ?>
                                <?php
                                $uploadSource = strtolower(trim((string) ($uploadDirectoryProtectionIssue['source'] ?? '')));
                                $uploadSourceLabel = match ($uploadSource) {
                                    'form' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_UPLOAD_DIRECTORY_PROTECTION_SOURCE_FORM'),
                                    'element' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_UPLOAD_DIRECTORY_PROTECTION_SOURCE_ELEMENT'),
                                    default => Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'),
                                };
                                ?>
                                <tr>
                                    <td><?php echo (int) ($uploadDirectoryProtectionRowNumber++); ?></td>
                                    <td><code><?php echo htmlspecialchars((string) ($uploadDirectoryProtectionIssue['path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td><?php echo htmlspecialchars($uploadSourceLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) ($uploadDirectoryProtectionIssue['form_id'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('plugin_duplicates'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 19;">
                <h4 class="h6 mt-3<?php echo $hasPluginDuplicateIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('plugin_duplicates', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATES'), $hasPluginDuplicateIssues); ?></h4>
                <?php if (empty($pluginExtensionDuplicates)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_PLUGIN_DUPLICATES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <?php echo LayoutHelper::render('about.audit_repair_button', [
                        'issue' => 'plugin_duplicates',
                        'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATES'),
                    ]); ?>
                    <div class="table-responsive">
                        <table id="cb-audit-plugin-duplicates-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CANONICAL_PLUGIN'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_KEEP'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_DROP'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_ROWS'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $pluginDuplicateRowNumber = 1; ?>
                            <?php foreach ($pluginExtensionDuplicates as $pluginExtensionDuplicate) : ?>
                                <?php
                                $canonicalFolder = trim((string) ($pluginExtensionDuplicate['canonical_folder'] ?? ''));
                                $canonicalElement = trim((string) ($pluginExtensionDuplicate['canonical_element'] ?? ''));
                                $canonicalLabel = trim($canonicalFolder . '/' . $canonicalElement, '/');
                                if ($canonicalLabel === '') {
                                    $canonicalLabel = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                                }

                                $keepId = (int) ($pluginExtensionDuplicate['keep_id'] ?? 0);
                                $dropIds = (array) ($pluginExtensionDuplicate['duplicate_ids'] ?? []);
                                $rows = (array) ($pluginExtensionDuplicate['rows'] ?? []);
                                $rowLabels = [];

                                foreach ($rows as $pluginRow) {
                                    if (!is_array($pluginRow)) {
                                        continue;
                                    }

                                    $rowId = (int) ($pluginRow['extension_id'] ?? 0);
                                    $rowFolder = trim((string) ($pluginRow['folder'] ?? ''));
                                    $rowElement = trim((string) ($pluginRow['element'] ?? ''));
                                    $rowEnabled = (int) ($pluginRow['enabled'] ?? 0) === 1
                                        ? Text::_('JENABLED')
                                        : Text::_('JDISABLED');

                                    $rowLabels[] = '#' . $rowId . ' ' . $rowFolder . '/' . $rowElement . ' [' . $rowEnabled . ']';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $pluginDuplicateRowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars($canonicalLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $keepId; ?></td>
                                    <td><?php echo htmlspecialchars(implode(', ', $dropIds), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(implode(' | ', $rowLabels), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('historical_tables'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 6;">
                <h4 class="h6 mt-3<?php echo $hasLegacyTableIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('historical_tables', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_TABLES'), $hasLegacyTableIssues); ?></h4>
                <?php if (empty($historicalTables)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_HISTORICAL_TABLES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <?php echo LayoutHelper::render('about.audit_repair_button', [
                        'issue' => 'historical_tables',
                        'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_TABLES'),
                    ]); ?>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($historicalTables as $historicalTable) : ?>
                            <li><?php echo htmlspecialchars((string) $historicalTable, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('historical_menu_entries'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 7;">
                <h4 class="h6 mt-3<?php echo $hasLegacyMenuIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('historical_menu_entries', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_MENU_ENTRIES'), $hasLegacyMenuIssues); ?></h4>
                <?php if (empty($historicalMenuEntries)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_HISTORICAL_MENU_ENTRIES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <?php echo LayoutHelper::render('about.audit_repair_button', [
                        'issue' => 'historical_menu_entries',
                        'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_MENU_ENTRIES'),
                    ]); ?>
                    <div class="table-responsive">
                        <table id="cb-audit-historical-menu-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_TITLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_NORMALIZED_TITLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_LINK'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $historicalMenuEntryRowNumber = 1; ?>
                            <?php foreach ($historicalMenuEntries as $historicalMenuEntry) : ?>
                                <tr>
                                    <td><?php echo $historicalMenuEntryRowNumber++; ?></td>
                                    <td><?php echo (int) ($historicalMenuEntry['menu_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($historicalMenuEntry['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($historicalMenuEntry['normalized_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($historicalMenuEntry['link'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('audit_columns'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 12;">
                <h4 class="h6 mt-3<?php echo $hasMissingAuditColumnIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('audit_columns', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS'), $hasMissingAuditColumnIssues); ?></h4>
                <div class="alert alert-info py-1 px-2 small mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_SCOPE_NOTE'); ?></div>
                <?php if (empty($missingAuditColumns)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_MISSING_AUDIT_COLUMNS'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <?php echo LayoutHelper::render('about.audit_repair_button', [
                        'issue' => 'audit_columns',
                        'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS'),
                    ]); ?>
                    <div class="table-responsive">
                        <table id="cb-audit-missing-audit-columns-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BYTABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $missingAuditColumnRowNumber = 1; ?>
                            <?php foreach ($missingAuditColumns as $missingAuditColumn) :
                                $missingAuditColumnNames = (array) ($missingAuditColumn['missing'] ?? []);
                                $missingAuditColumnIsId = in_array('id', $missingAuditColumnNames, true);
                                $missingAuditColumnStorageId = (int) ($missingAuditColumn['storage_id'] ?? 0);
                                $missingAuditColumnStorageName = (string) ($missingAuditColumn['storage_name'] ?? '');
                            ?>
                                <tr class="<?php echo $missingAuditColumnIsId ? 'table-danger' : ''; ?>">
                                    <td><?php echo $missingAuditColumnRowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($missingAuditColumn['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $missingAuditColumnStorageId; ?></td>
                                    <td>
                                        <?php if ($missingAuditColumnStorageId > 0) : ?>
                                            <a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . $missingAuditColumnStorageId, false), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo htmlspecialchars($missingAuditColumnStorageName, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo htmlspecialchars($missingAuditColumnStorageName, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo Text::_((int) ($missingAuditColumn['bytable'] ?? 0) > 0 ? 'JYES' : 'JNO'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(implode(', ', array_diff($missingAuditColumnNames, ['id'])), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($missingAuditColumnIsId) : ?>
                                            <span class="badge bg-danger" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_ID_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_ID_BADGE'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('form_audit_columns'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 14;">
                <h4 class="h6 mt-3<?php echo $hasMissingFormAuditColumnIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('form_audit_columns', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_FORM_AUDIT_COLUMNS'), $hasMissingFormAuditColumnIssues); ?></h4>
                <?php if (empty($missingFormAuditColumns)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_MISSING_FORM_AUDIT_COLUMNS'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <?php echo LayoutHelper::render('about.audit_repair_button', [
                        'issue' => 'form_audit_columns',
                        'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_FORM_AUDIT_COLUMNS'),
                    ]); ?>
                    <div class="table-responsive">
                        <table id="cb-audit-missing-form-audit-columns-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $missingFormAuditColumnRowNumber = 1; ?>
                            <?php foreach ($missingFormAuditColumns as $missingFormAuditColumn) : ?>
                                <?php
                                $missingColumns = array_values(array_filter(array_map(
                                    static fn($column): string => trim((string) $column),
                                    (array) ($missingFormAuditColumn['missing'] ?? [])
                                )));
                                ?>
                                <tr>
                                    <td><?php echo $missingFormAuditColumnRowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($missingFormAuditColumn['table'] ?? '#__contentbuilderng_forms'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(implode(', ', $missingColumns), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('invalid_datetime_sort'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 16;">
                <h4 class="h6 mt-3<?php echo $hasInvalidDatetimeSortIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('invalid_datetime_sort', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INVALID_DATETIME_SORT'), $hasInvalidDatetimeSortIssues); ?></h4>
                <?php if (empty($invalidDatetimeSortIssues)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INVALID_DATETIME_SORT_OK'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table id="cb-audit-invalid-datetime-sort-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FORM'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COUNT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SAMPLE_VALUES'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $invalidDatetimeSortRowNumber = 1; ?>
                            <?php foreach ($invalidDatetimeSortIssues as $invalidDatetimeSortIssue) : ?>
                                <?php
                                $sampleValues = array_values(array_filter(array_map(
                                    static fn($value): string => trim((string) $value),
                                    (array) ($invalidDatetimeSortIssue['sample_values'] ?? [])
                                )));
                                $formLabel = trim((string) ($invalidDatetimeSortIssue['form_name'] ?? ''));
                                if ($formLabel === '') {
                                    $formLabel = '#' . (int) ($invalidDatetimeSortIssue['form_id'] ?? 0);
                                }
                                $elementLabel = trim((string) ($invalidDatetimeSortIssue['element_label'] ?? ''));
                                if ($elementLabel === '') {
                                    $elementLabel = '#' . (int) ($invalidDatetimeSortIssue['element_id'] ?? 0);
                                }
                                ?>
                                <tr>
                                    <td><?php echo $invalidDatetimeSortRowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars($formLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($invalidDatetimeSortIssue['storage_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($invalidDatetimeSortIssue['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($elementLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($invalidDatetimeSortIssue['column'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) ($invalidDatetimeSortIssue['invalid_count'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($sampleValues === [] ? '-' : implode(', ', $sampleValues), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('storage_column_types'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 18;">
                <h4 class="h6 mt-3<?php echo $hasStorageColumnTypeIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('storage_column_types', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_COLUMN_TYPES'), $hasStorageColumnTypeIssues); ?></h4>
                <?php if (empty($storageColumnTypeIssues)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_COLUMN_TYPES_OK'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table id="cb-audit-storage-column-types-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_EXPECTED_TYPE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PHYSICAL_TYPE'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $storageColumnTypeRowNumber = 1; ?>
                            <?php foreach ($storageColumnTypeIssues as $storageColumnTypeIssue) : ?>
                                <?php
                                $fieldLabel = trim((string) ($storageColumnTypeIssue['field_title'] ?? ''));
                                $columnName = trim((string) ($storageColumnTypeIssue['column'] ?? ''));
                                $columnDisplay = $fieldLabel !== '' && $fieldLabel !== $columnName ? ($fieldLabel . ' (' . $columnName . ')') : $columnName;
                                $expectedDisplay = trim((string) ($storageColumnTypeIssue['expected_label'] ?? ''));
                                $expectedSql = trim((string) ($storageColumnTypeIssue['expected_sql'] ?? ''));
                                if ($expectedSql !== '') {
                                    $expectedDisplay .= ' (' . $expectedSql . ')';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $storageColumnTypeRowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($storageColumnTypeIssue['storage_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($storageColumnTypeIssue['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($columnDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($expectedDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($storageColumnTypeIssue['physical_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('bf_field_sync'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 21;">
                <h4 class="h6 mt-3<?php echo $hasBfFieldSyncIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('bf_field_sync', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC'), $hasBfFieldSyncIssues); ?></h4>
            <?php if (empty($bfFieldSyncIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_NO_ISSUES'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <?php echo LayoutHelper::render('about.audit_repair_button', [
                    'issue' => 'bf_field_sync',
                    'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC'),
                ]); ?>
                <div class="table-responsive">
                    <table id="cb-audit-bf-field-sync-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEW_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEW'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_SOURCE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_MISSING'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_ORPHAN'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $bfFieldSyncIssueRowNumber = 1; ?>
                        <?php foreach ($bfFieldSyncIssues as $bfFieldSyncIssue) : ?>
                            <?php
                            $formId = (int) ($bfFieldSyncIssue['form_id'] ?? 0);
                            $formName = trim((string) ($bfFieldSyncIssue['form_name'] ?? ''));
                            $formNameDisplay = $formName !== '' ? $formName : ('#' . $formId);
                            $formEditLink = $formId > 0
                                ? Route::_('index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . $formId, false)
                                : '';

                            $sourceExists = (int) ($bfFieldSyncIssue['source_exists'] ?? 0) === 1;
                            $sourceType = trim((string) ($bfFieldSyncIssue['type'] ?? ''));
                            $sourceReferenceId = (int) ($bfFieldSyncIssue['reference_id'] ?? 0);
                            $sourceDisplay = $sourceType !== '' ? $sourceType : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');

                            if ($sourceReferenceId > 0) {
                                $sourceDisplay .= ' #' . $sourceReferenceId;
                            }

                            $sourceName = trim((string) ($bfFieldSyncIssue['source_name'] ?? ''));
                            if ($sourceName !== '') {
                                $sourceDisplay .= ' (' . $sourceName . ')';
                            }

                            $missingCount = (int) ($bfFieldSyncIssue['missing_count'] ?? 0);
                            $missingList = (array) ($bfFieldSyncIssue['missing_in_cb'] ?? []);
                            $orphanCount = (int) ($bfFieldSyncIssue['orphan_count'] ?? 0);
                            $orphanList = (array) ($bfFieldSyncIssue['orphan_in_cb'] ?? []);
                            ?>
                            <tr>
                                <td><?php echo $bfFieldSyncIssueRowNumber++; ?></td>
                                <td><?php echo $formId; ?></td>
                                <td>
                                    <?php if ($formEditLink !== '') : ?>
                                        <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($formNameDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars($formNameDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sourceExists) : ?>
                                        <?php echo htmlspecialchars($sourceDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php else : ?>
                                        <span class="text-warning-emphasis"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_SOURCE_MISSING'); ?></span>
                                        <br>
                                        <small><?php echo htmlspecialchars($sourceDisplay, ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $missingCount; ?>
                                    <?php if ($missingCount > 0) : ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($formatAuditIssueList($missingList), ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $orphanCount; ?>
                                    <?php if ($orphanCount > 0) : ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($formatAuditIssueList($orphanList), ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('menu_view_consistency'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 24;">
                <h4 class="h6 mt-3<?php echo $hasMenuViewIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('menu_view_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_VIEW_CONSISTENCY'), $hasMenuViewIssues); ?></h4>
            <?php if (empty($menuViewIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_VIEW_CONSISTENCY_OK'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table id="cb-audit-menu-view-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_TITLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_TARGET'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_LINK'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ISSUES'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $menuViewIssueRowNumber = 1; ?>
                        <?php foreach ($menuViewIssues as $menuViewIssue) : ?>
                            <?php
                            $menuId = (int) ($menuViewIssue['menu_id'] ?? 0);
                            $menuEditLink = $menuId > 0
                                ? Route::_('index.php?option=com_menus&view=item&client_id=0&layout=edit&id=' . $menuId, false)
                                : '';
                            $menuTitle = trim((string) ($menuViewIssue['title'] ?? ''));
                            $menuTitle = $menuTitle !== '' ? $menuTitle : ('#' . $menuId);
                            $menuLink = trim((string) ($menuViewIssue['link'] ?? ''));
                            $menuLinkUrl = $menuLink !== ''
                                ? Route::link('site', $menuLink, false, Route::TLS_IGNORE, true)
                                : '';
                            $menuIssueItems = array_values(array_filter(array_map(
                                static fn($issue): string => trim((string) $issue),
                                (array) ($menuViewIssue['issues'] ?? [])
                            )));
                            ?>
                            <tr>
                                <td><?php echo $menuViewIssueRowNumber++; ?></td>
                                <td><?php echo $menuId; ?></td>
                                <td>
                                    <?php if ($menuEditLink !== '') : ?>
                                        <a href="<?php echo htmlspecialchars($menuEditLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($menuTitle, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars($menuTitle, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($menuViewIssue['target'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($menuLinkUrl !== '') : ?>
                                        <a href="<?php echo htmlspecialchars($menuLinkUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($menuLink, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars($menuLink, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <ol class="mb-0 ps-3">
                                        <?php foreach ($menuIssueItems as $menuIssueItem) : ?>
                                            <li><?php echo htmlspecialchars($menuIssueItem, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('frontend_permission_consistency'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 25;">
                <h4 class="h6 mt-3<?php echo $hasFrontendPermissionIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('frontend_permission_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FRONTEND_PERMISSION_CONSISTENCY'), $hasFrontendPermissionIssues); ?></h4>
            <?php if (empty($frontendPermissionIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FRONTEND_PERMISSION_CONSISTENCY_OK'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table id="cb-audit-frontend-permission-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEW'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ISSUES'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $frontendPermissionIssueRowNumber = 1; ?>
                        <?php foreach ($frontendPermissionIssues as $frontendPermissionIssue) : ?>
                            <?php
                            $formId = (int) ($frontendPermissionIssue['form_id'] ?? 0);
                            $formEditLink = $formId > 0
                                ? Route::_('index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . $formId, false)
                                : '';
                            $formName = trim((string) ($frontendPermissionIssue['form_name'] ?? ''));
                            $formName = $formName !== '' ? $formName : ('#' . $formId);
                            $permissionIssueItems = array_values(array_filter(array_map(
                                static fn($issue): string => trim((string) $issue),
                                (array) ($frontendPermissionIssue['issues'] ?? [])
                            )));
                            $permissionIssueItemsHtml = [];

                            foreach ($permissionIssueItems as $permissionIssueItem) {
                                if (preg_match('/^Public menu #(\d+)\b(.*)$/', $permissionIssueItem, $matches) === 1) {
                                    $issueMenuId = (int) ($matches[1] ?? 0);
                                    $issueSuffix = trim((string) ($matches[2] ?? ''));
                                    $issueMenuLink = $issueMenuId > 0
                                        ? Route::_('index.php?option=com_menus&view=item&client_id=0&layout=edit&id=' . $issueMenuId, false)
                                        : '';

                                    if ($issueMenuLink !== '') {
                                        $permissionIssueItemsHtml[] = 'Public <a href="'
                                            . htmlspecialchars($issueMenuLink, ENT_QUOTES, 'UTF-8')
                                            . '">menu #'
                                            . $issueMenuId
                                            . '</a>'
                                            . ($issueSuffix !== '' ? ' ' . htmlspecialchars($issueSuffix, ENT_QUOTES, 'UTF-8') : '');
                                        continue;
                                    }
                                }

                                $permissionIssueItemsHtml[] = htmlspecialchars($permissionIssueItem, ENT_QUOTES, 'UTF-8');
                            }
                            ?>
                            <tr>
                                <td><?php echo $frontendPermissionIssueRowNumber++; ?></td>
                                <td><?php echo $formId; ?></td>
                                <td>
                                    <?php if ($formEditLink !== '') : ?>
                                        <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <ol class="mb-0 ps-3">
                                        <?php foreach ($permissionIssueItemsHtml as $permissionIssueItemHtml) : ?>
                                            <li><?php echo $permissionIssueItemHtml; ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('element_reference_consistency'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 26;">
                <h4 class="h6 mt-3<?php echo $hasElementReferenceIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('element_reference_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_CONSISTENCY'), $hasElementReferenceIssues); ?></h4>
            <?php if (empty($elementReferenceIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_CONSISTENCY_OK'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <?php if ($hasElementReferenceRepairableIssues) : ?>
                    <?php echo LayoutHelper::render('about.audit_repair_button', [
                        'issue' => 'element_reference_consistency',
                        'description' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_ELEMENT_REFERENCE_DESC'),
                    ]); ?>
                <?php endif; ?>
                <div class="table-responsive">
                    <table id="cb-audit-element-reference-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEW'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_TYPE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ISSUES'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $elementReferenceIssueRowNumber = 1; ?>
                        <?php foreach ($elementReferenceIssues as $elementReferenceIssue) : ?>
                            <?php
                            $formId = (int) ($elementReferenceIssue['form_id'] ?? 0);
                            $formEditLink = $formId > 0
                                ? Route::_('index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . $formId, false)
                                : '';
                            $formName = trim((string) ($elementReferenceIssue['form_name'] ?? ''));
                            $formName = $formName !== '' ? $formName : ('#' . $formId);
                            $referenceIssueItems = [];
                            $emptyReferenceIds = (array) ($elementReferenceIssue['empty_reference_ids'] ?? []);
                            if ($emptyReferenceIds !== []) {
                                $referenceIssueItems[] = Text::sprintf(
                                    'COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_EMPTY',
                                    $formatAuditIssueList($emptyReferenceIds, 12)
                                );
                            }

                            foreach ((array) ($elementReferenceIssue['duplicate_reference_ids'] ?? []) as $duplicateReferenceId) {
                                if (!is_array($duplicateReferenceId)) {
                                    continue;
                                }

                                $referenceIssueItems[] = Text::sprintf(
                                    'COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_DUPLICATE',
                                    (string) ($duplicateReferenceId['reference_id'] ?? ''),
                                    (int) ($duplicateReferenceId['count'] ?? 0),
                                    $formatAuditIssueList((array) ($duplicateReferenceId['labels'] ?? []), 12)
                                );
                            }

                            foreach ((array) ($elementReferenceIssue['orphan_reference_ids'] ?? []) as $orphanReferenceId) {
                                if (!is_array($orphanReferenceId)) {
                                    continue;
                                }

                                $referenceIssueItems[] = Text::sprintf(
                                    'COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_ORPHAN',
                                    (string) ($orphanReferenceId['reference_id'] ?? ''),
                                    (string) ($orphanReferenceId['label'] ?? '')
                                );
                            }

                            ?>
                            <tr>
                                <td><?php echo $elementReferenceIssueRowNumber++; ?></td>
                                <td><?php echo $formId; ?></td>
                                <td>
                                    <?php if ($formEditLink !== '') : ?>
                                        <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($elementReferenceIssue['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <ol class="mb-0 ps-3">
                                        <?php foreach ($referenceIssueItems as $referenceIssueItem) : ?>
                                            <li><?php echo htmlspecialchars($referenceIssueItem, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('form_audits'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 27;">
                <h4 class="h6 mt-3<?php echo $hasFormAuditIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('form_audits', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FORM_AUDITS'), $hasFormAuditIssues); ?></h4>
                <?php if ($formAudits === []) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FORM_AUDITS_NONE'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table id="cb-audit-form-audits-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FORM'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CHECKS_HEADING'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $formAuditRowNumber = 1; ?>
                            <?php foreach ($formAudits as $formAudit) : ?>
                                <?php
                                $formAudit = is_array($formAudit) ? $formAudit : [];
                                $formAuditForm = (array) ($formAudit['form'] ?? []);
                                $formId = (int) ($formAuditForm['id'] ?? 0);
                                $formName = trim((string) ($formAuditForm['name'] ?? ''));
                                $formName = $formName !== '' ? $formName : ('#' . $formId);
                                $formEditLink = $formId > 0
                                    ? Route::_('index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . $formId, false)
                                    : '';
                                $formAuditChecks = array_values(array_filter(
                                    (array) ($formAudit['checks'] ?? []),
                                    static fn($check): bool => is_array($check)
                                ));
                                $formAuditErrors = array_values(array_filter(
                                    $formAuditChecks,
                                    static fn(array $check): bool => (string) ($check['status'] ?? '') === 'error'
                                ));
                                $formAuditWarnings = array_values(array_filter(
                                    $formAuditChecks,
                                    static fn(array $check): bool => (string) ($check['status'] ?? '') === 'warning'
                                ));
                                $formAuditHasIssues = $formAuditErrors !== [] || $formAuditWarnings !== [];
                                $formAuditStatusClass = $formAuditErrors !== []
                                    ? 'bg-danger'
                                    : ($formAuditWarnings !== [] ? 'text-bg-warning' : 'bg-success');
                                $formAuditStatusKey = $formAuditErrors !== []
                                    ? 'COM_CONTENTBUILDERNG_AUDIT_STATUS_ERROR'
                                    : ($formAuditWarnings !== [] ? 'COM_CONTENTBUILDERNG_AUDIT_STATUS_WARNING' : 'COM_CONTENTBUILDERNG_AUDIT_STATUS_OK');
                                ?>
                                <tr class="<?php echo $formAuditHasIssues ? 'table-warning' : ''; ?>">
                                    <td><?php echo $formAuditRowNumber++; ?></td>
                                    <td>
                                        <?php if ($formEditLink !== '') : ?>
                                            <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                        <?php if ($formId > 0) : ?>
                                            <span class="text-muted">(#<?php echo $formId; ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo $formAuditStatusClass; ?>"><?php echo Text::_($formAuditStatusKey); ?></span></td>
                                    <td>
                                        <?php if (!$formAuditHasIssues) : ?>
                                            <span class="cb-audit-section-title">
                                                <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                                                <span><?php echo Text::_('COM_CONTENTBUILDERNG_AUDIT_CHECK_ALL_OK'); ?></span>
                                            </span>
                                        <?php else : ?>
                                            <ul class="mb-0 ps-3">
                                                <?php foreach ($formAuditChecks as $formAuditCheck) : ?>
                                                    <?php if ((string) ($formAuditCheck['status'] ?? '') === 'ok') { continue; } ?>
                                                        <?php $formAuditReference = trim((string) ($formAuditCheck['reference'] ?? '')); ?>
                                                    <li>
                                                        <?php echo htmlspecialchars((string) ($formAuditCheck['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if ($formAuditReference !== '') : ?>
                                                            <small class="d-block text-muted" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_REFERENCE_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php echo htmlspecialchars(Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_REFERENCE', $formAuditReference), ENT_QUOTES, 'UTF-8'); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if (in_array((string) ($formAuditCheck['code'] ?? ''), ['unknown_marker_details', 'unknown_marker_edit'], true) && $formId > 0 && trim((string) ($formAuditCheck['field'] ?? '')) !== '' && trim((string) ($formAuditCheck['template_type'] ?? '')) !== '') : ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-warning ms-2"
                                                                data-cb-audit-ajax-task="about.repairFormUnknownMarker"
                                                                data-cb-audit-ajax-field="form_id"
                                                                data-cb-audit-ajax-value="<?php echo htmlspecialchars((string) $formId, ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-cb-audit-ajax-marker-name="<?php echo htmlspecialchars((string) $formAuditCheck['field'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-cb-audit-ajax-template-type="<?php echo htmlspecialchars((string) $formAuditCheck['template_type'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                title="<?php echo htmlspecialchars(Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR_TIP', Text::_((string) ($formAuditCheck['template_type'] ?? '') === 'details' ? 'COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY' : 'COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY')), ENT_QUOTES, 'UTF-8'); ?>"
                                                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                            >
                                                                <span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR'); ?>
                                                            </button>
                                                        <?php elseif ((string) ($formAuditCheck['code'] ?? '') === 'theme_empty' && $formId > 0) : ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-warning ms-2"
                                                                value="thoth"
                                                                data-cb-audit-ajax-task="about.repairFormThemePlugin"
                                                                data-cb-audit-ajax-field="form_id"
                                                                data-cb-audit-ajax-value="<?php echo $formId; ?>"
                                                                title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_THEME_REPAIR_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_THEME_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                            >
                                                                <span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_AUDIT_THEME_REPAIR'); ?>
                                                            </button>
                                                        <?php elseif ((string) ($formAuditCheck['code'] ?? '') === 'editable_template_empty' && $formId > 0) : ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-warning ms-2"
                                                                value="thoth"
                                                                data-cb-audit-ajax-task="about.repairFormEditableTemplate"
                                                                data-cb-audit-ajax-field="form_id"
                                                                data-cb-audit-ajax-value="<?php echo $formId; ?>"
                                                                title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                            >
                                                                <span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR'); ?>
                                                            </button>
                                                        <?php elseif ((string) ($formAuditCheck['code'] ?? '') === 'details_template_empty' && $formId > 0) : ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-warning ms-2"
                                                                value="thoth"
                                                                data-cb-audit-ajax-task="about.repairFormDetailsTemplate"
                                                                data-cb-audit-ajax-field="form_id"
                                                                data-cb-audit-ajax-value="<?php echo $formId; ?>"
                                                                title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_DETAILS_TEMPLATE_REPAIR_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_DETAILS_TEMPLATE_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                            >
                                                                <span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_AUDIT_DETAILS_TEMPLATE_REPAIR'); ?>
                                                            </button>
                                                        <?php elseif ((string) ($formAuditCheck['code'] ?? '') === 'templates_empty' && $formId > 0) : ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-warning ms-2"
                                                                value="thoth"
                                                                data-cb-audit-ajax-task="about.repairFormTemplates"
                                                                data-cb-audit-ajax-field="form_id"
                                                                data-cb-audit-ajax-value="<?php echo $formId; ?>"
                                                                title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_TEMPLATES_REPAIR_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                            >
                                                                <span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('content_record_duplicates'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 28;">
                <h4 class="h6 mt-3<?php echo $hasContentRecordDuplicateIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('content_record_duplicates', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CONTENT_RECORD_DUPLICATES'), $hasContentRecordDuplicateIssues); ?></h4>
            <?php if (empty($contentRecordDuplicateIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CONTENT_RECORD_DUPLICATES_OK'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <?php echo LayoutHelper::render('about.audit_repair_button', [
                    'issue' => 'content_record_duplicates',
                    'description' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_CONTENT_RECORD_DUPLICATES_DESC'),
                ]); ?>
                <div class="table-responsive">
                    <table id="cb-audit-content-record-duplicates-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_TYPE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CONTENT_RECORD_REFERENCE_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CONTENT_RECORD_RECORD_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_KEEP'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_DROP'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $contentRecordDuplicateRowNumber = 1; ?>
                        <?php foreach ($contentRecordDuplicateIssues as $contentRecordDuplicateIssue) : ?>
                            <?php
                            $contentRecordDuplicateType = trim((string) ($contentRecordDuplicateIssue['type'] ?? ''));
                            $contentRecordDuplicateType = $contentRecordDuplicateType !== '' ? $contentRecordDuplicateType : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                            $contentRecordDuplicateKeepId = (int) ($contentRecordDuplicateIssue['keep_id'] ?? 0);
                            $contentRecordDuplicateDropIds = array_map(
                                static fn($id): int => (int) $id,
                                (array) ($contentRecordDuplicateIssue['duplicate_ids'] ?? [])
                            );
                            ?>
                            <tr>
                                <td><?php echo $contentRecordDuplicateRowNumber++; ?></td>
                                <td><?php echo htmlspecialchars($contentRecordDuplicateType, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) ($contentRecordDuplicateIssue['reference_id'] ?? 0); ?></td>
                                <td><?php echo (int) ($contentRecordDuplicateIssue['record_id'] ?? 0); ?></td>
                                <td><?php echo $contentRecordDuplicateKeepId; ?></td>
                                <td><?php echo htmlspecialchars(implode(', ', $contentRecordDuplicateDropIds), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('bf_content_record_orphans'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 29;">
                <h4 class="h6 mt-3<?php echo $hasBfContentRecordOrphans ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('bf_content_record_orphans', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_CONTENT_RECORD_ORPHANS'), $hasBfContentRecordOrphans); ?></h4>
            <?php if ($bfContentRecordOrphans === []) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title"><span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span><span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_CONTENT_RECORD_ORPHANS_OK'); ?></span></span>
                </div>
            <?php else : ?>
                <?php echo LayoutHelper::render('about.audit_repair_button', [
                    'issue' => 'bf_content_record_orphans',
                    'description' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_BF_CONTENT_RECORD_ORPHANS_DESC'),
                ]); ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead><tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FORM_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_RECORD_IDS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_RECORD_IDS'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($bfContentRecordOrphans as $index => $orphanForm) : ?>
                            <?php $orphanRecords = (array) ($orphanForm['records'] ?? []); ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo (int) ($orphanForm['form_id'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars(implode(', ', array_map('intval', array_column($orphanRecords, 'record_id'))), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(implode(', ', array_map('intval', array_column($orphanRecords, 'cb_record_id'))), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('generated_article_categories'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 32;">
                <h4 class="h6 mt-3<?php echo $hasGeneratedArticleCategoryIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('generated_article_categories', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES'), $hasGeneratedArticleCategoryIssues); ?></h4>
            <?php if (empty($generatedArticleCategoryIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_OK'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <label class="form-check mb-0">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            data-cb-select-all="generated-article-categories"
                            aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_SELECT_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                        <span class="form-check-label"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_SELECT_ALL'); ?></span>
                    </label>
                    <button
                        type="submit"
                        class="btn btn-sm btn-warning"
                        data-cb-audit-ajax-task="about.repairGeneratedArticleCategories"
                        data-cb-audit-ajax-field="form_ids"
                        data-cb-audit-ajax-checkbox-selector="[data-cb-select-item=&quot;generated-article-categories&quot;]"
                        title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_REPAIR_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                        onclick="this.form.elements['task'].value='about.repairGeneratedArticleCategories';"
                    >
                        <span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_REPAIR'); ?>
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="cb-audit-generated-article-categories-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    data-cb-select-all="generated-article-categories"
                                    aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_SELECT_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                <span class="visually-hidden"><?php echo $auditRowNumberLabel; ?></span>
                            </th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEW'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_CATEGORY'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORY_ROWS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DETAILS'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $generatedArticleCategoryIssueRowNumber = 1; ?>
                        <?php foreach ($generatedArticleCategoryIssues as $generatedArticleCategoryIssue) : ?>
                            <?php
                            if (!is_array($generatedArticleCategoryIssue)) {
                                continue;
                            }
                            $formId = (int) ($generatedArticleCategoryIssue['form_id'] ?? 0);
                            $formEditLink = $formId > 0
                                ? Route::_('index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . $formId, false)
                                : '';
                            $formName = trim((string) ($generatedArticleCategoryIssue['form_name'] ?? ''));
                            $formName = $formName !== '' ? $formName : ('#' . $formId);
                            $defaultCategoryId = (int) ($generatedArticleCategoryIssue['default_category_id'] ?? 0);
                            $defaultCategoryTitle = trim((string) ($generatedArticleCategoryIssue['default_category_title'] ?? ''));
                            $defaultCategoryLabel = $defaultCategoryId > 0
                                ? '#' . $defaultCategoryId . ($defaultCategoryTitle !== '' ? ' - ' . $defaultCategoryTitle : '')
                                : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                            $invalidArticles = (array) ($generatedArticleCategoryIssue['invalid_articles'] ?? []);
                            $invalidArticleLabels = [];
                            foreach ($invalidArticles as $invalidArticle) {
                                if (!is_array($invalidArticle)) {
                                    continue;
                                }
                                $invalidArticleLabels[] = '#' . (int) ($invalidArticle['article_id'] ?? 0)
                                    . ' / record #' . (int) ($invalidArticle['record_id'] ?? 0)
                                    . ' / catid ' . (int) ($invalidArticle['catid'] ?? 0);
                            }
                            $details = $invalidArticleLabels !== []
                                ? implode(', ', $invalidArticleLabels)
                                : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                            ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        class="form-check-input" data-cb-select-item="generated-article-categories"
                                        name="form_ids[]"
                                        value="<?php echo $formId; ?>"
                                        aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_SELECT_FORM', $formName), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                    <span class="ms-1"><?php echo $generatedArticleCategoryIssueRowNumber++; ?></span>
                                </td>
                                <td><?php echo $formId; ?></td>
                                <td>
                                    <?php if ($formEditLink !== '') : ?>
                                        <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($defaultCategoryLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) ($generatedArticleCategoryIssue['invalid_article_count'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($details, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('stale_language_files'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 35;">
                <h4 class="h6 mt-3<?php echo $hasStaleLanguageFiles ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('stale_language_files', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_LANGUAGE_FILES'), $hasStaleLanguageFiles); ?></h4>
                <?php if (empty($staleLanguageFiles)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_STALE_LANGUAGE_FILES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <?php echo LayoutHelper::render('about.audit_repair_button', [
                        'issue' => 'stale_language_files',
                        'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_LANGUAGE_FILES'),
                    ]); ?>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($staleLanguageFiles as $staleLanguageFile) : ?>
                            <?php if (!is_array($staleLanguageFile)) { continue; } ?>
                            <li>
                                <code><?php echo htmlspecialchars((string) ($staleLanguageFile['file'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                                <span class="text-muted ms-1">(<?php echo htmlspecialchars((string) ($staleLanguageFile['scope'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars((string) ($staleLanguageFile['lang_tag'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('debug_mode'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 34;">
                <h4 class="h6 mt-3<?php echo $hasDebugModeIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('debug_mode', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE'), $hasDebugModeIssues); ?></h4>
                <?php if (empty($debugModeIssues)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_OK'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <div class="alert alert-warning">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_INTRO'); ?>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <label class="form-check mb-0">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                data-cb-select-all="debug-mode"
                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_SELECT_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <span class="form-check-label"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_SELECT_ALL'); ?></span>
                        </label>
                        <button
                            type="button"
                            class="btn btn-sm btn-warning"
                            data-cb-audit-ajax-task="about.repairDebugMode"
                            data-cb-audit-ajax-field="form_ids"
                            data-cb-audit-ajax-checkbox-selector="[data-cb-select-item=&quot;debug-mode&quot;]"
                            title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_REPAIR_SELECTED_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_REPAIR_SELECTED'), ENT_QUOTES, 'UTF-8'); ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                        >
                            <span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_REPAIR_SELECTED'); ?>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="cb-audit-debug-mode-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        data-cb-select-all="debug-mode"
                                        aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_SELECT_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </th>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEW'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_OPTIONS'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ACTIONS'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $debugModeRowNumber = 1; ?>
                            <?php foreach ($debugModeIssues as $debugModeIssue) : ?>
                                <?php if (!is_array($debugModeIssue)) { continue; } ?>
                                <?php
                                $debugFormId = (int) ($debugModeIssue['form_id'] ?? 0);
                                if ($debugFormId <= 0) {
                                    continue;
                                }
                                $debugFormName = trim((string) ($debugModeIssue['form_name'] ?? ''));
                                $debugFormName = $debugFormName !== '' ? $debugFormName : ('#' . $debugFormId);
                                $debugFormEditLink = Route::_('index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . $debugFormId, false);
                                $debugOptionLabels = [];
                                foreach ((array) ($debugModeIssue['options'] ?? []) as $debugOptionKey) {
                                    $debugOptionLabels[] = Text::_((string) $debugOptionKey);
                                }
                                $debugOptions = $debugOptionLabels !== []
                                    ? implode(', ', $debugOptionLabels)
                                    : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                                ?>
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            data-cb-select-item="debug-mode"
                                            name="form_ids[]"
                                            value="<?php echo $debugFormId; ?>"
                                            aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_SELECT_ONE', $debugFormName), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                    </td>
                                    <td><?php echo $debugModeRowNumber++; ?></td>
                                    <td><?php echo $debugFormId; ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($debugFormEditLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($debugFormName, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php if ((int) ($debugModeIssue['published'] ?? 0) !== 1) : ?>
                                            <span class="badge text-bg-secondary ms-1"><?php echo Text::_('JUNPUBLISHED'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($debugOptions, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-warning"
                                            data-cb-audit-ajax-task="about.repairDebugMode"
                                            data-cb-audit-ajax-field="form_id"
                                            data-cb-audit-ajax-value="<?php echo $debugFormId; ?>"
                                            title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_REPAIR_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                        ><span class="fa-solid fa-wrench" aria-hidden="true"></span></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('stale_installer_temp'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 36;">
                <h4 class="h6 mt-3<?php echo $hasStaleInstallerTempDirs ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('stale_installer_temp', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP'), $hasStaleInstallerTempDirs); ?></h4>
                <?php if (empty($staleInstallerTempDirs)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_STALE_INSTALLER_TEMP'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <label class="form-check mb-0">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                data-cb-select-all="stale-installer-temp"
                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_SELECT_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <span class="form-check-label"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_SELECT_ALL'); ?></span>
                        </label>
                        <button
                            type="button"
                            class="btn btn-sm btn-danger"
                            data-cb-audit-ajax-task="about.deleteStaleInstallerTemp"
                            data-cb-audit-ajax-field="stale_installer_temp_paths"
                            data-cb-audit-ajax-checkbox-selector="[data-cb-select-item=&quot;stale-installer-temp&quot;]"
                            title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETE_SELECTED_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETE_SELECTED'), ENT_QUOTES, 'UTF-8'); ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                        >
                            <span class="fa-solid fa-trash me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETE_SELECTED'); ?>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="cb-audit-stale-installer-temp-table" class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th scope="col">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        data-cb-select-all="stale-installer-temp"
                                        aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_SELECT_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </th>
                                <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_PATH'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_MODIFIED'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ACTIONS'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $staleInstallerTempRowNumber = 1; ?>
                            <?php foreach ($staleInstallerTempDirs as $staleInstallerTempDir) : ?>
                                <?php if (!is_array($staleInstallerTempDir)) { continue; } ?>
                                <?php $staleInstallerTempPath = (string) ($staleInstallerTempDir['path'] ?? ''); ?>
                                <?php if ($staleInstallerTempPath === '') { continue; } ?>
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            data-cb-select-item="stale-installer-temp"
                                            name="stale_installer_temp_paths[]"
                                            value="<?php echo htmlspecialchars($staleInstallerTempPath, ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_SELECT_ONE', $staleInstallerTempPath), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                    </td>
                                    <td><?php echo $staleInstallerTempRowNumber++; ?></td>
                                    <td><code><?php echo htmlspecialchars($staleInstallerTempPath, ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td><?php echo htmlspecialchars((string) ($staleInstallerTempDir['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            data-cb-audit-ajax-task="about.deleteStaleInstallerTemp"
                                            data-cb-audit-ajax-field="stale_installer_temp_path"
                                            data-cb-audit-ajax-value="<?php echo htmlspecialchars($staleInstallerTempPath, ENT_QUOTES, 'UTF-8'); ?>"
                                            title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETE_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETE'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                        ><span class="fa-solid fa-trash" aria-hidden="true"></span></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('table_encoding'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 8;">
                <h4 class="h6 mt-3<?php echo $hasTableEncodingIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('table_encoding', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE_ENCODING_ISSUES') . ' (' . $tableEncodingIssueCount . ')', $hasTableEncodingIssues); ?></h4>
            <?php if ($hasTableEncodingIssues || $hasColumnEncodingIssues || $hasMixedCollationIssues) : ?>
                <?php echo LayoutHelper::render('about.audit_repair_button', [
                    'issue' => 'table_encoding',
                    'description' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_TABLE_ENCODING_DESC'),
                ]); ?>
            <?php endif; ?>
            <?php if (empty($tableEncodingIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_TABLE_ENCODING_ISSUES'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table id="cb-audit-table-stats-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLLATION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_EXPECTED'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $tableEncodingIssueRowNumber = 1; ?>
                        <?php foreach ($tableEncodingIssues as $tableIssue) : ?>
                            <tr>
                                <td><?php echo $tableEncodingIssueRowNumber++; ?></td>
                                <td><?php echo htmlspecialchars((string) ($tableIssue['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) (($tableIssue['collation'] ?? '') !== '' ? $tableIssue['collation'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($tableIssue['expected'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('column_encoding'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 10;">
                <h4 class="h6 mt-3<?php echo $hasColumnEncodingIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('column_encoding', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN_ENCODING_ISSUES') . ' (' . $columnEncodingIssueCount . ')', $hasColumnEncodingIssues); ?></h4>
            <?php if (empty($columnEncodingIssuesDisplayed)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_COLUMN_ENCODING_ISSUES'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table id="cb-audit-column-encoding-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CHARSET'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLLATION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_EXPECTED'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $columnEncodingIssueRowNumber = 1; ?>
                        <?php foreach ($columnEncodingIssuesDisplayed as $columnIssue) : ?>
                            <tr>
                                <td><?php echo $columnEncodingIssueRowNumber++; ?></td>
                                <td><?php echo htmlspecialchars((string) ($columnIssue['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($columnIssue['column'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) (($columnIssue['charset'] ?? '') !== '' ? $columnIssue['charset'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) (($columnIssue['collation'] ?? '') !== '' ? $columnIssue['collation'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(
                                    (string) (
                                        (($columnIssue['expected_charset'] ?? '') !== '' ? $columnIssue['expected_charset'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'))
                                        . ' / '
                                        . (($columnIssue['expected_collation'] ?? '') !== '' ? $columnIssue['expected_collation'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'))
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($columnEncodingIssueHiddenCount > 0) : ?>
                    <p class="text-muted small mb-0">
                        <?php echo Text::plural('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TRUNCATED', $columnEncodingIssueHiddenCount); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('mixed_collations'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 11;">
                <h4 class="h6 mt-3<?php echo $hasMixedCollationIssues ? ' text-warning-emphasis' : ''; ?>"><?php echo $renderNumberedAuditTitle('mixed_collations', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MIXED_COLLATIONS') . ' (' . $mixedCollationIssueCount . ')', $hasMixedCollationIssues); ?></h4>
            <p class="text-muted small mb-2">
                <?php echo Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_EXPECTED_TARGET', $encodingTargetCharset, $encodingTargetCollation); ?>
            </p>
            <?php if ($encodingTargetCollation !== 'utf8mb4_0900_ai_ci') : ?>
                <div class="alert alert-warning py-2 mb-2">
                    <?php echo Text::sprintf(
                        'COM_CONTENTBUILDERNG_ABOUT_AUDIT_ENCODING_TARGET_FALLBACK',
                        'utf8mb4_0900_ai_ci',
                        $encodingTargetCollation
                    ); ?>
                </div>
            <?php endif; ?>
            <?php if (count($mixedTableCollations) <= 1) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_MIXED_COLLATIONS'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table id="cb-audit-mixed-collations-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLLATION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COUNT'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $mixedCollationRowNumber = 1; ?>
                        <?php foreach ($mixedTableCollations as $collationStat) : ?>
                            <tr>
                                <td><?php echo $mixedCollationRowNumber++; ?></td>
                                <td><?php echo htmlspecialchars((string) ($collationStat['collation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) ($collationStat['count'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars(implode(', ', (array) ($collationStat['tables'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('cb_table_stats'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 37;">
                <h4 class="h6 mt-3"><?php echo $renderNumberedAuditTitle('cb_table_stats', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_TABLE_STATS'), false); ?></h4>
            <?php if (empty($cbTableDetails)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_CB_TABLE_STATS'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table id="cb-audit-cb-table-stats-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COUNT'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SIZE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ENGINE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLLATION'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $cbTableDetailRowNumber = 1; ?>
                        <?php foreach ($cbTableDetails as $cbTableDetail) : ?>
                            <tr>
                                <td><?php echo $cbTableDetailRowNumber++; ?></td>
                                <td><?php echo htmlspecialchars((string) ($cbTableDetail['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo number_format((int) ($cbTableDetail['rows'] ?? 0), 0, '.', ' '); ?></td>
                                <td><?php echo $formatBytes((int) ($cbTableDetail['size_bytes'] ?? 0)); ?></td>
                                <td><?php echo htmlspecialchars((string) (($cbTableDetail['engine'] ?? '') !== '' ? $cbTableDetail['engine'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) (($cbTableDetail['collation'] ?? '') !== '' ? $cbTableDetail['collation'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('packed_data'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 9;">
            <?php if ($hasPackedDataAuditRows) : ?>
                <h4 class="h6 mt-3"><?php echo $renderNumberedAuditTitle('packed_data', Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_DETAILS_TITLE'), $hasPackedDataIssues); ?></h4>
                <?php echo LayoutHelper::render('about.audit_repair_button', [
                    'issue' => 'packed_data',
                    'description' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_PACKED_DATA_DESC'),
                ]); ?>
                <?php foreach ($packedDataAuditTables as $packedDataAuditTable) : ?>
                    <?php
                    if (!is_array($packedDataAuditTable)) {
                        continue;
                    }

                    $packedDataAuditTableName = trim((string) ($packedDataAuditTable['table'] ?? ''));
                    $packedDataAuditTableColumn = trim((string) ($packedDataAuditTable['column'] ?? ''));
                    $packedDataSource = match ($packedDataAuditTableName) {
                        '#__contentbuilderng_elements' => 'elements',
                        '#__contentbuilderng_forms' => 'forms',
                        default => '',
                    };
                    $packedDataAuditRows = array_values((array) ($packedDataAuditTable['rows'] ?? []));

                    if ($packedDataAuditRows === []) {
                        continue;
                    }
                    ?>
                    <div class="mb-4">
                        <h5 class="h6 mb-2">
                            <?php echo htmlspecialchars($packedDataAuditTableName !== '' ? $packedDataAuditTableName : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'), ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($packedDataAuditTableColumn !== '') : ?>
                                <small class="text-muted">/ <?php echo htmlspecialchars($packedDataAuditTableColumn, ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </h5>
                        <div class="table-responsive">
                            <table id="<?php echo htmlspecialchars($packedDataSource !== '' ? 'cb-packed-data-audit-table-' . $packedDataSource : 'cb-packed-data-audit-table', ENT_QUOTES, 'UTF-8'); ?>" class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RECORD'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_FORM'); ?></th>
                                    <th scope="col" class="text-end"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_STATUS'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_FORMAT'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ERRORS'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RAW'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($packedDataAuditRows as $packedDataAuditRow) : ?>
                                    <?php
                                    if (!is_array($packedDataAuditRow)) {
                                        continue;
                                    }

                                    $recordId = (int) ($packedDataAuditRow['record_id'] ?? 0);
                                    $recordLabel = trim((string) ($packedDataAuditRow['record_label'] ?? ''));
                                    $formId = (int) ($packedDataAuditRow['form_id'] ?? 0);
                                    $formLabel = trim((string) ($packedDataAuditRow['form_label'] ?? ''));
                                    $payloadType = (string) ($packedDataAuditRow['payload_type'] ?? '');
                                    $rowStatus = (string) ($packedDataAuditRow['status'] ?? '');
                                    $rowError = trim((string) ($packedDataAuditRow['error'] ?? ''));
                                    $rowStatusLabelKey = match ($rowStatus) {
                                        'migrated' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ROW_STATUS_MIGRATED',
                                        'unchanged' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ROW_STATUS_UNCHANGED',
                                        'error' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ROW_STATUS_ERROR',
                                        default => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ROW_STATUS_UNCHANGED',
                                    };
                                    $payloadTypeLabelKey = match ($payloadType) {
                                        'json' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_FORMAT_JSON',
                                        'legacy_php' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_FORMAT_LEGACY_PHP',
                                        default => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_FORMAT_INVALID',
                                    };
                                    $formLink = $formId > 0
                                        ? Route::_('index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . $formId, false)
                                        : '';
                                    $recordDisplay = '#' . $recordId;
                                    if ($recordLabel !== '') {
                                        $recordDisplay .= ' - ' . $recordLabel;
                                    }
                                    $formDisplay = '#' . $formId;
                                    if ($formLabel !== '') {
                                        $formDisplay .= ' - ' . $formLabel;
                                    }
                                    $packedPayloadLink = '';
                                    if ($packedDataSource !== '' && $recordId > 0) {
                                        $packedPayloadLink = Route::_(
                                            'index.php?option=com_contentbuilderng&view=about&layout=packedpayload&packed_source=' . rawurlencode($packedDataSource) . '&id=' . $recordId,
                                            false
                                        );
                                    }
                                    ?>
                                    <tr class="<?php echo $rowStatus === 'error' ? 'table-warning' : ''; ?>">
                                        <td><?php echo htmlspecialchars($recordDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($formLink !== '') : ?>
                                                <a href="<?php echo htmlspecialchars($formLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($formDisplay, ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php else : ?>
                                                <?php echo htmlspecialchars($formDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo Text::_($rowStatusLabelKey); ?></td>
                                        <td><?php echo Text::_($payloadTypeLabelKey); ?></td>
                                        <td><?php echo htmlspecialchars($rowError !== '' ? $rowError : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($packedPayloadLink !== '') : ?>
                                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($packedPayloadLink, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RAW'); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('cb_ng_tables_missing'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 40;">
            <?php if (!empty($cbMissingNgTables)) : ?>
                <h4 class="h6 mt-3"><?php echo $renderNumberedAuditTitle('cb_ng_tables_missing', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES_MISSING'), !empty($cbMissingNgTables)); ?></h4>
                <ol class="mb-0 ps-3">
                    <?php foreach ($cbMissingNgTables as $missingNgTable) : ?>
                        <li><?php echo htmlspecialchars((string) $missingNgTable, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('errors'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 44;">
            <?php if (!empty($auditWarnings)) : ?>
                <h4 class="h6 mt-3"><?php echo $renderNumberedAuditTitle('errors', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ERRORS'), !empty($auditWarnings)); ?></h4>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($auditWarnings as $auditWarning) : ?>
                        <div class="alert alert-warning cb-audit-warning-alert mb-0">
                            <div class="cb-audit-warning-content">
                            <?php
                            $auditWarningSummary = htmlspecialchars((string) ($auditWarning['summary'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $auditWarningStorageId = (int) ($auditWarning['storage_id'] ?? 0);
                            if ($auditWarningStorageId > 0 && !empty($auditWarning['link_url'])) {
                                $auditWarningStoragePattern = '/\b[^\s]+\s+#' . $auditWarningStorageId . '\b/u';
                                $auditWarningSummary = preg_replace_callback(
                                    $auditWarningStoragePattern,
                                    static fn(array $matches): string => '<a href="'
                                        . htmlspecialchars((string) $auditWarning['link_url'], ENT_QUOTES, 'UTF-8')
                                        . '" target="_blank" rel="noopener noreferrer">'
                                        . $matches[0]
                                        . '</a>',
                                    $auditWarningSummary,
                                    1
                                ) ?? $auditWarningSummary;
                            }
                            ?>
                            <span class="cb-audit-warning-title"><?php echo $auditWarningSummary; ?></span>
                            <?php if (!empty($auditWarning['detail']) || !empty($auditWarning['link_url'])) : ?>
                                <span class="cb-audit-warning-help">
                                    <?php if (!empty($auditWarning['detail'])) : ?>
                                        <?php echo htmlspecialchars((string) $auditWarning['detail'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($auditWarning['repair_available']) && $auditWarningStorageId > 0) : ?>
                                        <?php if (!empty($auditWarning['detail'])) : ?><br><?php endif; ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-warning ms-2"
                                                data-cb-audit-ajax-task="about.repairMissingStorageTable"
                                                data-cb-audit-ajax-field="repair_storage_id"
                                                data-cb-audit-ajax-value="<?php echo $auditWarningStorageId; ?>"
                                                title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIR_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
                                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIR'), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                            ><span class="fa-solid fa-wrench me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIR'); ?></button>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
            </div>
        <?php endif; ?>
    </div>
</div>
