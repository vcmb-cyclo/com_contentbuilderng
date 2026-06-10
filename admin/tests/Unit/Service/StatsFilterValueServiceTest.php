<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Site\Service\StatsFilterValueService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatsFilterValueServiceTest extends TestCase
{
    public function testParsesPipeSeparatedExactAlternatives(): void
    {
        self::assertSame(
            ['200 km', '200 km (Formule)'],
            (new StatsFilterValueService())->parseAlternatives('200 km | 200 km (Formule)')
        );
    }

    public function testIgnoresEmptyAndDuplicateAlternatives(): void
    {
        self::assertSame(
            ['200 km'],
            (new StatsFilterValueService())->parseAlternatives(' | 200 km | 200 km | ')
        );
    }

    public function testConvertsStarToSqlWildcard(): void
    {
        $service = new StatsFilterValueService();

        self::assertTrue($service->hasWildcard('200 km*'));
        self::assertSame('200 km%', $service->toSqlLikePattern('200 km*'));
    }

    public function testEscapesNativeSqlWildcards(): void
    {
        self::assertSame(
            '100\\%\\_\\\\%',
            (new StatsFilterValueService())->toSqlLikePattern('100%_\\*')
        );
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function surroundingSpacesProvider(): array
    {
        return [
            'no spaces' => ['200 km'],
            'leading space' => [' 200 km'],
            'trailing space' => ['200 km '],
            'surrounding spaces' => [' 200 km '],
        ];
    }

    #[DataProvider('surroundingSpacesProvider')]
    public function testTrimsSurroundingSpaces(string $value): void
    {
        self::assertSame(
            ['200 km'],
            (new StatsFilterValueService())->parseAlternatives($value)
        );
    }
}
