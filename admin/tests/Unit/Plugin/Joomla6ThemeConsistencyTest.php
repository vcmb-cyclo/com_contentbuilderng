<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;

final class Joomla6ThemeConsistencyTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $path = \dirname(__DIR__, 4)
            . '/plugins/contentbuilderng_themes/joomla6/src/Extension/Joomla6.php';
        $source = \file_get_contents($path);
        self::assertIsString($source);
        $this->source = $source;
    }

    public function testDetailsAndEditUseBootstrapBodyColors(): void
    {
        self::assertStringContainsString(
            ".cbEditableWrapper,\n.cbDetailsWrapper {\n"
                . "    max-width: 100%;",
            $this->source
        );
        self::assertStringContainsString(
            'color: var(--bs-body-color, #212529);',
            $this->source
        );
        self::assertStringContainsString(
            'background: var(--bs-body-bg, #ffffff);',
            $this->source
        );
        self::assertStringContainsString(
            'border-color: var(--bs-border-color, #dee2e6);',
            $this->source
        );
    }

    public function testDetailsAndEditPanelsMatchListVisualLanguage(): void
    {
        self::assertStringContainsString(
            ".cbEditableWrapper .cbEditableBody,\n"
                . ".cbDetailsWrapper .cbDetailsBody {",
            $this->source
        );
        self::assertStringContainsString(
            ".cbEditableWrapper .cbEditableBody > .mb-3:nth-child(odd),",
            $this->source
        );
        self::assertStringContainsString(
            'background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.035);',
            $this->source
        );
        self::assertStringContainsString(
            'color: var(--bs-body-color, #212529);',
            $this->source
        );
    }

    public function testListEditAndDetailsSharePanelAndControlStyles(): void
    {
        self::assertStringContainsString(
            ".cbEditableWrapper .cbToolBar,\n"
                . ".cbDetailsWrapper .cbToolBar {",
            $this->source
        );
        self::assertStringContainsString(
            'box-shadow: 0 0.35rem 0.9rem rgba(0, 0, 0, 0.06);',
            $this->source
        );
        self::assertStringContainsString(
            '.cb-list-panel{border:1px solid var(--bs-border-color,#dee2e6);'
                . 'border-radius:.9rem;padding:.65rem .75rem;'
                . 'background:var(--bs-body-bg,#fff);'
                . 'box-shadow:0 .35rem .9rem rgba(0,0,0,.06)}',
            $this->source
        );
        self::assertStringContainsString(
            ".cbEditableWrapper .btn,\n.cbDetailsWrapper .btn {",
            $this->source
        );
        self::assertStringContainsString(
            '.cb-list-table .form-control:focus,',
            $this->source
        );
    }

    public function testEditAndDetailsUseTheSameSoberTitleAsTheList(): void
    {
        self::assertStringContainsString(
            'border-bottom: 1px solid rgba(0, 0, 0, 0.12);',
            $this->source
        );
        self::assertStringContainsString(
            ".cbEditableWrapper > h1.display-6::after,\n"
                . ".cbDetailsWrapper > h1.display-6::after {\n"
                . "    display: none;",
            $this->source
        );
    }

    public function testFrontendEditSelectsKeepTextAwayFromBootstrapArrow(): void
    {
        self::assertStringContainsString(
            ".cbEditableWrapper select:not([multiple]):not([size]),\n"
                . ".cbEditableWrapper .form-select:not([multiple]):not([size]),\n"
                . ".cbEditableWrapper .form-select-sm:not([multiple]):not([size]) {\n"
                . "    min-height: 2rem;\n"
                . "    padding-top: 0.24rem;\n"
                . "    padding-bottom: 0.24rem;\n"
                . "    padding-right: 3.25rem;",
            $this->source
        );
        self::assertStringContainsString(
            ".cbEditableWrapper .form-select:not([multiple]):not([size]),\n"
                . ".cbEditableWrapper .form-select-sm:not([multiple]):not([size]) {\n"
                . "    background-position: right 0.72rem center;\n"
                . "    background-repeat: no-repeat;",
            $this->source
        );
        self::assertStringContainsString(
            ".cbEditableWrapper .cbEditableBody > .mb-3 select:not([multiple]):not([size]),\n"
                . ".cbEditableWrapper .cbEditableBody > .mb-3 .form-select:not([multiple]):not([size]),\n"
                . ".cbEditableWrapper .cbEditableBody > .mb-3 .form-select-sm:not([multiple]):not([size]) {\n"
                . "    min-height: 1.86rem;\n"
                . "    font-size: 0.83rem;\n"
                . "    padding-top: 0.2rem;\n"
                . "    padding-bottom: 0.2rem;\n"
                . "    padding-right: 3.25rem;",
            $this->source
        );
    }

    public function testEditableSampleKeepsPublishedNonEditableFieldsVisible(): void
    {
        self::assertStringContainsString(
            'private function fetchElementDefinitions(DatabaseInterface $db, int $contentbuilderng_form_id): array',
            $this->source
        );
        self::assertStringContainsString(
            'SELECT reference_id, `type`, editable',
            $this->source
        );
        self::assertStringContainsString(
            'if (!$editable) {',
            $this->source
        );
        self::assertStringContainsString(
            '<div class="mb-3"><label class="form-label">{\' . $name . \':label}</label><div class="form-control-plaintext py-0">{\' . $name . \':value}</div></div>',
            $this->source
        );
        self::assertStringContainsString(
            'if ($type === \'hidden\') {',
            $this->source
        );
        self::assertStringContainsString(
            'if ($editable) {',
            $this->source
        );
    }
}
