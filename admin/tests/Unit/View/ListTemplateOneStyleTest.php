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

        self::assertStringContainsString('.cb-list-template-cassiopeia{', $template);
        self::assertStringContainsString('--cassiopeia-color-primary', $template);
        self::assertStringContainsString('--bs-body-bg', $template);
        self::assertStringContainsString('--bs-border-color', $template);
        self::assertStringContainsString(
            '.cb-list-template-cassiopeia .cb-list-table',
            $template
        );
        self::assertStringContainsString(
            '.cb-list-template-cassiopeia .pagination .active .page-link',
            $template
        );
    }

    private function read(string $relativePath): string
    {
        $contents = \file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
