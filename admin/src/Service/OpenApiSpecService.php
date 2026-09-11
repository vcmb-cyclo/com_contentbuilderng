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

/** Builds the OpenAPI description of the query-dispatched JSON endpoint. */
final class OpenApiSpecService
{
    public function build(string $componentVersion): array
    {
        $version = trim($componentVersion) !== '' ? trim($componentVersion) : '0.0.0';

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'ContentBuilder NG JSON API',
                'version' => $version,
                'description' => 'Read and update API-authorized ContentBuilder NG view records. '
                    . 'The endpoint uses the Joomla front controller and dispatches operations with query parameters.',
            ],
            'servers' => [
                ['url' => rtrim((string) Uri::root(), '/'), 'description' => 'Joomla site root'],
            ],
            'security' => [
                ['joomlaSession' => []],
                [],
            ],
            'components' => $this->buildComponents(),
            'paths' => $this->buildPaths(),
        ];
    }

    private function buildComponents(): array
    {
        return [
            'securitySchemes' => [
                'joomlaSession' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'Cookie',
                    'description' => 'Authenticated Joomla browser session. Public access remains subject to the view ACL.',
                ],
            ],
            'schemas' => [
                'SuccessEnvelope' => [
                    'type' => 'object',
                    'required' => ['success', 'messages', 'data'],
                    'properties' => [
                        'success' => ['type' => 'boolean', 'enum' => [true]],
                        'messages' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'data' => [
                            'oneOf' => [
                                ['type' => 'object', 'additionalProperties' => true],
                                ['type' => 'number'],
                                ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'ErrorEnvelope' => [
                    'type' => 'object',
                    'required' => ['success', 'messages', 'data'],
                    'properties' => [
                        'success' => ['type' => 'boolean', 'enum' => [false]],
                        'messages' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'data' => ['nullable' => true, 'example' => null],
                    ],
                ],
                'UpdatePayload' => [
                    'type' => 'object',
                    'required' => ['fields'],
                    'properties' => [
                        'fields' => [
                            'type' => 'object',
                            'description' => 'Field names or numeric references authorized for the API.',
                            'additionalProperties' => true,
                            'example' => ['Name' => 'New name', 'Email' => 'contact@example.test'],
                        ],
                    ],
                ],
                'CbStatsItems' => [
                    'type' => 'array',
                    'maxItems' => 100,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'value' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'parameters' => [
                'Option' => $this->queryParameter('option', true, ['type' => 'string', 'enum' => ['com_contentbuilderng']]),
                'Task' => $this->queryParameter('task', true, ['type' => 'string', 'enum' => ['api.display']]),
                'ViewId' => $this->queryParameter('id', true, ['type' => 'integer', 'minimum' => 1], 15),
                'RecordId' => $this->queryParameter('record_id', false, ['type' => 'integer', 'minimum' => 1], 123),
                'Format' => $this->queryParameter('format', false, ['type' => 'string', 'enum' => ['json']]),
                'ReadAction' => $this->queryParameter('action', false, [
                    'type' => 'string',
                    'enum' => ['get-unique-values', 'stats', 'cbstats'],
                ]),
                'RatingAction' => $this->queryParameter('action', false, [
                    'type' => 'string', 'enum' => ['rating'],
                ]),
                'ListLimit' => $this->queryParameter('list[limit]', false, [
                    'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20,
                ]),
                'ListStart' => $this->queryParameter('list[start]', false, [
                    'type' => 'integer', 'minimum' => 0, 'default' => 0,
                ]),
                'CsrfToken' => [
                    'name' => 'X-CSRF-Token',
                    'in' => 'header',
                    'required' => true,
                    'description' => 'Current Joomla form token name. Required for session-authenticated state changes.',
                    'schema' => ['type' => 'string'],
                ],
                'CbstatsOutput' => $this->queryParameter('output', false, [
                    'type' => 'string',
                    'enum' => [
                        'total', 'table', 'pie', 'bar', 'histogram', 'line', 'radar', 'json',
                        'sum', 'min', 'max', 'avg', 'remaining', 'percentage', 'progress', 'distinct', 'view_name',
                    ],
                ]),
            ],
            'responses' => [
                'Error' => [
                    'description' => 'Concise failure envelope.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
                ],
            ],
        ];
    }

    private function buildPaths(): array
    {
        $common = [
            ['$ref' => '#/components/parameters/Option'],
            ['$ref' => '#/components/parameters/Task'],
            ['$ref' => '#/components/parameters/ViewId'],
            ['$ref' => '#/components/parameters/Format'],
        ];
        $errors = $this->errorResponses();

        return [
            '/index.php' => [
                'get' => [
                    'operationId' => 'readContentBuilderData',
                    'summary' => 'Read records, distinct values, or statistics',
                    'description' => 'Without action: paginated list, or one record when record_id is present. '
                        . 'Actions: get-unique-values, stats, and cbstats. Permissions and api_allowed fields are enforced server-side. '
                        . 'CBStats outputs: total, table, pie, bar, histogram, line, radar, json, '
                        . 'sum, min, max, avg, remaining, percentage, progress, distinct, view_name.',
                    'parameters' => [...$common,
                        ['$ref' => '#/components/parameters/ReadAction'],
                        ['$ref' => '#/components/parameters/RecordId'],
                        ['$ref' => '#/components/parameters/ListLimit'],
                        ['$ref' => '#/components/parameters/ListStart'],
                        $this->queryParameter('verbose', false, ['type' => 'integer', 'enum' => [0, 1], 'default' => 0]),
                        $this->queryParameter('field_reference_id', false, ['type' => 'string']),
                        $this->queryParameter('where_field', false, ['type' => 'string']),
                        $this->queryParameter('where', false, ['type' => 'string']),
                        $this->queryParameter('field', false, ['type' => 'string']),
                        $this->queryParameter('filter[field]', false, ['type' => 'string']),
                        $this->queryParameter('filter[value]', false, ['type' => 'string']),
                        ['$ref' => '#/components/parameters/CbstatsOutput'],
                        $this->queryParameter('value', false, ['type' => 'string']),
                        $this->queryParameter('target', false, ['type' => 'number', 'exclusiveMinimum' => true, 'minimum' => 0]),
                        $this->queryParameter('sort', false, ['type' => 'string', 'enum' => ['none', 'title', 'value']]),
                        $this->queryParameter('dir', false, ['type' => 'string', 'enum' => ['asc', 'desc']]),
                        $this->queryParameter('add', false, ['type' => 'string']),
                        $this->queryParameter('titles', false, ['type' => 'string']),
                        $this->queryParameter('titleset', false, ['type' => 'string']),
                        $this->queryParameter('groups', false, ['type' => 'string']),
                        $this->queryParameter('groupset', false, ['type' => 'string']),
                        $this->queryParameter('hide', false, ['type' => 'string']),
                        $this->queryParameter('limit', false, ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]),
                        $this->queryParameter('fields[items]', false, ['type' => 'string']),
                        $this->queryParameter('fields[fields]', false, ['type' => 'string']),
                        $this->queryParameter('fields[records]', false, ['type' => 'string']),
                        $this->queryParameter('fields[ratings]', false, ['type' => 'string']),
                    ],
                    'responses' => ['200' => $this->successResponse('Requested data.')] + $errors,
                ],
                'post' => [
                    'operationId' => 'changeContentBuilderData',
                    'security' => [['joomlaSession' => []]],
                    'summary' => 'Update one record or cast a rating vote',
                    'description' => 'Without action: update record_id from a JSON fields object. With action=rating: cast a vote. '
                        . 'A Joomla CSRF token is required for session-authenticated requests.',
                    'parameters' => [...$common,
                        ['$ref' => '#/components/parameters/RatingAction'],
                        ['$ref' => '#/components/parameters/RecordId'],
                        ['$ref' => '#/components/parameters/CsrfToken'],
                        $this->queryParameter('rate', false, ['type' => 'integer', 'minimum' => 0, 'maximum' => 5]),
                    ],
                    'requestBody' => [
                        'required' => false,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UpdatePayload']]],
                    ],
                    'responses' => ['200' => $this->successResponse('Change accepted.')] + $errors,
                ],
                'put' => $this->updateOperation('replaceContentBuilderRecord'),
                'patch' => $this->updateOperation('patchContentBuilderRecord'),
            ],
        ];
    }

    private function updateOperation(string $operationId): array
    {
        return [
            'operationId' => $operationId,
            'security' => [['joomlaSession' => []]],
            'summary' => 'Update one record',
            'description' => 'API + Edit permissions and api_allowed fields are enforced. '
                . 'A Joomla CSRF token is required for session-authenticated requests.',
            'parameters' => [
                ['$ref' => '#/components/parameters/Option'],
                ['$ref' => '#/components/parameters/Task'],
                ['$ref' => '#/components/parameters/ViewId'],
                ['$ref' => '#/components/parameters/RecordId'],
                ['$ref' => '#/components/parameters/Format'],
                ['$ref' => '#/components/parameters/CsrfToken'],
            ],
            'requestBody' => [
                'required' => true,
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UpdatePayload']]],
            ],
            'responses' => ['200' => $this->successResponse('Record updated.')] + $this->errorResponses(),
        ];
    }

    private function queryParameter(string $name, bool $required, array $schema, mixed $example = null): array
    {
        $parameter = ['name' => $name, 'in' => 'query', 'required' => $required, 'schema' => $schema];
        if ($example !== null) {
            $parameter['example'] = $example;
        }

        return $parameter;
    }

    private function successResponse(string $description): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => ['schema' => [
                'oneOf' => [
                    ['$ref' => '#/components/schemas/SuccessEnvelope'],
                    ['$ref' => '#/components/schemas/CbStatsItems'],
                ],
            ]]],
        ];
    }

    private function errorResponses(): array
    {
        $response = ['$ref' => '#/components/responses/Error'];

        return ['400' => $response, '403' => $response, '404' => $response, '405' => $response, '500' => $response];
    }
}
