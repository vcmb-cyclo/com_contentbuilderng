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

namespace CB\Component\Contentbuilderng\Tests\Unit\Site;

use CB\Component\Contentbuilderng\Site\Service\StatsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatsServiceAggregatesTest extends TestCase
{
    /**
     * @return array<string,array{0:array<int|string,int>,1:array{sum: float|null, min: float|null, max: float|null}}>
     */
    public static function aggregatesProvider(): array
    {
        return [
            'empty map' => [
                [],
                ['sum' => null, 'min' => null, 'max' => null],
            ],
            'single value' => [
                ['5' => 3],
                ['sum' => 15.0, 'min' => 5.0, 'max' => 5.0],
            ],
            'integers weighted by counts' => [
                ['1' => 2, '10' => 1, '4' => 3],
                ['sum' => 24.0, 'min' => 1.0, 'max' => 10.0],
            ],
            'decimal and negative values' => [
                ['-2.5' => 2, '0' => 1, '3.25' => 4],
                ['sum' => 8.0, 'min' => -2.5, 'max' => 3.25],
            ],
            'mixed numeric and text' => [
                ['1' => 2, 'n/a' => 1, '3' => 5],
                ['sum' => null, 'min' => null, 'max' => null],
            ],
            'text only' => [
                ['yes' => 4, 'no' => 2],
                ['sum' => null, 'min' => null, 'max' => null],
            ],
            'dates' => [
                ['2026-03-15' => 2, '2025-12-01' => 1, '2026-01-08' => 4],
                ['sum' => null, 'min' => '2025-12-01', 'max' => '2026-03-15'],
            ],
            'datetimes with and without seconds' => [
                ['2026-01-08 09:30' => 1, '2026-01-08 09:15:42' => 2],
                ['sum' => null, 'min' => '2026-01-08 09:15:42', 'max' => '2026-01-08 09:30'],
            ],
            'mixed dates and text' => [
                ['2026-01-08' => 1, 'n/a' => 2],
                ['sum' => null, 'min' => null, 'max' => null],
            ],
            'invalid calendar date' => [
                ['2026-02-30' => 1, '2026-01-08' => 1],
                ['sum' => null, 'min' => null, 'max' => null],
            ],
            'numeric with surrounding sign and exponent' => [
                ['1e2' => 1, '+3' => 2],
                ['sum' => 106.0, 'min' => 3.0, 'max' => 100.0],
            ],
        ];
    }

    /**
     * @param array<int|string,int> $values
     * @param array{sum: float|null, min: float|null, max: float|null} $expected
     */
    #[DataProvider('aggregatesProvider')]
    public function testComputeFieldAggregates(array $values, array $expected): void
    {
        $this->assertSame($expected, StatsService::computeFieldAggregates($values));
    }

    public function testMinAndMaxAreNotWeightedByCounts(): void
    {
        $aggregates = StatsService::computeFieldAggregates(['2' => 100, '7' => 1]);

        $this->assertSame(2.0, $aggregates['min']);
        $this->assertSame(7.0, $aggregates['max']);
        $this->assertSame(207.0, $aggregates['sum']);
    }
}
