<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class DetailsStateRatingLayoutTest extends TestCase
{
    public function testStateAndRatingShareOneDetailsRow(): void
    {
        $template = \file_get_contents(
            \dirname(__DIR__, 4) . '/site/tmpl/details/default.php'
        );

        self::assertIsString($template);
        self::assertStringContainsString(
            'if ($showStateDisplay || $showRatingDisplay)',
            $template
        );
        self::assertStringContainsString(
            '<div class="cbDetailsMeta d-flex flex-wrap align-items-start gap-4 mb-3">',
            $template
        );
        self::assertStringNotContainsString('<aside class="cbDetailsMetaAside">', $template);
        self::assertLessThan(
            strpos($template, '<?php echo $this->tpl ?>'),
            strpos($template, '<div class="cbDetailsMeta d-flex flex-wrap align-items-start gap-4 mb-3">')
        );
    }
}
