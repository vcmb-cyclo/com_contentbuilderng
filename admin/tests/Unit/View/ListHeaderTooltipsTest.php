<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListHeaderTooltipsTest extends TestCase
{
    private const FORM_KEYS = [
        'COM_CONTENTBUILDERNG_FORMS_COLUMN_ID_TIP',
        'COM_CONTENTBUILDERNG_COLUMN_SELECT_TIP',
        'COM_CONTENTBUILDERNG_FORMS_COLUMN_PREVIEW_TIP',
        'COM_CONTENTBUILDERNG_FORMS_COLUMN_NAME_TIP',
        'COM_CONTENTBUILDERNG_FORMS_COLUMN_TAG_TIP',
        'COM_CONTENTBUILDERNG_FORMS_COLUMN_SOURCE_TIP',
        'COM_CONTENTBUILDERNG_FORMS_COLUMN_TYPE_TIP',
        'COM_CONTENTBUILDERNG_COLUMN_ORDERING_TIP',
        'COM_CONTENTBUILDERNG_COLUMN_MODIFIED_TIP',
        'COM_CONTENTBUILDERNG_FORMS_COLUMN_DEBUG_TIP',
        'COM_CONTENTBUILDERNG_FORMS_COLUMN_PUBLISHED_TIP',
    ];

    private const STORAGE_KEYS = [
        'COM_CONTENTBUILDERNG_STORAGES_COLUMN_ID_TIP',
        'COM_CONTENTBUILDERNG_COLUMN_SELECT_TIP',
        'COM_CONTENTBUILDERNG_STORAGES_COLUMN_PREVIEW_TIP',
        'COM_CONTENTBUILDERNG_STORAGES_COLUMN_NAME_TIP',
        'COM_CONTENTBUILDERNG_STORAGES_COLUMN_TITLE_TIP',
        'COM_CONTENTBUILDERNG_STORAGES_COLUMN_MODE_TIP',
        'COM_CONTENTBUILDERNG_COLUMN_ORDERING_TIP',
        'COM_CONTENTBUILDERNG_COLUMN_MODIFIED_TIP',
        'COM_CONTENTBUILDERNG_STORAGES_COLUMN_PUBLISHED_TIP',
    ];

    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testFormsHeadersHaveTranslatedTooltips(): void
    {
        $this->assertTemplateContainsTooltipKeys(
            'admin/tmpl/forms/default.php',
            self::FORM_KEYS
        );
    }

    public function testStoragesHeadersHaveTranslatedTooltips(): void
    {
        $this->assertTemplateContainsTooltipKeys(
            'admin/tmpl/storages/default.php',
            self::STORAGE_KEYS
        );
    }

    #[DataProvider('languageProvider')]
    public function testHeaderTooltipTranslationsAreComplete(string $language): void
    {
        $translations = \parse_ini_file(
            $this->root . '/admin/language/' . $language . '/com_contentbuilderng.ini'
        );
        self::assertIsArray($translations);

        foreach (\array_unique([...self::FORM_KEYS, ...self::STORAGE_KEYS]) as $key) {
            self::assertArrayHasKey($key, $translations, $language . ': ' . $key);
            self::assertNotSame('', $translations[$key], $language . ': ' . $key);
        }
    }

    public static function languageProvider(): array
    {
        return [
            'English' => ['en-GB'],
            'French' => ['fr-FR'],
            'German' => ['de-DE'],
        ];
    }

    /**
     * @param list<string> $keys
     */
    private function assertTemplateContainsTooltipKeys(string $relativePath, array $keys): void
    {
        $template = \file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($template);

        foreach ($keys as $key) {
            self::assertStringContainsString("Text::_('{$key}')", $template);
        }

        self::assertGreaterThanOrEqual(
            \count($keys),
            \substr_count($template, 'class="hasTooltip')
                + \substr_count($template, ' hasTooltip"')
        );
    }
}
