<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class StorageEditLayoutTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testStorageTabIsFirstAndFieldsTabIsSecond(): void
    {
        $template = \file_get_contents($this->root . '/admin/tmpl/storage/default.php');
        self::assertIsString($template);

        $storageTab = \strpos(
            $template,
            "addTab', 'view-pane', 'tab1', \$storageTabLabel('fa-solid fa-database', 'COM_CONTENTBUILDERNG_STORAGE_ADMINISTRATION')"
        );
        $fieldsTab = \strpos(
            $template,
            "addTab', 'view-pane', 'tab0', \$storageTabLabel('fa-solid fa-table-list', 'COM_CONTENTBUILDERNG_STORAGE')"
        );

        self::assertIsInt($storageTab);
        self::assertIsInt($fieldsTab);
        self::assertLessThan($fieldsTab, $storageTab);
        self::assertStringContainsString("\$defaultTab = 'tab1';", $template);
    }

    public function testCsvUpdatePanelIsOptIn(): void
    {
        $layout = \file_get_contents($this->root . '/admin/layouts/storage/information_tab.php');
        self::assertIsString($layout);

        self::assertStringContainsString(
            "if ((int) (\$item->id ?? 0) > 0 && \$csvImportRequested)",
            $layout
        );
    }

    public function testFieldEditorUsesGroupedRowActions(): void
    {
        $layout = \file_get_contents($this->root . '/admin/layouts/storage/storage_tab.php');
        $script = \file_get_contents($this->root . '/admin/tmpl/storage/default.php');

        self::assertIsString($layout);
        self::assertIsString($script);
        self::assertStringContainsString('cb-storage-fields-editor', $layout);
        self::assertStringContainsString('cb-storage-field-sql-size-limits', $layout);
        self::assertStringContainsString('cb-storage-field-actions', $layout);
        self::assertStringContainsString('cb-storage-field-new-cancel', $script);
        self::assertStringContainsString('cb-storage-field-new-size', $script);
        self::assertStringContainsString("const cbStorageId =", $script);
        self::assertStringContainsString("formData.set('jform[id]', String(cbStorageId));", $script);
        self::assertStringContainsString("formData.set('jform[fieldtitle]'", $script);
        self::assertStringContainsString("formData.set('jform[sql_type]'", $script);
        self::assertStringContainsString("formData.set('jform[is_group]'", $script);
        self::assertStringContainsString("formData.set('jform[group_definition]'", $script);
        self::assertStringContainsString("max=\"' + maximum + '\"", $script);
        self::assertStringContainsString('data-max-size=', $layout);
    }

    public function testDataTabIsRegisteredAndDelegatesToItsLayout(): void
    {
        $root = \dirname(__DIR__, 4);
        $template = \file_get_contents($root . '/admin/tmpl/storage/default.php');
        $layout = \file_get_contents($root . '/admin/layouts/storage/data_tab.php');
        $view = \file_get_contents($root . '/admin/src/View/Storage/HtmlView.php');
        $model = \file_get_contents($root . '/admin/src/Model/StoragedataModel.php');

        self::assertIsString($template);
        self::assertIsString($layout);
        self::assertIsString($view);
        self::assertIsString($model);

        // Tab guarded by $this->showDataTab, rendered through its own layout.
        self::assertStringContainsString("'view-pane', 'tabData'", $template);
        self::assertStringContainsString("LayoutHelper::render('storage.data_tab'", $template);
        self::assertStringContainsString('$this->showDataTab', $template);
        // tabStartOffset=tabData must survive the active-tab whitelist.
        self::assertStringContainsString("preg_match('/^tab(?:\\d+|Data)$/'", $template);

        // The data model must populate its list state from data_* request
        // parameters so pagination, search and sorting are applied.
        self::assertStringContainsString("createModel('Storagedata', 'Administrator')", $view);
        self::assertStringNotContainsString("createModel('Storagedata', 'Administrator', ['ignore_request' => true])", $view);

        // Add / edit a record reuse the signed front-end editor (same
        // mechanism as the preview button); delete is an admin task.
        $controller = \file_get_contents($root . '/admin/src/Controller/StorageController.php');
        self::assertIsString($controller);
        self::assertStringContainsString('recordEditBaseUrl', $view);
        self::assertStringContainsString('view=edit&storage_id=', $view);
        self::assertStringContainsString('function deleteRecord()', $controller);
        self::assertStringContainsString("Joomla.submitform('storage.deleteRecord'", $template);

        // Display reads the physical table directly, paginated and sortable.
        self::assertStringContainsString('class StoragedataModel extends ListModel', $model);
        self::assertStringContainsString('cb-storage-data-table', $layout);
        self::assertStringContainsString('data_ordering', $layout);
        self::assertStringContainsString('data_ordering', $model);
        self::assertStringContainsString('cb-storage-data-sort', $layout);
    }

    public function testFieldRowActionsUseTitlesetSubformFormalism(): void
    {
        $layout = \file_get_contents($this->root . '/admin/layouts/storage/storage_tab.php');
        self::assertIsString($layout);

        // The add button is available once in the table header; rows only
        // expose delete and move actions.
        self::assertSame(1, substr_count($layout, 'class="btn btn-success group-add cb-storage-field-add'));
        self::assertStringContainsString('group-remove', $layout);
        self::assertStringContainsString('fa-solid fa-trash', $layout);
        self::assertStringContainsString('icon-arrows-alt', $layout);
        self::assertStringNotContainsString('icon-minus', $layout);

        // The separate up/down order icons are gone (drag only).
        self::assertStringNotContainsString('cb-order-icons', $layout);
        self::assertStringNotContainsString('storage.orderup', $layout);
        self::assertStringNotContainsString('storage.orderdown', $layout);
    }

    public function testGroupDefinitionEditControlRequiresGroupSelection(): void
    {
        $layout = \file_get_contents($this->root . '/admin/layouts/storage/storage_tab.php');
        $script = \file_get_contents($this->root . '/admin/tmpl/storage/default.php');

        self::assertIsString($layout);
        self::assertIsString($script);
        self::assertStringContainsString('data-cb-group-definition-edit', $layout);
        self::assertStringContainsString("\$isGroup ? '' : ' hidden'", $layout);
        self::assertStringContainsString('function initStorageFieldGroupToggle()', $script);
        self::assertStringContainsString('editControl.hidden = !isGroup;', $script);
    }

    public function testSystemFieldsUsePersistedUnpublishedMetadataRows(): void
    {
        $layout = \file_get_contents($this->root . '/admin/layouts/storage/storage_tab.php');
        $model = \file_get_contents($this->root . '/admin/src/Model/StorageModel.php');
        $view = \file_get_contents($this->root . '/admin/src/View/Storage/HtmlView.php');

        self::assertIsString($layout);
        self::assertIsString($model);
        self::assertIsString($view);

        self::assertStringContainsString("ContentbuilderngHelper::listPublish('storage', \$row, \$i)", $layout);
        self::assertStringContainsString("'published',", $model);
        self::assertStringContainsString('public function ensureSystemFieldMetadata(', $model);
        self::assertStringContainsString('$storageModel->ensureSystemFieldMetadata($storageId)', $view);
        self::assertStringContainsString('$isSystemField) : ?>', $layout);
        self::assertStringNotContainsString('showPendingSystemFields', $layout);
        self::assertStringNotContainsString('cb-storage-field-system-preview', $layout);
        self::assertStringNotContainsString('storage.publishSystemField', $layout);
        self::assertStringNotContainsString('cb-system-field-name', \file_get_contents($this->root . '/admin/tmpl/storage/default.php'));
    }

    public function testUnpublishedSystemFieldsCanBeHiddenByDefault(): void
    {
        $layout = \file_get_contents($this->root . '/admin/layouts/storage/storage_tab.php');
        $script = \file_get_contents($this->root . '/admin/tmpl/storage/default.php');

        self::assertIsString($layout);
        self::assertIsString($script);
        self::assertStringContainsString('data-cb-storage-hide-unpublished-system-fields="1"', $layout);
        self::assertStringContainsString('aria-controls="cb-storage-fields-tbody"', $layout);
        self::assertStringContainsString('checked>', $layout);
        self::assertStringContainsString('cb-storage-system-field-unpublished d-none', $layout);
        $systemCheckbox = <<<'PHP'
? '<input class="form-check-input" type="checkbox" id="cb' . (int) $i . '" value="' . $id . '" disabled>
PHP;
        self::assertStringContainsString($systemCheckbox, $layout);
        self::assertStringContainsString('data-cb-system-field="1"', $layout);
        self::assertStringContainsString('function initStorageSystemFieldVisibility()', $script);
        self::assertStringContainsString("row.classList.toggle('d-none', hideFields)", $script);
        self::assertStringContainsString("row.classList.toggle('cb-storage-system-field-unpublished', !meta.enabled)", $script);
        self::assertStringContainsString("hide-unpublished-system-fields.", $script);

        $expectedLabels = [
            'en-GB' => 'Hide unpublished system fields',
            'fr-FR' => 'Masquer les champs système non publiés',
            'de-DE' => 'Nicht veröffentlichte Systemfelder ausblenden',
        ];

        foreach ($expectedLabels as $language => $expectedLabel) {
            $translations = \parse_ini_file(
                $this->root . '/admin/language/' . $language . '/com_contentbuilderng.ini',
                true,
                INI_SCANNER_RAW
            );

            self::assertIsArray($translations);
            self::assertSame(
                $expectedLabel,
                $translations['COM_CONTENTBUILDERNG_STORAGE_HIDE_UNPUBLISHED_SYSTEM_FIELDS'] ?? null,
                $language
            );
        }
    }

    public function testStorageIndexesUseNamesDistinctFromColumns(): void
    {
        $model = \file_get_contents($this->root . '/admin/src/Model/StorageModel.php');
        $service = \file_get_contents($this->root . '/admin/src/Service/DatatableService.php');

        self::assertIsString($model);
        self::assertIsString($service);
        self::assertStringContainsString("'idx_user_id'", $model);
        self::assertStringContainsString("'idx_storage_id'", $service);
        self::assertStringContainsString('RENAME INDEX', $service);
        self::assertStringNotContainsString('ADD INDEX (', $model);
        self::assertStringNotContainsString('ADD INDEX (', $service);
    }

    public function testFieldEditActionUnlocksEditableColumnProperties(): void
    {
        $layout = \file_get_contents($this->root . '/admin/layouts/storage/storage_tab.php');
        $script = \file_get_contents($this->root . '/admin/tmpl/storage/default.php');

        self::assertIsString($layout);
        self::assertIsString($script);
        self::assertStringContainsString('$rowFieldEditable = !$isSystemField', $layout);
        self::assertStringContainsString('cb-storage-field-edit', $layout);
        self::assertStringContainsString('fa-solid fa-pencil', $layout);
        self::assertStringContainsString('aria-expanded="false"', $layout);
        self::assertStringContainsString('cb-storage-field-editable', $layout);
        self::assertStringContainsString('function initStorageFieldEditToggle()', $script);
        self::assertStringContainsString('control.disabled = !editing;', $script);
        self::assertStringContainsString('initStorageFieldEditToggle();', $script);

        $expectedLabels = [
            'en-GB' => 'Edit field',
            'fr-FR' => 'Modifier le champ',
            'de-DE' => 'Feld bearbeiten',
        ];

        foreach ($expectedLabels as $language => $expectedLabel) {
            $translations = \parse_ini_file(
                $this->root . '/admin/language/' . $language . '/com_contentbuilderng.ini',
                true,
                INI_SCANNER_RAW
            );

            self::assertIsArray($translations);
            self::assertSame(
                $expectedLabel,
                $translations['COM_CONTENTBUILDERNG_STORAGE_FIELD_EDIT'] ?? null,
                $language
            );
        }
    }
}
