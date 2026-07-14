<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

/**
 * Builds the OpenAPI 3.0 description of the com_contentbuilderng JSON API.
 *
 * The API dispatches every operation through a single site entry point
 * (index.php?option=com_contentbuilderng&task=api.display, plus an
 * "action" query parameter for the non-CRUD operations), which does not map
 * onto clean per-resource OpenAPI paths. Distinct query-string combinations
 * are used as literal path keys instead: this is not RFC 6570-conformant,
 * but it is a common, pragmatic convention for documenting legacy
 * query-dispatched APIs and renders correctly in Swagger UI / Redoc.
 *
 * Every parameter, permission and response shape below mirrors
 * docs/en/api-json.md, the canonical source of truth for this API; keep
 * both in sync when the controller's behaviour changes.
 */
final class OpenApiSpecService
{
    public function build(string $componentVersion): array
    {
        $base = rtrim((string) Uri::root(), '/') . '/index.php';
        $version = trim($componentVersion) !== '' ? trim($componentVersion) : '0.0.0';

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'ContentBuilder NG JSON API',
                'version' => $version,
                'description' => 'Read and update ContentBuilder NG view records as JSON. '
                    . 'Every operation is dispatched through index.php with option=com_contentbuilderng '
                    . '&task=api.display (or api.update); this document lists one path per '
                    . 'query-string combination for clarity. See docs/en/api-json.md in the '
                    . 'component repository for the full narrative documentation.',
            ],
            'servers' => [
                ['url' => $base, 'description' => 'Site front-end entry point'],
            ],
            'components' => $this->buildComponents(),
            'paths' => $this->buildPaths(),
        ];
    }

    private function buildComponents(): array
    {
        return [
            'schemas' => [
                'SuccessEnvelope' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => true],
                        'messages' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'data' => ['type' => 'object'],
                    ],
                ],
                'CbstatsScalarSuccessEnvelope' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => true],
                        'messages' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'data' => [
                            'oneOf' => [
                                ['type' => 'number', 'example' => 31],
                                ['type' => 'string', 'example' => 'Public contacts'],
                            ],
                        ],
                    ],
                ],
                'ErrorEnvelope' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'messages' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['View not found']],
                        'data' => ['nullable' => true, 'example' => null],
                    ],
                ],
                'FieldValue' => [
                    'type' => 'object',
                    'description' => 'Shape of a field entry when verbose=1 is requested.',
                    'properties' => [
                        'reference_id' => ['type' => 'string', 'example' => '17'],
                        'label' => ['type' => 'string', 'example' => 'Name'],
                        'value' => ['type' => 'string', 'example' => 'Example'],
                    ],
                ],
                'UpdatePayload' => [
                    'type' => 'object',
                    'required' => ['fields'],
                    'properties' => [
                        'fields' => [
                            'type' => 'object',
                            'description' => 'Keys are field names or numeric field references. '
                                . 'Unauthorized fields are ignored; the request is refused when '
                                . 'no authorized field remains.',
                            'additionalProperties' => ['type' => 'string'],
                            'example' => ['Name' => 'New name', 'Email' => 'contact@example.test'],
                        ],
                    ],
                ],
            ],
            'parameters' => [
                'OptionParam' => [
                    'name' => 'option', 'in' => 'query', 'required' => true,
                    'schema' => ['type' => 'string', 'enum' => ['com_contentbuilderng']],
                ],
                'IdParam' => [
                    'name' => 'id', 'in' => 'query', 'required' => true,
                    'description' => 'The ContentBuilder NG view ID.',
                    'schema' => ['type' => 'integer'], 'example' => 3,
                ],
                'FormatParam' => [
                    'name' => 'format', 'in' => 'query', 'required' => false,
                    'description' => 'Add format=json when required by the Joomla routing or integration context.',
                    'schema' => ['type' => 'string', 'enum' => ['json']],
                ],
                'RecordIdParam' => [
                    'name' => 'record_id', 'in' => 'query', 'required' => false,
                    'description' => 'Present: read/update one record (detail mode). Absent: read the paginated list.',
                    'schema' => ['type' => 'integer'], 'example' => 123,
                ],
                'VerboseParam' => [
                    'name' => 'verbose', 'in' => 'query', 'required' => false,
                    'description' => 'With verbose=1, each field is returned as {reference_id, label, value} instead of a plain value.',
                    'schema' => ['type' => 'integer', 'enum' => [0, 1]],
                ],
                'ListLimitParam' => [
                    'name' => 'list[limit]', 'in' => 'query', 'required' => false,
                    'schema' => ['type' => 'integer'], 'example' => 20,
                ],
                'ListStartParam' => [
                    'name' => 'list[start]', 'in' => 'query', 'required' => false,
                    'schema' => ['type' => 'integer'], 'example' => 0,
                ],
                'FieldsItemsParam' => [
                    'name' => 'fields[items]', 'in' => 'query', 'required' => false,
                    'description' => 'Sparse fieldset: comma-separated keys to keep in data.items entries.',
                    'schema' => ['type' => 'string'], 'example' => 'record_id,Name,Email',
                ],
                'FieldsFieldsParam' => [
                    'name' => 'fields[fields]', 'in' => 'query', 'required' => false,
                    'description' => 'Sparse fieldset: comma-separated keys to keep in data.fields.',
                    'schema' => ['type' => 'string'], 'example' => 'Name,Email',
                ],
                'FieldsRecordsParam' => [
                    'name' => 'fields[records]', 'in' => 'query', 'required' => false,
                    'description' => 'Sparse fieldset: comma-separated keys to keep in data.records (stats).',
                    'schema' => ['type' => 'string'], 'example' => 'total,published',
                ],
                'FieldsRatingsParam' => [
                    'name' => 'fields[ratings]', 'in' => 'query', 'required' => false,
                    'description' => 'Sparse fieldset: comma-separated keys to keep in data.ratings (stats).',
                    'schema' => ['type' => 'string'], 'example' => 'average',
                ],
                'StatsFieldParam' => [
                    'name' => 'field', 'in' => 'query', 'required' => false,
                    'description' => 'Group stats by this field (resolved by reference, name, or label; '
                        . 'must be published and API-authorized). Adds sum/min/max to the field payload '
                        . 'when every distinct value is numeric or an ISO date.',
                    'schema' => ['type' => 'string'], 'example' => 'Category',
                ],
                'StatsFilterFieldParam' => [
                    'name' => 'filter[field]', 'in' => 'query', 'required' => false,
                    'schema' => ['type' => 'string'], 'example' => 'Status',
                ],
                'StatsFilterValueParam' => [
                    'name' => 'filter[value]', 'in' => 'query', 'required' => false,
                    'description' => 'Leading/trailing spaces ignored. "*" matches any character sequence; '
                        . '"|" separates alternatives, e.g. "Open* | Pending".',
                    'schema' => ['type' => 'string'], 'example' => 'Open* | Pending',
                ],
                'CbstatsOutputParam' => [
                    'name' => 'output', 'in' => 'query', 'required' => false,
                    'description' => 'CBStats URL data output. Defaults to json. HTML and chart outputs are not supported.',
                    'schema' => ['type' => 'string', 'enum' => ['json', 'total', 'sum', 'min', 'max', 'form_name']],
                    'example' => 'json',
                ],
                'CbstatsSortParam' => [
                    'name' => 'sort', 'in' => 'query', 'required' => false,
                    'description' => 'Sort normalized JSON field statistics. Defaults to none, which preserves natural order.',
                    'schema' => ['type' => 'string', 'enum' => ['none', 'title', 'value']], 'example' => 'title',
                ],
                'CbstatsDirParam' => [
                    'name' => 'dir', 'in' => 'query', 'required' => false,
                    'description' => 'JSON sort direction. Defaults to asc.',
                    'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']], 'example' => 'asc',
                ],
                'CbstatsAddParam' => [
                    'name' => 'add', 'in' => 'query', 'required' => false,
                    'description' => 'Signed JSON count deltas as Label=SignedInteger entries separated by semicolons. '
                        . 'A negative final result is temporarily normalized to zero before sorting and output.',
                    'schema' => ['type' => 'string'], 'example' => '1=-2;2=3',
                ],
                'CbstatsTitlesParam' => [
                    'name' => 'titles', 'in' => 'query', 'required' => false,
                    'description' => 'JSON display-label mappings as Original=Display title entries separated by semicolons. '
                        . 'Mappings apply after add and before sorting and do not merge categories.',
                    'schema' => ['type' => 'string'], 'example' => '1=Group 1;2=Group 2',
                ],
            ],
            'responses' => [
                'ErrorResponse' => [
                    'description' => 'Failure envelope. HTTP status is set for codes from 400 to 599.',
                    'content' => [
                        'application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']],
                    ],
                ],
            ],
        ];
    }

    private function buildPaths(): array
    {
        $optionRef = ['$ref' => '#/components/parameters/OptionParam'];
        $idRef = ['$ref' => '#/components/parameters/IdParam'];
        $formatRef = ['$ref' => '#/components/parameters/FormatParam'];
        $verboseRef = ['$ref' => '#/components/parameters/VerboseParam'];
        $recordIdRef = ['$ref' => '#/components/parameters/RecordIdParam'];
        $errorResponse = ['$ref' => '#/components/responses/ErrorResponse'];

        return [
            '/index.php?task=api.display (list / detail)' => [
                'get' => [
                    'summary' => 'Read the paginated list, or one record when record_id is present',
                    'description' => 'Permissions: API + View + List Access (list); API + View (detail).',
                    'parameters' => [
                        $optionRef,
                        ['name' => 'task', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['api.display']]],
                        $idRef,
                        $formatRef,
                        $recordIdRef,
                        ['$ref' => '#/components/parameters/ListLimitParam'],
                        ['$ref' => '#/components/parameters/ListStartParam'],
                        $verboseRef,
                        ['$ref' => '#/components/parameters/FieldsItemsParam'],
                        ['$ref' => '#/components/parameters/FieldsFieldsParam'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'List or detail payload.',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'],
                                    'examples' => [
                                        'list' => ['value' => [
                                            'success' => true, 'messages' => [],
                                            'data' => [
                                                'items' => [['record_id' => 123, 'values' => ['Name' => 'Example']]],
                                                'pagination' => ['total' => 1, 'limit' => 20, 'start' => 0],
                                            ],
                                        ]],
                                        'detail' => ['value' => [
                                            'success' => true, 'messages' => [],
                                            'data' => [
                                                'record_id' => 123, 'form_id' => 3,
                                                'fields' => ['Name' => 'Example'],
                                                'navigation' => ['previous' => 122, 'next' => 124],
                                            ],
                                        ]],
                                    ],
                                ],
                            ],
                        ],
                        'default' => $errorResponse,
                    ],
                ],
                'put' => $this->buildUpdateOperation('PUT'),
                'patch' => $this->buildUpdateOperation('PATCH'),
                'post' => $this->buildUpdateOperation('POST'),
            ],
            '/index.php?task=api.display&action=get-unique-values' => [
                'get' => [
                    'summary' => 'Distinct values of a field',
                    'description' => 'Permissions: API + List Access. Both referenced fields must be API-authorized.',
                    'parameters' => [
                        $optionRef,
                        ['name' => 'task', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['api.display']]],
                        $idRef,
                        $formatRef,
                        ['name' => 'action', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['get-unique-values']]],
                        ['name' => 'field_reference_id', 'in' => 'query', 'required' => true, 'description' => 'Requested field reference.', 'schema' => ['type' => 'string'], 'example' => '17'],
                        ['name' => 'where_field', 'in' => 'query', 'required' => false, 'description' => 'Optional condition field.', 'schema' => ['type' => 'string']],
                        ['name' => 'where', 'in' => 'query', 'required' => false, 'description' => 'Optional condition value.', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Distinct values for the requested field.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'], 'example' => [
                                'success' => true, 'messages' => [],
                                'data' => ['code' => 0, 'field_reference_id' => '17', 'msg' => ['Value A', 'Value B']],
                            ]]],
                        ],
                        'default' => $errorResponse,
                    ],
                ],
            ],
            '/index.php?task=api.display&action=rating' => [
                'post' => [
                    'summary' => 'Cast a rating vote',
                    'description' => 'Permissions: API + Rating. Requires a valid Joomla CSRF token '
                        . '(Session::checkToken); returns JINVALID_TOKEN (403) otherwise. The rating '
                        . 'level count comes from the view (rating_slots); repeated votes are limited '
                        . 'by session and IP.',
                    'parameters' => [
                        $optionRef,
                        ['name' => 'task', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['api.display']]],
                        $idRef,
                        $formatRef,
                        ['name' => 'action', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['rating']]],
                        ['name' => 'record_id', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer'], 'example' => 123],
                        ['name' => 'rate', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer'], 'example' => 5],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Vote accepted.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessEnvelope']]]],
                        'default' => $errorResponse,
                    ],
                ],
            ],
            '/index.php?task=api.display&action=stats' => [
                'get' => [
                    'summary' => 'View statistics, optionally grouped and filtered by field',
                    'description' => 'Permission: Stats only.',
                    'parameters' => [
                        $optionRef,
                        ['name' => 'task', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['api.display']]],
                        $idRef,
                        $formatRef,
                        ['name' => 'action', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['stats']]],
                        ['$ref' => '#/components/parameters/StatsFieldParam'],
                        ['$ref' => '#/components/parameters/StatsFilterFieldParam'],
                        ['$ref' => '#/components/parameters/StatsFilterValueParam'],
                        ['$ref' => '#/components/parameters/FieldsRecordsParam'],
                        ['$ref' => '#/components/parameters/FieldsRatingsParam'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Aggregated statistics for the view.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'], 'example' => [
                                'success' => true, 'messages' => [],
                                'data' => [
                                    'form' => ['id' => 3, 'name' => 'Contacts', 'title' => 'Public contacts'],
                                    'records' => [
                                        'total' => 31, 'published' => 9, 'unpublished' => 22, 'future' => 0,
                                        'edited' => 5, 'scheduled' => 0, 'expired' => 0, 'last_update' => '2026-06-04 19:01:43',
                                    ],
                                    'ratings' => ['rated_records' => 0, 'rating_count' => 0, 'rating_sum' => 0, 'average' => 0],
                                    'languages' => ['*' => 31],
                                    'field' => [
                                        'requested' => 'Route', 'reference_id' => 17, 'name' => 'Route', 'label' => 'Route',
                                        'total' => 31, 'sum' => null, 'min' => null, 'max' => null,
                                        'values' => ['200 km' => 12, '300 km' => 19],
                                    ],
                                ],
                            ]]],
                        ],
                        'default' => $errorResponse,
                    ],
                ],
            ],
            '/index.php?task=api.display&action=cbstats' => [
                'get' => [
                    'summary' => 'CBStats URL data outputs',
                    'description' => 'Permission: Stats only; the general API permission is not additionally required. '
                        . 'Requested fields must be published and API/Stats-enabled. output=json returns the same raw normalized array as '
                        . '{CBStats id=ViewID field=FieldName output=json}. Scalar outputs use the standard success '
                        . 'envelope. Errors stay concise unless view DEBUG enables safe 4xx diagnostics; DEBUG never changes permissions.',
                    'parameters' => [
                        $optionRef,
                        ['name' => 'task', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['api.display']]],
                        $idRef,
                        $formatRef,
                        ['name' => 'action', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['cbstats']]],
                        [
                            'name' => 'field', 'in' => 'query', 'required' => false,
                            'description' => 'Required for json, sum, min and max; not required for total or form_name. '
                                . 'Resolved by reference, name, or label; must be published and API-authorized.',
                            'schema' => ['type' => 'string'], 'example' => 'FieldName',
                        ],
                        ['$ref' => '#/components/parameters/CbstatsOutputParam'],
                        ['$ref' => '#/components/parameters/StatsFilterFieldParam'],
                        ['$ref' => '#/components/parameters/StatsFilterValueParam'],
                        ['$ref' => '#/components/parameters/CbstatsSortParam'],
                        ['$ref' => '#/components/parameters/CbstatsDirParam'],
                        ['$ref' => '#/components/parameters/CbstatsAddParam'],
                        ['$ref' => '#/components/parameters/CbstatsTitlesParam'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Raw normalized records for json, or a success envelope for scalar outputs.',
                            'content' => ['application/json' => ['schema' => [
                                'oneOf' => [
                                    [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'label' => ['type' => 'string'],
                                                'value' => ['type' => 'integer'],
                                            ],
                                        ],
                                    ],
                                    ['$ref' => '#/components/schemas/CbstatsScalarSuccessEnvelope'],
                                ],
                            ], 'example' => [
                                ['label' => 'Value A', 'value' => 12],
                                ['label' => 'Value B', 'value' => 7],
                            ]]],
                        ],
                        'default' => $errorResponse,
                    ],
                ],
            ],
        ];
    }

    private function buildUpdateOperation(string $method): array
    {
        return [
            'summary' => $method . ' — update one record',
            'description' => 'Permissions: API + Edit. record_id is required. Keys can be field names '
                . 'or recognized numeric field references. Unauthorized fields are ignored; the request '
                . 'is refused when no authorized field remains.',
            'parameters' => [
                ['$ref' => '#/components/parameters/OptionParam'],
                ['name' => 'task', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['api.display']]],
                ['$ref' => '#/components/parameters/IdParam'],
                ['$ref' => '#/components/parameters/FormatParam'],
                ['name' => 'record_id', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer'], 'example' => 123],
            ],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => ['schema' => ['$ref' => '#/components/schemas/UpdatePayload']],
                ],
            ],
            'responses' => [
                '200' => ['description' => 'Record updated.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessEnvelope']]]],
                'default' => ['$ref' => '#/components/responses/ErrorResponse'],
            ],
        ];
    }
}
