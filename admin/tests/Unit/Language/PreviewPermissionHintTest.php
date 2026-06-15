<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Language;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PreviewPermissionHintTest extends TestCase
{
    #[DataProvider('languageProvider')]
    public function testHintStatesThatPermissionAppliesOutsidePreview(
        string $locale,
        string $expectedPrefix
    ): void {
        $path = \dirname(__DIR__, 4)
            . '/site/language/'
            . $locale
            . '/com_contentbuilderng.ini';
        $translations = \parse_ini_file($path);

        self::assertIsArray($translations);
        self::assertArrayHasKey(
            'COM_CONTENTBUILDERNG_PREVIEW_FRONTEND_PERMISSION_HINT',
            $translations
        );
        self::assertStringStartsWith(
            $expectedPrefix,
            $translations['COM_CONTENTBUILDERNG_PREVIEW_FRONTEND_PERMISSION_HINT']
        );
    }

    public static function languageProvider(): array
    {
        return [
            'English' => ['en-GB', 'Outside preview mode'],
            'French' => ['fr-FR', 'Hors mode prévisualisation'],
            'German' => ['de-DE', 'Außerhalb des Vorschaumodus'],
        ];
    }
}
