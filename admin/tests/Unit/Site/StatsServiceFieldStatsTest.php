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

final class StatsServiceFieldStatsTest extends TestCase
{
    public function testNormalizesValueCountMapToPublicFieldStatsShape(): void
    {
        self::assertSame(
            [
                ['label' => 'Alpha', 'value' => 2],
                ['label' => 'Bravo', 'value' => 5],
            ],
            StatsService::normalizeFieldStats(['Alpha' => 2, 'Bravo' => 5])
        );
    }

    public function testSortNonePreservesCurrentOrder(): void
    {
        self::assertSame(
            [
                ['label' => 'Bravo', 'value' => 5],
                ['label' => 'Alpha', 'value' => 2],
            ],
            StatsService::normalizeFieldStats(['Bravo' => 5, 'Alpha' => 2], 'none', 'desc')
        );
    }

    public function testSortsByTitle(): void
    {
        self::assertSame(
            [
                ['label' => 'Alpha', 'value' => 2],
                ['label' => 'Bravo', 'value' => 5],
            ],
            StatsService::normalizeFieldStats(['Bravo' => 5, 'Alpha' => 2], 'title', 'asc', 'en-GB')
        );
    }

    public function testSortsNumericTitlesNaturallyWithTheActiveLocale(): void
    {
        self::assertSame(
            ['2', '10', 'École', 'Eté'],
            array_column(
                StatsService::normalizeFieldStats(
                    ['10' => 1, 'Eté' => 1, '2' => 1, 'École' => 1],
                    'title',
                    'asc',
                    'fr-FR'
                ),
                'label'
            )
        );

        self::assertSame(
            ['Eté', 'École', '10', '2'],
            array_column(
                StatsService::normalizeFieldStats(
                    ['10' => 1, 'Eté' => 1, '2' => 1, 'École' => 1],
                    'title',
                    'desc',
                    'fr-FR'
                ),
                'label'
            )
        );
    }

    public function testSortsByNumericValueDescending(): void
    {
        self::assertSame(
            [
                ['label' => 'Bravo', 'value' => 12],
                ['label' => 'Charlie', 'value' => 5],
                ['label' => 'Alpha', 'value' => 2],
            ],
            StatsService::normalizeFieldStats(['Alpha' => 2, 'Bravo' => 12, 'Charlie' => 5], 'value', 'desc')
        );
    }

    public function testKeepsUtf8AndSpecialCharactersInLabels(): void
    {
        self::assertSame(
            [
                ['label' => 'Été "A" & <B>', 'value' => 1],
            ],
            StatsService::normalizeFieldStats(['Été "A" & <B>' => 1])
        );
    }

    public function testParsesAndCombinesExternalAdditions(): void
    {
        self::assertSame(
            ['Alpha' => 5, 'École' => 3],
            StatsService::parseFieldStatsAdditions(' Alpha = 2 ;École=3;Alpha=003')
        );
    }

    public function testMergesExistingAndNewLabelsBeforeSorting(): void
    {
        self::assertSame(
            [
                ['label' => 'New label', 'value' => 12],
                ['label' => 'Existing', 'value' => 3],
            ],
            StatsService::normalizeFieldStats(
                ['Existing' => 1],
                'value',
                'desc',
                'en-GB',
                StatsService::parseFieldStatsAdditions('Existing=2;New label=12')
            )
        );
    }

    public function testSortNoneAppendsNewLabelsInAddOrder(): void
    {
        self::assertSame(
            ['Existing', 'First new', 'Second new'],
            array_column(
                StatsService::normalizeFieldStats(
                    ['Existing' => 1],
                    'none',
                    'desc',
                    'en-GB',
                    StatsService::parseFieldStatsAdditions('First new=2;Second new=3')
                ),
                'label'
            )
        );
    }

    public function testAdditionLabelMatchingIsExactAfterTrimming(): void
    {
        self::assertSame(
            [
                ['label' => 'École', 'value' => 2],
                ['label' => 'Ecole', 'value' => 3],
            ],
            StatsService::normalizeFieldStats(
                ['École' => 2],
                additions: StatsService::parseFieldStatsAdditions(' Ecole = 3 ')
            )
        );
    }

    public function testRejectsInvalidAddSyntaxWithoutPartialResult(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        StatsService::parseFieldStatsAdditions('Valid=2;Invalid=2.5');
    }

    public function testParsesSignedAdditionsAndCombinesRepeatedLabels(): void
    {
        self::assertSame(
            ['Alpha' => 6, 'Bravo' => 0, 'Charlie' => -5],
            StatsService::parseFieldStatsAdditions('Alpha=+5;Alpha=-2;Alpha=3;Bravo=0;Charlie=-5')
        );
    }

