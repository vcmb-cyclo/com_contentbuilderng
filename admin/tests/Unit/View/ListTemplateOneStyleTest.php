<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class ListTemplateOneStyleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testTemplateOneSelectsCassiopeiaVariant(): void
    {
        $template = $this->read('site/tmpl/list/listone.php');

        self::assertStringContainsString(
            "\$cbListTemplateVariant = 'cassiopeia';",
            $template
        );
        self::assertStringContainsString(
            "require_once __DIR__ . '/default.php';",
            $template
        );
    }

    public function testCassiopeiaVariantUsesTemplateAndBootstrapVariables(): void
    {
        $template = $this->read('site/tmpl/list/default.php');
        $css      = $this->read('media/css/list.css');

        // Styles are loaded via WAM, no longer inline in the template
        self::assertStringContainsString("useStyle('com_contentbuilderng.list')", $template);

        // Cassiopeia-specific rules live in list.css
        self::assertStringContainsString('.cb-list-template-cassiopeia{', $css);
        self::assertStringContainsString('--cassiopeia-color-primary', $css);
        self::assertStringContainsString('--bs-body-bg', $css);
        self::assertStringContainsString('--bs-border-color', $css);
        self::assertStringContainsString('.cb-list-template-cassiopeia .cb-list-table', $css);
        self::assertStringContainsString(
            '.cb-list-template-cassiopeia .pagination .active .page-link',
            $css
        );
    }

    private function read(string $relativePath): string
    {
        $contents = \file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
