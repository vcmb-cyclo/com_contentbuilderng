<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

final class TemplateRenderServiceEditableHideTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $path = \dirname(__DIR__, 4)
            . '/admin/src/Service/TemplateRenderService.php';
        $source = \file_get_contents($path);
        self::assertIsString($source);
        $this->source = $source;
    }

    public function testEditableHideIfEmptyKeepsEmptyEditableItemsVisible(): void
    {
        self::assertStringContainsString(
            'private function applyEditableHideIfEmpty(string $template, string $name, string $rawValue): string',
            $this->source
        );
        self::assertStringContainsString(
            "if (str_contains(\$body, '{' . \$name . ':item}')) {\n"
                . "                    return \$body;\n"
                . '                }',
            $this->source
        );
        self::assertStringContainsString(
            "return trim(\$rawValue) === '' ? '' : \$body;",
            $this->source
        );
        self::assertStringContainsString(
            '$template = $this->applyEditableHideIfEmpty($template, (string) $key, (string) $hideIfEmptyValue);',
            $this->source
        );
    }

    public function testEditableTemplateSupportsReadonlyValuesAndHideIfMatches(): void
    {
        self::assertStringContainsString(
            'private function applyEditableHideIfMatches(string $template, string $name, string $rawValue): string',
            $this->source
        );
        self::assertStringContainsString(
            "if (str_contains(\$body, '{' . \$name . ':item}')) {\n"
                . "                    return \$body;\n"
                . '                }',
            $this->source
        );
        self::assertStringContainsString(
            'return trim($rawValue) === $expectedValue ? \'\' : $body;',
            $this->source
        );
        self::assertStringContainsString(
            '$template = $this->applyEditableHideIfMatches($template, (string) $key, (string) $hideIfEmptyValue);',
            $this->source
        );
        self::assertStringContainsString(
            'str_replace(\'{\' . $key . \':value}\', nl2br(htmlspecialchars((string) $hideIfEmptyValue, ENT_QUOTES, \'UTF-8\')), $template)',
            $this->source
        );
    }
}
