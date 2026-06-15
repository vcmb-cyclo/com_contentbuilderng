<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class DetailsOwnerEditActionTest extends TestCase
{
    public function testDetailsEditButtonSupportsOwnerPermission(): void
    {
        $template = \file_get_contents(
            \dirname(__DIR__, 4) . '/site/tmpl/details/default.php'
        );

        self::assertIsString($template);
        self::assertStringContainsString(
            "\$ownerRuleSet = (array) (\$ownerPermissionMatrix['own_fe'] ?? []);",
            $template
        );
        self::assertStringContainsString(
            '$formInstance->isOwner($ownerUserId, $recordId)',
            $template
        );
        self::assertStringContainsString(
            '$canEditRecord = $edit_allowed || $ownerEditAllowed;',
            $template
        );
        self::assertStringContainsString(
            '<?php if ($canEditRecord) : ?>',
            $template
        );
        self::assertStringContainsString(
            '|| $canEditRecord',
            $template
        );
    }
}
