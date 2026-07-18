<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class ApplicationAccessorRegressionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testAllViewsUseTheirInjectedApplication(): void
    {
        $viewsWithAccessor = 0;

        foreach ($this->viewFiles() as $relativePath) {
            $source = $this->read($relativePath);

            if (!str_contains($source, 'function getApp(')) {
                continue;
            }

            ++$viewsWithAccessor;
            self::assertStringContainsString('$app = $this->app;', $source, $relativePath);
            self::assertStringContainsString(
                'throw new \RuntimeException(\'Unexpected application instance\');',
                $source,
                $relativePath
            );
            self::assertStringNotContainsString(
                'RuntimeContextHelper::getApplication()',
                $source,
                $relativePath
            );
            self::assertStringNotContainsString('Factory::getApplication()', $source, $relativePath);
        }

        self::assertGreaterThan(0, $viewsWithAccessor, 'No view application accessors were discovered');
    }

    public function testNoViewNarrowsInheritedLanguageAccessorVisibility(): void
    {
        foreach ($this->viewFiles() as $relativePath) {
            self::assertDoesNotMatchRegularExpression(
                '/\bprivate\s+function\s+getLanguage\s*\(/',
                $this->read($relativePath),
                $relativePath
            );
        }
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

    /**
     * @return list<string>
     */
    private function viewFiles(): array
    {
        $files = array_merge(
            glob($this->root . '/admin/src/View/*/HtmlView.php') ?: [],
            glob($this->root . '/site/src/View/*/HtmlView.php') ?: []
        );
        sort($files);

        self::assertNotEmpty($files, 'No Joomla view files were discovered');

        return array_map(
            fn(string $path): string => substr($path, strlen($this->root) + 1),
            $files
        );
    }

    private function read(string $relativePath): string
    {
        $contents = \file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
