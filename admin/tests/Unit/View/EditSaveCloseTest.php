<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class EditSaveCloseTest extends TestCase
{
    public function testSaveClosesAndSecondaryActionCancels(): void
    {
        $template = \file_get_contents(
            \dirname(__DIR__, 4) . '/site/tmpl/edit/default.php'
        );
        $controller = \file_get_contents(
            \dirname(__DIR__, 4) . '/site/src/Controller/EditController.php'
        );

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString(
            "value='edit.save';contentbuilderng.onSubmit();",
            $template
        );
        self::assertStringContainsString(
            "'closeTitle' => Text::_('COM_CONTENTBUILDERNG_CANCEL')",
            $template
        );
        self::assertStringNotContainsString(
            'In admin preview we keep users on the form page.',
            $controller
        );
        self::assertStringContainsString(
            "'&Itemid=' . \$this->siteApp->input->getInt('Itemid', 0) . \$previewQuery",
            $controller
        );
    }
}
