<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

final class StorageRegressionFixesTest extends TestCase
{
    public function testRequiredValidationPreservesSparseEditSubmissions(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 4) . '/site/src/Model/EditModel.php');
        self::assertIsString($source);

        $recordGuard = strpos(
            $source,
            "\$isExistingRecord = \$this->app->getInput()->getInt('record_id', 0) > 0;"
        );
        $loop = strpos($source, 'foreach ($requiredElementIds as $requiredElementId)');
        $presenceGuard = strpos(
            $source,
            'if (!array_key_exists($requiredElementId, $values) && $isExistingRecord)',
            $loop
        );
        $valueRead = strpos($source, '$requiredValue = $values[$requiredElementId] ?? null;', $loop);

        self::assertIsInt($recordGuard);
        self::assertIsInt($loop);
        self::assertIsInt($presenceGuard);
        self::assertIsInt($valueRead);
        self::assertLessThan($loop, $recordGuard);
        self::assertLessThan(
            $valueRead,
            $presenceGuard,
            'Only an existing record may skip a required field omitted from a sparse submission.'
        );
    }

    public function testNewStorageFieldPersistsMetadataOnlyAfterQuotedDdl(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 3) . '/src/Model/StorageModel.php');
        self::assertIsString($source);

        $method = strpos($source, 'public function addFieldFromRequest(int $storageId): bool');
        $quotedTable = strpos($source, '$quotedTable = $db->quoteName(', $method);
        $quotedColumn = strpos($source, '$quotedColumn = $db->quoteName(', $method);
        $ddl = strpos($source, "'ALTER TABLE ' . \$quotedTable", $method);
        $requiredDdl = strpos($source, 'StorageColumnTypeHelper::enforceRequired(', $method);
        $metadataExecute = strpos($source, '$db->setQuery($query);', $requiredDdl);

        self::assertIsInt($method);
        self::assertIsInt($quotedTable);
        self::assertIsInt($quotedColumn);
        self::assertIsInt($ddl);
        self::assertIsInt($requiredDdl);
        self::assertIsInt($metadataExecute);
        self::assertLessThan($ddl, $quotedTable);
        self::assertLessThan($ddl, $quotedColumn);
        self::assertLessThan($metadataExecute, $ddl);
        self::assertLessThan($metadataExecute, $requiredDdl);
    }

    public function testRequiredMetadataIsPersistedOnlyAfterPhysicalDdl(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 3) . '/src/Controller/StorageController.php');
        self::assertIsString($source);

        $method = strpos($source, 'public function ajax_update_field_required(): void');
        $ddl = strpos($source, 'StorageColumnTypeHelper::enforceRequired(', $method);
        $metadataUpdate = strpos($source, "->set(\$db->quoteName('required')", $method);

        self::assertIsInt($method);
        self::assertIsInt($ddl);
        self::assertIsInt($metadataUpdate);
        self::assertLessThan(
            $metadataUpdate,
            $ddl,
            'The physical column change must succeed before required metadata is updated.'
        );
    }

    public function testInlineStorageMutationsRequireEditPermission(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 3) . '/src/Controller/StorageController.php');
        self::assertIsString($source);

        self::assertStringContainsString("authorise('core.edit', 'com_contentbuilderng')", $source);

        foreach (['ajax_update_field_type', 'ajax_update_field_required', 'ajax_update_field_title'] as $methodName) {
            $expectedGuard = '/public function ' . $methodName
                . '\(\): void\s*\{\s*\$this->checkToken\(\);\s*\$this->assertStorageEditAccess\(\);/';

            self::assertMatchesRegularExpression(
                $expectedGuard,
                $source,
                $methodName . ' must enforce CSRF and core.edit before mutating Storage state.'
            );
        }
    }

    public function testAjaxFieldCreationRequiresEditPermission(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 3) . '/src/Controller/StorageController.php');
        self::assertIsString($source);

        $method = strpos($source, 'public function ajax_addfield(): void');
        $methodBody = substr($source, $method, 500);

        self::assertIsInt($method);
        self::assertIsString($methodBody);
        self::assertStringContainsString(
            "\$this->checkToken();\n        \$this->assertStorageEditAccess();",
            $methodBody
        );
    }

    public function testRuntimeDirectStorageSynchronizationIsAdditive(): void
    {
        $provisioningSource = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Service/DirectStorageFormProvisioningService.php'
        );
        $supportSource = file_get_contents(\dirname(__DIR__, 3) . '/src/Service/FormSupportService.php');

        self::assertIsString($provisioningSource);
        self::assertIsString($supportSource);
        self::assertStringContainsString('synchElements($formId, $form, false)', $provisioningSource);
        self::assertStringContainsString(
            'public function synchElements($formId, $form, bool $removeMissing = true): array',
            $supportSource
        );
        self::assertStringContainsString('if ($removeMissing && $ids !== []) {', $supportSource);
        self::assertStringContainsString('if ($removeMissing) {', $supportSource);
    }

    public function testStorageSynchronizationRefreshesOnlyManagedEditableTypes(): void
    {
        $supportSource = file_get_contents(\dirname(__DIR__, 3) . '/src/Service/FormSupportService.php');
        $storageSource = file_get_contents(\dirname(__DIR__, 3) . '/src/types/com_contentbuilderng.php');

        self::assertIsString($supportSource);
        self::assertIsString($storageSource);
        self::assertStringContainsString('shouldSynchronizeEditableElementTypes', $storageSource);
        self::assertStringContainsString('StorageColumnTypeHelper::editableElementType(', $storageSource);
        self::assertStringContainsString('$synchronizeEditableTypes = !$removeMissing', $supportSource);
        self::assertStringContainsString('StorageColumnTypeHelper::isStorageManagedEditableType(', $supportSource);
        self::assertStringContainsString("->set(\$db->quoteName('type')", $supportSource);
    }

    public function testStorageRenderingUsesPhysicalInputBounds(): void
    {
        $storageSource = file_get_contents(\dirname(__DIR__, 3) . '/src/types/com_contentbuilderng.php');
        $renderSource = file_get_contents(\dirname(__DIR__, 3) . '/src/Service/TemplateRenderService.php');

        self::assertIsString($storageSource);
        self::assertIsString($renderSource);
        self::assertStringContainsString('public function getVarcharElementSizes(): array', $storageSource);
        self::assertStringContainsString("method_exists(\$form, 'getVarcharElementSizes')", $renderSource);
        self::assertStringContainsString('StorageColumnTypeHelper::INT_MIN', $renderSource);
        self::assertStringContainsString('StorageColumnTypeHelper::INT_MAX', $renderSource);
    }
}
