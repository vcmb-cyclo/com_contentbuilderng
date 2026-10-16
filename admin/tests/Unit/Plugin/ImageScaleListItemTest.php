<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;

/**
 * In a list, the Image Scale plugin keeps the current item in
 * $article->cbrecord->items[0]; the article itself has no items.
 */
final class ImageScaleListItemTest extends TestCase
{
    public function testListModeReadsTheItemFromTheRecord(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/plugins/content/contentbuilderng_image_scale'
                . '/src/Extension/ContentbuilderngImageScale.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString('$article->cbrecord->items[0] = $item;', $source);
        self::assertStringNotContainsString('$article->items[', $source);
        self::assertSame(2, substr_count($source, '$is_list ? $article->cbrecord->items[0]->recName'));
    }
}
