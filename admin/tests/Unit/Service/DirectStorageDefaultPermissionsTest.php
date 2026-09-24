<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

final class DirectStorageDefaultPermissionsTest extends TestCase
{
    public function testFrontendAutoProvisionedFormsOnlyGrantGuestReadAccess(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Service/DirectStorageFormProvisioningService.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString("get('guest_usergroup')", $source);
        self::assertStringContainsString('if (!$isAdminProvisioned) {', $source);
        self::assertStringContainsString("'listaccess' => true", $source);
        self::assertStringContainsString("'view' => true", $source);
        self::assertStringContainsString("'new' => false", $source);
        self::assertStringContainsString("'edit' => false", $source);
        self::assertStringContainsString("'delete' => false", $source);
        self::assertStringContainsString("'state' => false", $source);
        self::assertStringContainsString("'publish' => false", $source);
        self::assertStringContainsString("'api' => false", $source);
        self::assertStringContainsString("'stats' => false", $source);
        self::assertStringContainsString("'fullarticle' => false", $source);
        self::assertStringContainsString("'language' => false", $source);
        self::assertStringContainsString("'rating' => false", $source);
    }

    public function testAdminProvisionedFormsGrantUsableDefaultsToRealGroups(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Service/DirectStorageFormProvisioningService.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString('foreach ($groupIds as $groupId)', $source);
        self::assertStringContainsString("'new' => \$groupId !== \$guestGroupId", $source);
        self::assertStringContainsString("'edit' => false", $source);
        self::assertStringContainsString('ensureAdminProvisionedPermissions($formId);', $source);
        self::assertStringContainsString('array_key_exists((string) $groupId, $permissions)', $source);

        $wizardSource = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Controller/StoragewizardController.php'
        );
        self::assertIsString($wizardSource);
        self::assertStringContainsString(
            "->resolveOrCreateFormId(\$storageId, 'thoth', true);",
            $wizardSource
        );
    }

    public function testAutoProvisionedFormsDisableArticleCreation(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Service/DirectStorageFormProvisioningService.php'
        );

        self::assertIsString($source);
        self::assertMatchesRegularExpression(
            "/'published',\\s*'create_articles',\\s*'config'/",
            $source
        );
        self::assertStringContainsString(
            ':cbType, :cbReferenceId, :cbName, :cbTitle, :cbTag, 1, 0, :cbConfig',
            $source
        );
    }
}
