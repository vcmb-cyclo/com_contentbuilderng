<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use CB\Plugin\Content\ContentbuilderngStats\Service\PiePresentationService;
use PHPUnit\Framework\TestCase;

final class CbStatsPiePresentationServiceTest extends TestCase
{
    public function testPreparesTotalPercentagesAndStableColorsFromNormalizedData(): void
    {
        $presentation = PiePresentationService::prepare([
            ['label' => 'Été', 'value' => 45],
            ['label' => 'Automne', 'value' => 37],
            ['label' => 'Hiver', 'value' => 25],
        ], 'en-GB');

        self::assertSame(107, $presentation['total']);
        self::assertSame([42.1, 34.6, 23.4], array_column($presentation['items'], 'percentage'));
        self::assertSame(['42.1', '34.6', '23.4'], array_column($presentation['items'], 'percentageLabel'));
        self::assertSame(['#2563eb', '#dc2626', '#059669'], array_column($presentation['items'], 'color'));
        self::assertSame('Été', $presentation['items'][0]['label']);
    }

    public function testHandlesEmptyNormalizedData(): void
    {
        self::assertSame(['total' => 0, 'items' => []], PiePresentationService::prepare([], 'en-GB'));
    }

    public function testExtendsPaletteWithoutBusinessValueMapping(): void
    {
        $fieldStats = [];

        for ($index = 0; $index < 14; $index++) {
            $fieldStats[] = ['label' => 'Value ' . $index, 'value' => 1];
        }

        $items = PiePresentationService::prepare($fieldStats, 'en-GB')['items'];

        self::assertCount(14, array_unique(array_column($items, 'color')));
        self::assertStringStartsWith('hsl(', $items[12]['color']);
    }

    public function testFormatsExactlyOneDecimalWithTheActiveLocale(): void
    {
        $fieldStats = [
            ['label' => 'A', 'value' => 9],
            ['label' => 'B', 'value' => 22],
        ];

        self::assertSame(
            ['29.0', '71.0'],
            array_column(PiePresentationService::prepare($fieldStats, 'en-GB')['items'], 'percentageLabel')
        );
        self::assertSame(
            ['29,0', '71,0'],
            array_column(PiePresentationService::prepare($fieldStats, 'fr-FR')['items'], 'percentageLabel')
        );
        self::assertSame(
            ['29,0', '71,0'],
            array_column(PiePresentationService::prepare($fieldStats, 'de-DE')['items'], 'percentageLabel')
        );
    }
}
