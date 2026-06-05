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

use CB\Component\Contentbuilderng\Administrator\Helper\Audit\BfFieldSyncAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\AuditTableSupportHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\DatabaseAuditReportBuilder;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\DuplicateIndexAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\ElementReferenceAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\EncodingAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\FrontendPermissionAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\GeneratedArticleCategoryAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\HistoricalAssetAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\InvalidDatetimeSortAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\MenuViewAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\FormDisplayColumnsHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class DatabaseAuditHelper
{

    /**
     * @return array{
     *   generated_at:string,
     *   scanned_tables:int,
     *   tables:array<int,string>,
     *   duplicate_indexes:array<int,array{table:string,indexes:array<int,string>,keep:string,drop:array<int,string>}>,
     *   historical_tables:array<int,string>,
     *   historical_menu_entries:array<int,array{
     *     menu_id:int,
     *     title:string,
     *     normalized_title:string,
     *     alias:string,
     *     path:string,
     *     link:string,
     *     parent_id:int
     *   }>,
     *   table_encoding_issues:array<int,array{table:string,collation:string,expected:string}>,
     *   packed_data_audit:array{
     *     scanned:int,
     *     candidates:int,
     *     migrated:int,
     *     unchanged:int,
     *     errors:int,
     *     tables:array<int,array{
     *       table:string,
     *       column:string,
     *       scanned:int,
     *       candidates:int,
     *       migrated:int,
     *       unchanged:int,
     *       errors:int,
     *       rows:array<int,array{
     *         record_id:int,
     *         record_label:string,
     *         form_id:int,
     *         form_label:string,
     *         status:string,
     *         error:string
     *       }>
     *     }>
     *   },
     *   column_encoding_issues:array<int,array{table:string,column:string,charset:string,collation:string}>,
     *   mixed_table_collations:array<int,array{collation:string,count:int,tables:array<int,string>}>,
     *   missing_audit_columns_scanned:int,
     *   missing_audit_columns:array<int,array{
     *     table:string,
     *     storage_id:int,
     *     storage_name:string,
     *     bytable:int,
     *     missing:array<int,string>
     *   }>,
     *   missing_form_audit_columns_scanned:int,
     *   missing_form_audit_columns:array<int,array{
     *     table:string,
     *     missing:array<int,string>
     *   }>,
     *   plugin_extension_duplicates:array<int,array{
     *     canonical_folder:string,
     *     canonical_element:string,
     *     keep_id:int,
     *     duplicate_ids:array<int,int>,
     *     rows:array<int,array{
     *       extension_id:int,
     *       folder:string,
     *       element:string,
     *       enabled:int,
     *       is_canonical:int
     *     }>
     *   }>,
     *   bf_view_field_sync_issues:array<int,array{
     *     form_id:int,
     *     form_name:string,
     *     type:string,
     *     reference_id:int,
     *     source_name:string,
     *     source_exists:int,
     *     source_total:int,
     *     cb_total:int,
     *     missing_count:int,
     *     orphan_count:int,
     *     missing_in_cb:array<int,string>,
     *     orphan_in_cb:array<int,string>
     *   }>,
     *   menu_view_issues:array<int,array{
     *     menu_id:int,
     *     title:string,
     *     access:int,
     *     link:string,
     *     target:string,
     *     issues:array<int,string>
     *   }>,
     *   frontend_permission_issues:array<int,array{
     *     form_id:int,
     *     form_name:string,
     *     issues:array<int,string>
     *   }>,
     *   element_reference_issues:array<int,array{
     *     form_id:int,
     *     form_name:string,
     *     type:string,
     *     reference_id:int,
     *     empty_reference_ids:array<int,string>,
     *     duplicate_reference_ids:array<int,array{reference_id:string,count:int,labels:array<int,string>}>,
     *     orphan_reference_ids:array<int,array{reference_id:string,label:string}>
     *   }>,
     *   invalid_datetime_sort_issues:array<int,array{
     *     form_id:int,
     *     form_name:string,
     *     storage_id:int,
     *     storage_name:string,
     *     table:string,
     *     element_id:int,
     *     element_label:string,
     *     reference_id:int,
     *     column:string,
     *     invalid_count:int,
     *     sample_values:array<int,string>
     *   }>,
     *   cb_tables:array{
     *     summary:array{
     *       tables_total:int,
     *       tables_ng_total:int,
     *       tables_ng_expected:int,
     *       tables_ng_present:int,
     *       tables_ng_missing:int,
     *       tables_historical_total:int,
     *       tables_storage_total:int,
     *       rows_total:int,
     *       data_bytes_total:int,
     *       index_bytes_total:int,
     *       size_bytes_total:int
     *     },
     *     missing_ng_tables:array<int,string>,
     *     tables:array<int,array{
     *       table:string,
     *       rows:int,
     *       data_bytes:int,
     *       index_bytes:int,
     *       size_bytes:int,
     *       engine:string,
     *       collation:string
     *     }>
     *   },
     *   summary:array{
     *     duplicate_index_groups:int,
     *     duplicate_indexes_to_drop:int,
     *     historical_tables:int,
     *     historical_menu_entries:int,
     *     table_encoding_issues:int,
     *     packed_data_candidates:int,
     *     column_encoding_issues:int,
     *     mixed_table_collations:int,
     *     missing_audit_column_tables:int,
     *     missing_audit_columns_total:int,
     *     plugin_duplicate_groups:int,
     *     plugin_duplicate_rows_to_remove:int,
     *     bf_view_field_sync_views:int,
     *     bf_view_field_sync_missing_in_cb:int,
     *     bf_view_field_sync_orphan_in_cb:int,
     *     menu_view_issues:int,
     *     frontend_permission_issues:int,
     *     element_reference_issues:int,
     *     invalid_datetime_sort_issues:int,
     *     invalid_datetime_sort_rows:int,
     *     issues_total:int
     *   },
     *   errors:array<int,string>
     * }
     */
    public static function run(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $prefix = $db->getPrefix();
        $errors = [];
        $getTableIndexes = [AuditTableSupportHelper::class, 'getTableIndexes'];
        $toAlias = [AuditTableSupportHelper::class, 'toAlias'];

        $tables = AuditTableSupportHelper::collectAuditableTables($db, $errors);
        $encodingTargetCollation = EncodingAuditHelper::resolveTargetCollation($db);

        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        [$duplicateIndexes, $duplicateErrors] = DuplicateIndexAuditHelper::find($db, $tables, $prefix, $getTableIndexes, $toAlias);
        $errors = array_merge($errors, $duplicateErrors);

        $historicalTables = HistoricalAssetAuditHelper::findLegacyContentbuilderTables($tables, $prefix, $toAlias);
        [$historicalMenuEntries, $historicalMenuErrors] = HistoricalAssetAuditHelper::findLegacyMenuEntries($db);
        $errors = array_merge($errors, $historicalMenuErrors);
        [$cbTableStats, $cbTableStatsErrors] = HistoricalAssetAuditHelper::collectCbTableStats($db, $tables, $prefix, $historicalTables, $toAlias);
        $errors = array_merge($errors, $cbTableStatsErrors);

        [$tableEncodingIssues, $columnEncodingIssues, $mixedTableCollations, $encodingErrors] =
            EncodingAuditHelper::inspect($db, $tables, $prefix, $toAlias);
        $errors = array_merge($errors, $encodingErrors);
        $packedDataAudit = PackedDataMigrationHelper::auditPackedPayloadsStep($db);

        $auditColumnsSummary = StorageAuditColumnsHelper::audit($db);
        $missingAuditColumns = (array) ($auditColumnsSummary['issues'] ?? []);
        $errors = array_merge($errors, (array) ($auditColumnsSummary['warnings'] ?? []));
        $formAuditColumnsSummary = FormDisplayColumnsHelper::audit($db);
        $missingFormAuditColumns = (array) ($formAuditColumnsSummary['issues'] ?? []);
        $errors = array_merge($errors, (array) ($formAuditColumnsSummary['warnings'] ?? []));
        $pluginDuplicatesSummary = PluginExtensionDedupHelper::audit($db);
        $pluginExtensionDuplicates = (array) ($pluginDuplicatesSummary['groups'] ?? []);
        $errors = array_merge($errors, (array) ($pluginDuplicatesSummary['warnings'] ?? []));
        [$bfFieldSyncIssues, $bfFieldSyncErrors] = BfFieldSyncAuditHelper::inspect($db);
        $errors = array_merge($errors, $bfFieldSyncErrors);
        [$menuViewIssues, $menuViewErrors] = MenuViewAuditHelper::inspect($db);
        $errors = array_merge($errors, $menuViewErrors);
        [$frontendPermissionIssues, $permissionErrors] = FrontendPermissionAuditHelper::inspect($db);
        $errors = array_merge($errors, $permissionErrors);
        [$elementReferenceIssues, $elementReferenceErrors] = ElementReferenceAuditHelper::inspect($db);
        $errors = array_merge($errors, $elementReferenceErrors);
        [$invalidDatetimeSortIssues, $invalidDatetimeSortErrors] = InvalidDatetimeSortAuditHelper::inspect($db);
        $errors = array_merge($errors, $invalidDatetimeSortErrors);
        [$generatedArticleCategoryIssues, $generatedArticleCategoryErrors] = GeneratedArticleCategoryAuditHelper::inspect($db);
        $errors = array_merge($errors, $generatedArticleCategoryErrors);

        return DatabaseAuditReportBuilder::build([
            'tables' => $tables,
            'prefix' => $prefix,
            'encoding_target_charset' => 'utf8mb4',
            'encoding_target_collation' => $encodingTargetCollation,
            'duplicate_indexes' => $duplicateIndexes,
            'historical_tables' => $historicalTables,
            'historical_menu_entries' => $historicalMenuEntries,
            'table_encoding_issues' => $tableEncodingIssues,
            'packed_data_audit' => $packedDataAudit,
            'column_encoding_issues' => $columnEncodingIssues,
            'mixed_table_collations' => $mixedTableCollations,
            'missing_audit_columns_scanned' => (int) ($auditColumnsSummary['scanned'] ?? 0),
            'missing_audit_columns' => $missingAuditColumns,
            'plugin_extension_duplicates' => $pluginExtensionDuplicates,
            'bf_view_field_sync_issues' => $bfFieldSyncIssues,
            'menu_view_issues' => $menuViewIssues,
            'frontend_permission_issues' => $frontendPermissionIssues,
            'element_reference_issues' => $elementReferenceIssues,
            'invalid_datetime_sort_issues' => $invalidDatetimeSortIssues,
            'generated_article_category_issues' => $generatedArticleCategoryIssues,
            'cb_tables' => $cbTableStats,
            'errors' => $errors,
        ], $toAlias);
    }
}
