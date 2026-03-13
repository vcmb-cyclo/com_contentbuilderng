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

$versionValue = (string) ($this->componentVersion ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$creationDateValue = (string) ($this->componentCreationDate ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$authorValue = (string) ($this->componentAuthor ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$copyrightValue = (string) ($this->componentCopyright ?: Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$licenseValue = trim((string) $this->componentLicense);
$genericLicenseValues = ['gpl', 'gnu/gpl', 'gnu/gpl v2 or later'];
if ($licenseValue === '' || in_array(strtolower($licenseValue), $genericLicenseValues, true)) {
    $licenseValue = Text::_('COM_CONTENTBUILDERNG_LICENSE_FALLBACK');
}
$licenseUrl = 'https://www.gnu.org/licenses/gpl-2.0.html';
$tooltipAudit = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_AUDIT');
$tooltipDbRepair = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_DB_REPAIR');
$tooltipShowLog = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_SHOW_LOG');
$tooltipLinkVcmb = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_LINK_VCMB');
$tooltipLinkGithub = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_LINK_GITHUB');
$tooltipLinkLicense = Text::_('COM_CONTENTBUILDERNG_ABOUT_TOOLTIP_LINK_LICENSE');
$labelAuditButton = Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT');
$labelDbRepairButton = Text::_('COM_CONTENTBUILDERNG_ABOUT_MIGRATE_PACKED_DATA');
$labelShowLogButton = Text::_('COM_CONTENTBUILDERNG_ABOUT_SHOW_LOG');
$auditReport = is_array($this->auditReport ?? null) ? $this->auditReport : [];
$auditSummary = (array) ($auditReport['summary'] ?? []);
$duplicateIndexes = (array) ($auditReport['duplicate_indexes'] ?? []);
$historicalTables = (array) ($auditReport['historical_tables'] ?? []);
$historicalMenuEntries = (array) ($auditReport['historical_menu_entries'] ?? []);
$tableEncodingIssues = (array) ($auditReport['table_encoding_issues'] ?? []);
$columnEncodingIssues = (array) ($auditReport['column_encoding_issues'] ?? []);
$mixedTableCollations = (array) ($auditReport['mixed_table_collations'] ?? []);
$missingAuditColumns = (array) ($auditReport['missing_audit_columns'] ?? []);
$pluginExtensionDuplicates = (array) ($auditReport['plugin_extension_duplicates'] ?? []);
$bfFieldSyncIssues = (array) ($auditReport['bf_view_field_sync_issues'] ?? []);
$missingAuditColumnsTotal = (int) ($auditSummary['missing_audit_columns_total'] ?? 0);
$missingAuditColumnsTableCount = (int) ($auditSummary['missing_audit_column_tables'] ?? count($missingAuditColumns));
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
$dbRepairConfirmMessage = str_replace('\n', "\n", Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_CONFIRMATION'));
$dbRepairPromptMessage = str_replace('\n', "\n", Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_CONFIRMATION_PROMPT'));
$dbRepairPromptFailedMessage = str_replace('\n', "\n", Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_CONFIRMATION_FAILED'));
$phpLibrariesCount = count((array) $this->phpLibraries);
$javascriptLibrariesCount = count((array) $this->javascriptLibraries);
$columnEncodingIssueLimit = 200;
$columnEncodingIssuesDisplayed = array_slice($columnEncodingIssues, 0, $columnEncodingIssueLimit);
$columnEncodingIssueHiddenCount = max(0, count($columnEncodingIssues) - count($columnEncodingIssuesDisplayed));
$hasAuditIssues = (int) ($auditSummary['issues_total'] ?? 0) > 0;
$hasDuplicateIndexIssues = !empty($duplicateIndexes);
$hasDuplicateIndexDropIssues = (int) ($auditSummary['duplicate_indexes_to_drop'] ?? 0) > 0;
$hasLegacyTableIssues = !empty($legacyTables);
$hasLegacyMenuIssues = $legacyMenuEntriesCount > 0;
$hasTableEncodingIssues = !empty($tableEncodingIssues);
$hasColumnEncodingIssues = !empty($columnEncodingIssues);
$hasMixedCollationIssues = count($mixedTableCollations) > 1;
$hasMissingAuditColumnIssues = $missingAuditColumnsTableCount > 0 || $missingAuditColumnsTotal > 0;
$hasPluginDuplicateIssues = $pluginDuplicateGroups > 0 || $pluginDuplicateRowsToRemove > 0;
$hasBfFieldSyncIssues = $bfFieldSyncViews > 0 || $bfFieldSyncMissingTotal > 0 || $bfFieldSyncOrphanTotal > 0;
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
        background-color: var(--bs-primary-bg-subtle);
        color: var(--bs-primary-text-emphasis);
    }
    .cb-about-version-tile--date .cb-about-version-icon {
        background-color: var(--bs-success-bg-subtle);
        color: var(--bs-success-text-emphasis);
    }
    .cb-about-version-tile--author .cb-about-version-icon {
        background-color: var(--bs-warning-bg-subtle);
        color: var(--bs-warning-text-emphasis);
    }
    .cb-about-version-tile--license .cb-about-version-icon {
        background-color: var(--bs-warning-bg-subtle);
        color: var(--bs-warning-text-emphasis);
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
        font-size: 1.22rem;
        font-weight: 700;
        line-height: 1.25;
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
        content: "\2713";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.2rem;
        height: 1.2rem;
        border-radius: 50%;
        background-color: var(--bs-success);
        color: var(--bs-white);
        font-weight: 700;
        font-size: .78rem;
        line-height: 1;
        flex: 0 0 auto;
    }
    .cb-audit-warning-alert {
        border-color: var(--bs-warning-border-subtle);
        background-color: var(--bs-warning-bg-subtle);
        color: var(--bs-warning-text-emphasis);
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

<div class="card mt-3">
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
                    (string) ($auditReport['generated_at'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')),
                    (int) ($auditReport['scanned_tables'] ?? 0)
                ); ?>
            </p>

            <div class="table-responsive mb-3">
                <table class="table table-sm table-striped align-middle mb-0">
                    <tbody>
                    <tr class="<?php echo $hasAuditIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ISSUES_TOTAL'); ?></th>
                        <td><?php echo (int) ($auditSummary['issues_total'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasDuplicateIndexIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'); ?></th>
                        <td><?php echo (int) ($auditSummary['duplicate_index_groups'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasDuplicateIndexDropIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_TO_DROP'); ?></th>
                        <td><?php echo (int) ($auditSummary['duplicate_indexes_to_drop'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasLegacyTableIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_TABLES'); ?></th>
                        <td><?php echo (int) ($auditSummary['historical_tables'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasLegacyMenuIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_MENU_ENTRIES'); ?></th>
                        <td><?php echo $historicalMenuEntriesCount; ?></td>
                    </tr>
                    <tr class="<?php echo $hasTableEncodingIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE_ENCODING_ISSUES'); ?></th>
                        <td><?php echo (int) ($auditSummary['table_encoding_issues'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasColumnEncodingIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN_ENCODING_ISSUES'); ?></th>
                        <td><?php echo (int) ($auditSummary['column_encoding_issues'] ?? 0); ?></td>
                    </tr>
                    <tr class="<?php echo $hasMixedCollationIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MIXED_COLLATIONS'); ?></th>
                        <td><?php echo max(0, count($mixedTableCollations) - 1); ?></td>
                    </tr>
                    <tr class="<?php echo $missingAuditColumnsTableCount > 0 ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS_TABLES'); ?></th>
                        <td><?php echo $missingAuditColumnsTableCount; ?></td>
                    </tr>
                    <tr class="<?php echo $missingAuditColumnsTotal > 0 ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS_TOTAL'); ?></th>
                        <td><?php echo $missingAuditColumnsTotal; ?></td>
                    </tr>
                    <tr class="<?php echo $hasPluginDuplicateIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATE_GROUPS'); ?></th>
                        <td><?php echo $pluginDuplicateGroups; ?></td>
                    </tr>
                    <tr class="<?php echo $hasPluginDuplicateIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATE_ROWS_TO_REMOVE'); ?></th>
                        <td><?php echo $pluginDuplicateRowsToRemove; ?></td>
                    </tr>
                    <tr class="<?php echo $hasBfFieldSyncIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEWS'); ?></th>
                        <td><?php echo $bfFieldSyncViews; ?></td>
                    </tr>
                    <tr class="<?php echo $hasBfFieldSyncIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_MISSING_IN_CB'); ?></th>
                        <td><?php echo $bfFieldSyncMissingTotal; ?></td>
                    </tr>
                    <tr class="<?php echo $hasBfFieldSyncIssues ? 'table-warning' : ''; ?>">
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_EXTRA_IN_CB'); ?></th>
                        <td><?php echo $bfFieldSyncOrphanTotal; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_TABLES_TOTAL'); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_total'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES'); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_ng_total'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES_EXPECTED'); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_ng_expected'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES_MISSING'); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_ng_missing'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_STORAGE_TABLES'); ?></th>
                        <td><?php echo (int) ($cbTableSummary['tables_storage_total'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_ESTIMATED_ROWS'); ?></th>
                        <td><?php echo number_format((int) ($cbTableSummary['rows_total'] ?? 0), 0, '.', ' '); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_ESTIMATED_SIZE'); ?></th>
                        <td><?php echo $formatBytes((int) ($cbTableSummary['size_bytes_total'] ?? 0)); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <?php if ((int) ($auditSummary['issues_total'] ?? 0) === 0 && empty($auditErrors)) : ?>
                <div class="alert cb-audit-ok-alert mb-3">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_ISSUES'); ?>
                </div>
            <?php endif; ?>

            <h4 class="h6 mt-3<?php echo $hasDuplicateIndexIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'); ?></h4>
            <?php if (empty($duplicateIndexes)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_DUPLICATE_INDEXES'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_KEEP'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_DROP'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEXES'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($duplicateIndexes as $duplicateIndex) : ?>
                            <tr>
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

            <h4 class="h6 mt-3<?php echo $hasPluginDuplicateIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATES'); ?></h4>
            <?php if (empty($pluginExtensionDuplicates)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_PLUGIN_DUPLICATES'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CANONICAL_PLUGIN'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_KEEP'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_INDEX_DROP'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_ROWS'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
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

            <h4 class="h6 mt-3<?php echo $hasLegacyTableIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_TABLES'); ?></h4>
            <?php if (empty($historicalTables)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_HISTORICAL_TABLES'); ?>
                </div>
            <?php else : ?>
                <ul class="mb-0">
                    <?php foreach ($historicalTables as $historicalTable) : ?>
                        <li><?php echo htmlspecialchars((string) $historicalTable, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4 class="h6 mt-3<?php echo $hasLegacyMenuIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_HISTORICAL_MENU_ENTRIES'); ?></h4>
            <?php if (empty($historicalMenuEntries)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_HISTORICAL_MENU_ENTRIES'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_TITLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_NORMALIZED_TITLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MENU_LINK'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historicalMenuEntries as $historicalMenuEntry) : ?>
                            <tr>
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

            <h4 class="h6 mt-3<?php echo $hasMissingAuditColumnIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS'); ?></h4>
            <?php if (empty($missingAuditColumns)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_MISSING_AUDIT_COLUMNS'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BYTABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($missingAuditColumns as $missingAuditColumn) : ?>
                            <tr>
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

            <h4 class="h6 mt-3<?php echo $hasBfFieldSyncIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC'); ?></h4>
            <?php if (empty($bfFieldSyncIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_NO_ISSUES'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEW_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEW'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_SOURCE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_MISSING'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_ORPHAN'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bfFieldSyncIssues as $bfFieldSyncIssue) : ?>
                            <?php
                            $formId = (int) ($bfFieldSyncIssue['form_id'] ?? 0);
                            $formName = trim((string) ($bfFieldSyncIssue['form_name'] ?? ''));
                            $formNameDisplay = $formName !== '' ? $formName : ('#' . $formId);
                            $formEditLink = $formId > 0
                                ? Route::_('index.php?option=com_contentbuilderng&task=form.edit&id=' . $formId)
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

            <h4 class="h6 mt-3<?php echo $hasTableEncodingIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE_ENCODING_ISSUES'); ?></h4>
            <?php if (empty($tableEncodingIssues)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_TABLE_ENCODING_ISSUES'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLLATION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_EXPECTED'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tableEncodingIssues as $tableIssue) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($tableIssue['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) (($tableIssue['collation'] ?? '') !== '' ? $tableIssue['collation'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($tableIssue['expected'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h4 class="h6 mt-3<?php echo $hasColumnEncodingIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN_ENCODING_ISSUES'); ?></h4>
            <?php if (empty($columnEncodingIssuesDisplayed)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_COLUMN_ENCODING_ISSUES'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CHARSET'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLLATION'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($columnEncodingIssuesDisplayed as $columnIssue) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($columnIssue['table'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($columnIssue['column'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) (($columnIssue['charset'] ?? '') !== '' ? $columnIssue['charset'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) (($columnIssue['collation'] ?? '') !== '' ? $columnIssue['collation'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
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

            <h4 class="h6 mt-3<?php echo $hasMixedCollationIssues ? ' text-warning' : ''; ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MIXED_COLLATIONS'); ?></h4>
            <?php if (count($mixedTableCollations) <= 1) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_MIXED_COLLATIONS'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLLATION'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COUNT'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mixedTableCollations as $collationStat) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($collationStat['collation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) ($collationStat['count'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars(implode(', ', (array) ($collationStat['tables'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_TABLE_STATS'); ?></h4>
            <?php if (empty($cbTableDetails)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_CB_TABLE_STATS'); ?>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COUNT'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SIZE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ENGINE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLLATION'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cbTableDetails as $cbTableDetail) : ?>
                            <tr>
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

            <?php if (!empty($cbMissingNgTables)) : ?>
                <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_CB_NG_TABLES_MISSING_LIST'); ?></h4>
                <ul class="mb-0">
                    <?php foreach ($cbMissingNgTables as $missingNgTable) : ?>
                        <li><?php echo htmlspecialchars((string) $missingNgTable, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($auditWarnings)) : ?>
                <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ERRORS'); ?></h4>
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
            <span class="cb-about-version-badge">ContentBuilder NG</span>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-1">
                <div class="cb-about-version-tile cb-about-version-tile--version">
                    <span class="cb-about-version-icon" aria-hidden="true">VER</span>
                    <p class="cb-about-version-label"><?php echo Text::_('COM_CONTENTBUILDERNG_VERSION_LABEL'); ?></p>
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
                    <p class="cb-about-version-label"><?php echo Text::_('COM_CONTENTBUILDERNG_AUTHOR_LABEL'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($authorValue, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="cb-about-version-label mt-2"><?php echo Text::_('COM_CONTENTBUILDERNG_COPYRIGHT_LABEL'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($copyrightValue, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-12 col-lg-5">
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
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PHP_LIBRARY'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PHP_LIBRARY_VERSION'); ?></th>
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
                                <table class="table table-sm table-striped align-middle mb-0">
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
<script>
    (function () {
        var originalSubmitbutton = Joomla.submitbutton;
        var toolbarTaskMeta = {
            'about.runAudit': {
                tooltip: <?php echo json_encode($tooltipAudit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                label: <?php echo json_encode($labelAuditButton, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                fragments: ['about_audit', 'about-audit', 'runaudit', 'run-audit']
            },
            'about.migratePackedData': {
                tooltip: <?php echo json_encode($tooltipDbRepair, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                label: <?php echo json_encode($labelDbRepairButton, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                fragments: ['about_migrate_packed_data', 'about-migrate-packed-data', 'migratepackeddata', 'migrate-packed-data']
            },
            'about.showLog': {
                tooltip: <?php echo json_encode($tooltipShowLog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                label: <?php echo json_encode($labelShowLogButton, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                fragments: ['about_show_log', 'about-show-log', 'showlog', 'show-log']
            }
        };

        function getToolbarHost() {
            return document.getElementById('toolbar')
                || document.querySelector('joomla-toolbar')
                || document.querySelector('.toolbar');
        }

        function uniqueNodes(nodes) {
            var seen = [];
            var unique = [];

            Array.prototype.forEach.call(nodes || [], function (node) {
                if (!node || seen.indexOf(node) !== -1) {
                    return;
                }

                seen.push(node);
                unique.push(node);
            });

            return unique;
        }

        function normalizeText(value) {
            return String(value || '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
        }

        function collectTaskNodes(task, meta) {
            var selectors = [
                '[data-task="' + task + '"]',
                '[task="' + task + '"]',
                '[data-submit-task="' + task + '"]',
                '[onclick*="' + task + '"]'
            ];
            var nodes = [];

            if (meta && Array.isArray(meta.fragments)) {
                meta.fragments.forEach(function (fragment) {
                    if (!fragment) {
                        return;
                    }

                    selectors.push('[id*="' + fragment + '"]');
                    selectors.push('[class*="' + fragment + '"]');
                });
            }

            selectors.forEach(function (selector) {
                try {
                    Array.prototype.push.apply(nodes, document.querySelectorAll(selector));
                } catch (error) {
                    // Ignore invalid selector fragments.
                }
            });

            if (nodes.length === 0 && meta && meta.label) {
                var expected = normalizeText(meta.label);

                Array.prototype.forEach.call(
                    document.querySelectorAll('#toolbar button, #toolbar a, joomla-toolbar-button, .toolbar button, .toolbar a'),
                    function (node) {
                        var text = normalizeText(node.textContent || '');

                        if (text === expected || (expected && text.indexOf(expected) !== -1)) {
                            nodes.push(node);
                        }
                    }
                );
            }

            return uniqueNodes(nodes);
        }

        function applyTooltipAttributes(node, tooltip) {
            if (!node || !tooltip) {
                return;
            }

            node.setAttribute('title', tooltip);
            node.setAttribute('aria-label', tooltip);
            node.setAttribute('data-bs-title', tooltip);
            node.setAttribute('data-bs-toggle', 'tooltip');
        }

        function applyTooltipToTaskNodes(nodes, tooltip) {
            nodes.forEach(function (node) {
                applyTooltipAttributes(node, tooltip);

                Array.prototype.forEach.call(
                    node.querySelectorAll('button, a, [role="button"], .btn, .toolbar-button'),
                    function (child) {
                        applyTooltipAttributes(child, tooltip);
                    }
                );

                if (node.shadowRoot) {
                    Array.prototype.forEach.call(
                        node.shadowRoot.querySelectorAll('button, a, [role="button"], .btn, .toolbar-button'),
                        function (child) {
                            applyTooltipAttributes(child, tooltip);
                        }
                    );
                }

                var host = node.closest('joomla-toolbar-button, .btn-wrapper, .toolbar-button');
                if (host) {
                    applyTooltipAttributes(host, tooltip);
                    Array.prototype.forEach.call(
                        host.querySelectorAll('button, a, [role="button"], .btn, .toolbar-button'),
                        function (child) {
                            applyTooltipAttributes(child, tooltip);
                        }
                    );
                }
            });
        }

        function initBootstrapTooltips() {
            if (!window.bootstrap || !window.bootstrap.Tooltip) {
                return;
            }

            Array.prototype.forEach.call(
                document.querySelectorAll('#toolbar [data-bs-toggle="tooltip"], joomla-toolbar [data-bs-toggle="tooltip"]'),
                function (node) {
                    var instance = window.bootstrap.Tooltip.getInstance(node);
                    if (!instance) {
                        new window.bootstrap.Tooltip(node, { container: 'body', trigger: 'hover focus' });
                    }
                }
            );
        }

        function applyToolbarTaskTooltips() {
            Object.keys(toolbarTaskMeta).forEach(function (task) {
                var meta = toolbarTaskMeta[task] || {};
                var tooltip = String(meta.tooltip || '').trim();

                if (!tooltip) {
                    return;
                }

                applyTooltipToTaskNodes(collectTaskNodes(task, meta), tooltip);
            });

            initBootstrapTooltips();
        }

        function scheduleToolbarTaskTooltips() {
            [0, 200, 600].forEach(function (delay) {
                window.setTimeout(applyToolbarTaskTooltips, delay);
            });
        }

        function observeToolbarUpdates() {
            var toolbarHost = getToolbarHost();

            if (!toolbarHost || typeof MutationObserver !== 'function') {
                return;
            }

            var observer = new MutationObserver(applyToolbarTaskTooltips);
            observer.observe(toolbarHost, { childList: true, subtree: true });

            window.setTimeout(function () {
                observer.disconnect();
            }, 8000);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                scheduleToolbarTaskTooltips();
                observeToolbarUpdates();
            }, { once: true });
        } else {
            scheduleToolbarTaskTooltips();
            observeToolbarUpdates();
        }

        Joomla.submitbutton = function (task) {
            if (task === 'about.migratePackedData') {
                var confirmed = window.confirm(
                    <?php echo json_encode($dbRepairConfirmMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                );

                if (!confirmed) {
                    return false;
                }

                var requiredToken = 'REPAIR';
                var typedToken = window.prompt(
                    <?php echo json_encode($dbRepairPromptMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                    ''
                );

                if (typedToken === null || typedToken.trim() !== requiredToken) {
                    window.alert(
                        <?php echo json_encode($dbRepairPromptFailedMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                    );
                    return false;
                }
            }

            if (typeof originalSubmitbutton === 'function') {
                return originalSubmitbutton(task);
            }

            return Joomla.submitform(task, document.getElementById('adminForm'));
        };
    })();
</script>
    <input type="hidden" name="option" value="com_contentbuilderng">
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
