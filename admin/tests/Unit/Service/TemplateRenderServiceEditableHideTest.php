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
            'private function applyTemplateHideIfEmpty(string $template, string $name, string $rawValue, bool $preserveEditableItem): string',
            $this->source
        );
        self::assertStringContainsString(
            "if (\$preserveEditableItem && preg_match('/\\\\{' . \$quotedName . ':item\\\\}/i', \$body)) {\n"
                . "                    return \$body;\n"
                . '                }',
            $this->source
        );
        self::assertStringContainsString(
            "return trim(\$rawValue) === '' ? '' : \$body;",
            $this->source
        );
        self::assertStringContainsString(
            'return $this->applyTemplateHideIfEmpty($template, $name, $rawValue, true);',
            $this->source
        );
    }

    public function testEditableTemplateSupportsReadonlyValuesAndHideIfMatches(): void
    {
        self::assertStringContainsString(
            'private function applyTemplateHideIfMatches(string $template, string $name, string $rawValue, bool $preserveEditableItem): string',
            $this->source
        );
        self::assertStringContainsString(
            "if (\$preserveEditableItem && preg_match('/\\\\{' . \$quotedName . ':item\\\\}/i', \$body)) {\n"
                . "                    return \$body;\n"
                . '                }',
            $this->source
        );
        self::assertStringContainsString(
            'return trim($rawValue) === $expectedValue ? \'\' : $body;',
            $this->source
        );
        self::assertStringContainsString(
            'return $this->applyTemplateHideIfMatches($template, $name, $rawValue, true);',
            $this->source
        );
        self::assertStringContainsString(
            'private function replaceEditableReadonlyPair(string $template, string $name, string $labelHtml, string $valueHtml): string',
            $this->source
        );
        self::assertStringContainsString(
            '\'<div class="mb-3">\' . $labelHtml . \'<div class="form-control-plaintext py-0">\' . $valueHtml . \'</div></div>\'',
            $this->source
        );
        self::assertStringContainsString(
            '$template = $this->replaceEditableReadonlyPair($template, (string) $key, $labelHtml, $valueHtml);',
            $this->source
        );
        self::assertStringContainsString(
            'str_replace(\'{\' . $key . \':value}\', \'<div class="form-control-plaintext py-0">\' . $valueHtml . \'</div>\', $template)',
            $this->source
        );
    }
}
