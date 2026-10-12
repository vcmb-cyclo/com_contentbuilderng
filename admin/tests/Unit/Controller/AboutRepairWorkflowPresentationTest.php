<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

final class AboutRepairWorkflowPresentationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testWorkflowIsReplacedByDirectAuditRepairs(): void
    {
        $controller = (string) file_get_contents($this->root . '/admin/src/Controller/AboutController.php');
        $view = (string) file_get_contents($this->root . '/admin/src/View/About/HtmlView.php');
        $template = (string) file_get_contents($this->root . '/admin/tmpl/about/audit_report.php');
        $defaultTemplate = (string) file_get_contents($this->root . '/admin/tmpl/about/default.php');
        $buttonLayout = (string) file_get_contents($this->root . '/admin/layouts/about/audit_repair_button.php');

        self::assertStringContainsString('public function repairAuditIssue(): void', $controller);
        self::assertStringNotContainsString('startRepairWorkflow', $controller);
        self::assertStringNotContainsString('about.startRepairWorkflow', $view);
        self::assertStringNotContainsString('repair_workflow.php', $defaultTemplate);
        self::assertStringContainsString('data-cb-audit-ajax-task="about.repairAuditIssue"', $buttonLayout);

        foreach ([
            'duplicate_indexes',
            'historical_tables',
            'historical_menu_entries',
            'table_encoding',
            'packed_data',
            'audit_columns',
            'form_audit_columns',
            'plugin_duplicates',
            'bf_field_sync',
            'element_reference_consistency',
            'content_record_duplicates',
            'bf_content_record_orphans',
            'stale_language_files',
        ] as $issue) {
            self::assertStringContainsString("'issue' => '" . $issue . "'", $template);
        }
    }

    public function testAuditRepairMessagesRemainAlignedAcrossSupportedLanguages(): void
    {
        $expectedFragments = [
            'en-GB' => ['Audit repair completed', 'Audit repair failed'],
            'fr-FR' => ['Réparation de l’audit terminée', 'Échec de la réparation de l’audit'],
            'de-DE' => ['Audit-Reparatur abgeschlossen', 'Audit-Reparatur fehlgeschlagen'],
        ];

        foreach ($expectedFragments as $language => $fragments) {
            $translations = (string) file_get_contents(
                $this->root . '/admin/language/' . $language . '/com_contentbuilderng.ini'
            );

            foreach ($fragments as $fragment) {
                self::assertStringContainsString($fragment, $translations);
            }
        }
    }

    public function testUnknownTemplateMarkersExposeATargetedRepair(): void
    {
        $auditService = (string) file_get_contents($this->root . '/admin/src/Service/FormAuditService.php');
        $formSupportService = (string) file_get_contents($this->root . '/admin/src/Service/FormSupportService.php');
        $controller = (string) file_get_contents($this->root . '/admin/src/Controller/AboutController.php');
        $template = (string) file_get_contents($this->root . '/admin/tmpl/about/audit_report.php');
        $script = (string) file_get_contents($this->root . '/media/js/admin-about.js');

        self::assertStringContainsString("'code' => 'unknown_marker_details'", $auditService);
        self::assertStringContainsString("'code' => 'unknown_marker_edit'", $auditService);
        self::assertStringContainsString('removeUnknownTemplateMarker', $formSupportService);
        self::assertStringContainsString('public function repairFormUnknownMarker(): void', $controller);
        self::assertStringContainsString('about.repairFormUnknownMarker', $template);
        self::assertStringContainsString('data-cb-audit-ajax-marker-name', $template);
        self::assertStringContainsString("formData.set('marker_name', markerName)", $script);
        self::assertStringContainsString("formData.set('template_type', templateType)", $script);

        $expectedLabels = [
            'en-GB' => ['Unknown marker repair failed'],
            'fr-FR' => ['Échec de la réparation du marqueur inconnu'],
            'de-DE' => ['Reparatur des unbekannten Markers fehlgeschlagen'],
        ];

        foreach ($expectedLabels as $language => $labels) {
            $translations = (string) file_get_contents(
                $this->root . '/admin/language/' . $language . '/com_contentbuilderng.ini'
            );

            foreach ($labels as $label) {
                self::assertStringContainsString($label, $translations, $language);
            }
        }
    }

    public function testAuditSectionVisualOrderMatchesSummaryOrder(): void
    {
        $template = (string) file_get_contents($this->root . '/admin/tmpl/about/audit_report.php');
        $expectedOrder = [
            'duplicate_indexes' => 2,
            'legacy_storage_indexes' => 4,
            'upload_directory_protection' => 5,
            'historical_tables' => 6,
            'historical_menu_entries' => 7,
            'table_encoding' => 8,
            'packed_data' => 9,
            'column_encoding' => 10,
            'mixed_collations' => 11,
            'audit_columns' => 12,
            'form_audit_columns' => 14,
            'invalid_datetime_sort' => 16,
            'storage_column_types' => 18,
            'plugin_duplicates' => 19,
            'bf_field_sync' => 21,
            'menu_view_consistency' => 24,
            'frontend_permission_consistency' => 25,
            'element_reference_consistency' => 26,
            'form_audits' => 27,
            'content_record_duplicates' => 28,
            'bf_content_record_orphans' => 29,
            'generated_article_categories' => 32,
            'debug_mode' => 34,
            'stale_language_files' => 35,
            'stale_installer_temp' => 36,
            'cb_table_stats' => 37,
            'cb_ng_tables_missing' => 40,
            'errors' => 44,
        ];

        foreach ($expectedOrder as $sectionId => $order) {
            $pattern = '/getAuditSectionHeadingId\(\'' . preg_quote($sectionId, '/') . '\'\).*?style="order: (\d+);"/s';
            self::assertSame(
                1,
                preg_match($pattern, $template, $matches),
                $sectionId . ' must define a visual order.'
            );
            self::assertSame((string) $order, $matches[1], $sectionId . ' is out of order.');
        }
    }

    public function testLegacyStorageIndexesAreAuditedAndRepairedOnDemand(): void
    {
        $service = (string) file_get_contents($this->root . '/admin/src/Service/DatatableService.php');
        $auditHelper = (string) file_get_contents($this->root . '/admin/src/Helper/Audit/StorageIndexNamingAuditHelper.php');
        $audit = (string) file_get_contents($this->root . '/admin/src/Helper/DatabaseAuditHelper.php');
        $reportBuilder = (string) file_get_contents($this->root . '/admin/src/Helper/Audit/DatabaseAuditReportBuilder.php');
        $controller = (string) file_get_contents($this->root . '/admin/src/Controller/AboutController.php');
        $template = (string) file_get_contents($this->root . '/admin/tmpl/about/audit_report.php');

        self::assertStringContainsString('public function repairLegacyAuditIndexes(int $storageId): int', $service);
        $ensureStart = strpos($service, 'private function ensureInternalAuditColumnsAndIndexes');
        $createStart = strpos($service, 'public function createForStorage');
        self::assertIsInt($ensureStart);
        self::assertIsInt($createStart);
        self::assertStringNotContainsString(
            'renameLegacyAuditIndexes',
            substr($service, $ensureStart, $createStart - $ensureStart)
        );

        self::assertStringContainsString('final class StorageIndexNamingAuditHelper', $auditHelper);
        self::assertStringContainsString('StorageIndexNamingAuditHelper::inspect($db)', $audit);
        self::assertStringContainsString('\'legacy_storage_index_issues\' => $legacyStorageIndexIssues', $audit);
        self::assertStringContainsString('\'legacy_storage_index_issues\' => $legacyStorageIndexIssues', $reportBuilder);
        self::assertStringContainsString('repairLegacyStorageIndexes()', $controller);
        self::assertStringContainsString('about.repairLegacyStorageIndexes', $template);
        self::assertStringContainsString('cb-audit-legacy-storage-indexes-table', $template);
        // One repair renames all legacy indexes of a storage: a single button spans its rows.
        self::assertStringContainsString('rowspan="<?php echo count($legacyIndexes); ?>"', $template);
        self::assertStringContainsString('if ($legacyIndexPosition === 0)', $template);

        $expectedLabels = [
            'en-GB' => ['Legacy storage index names', 'Fix names'],
            'fr-FR' => ['Anciens noms d’index des storages', 'Corriger les noms'],
            'de-DE' => ['Veraltete Namen von Storage-Indizes', 'Namen korrigieren'],
        ];

        foreach ($expectedLabels as $language => $labels) {
            $translations = (string) file_get_contents(
                $this->root . '/admin/language/' . $language . '/com_contentbuilderng.ini'
            );

            foreach ($labels as $label) {
                self::assertStringContainsString($label, $translations, $language);
            }
        }
    }

    public function testUploadDirectoryProtectionIsAuditedAndRepairedOutsideTheWorkflow(): void
    {
        $workflow = (string) file_get_contents($this->root . '/admin/src/Service/RepairWorkflowService.php');
        $audit = (string) file_get_contents($this->root . '/admin/src/Helper/DatabaseAuditHelper.php');
        $reportBuilder = (string) file_get_contents($this->root . '/admin/src/Helper/Audit/DatabaseAuditReportBuilder.php');
        $controller = (string) file_get_contents($this->root . '/admin/src/Controller/AboutController.php');
        $template = (string) file_get_contents($this->root . '/admin/tmpl/about/audit_report.php');

        self::assertStringNotContainsString('UploadDirectoryProtectionAuditHelper', $workflow);
        self::assertStringContainsString('UploadDirectoryProtectionAuditHelper::inspect($db)', $audit);
        self::assertStringContainsString('\'upload_directory_protection_issues\' => $uploadDirectoryProtectionIssues', $audit);
        self::assertStringContainsString('\'upload_directory_protection_issues\' => $uploadDirectoryProtectionIssues', $reportBuilder);
        self::assertStringContainsString('public function repairUploadDirectoryProtection()', $controller);
        self::assertStringContainsString('UploadDirectoryProtectionAuditHelper::repair', $controller);
        self::assertStringContainsString('about.repairUploadDirectoryProtection', $template);
        self::assertStringContainsString('cb-audit-upload-directory-protection-table', $template);

        $expectedLabels = [
            'en-GB' => ['Upload Directory Protection', 'Upload directory protection repair failed'],
            'fr-FR' => ['Protection des répertoires de téléversement', 'Échec de la protection des répertoires de téléversement'],
            'de-DE' => ['Schutz der Upload-Verzeichnisse', 'Reparatur des Upload-Verzeichnisschutzes fehlgeschlagen'],
        ];

        foreach ($expectedLabels as $language => $labels) {
            $translations = (string) file_get_contents(
                $this->root . '/admin/language/' . $language . '/com_contentbuilderng.ini'
            );

            foreach ($labels as $label) {
                self::assertStringContainsString($label, $translations, $language);
            }
        }
    }
}
