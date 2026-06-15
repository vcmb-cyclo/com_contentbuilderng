<?php

declare(strict_types=1);

namespace Joomla\Input {
    if (!class_exists(Input::class, false)) {
        final class Input
        {
            public function __construct(private readonly array $values = [])
            {
            }

            public function getCmd(string $name, string $default = ''): string
            {
                return preg_replace('/[^A-Z0-9_\.-]/i', '', (string) ($this->values[$name] ?? $default));
            }
        }
    }
}

namespace CB\Component\Contentbuilderng\Tests\Unit\Helper {

use CB\Component\Contentbuilderng\Site\Helper\PreviewColorModeHelper;
use Joomla\Input\Input;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 4) . '/site/src/Helper/PreviewColorModeHelper.php';

final class PreviewColorModeHelperTest extends TestCase
{
    public function testModeIsAvailableOnlyDuringPreview(): void
    {
        $input = new Input(['cb_preview_color_mode' => 'dark']);

        self::assertSame('default', PreviewColorModeHelper::resolve($input, false));
        self::assertSame('dark', PreviewColorModeHelper::resolve($input, true));
    }

    public function testInvalidModeFallsBackToDefault(): void
    {
        $input = new Input(['cb_preview_color_mode' => 'invalid']);

        self::assertSame('default', PreviewColorModeHelper::resolve($input, true));
    }

    public function testModeIsPropagatedToLinksAndForms(): void
    {
        self::assertSame(
            '&cb_preview=1&cb_preview_color_mode=light',
            PreviewColorModeHelper::appendQuery('&cb_preview=1', 'light')
        );
        self::assertStringContainsString(
            'name="cb_preview_color_mode" value="dark"',
            PreviewColorModeHelper::appendHiddenField('', 'dark')
        );
    }
}
}
