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

use CB\Component\Contentbuilderng\Administrator\Service\ConfigImportService;
use PHPUnit\Framework\TestCase;

final class ConfigImportServiceTest extends TestCase
{
    private ConfigImportService $service;

    protected function setUp(): void
    {
        $this->service = new ConfigImportService();
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function buildPayload(): array
    {
        return [
            'filters' => [
                'form_ids'     => [1, 2],
                'storage_ids'  => [10, 20],
            ],
            'data' => [
                'forms' => [
                    'type'      => 'table',
                    'row_count' => 2,
                    'rows'      => [
                        ['id' => 1, 'name' => 'contact', 'title' => 'Contact'],
                        ['id' => 2, 'name' => 'survey',  'title' => 'Survey'],
                    ],
                ],
                'elements' => [
                    'type'      => 'table',
                    'row_count' => 3,
                    'rows'      => [
                        ['id' => 10, 'form_id' => 1, 'name' => 'field_a'],
                        ['id' => 11, 'form_id' => 2, 'name' => 'field_b'],
                        ['id' => 12, 'form_id' => 1, 'name' => 'field_c'],
                    ],
                ],
                'list_states' => [
                    'type'      => 'table',
                    'row_count' => 1,
                    'rows'      => [
                        ['id' => 5, 'form_id' => 2, 'name' => 'state_x'],
                    ],
                ],
                'resource_access' => [
                    'type'      => 'table',
                    'row_count' => 0,
                    'rows'      => [],
                ],
                'storages' => [
                    'type'      => 'table',
                    'row_count' => 2,
                    'rows'      => [
                        ['id' => 10, 'name' => 'orders',   'title' => 'Orders'],
                        ['id' => 20, 'name' => 'feedback', 'title' => 'Feedback'],
                    ],
                ],
                'storage_fields' => [
                    'type'      => 'table',
                    'row_count' => 3,
                    'rows'      => [
                        ['id' => 100, 'storage_id' => 10, 'name' => 'amount'],
                        ['id' => 101, 'storage_id' => 20, 'name' => 'comment'],
                        ['id' => 102, 'storage_id' => 10, 'name' => 'status'],
                    ],
                ],
                'storage_content' => [
                    'type'      => 'storage_content',
                    'row_count' => 5,
                    'storages'  => [
                        ['storage_id' => 10, 'storage_name' => 'orders',   'row_count' => 3, 'rows' => [['id' => 1], ['id' => 2], ['id' => 3]]],
                        ['storage_id' => 20, 'storage_name' => 'feedback', 'row_count' => 2, 'rows' => [['id' => 1], ['id' => 2]]],
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Forms filtering
    // -------------------------------------------------------------------------

    public function testFilterByFormNameKeepsMatchingFormRows(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['contact'], []);
        $rows = $result['data']['forms']['rows'];

        self::assertCount(1, $rows);
        self::assertSame('contact', $rows[0]['name']);
    }

    public function testFilterByFormNameExcludesNonMatchingRows(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['contact'], []);
        $rows = $result['data']['forms']['rows'];

        self::assertSame(1, $result['data']['forms']['row_count']);
        foreach ($rows as $row) {
            self::assertNotSame('survey', $row['name']);
        }
    }

    public function testFilterByFormCascadesToElements(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['contact'], []);
        $elementRows = $result['data']['elements']['rows'];

        self::assertCount(2, $elementRows);
        foreach ($elementRows as $row) {
            self::assertSame(1, $row['form_id']);
        }
    }

    public function testFilterByFormCascadesToListStates(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['survey'], []);
        $rows = $result['data']['list_states']['rows'];

        self::assertCount(1, $rows);
        self::assertSame(2, $rows[0]['form_id']);
    }

    public function testFilterByFormUpdatesFormIdsInFilters(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['contact'], []);
        self::assertSame([1], $result['filters']['form_ids']);
    }

    public function testNoFormNamesSkipsFormFiltering(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], [], []);
        self::assertCount(2, $result['data']['forms']['rows']);
    }

    public function testFormSectionNotSelectedSkipsFormFiltering(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), [], ['contact'], []);
        self::assertCount(2, $result['data']['forms']['rows']);
    }

    // -------------------------------------------------------------------------
    // Storages filtering
    // -------------------------------------------------------------------------

    public function testFilterByStorageNameKeepsMatchingStorageRows(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders']);
        $rows = $result['data']['storages']['rows'];

        self::assertCount(1, $rows);
        self::assertSame('orders', $rows[0]['name']);
    }

    public function testFilterByStorageCascadesToStorageFields(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders']);
        $fieldRows = $result['data']['storage_fields']['rows'];

        self::assertCount(2, $fieldRows);
        foreach ($fieldRows as $row) {
            self::assertSame(10, $row['storage_id']);
        }
    }

    public function testFilterByStorageUpdatesStorageIdsInFilters(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['feedback']);
        self::assertSame([20], $result['filters']['storage_ids']);
    }

    public function testStorageContentExcludedWhenNoStorageContentNamesGiven(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders']);
        self::assertSame([], $result['data']['storage_content']['storages']);
    }

    public function testStorageContentFilteredByNameWhenProvided(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders', 'feedback'], ['feedback']);
        $storages = $result['data']['storage_content']['storages'];

        self::assertCount(1, $storages);
        self::assertSame('feedback', $storages[0]['storage_name']);
    }

    public function testStorageContentRowCountIsRecomputedAfterFilter(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders', 'feedback'], ['feedback']);
        self::assertSame(2, $result['data']['storage_content']['row_count']);
    }

    // -------------------------------------------------------------------------
    // No-op cases
    // -------------------------------------------------------------------------

    public function testEmptyPayloadDataReturnsSafely(): void
    {
        $payload = ['filters' => [], 'data' => []];
        $result = $this->service->filterPayload($payload, ['forms', 'storages'], ['contact'], ['orders']);
        self::assertSame([], $result['data']);
    }

    public function testPayloadReturnedUnmodifiedWhenNoSectionsSelected(): void
    {
        $payload = $this->buildPayload();
        $result = $this->service->filterPayload($payload, [], [], []);
        self::assertSame($payload['data']['forms']['row_count'], $result['data']['forms']['row_count']);
        self::assertSame($payload['data']['storages']['row_count'], $result['data']['storages']['row_count']);
    }
}
