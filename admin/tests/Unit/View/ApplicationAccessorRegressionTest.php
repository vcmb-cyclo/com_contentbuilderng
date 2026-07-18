<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationAccessorRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    #[DataProvider('viewProvider')]
    public function testUpdatedViewsUseLocalApplicationAccessor(
        string $relativePath,
        string $expectedReturnType
    ): void {
        $source = $this->read($relativePath);

        self::assertStringContainsString(
            'private function getApp(): ' . $expectedReturnType,
            $source
        );
        self::assertStringContainsString(
            '$app = $this->app;',
            $source
        );
        self::assertStringContainsString(
            'throw new \RuntimeException(\'Unexpected application instance\');',
            $source
        );
        self::assertStringNotContainsString(
            'RuntimeContextHelper::getApplication()',
            $source
        );
    }

    public function testEditTemplateUsesCanonicalRuntimeContextDatabaseNamespace(): void
    {
        $template = $this->read('site/tmpl/edit/default.php');

        self::assertStringContainsString(
            '$debugDb = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getDatabase();',
            $template
        );
        self::assertStringNotContainsString(
            '\Joomla\CMS\\CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getDatabase();',
            $template
        );
    }

    public static function viewProvider(): array
    {
        return [
            'Admin edit view' => [
                'admin/src/View/Edit/HtmlView.php',
                'CMSApplicationInterface',
            ],
            'Admin form view' => [
                'admin/src/View/Form/HtmlView.php',
                'CMSApplication',
            ],
            'Site details view' => [
                'site/src/View/Details/HtmlView.php',
                'SiteApplication',
            ],
            'Site edit view' => [
                'site/src/View/Edit/HtmlView.php',
                'SiteApplication',
            ],
            'Site list view' => [
                'site/src/View/List/HtmlView.php',
                'SiteApplication',
            ],
        ];
    }

    private function read(string $relativePath): string
    {
        $contents = \file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
