<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use CB\Plugin\Content\ContentbuilderngStats\Service\ManualExportService;
use CB\Plugin\Content\ContentbuilderngStats\Service\TagSyntaxService;
use CB\Component\Contentbuilderng\Site\Service\StatsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CbStatsManualExportServiceTest extends TestCase
{
    #[DataProvider('exportValueProvider')]
    public function testOnlyManualRequestsExport(string $value, bool $expected): void
    {
        self::assertSame($expected, ManualExportService::isRequested($value));
    }

    public static function exportValueProvider(): iterable
    {
        yield ['manual', true];
        yield ['MANUAL', true];
        yield [' manual ', true];
        foreach (['', 'yes', 'copy', 'true', '1', 'test'] as $value) {
            yield [$value, false];
        }
    }

    public function testTagNameOptionNamesAndClosedValuesIgnoreCase(): void
    {
        foreach (['CBStats', 'cbstats', 'CBSTATS'] as $tagName) {
            self::assertSame(1, preg_match(TagSyntaxService::TAG_PATTERN, '{' . $tagName . ' id=15}'));
        }

        $attributes = TagSyntaxService::parseAttributes(
            ' ID=15 Field=GroupeVcmb OUTPUT=Bar Sort=VALUE DIR=Desc EXPORT=MANUAL SOURCE=Manual'
            . ' Title="Total VCMB" Titles="Route=Groupe Sportif" Values="Route=30"'
            . ' FILTER[value]="GroupeVcmb" ADD="GroupeVcmb=2"'
        );

        self::assertSame('GroupeVcmb', $attributes['field']);
        self::assertSame('Total VCMB', $attributes['title']);
        self::assertSame('Route=Groupe Sportif', $attributes['titles']);
        self::assertSame('Route=30', $attributes['values']);
        self::assertSame('GroupeVcmb', $attributes['filter[value]']);
        self::assertSame('GroupeVcmb=2', $attributes['add']);
        foreach (['output' => 'bar', 'sort' => 'value', 'dir' => 'desc', 'export' => 'manual', 'source' => 'manual'] as $key => $expected) {
            self::assertSame($expected, TagSyntaxService::normalizeKeyword($attributes[$key]));
        }
    }

    public function testFieldResolverAlreadyAcceptsCaseAliasesWithoutChangingTheRequestedValue(): void
    {
        $service = (new \ReflectionClass(StatsService::class))->newInstanceWithoutConstructor();
        $normalizer = new \ReflectionMethod(StatsService::class, 'normalizeStatsFieldName');

        self::assertSame(
            $normalizer->invoke($service, 'GroupeVcmb'),
            $normalizer->invoke($service, 'groupevcmb')
        );
        self::assertSame('GroupeVcmb', TagSyntaxService::parseAttributes('field=GroupeVcmb')['field']);
    }

    #[DataProvider('outputProvider')]
    public function testBuildsSupportedFrozenOutputSyntax(string $output): void
    {
        self::assertSame(
            '{CBStats source=manual output=' . $output . ' values="Route=45;Gravel=20"}',
            ManualExportService::buildSyntax([
                ['label' => 'Route', 'value' => 45],
                ['label' => 'Gravel', 'value' => 20],
            ], $output)
        );
    }

    public static function outputProvider(): iterable
    {
        yield ['pie'];
        yield ['bar'];
        yield ['table'];
    }

    public function testPreservesFinalOrderValuesDecimalsUnicodeAndVisualOptions(): void
    {
        self::assertSame(
            '{CBStats source=manual output=pie values="🚴=0;École=2.5;Route=15" title="👥 Total des inscrits" background="transparent"}',
            ManualExportService::buildSyntax([
                ['label' => '🚴', 'value' => 0],
                ['label' => 'École', 'value' => 2.5],
                ['label' => 'Route', 'value' => 15],
            ], 'pie', '👥 Total des inscrits', 'transparent')
        );
    }

    public function testUsesTheRc83EscapingContract(): void
    {
        self::assertSame(
            '{CBStats source=manual output=table values="200 km \\; A\\=B=30;Chemin\\\\retour=4"}',
            ManualExportService::buildSyntax([
                ['label' => '200 km ; A=B', 'value' => 30],
                ['label' => 'Chemin\\retour', 'value' => 4],
            ], 'table')
        );
    }

    public function testEscapesQuotesWithoutHtmlOrJavaScriptInterpretation(): void
    {
        self::assertSame(
            '{CBStats source=manual output=bar values="&&lt;tag&gt; \\"test\\"=1" title="L\\"été d\'Alice 👥"}',
            ManualExportService::buildSyntax([
                ['label' => '&<tag> "test"', 'value' => 1],
            ], 'bar', 'L"été d\'Alice 👥')
        );
    }

    public function testFrozenSyntaxOmitsDynamicTransformationParameters(): void
    {
        $syntax = ManualExportService::buildSyntax([['label' => 'Final', 'value' => 9]], 'pie');

        foreach ([' id=', ' field=', ' filter[', ' add=', ' titles=', ' sort=', ' dir=', ' limit=', ' export='] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $syntax);
        }
    }

    #[DataProvider('finalTitleOutputProvider')]
    public function testExportsFinalTitlesAdditionsAndSortedOrder(string $output): void
    {
        $items = StatsService::normalizeFieldStats(
            ['100 km' => 45, '150 km' => 47, '200 km' => 38],
            'value',
            'desc',
            'fr-FR',
            StatsService::parseFieldStatsAdditions('100 km=2'),
            StatsService::parseFieldStatsTitles(
                '100 km=Découverte;150 km=Sportif;200 km=Endurance'
            )
        );

        self::assertSame([
            ['label' => 'Découverte', 'value' => 47],
            ['label' => 'Sportif', 'value' => 47],
            ['label' => 'Endurance', 'value' => 38],
        ], $items);
        $syntax = ManualExportService::buildSyntax($items, $output);
        self::assertSame(
            '{CBStats source=manual output=' . $output
                . ' values="Découverte=47;Sportif=47;Endurance=38"}',
            $syntax
        );
        self::assertStringNotContainsString('100 km', $syntax);
        self::assertStringNotContainsString('titles=', $syntax);
    }

    public static function finalTitleOutputProvider(): iterable
    {
        yield ['pie'];
        yield ['bar'];
        yield ['table'];
    }

    public function testRendererOnlyLoadsExportAssetsWhenRequested(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/plugins/content/contentbuilderng_cbstats/src/Extension/ContentbuilderngStats.php'
        );

        self::assertStringContainsString("=== 'manual'", $source);
        self::assertStringContainsString('$exportManual ? $this->renderManualExport', $source);
        self::assertStringContainsString('if ($exportManual)', $source);
    }

    public function testCopyScriptSupportsIndependentInstancesAndFailureSelection(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/plugins/content/contentbuilderng_cbstats/media/js/cbstats-manual-export.js'
        );

        self::assertStringContainsString("closest('.cbstats-manual-export')", $source);
        self::assertStringContainsString('navigator.clipboard.writeText(syntax.value)', $source);
        self::assertStringContainsString('syntax.select()', $source);
        self::assertStringContainsString('window.cbstatsManualExportReady', $source);
    }

    public function testCopyButtonIsCenteredByDedicatedFlexRule(): void
    {
        $css = (string) file_get_contents(
            dirname(__DIR__, 4) . '/plugins/content/contentbuilderng_cbstats/media/css/cbstats-manual-export.css'
        );

        self::assertMatchesRegularExpression(
            '/\.cbstats-manual-export-actions\s*\{[^}]*display:\s*flex;[^}]*justify-content:\s*center;/s',
            $css
        );
    }
}
