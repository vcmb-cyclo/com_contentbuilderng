<?php

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminListExportRouteTest extends TestCase
{
    public static function templateProvider(): array
    {
        return [
            'default list' => ['default.php'],
            'selection list' => ['select.php'],
        ];
    }

    #[DataProvider('templateProvider')]
    public function testXlsxExportTargetsTheSiteApplication(string $template): void
    {
        $path = dirname(__DIR__, 3) . '/tmpl/list/' . $template;
        $source = file_get_contents($path);

        self::assertIsString($source);
        self::assertStringContainsString("Route::link('site', 'index.php?option=com_contentbuilderng&view=export", $source);
        self::assertStringNotContainsString("Route::_('index.php?option=com_contentbuilderng&view=export", $source);
    }

    public function testBreezingFormsNgSourceUsesTheRenamedJoomla6TypeFile(): void
    {
        $helper = dirname(__DIR__, 3) . '/src/Helper/FormSourceFactory.php';
        $source = file_get_contents($helper);

        self::assertIsString($source);
        self::assertStringContainsString(". \$normalizedType", $source);
        self::assertStringNotContainsString("? 'com_breezingforms'", $source);
        self::assertFileExists(dirname(__DIR__, 3) . '/src/types/com_breezingformsng.php');
    }
}
