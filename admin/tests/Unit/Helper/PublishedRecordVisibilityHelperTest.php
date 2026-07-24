<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Helper;

use CB\Component\Contentbuilderng\Site\Helper\PublishedRecordVisibilityHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 4) . '/site/src/Helper/PublishedRecordVisibilityHelper.php';

final class PublishedRecordVisibilityHelperTest extends TestCase
{
    /**
     * @return array<string,array{0:object,1:bool,2:bool}>
     */
    public static function visibilityProvider(): array
    {
        return [
            'published-only frontend' => [(object) ['published_only' => 1], false, true],
            'published-only preview' => [(object) ['published_only' => 1], true, false],
            'all records frontend' => [(object) ['published_only' => 0], false, false],
            'missing setting' => [(object) [], false, false],
        ];
    }

    #[DataProvider('visibilityProvider')]
    public function testDeterminesPublishedOnlyRestriction(object $data, bool $preview, bool $expected): void
    {
        self::assertSame(
            $expected,
            PublishedRecordVisibilityHelper::shouldRestrictToPublishedOnly($data, $preview)
        );
    }
}
