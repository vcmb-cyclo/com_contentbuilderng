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
            . '/plugins/contentbuilderng_themes/joomla6/joomla6.php';
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
}
