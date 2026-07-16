<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use CB\Plugin\Content\ContentbuilderngStats\Service\TotalPresentationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/plugins/content/contentbuilderng_cbstats/src/Service/TotalPresentationService.php';

final class CbStatsTotalPresentationServiceTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function testFormatsLocalizedAndCustomLabels(
        string $title,
        string $defaultLabel,
        string $separator,
        string $expected
    ): void {
        self::assertSame($expected, TotalPresentationService::formatLabel($title, $defaultLabel, $separator));
    }

    public static function labelProvider(): array
    {
        return [
            'English default' => ['', 'Total', ':', 'Total:'],
            'French default' => ['', 'Total', "\u{202F}:", "Total\u{202F}:"],
            'French custom separator' => ['👥 Total des inscrits', 'Total', "\u{202F}:", "👥 Total des inscrits\u{202F}:"],
            'existing compact colon' => ['👥 Total des inscrits:', 'Total', "\u{202F}:", '👥 Total des inscrits:'],
            'existing spaced colon' => ['👥 Total des inscrits :  ', 'Total', "\u{202F}:", '👥 Total des inscrits :'],
        ];
    }

    #[DataProvider('backgroundProvider')]
    public function testValidatesBackgroundValues(string $input, string $expected): void
    {
        self::assertSame($expected, TotalPresentationService::validateBackground($input));
    }

    public static function backgroundProvider(): array
    {
        return [
            ['transparent', 'transparent'],
            ['#fff', '#fff'],
            ['#12abef80', '#12abef80'],
            ['rgb(12, 34, 255)', 'rgb(12, 34, 255)'],
            ['rgba(12, 34, 56, 0.5)', 'rgba(12, 34, 56, 0.5)'],
            ['white', 'white'],
            ['rgb(999, 0, 0)', ''],
            ['url(javascript:alert(1))', ''],
            ['red; color: black', ''],
            ['var(--bs-tertiary-bg)', ''],
        ];
    }
}
