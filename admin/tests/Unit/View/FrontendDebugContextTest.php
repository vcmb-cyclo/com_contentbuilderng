<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontendDebugContextTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testDebugPanelAlwaysRendersAccountAndFormContext(): void
    {
        $layout = $this->read('site/layouts/contentbuilderng/debug_panel.php');

        self::assertStringContainsString("RuntimeContextHelper::getApplication()->getIdentity()", $layout);
        self::assertStringContainsString("'COM_CONTENTBUILDERNG_DEBUG_CURRENT_ACCOUNT'", $layout);
        self::assertStringContainsString("'COM_CONTENTBUILDERNG_DEBUG_FORM_ID'", $layout);
        self::assertStringNotContainsString(
            'if (!$showPermissions && !$showFilters && !$showLogs && !$showCbRecordId)',
            $layout
        );
    }

    #[DataProvider('templateProvider')]
    public function testFrontendTemplatesPassFormIdToDebugPanel(string $relativePath): void
    {
        $template = $this->read($relativePath);

        self::assertStringContainsString("'formId' =>", $template);
    }

    public static function templateProvider(): array
    {
        return [
            'List' => ['site/tmpl/list/default.php'],
            'Details' => ['site/tmpl/details/default.php'],
            'Edit' => ['site/tmpl/edit/default.php'],
        ];
    }

    #[DataProvider('languageProvider')]
    public function testDebugContextTranslationsAreComplete(string $language): void
    {
        $translations = \parse_ini_file(
            $this->root . '/site/language/' . $language . '/com_contentbuilderng.ini'
        );
        self::assertIsArray($translations);

        foreach ([
            'COM_CONTENTBUILDERNG_DEBUG_CONTEXT',
            'COM_CONTENTBUILDERNG_DEBUG_CURRENT_ACCOUNT',
            'COM_CONTENTBUILDERNG_DEBUG_FORM_ID',
            'COM_CONTENTBUILDERNG_DEBUG_GUEST_ACCOUNT',
        ] as $key) {
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

    private function read(string $relativePath): string
    {
        $contents = \file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
