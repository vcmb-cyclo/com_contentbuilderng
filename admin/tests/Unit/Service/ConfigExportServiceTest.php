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
     * A section is "effective" only when it is explicitly selected AND has at least one
     * matching ID. A section with no IDs is silently dropped — the controller would have
     * nothing to export and must not produce an empty export file.
     *
     * @return array<string,array{0:list<string>,1:list<int>,2:list<int>,3:list<string>}>
     */
    public static function effectiveSectionsProvider(): array
    {
        return [
            // Basic inclusion: a known section paired with at least one ID is kept.
            'forms with ids is effective' => [
                ['forms'], [10, 20], [], ['forms'],
            ],
            // No IDs → nothing to export → section is dropped.
            'forms without ids is excluded' => [
                ['forms'], [], [], [],
            ],
            'storages with ids is effective' => [
                ['storages'], [], [5], ['storages'],
            ],
            'storages without ids is excluded' => [
                ['storages'], [], [], [],
            ],
            // Both sections present with IDs → both survive.
            'both sections with ids both effective' => [
                ['forms', 'storages'], [1], [2], ['forms', 'storages'],
            ],
            // Mixed: only the section that has IDs is kept.
            'only forms has ids, storages excluded' => [
                ['forms', 'storages'], [1], [], ['forms'],
            ],
            'only storages has ids, forms excluded' => [
                ['forms', 'storages'], [], [2], ['storages'],
            ],
            'empty selection returns empty' => [
                [], [], [], [],
            ],
            // Unrecognised section keys are silently ignored (not in ROOT_SECTIONS).
            'unknown section is ignored' => [
                ['unknown'], [1], [2], [],
            ],
            // Duplicate section keys must not produce duplicate entries in the result.
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