    public static function invalidSignedAdditionsProvider(): array
    {
        return [
            'decimal' => ['Alpha=2.5'],
            'negative decimal' => ['Alpha=-2.5'],
            'text' => ['Alpha=abc'],
            'empty value' => ['Alpha='],
        ];
    }

    #[DataProvider('invalidSignedAdditionsProvider')]
    public function testRejectsInvalidSignedAdditions(string $add): void
    {
        $this->expectExceptionCode(StatsService::CBSTATS_ERROR_INVALID_ADD);

        StatsService::parseFieldStatsAdditions($add);
    }

    public function testAppliesNegativeAdditionToExistingLabel(): void
    {
        self::assertSame(
            [['label' => '1', 'value' => 3]],
            StatsService::normalizeFieldStats(['1' => 5], additions: ['1' => -2])
        );
    }

    public function testClampsNegativeAdditionForMissingLabelToZero(): void
    {
        self::assertSame(
            [
                ['label' => 'Existing', 'value' => 3],
                ['label' => 'Missing', 'value' => 0],
            ],
            StatsService::normalizeFieldStats(['Existing' => 3], additions: ['Missing' => -1])
        );
    }

    public function testClampsNegativeFinalAddResultToZero(): void
    {
        self::assertSame(
            [['label' => 'Existing', 'value' => 0]],
            StatsService::normalizeFieldStats(['Existing' => 3], additions: ['Existing' => -5])
        );
    }

    public static function progressiveAddResultProvider(): array
    {
        return [
            'minus eight' => [-8, 0],
            'minus two' => [-2, 0],
            'zero' => [0, 0],
            'one' => [1, 1],
            'five' => [5, 5],
        ];
    }

    #[DataProvider('progressiveAddResultProvider')]
    public function testUsesPositiveOrZeroValueAsSoonAsRawAddResultRecovers(int $rawResult, int $effectiveResult): void
    {
        self::assertSame(
            [['label' => 'Existing', 'value' => $effectiveResult]],
            StatsService::normalizeFieldStats(['Existing' => 8], additions: ['Existing' => $rawResult - 8])
        );
    }

    public function testSortValueUsesClampedResultAscendingAndDescending(): void
    {
        $values = ['Ten' => 10, 'Negative' => 2, 'Three' => 3];
        $additions = ['Negative' => -10];

        self::assertSame(
            [0, 3, 10],
            array_column(StatsService::normalizeFieldStats($values, 'value', 'asc', additions: $additions), 'value')
        );
        self::assertSame(
            [10, 3, 0],
            array_column(StatsService::normalizeFieldStats($values, 'value', 'desc', additions: $additions), 'value')
        );
    }

    public function testNegativeAddResultDoesNotAffectIndependentStatistics(): void
    {
        self::assertSame(
            [['label' => 'Normal A', 'value' => 4]],
            StatsService::normalizeFieldStats(['Normal A' => 4])
        );
        self::assertSame(
            [['label' => 'Affected', 'value' => 0]],
            StatsService::normalizeFieldStats(['Affected' => 2], additions: ['Affected' => -10])
        );
        self::assertSame(
            [['label' => 'Normal C', 'value' => 6]],
            StatsService::normalizeFieldStats(['Normal C' => 6])
        );
    }

    public function testParsesUtf8TitleMappingsUsingFirstEqualsSign(): void
    {
        self::assertSame(
            ['1' => 'Groupe 1', 'Détente' => 'École = vélo'],
            StatsService::parseFieldStatsTitles(' 1 = Groupe 1 ; Détente = École = vélo ')
        );
    }

    public function testAppliesTitlesAfterAddWithoutMergingEqualDisplayLabels(): void
    {
        self::assertSame(
            [
                ['label' => 'Groupe', 'value' => 3],
                ['label' => 'Groupe', 'value' => 2],
                ['label' => 'Inscriptions sur place', 'value' => 4],
            ],
            StatsService::normalizeFieldStats(
                ['A' => 5, 'B' => 2],
                additions: ['A' => -2, 'Sur place' => 4],
                titles: ['A' => 'Groupe', 'B' => 'Groupe', 'Sur place' => 'Inscriptions sur place']
            )
        );
    }

    public function testSortTitleUsesFinalDisplayTitles(): void
    {
        self::assertSame(
            ['Groupe A', 'Groupe B', 'Groupe détente'],
            array_column(
                StatsService::normalizeFieldStats(
                    ['1' => 5, '2' => 9, 'Détente' => 1],
                    'title',
                    'asc',
                    'fr-FR',
                    titles: ['1' => 'Groupe B', '2' => 'Groupe A', 'Détente' => 'Groupe détente']
                ),
                'label'
            )
        );
    }

    public function testRejectsInvalidTitleMappings(): void
    {
        $this->expectExceptionCode(StatsService::CBSTATS_ERROR_INVALID_TITLES);

        StatsService::parseFieldStatsTitles('1=Groupe 1;2=');
    }
}
