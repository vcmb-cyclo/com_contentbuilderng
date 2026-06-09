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

use CB\Component\Contentbuilderng\Site\Service\SparseFieldsetService;
use PHPUnit\Framework\TestCase;

final class SparseFieldsetServiceTest extends TestCase
{
    private SparseFieldsetService $service;

    protected function setUp(): void
    {
        $this->service = new SparseFieldsetService();
    }

    public function testFiltersNamedResourceFields(): void
    {
        $payload = [
            'records' => [
                'total' => 31,
                'published' => 9,
                'unpublished' => 22,
            ],
            'ratings' => ['average' => 0.0],
        ];

        self::assertSame([
            'records' => [
                'total' => 31,
                'published' => 9,
            ],
        ], $this->service->filter($payload, ['records' => 'total, published']));
    }

    public function testFiltersListItemPropertiesAndBusinessValues(): void
    {
        $payload = [
            'items' => [
                [
                    'record_id' => 12,
                    'values' => [
                        'title' => 'Example',
                        'slug' => 'example',
                        'state' => 1,
                    ],
                ],
            ],
            'pagination' => ['total' => 1],
        ];

        self::assertSame([
            'items' => [
                [
                    'record_id' => 12,
                    'values' => [
                        'title' => 'Example',
                        'slug' => 'example',
                    ],
                ],
            ],
        ], $this->service->filter($payload, ['items' => 'record_id,title,slug']));
    }

    public function testSupportsMultipleRequestedResources(): void
    {
        $payload = [
            'records' => ['total' => 31, 'published' => 9],
            'ratings' => ['average' => 4.5, 'rating_count' => 2],
            'languages' => ['*' => 31],
        ];

        self::assertSame([
            'records' => ['total' => 31],
            'ratings' => ['average' => 4.5],
        ], $this->service->filter($payload, [
            'records' => 'total',
            'ratings' => 'average',
        ]));
    }

    public function testUnknownResourceReturnsNoData(): void
    {
        self::assertSame([], $this->service->filter(
            ['records' => ['total' => 31]],
            ['unknown' => 'id']
        ));
    }
}
