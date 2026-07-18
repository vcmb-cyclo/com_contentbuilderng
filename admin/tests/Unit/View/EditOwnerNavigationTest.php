<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class EditOwnerNavigationTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 4) . '/site/src/View/Edit/HtmlView.php'
        );

        self::assertIsString($source);
        $this->source = $source;
    }

    public function testEditPreviousNextSkipsRecordsNotEditableByOwner(): void
    {
        self::assertStringContainsString(
            'private function isFrontendEditAllowedForNavigation(): bool',
            $this->source
        );
        self::assertStringContainsString(
            'private function canNavigateToEditableRecord(int $recordId): bool',
            $this->source
        );
        // The view resolves the app through its local getApp() accessor (see
        // testSiteViewsUseLocalApplicationAndDatabaseAccessors), not through
        // the static RuntimeContextHelper.
        self::assertStringContainsString(
            "\$permissions = (array) \$this->getApp()->getSession()->get('com_contentbuilderng.permissions_fe', []);",
            $this->source
        );
        self::assertStringContainsString(
            "if (!empty(\$permissions[(int) \$groupId]['edit']))",
            $this->source
        );
        self::assertStringContainsString(
            'private function canUseEditPermissionBase(array $permissions): bool',
            $this->source
        );
        self::assertStringContainsString(
            "\$ownerRuleSet = (array) (\$ownerPermissionMatrix['own_fe'] ?? []);",
            $this->source
        );
        self::assertStringContainsString(
            "\$this->form->isOwner(\$this->getOwnerEditNavigationUserId(), \$recordId)",
            $this->source
        );
        self::assertStringContainsString(
            'for ($i = $position - 1; $i >= 0; $i--)',
            $this->source
        );
        self::assertStringContainsString(
            'for ($i = $position + 1, $count = count($recordIds); $i < $count; $i++)',
            $this->source
        );
        self::assertStringNotContainsString(
            "'next' => (\$position + 1) < count(\$recordIds) ? (int) \$recordIds[\$position + 1] : 0",
            $this->source
        );
    }
}
