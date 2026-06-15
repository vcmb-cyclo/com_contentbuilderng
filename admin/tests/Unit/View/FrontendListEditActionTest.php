<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class FrontendListEditActionTest extends TestCase
{
    public function testEditColumnAndCardActionUseOwnerEditPermission(): void
    {
        $template = \file_get_contents(
            \dirname(__DIR__, 4) . '/site/tmpl/list/default.php'
        );

        self::assertIsString($template);

        self::assertSame(
            2,
            \substr_count($template, 'if ($showEditAction)'),
            'Table header and table cell must use the same Edit condition.'
        );
        self::assertStringContainsString(
            '$showEditAction = !empty($this->edit_button) && $edit_allowed;',
            $template
        );
        self::assertStringContainsString(
            "\$canAccessOwnedRecord('edit', \$item->colRecord ?? 0)",
            $template
        );
        self::assertStringContainsString(
            'if (!empty($this->edit_button) && $rowCanEdit)',
            $template
        );
        self::assertStringContainsString(
            "\$rowCanEdit = \$edit_allowed || \$canAccessOwnedRecord('edit', \$row->colRecord);",
            $template
        );
        self::assertStringContainsString(
            "\$ownerUserId = \$isAdminPreview && \$previewActorId > 0",
            $template
        );
        self::assertStringContainsString(
            '$formInstance->isOwner($ownerUserId, $recordId)',
            $template
        );
        self::assertStringContainsString(
            '<span class="fa-solid fa-pen" aria-hidden="true"></span>',
            $template
        );
    }
}
