<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

final class TemplateRenderServiceDetailsConditionsTest extends TestCase
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

    public function testDetailsTemplateAddsMissingAllowedFieldsBeforeConditions(): void
    {
        self::assertStringContainsString(
            '$elementNames = (array) $form->getElementNames();',
            $this->source
        );
        self::assertStringContainsString(
            "'raw_value' => '',",
            $this->source
        );
        self::assertStringContainsString(
            'private function applyTemplateHideIfEmpty(string $template, string $name, string $rawValue, bool $preserveEditableItem): string',
            $this->source
        );
        self::assertStringContainsString(
            'return $this->applyTemplateHideIfEmpty($template, $name, $rawValue, false);',
            $this->source
        );
    }

    public function testDetailsTemplateReplacesFieldTokensCaseInsensitively(): void
    {
        self::assertStringContainsString(
            'private function replaceTemplateFieldToken(string $template, string $name, string $token, string $replacement): string',
            $this->source
        );
        self::assertStringContainsString(
            '\'/\\\\{\' . preg_quote($name, \'/\') . \':\' . preg_quote($token, \'/\') . \'\\\\}/i\'',
            $this->source
        );
        self::assertStringContainsString(
            '$template = $this->replaceTemplateFieldToken($template, (string) $key, \'label\', (string) $item[\'label\']);',
            $this->source
        );
        self::assertStringContainsString(
            '$template = $this->replaceTemplateFieldToken($template, (string) $key, \'value\', (string) $item[\'value\']);',
            $this->source
        );
    }

    public function testDebugModeReportsCaseMismatches(): void
    {
        self::assertStringContainsString(
            'private function addDebugTemplateWarning(int $formId, string $message): void',
            $this->source
        );
        self::assertStringContainsString(
            'private function addCaseMismatchWarnings(int $formId, string $template, string $fieldName): void',
            $this->source
        );
        self::assertStringContainsString(
            'COM_CONTENTBUILDERNG_DEBUG_WARNING_TEMPLATE_CASE_MISMATCH',
            $this->source
        );
        $removedWarningKey = 'COM_CONTENTBUILDERNG_DEBUG_WARNING_TEMPLATE_' . 'MISSING' . '_RECORD' . '_FIELD';
        self::assertStringNotContainsString($removedWarningKey, $this->source);
        self::assertStringContainsString(
            "\$session->set('com_contentbuilderng.debug.template_warnings', \$warnings);",
            $this->source
        );
    }

    public function testUnknownTemplateFieldsAreRemovedAndReported(): void
    {
        self::assertStringContainsString(
            'private function removeUnknownTemplateMarkers(int $formId, string $template, array $knownFieldNames): string',
            $this->source
        );
        self::assertStringContainsString(
            'COM_CONTENTBUILDERNG_DEBUG_WARNING_TEMPLATE_UNKNOWN_FIELD',
            $this->source
        );
        self::assertStringContainsString(
            "\$template = (string) preg_replace(\"/\\\\{hide-if-empty\\\\s+\" . \$quotedName . \"\\\\}.*?\\\\{\\\\/hide\\\\}/is\", '', \$template);",
            $this->source
        );
        self::assertStringContainsString(
            "\$template = \$this->removeUnknownTemplateMarkers((int) \$contentbuilderngFormId, \$template, array_keys(\$items));",
            $this->source
        );
    }
}
