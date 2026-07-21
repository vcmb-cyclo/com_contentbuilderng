<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use CB\Component\Contentbuilderng\Site\Service\StatsFilterValueService;
use CB\Plugin\Content\ContentbuilderngStats\Service\TagSyntaxService;
use PHPUnit\Framework\TestCase;

final class CbStatsRc91B01Test extends TestCase
{
    private const ROOT = __DIR__ . '/../../../..';

    public function testSameFieldValueShorthandResolvesExactlyLikeCompleteFilter(): void
    {
        $shorthand = TagSyntaxService::resolveFilter(TagSyntaxService::parseAttributes(
            'id=15 field=Element-2 value="Dét* | 3 | 4" output=bar'
        ));
        $complete = TagSyntaxService::resolveFilter(TagSyntaxService::parseAttributes(
            'id=15 field=Element-2 filter[field]=Element-2 filter[value]="Dét* | 3 | 4" output=bar'
        ));

        self::assertSame($complete, $shorthand);
        self::assertSame(['field' => 'Element-2', 'value' => 'Dét* | 3 | 4'], $shorthand);
    }

    public function testFilterAlternativesWildcardAndExactValueRemainDistinct(): void
    {
        $service = new StatsFilterValueService();

        self::assertSame(
            ['Dét*', '3', '4'],
            array_map('strval', $service->parseAlternatives('Dét* | 3 | 4'))
        );
        self::assertTrue($service->hasWildcard('Dét*'));
        self::assertSame('Dét%', $service->toSqlLikePattern('Dét*'));
        self::assertFalse($service->hasWildcard('3'));
        self::assertSame('3', $service->toSqlLikePattern('3'));

        $statsSource = (string) file_get_contents(self::ROOT . '/site/src/Service/StatsService.php');
        self::assertStringContainsString('$filterValues->hasWildcard($value)', $statsSource);
        self::assertStringContainsString("? \$column . ' LIKE '", $statsSource);
        self::assertStringContainsString(": \$column . ' = '", $statsSource);
    }

    public function testBarUsesOneShotHorizontalAnimationLikePie(): void
    {
        $javascript = (string) file_get_contents(
            self::ROOT . '/plugins/content/contentbuilderng_cbstats/media/js/cbstats-bar.js'
        );

        self::assertStringNotContainsString('prefers-reduced-motion', $javascript);
        self::assertMatchesRegularExpression('/animation:\s*\{\s*duration:\s*900,/s', $javascript);
        self::assertStringNotContainsString('animations:', $javascript);
        self::assertStringContainsString('finalValues.map(() => 0)', $javascript);
        self::assertStringContainsString('chart.data.datasets[0].data = finalValues', $javascript);
        self::assertSame(2, substr_count($javascript, 'window.requestAnimationFrame'));
        self::assertStringContainsString("querySelectorAll('[data-cbstats-bar]').forEach(initialise)", $javascript);
        self::assertStringContainsString("root.dataset.cbstatsInitialised === 'true'", $javascript);
    }

    public function testBarSpacingIsCompactWithoutReducingAdaptiveChartHeight(): void
    {
        $css = (string) file_get_contents(
            self::ROOT . '/plugins/content/contentbuilderng_cbstats/media/css/cbstats-bar.css'
        );
        $javascript = (string) file_get_contents(
            self::ROOT . '/plugins/content/contentbuilderng_cbstats/media/js/cbstats-bar.js'
        );

        self::assertStringContainsString('calc(var(--cbstats-bar-items) * 30px + 48px)', $css);
        self::assertStringContainsString('calc(var(--cbstats-bar-items) * 34px + 48px)', $css);
        self::assertStringContainsString('width: min(100%, 760px)', $css);
        self::assertStringContainsString('categoryPercentage: 0.9', $javascript);
        self::assertStringContainsString('barPercentage: 0.9', $javascript);
        self::assertStringContainsString('maxBarThickness: 24', $javascript);
    }

    public function testManifestVersionAndHelpBlocksAreUniform(): void
    {
        $manifest = simplexml_load_file(
            self::ROOT . '/plugins/content/contentbuilderng_cbstats/contentbuilderng_cbstats.xml'
        );

        self::assertNotFalse($manifest);
        self::assertSame('6.1.7-RC91', (string) $manifest->version);

        $fields = $manifest->xpath('/extension/config/fields/fieldset/field');
        self::assertCount(6, $fields);
        foreach ($fields as $field) {
            self::assertSame('alert alert-info w-100', (string) $field['class']);
        }
    }

    public function testDistributedHelpContainsEscapedFilterExamples(): void
    {
        $crossField = '{CBStats id=15 field=Element-1 filter[field]=Element-2 filter[value]="Dét* | 3 | 4" output=bar}';
        $sameField = '{CBStats id=15 field=Element-2 value="Dét* | 3 | 4" output=bar}';

        foreach (['en-GB', 'fr-FR', 'de-DE'] as $locale) {
            $path = self::ROOT . '/plugins/content/contentbuilderng_cbstats/language/'
                . $locale . '/plg_content_contentbuilderng_cbstats.ini';
            $strings = parse_ini_file($path);

            self::assertIsArray($strings);
            $help = html_entity_decode(
                (string) $strings['PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_HELP_FILTERS_TEXT'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            self::assertStringContainsString('<code>' . $crossField . '</code>', $help);
            self::assertStringContainsString('<code>' . $sameField . '</code>', $help);
            self::assertSame(5, substr_count($help, '<br><code>'));
        }

        $description = (string) file_get_contents(
            self::ROOT . '/plugins/content/contentbuilderng_cbstats/docs/Gil_PLUGIN_DESCRIPTION.md'
        );
        self::assertStringContainsString($crossField, $description);
        self::assertStringContainsString($sameField, $description);
        self::assertSame(3, substr_count($description, "{CBStats id=25 field=Route output=pie add='100 km=-3'}"));
    }
}
