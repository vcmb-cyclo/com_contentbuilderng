<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Site;

use PHPUnit\Framework\TestCase;

final class CardGridCssTest extends TestCase
{
    public function testJceSpanWrappersPreserveCardGridWidths(): void
    {
        $root = \dirname(__DIR__, 4);
        $css = (string) \file_get_contents($root . '/media/css/cards.css');

        self::assertStringContainsString(
            '.cb-cards > span:has(> .cb-card:only-child)',
            $css
        );
        self::assertStringContainsString(
            '.cb-cards > span > :is(.cb-card-v1, .cb-card-v2, .cb-card-v3, .cb-card-v4, .cb-card-v5, .cb-card-v6)',
            $css
        );
        self::assertStringContainsString('.cb-cards > span > .cb-card-w33', $css);
        self::assertStringContainsString('.cb-cards > span > .cb-card-w66', $css);
        self::assertStringContainsString('.cb-cards > span > .cb-card-w100', $css);
    }
}
