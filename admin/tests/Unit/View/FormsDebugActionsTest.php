<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormsDebugActionsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testDebugColumnIsRenderedBeforePublishedColumn(): void
    {
        $template = $this->read('admin/tmpl/forms/default.php');
        $debugHeading = "'COM_CONTENTBUILDERNG_DEBUG_MODE', 'a.debug_mode'";
        $publishedHeading = "'COM_CONTENTBUILDERNG_PUBLISHED', 'a.published'";

        $debugPosition = \strpos($template, $debugHeading);
        $publishedPosition = \strpos($template, $publishedHeading);

        self::assertNotFalse($debugPosition);
        self::assertNotFalse($publishedPosition);
        self::assertLessThan($publishedPosition, $debugPosition);
        self::assertStringContainsString(
            "ContentbuilderngHelper::listDebug('forms', \$row, \$i)",
            $template
        );
        self::assertStringContainsString('<td colspan="12">', $template);
    }

    public function testDebugStateToggleUsesDedicatedListTasks(): void
    {
        $helper = $this->read('admin/src/Helper/ContentbuilderngHelper.php');

        self::assertStringContainsString('public static function listDebug(', $helper);
        self::assertStringContainsString("'debug_on'", $helper);
        self::assertStringContainsString("'debug_off'", $helper);
        self::assertStringContainsString("'COM_CONTENTBUILDERNG_DEBUG_ON'", $helper);
        self::assertStringContainsString("'COM_CONTENTBUILDERNG_DEBUG_OFF'", $helper);
        self::assertStringContainsString(
            "str_replace('icon-publish', 'fa fa-bug text-success', \$toggle)",
            $helper
        );
    }

    public function testEditViewUsesGreenBugIconForEnabledDebugState(): void
    {
        $layout = $this->read('admin/layouts/form/view_tab.php');
        $script = $this->read('media/js/form-edit-init.js');

        self::assertStringContainsString(
            "str_replace('icon-publish', 'fa fa-bug text-success', \$debugToggleHtml)",
            $layout
        );
        self::assertStringContainsString(
            "enabled && useDebugIcon ? 'fa-bug'",
            $script
        );
        self::assertStringContainsString(
            "task === 'form.debug_on' || task === 'form.debug_off'",
            $script
        );
    }

    public function testEditViewAppliesInitialDebugTabStateAfterDomIsReady(): void
    {
        $script = $this->read('media/js/form-edit-init.js');

        self::assertStringContainsString(
            "document.addEventListener('DOMContentLoaded', function() {\n        cbToggleDebugTab(cbDebugModeEnabled);\n    });",
            $script
        );
        self::assertStringNotContainsString(
            "if (!cbDebugModeEnabled) {\n        cbToggleDebugTab(false);\n    }",
            $script
        );
    }

    public function testToolbarAndControllerExposeBulkDebugActions(): void
    {
        $view = $this->read('admin/src/View/Forms/HtmlView.php');
        $controller = $this->read('admin/src/Controller/FormsController.php');

        self::assertStringContainsString("->task('forms.debug_on')", $view);
        self::assertStringContainsString("->task('forms.debug_off')", $view);
        self::assertStringContainsString('public function debug_on(): void', $controller);
        self::assertStringContainsString('public function debug_off(): void', $controller);
        self::assertStringContainsString("->set(\$db->quoteName('debug_mode') . ' = ' . \$state)", $controller);
        self::assertStringContainsString(
            "->whereIn(\$db->quoteName('id'), \$cid, ParameterType::INTEGER)",
            $controller
        );
    }

    public function testDebugModeIsAnAllowedFormsOrderingField(): void
    {
        $model = $this->read('admin/src/Model/FormsModel.php');

        self::assertGreaterThanOrEqual(2, \substr_count($model, "'a.debug_mode'"));
    }

    #[DataProvider('languageProvider')]
    public function testBulkDebugTranslationsAreComplete(string $language): void
    {
        $translations = $this->parseIni(
            'admin/language/' . $language . '/com_contentbuilderng.ini'
        );

        foreach ([
            'COM_CONTENTBUILDERNG_DEBUG_ON',
            'COM_CONTENTBUILDERNG_DEBUG_OFF',
            'COM_CONTENTBUILDERNG_N_ITEMS_DEBUG_ON',
            'COM_CONTENTBUILDERNG_N_ITEMS_DEBUG_ON_1',
            'COM_CONTENTBUILDERNG_N_ITEMS_DEBUG_OFF',
            'COM_CONTENTBUILDERNG_N_ITEMS_DEBUG_OFF_1',
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

    /**
     * @return array<string,string>
     */
    private function parseIni(string $relativePath): array
    {
        $translations = \parse_ini_file($this->root . '/' . $relativePath);
        self::assertIsArray($translations, 'Unable to parse ' . $relativePath);

        return $translations;
    }
}
