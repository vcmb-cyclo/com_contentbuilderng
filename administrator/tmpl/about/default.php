<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU/GPL
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
$licenseValue = trim((string) $this->componentLicense);
$genericLicenseValues = ['gpl', 'gnu/gpl', 'gnu/gpl v2 or later'];
if ($licenseValue === '' || in_array(strtolower($licenseValue), $genericLicenseValues, true)) {
    $licenseValue = Text::_('COM_CONTENTBUILDERNG_LICENSE_FALLBACK');
}
$licenseUrl = 'https://www.gnu.org/licenses/gpl-2.0.html';
$auditReport = is_array($this->auditReport ?? null) ? $this->auditReport : [];
$auditSummary = (array) ($auditReport['summary'] ?? []);
$duplicateIndexes = (array) ($auditReport['duplicate_indexes'] ?? []);
$legacyTables = (array) ($auditReport['legacy_tables'] ?? []);
$tableEncodingIssues = (array) ($auditReport['table_encoding_issues'] ?? []);
$columnEncodingIssues = (array) ($auditReport['column_encoding_issues'] ?? []);
$mixedTableCollations = (array) ($auditReport['mixed_table_collations'] ?? []);
$missingAuditColumns = (array) ($auditReport['missing_audit_columns'] ?? []);
$pluginExtensionDuplicates = (array) ($auditReport['plugin_extension_duplicates'] ?? []);
$bfFieldSyncIssues = (array) ($auditReport['bf_view_field_sync_issues'] ?? []);
$missingAuditColumnsTotal = (int) ($auditSummary['missing_audit_columns_total'] ?? 0);
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
    $storageTableNotFoundMatch = [];
    if (preg_match('/^Storage #(\d+)\s+\((.*?)\)\s+table not found\.?$/i', $warningText, $storageTableNotFoundMatch) === 1) {
        $storageIdLabel = (int) ($storageTableNotFoundMatch[1] ?? 0);
        $storageNameLabel = trim((string) ($storageTableNotFoundMatch[2] ?? ''));
        $warningText = Text::sprintf(
            'COM_CONTENTBUILDERNG_ABOUT_AUDIT_WARNING_STORAGE_TABLE_NOT_FOUND',
            $storageIdLabel,
            $storageNameLabel
        );
        $warningDetail = Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_WARNING_STORAGE_TABLE_NOT_FOUND_DETAIL');
    }

    $auditWarnings[] = [
        'summary' => $warningText,
        'detail' => $warningDetail,
    ];
}
$hasAuditReport = $auditReport !== [];
$logReport = is_array($this->logReport ?? null) ? $this->logReport : [];
$hasLogReport = $logReport !== [];
$logFileName = (string) ($logReport['file'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$logSize = (int) ($logReport['size'] ?? 0);
$logLoadedAt = (string) ($logReport['loaded_at'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$logContent = (string) ($logReport['content'] ?? '');
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
        color: #0d6efd;
        background: #eaf2ff;
        border: 1px solid #b9d2ff;
    }
    .cb-about-intro-link--github {
        color: #ffffff;
        background: linear-gradient(135deg, #0f172a 0%, #1f2937 100%);
        border: 1px solid #111827;
        box-shadow: 0 .35rem .9rem rgba(15, 23, 42, .26);
    }
    .cb-about-intro-link--license {
        color: #3f2d00;
        background: linear-gradient(135deg, #fff3cd 0%, #ffe08a 100%);
        border: 1px solid #ffcf66;
        box-shadow: 0 .25rem .75rem rgba(191, 144, 0, .22);
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
            linear-gradient(140deg, #f8fafc 0%, #ffffff 72%);
        border: 1px solid #dbe4ee;
        border-radius: 1rem;
        overflow: hidden;
    }
    .cb-about-version-header {
        border-bottom: 1px dashed #d2dbe6;
        padding-bottom: .75rem;
    }
    .cb-about-version-title {
        color: #172b4d;
        font-weight: 700;
        letter-spacing: .01em;
    }
    .cb-about-version-badge {
        background-color: #172b4d;
        color: #ffffff;
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
        border: 1px solid #dce3eb;
        border-radius: .9rem;
        background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
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
        background-color: #e8f1ff;
        color: #0d6efd;
    }
    .cb-about-version-tile--date .cb-about-version-icon {
        background-color: #e7f6ed;
        color: #198754;
    }
    .cb-about-version-tile--author .cb-about-version-icon {
        background-color: #fff1e8;
        color: #fd7e14;
    }
    .cb-about-version-tile--license .cb-about-version-icon {
        background-color: #fff6d6;
        color: #a87400;
    }
    .cb-about-version-label {
        margin: .15rem 0 0;
        color: #6c757d;
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .cb-about-version-value {
        margin: 0;
        color: #1b2a41;
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
        background-color: #eaf7ef;
        border-color: #b7e1c1;
        color: #0f5132;
    }
    .cb-audit-ok-alert::before {
        content: "\2713";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.2rem;
        height: 1.2rem;
        border-radius: 50%;
        background-color: #198754;
        color: #ffffff;
        font-weight: 700;
        font-size: .78rem;
        line-height: 1;
        flex: 0 0 auto;
    }
    .cb-audit-warning-alert {
        border-color: #ffda9f;
        background-color: #fff4df;
        color: #664d03;
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
</style>
<form
    action="<?php echo Route::_('index.php?option=com_contentbuilderng&view=about'); ?>"
    method="post"
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
            >VCMB migration</a>
            <a
                class="cb-about-intro-link cb-about-intro-link--github"
                href="https://github.com/vcmb-cyclo/com_contentbuilder-ng"
                target="_blank"
                rel="noopener noreferrer"
            >GitHub repository</a>
            <a
                class="cb-about-intro-link cb-about-intro-link--license"
                href="<?php echo htmlspecialchars($licenseUrl, ENT_QUOTES, 'UTF-8'); ?>"
                target="_blank"
                rel="noopener noreferrer"
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
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_ISSUES_TOTAL'); ?></th>
                        <td><?php echo (int) ($auditSummary['issues_total'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'); ?></th>
                        <td><?php echo (int) ($auditSummary['duplicate_index_groups'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_TO_DROP'); ?></th>
                        <td><?php echo (int) ($auditSummary['duplicate_indexes_to_drop'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_TABLES'); ?></th>
                        <td><?php echo (int) ($auditSummary['legacy_tables'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE_ENCODING_ISSUES'); ?></th>
                        <td><?php echo (int) ($auditSummary['table_encoding_issues'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN_ENCODING_ISSUES'); ?></th>
                        <td><?php echo (int) ($auditSummary['column_encoding_issues'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MIXED_COLLATIONS'); ?></th>
                        <td><?php echo max(0, count($mixedTableCollations) - 1); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS_TABLES'); ?></th>
                        <td><?php echo (int) ($auditSummary['missing_audit_column_tables'] ?? count($missingAuditColumns)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS_TOTAL'); ?></th>
                        <td><?php echo $missingAuditColumnsTotal; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATE_GROUPS'); ?></th>
                        <td><?php echo $pluginDuplicateGroups; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATE_ROWS_TO_REMOVE'); ?></th>
                        <td><?php echo $pluginDuplicateRowsToRemove; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_VIEWS'); ?></th>
                        <td><?php echo $bfFieldSyncViews; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC_MISSING_IN_CB'); ?></th>
                        <td><?php echo $bfFieldSyncMissingTotal; ?></td>
                    </tr>
                    <tr>
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

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DUPLICATE_GROUPS'); ?></h4>
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

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_PLUGIN_DUPLICATES'); ?></h4>
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

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_TABLES'); ?></h4>
            <?php if (empty($legacyTables)) : ?>
                <div class="alert cb-audit-ok-alert">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_NO_LEGACY_TABLES'); ?>
                </div>
            <?php else : ?>
                <ul class="mb-0">
                    <?php foreach ($legacyTables as $legacyTable) : ?>
                        <li><?php echo htmlspecialchars((string) $legacyTable, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MISSING_AUDIT_COLUMNS'); ?></h4>
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

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_BF_FIELD_SYNC'); ?></h4>
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

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_TABLE_ENCODING_ISSUES'); ?></h4>
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

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_COLUMN_ENCODING_ISSUES'); ?></h4>
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

            <h4 class="h6 mt-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_MIXED_COLLATIONS'); ?></h4>
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
                            <?php if (!empty($auditWarning['detail'])) : ?>
                                <span class="cb-audit-warning-help"><?php echo htmlspecialchars((string) $auditWarning['detail'], ENT_QUOTES, 'UTF-8'); ?></span>
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

            <?php if ($logContent === '') : ?>
                <div class="alert alert-info mb-0">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_LOG_NO_CONTENT'); ?>
                </div>
            <?php else : ?>
                <pre class="bg-light p-3 border rounded small mb-0" style="max-height: 420px; overflow: auto;"><?php echo htmlspecialchars($logContent, ENT_QUOTES, 'UTF-8'); ?></pre>
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
            <div class="col-12 col-md-6 col-lg-2">
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
            <div class="col-12 col-md-6 col-lg-2">
                <div class="cb-about-version-tile cb-about-version-tile--author">
                    <span class="cb-about-version-icon" aria-hidden="true">DEV</span>
                    <p class="cb-about-version-label"><?php echo Text::_('COM_CONTENTBUILDERNG_AUTHOR_LABEL'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($authorValue, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-12 col-lg-6">
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
