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

use CB\Component\Contentbuilderng\Administrator\Helper\Audit\EncodingAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\GeneratedArticleCategoryAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\DatabaseAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\DatabaseRepairHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\FormDisplayColumnsHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataMigrationHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\PluginExtensionDedupHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\StorageAuditColumnsHelper;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class RepairWorkflowService
{
    public const WORKFLOW_STATE_KEY = 'com_contentbuilderng.about.repair_workflow';

    private const WORKFLOW_STEPS = [
        'duplicate_indexes',
        'historical_tables',
        'historical_menu_entries',
        'table_encoding',
        'packed_data',
        'audit_columns',
        'form_audit_columns',
        'plugin_duplicates',
        'bf_field_sync',
        'menu_view_consistency',
        'frontend_permission_consistency',
        'element_reference_consistency',
        'generated_article_categories',
    ];

    public function createWorkflowState(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $steps = [];
        $now = $this->localNow();
        $prechecks = $this->buildPrechecks($db);

        foreach (self::WORKFLOW_STEPS as $stepId) {
            $step = [
                'id' => $stepId,
                'status' => 'pending',
                'decision' => '',
                'completed_at' => '',
                'result' => [
                    'level' => 'message',
                    'summary' => '',
                    'lines' => [],
                ],
            ];

            if (isset($prechecks[$stepId])) {
                $step['precheck'] = $prechecks[$stepId];
            }

            if (($prechecks[$stepId]['mode'] ?? '') === 'diagnostic') {
                $step['status'] = 'done';
                $step['decision'] = 'diagnostic';
                $step['completed_at'] = $now;
                $step['result'] = (array) ($prechecks[$stepId]['result'] ?? [
                    'level' => 'message',
                    'summary' => (string) ($prechecks[$stepId]['description'] ?? ''),
                    'lines' => [],
                ]);
                $steps[] = $step;
                continue;
            }

            if ((int) ($prechecks[$stepId]['count'] ?? 0) === 0 && empty($prechecks[$stepId]['has_errors'])) {
                $step['status'] = 'skipped';
                $step['decision'] = 'skip';
                $step['completed_at'] = $now;
                $step['result'] = [
                    'level' => 'message',
                    'summary' => (string) ($prechecks[$stepId]['skip_summary'] ?? 'Skipped automatically because there is nothing to repair.'),
                    'lines' => [],
                ];
            }

            $steps[] = $step;
        }

        $currentStep = 0;
        while ($currentStep < count($steps) && (string) ($steps[$currentStep]['status'] ?? 'pending') !== 'pending') {
            $currentStep++;
        }

        $completed = $currentStep >= count($steps);
        if ($completed) {
            $currentStep = max(0, count($steps) - 1);
        }

        return [
            'active' => true,
            'completed' => $completed,
            'current_step' => $currentStep,
            'steps' => $steps,
            'started_at' => $now,
            'updated_at' => $now,
        ];
    }

    public function getWorkflowState(AdministratorApplication $app): array
    {
        $workflow = $app->getUserState(self::WORKFLOW_STATE_KEY, []);
        return is_array($workflow) ? $workflow : [];
    }

    public function executeStep(string $stepId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return match ($stepId) {
            'duplicate_indexes'    => $this->buildDuplicateIndexStepResult(DatabaseRepairHelper::repairDuplicateIndexesStep($db)),
            'historical_tables'    => $this->buildHistoricalTablesStepResult(DatabaseRepairHelper::repairHistoricalTablesStep($db)),
            'table_encoding'       => $this->buildTableEncodingStepResult(PackedDataMigrationHelper::repairTableCollationsStep($db)),
            'packed_data'          => $this->buildPackedDataStepResult(PackedDataMigrationHelper::migratePackedPayloadsStep($db)),
            'audit_columns'        => $this->buildAuditColumnsStepResult(StorageAuditColumnsHelper::repair($db)),
            'form_audit_columns'   => $this->buildFormAuditColumnsStepResult(FormDisplayColumnsHelper::repair($db)),
            'plugin_duplicates'    => $this->buildPluginDuplicateStepResult(PluginExtensionDedupHelper::repair($db)),
            'historical_menu_entries' => $this->buildHistoricalMenuStepResult(PackedDataMigrationHelper::repairLegacyMenuEntriesStep($db)),
            'bf_field_sync'        => $this->buildBfFieldSyncStepResult(DatabaseAuditHelper::run()),
            'generated_article_categories' => $this->buildGeneratedArticleCategoryStepResult(GeneratedArticleCategoryAuditHelper::repair($db)),
            default                => throw new \RuntimeException('Unknown repair step: ' . $stepId),
        };
    }

    public function logStepResult(string $stepId, string $action, array $result): void
    {
        $level = strtolower((string) ($result['level'] ?? 'info'));
        $summary = trim((string) ($result['summary'] ?? ''));
        $lines = array_values(array_filter(
            array_map('trim', array_map('strval', (array) ($result['lines'] ?? []))),
            static fn(string $line): bool => $line !== ''
        ));

        $blockLines = [
            'step: ' . $stepId,
            'action: ' . $action,
            'summary: ' . ($summary !== '' ? $summary : '-'),
            'details_count: ' . count($lines),
        ];

        foreach ($lines as $index => $line) {
            $blockLines[] = sprintf('%d. %s', $index + 1, $line);
        }

        $context = [
            'step' => $stepId,
            'action' => $action,
            'summary' => $summary,
            'lines_count' => count($lines),
        ];

        if (in_array($level, ['warning', 'error', 'danger'], true)) {
            $this->logStructuredReport('DB repair workflow step completed', $blockLines, $context, $level);
            return;
        }

        $this->logStructuredReport('DB repair workflow step completed', $blockLines, $context, 'info');
    }

    public function logAuditReport(array $report): void
    {
        $summary = (array) ($report['summary'] ?? []);
        $duplicateIndexes = (array) ($report['duplicate_indexes'] ?? []);
        $historicalTables = (array) ($report['historical_tables'] ?? []);
        $historicalMenuEntries = (array) ($report['historical_menu_entries'] ?? []);
        $tableEncodingIssues = (array) ($report['table_encoding_issues'] ?? []);
        $columnEncodingIssues = (array) ($report['column_encoding_issues'] ?? []);
        $mixedTableCollations = (array) ($report['mixed_table_collations'] ?? []);
        $missingAuditColumns = (array) ($report['missing_audit_columns'] ?? []);
        $pluginExtensionDuplicates = (array) ($report['plugin_extension_duplicates'] ?? []);
        $bfFieldSyncIssues = (array) ($report['bf_view_field_sync_issues'] ?? []);
        $menuViewIssues = (array) ($report['menu_view_issues'] ?? []);
        $frontendPermissionIssues = (array) ($report['frontend_permission_issues'] ?? []);
        $elementReferenceIssues = (array) ($report['element_reference_issues'] ?? []);
        $invalidDatetimeSortIssues = (array) ($report['invalid_datetime_sort_issues'] ?? []);
        $generatedArticleCategoryIssues = (array) ($report['generated_article_category_issues'] ?? []);
        $summaryLines = [
            'scanned_tables: ' . (int) ($report['scanned_tables'] ?? 0),
            'issues_total: ' . (int) ($summary['issues_total'] ?? 0),
            'table_encoding_issues: ' . (int) ($summary['table_encoding_issues'] ?? 0),
            'column_encoding_issues: ' . (int) ($summary['column_encoding_issues'] ?? 0),
            'mixed_table_collations: ' . (int) ($summary['mixed_table_collations'] ?? 0),
            'duplicate_index_groups: ' . (int) ($summary['duplicate_index_groups'] ?? 0),
            'duplicate_indexes_to_drop: ' . (int) ($summary['duplicate_indexes_to_drop'] ?? 0),
            'historical_tables: ' . (int) ($summary['historical_tables'] ?? 0),
            'historical_menu_entries: ' . (int) ($summary['historical_menu_entries'] ?? 0),
            'missing_audit_columns_total: ' . (int) ($summary['missing_audit_columns_total'] ?? 0),
            'plugin_duplicate_groups: ' . (int) ($summary['plugin_duplicate_groups'] ?? 0),
            'plugin_duplicate_rows_to_remove: ' . (int) ($summary['plugin_duplicate_rows_to_remove'] ?? 0),
            'bf_view_field_sync_views: ' . (int) ($summary['bf_view_field_sync_views'] ?? 0),
            'menu_view_issues: ' . (int) ($summary['menu_view_issues'] ?? 0),
            'frontend_permission_issues: ' . (int) ($summary['frontend_permission_issues'] ?? 0),
            'element_reference_issues: ' . (int) ($summary['element_reference_issues'] ?? 0),
            'invalid_datetime_sort_issues: ' . (int) ($summary['invalid_datetime_sort_issues'] ?? 0),
            'invalid_datetime_sort_rows: ' . (int) ($summary['invalid_datetime_sort_rows'] ?? 0),
            'generated_article_category_issues: ' . (int) ($summary['generated_article_category_issues'] ?? 0),
            'generated_article_category_rows: ' . (int) ($summary['generated_article_category_rows'] ?? 0),
        ];

        $this->logStructuredReport(
            'Database audit summary',
            $summaryLines,
            [
                'scanned_tables' => (int) ($report['scanned_tables'] ?? 0),
                'issues_total' => (int) ($summary['issues_total'] ?? 0),
            ]
        );

        $this->logStructuredSection('Database audit table collation issues', $tableEncodingIssues, static function (array $issue, int $index): ?string {
            $table = (string) ($issue['table'] ?? '');
            if ($table === '') {
                return null;
            }
            return sprintf('%d. table=%s current=%s expected=%s', $index + 1, $table, (string) ($issue['collation'] ?? ''), (string) ($issue['expected'] ?? ''));
        });

        $this->logStructuredSection('Database audit column collation issues', $columnEncodingIssues, static function (array $issue, int $index): ?string {
            $table = (string) ($issue['table'] ?? '');
            $column = (string) ($issue['column'] ?? '');
            if ($table === '' || $column === '') {
                return null;
            }
            return sprintf('%d. table=%s column=%s charset=%s collation=%s expected_charset=%s expected_collation=%s', $index + 1, $table, $column, (string) ($issue['charset'] ?? ''), (string) ($issue['collation'] ?? ''), (string) ($issue['expected_charset'] ?? ''), (string) ($issue['expected_collation'] ?? ''));
        });

        $this->logStructuredSection('Database audit mixed table collations', $mixedTableCollations, static function (array $item, int $index): ?string {
            $collation = (string) ($item['collation'] ?? '');
            if ($collation === '') {
                return null;
            }
            return sprintf('%d. collation=%s count=%d tables=%s', $index + 1, $collation, (int) ($item['count'] ?? 0), implode(', ', array_values((array) ($item['tables'] ?? []))));
        });

        $this->logStructuredSection('Database audit duplicate index groups', $duplicateIndexes, static function (array $group, int $index): ?string {
            $table = (string) ($group['table'] ?? '');
            if ($table === '') {
                return null;
            }
            return sprintf('%d. table=%s keep=%s drop=%s', $index + 1, $table, (string) ($group['keep'] ?? ''), implode(', ', array_values((array) ($group['drop'] ?? []))));
        });

        $this->logStructuredSection('Database audit historical tables', $historicalTables, static function (string|array $historicalTable, int $index): ?string {
            $table = trim((string) $historicalTable);
            return $table === '' ? null : sprintf('%d. %s', $index + 1, $table);
        });

        $this->logStructuredSection('Database audit historical menu entries', $historicalMenuEntries, static function (array $entry, int $index): ?string {
            $menuId = (int) ($entry['menu_id'] ?? 0);
            if ($menuId <= 0) {
                return null;
            }
            return sprintf('%d. menu_id=%d title=%s normalized_title=%s link=%s', $index + 1, $menuId, (string) ($entry['title'] ?? ''), (string) ($entry['normalized_title'] ?? ''), (string) ($entry['link'] ?? ''));
        });

        $this->logStructuredSection('Database audit missing audit columns', $missingAuditColumns, static function (array $issue, int $index): ?string {
            $table = (string) ($issue['table'] ?? '');
            if ($table === '') {
                return null;
            }
            return sprintf('%d. table=%s storage_id=%d storage_name=%s missing=%s', $index + 1, $table, (int) ($issue['storage_id'] ?? 0), (string) ($issue['storage_name'] ?? ''), implode(', ', array_values((array) ($issue['missing'] ?? []))));
        });

        $this->logStructuredSection('Database audit invalid DATETIME sort casts', $invalidDatetimeSortIssues, static function (array $issue, int $index): ?string {
            $formId = (int) ($issue['form_id'] ?? 0);
            if ($formId <= 0) {
                return null;
            }
            return sprintf('%d. form_id=%d form_name=%s storage_id=%d table=%s column=%s invalid_count=%d', $index + 1, $formId, (string) ($issue['form_name'] ?? ''), (int) ($issue['storage_id'] ?? 0), (string) ($issue['table'] ?? ''), (string) ($issue['column'] ?? ''), (int) ($issue['invalid_count'] ?? 0));
        });

        $this->logStructuredSection('Database audit plugin duplicate groups', $pluginExtensionDuplicates, static function (array $group, int $index): ?string {
            $folder = (string) ($group['canonical_folder'] ?? '');
            if ($folder === '') {
                return null;
            }
            return sprintf('%d. canonical_folder=%s canonical_element=%s keep_id=%d duplicate_ids=%s', $index + 1, $folder, (string) ($group['canonical_element'] ?? ''), (int) ($group['keep_id'] ?? 0), implode(', ', array_values((array) ($group['duplicate_ids'] ?? []))));
        });

        $this->logStructuredSection('Database audit BF field sync issues', $bfFieldSyncIssues, static function (array $issue, int $index): ?string {
            $formId = (int) ($issue['form_id'] ?? 0);
            if ($formId <= 0) {
                return null;
            }
            return sprintf('%d. form_id=%d form_name=%s source_name=%s missing_count=%d orphan_count=%d', $index + 1, $formId, (string) ($issue['form_name'] ?? ''), (string) ($issue['source_name'] ?? ''), (int) ($issue['missing_count'] ?? 0), (int) ($issue['orphan_count'] ?? 0));
        });

        $this->logStructuredSection('Database audit menu consistency issues', $menuViewIssues, static function (array $issue, int $index): ?string {
            $menuId = (int) ($issue['menu_id'] ?? 0);
            if ($menuId <= 0) {
                return null;
            }
            return sprintf('%d. menu_id=%d title=%s target=%s issues=%s', $index + 1, $menuId, (string) ($issue['title'] ?? ''), (string) ($issue['target'] ?? ''), implode(', ', array_values((array) ($issue['issues'] ?? []))));
        });

        $this->logStructuredSection('Database audit frontend permission issues', $frontendPermissionIssues, static function (array $issue, int $index): ?string {
            $formId = (int) ($issue['form_id'] ?? 0);
            if ($formId <= 0) {
                return null;
            }
            return sprintf('%d. form_id=%d form_name=%s issues=%s', $index + 1, $formId, (string) ($issue['form_name'] ?? ''), implode(', ', array_values((array) ($issue['issues'] ?? []))));
        });

        $this->logStructuredSection('Database audit element reference issues', $elementReferenceIssues, static function (array $issue, int $index): ?string {
            $formId = (int) ($issue['form_id'] ?? 0);
            if ($formId <= 0) {
                return null;
            }
            return sprintf('%d. form_id=%d form_name=%s type=%s empty_reference_ids=%s duplicate_reference_ids=%s orphan_reference_ids=%s', $index + 1, $formId, (string) ($issue['form_name'] ?? ''), (string) ($issue['type'] ?? ''), implode(', ', array_values((array) ($issue['empty_reference_ids'] ?? []))), implode(', ', array_values((array) ($issue['duplicate_reference_ids'] ?? []))), implode(', ', array_values((array) ($issue['orphan_reference_ids'] ?? []))));
        });

        $this->logStructuredSection('Database audit generated article category issues', $generatedArticleCategoryIssues, static function (array $issue, int $index): ?string {
            $formId = (int) ($issue['form_id'] ?? 0);
            if ($formId <= 0) {
                return null;
            }
            return sprintf('%d. form_id=%d form_name=%s default_category_id=%d default_category_valid=%d invalid_article_count=%d', $index + 1, $formId, (string) ($issue['form_name'] ?? ''), (int) ($issue['default_category_id'] ?? 0), !empty($issue['default_category_valid']) ? 1 : 0, (int) ($issue['invalid_article_count'] ?? 0));
        });
    }

    private function buildPrechecks(DatabaseInterface $db): array
    {
        $prechecks = [];

        try {
            $auditReport = DatabaseAuditHelper::run();
            $auditSummary = (array) ($auditReport['summary'] ?? []);

            $duplicateIndexGroups = (int) ($auditSummary['duplicate_index_groups'] ?? 0);
            $duplicateIndexesToDrop = (int) ($auditSummary['duplicate_indexes_to_drop'] ?? 0);
            $historicalTablesCount = (int) ($auditSummary['historical_tables'] ?? 0);
            $tableEncodingCount = (int) ($auditSummary['table_encoding_issues'] ?? 0);
            $columnEncodingCount = (int) ($auditSummary['column_encoding_issues'] ?? 0);
            $mixedCollationsCount = (int) ($auditSummary['mixed_table_collations'] ?? 0);
            $collationIssueCount = $tableEncodingCount + $columnEncodingCount + $mixedCollationsCount;
            $missingAuditColumnsTotal = (int) ($auditSummary['missing_audit_columns_total'] ?? 0);
            $missingAuditColumnsTables = (int) ($auditSummary['missing_audit_column_tables'] ?? 0);
            $missingFormAuditColumnsTotal = (int) ($auditSummary['missing_form_audit_columns_total'] ?? 0);
            $missingFormAuditColumnsTables = (int) ($auditSummary['missing_form_audit_column_tables'] ?? 0);
            $pluginDuplicateRows = (int) ($auditSummary['plugin_duplicate_rows_to_remove'] ?? 0);
            $pluginDuplicateGroups = (int) ($auditSummary['plugin_duplicate_groups'] ?? 0);
            $historicalMenuEntriesCount = (int) ($auditSummary['historical_menu_entries'] ?? 0);
            $bfFieldSyncViews = (int) ($auditSummary['bf_view_field_sync_views'] ?? 0);
            $bfMissingInCbTotal = (int) ($auditSummary['bf_view_field_sync_missing_in_cb'] ?? 0);
            $bfOrphanInCbTotal = (int) ($auditSummary['bf_view_field_sync_orphan_in_cb'] ?? 0);
            $menuViewIssues = (array) ($auditReport['menu_view_issues'] ?? []);
            $frontendPermissionIssues = (array) ($auditReport['frontend_permission_issues'] ?? []);
            $elementReferenceIssues = (array) ($auditReport['element_reference_issues'] ?? []);
            $generatedArticleCategoryIssues = (array) ($auditReport['generated_article_category_issues'] ?? []);
            $generatedArticleCategoryRows = (int) ($auditSummary['generated_article_category_rows'] ?? 0);
            $encodingTargetCollation = EncodingAuditHelper::resolveTargetCollation($db);

            $prechecks['duplicate_indexes'] = [
                'count' => $duplicateIndexesToDrop,
                'description' => match (true) {
                    $duplicateIndexesToDrop <= 0 => 'No duplicate index to remove was detected by the last audit.',
                    $duplicateIndexesToDrop === 1 => '1 duplicate index was detected in ' . max(1, $duplicateIndexGroups) . ' group and can be removed in this step.',
                    default => $duplicateIndexesToDrop . ' duplicate indexes were detected in ' . max(1, $duplicateIndexGroups) . ' groups and can be removed in this step.',
                },
                'skip_summary' => 'No duplicate index detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
            ];

            $prechecks['historical_tables'] = [
                'count' => $historicalTablesCount,
                'description' => match (true) {
                    $historicalTablesCount <= 0 => 'No historical table name was detected by the last audit.',
                    $historicalTablesCount === 1 => '1 historical table name was detected by the last audit and can be renamed in this step when the NG target table does not already exist.',
                    default => $historicalTablesCount . ' historical table names were detected by the last audit and can be renamed in this step when the NG target tables do not already exist.',
                },
                'skip_summary' => 'No historical table name detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
            ];

            $prechecks['table_encoding'] = [
                'count' => $collationIssueCount,
                'description' => match (true) {
                    $collationIssueCount <= 0 => Text::sprintf('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_ENCODING_PRECHECK_NONE', 'utf8mb4', $encodingTargetCollation),
                    default => Text::sprintf('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_ENCODING_PRECHECK', $collationIssueCount, $tableEncodingCount, $columnEncodingCount, $mixedCollationsCount, 'utf8mb4', $encodingTargetCollation),
                },
                'skip_summary' => 'No encoding/collation issue detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
            ];

            $prechecks['audit_columns'] = [
                'count' => $missingAuditColumnsTotal,
                'description' => match (true) {
                    $missingAuditColumnsTotal <= 0 => 'No missing audit column was detected by the last audit.',
                    $missingAuditColumnsTotal === 1 => '1 missing audit column was detected across ' . max(1, $missingAuditColumnsTables) . ' table and can be repaired in this step.',
                    default => $missingAuditColumnsTotal . ' missing audit columns were detected across ' . max(1, $missingAuditColumnsTables) . ' tables and can be repaired in this step.',
                },
                'skip_summary' => 'No missing audit column detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
            ];

            $prechecks['form_audit_columns'] = [
                'count' => $missingFormAuditColumnsTotal,
                'description' => match (true) {
                    $missingFormAuditColumnsTotal <= 0 => 'No missing form column was detected by the last audit.',
                    $missingFormAuditColumnsTotal === 1 => '1 missing form column was detected across ' . max(1, $missingFormAuditColumnsTables) . ' table and can be repaired in this step.',
                    default => $missingFormAuditColumnsTotal . ' missing form columns were detected across ' . max(1, $missingFormAuditColumnsTables) . ' tables and can be repaired in this step.',
                },
                'skip_summary' => 'No missing form column detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
            ];

            $prechecks['plugin_duplicates'] = [
                'count' => $pluginDuplicateRows,
                'description' => match (true) {
                    $pluginDuplicateRows <= 0 => 'No duplicate plugin row was detected by the last audit.',
                    $pluginDuplicateRows === 1 => '1 duplicate plugin row was detected in ' . max(1, $pluginDuplicateGroups) . ' group and can be removed in this step.',
                    default => $pluginDuplicateRows . ' duplicate plugin rows were detected in ' . max(1, $pluginDuplicateGroups) . ' groups and can be removed in this step.',
                },
                'skip_summary' => 'No duplicate plugin row detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
            ];

            $prechecks['historical_menu_entries'] = [
                'count' => $historicalMenuEntriesCount,
                'description' => match (true) {
                    $historicalMenuEntriesCount <= 0 => 'No historical menu entry was detected by the last audit.',
                    $historicalMenuEntriesCount === 1 => '1 historical menu entry was detected by the last audit and can be repaired in this step.',
                    default => $historicalMenuEntriesCount . ' historical menu entries were detected by the last audit and can be repaired in this step.',
                },
                'skip_summary' => 'No historical menu entry detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
            ];

            $prechecks['bf_field_sync'] = [
                'count' => $bfFieldSyncViews,
                'description' => match (true) {
                    $bfFieldSyncViews <= 0 => 'No BF-linked CB view synchronization issue was detected by the last audit.',
                    default => $bfFieldSyncViews . ' BF-linked CB views need manual review (' . $bfMissingInCbTotal . ' source fields missing in CB, ' . $bfOrphanInCbTotal . ' extra fields in CB). This diagnostic step does not perform an automatic repair.',
                },
                'skip_summary' => 'No BF-linked CB view synchronization issue detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
            ];

            $menuViewLines = [];
            foreach ($menuViewIssues as $menuViewIssue) {
                if (!is_array($menuViewIssue)) {
                    continue;
                }
                $menuId = (int) ($menuViewIssue['menu_id'] ?? 0);
                $menuTitle = trim((string) ($menuViewIssue['title'] ?? ''));
                $menuIssueText = implode(' | ', array_filter(array_map('strval', (array) ($menuViewIssue['issues'] ?? []))));
                $menuViewLines[] = 'Menu #' . $menuId . ($menuTitle !== '' ? ' (' . $menuTitle . ')' : '') . ': ' . $menuIssueText;
            }

            $prechecks['menu_view_consistency'] = [
                'count' => count($menuViewIssues),
                'description' => match (true) {
                    count($menuViewIssues) <= 0 => 'No menu -> view inconsistency was detected by the last audit.',
                    count($menuViewIssues) === 1 => '1 ContentBuilder menu points to an invalid or inconsistent target. This diagnostic step does not perform an automatic repair.',
                    default => count($menuViewIssues) . ' ContentBuilder menus point to invalid or inconsistent targets. This diagnostic step does not perform an automatic repair.',
                },
                'skip_summary' => '',
                'has_errors' => false,
                'mode' => 'diagnostic',
                'result' => [
                    'level' => count($menuViewIssues) > 0 ? 'warning' : 'message',
                    'summary' => match (true) {
                        count($menuViewIssues) <= 0 => 'No menu -> view inconsistency detected by the last audit.',
                        count($menuViewIssues) === 1 => '1 menu -> view inconsistency detected. Review the affected menu in the Audit section.',
                        default => count($menuViewIssues) . ' menu -> view inconsistencies detected. Review the affected menus in the Audit section.',
                    },
                    'lines' => $menuViewLines,
                ],
            ];

            $frontendPermissionLines = [];
            foreach ($frontendPermissionIssues as $frontendPermissionIssue) {
                if (!is_array($frontendPermissionIssue)) {
                    continue;
                }
                $formId = (int) ($frontendPermissionIssue['form_id'] ?? 0);
                $formName = trim((string) ($frontendPermissionIssue['form_name'] ?? ''));
                $issueText = implode(' | ', array_filter(array_map('strval', (array) ($frontendPermissionIssue['issues'] ?? []))));
                $frontendPermissionLines[] = 'View #' . $formId . ($formName !== '' ? ' (' . $formName . ')' : '') . ': ' . $issueText;
            }

            $prechecks['frontend_permission_consistency'] = [
                'count' => count($frontendPermissionIssues),
                'description' => match (true) {
                    count($frontendPermissionIssues) <= 0 => 'No frontend permission incoherence was detected by the last audit.',
                    count($frontendPermissionIssues) === 1 => '1 view has an incoherent frontend permission setup. This diagnostic step does not perform an automatic repair.',
                    default => count($frontendPermissionIssues) . ' views have an incoherent frontend permission setup. This diagnostic step does not perform an automatic repair.',
                },
                'skip_summary' => '',
                'has_errors' => false,
                'mode' => 'diagnostic',
                'result' => [
                    'level' => count($frontendPermissionIssues) > 0 ? 'warning' : 'message',
                    'summary' => match (true) {
                        count($frontendPermissionIssues) <= 0 => 'No frontend permission incoherence detected by the last audit.',
                        count($frontendPermissionIssues) === 1 => '1 frontend permission incoherence detected. Review the affected view in the Audit section.',
                        default => count($frontendPermissionIssues) . ' frontend permission incoherences detected. Review the affected views in the Audit section.',
                    },
                    'lines' => $frontendPermissionLines,
                ],
            ];

            $elementReferenceLines = [];
            foreach ($elementReferenceIssues as $elementReferenceIssue) {
                if (!is_array($elementReferenceIssue)) {
                    continue;
                }
                $formId = (int) ($elementReferenceIssue['form_id'] ?? 0);
                $formName = trim((string) ($elementReferenceIssue['form_name'] ?? ''));
                $issueParts = [];

                foreach ((array) ($elementReferenceIssue['empty_reference_ids'] ?? []) as $emptyReferenceId) {
                    $issueParts[] = 'empty reference_id on ' . trim((string) $emptyReferenceId);
                }
                foreach ((array) ($elementReferenceIssue['duplicate_reference_ids'] ?? []) as $duplicateReferenceId) {
                    if (!is_array($duplicateReferenceId)) {
                        continue;
                    }
                    $issueParts[] = 'duplicate reference_id ' . trim((string) ($duplicateReferenceId['reference_id'] ?? ''));
                }
                foreach ((array) ($elementReferenceIssue['orphan_reference_ids'] ?? []) as $orphanReferenceId) {
                    if (!is_array($orphanReferenceId)) {
                        continue;
                    }
                    $issueParts[] = 'orphan reference_id ' . trim((string) ($orphanReferenceId['reference_id'] ?? ''));
                }

                $elementReferenceLines[] = 'View #' . $formId . ($formName !== '' ? ' (' . $formName . ')' : '') . ': ' . implode(' | ', $issueParts);
            }

            $prechecks['element_reference_consistency'] = [
                'count' => count($elementReferenceIssues),
                'description' => match (true) {
                    count($elementReferenceIssues) <= 0 => 'No element reference_id incoherence was detected by the last audit.',
                    count($elementReferenceIssues) === 1 => '1 view has duplicate, empty, or orphan element reference_id values. This diagnostic step does not perform an automatic repair.',
                    default => count($elementReferenceIssues) . ' views have duplicate, empty, or orphan element reference_id values. This diagnostic step does not perform an automatic repair.',
                },
                'skip_summary' => '',
                'has_errors' => false,
                'mode' => 'diagnostic',
                'result' => [
                    'level' => count($elementReferenceIssues) > 0 ? 'warning' : 'message',
                    'summary' => match (true) {
                        count($elementReferenceIssues) <= 0 => 'No element reference_id incoherence detected by the last audit.',
                        count($elementReferenceIssues) === 1 => '1 element reference_id incoherence detected. Review the affected view in the Audit section.',
                        default => count($elementReferenceIssues) . ' element reference_id incoherences detected. Review the affected views in the Audit section.',
                    },
                    'lines' => $elementReferenceLines,
                ],
            ];

            $prechecks['generated_article_categories'] = [
                'count' => count($generatedArticleCategoryIssues),
                'description' => match (true) {
                    count($generatedArticleCategoryIssues) <= 0 => 'No generated article category issue was detected by the last audit.',
                    count($generatedArticleCategoryIssues) === 1 => '1 ContentBuilder view has an invalid generated article category setup and can be repaired in this step.',
                    default => count($generatedArticleCategoryIssues) . ' ContentBuilder views have invalid generated article category setups and can be repaired in this step.',
                },
                'skip_summary' => 'No generated article category issue detected by the pre-check. Skipped automatically.',
                'has_errors' => false,
                'details' => $generatedArticleCategoryRows . ' generated articles currently use an invalid category.',
            ];
        } catch (\Throwable $e) {
            foreach (['duplicate_indexes', 'historical_tables', 'historical_menu_entries', 'table_encoding', 'audit_columns', 'form_audit_columns', 'plugin_duplicates', 'bf_field_sync', 'menu_view_consistency', 'frontend_permission_consistency', 'element_reference_consistency', 'generated_article_categories'] as $stepId) {
                $prechecks[$stepId] = [
                    'count' => 1,
                    'description' => 'Pre-check unavailable for this step. You can still run the repair manually.',
                    'skip_summary' => '',
                    'has_errors' => true,
                ];
            }
        }

        try {
            $packedDataAudit = PackedDataMigrationHelper::auditPackedPayloadsStep($db);
            $packedDataCandidates = (int) ($packedDataAudit['candidates'] ?? 0);
            $packedDataErrors = (int) ($packedDataAudit['errors'] ?? 0);

            $prechecks['packed_data'] = [
                'count' => $packedDataCandidates,
                'description' => match (true) {
                    $packedDataErrors > 0 => 'Packed data pre-check reported ' . $packedDataErrors . ' errors. You can still run the repair manually.',
                    $packedDataCandidates <= 0 => 'No packed payload needing migration was detected by the pre-check.',
                    $packedDataCandidates === 1 => '1 packed payload needing migration was detected by the pre-check.',
                    default => $packedDataCandidates . ' packed payloads needing migration were detected by the pre-check.',
                },
                'skip_summary' => 'No packed payload needing migration was detected by the pre-check. Skipped automatically.',
                'has_errors' => $packedDataErrors > 0,
            ];
        } catch (\Throwable $e) {
            $prechecks['packed_data'] = [
                'count' => 1,
                'description' => 'Packed data pre-check unavailable. You can still run the repair manually.',
                'skip_summary' => '',
                'has_errors' => true,
            ];
        }

        return $prechecks;
    }

    private function buildDuplicateIndexStepResult(array $summary): array
    {
        $scanned = (int) ($summary['scanned'] ?? 0);
        $issues = (int) ($summary['issues'] ?? 0);
        $repaired = (int) ($summary['repaired'] ?? 0);
        $unchanged = (int) ($summary['unchanged'] ?? 0);
        $errors = (int) ($summary['errors'] ?? 0);
        $dropped = (int) ($summary['dropped'] ?? 0);
        $lines = [];

        foreach ((array) ($summary['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }
            $table = trim((string) ($group['table'] ?? ''));
            $keep = trim((string) ($group['keep'] ?? ''));
            $drop = implode(', ', (array) ($group['drop'] ?? []));
            $removed = implode(', ', (array) ($group['removed'] ?? []));
            $status = (string) ($group['status'] ?? '');
            $error = trim((string) ($group['error'] ?? ''));
            $line = $table . ' [' . $status . '] keep=' . $keep . ' drop=[' . $drop . ']';
            if ($removed !== '') {
                $line .= ' removed=[' . $removed . ']';
            }
            if ($error !== '') {
                $line .= ' error=' . $error;
            }
            $lines[] = $line;
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = 'Warning: ' . $warning;
            }
        }

        return [
            'level' => $errors > 0 ? 'warning' : 'message',
            'summary' => 'Duplicate index cleanup: scanned ' . $scanned . ' groups, issues ' . $issues . ', repaired ' . $repaired . ', unchanged ' . $unchanged . ', dropped indexes ' . $dropped . ', errors ' . $errors . '.',
            'lines' => $lines,
        ];
    }

    private function buildHistoricalTablesStepResult(array $summary): array
    {
        $scanned = (int) ($summary['scanned'] ?? 0);
        $issues = (int) ($summary['issues'] ?? 0);
        $repaired = (int) ($summary['repaired'] ?? 0);
        $unchanged = (int) ($summary['unchanged'] ?? 0);
        $errors = (int) ($summary['errors'] ?? 0);
        $lines = [];

        foreach ((array) ($summary['renames'] ?? []) as $rename) {
            if (!is_array($rename)) {
                continue;
            }
            $line = trim((string) ($rename['from'] ?? '')) . ' -> ' . trim((string) ($rename['to'] ?? '')) . ' [' . trim((string) ($rename['status'] ?? '')) . ']';
            $error = trim((string) ($rename['error'] ?? ''));
            if ($error !== '') {
                $line .= ' ' . $error;
            }
            $lines[] = $line;
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = 'Warning: ' . $warning;
            }
        }

        return [
            'level' => $errors > 0 ? 'warning' : 'message',
            'summary' => 'Historical table rename: scanned ' . $scanned . ' tables, issues ' . $issues . ', repaired ' . $repaired . ', unchanged ' . $unchanged . ', errors ' . $errors . '.',
            'lines' => $lines,
        ];
    }

    private function buildTableEncodingStepResult(array $summary): array
    {
        $supported = (bool) ($summary['supported'] ?? false);
        $target = (string) ($summary['target_collation'] ?? 'utf8mb4_0900_ai_ci');
        $nativeTarget = 'utf8mb4_0900_ai_ci';
        $scanned = (int) ($summary['scanned'] ?? 0);
        $issues = (int) ($summary['issues'] ?? 0);
        $tableIssues = (int) ($summary['table_issues'] ?? 0);
        $columnIssues = (int) ($summary['column_issues'] ?? 0);
        $mixedCollationGroups = (int) ($summary['mixed_collation_groups'] ?? 0);
        $converted = (int) ($summary['converted'] ?? 0);
        $unchanged = (int) ($summary['unchanged'] ?? 0);
        $errors = (int) ($summary['errors'] ?? 0);
        $lines = [];

        if (!$supported) {
            $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_COLLATION_REPAIR_UNSUPPORTED', $nativeTarget, $target);
            return [
                'level' => 'warning',
                'summary' => Text::sprintf('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_ENCODING_SUMMARY_DETAIL', $scanned, $tableIssues, $columnIssues, $mixedCollationGroups, $converted, $unchanged, $errors, 'utf8mb4', $target),
                'lines' => $lines,
            ];
        }

        if ($target !== $nativeTarget) {
            $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_COLLATION_REPAIR_FALLBACK', $nativeTarget, $target);
        }

        foreach ((array) ($summary['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }
            $from = trim((string) ($table['from'] ?? ''));
            if ($from === '') {
                $from = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            }
            $status = (string) ($table['status'] ?? '');
            $statusLabel = $status !== '' ? $status : 'unknown';

            if ($status === 'converted') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_COLLATION_REPAIR_TABLE_CONVERTED_DETAIL', (string) ($table['table'] ?? ''), $from, (string) ($table['to'] ?? $target), (int) ($table['table_issues'] ?? 0), (int) ($table['column_issues'] ?? 0));
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_COLLATION_REPAIR_TABLE_ERROR_DETAIL', (string) ($table['table'] ?? ''), $from, (string) ($table['to'] ?? $target), (int) ($table['table_issues'] ?? 0), (int) ($table['column_issues'] ?? 0), (string) ($table['error'] ?? ''));
            } else {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_COLLATION_REPAIR_TABLE_STATUS_DETAIL', (string) ($table['table'] ?? ''), $from, (string) ($table['to'] ?? $target), $statusLabel, (int) ($table['table_issues'] ?? 0), (int) ($table['column_issues'] ?? 0));
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_COLLATION_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => $errors > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_ENCODING_SUMMARY_DETAIL', $scanned, $tableIssues, $columnIssues, $mixedCollationGroups, $converted, $unchanged, $errors, 'utf8mb4', $target),
            'lines' => $lines,
        ];
    }

    private function buildPackedDataStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }
            $tableName = (string) ($table['table'] ?? '');
            $tableColumn = (string) ($table['column'] ?? '');
            $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_PACKED_MIGRATION_TABLE_SUMMARY', $tableName, $tableColumn, (int) ($table['scanned'] ?? 0), (int) ($table['candidates'] ?? 0), (int) ($table['migrated'] ?? 0), (int) ($table['unchanged'] ?? 0), (int) ($table['errors'] ?? 0));

            foreach ((array) ($table['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rowStatus = (string) ($row['status'] ?? '');
                $rowStatusLabelKey = match ($rowStatus) {
                    'migrated' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ROW_STATUS_MIGRATED',
                    'unchanged' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ROW_STATUS_UNCHANGED',
                    'error' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ROW_STATUS_ERROR',
                    default => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_ROW_STATUS_UNCHANGED',
                };
                $payloadType = (string) ($row['payload_type'] ?? '');
                $payloadTypeLabelKey = match ($payloadType) {
                    'json' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_FORMAT_JSON',
                    'legacy_php' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_FORMAT_LEGACY_PHP',
                    default => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_FORMAT_INVALID',
                };
                $rowError = trim((string) ($row['error'] ?? ''));
                $rowErrorSuffix = $rowError !== '' ? '; error=' . $rowError : '';
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_PACKED_MIGRATION_ROW_DETAIL', $tableName, $tableColumn, (int) ($row['record_id'] ?? 0), (string) ($row['record_label'] ?? ''), (string) ($row['form_label'] ?? ''), Text::_($payloadTypeLabelKey), Text::_($rowStatusLabelKey), $rowErrorSuffix);
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_SUMMARY', (int) ($summary['scanned'] ?? 0), (int) ($summary['candidates'] ?? 0), (int) ($summary['migrated'] ?? 0), (int) ($summary['unchanged'] ?? 0), (int) ($summary['errors'] ?? 0)),
            'lines' => $lines,
        ];
    }

    private function buildAuditColumnsStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }
            $status = (string) ($table['status'] ?? '');
            $missing = (array) ($table['missing'] ?? []);
            $added = (array) ($table['added'] ?? []);
            $missingLabel = $missing !== [] ? implode(', ', $missing) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $addedLabel = $added !== [] ? implode(', ', $added) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');

            if ($status === 'repaired') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_REPAIRED', (string) ($table['table'] ?? ''), $missingLabel, $addedLabel);
            } elseif ($status === 'partial') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_PARTIAL', (string) ($table['table'] ?? ''), $missingLabel, $addedLabel, (string) ($table['error'] ?? ''));
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_ERROR', (string) ($table['table'] ?? ''), $missingLabel, (string) ($table['error'] ?? ''));
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_SUMMARY', (int) ($summary['scanned'] ?? 0), (int) ($summary['issues'] ?? 0), (int) ($summary['repaired'] ?? 0), (int) ($summary['unchanged'] ?? 0), (int) ($summary['errors'] ?? 0)),
            'lines' => $lines,
        ];
    }

    private function buildFormAuditColumnsStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }
            $status = (string) ($table['status'] ?? '');
            $missing = (array) ($table['missing'] ?? []);
            $added = (array) ($table['added'] ?? []);
            $missingLabel = $missing !== [] ? implode(', ', $missing) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $addedLabel = $added !== [] ? implode(', ', $added) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');

            if ($status === 'repaired') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_FORM_AUDIT_COLUMNS_REPAIR_TABLE_REPAIRED', (string) ($table['table'] ?? ''), $missingLabel, $addedLabel);
            } elseif ($status === 'partial') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_FORM_AUDIT_COLUMNS_REPAIR_TABLE_PARTIAL', (string) ($table['table'] ?? ''), $missingLabel, $addedLabel, (string) ($table['error'] ?? ''));
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_FORM_AUDIT_COLUMNS_REPAIR_TABLE_ERROR', (string) ($table['table'] ?? ''), $missingLabel, (string) ($table['error'] ?? ''));
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_FORM_AUDIT_COLUMNS_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf('COM_CONTENTBUILDERNG_FORM_AUDIT_COLUMNS_REPAIR_SUMMARY', (int) ($summary['scanned'] ?? 0), (int) ($summary['issues'] ?? 0), (int) ($summary['repaired'] ?? 0), (int) ($summary['unchanged'] ?? 0), (int) ($summary['errors'] ?? 0)),
            'lines' => $lines,
        ];
    }

    private function buildPluginDuplicateStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }
            $canonicalFolder = trim((string) ($group['canonical_folder'] ?? ''));
            $canonicalElement = trim((string) ($group['canonical_element'] ?? ''));
            $canonicalLabel = $canonicalFolder !== '' || $canonicalElement !== ''
                ? $canonicalFolder . '/' . $canonicalElement
                : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $removedIds = array_values(array_map(static fn($id): int => (int) $id, (array) ($group['removed_ids'] ?? [])));
            $removedLabel = $removedIds !== [] ? implode(', ', $removedIds) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $status = (string) ($group['status'] ?? '');

            if ($status === 'repaired') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_GROUP_REPAIRED', $canonicalLabel, (int) ($group['keep_id'] ?? 0), $removedLabel);
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_GROUP_ERROR', $canonicalLabel, (int) ($group['keep_id'] ?? 0), $removedLabel, (string) ($group['error'] ?? ''));
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf('COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_SUMMARY', (int) ($summary['scanned'] ?? 0), (int) ($summary['issues'] ?? 0), (int) ($summary['repaired'] ?? 0), (int) ($summary['unchanged'] ?? 0), (int) ($summary['rows_removed'] ?? 0), (int) ($summary['errors'] ?? 0)),
            'lines' => $lines,
        ];
    }

    private function buildHistoricalMenuStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['entries'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $oldTitle = trim((string) ($entry['old_title'] ?? ''));
            $newTitle = trim((string) ($entry['new_title'] ?? ''));
            $oldTitle = $oldTitle !== '' ? $oldTitle : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $newTitle = $newTitle !== '' ? $newTitle : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $status = (string) ($entry['status'] ?? '');

            if ($status === 'repaired') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_HISTORICAL_MENU_REPAIR_ENTRY_REPAIRED', (int) ($entry['menu_id'] ?? 0), $oldTitle, $newTitle);
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_HISTORICAL_MENU_REPAIR_ENTRY_ERROR', (int) ($entry['menu_id'] ?? 0), $oldTitle, $newTitle, (string) ($entry['error'] ?? ''));
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_HISTORICAL_MENU_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf('COM_CONTENTBUILDERNG_HISTORICAL_MENU_REPAIR_SUMMARY', (int) ($summary['scanned'] ?? 0), (int) ($summary['issues'] ?? 0), (int) ($summary['repaired'] ?? 0), (int) ($summary['unchanged'] ?? 0), (int) ($summary['errors'] ?? 0)),
            'lines' => $lines,
        ];
    }

    private function buildBfFieldSyncStepResult(array $auditReport): array
    {
        $issues = (array) ($auditReport['bf_view_field_sync_issues'] ?? []);
        $summary = (array) ($auditReport['summary'] ?? []);
        $views = (int) ($summary['bf_view_field_sync_views'] ?? count($issues));
        $missing = (int) ($summary['bf_view_field_sync_missing_in_cb'] ?? 0);
        $orphan = (int) ($summary['bf_view_field_sync_orphan_in_cb'] ?? 0);
        $lines = [
            'Diagnostic only. No automatic repair is available for BF field synchronization.',
            'Review the impacted CB views in index.php?option=com_contentbuilderng&view=forms',
            'Review storage mappings in index.php?option=com_contentbuilderng&view=storages when the issue is storage-related.',
        ];

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $lines[] = 'View #' . (int) ($issue['form_id'] ?? 0)
                . ' "' . trim((string) ($issue['form_name'] ?? '')) . '"'
                . ' missing=' . (int) ($issue['missing_count'] ?? 0)
                . ' extra=' . (int) ($issue['orphan_count'] ?? 0);
        }

        return [
            'level' => $views > 0 ? 'warning' : 'message',
            'summary' => $views > 0
                ? 'BF field sync diagnostic: ' . $views . ' views require manual review (' . $missing . ' source fields missing in CB, ' . $orphan . ' extra fields in CB).'
                : 'No BF field synchronization issue detected.',
            'lines' => $lines,
        ];
    }

    private function buildGeneratedArticleCategoryStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['forms'] ?? []) as $form) {
            if (!is_array($form)) {
                continue;
            }

            $line = 'View #' . (int) ($form['form_id'] ?? 0)
                . ' "' . trim((string) ($form['form_name'] ?? '')) . '"'
                . ' [' . (string) ($form['status'] ?? '') . ']'
                . ' category ' . (int) ($form['from_category_id'] ?? 0)
                . ' -> ' . (int) ($form['to_category_id'] ?? 0)
                . ', form_updated=' . (!empty($form['form_updated']) ? 'yes' : 'no')
                . ', articles_updated=' . (int) ($form['articles_updated'] ?? 0);
            $error = trim((string) ($form['error'] ?? ''));
            if ($error !== '') {
                $line .= ', error=' . $error;
            }
            $lines[] = $line;
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = 'Warning: ' . $warning;
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf(
                'COM_CONTENTBUILDERNG_GENERATED_ARTICLE_CATEGORIES_REPAIR_SUMMARY',
                (int) ($summary['scanned'] ?? 0),
                (int) ($summary['issues'] ?? 0),
                (int) ($summary['repaired'] ?? 0),
                (int) ($summary['unchanged'] ?? 0),
                (int) ($summary['forms_updated'] ?? 0),
                (int) ($summary['articles_updated'] ?? 0),
                (int) ($summary['errors'] ?? 0)
            ),
            'lines' => $lines,
        ];
    }

    private function logStructuredReport(string $title, array $lines, array $context = [], string $level = 'info'): void
    {
        $message = $title;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $message .= "\n- " . $line;
        }

        match ($level) {
            'warning', 'warn' => Logger::warning($message, $context),
            'error', 'danger' => Logger::error($message, $context),
            default => Logger::info($message, $context),
        };
    }

    /**
     * @param array<int, mixed> $items
     * @param callable(array<mixed>, int): ?string $formatter
     */
    private function logStructuredSection(string $title, array $items, callable $formatter): void
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $line = $formatter($item, $index);
            if (is_string($line) && trim($line) !== '') {
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            return;
        }

        $this->logStructuredReport($title, $lines);
    }

    private function localNow(): string
    {
        $app = Factory::getApplication();
        $offset = is_object($app) && method_exists($app, 'get') ? (string) $app->get('offset', 'UTC') : 'UTC';

        try {
            $timezone = new \DateTimeZone($offset !== '' ? $offset : 'UTC');
        } catch (\Throwable) {
            $timezone = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
    }
}
