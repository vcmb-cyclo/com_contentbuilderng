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

    /**
     * Minimal but realistic export payload: 2 forms, 3 elements, 1 list state,
     * 2 storages, 3 storage fields, and storage content with 2 entries.
     * IDs are intentionally non-sequential to catch off-by-one assumptions.
     */
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
    // Filtering by name restricts the forms rows, then cascades to all
    // form-dependent sections (elements, list_states, resource_access) using
    // the resolved form IDs — never the original payload IDs.
    // -------------------------------------------------------------------------

    public function testFilterByFormNameKeepsMatchingFormRows(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['contact'], []);
        $rows = $result['data']['forms']['rows'];

        self::assertCount(1, $rows);
        self::assertSame('contact', $rows[0]['name']);
    }

    // row_count must stay in sync with the actual filtered rows array.
    public function testFilterByFormNameExcludesNonMatchingRows(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['contact'], []);
        $rows = $result['data']['forms']['rows'];

        self::assertSame(1, $result['data']['forms']['row_count']);
        foreach ($rows as $row) {
            self::assertNotSame('survey', $row['name']);
        }
    }

    // Elements are linked by form_id; only the 2 elements of form 1 should survive.
    public function testFilterByFormCascadesToElements(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['contact'], []);
        $elementRows = $result['data']['elements']['rows'];

        self::assertCount(2, $elementRows);
        foreach ($elementRows as $row) {
            self::assertSame(1, $row['form_id']);
        }
    }

    // list_states are also form-dependent; filtering by 'survey' must keep its state.
    public function testFilterByFormCascadesToListStates(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['survey'], []);
        $rows = $result['data']['list_states']['rows'];

        self::assertCount(1, $rows);
        self::assertSame(2, $rows[0]['form_id']);
    }

    // The payload's filters.form_ids must be updated to reflect the filtered selection
    // so the importer knows which IDs to remap during apply.
    public function testFilterByFormUpdatesFormIdsInFilters(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], ['contact'], []);
        self::assertSame([1], $result['filters']['form_ids']);
    }

    // Empty name list means "no sub-selection" → all forms pass through unchanged.
    public function testNoFormNamesSkipsFormFiltering(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['forms'], [], []);
        self::assertCount(2, $result['data']['forms']['rows']);
    }

    // Providing names without selecting the 'forms' section must be a no-op.
    public function testFormSectionNotSelectedSkipsFormFiltering(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), [], ['contact'], []);
        self::assertCount(2, $result['data']['forms']['rows']);
    }

    // -------------------------------------------------------------------------
    // Storages filtering
    // Same cascade pattern as forms: storage rows → storage_fields → storage_content.
    // Storage content has an extra dimension: it is opt-in via $selectedStorageContentNames,
    // so it can be excluded even when a storage itself is included.
    // -------------------------------------------------------------------------

    public function testFilterByStorageNameKeepsMatchingStorageRows(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders']);
        $rows = $result['data']['storages']['rows'];

        self::assertCount(1, $rows);
        self::assertSame('orders', $rows[0]['name']);
    }

    // storage_fields are linked by storage_id; only the 2 fields of storage 10 survive.
    public function testFilterByStorageCascadesToStorageFields(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders']);
        $fieldRows = $result['data']['storage_fields']['rows'];

        self::assertCount(2, $fieldRows);
        foreach ($fieldRows as $row) {
            self::assertSame(10, $row['storage_id']);
        }
    }

    // filters.storage_ids must reflect the filtered selection for the importer's remap step.
    public function testFilterByStorageUpdatesStorageIdsInFilters(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['feedback']);
        self::assertSame([20], $result['filters']['storage_ids']);
    }

    // Storage content is always excluded when no storage content names are provided,
    // even if the storage itself was selected. This is the default "structure only" import.
    public function testStorageContentExcludedWhenNoStorageContentNamesGiven(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders']);
        self::assertSame([], $result['data']['storage_content']['storages']);
    }

    // When content names are explicitly provided, only the matching entries are kept.
    public function testStorageContentFilteredByNameWhenProvided(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders', 'feedback'], ['feedback']);
        $storages = $result['data']['storage_content']['storages'];

        self::assertCount(1, $storages);
        self::assertSame('feedback', $storages[0]['storage_name']);
    }

    // row_count on storage_content is the sum of individual entry row_counts, not a flat count.
    public function testStorageContentRowCountIsRecomputedAfterFilter(): void
    {
        $result = $this->service->filterPayload($this->buildPayload(), ['storages'], [], ['orders', 'feedback'], ['feedback']);
        self::assertSame(2, $result['data']['storage_content']['row_count']);
    }

    // -------------------------------------------------------------------------
    // No-op / defensive cases
    // -------------------------------------------------------------------------

    // A payload with no data sections must not crash — e.g. a partial or legacy export.
    public function testEmptyPayloadDataReturnsSafely(): void
    {
        $payload = ['filters' => [], 'data' => []];
        $result = $this->service->filterPayload($payload, ['forms', 'storages'], ['contact'], ['orders']);
        self::assertSame([], $result['data']);
    }

    // When no sections are selected the payload passes through entirely unmodified.
    public function testPayloadReturnedUnmodifiedWhenNoSectionsSelected(): void
    {
        $payload = $this->buildPayload();
        $result = $this->service->filterPayload($payload, [], [], []);
        self::assertSame($payload['data']['forms']['row_count'], $result['data']['forms']['row_count']);
        self::assertSame($payload['data']['storages']['row_count'], $result['data']['storages']['row_count']);
    }
}
