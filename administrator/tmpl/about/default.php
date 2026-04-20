<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/



// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');


use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

$versionValue = (string) ($this->componentVersion ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$creationDateValue = (string) ($this->componentCreationDate ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$buildTimestampValue = trim((string) ($this->componentBuildTimestamp ?? ''));
$authorValue = (string) ($this->componentAuthor ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$copyrightValue = (string) ($this->componentCopyright ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$licenseValue = trim((string) $this->componentLicense);
$buildTypeValue = strtolower(trim((string) ($this->componentBuildType ?? '')));
$isProductionBuild = $buildTypeValue === 'production';
$buildTypeLabel = $isProductionBuild
    ? Text::_('COM_CONTENTBUILDERNG_PRODUCTION_BUILD_LABEL')
    : Text::_('COM_CONTENTBUILDERNG_DEV_BUILD_LABEL');
$genericLicenseValues = ['gpl', 'gnu/gpl', 'gnu/gpl v2 or later'];
if ($licenseValue === '' || in_array(strtolower($licenseValue), $genericLicenseValues, true)) {
    $licenseValue = Text::_('COM_CONTENTBUILDERNG_LICENSE_FALLBACK');
}
$formatBuildTimestamp = static function (string $timestamp): string {
    if (trim($timestamp) === '') {
        return '';
    }

    try {
        $timezoneName = (string) Factory::getApplication()->get('offset', 'UTC');
        $timezone = new \DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC');
        $date = new \DateTimeImmutable($timestamp, new \DateTimeZone('UTC'));

        return $date->setTimezone($timezone)->format('Y-m-d H:i:s T');
    } catch (\Throwable) {
        return '';
    }
};
$buildTimestampDisplay = $isProductionBuild ? $formatBuildTimestamp($buildTimestampValue) : '';
$buildTypeDisplay = $buildTimestampDisplay !== ''
    ? $buildTypeLabel . ' · ' . $buildTimestampDisplay
    : $buildTypeLabel;
$licenseUrl = 'https://www.gnu.org/licenses/gpl-2.0.html';
$tooltipAudit = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_AUDIT');
$tooltipDbRepair = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_DB_REPAIR');
$tooltipShowLog = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_SHOW_LOG');
$tooltipLinkVcmb = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_LINK_VCMB');
$tooltipLinkGithub = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_LINK_GITHUB');
$tooltipLinkLicense = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_LINK_LICENSE');
$labelAuditButton = Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT');
$labelDbRepairButton = Text::_('COM_CONTENTBUILDERNG_ABOUT_MIGRATE_PACKED_DATA');
$labelShowLogButton = Text::_('COM_CONTENTBUILDERNG_ABOUT_LAST_LOG');
$auditRowNumberLabel = Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ROW');
$auditReport = is_array($this->auditReport ?? null) ? $this->auditReport : [];
$auditSummary = (array) ($auditReport['summary'] ?? []);
$duplicateIndexes = (array) ($auditReport['duplicate_indexes'] ?? []);
$historicalTables = (array) ($auditReport['historical_tables'] ?? []);
$historicalMenuEntries = (array) ($auditReport['historical_menu_entries'] ?? []);
$tableEncodingIssues = (array) ($auditReport['table_encoding_issues'] ?? []);
$packedDataAudit = is_array($auditReport['packed_data_audit'] ?? null) ? $auditReport['packed_data_audit'] : [];
$packedDataAuditTables = array_values((array) ($packedDataAudit['tables'] ?? []));
$hasPackedDataAuditRows = false;
foreach ($packedDataAuditTables as $packedDataAuditTable) {
    if (is_array($packedDataAuditTable) && !empty((array) ($packedDataAuditTable['rows'] ?? []))) {
        $hasPackedDataAuditRows = true;
        break;
    }
}
$columnEncodingIssues = (array) ($auditReport['column_encoding_issues'] ?? []);
$mixedTableCollations = (array) ($auditReport['mixed_table_collations'] ?? []);
$missingAuditColumns = (array) ($auditReport['missing_audit_columns'] ?? []);
$missingFormAuditColumns = (array) ($auditReport['missing_form_audit_columns'] ?? []);
$pluginExtensionDuplicates = (array) ($auditReport['plugin_extension_duplicates'] ?? []);
$bfFieldSyncIssues = (array) ($auditReport['bf_view_field_sync_issues'] ?? []);
$menuViewIssues = (array) ($auditReport['menu_view_issues'] ?? []);
$frontendPermissionIssues = (array) ($auditReport['frontend_permission_issues'] ?? []);
$elementReferenceIssues = (array) ($auditReport['element_reference_issues'] ?? []);
$invalidDatetimeSortIssues = (array) ($auditReport['invalid_datetime_sort_issues'] ?? []);
$generatedArticleCategoryIssues = (array) ($auditReport['generated_article_category_issues'] ?? []);
$encodingTargetCharset = (string) ($auditSummary['encoding_target_charset'] ?? 'utf8mb4');
$encodingTargetCollation = (string) ($auditSummary['encoding_target_collation'] ?? 'utf8mb4_0900_ai_ci');
$missingAuditColumnsTotal = (int) ($auditSummary['missing_audit_columns_total'] ?? 0);
$missingAuditColumnsTableCount = (int) ($auditSummary['missing_audit_column_tables'] ?? count($missingAuditColumns));
$missingFormAuditColumnsTotal = (int) ($auditSummary['missing_form_audit_columns_total'] ?? 0);
$missingFormAuditColumnsTableCount = (int) ($auditSummary['missing_form_audit_column_tables'] ?? count($missingFormAuditColumns));
$pluginDuplicateGroups = (int) ($auditSummary['plugin_duplicate_groups'] ?? count($pluginExtensionDuplicates));
$pluginDuplicateRowsToRemove = (int) ($auditSummary['plugin_duplicate_rows_to_remove'] ?? 0);
if ($pluginDuplicateRowsToRemove === 0 && $pluginExtensionDuplicates !== []) {
    foreach ($pluginExtensionDuplicates as $pluginExtensionDuplicate) {
        if (!is_array($pluginExtensionDuplicate)) {
            continue;
        }

        $pluginDuplicateRowsToRemove += count((array) ($pluginExtensionDuplicate['duplicate_ids'] ?? []));
    }
}
if ($missingAuditColumnsTotal === 0 && $missingAuditColumns !== []) {
    foreach ($missingAuditColumns as $missingAuditColumn) {
        if (!is_array($missingAuditColumn)) {
            continue;
        }

        $missingAuditColumnsTotal += count((array) ($missingAuditColumn['missing'] ?? []));
    }
}
$bfFieldSyncViews = (int) ($auditSummary['bf_view_field_sync_views'] ?? count($bfFieldSyncIssues));
$bfFieldSyncMissingTotal = (int) ($auditSummary['bf_view_field_sync_missing_in_cb'] ?? 0);
$bfFieldSyncOrphanTotal = (int) ($auditSummary['bf_view_field_sync_orphan_in_cb'] ?? 0);
$historicalMenuEntriesCount = (int) ($auditSummary['historical_menu_entries'] ?? count($historicalMenuEntries));

if (($bfFieldSyncMissingTotal === 0 || $bfFieldSyncOrphanTotal === 0) && $bfFieldSyncIssues !== []) {
    $fallbackMissingTotal = 0;
    $fallbackOrphanTotal = 0;

    foreach ($bfFieldSyncIssues as $bfFieldSyncIssue) {
        if (!is_array($bfFieldSyncIssue)) {
            continue;
        }

        $fallbackMissingTotal += (int) ($bfFieldSyncIssue['missing_count'] ?? 0);
        $fallbackOrphanTotal += (int) ($bfFieldSyncIssue['orphan_count'] ?? 0);
    }

    if ($bfFieldSyncMissingTotal === 0) {
        $bfFieldSyncMissingTotal = $fallbackMissingTotal;
    }

    if ($bfFieldSyncOrphanTotal === 0) {
        $bfFieldSyncOrphanTotal = $fallbackOrphanTotal;
    }
}
$cbTableStats = is_array($auditReport['cb_tables'] ?? null) ? $auditReport['cb_tables'] : [];
$cbTableSummary = (array) ($cbTableStats['summary'] ?? []);
$cbTableDetails = (array) ($cbTableStats['tables'] ?? []);
$cbMissingNgTables = (array) ($cbTableStats['missing_ng_tables'] ?? []);
$auditErrors = (array) ($auditReport['errors'] ?? []);
$auditGeneratedAtDisplay = (string) ($auditReport['generated_at'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));

if (!empty($auditReport['generated_at'])) {
    try {
        $userTz = Factory::getApplication()->getIdentity()->getParam('timezone', '');
        $configTz = Factory::getApplication()->get('offset', 'UTC');
        $displayTz = new \DateTimeZone($userTz !== '' ? $userTz : $configTz);
        $auditGeneratedAt = new \DateTimeImmutable((string) $auditReport['generated_at'], new \DateTimeZone('UTC'));
        $auditGeneratedAtDisplay = $auditGeneratedAt->setTimezone($displayTz)->format('Y-m-d H:i:s');
    } catch (\Throwable) {
    }
}
$auditWarnings = [];
foreach ($auditErrors as $auditError) {
    $warningText = trim((string) $auditError);

    if ($warningText === '') {
        continue;
    }

    $warningDetail = '';
    $warningLinkUrl = '';
    $warningLinkLabel = '';
    $storageTableNotFoundMatch = [];
    if (preg_match('/^Storage\s+#(\d+)\s+\((.*?)\)\s*:?\s*table not found\.?(?:\s+.*)?$/i', $warningText, $storageTableNotFoundMatch) === 1) {
        $storageIdLabel = (int) ($storageTableNotFoundMatch[1] ?? 0);
        $storageNameLabel = trim((string) ($storageTableNotFoundMatch[2] ?? ''));
        $warningText = Text::sprintf(
            'COM_CONTENTBUILDERNG_ABOUT_AUDIT_WARNING_STORAGE_TABLE_NOT_FOUND',
            $storageIdLabel,
            $storageNameLabel
        );
        $warningDetail = Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_WARNING_STORAGE_TABLE_NOT_FOUND_DETAIL');
        if ($storageIdLabel > 0) {
            $warningLinkUrl = Route::_(
                'index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . $storageIdLabel,
                false
            );
            $warningLinkLabel = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_AUDIT_WARNING_STORAGE_TABLE_NOT_FOUND_LINK',
                $storageIdLabel
            );
        }
    }

    $auditWarnings[] = [
        'summary' => $warningText,
        'detail' => $warningDetail,
        'link_url' => $warningLinkUrl,
        'link_label' => $warningLinkLabel,
    ];
}
$hasAuditReport = $auditReport !== [];
$logReport = is_array($this->logReport ?? null) ? $this->logReport : [];
$hasLogReport = $logReport !== [];
$logFileName = (string) ($logReport['file'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$logSize = (int) ($logReport['size'] ?? 0);
$logLoadedAt = (string) ($logReport['loaded_at'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$logContent = (string) ($logReport['content'] ?? '');
$logDisplayContent = $logContent;
if ($logDisplayContent !== '') {
    $logLines = preg_split('/\r\n|\r|\n/', $logDisplayContent);
    if (is_array($logLines) && $logLines !== []) {
        $logDisplayContent = implode(PHP_EOL, array_reverse($logLines));
    }
}
$logTruncated = (int) ($logReport['truncated'] ?? 0) === 1;
$logTailBytes = (int) ($logReport['tail_bytes'] ?? 0);
$repairWorkflow = is_array($this->repairWorkflow ?? null) ? $this->repairWorkflow : [];
$repairWorkflowSteps = array_values((array) ($repairWorkflow['steps'] ?? []));
$repairWorkflowCurrentIndex = (int) ($repairWorkflow['current_step'] ?? 0);
$repairWorkflowCurrentStep = $repairWorkflowSteps[$repairWorkflowCurrentIndex] ?? null;
$repairWorkflowCurrentStepId = is_array($repairWorkflowCurrentStep) ? (string) ($repairWorkflowCurrentStep['id'] ?? '') : '';
$repairWorkflowCurrentStatus = is_array($repairWorkflowCurrentStep) ? (string) ($repairWorkflowCurrentStep['status'] ?? 'pending') : 'pending';
$repairWorkflowCurrentResult = is_array($repairWorkflowCurrentStep) ? (array) ($repairWorkflowCurrentStep['result'] ?? []) : [];
$repairWorkflowRequested = isset($_GET['repair_workflow']) && (int) $_GET['repair_workflow'] === 1;
$repairWorkflowIsActive = $repairWorkflowRequested && !empty($repairWorkflow) && (bool) ($repairWorkflow['active'] ?? false);
$repairWorkflowIsCompleted = (bool) ($repairWorkflow['completed'] ?? false);
$repairWorkflowHasNext = $repairWorkflowIsActive && $repairWorkflowCurrentIndex < count($repairWorkflowSteps) - 1;
$repairWorkflowDisplayCurrentIndex = $repairWorkflowCurrentIndex;
if ($repairWorkflowCurrentStatus !== 'pending' && $repairWorkflowHasNext) {
    $repairWorkflowDisplayCurrentIndex++;
}
$repairWorkflowShowCurrentPanel = true;
if (
    $repairWorkflowIsCompleted
    && is_array($repairWorkflowCurrentStep)
    && in_array($repairWorkflowCurrentStatus, ['done', 'skipped'], true)
) {
    $currentResultLines = (array) ($repairWorkflowCurrentResult['lines'] ?? []);
    $currentResultSummary = trim((string) ($repairWorkflowCurrentResult['summary'] ?? ''));

    if ($currentResultLines === [] || $currentResultSummary !== '') {
        $repairWorkflowShowCurrentPanel = false;
    }
}
$repairWorkflowStepLabels = [
    'duplicate_indexes' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'),
    'historical_tables' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_TABLES'),
    'historical_menu_entries' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_MENU_ENTRIES'),
    'table_encoding' => Text::sprintf(
        'COM_CONTENTBUILDERNG_DB_REPAIR_STEP_TABLE_ENCODING_TITLE_WITH_TARGET',
        $encodingTargetCollation
    )
        . ' / '
        . Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN_ENCODING_ISSUES')
        . ' / '
        . Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MIXED_COLLATIONS'),
    'packed_data' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_PACKED_DATA_TITLE'),
    'audit_columns' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS'),
    'form_audit_columns' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_FORM_AUDIT_COLUMNS'),
    'plugin_duplicates' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATES'),
    'bf_field_sync' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC'),
    'menu_view_consistency' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_VIEW_CONSISTENCY'),
    'frontend_permission_consistency' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FRONTEND_PERMISSION_CONSISTENCY'),
    'element_reference_consistency' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_CONSISTENCY'),
    'generated_article_categories' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES'),
];
$repairWorkflowStepDescriptions = [
    'duplicate_indexes' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'),
    'historical_tables' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_TABLES'),
    'table_encoding' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_TABLE_ENCODING_DESC'),
    'packed_data' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_PACKED_DATA_DESC'),
    'audit_columns' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_AUDIT_COLUMNS_DESC'),
    'form_audit_columns' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_FORM_AUDIT_COLUMNS_DESC'),
    'plugin_duplicates' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_PLUGIN_DUPLICATES_DESC'),
    'historical_menu_entries' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_HISTORICAL_MENU_DESC'),
    'bf_field_sync' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC'),
    'menu_view_consistency' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_VIEW_CONSISTENCY'),
    'frontend_permission_consistency' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FRONTEND_PERMISSION_CONSISTENCY'),
    'element_reference_consistency' => Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_CONSISTENCY'),
    'generated_article_categories' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_STEP_GENERATED_ARTICLE_CATEGORIES_DESC'),
];
$phpLibrariesCount = count((array) $this->phpLibraries);
$javascriptLibrariesCount = count((array) $this->javascriptLibraries);
$columnEncodingIssueLimit = 200;
$tableEncodingIssueCount = count($tableEncodingIssues);
$columnEncodingIssueCount = count($columnEncodingIssues);
$mixedCollationIssueCount = max(0, count($mixedTableCollations) - 1);
$columnEncodingIssuesDisplayed = array_slice($columnEncodingIssues, 0, $columnEncodingIssueLimit);
$columnEncodingIssueHiddenCount = max(0, count($columnEncodingIssues) - count($columnEncodingIssuesDisplayed));
$hasAuditIssues = (int) ($auditSummary['issues_total'] ?? 0) > 0;
$hasDuplicateIndexIssues = !empty($duplicateIndexes);
$hasDuplicateIndexDropIssues = (int) ($auditSummary['duplicate_indexes_to_drop'] ?? 0) > 0;
$hasLegacyTableIssues = !empty($historicalTables);
$hasLegacyMenuIssues = $historicalMenuEntriesCount > 0;
$hasTableEncodingIssues = $tableEncodingIssueCount > 0;
$hasPackedDataIssues = (int) ($auditSummary['packed_data_candidates'] ?? 0) > 0;
$hasColumnEncodingIssues = $columnEncodingIssueCount > 0;
$hasMixedCollationIssues = $mixedCollationIssueCount > 0;
$hasMissingAuditColumnIssues = $missingAuditColumnsTableCount > 0 || $missingAuditColumnsTotal > 0;
$hasMissingFormAuditColumnIssues = $missingFormAuditColumnsTableCount > 0 || $missingFormAuditColumnsTotal > 0;
$hasPluginDuplicateIssues = $pluginDuplicateGroups > 0 || $pluginDuplicateRowsToRemove > 0;
$hasBfFieldSyncIssues = $bfFieldSyncViews > 0 || $bfFieldSyncMissingTotal > 0 || $bfFieldSyncOrphanTotal > 0;
$hasMenuViewIssues = (int) ($auditSummary['menu_view_issues'] ?? count($menuViewIssues)) > 0;
$hasFrontendPermissionIssues = (int) ($auditSummary['frontend_permission_issues'] ?? count($frontendPermissionIssues)) > 0;
$hasElementReferenceIssues = (int) ($auditSummary['element_reference_issues'] ?? count($elementReferenceIssues)) > 0;
$generatedArticleCategoryIssueCount = (int) ($auditSummary['generated_article_category_issues'] ?? count($generatedArticleCategoryIssues));
$generatedArticleCategoryRowCount = (int) ($auditSummary['generated_article_category_rows'] ?? 0);
if ($generatedArticleCategoryRowCount === 0 && $generatedArticleCategoryIssues !== []) {
    foreach ($generatedArticleCategoryIssues as $generatedArticleCategoryIssue) {
        if (!is_array($generatedArticleCategoryIssue)) {
            continue;
        }

        $generatedArticleCategoryRowCount += (int) ($generatedArticleCategoryIssue['invalid_article_count'] ?? 0);
    }
}
$hasGeneratedArticleCategoryIssues = $generatedArticleCategoryIssueCount > 0 || $generatedArticleCategoryRowCount > 0;
$invalidDatetimeSortIssueCount = (int) ($auditSummary['invalid_datetime_sort_issues'] ?? count($invalidDatetimeSortIssues));
$invalidDatetimeSortRowCount = (int) ($auditSummary['invalid_datetime_sort_rows'] ?? 0);
if ($invalidDatetimeSortRowCount === 0 && $invalidDatetimeSortIssues !== []) {
    foreach ($invalidDatetimeSortIssues as $invalidDatetimeSortIssue) {
        if (!is_array($invalidDatetimeSortIssue)) {
            continue;
        }

        $invalidDatetimeSortRowCount += (int) ($invalidDatetimeSortIssue['invalid_count'] ?? 0);
    }
}
$hasInvalidDatetimeSortIssues = $invalidDatetimeSortIssueCount > 0 || $invalidDatetimeSortRowCount > 0;
$formatBytes = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    if ($unitIndex === 0) {
        return number_format((int) round($value), 0, '.', ' ') . ' ' . $units[$unitIndex];
    }

    return number_format($value, 2, '.', ' ') . ' ' . $units[$unitIndex];
};
$formatAuditIssueList = static function (array $values, int $limit = 8): string {
    $clean = [];

    foreach ($values as $value) {
        $label = trim((string) $value);
        if ($label !== '') {
            $clean[] = $label;
        }
    }

    $clean = array_values(array_unique($clean));
    if ($clean === []) {
        return '0';
    }

    $visible = array_slice($clean, 0, $limit);
    $remaining = max(0, count($clean) - count($visible));
    $rendered = implode(', ', $visible);

    if ($remaining > 0) {
        $rendered .= ' +' . $remaining;
    }

    return $rendered;
};
$renderAuditTitle = static function (string $label, bool $hasIssues): string {
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    if ($hasIssues) {
        return '<span class="cb-audit-section-title"><span class="cb-audit-warning-icon icon-warning" aria-hidden="true"></span><span>' . $safeLabel . '</span></span>';
    }

    return $safeLabel;
};
$auditSectionNumbers = [
    'issues_total' => 1,
    'duplicate_indexes' => 2,
    'duplicate_indexes_to_drop' => 3,
    'historical_tables' => 4,
    'historical_menu_entries' => 5,
    'table_encoding' => 6,
    'packed_data' => 7,
    'column_encoding' => 8,
    'mixed_collations' => 9,
    'audit_columns' => 10,
    'audit_columns_total' => 11,
    'form_audit_columns' => 12,
    'form_audit_columns_total' => 13,
    'invalid_datetime_sort' => 14,
    'invalid_datetime_sort_rows' => 15,
    'plugin_duplicates' => 16,
    'plugin_duplicate_rows' => 17,
    'bf_field_sync' => 18,
    'bf_field_sync_missing' => 19,
    'bf_field_sync_orphan' => 20,
    'menu_view_consistency' => 21,
    'frontend_permission_consistency' => 22,
    'element_reference_consistency' => 23,
    'generated_article_categories' => 24,
    'generated_article_category_rows' => 25,
    'cb_table_stats' => 26,
    'cb_tables_total' => 27,
    'cb_ng_tables_expected' => 28,
    'cb_ng_tables_missing' => 29,
    'cb_storage_tables' => 30,
    'cb_estimated_rows' => 31,
    'cb_estimated_size' => 32,
];
$getAuditSectionNumber = static function (string $sectionId) use ($auditSectionNumbers): int {
    return (int) ($auditSectionNumbers[$sectionId] ?? 0);
};
$getAuditSectionHeadingId = static function (string $sectionId): string {
    return 'cb-audit-section-' . $sectionId;
};
$renderAuditSummaryLink = static function (string $sectionId, string $label) use ($getAuditSectionHeadingId): string {
    $anchorId = htmlspecialchars($getAuditSectionHeadingId($sectionId), ENT_QUOTES, 'UTF-8');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    return '<a class="text-reset text-decoration-none" href="#' . $anchorId . '">' . $safeLabel . '</a>';
};
$renderNumberedAuditTitle = static function (string $sectionId, string $label, bool $hasIssues) use ($renderAuditTitle, $getAuditSectionNumber): string {
    $number = $getAuditSectionNumber($sectionId);

    return $renderAuditTitle(($number > 0 ? $number . '. ' : '') . $label, $hasIssues);
};

?>
<style>
    .cb-about-intro {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .cb-about-intro-media {
        flex: 0 0 auto;
    }
    .cb-about-intro-content {
        flex: 1 1 auto;
        min-width: 0;
    }
    .cb-about-intro-content p {
        margin: 0;
        padding: 0;
        text-align: left;
    }
    .cb-about-intro-links {
        margin-top: .55rem;
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
    }
    .cb-about-intro-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        font-size: .78rem;
        font-weight: 700;
        line-height: 1;
        letter-spacing: .01em;
        text-decoration: none;
        border-radius: 999px;
        padding: .45rem .8rem;
        transition: transform .16s ease, box-shadow .16s ease, opacity .16s ease;
    }
    .cb-about-intro-link:hover,
    .cb-about-intro-link:focus {
        transform: translateY(-1px);
        opacity: .95;
        text-decoration: none;
    }
    .cb-about-intro-link--vcmb {
        color: var(--bs-primary-text-emphasis);
        background: var(--bs-primary-bg-subtle);
        border: 1px solid var(--bs-primary-border-subtle);
    }
    .cb-about-intro-link--github {
        color: var(--bs-secondary-text-emphasis);
        background: var(--bs-secondary-bg-subtle);
        border: 1px solid var(--bs-secondary-border-subtle);
        box-shadow: 0 .35rem .9rem rgba(15, 23, 42, .16);
    }
    .cb-about-intro-link--license {
        color: var(--bs-warning-text-emphasis);
        background: var(--bs-warning-bg-subtle);
        border: 1px solid var(--bs-warning-border-subtle);
        box-shadow: 0 .25rem .75rem rgba(191, 144, 0, .16);
    }
    @media (max-width: 767.98px) {
        .cb-about-intro {
            flex-wrap: wrap;
        }
    }
    .cb-about-version-card {
        background:
            radial-gradient(circle at 100% 0%, rgba(13, 110, 253, .10), transparent 48%),
            radial-gradient(circle at 0% 100%, rgba(25, 135, 84, .09), transparent 44%),
            linear-gradient(140deg, var(--bs-tertiary-bg) 0%, var(--bs-body-bg) 72%);
        border: 1px solid var(--bs-border-color);
        border-radius: 1rem;
        overflow: hidden;
    }
    .cb-about-version-header {
        border-bottom: 1px dashed var(--bs-border-color);
        padding-bottom: .75rem;
    }
    .cb-about-version-title {
        color: var(--bs-emphasis-color);
        font-weight: 700;
        letter-spacing: .01em;
    }
    .cb-about-version-badge {
        background-color: var(--bs-secondary-bg);
        color: var(--bs-emphasis-color);
        border-radius: 999px;
        font-size: .72rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        padding: .35rem .65rem;
    }
    .cb-about-version-badge--production {
        background-color: var(--bs-success-bg-subtle, #e7f6ed);
        color: var(--bs-success-text-emphasis, #198754);
    }
    .cb-about-version-badge--dev {
        background-color: var(--bs-warning-bg-subtle, #fff6d6);
        color: var(--bs-warning-text-emphasis, #a87400);
    }
    .cb-about-version-tile {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: .35rem;
        height: 100%;
        border: 1px solid var(--bs-border-color);
        border-radius: .9rem;
        background: linear-gradient(180deg, var(--bs-body-bg) 0%, var(--bs-tertiary-bg) 100%);
        padding: 1.05rem 1.05rem .95rem;
        box-shadow: 0 .5rem 1rem rgba(15, 23, 42, .06);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .cb-about-version-tile::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: .23rem;
        border-radius: .9rem .9rem 0 0;
        background: var(--cb-accent-color, #0d6efd);
    }
    .cb-about-version-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 .65rem 1.25rem rgba(15, 23, 42, .1);
    }
    .cb-about-version-tile--version {
        --cb-accent-color: #0d6efd;
    }
    .cb-about-version-tile--date {
        --cb-accent-color: #198754;
    }
    .cb-about-version-tile--author {
        --cb-accent-color: #fd7e14;
    }
    .cb-about-version-tile--license {
        --cb-accent-color: #d39e00;
    }
    .cb-about-version-icon {
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        background-color: var(--bs-primary-bg-subtle, #e8f1ff);
        color: var(--bs-primary-text-emphasis, #0d6efd);
    }
    .cb-about-version-tile--date .cb-about-version-icon {
        background-color: var(--bs-success-bg-subtle, #e7f6ed);
        color: var(--bs-success-text-emphasis, #198754);
    }
    .cb-about-version-tile--author .cb-about-version-icon {
        background-color: var(--bs-warning-bg-subtle, #fff1e8);
        color: var(--bs-warning-text-emphasis, #fd7e14);
    }
    .cb-about-version-tile--license .cb-about-version-icon {
        background-color: var(--bs-warning-bg-subtle, #fff6d6);
        color: var(--bs-warning-text-emphasis, #a87400);
    }
    .cb-about-version-label {
        margin: .15rem 0 0;
        color: var(--bs-secondary-color);
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .cb-about-version-value {
        margin: 0;
        color: var(--bs-emphasis-color);
        font-size: .98rem;
        font-weight: 700;
        line-height: 1.3;
        word-break: break-word;
    }
    .cb-about-version-link {
        margin-top: .4rem;
        font-size: .76rem;
        font-weight: 700;
    }
    .cb-audit-ok-alert {
        display: flex;
        align-items: center;
        gap: .5rem;
        background-color: var(--bs-success-bg-subtle);
        border-color: var(--bs-success-border-subtle);
        color: var(--bs-success-text-emphasis);
    }
    .cb-audit-ok-alert::before {
        display: none;
        font-weight: 700;
        font-size: .78rem;
        line-height: 1;
        flex: 0 0 auto;
    }
    .cb-audit-warning-alert {
        position: relative;
        padding-left: 2.6rem;
        border-color: var(--bs-warning-border-subtle);
        background-color: var(--bs-warning-bg-subtle);
        color: var(--bs-warning-text-emphasis);
    }
    .cb-audit-warning-alert::before {
        content: "\26A0";
        position: absolute;
        top: .9rem;
        left: 1rem;
        color: #fd7e14;
        font-size: 1rem;
        line-height: 1;
    }
    .cb-audit-warning-alert .cb-audit-warning-title {
        font-weight: 600;
    }
    .cb-audit-warning-alert .cb-audit-warning-help {
        display: block;
        margin-top: .35rem;
        font-size: .88rem;
        line-height: 1.35;
    }
    .cb-audit-warning-alert .cb-audit-warning-link {
        color: inherit;
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    .cb-audit-warning-alert .cb-audit-warning-link:hover,
    .cb-audit-warning-alert .cb-audit-warning-link:focus {
        color: inherit;
        text-decoration-thickness: 2px;
    }
    .cb-config-sections-scroll {
        max-height: 400px;
        min-height: 280px;
        overflow-y: auto;
        border: 1px solid var(--bs-border-color);
        border-radius: .5rem;
        padding: .65rem .75rem;
        background: var(--bs-body-bg);
    }
    .cb-config-section-item {
        display: grid;
        grid-template-columns: auto 1fr;
        align-items: start;
        column-gap: .6rem;
    }
    .cb-config-section-main {
        min-width: 0;
    }
    .cb-config-section-desc {
        display: block;
        margin-top: .15rem;
        font-size: .78rem;
        color: var(--bs-secondary-color);
        line-height: 1.25;
    }
    .cb-import-log-scroll {
        max-height: 240px;
        overflow-y: auto;
    }
    .cb-audit-section-title {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
    }
    .cb-audit-detail-sections {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .cb-audit-section-block {
        display: flex;
        flex-direction: column;
        gap: .75rem;
        min-width: 0;
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: .85rem;
        padding: .75rem .9rem;
        background: linear-gradient(135deg, rgba(248, 249, 250, .95), rgba(255, 255, 255, .98));
    }
    .cb-audit-section-block > h4:first-child {
        margin: 0 !important;
    }
    .cb-audit-section-block > .alert,
    .cb-audit-section-block > .table-responsive,
    .cb-audit-section-block > ol {
        margin: 0;
    }
    .cb-audit-ok-check {
        color: #198754;
        font-size: .95rem;
        line-height: 1;
    }
    .cb-audit-warning-icon {
        color: #fd7e14;
        font-size: .95rem;
        line-height: 1;
    }
    .cb-repair-workflow-steps {
        display: grid;
        gap: .65rem;
        margin-bottom: 1rem;
    }
    .cb-repair-workflow-step {
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: .85rem;
        padding: .75rem .9rem;
        background: linear-gradient(135deg, rgba(248, 249, 250, .95), rgba(255, 255, 255, .98));
    }
    .cb-repair-workflow-step.is-current {
        border-color: rgba(13, 110, 253, .35);
        box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .08);
    }
    .cb-repair-workflow-step.is-done,
    .cb-repair-workflow-step.is-skipped {
        border-color: rgba(25, 135, 84, .22);
        background: linear-gradient(135deg, rgba(209, 231, 221, .72), rgba(248, 255, 251, .96));
    }
    .cb-repair-workflow-step-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
    }
    .cb-repair-workflow-step-title {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin: 0;
        font-weight: 600;
    }
    .cb-repair-workflow-step-check {
        color: #198754;
        font-size: .95rem;
        line-height: 1;
    }
    .cb-repair-workflow-step-desc {
        margin: .4rem 0 0;
        color: var(--bs-secondary-color);
    }
    .cb-repair-workflow-status {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-radius: 999px;
        padding: .25rem .55rem;
        background: rgba(108, 117, 125, .12);
        color: var(--bs-secondary-color);
        white-space: nowrap;
    }
    .cb-repair-workflow-status.is-done,
    .cb-repair-workflow-status.is-skipped {
        background: rgba(25, 135, 84, .12);
        color: #146c43;
    }
    .cb-repair-workflow-status.is-current {
        background: rgba(13, 110, 253, .12);
        color: #0a58ca;
    }
    .cb-repair-workflow-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        margin-top: 1rem;
    }
    .cb-repair-workflow-log {
        max-height: 260px;
        overflow: auto;
        white-space: pre-wrap;
        background: var(--bs-tertiary-bg);
    }
    .cb-repair-workflow-result {
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: .85rem;
        padding: 1rem;
        background: var(--bs-tertiary-bg);
    }
    .cb-repair-workflow-result.is-success {
        border-color: rgba(25, 135, 84, .22);
        background: linear-gradient(135deg, rgba(209, 231, 221, .72), rgba(248, 255, 251, .96));
    }
    .cb-repair-workflow-result.is-warning {
        border-color: rgba(255, 193, 7, .28);
        background: linear-gradient(135deg, rgba(255, 243, 205, .8), rgba(255, 252, 240, .97));
    }
    .cb-repair-workflow-result.is-danger {
        border-color: rgba(220, 53, 69, .22);
        background: linear-gradient(135deg, rgba(248, 215, 218, .72), rgba(255, 247, 248, .97));
    }
    .cb-repair-workflow-result-title {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
    }
    .cb-repair-workflow-summary {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        border: 1px solid rgba(25, 135, 84, .22);
        border-radius: .85rem;
        padding: .85rem 1rem;
        background: linear-gradient(135deg, rgba(209, 231, 221, .72), rgba(248, 255, 251, .96));
        color: #146c43;
        font-weight: 600;
    }
    .cb-repair-workflow-summary .icon-check-circle {
        font-size: 1rem;
    }
    .cb-repair-workflow-summary-section {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(0, 0, 0, .08);
    }
    .cb-repair-workflow-summary-title {
        margin: 0 0 .75rem;
        font-size: .95rem;
        font-weight: 600;
    }
</style>
<form
    action="<?php echo Route::_('index.php?option=com_contentbuilderng&view=about'); ?>"
    method="post"
    enctype="multipart/form-data"
    name="adminForm"
    id="adminForm"
>
<div class="cb-about-intro mt-3 mb-3">
    <div class="cb-about-intro-media">
        <img
            src="<?php echo htmlspecialchars(Uri::root(true) . '/media/com_contentbuilderng/images/piranha_50x500_blanc.png', ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_PIRANHA_IMAGE_ALT'), ENT_QUOTES, 'UTF-8'); ?>"
            class="img-fluid"
            style="max-width: 140px; height: auto;"
            loading="lazy"
        />
    </div>
    <div class="cb-about-intro-content">
        <p class="mb-0">
            <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_DESC'); ?>
        </p>
        <div class="cb-about-intro-links">
            <a
                class="cb-about-intro-link cb-about-intro-link--vcmb"
                href="https://breezingforms-ng.vcmb.fr"
                target="_blank"
                rel="noopener noreferrer"
                title="<?php echo htmlspecialchars($tooltipLinkVcmb, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($tooltipLinkVcmb, ENT_QUOTES, 'UTF-8'); ?>"
            >VCMB migration</a>
            <a
                class="cb-about-intro-link cb-about-intro-link--github"
                href="https://github.com/vcmb-cyclo/com_contentbuilder-ng"
                target="_blank"
                rel="noopener noreferrer"
                title="<?php echo htmlspecialchars($tooltipLinkGithub, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($tooltipLinkGithub, ENT_QUOTES, 'UTF-8'); ?>"
            >GitHub repository</a>
            <a
                class="cb-about-intro-link cb-about-intro-link--license"
                href="<?php echo htmlspecialchars($licenseUrl, ENT_QUOTES, 'UTF-8'); ?>"
                target="_blank"
                rel="noopener noreferrer"
                title="<?php echo htmlspecialchars($tooltipLinkLicense, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($tooltipLinkLicense, ENT_QUOTES, 'UTF-8'); ?>"
            ><?php echo Text::_('COM_CONTENTBUILDERNG_LICENSE_LINK'); ?></a>
        </div>
    </div>
</div>

<?php if ($repairWorkflowIsActive) : ?>
    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="h6 card-title mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_TITLE'); ?></h3>
                <small class="text-muted">
                    <?php echo Text::sprintf(
                        'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PROGRESS',
                        min(count($repairWorkflowSteps), $repairWorkflowCurrentIndex + 1),
                        count($repairWorkflowSteps)
                    ); ?>
                </small>
            </div>

            <p class="text-muted mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_INTRO'); ?></p>

            <div class="cb-repair-workflow-steps">
                <?php foreach ($repairWorkflowSteps as $stepIndex => $repairWorkflowStep) : ?>
                    <?php
                    if (!is_array($repairWorkflowStep)) {
                        continue;
                    }

                    $stepId = (string) ($repairWorkflowStep['id'] ?? '');
                    $stepStatus = (string) ($repairWorkflowStep['status'] ?? 'pending');
                    $stepIsCurrent = $stepIndex === $repairWorkflowDisplayCurrentIndex;
                    $stepClasses = ['cb-repair-workflow-step'];
                    $statusClasses = ['cb-repair-workflow-status'];
                    $stepNumber = $getAuditSectionNumber($stepId);

                    if ($stepIsCurrent && $stepStatus === 'pending') {
                        $stepClasses[] = 'is-current';
                        $statusClasses[] = 'is-current';
                    } elseif ($stepStatus === 'done' || $stepStatus === 'skipped') {
                        $stepClasses[] = 'is-' . $stepStatus;
                        $statusClasses[] = 'is-' . $stepStatus;
                    }

                    $stepLabel = $repairWorkflowStepLabels[$stepId] ?? $stepId;
                    $stepPrecheck = (array) ($repairWorkflowStep['precheck'] ?? []);
                    $stepResult = (array) ($repairWorkflowStep['result'] ?? []);
                    $stepResultLevel = (string) ($stepResult['level'] ?? 'message');
                    $stepDescription = trim((string) ($stepPrecheck['description'] ?? ($repairWorkflowStepDescriptions[$stepId] ?? '')));
                    $statusLabelKey = match ($stepStatus) {
                        'done' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STATUS_DONE',
                        'skipped' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STATUS_SKIPPED',
                        default => $stepIsCurrent
                            ? 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STATUS_CURRENT'
                            : 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STATUS_PENDING',
                    };
                    $statusIconClass = match (true) {
                        ($stepStatus === 'done' || $stepStatus === 'skipped') && !in_array($stepResultLevel, ['warning', 'error', 'danger'], true) => 'icon-check-circle',
                        default => '',
                    };
                    $showStepCheck = ($stepStatus === 'done' || $stepStatus === 'skipped')
                        && !in_array($stepResultLevel, ['warning', 'error', 'danger'], true);
                    ?>
                    <div class="<?php echo implode(' ', $stepClasses); ?>">
                        <div class="cb-repair-workflow-step-head">
                            <p class="cb-repair-workflow-step-title">
                                <?php if ($showStepCheck) : ?>
                                    <span class="cb-repair-workflow-step-check icon-check-circle" aria-hidden="true"></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars(($stepNumber > 0 ? $stepNumber . '. ' : '') . $stepLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <span class="<?php echo implode(' ', $statusClasses); ?>">
                                <?php if ($statusIconClass !== '') : ?>
                                    <span class="<?php echo htmlspecialchars($statusIconClass, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                                <?php endif; ?>
                                <?php echo Text::_($statusLabelKey); ?>
                            </span>
                        </div>
                        <?php if ($stepDescription !== '') : ?>
                            <p class="cb-repair-workflow-step-desc"><?php echo htmlspecialchars($stepDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <?php if ($stepIsCurrent && $stepStatus === 'pending') : ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_CONFIRM_PROMPT'); ?>
                            </div>
                            <div class="cb-repair-workflow-actions">
                                <button
                                    type="submit"
                                    class="btn btn-success"
                                    onclick="var f=this.form;if(f){f.elements['repair_step'].value=<?php echo htmlspecialchars(json_encode($stepId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>;f.elements['repair_action'].value='apply';f.elements['task'].value='about.executeRepairWorkflowStep';}"
                                ><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_APPLY'); ?></button>
                                <button
                                    type="submit"
                                    class="btn btn-outline-secondary"
                                    onclick="var f=this.form;if(f){f.elements['repair_step'].value=<?php echo htmlspecialchars(json_encode($stepId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>;f.elements['repair_action'].value='skip';f.elements['task'].value='about.executeRepairWorkflowStep';}"
                                ><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_SKIP'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($repairWorkflowShowCurrentPanel && is_array($repairWorkflowCurrentStep)) : ?>
                <?php
                $currentStepLabel = $repairWorkflowStepLabels[$repairWorkflowCurrentStepId] ?? $repairWorkflowCurrentStepId;
                $currentStepNumber = $getAuditSectionNumber($repairWorkflowCurrentStepId);
                $currentStepTitle = ($currentStepNumber > 0 ? $currentStepNumber . '. ' : '') . $currentStepLabel;
                $currentStepPrecheck = is_array($repairWorkflowCurrentStep) ? (array) ($repairWorkflowCurrentStep['precheck'] ?? []) : [];
                $currentStepDescription = trim((string) ($currentStepPrecheck['description'] ?? ($repairWorkflowStepDescriptions[$repairWorkflowCurrentStepId] ?? '')));
                $currentStepLines = (array) ($repairWorkflowCurrentResult['lines'] ?? []);
                $currentStepSummary = trim((string) ($repairWorkflowCurrentResult['summary'] ?? ''));
                $currentStepLevel = (string) ($repairWorkflowCurrentResult['level'] ?? 'info');
                $currentStepAlertClass = match ($currentStepLevel) {
                    'error' => 'danger',
                    'warning' => 'warning',
                    'message' => 'success',
                    default => 'info',
                };
                $currentStepPanelClasses = ['cb-repair-workflow-result'];
                if ($repairWorkflowCurrentStatus === 'skipped' || $currentStepAlertClass === 'success') {
                    $currentStepPanelClasses[] = 'is-success';
                } elseif ($currentStepAlertClass === 'warning') {
                    $currentStepPanelClasses[] = 'is-warning';
                } elseif ($currentStepAlertClass === 'danger') {
                    $currentStepPanelClasses[] = 'is-danger';
                }
                $currentStepShowCheck = $repairWorkflowCurrentStatus === 'skipped' || $currentStepAlertClass === 'success';
                ?>
                <div class="<?php echo implode(' ', $currentStepPanelClasses); ?>">
                    <h4 class="h6 mb-2 cb-repair-workflow-result-title">
                        <?php if ($currentStepShowCheck) : ?>
                            <span class="cb-repair-workflow-step-check icon-check-circle" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($currentStepTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                    </h4>
                    <?php if ($currentStepDescription !== '') : ?>
                        <p class="mb-2"><?php echo htmlspecialchars($currentStepDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>

                    <?php if ($repairWorkflowCurrentStatus !== 'pending') : ?>
                        <?php if ($currentStepSummary !== '') : ?>
                            <div class="alert alert-<?php echo htmlspecialchars($currentStepAlertClass, ENT_QUOTES, 'UTF-8'); ?> mb-3">
                                <?php echo htmlspecialchars($currentStepSummary, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($currentStepLines !== []) : ?>
                            <h5 class="h6"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_LOG_TITLE'); ?></h5>
                            <pre class="cb-repair-workflow-log border rounded p-3 mb-0"><?php echo htmlspecialchars(implode(PHP_EOL, $currentStepLines), ENT_QUOTES, 'UTF-8'); ?></pre>
                        <?php endif; ?>

                        <div class="cb-repair-workflow-actions">
                            <?php if ($repairWorkflowHasNext) : ?>
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    onclick="var f=this.form;if(f){f.elements['repair_step'].value='';f.elements['repair_action'].value='';f.elements['task'].value='about.nextRepairWorkflowStep';}"
                                ><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_NEXT'); ?></button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($repairWorkflowIsCompleted) : ?>
                <div class="cb-repair-workflow-summary-section">
                    <h4 class="cb-repair-workflow-summary-title">Summary</h4>
                    <div class="cb-repair-workflow-summary">
                        <span class="icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_FINISHED'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

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
                    <?php $auditSummaryRowNumber = static fn(string $sectionId): int => $getAuditSectionNumber($sectionId); ?>
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
                <h4 class="h6 mt-3<?php echo $hasDuplicateIndexIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('duplicate_indexes', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'), $hasDuplicateIndexIssues); ?></h4>
                <?php if (empty($duplicateIndexes)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_DUPLICATE_INDEXES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('plugin_duplicates'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 14;">
                <h4 class="h6 mt-3<?php echo $hasPluginDuplicateIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('plugin_duplicates', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATES'), $hasPluginDuplicateIssues); ?></h4>
                <?php if (empty($pluginExtensionDuplicates)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_PLUGIN_DUPLICATES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('historical_tables'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 4;">
                <h4 class="h6 mt-3<?php echo $hasLegacyTableIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('historical_tables', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_TABLES'), $hasLegacyTableIssues); ?></h4>
                <?php if (empty($historicalTables)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_HISTORICAL_TABLES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($historicalTables as $historicalTable) : ?>
                            <li><?php echo htmlspecialchars((string) $historicalTable, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('historical_menu_entries'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 5;">
                <h4 class="h6 mt-3<?php echo $hasLegacyMenuIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('historical_menu_entries', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_MENU_ENTRIES'), $hasLegacyMenuIssues); ?></h4>
                <?php if (empty($historicalMenuEntries)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_HISTORICAL_MENU_ENTRIES'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('audit_columns'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 10;">
                <h4 class="h6 mt-3<?php echo $hasMissingAuditColumnIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('audit_columns', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS'), $hasMissingAuditColumnIssues); ?></h4>
                <?php if (empty($missingAuditColumns)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_MISSING_AUDIT_COLUMNS'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
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
                            <?php foreach ($missingAuditColumns as $missingAuditColumn) : ?>
                                <tr>
                                    <td><?php echo $missingAuditColumnRowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($missingAuditColumn['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) ($missingAuditColumn['storage_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($missingAuditColumn['storage_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo Text::_((int) ($missingAuditColumn['bytable'] ?? 0) === 1 ? 'JYES' : 'JNO'); ?></td>
                                    <td><?php echo htmlspecialchars(implode(', ', (array) ($missingAuditColumn['missing'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('form_audit_columns'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 12;">
                <h4 class="h6 mt-3<?php echo $hasMissingFormAuditColumnIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('form_audit_columns', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_FORM_AUDIT_COLUMNS'), $hasMissingFormAuditColumnIssues); ?></h4>
                <?php if (empty($missingFormAuditColumns)) : ?>
                    <div class="alert cb-audit-ok-alert">
                        <span class="cb-audit-section-title">
                            <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                            <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_MISSING_FORM_AUDIT_COLUMNS'); ?></span>
                        </span>
                    </div>
                <?php else : ?>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('invalid_datetime_sort'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 13;">
                <h4 class="h6 mt-3<?php echo $hasInvalidDatetimeSortIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('invalid_datetime_sort', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INVALID_DATETIME_SORT'), $hasInvalidDatetimeSortIssues); ?></h4>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('bf_field_sync'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 16;">
                <h4 class="h6 mt-3<?php echo $hasBfFieldSyncIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('bf_field_sync', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC'), $hasBfFieldSyncIssues); ?></h4>
            <?php if (empty($bfFieldSyncIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_NO_ISSUES'); ?></span>
                    </span>
                </div>
            <?php else : ?>
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
                                        <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>">
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
                                        <span class="text-warning"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_SOURCE_MISSING'); ?></span>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('menu_view_consistency'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 19;">
                <h4 class="h6 mt-3<?php echo $hasMenuViewIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('menu_view_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_VIEW_CONSISTENCY'), $hasMenuViewIssues); ?></h4>
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
                                        <a href="<?php echo htmlspecialchars($menuEditLink, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($menuTitle, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars($menuTitle, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($menuViewIssue['target'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($menuViewIssue['link'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('frontend_permission_consistency'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 20;">
                <h4 class="h6 mt-3<?php echo $hasFrontendPermissionIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('frontend_permission_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FRONTEND_PERMISSION_CONSISTENCY'), $hasFrontendPermissionIssues); ?></h4>
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
                                        <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>">
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('element_reference_consistency'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 21;">
                <h4 class="h6 mt-3<?php echo $hasElementReferenceIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('element_reference_consistency', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_CONSISTENCY'), $hasElementReferenceIssues); ?></h4>
            <?php if (empty($elementReferenceIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ELEMENT_REFERENCE_CONSISTENCY_OK'); ?></span>
                    </span>
                </div>
            <?php else : ?>
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
                                        <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>">
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('generated_article_categories'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 22;">
                <h4 class="h6 mt-3<?php echo $hasGeneratedArticleCategoryIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('generated_article_categories', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES'), $hasGeneratedArticleCategoryIssues); ?></h4>
            <?php if (empty($generatedArticleCategoryIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <span class="cb-audit-section-title">
                        <span class="cb-audit-ok-check icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_OK'); ?></span>
                    </span>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table id="cb-audit-generated-article-categories-table" class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo $auditRowNumberLabel; ?></th>
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
                                <td><?php echo $generatedArticleCategoryIssueRowNumber++; ?></td>
                                <td><?php echo $formId; ?></td>
                                <td>
                                    <?php if ($formEditLink !== '') : ?>
                                        <a href="<?php echo htmlspecialchars($formEditLink, ENT_QUOTES, 'UTF-8'); ?>">
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('table_encoding'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 6;">
                <h4 class="h6 mt-3<?php echo $hasTableEncodingIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('table_encoding', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE_ENCODING_ISSUES') . ' (' . $tableEncodingIssueCount . ')', $hasTableEncodingIssues); ?></h4>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('column_encoding'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 8;">
                <h4 class="h6 mt-3<?php echo $hasColumnEncodingIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('column_encoding', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN_ENCODING_ISSUES') . ' (' . $columnEncodingIssueCount . ')', $hasColumnEncodingIssues); ?></h4>
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
                        <?php echo Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TRUNCATED', $columnEncodingIssueHiddenCount); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('mixed_collations'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 9;">
                <h4 class="h6 mt-3<?php echo $hasMixedCollationIssues ? ' text-warning' : ''; ?>"><?php echo $renderNumberedAuditTitle('mixed_collations', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MIXED_COLLATIONS') . ' (' . $mixedCollationIssueCount . ')', $hasMixedCollationIssues); ?></h4>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('cb_table_stats'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 22;">
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('packed_data'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 7;">
            <?php if ($hasPackedDataAuditRows) : ?>
                <h4 class="h6 mt-3"><?php echo $renderNumberedAuditTitle('packed_data', Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_DETAILS_TITLE'), $hasPackedDataIssues); ?></h4>
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
                                                <a href="<?php echo htmlspecialchars($formLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($formDisplay, ENT_QUOTES, 'UTF-8'); ?></a>
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

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('cb_ng_tables_missing'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 25;">
            <?php if (!empty($cbMissingNgTables)) : ?>
                <h4 class="h6 mt-3"><?php echo $renderNumberedAuditTitle('cb_ng_tables_missing', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES_MISSING'), !empty($cbMissingNgTables)); ?></h4>
                <ol class="mb-0 ps-3">
                    <?php foreach ($cbMissingNgTables as $missingNgTable) : ?>
                        <li><?php echo htmlspecialchars((string) $missingNgTable, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            </div>

            <div id="<?php echo htmlspecialchars($getAuditSectionHeadingId('errors'), ENT_QUOTES, 'UTF-8'); ?>" class="cb-audit-section-block" style="order: 28;">
            <?php if (!empty($auditWarnings)) : ?>
                <h4 class="h6 mt-3"><?php echo $renderNumberedAuditTitle('errors', Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ERRORS'), !empty($auditWarnings)); ?></h4>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($auditWarnings as $auditWarning) : ?>
                        <div class="alert alert-warning cb-audit-warning-alert mb-0">
                            <span class="cb-audit-warning-title"><?php echo htmlspecialchars((string) ($auditWarning['summary'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($auditWarning['detail']) || !empty($auditWarning['link_url'])) : ?>
                                <span class="cb-audit-warning-help">
                                    <?php if (!empty($auditWarning['detail'])) : ?>
                                        <?php echo htmlspecialchars((string) $auditWarning['detail'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($auditWarning['link_url'])) : ?>
                                        <?php if (!empty($auditWarning['detail'])) : ?><br><?php endif; ?>
                                        <a class="cb-audit-warning-link" href="<?php echo htmlspecialchars((string) $auditWarning['link_url'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars((string) ($auditWarning['link_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h3 class="h6 card-title mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_LOG_TITLE'); ?></h3>

        <?php if (!$hasLogReport) : ?>
            <div class="alert alert-info mb-0">
                <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_LOG_EMPTY'); ?>
            </div>
        <?php else : ?>
            <p class="text-muted small mb-2">
                <?php echo Text::sprintf(
                    'COM_CONTENTBUILDERNG_ABOUT_LOG_LAST_READ',
                    htmlspecialchars($logFileName, ENT_QUOTES, 'UTF-8'),
                    number_format($logSize, 0, '.', ' '),
                    htmlspecialchars($logLoadedAt, ENT_QUOTES, 'UTF-8')
                ); ?>
            </p>

            <?php if ($logTruncated) : ?>
                <div class="alert alert-warning py-2">
                    <?php echo Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_LOG_TRUNCATED', max(1, $logTailBytes)); ?>
                </div>
            <?php endif; ?>

            <?php if ($logDisplayContent === '') : ?>
                <div class="alert alert-info mb-0">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_LOG_NO_CONTENT'); ?>
                </div>
            <?php else : ?>
                <pre class="bg-body-tertiary text-body p-3 border rounded small mb-0" style="max-height: 420px; overflow: auto;"><?php echo htmlspecialchars($logDisplayContent, ENT_QUOTES, 'UTF-8'); ?></pre>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-3 cb-about-version-card">
    <div class="card-body p-3 p-lg-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 cb-about-version-header">
            <h3 class="h5 mb-0 cb-about-version-title"><?php echo Text::_('COM_CONTENTBUILDERNG_VERSION_INFORMATION'); ?></h3>
            <span class="d-flex flex-wrap gap-2">
                <span class="cb-about-version-badge">ContentBuilder NG</span>
                <span class="cb-about-version-badge <?php echo $isProductionBuild ? 'cb-about-version-badge--production' : 'cb-about-version-badge--dev'; ?>"><?php echo $buildTypeDisplay; ?></span>
            </span>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-2">
                <div class="cb-about-version-tile cb-about-version-tile--version">
                    <span class="cb-about-version-icon" aria-hidden="true">VER</span>
                    <p class="cb-about-version-label"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_VERSION'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($versionValue, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <div class="cb-about-version-tile cb-about-version-tile--date">
                    <span class="cb-about-version-icon" aria-hidden="true">DATE</span>
                    <p class="cb-about-version-label"><?php echo Text::_('COM_CONTENTBUILDERNG_CREATION_DATE_LABEL'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($creationDateValue, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="cb-about-version-tile cb-about-version-tile--author">
                    <span class="cb-about-version-icon" aria-hidden="true">DEV</span>
                    <p class="cb-about-version-label"><?php echo Text::_('COM_CONTENTBUILDERNG_AUTHOR'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($authorValue, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="cb-about-version-label mt-2"><?php echo Text::_('COM_CONTENTBUILDERNG_COPYRIGHT_LABEL'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($copyrightValue, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-12 col-lg-4">
                <div class="cb-about-version-tile cb-about-version-tile--license">
                    <span class="cb-about-version-icon" aria-hidden="true">GPL</span>
                    <p class="cb-about-version-label"><?php echo Text::_('COM_CONTENTBUILDERNG_LICENSE_LABEL'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($licenseValue, ENT_QUOTES, 'UTF-8'); ?></p>
                    <a
                        class="cb-about-version-link"
                        href="<?php echo htmlspecialchars($licenseUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    ><?php echo Text::_('COM_CONTENTBUILDERNG_LICENSE_LINK'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="cb-about-php-libraries-accordion">
            <div class="accordion-item">
                <h3 class="accordion-header" id="cb-about-php-libraries-heading">
                    <button
                        class="accordion-button collapsed fw-semibold"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#cb-about-php-libraries-collapse"
                        aria-expanded="false"
                        aria-controls="cb-about-php-libraries-collapse"
                    >
                        <?php echo (int) $phpLibrariesCount . ' ' . Text::_('COM_CONTENTBUILDERNG_PHP_LIBRARIES'); ?>
                    </button>
                </h3>
                <div
                    id="cb-about-php-libraries-collapse"
                    class="accordion-collapse collapse"
                    aria-labelledby="cb-about-php-libraries-heading"
                    data-bs-parent="#cb-about-php-libraries-accordion"
                >
                    <div class="accordion-body">
                        <?php if (empty($this->phpLibraries)) : ?>
                            <div class="alert alert-info mb-0">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PHP_LIBRARIES_NOT_AVAILABLE'); ?>
                            </div>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table id="cb-php-libraries-table" class="table table-sm table-striped align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_VERSION'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PHP_LIBRARY_SCOPE'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($this->phpLibraries as $library) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $library['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($library['version'] ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php echo Text::_(!empty($library['is_dev']) ? 'COM_CONTENTBUILDERNG_PHP_LIBRARY_SCOPE_DEV' : 'COM_CONTENTBUILDERNG_PHP_LIBRARY_SCOPE_RUNTIME'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card mt-3">
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="cb-about-js-libraries-accordion">
            <div class="accordion-item">
                <h3 class="accordion-header" id="cb-about-js-libraries-heading">
                    <button
                        class="accordion-button collapsed fw-semibold"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#cb-about-js-libraries-collapse"
                        aria-expanded="false"
                        aria-controls="cb-about-js-libraries-collapse"
                    >
                        <?php echo (int) $javascriptLibrariesCount . ' ' . Text::_('COM_CONTENTBUILDERNG_JS_LIBRARIES'); ?>
                    </button>
                </h3>
                <div
                    id="cb-about-js-libraries-collapse"
                    class="accordion-collapse collapse"
                    aria-labelledby="cb-about-js-libraries-heading"
                    data-bs-parent="#cb-about-js-libraries-accordion"
                >
                    <div class="accordion-body">
                        <?php if (empty($this->javascriptLibraries)) : ?>
                            <div class="alert alert-info mb-0">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARIES_NOT_AVAILABLE'); ?>
                            </div>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table id="cb-js-libraries-table" class="table table-sm table-striped align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_VERSION'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_ASSETS'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_SOURCE'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($this->javascriptLibraries as $library) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) ($library['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($library['version'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($library['assets'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($library['source'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <input type="hidden" name="option" value="com_contentbuilderng">
    <input type="hidden" name="repair_step" id="repair_step" value="">
    <input type="hidden" name="repair_action" id="repair_action" value="">
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
