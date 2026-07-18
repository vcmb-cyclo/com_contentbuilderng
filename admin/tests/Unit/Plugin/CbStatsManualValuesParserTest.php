<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use CB\Component\Contentbuilderng\Site\Service\StatsService;
use CB\Plugin\Content\ContentbuilderngStats\Service\ManualValuesException;
use CB\Plugin\Content\ContentbuilderngStats\Service\ManualValuesParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CbStatsManualValuesParserTest extends TestCase
{
    public function testParsesUnicodeDecimalsEscapesAndAggregatesDuplicates(): void
    {
        self::assertSame([
            '🚴 Route' => 15,
            'Formule A; longue' => 25.5,
            'Tarif A=B' => 12,
            'Chemin\\retour' => 0,
        ], ManualValuesParser::parse(
            ' 🚴 Route = 10 ;Formule A\; longue=25.5;Tarif A\=B=12;🚴 Route=5;Chemin\\\\retour=0;'
        ));
    }

    public function testUsesTheSharedPipelineForAddTitlesSortingAndNegativeNormalization(): void
    {
        $items = StatsService::normalizeFieldStats(
            ManualValuesParser::parse('Route=10;Gravel=-3;École=2.5'),
            'value',
            'desc',
            'fr-FR',
            StatsService::parseFieldStatsAdditions('Route=5;Gravel=-2;Autre=3'),
            StatsService::parseFieldStatsTitles('Route=Route longue;École=École vélo')
        );

        self::assertSame([
            ['label' => 'Route longue', 'value' => 15],
            ['label' => 'Autre', 'value' => 3],
            ['label' => 'École vélo', 'value' => 2.5],
            ['label' => 'Gravel', 'value' => 0],
        ], $items);
        self::assertSame(20.5, array_sum(array_column($items, 'value')));
    }

    #[DataProvider('invalidValuesProvider')]
    public function testRejectsInvalidValuesStrictly(string $values): void
    {
        $this->expectException(ManualValuesException::class);
        ManualValuesParser::parse($values);
    }

    public static function invalidValuesProvider(): iterable
    {
        foreach (['', 'Route', '=20', 'Route=', 'Route=abc', 'Route=NaN', 'Route=Infinity',
            'Route=1e999', 'Route=20 personnes', 'Route=1;;Gravel=2'] as $value) {
            yield [$value];
        }
    }

    public function testManualBranchPrecedesEveryViewOrDatabaseOperation(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/plugins/content/contentbuilderng_cbstats/src/Extension/ContentbuilderngStats.php'
        );
        $manualBranch = strpos($source, 'return $this->renderManualStats(');

        self::assertNotFalse($manualBranch);
        self::assertGreaterThan($manualBranch, strpos($source, '$statsService->isFormDebugEnabled('));
        self::assertGreaterThan($manualBranch, strpos($source, '$this->canViewStats('));
        self::assertGreaterThan($manualBranch, strpos($source, '->getStatsPayload('));
    }
}
