<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class DetailsStateRatingLayoutTest extends TestCase
{
    public function testStateAndRatingUseDetailsRightSidePanel(): void
    {
        $template = \file_get_contents(
            \dirname(__DIR__, 4) . '/site/tmpl/details/default.php'
        );

        self::assertIsString($template);
        self::assertStringContainsString(
            'grid-template-columns:minmax(0, 1fr) auto;',
            $template
        );
        self::assertStringContainsString(
            '<aside class="cbDetailsMetaAside">',
            $template
        );
        self::assertStringContainsString(
            'if ($showStateDisplay || $showRatingDisplay)',
            $template
        );
        self::assertStringContainsString(
            '.cbDetailsMetaAside .cbDetailState,',
            $template
        );
        self::assertStringContainsString(
            '.cbDetailsMetaAside .cbDetailRating{',
            $template
        );
        self::assertStringContainsString(
            '.cbDetailsWrapper .form-check-input:disabled{',
            $template
        );
        self::assertStringContainsString(
            'border-color:#b8b8b8;',
            $template
        );
        self::assertStringContainsString(
            'color:#909090;',
            $template
        );
    }
}
