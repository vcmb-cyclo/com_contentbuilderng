<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\ConfigExportService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigExportServiceTest extends TestCase
{
    private ConfigExportService $service;

    protected function setUp(): void
    {
        $this->service = new ConfigExportService();
    }

    /**
     * @return array<string,array{0:list<string>,1:list<int>,2:list<int>,3:list<string>}>
     */
    public static function effectiveSectionsProvider(): array
    {
        return [
            'forms with ids is effective' => [
                ['forms'], [10, 20], [], ['forms'],
            ],
            'forms without ids is excluded' => [
                ['forms'], [], [], [],
            ],
            'storages with ids is effective' => [
                ['storages'], [], [5], ['storages'],
            ],
            'storages without ids is excluded' => [
                ['storages'], [], [], [],
            ],
            'both sections with ids both effective' => [
                ['forms', 'storages'], [1], [2], ['forms', 'storages'],
            ],
            'only forms has ids, storages excluded' => [
                ['forms', 'storages'], [1], [], ['forms'],
            ],
            'only storages has ids, forms excluded' => [
                ['forms', 'storages'], [], [2], ['storages'],
            ],
            'empty selection returns empty' => [
                [], [], [], [],
            ],
            'unknown section is ignored' => [
                ['unknown'], [1], [2], [],
            ],
            'duplicates are collapsed' => [
                ['forms', 'forms'], [1], [], ['forms'],
            ],
        ];
    }

    /**
     * @param list<string> $selectedSections
     * @param list<int>    $selectedFormIds
     * @param list<int>    $selectedStorageIds
     * @param list<string> $expected
     */
    #[DataProvider('effectiveSectionsProvider')]
    public function testResolveEffectiveSections(
        array $selectedSections,
        array $selectedFormIds,
        array $selectedStorageIds,
        array $expected
    ): void {
        self::assertSame($expected, $this->service->resolveEffectiveSections($selectedSections, $selectedFormIds, $selectedStorageIds));
    }
}
