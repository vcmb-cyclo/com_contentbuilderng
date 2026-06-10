<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Service;

\defined('_JEXEC') or die('Restricted access');

final class SparseFieldsetService
{
    public function filter(array $payload, array $requestedFieldsets): array
    {
        $fieldsets = $this->normalizeFieldsets($requestedFieldsets);

        if ($fieldsets === []) {
            return $payload;
        }

        $filtered = [];

        foreach ($fieldsets as $resource => $fields) {
            if (!isset($payload[$resource]) || !is_array($payload[$resource])) {
                continue;
            }

            $filtered[$resource] = $this->filterResource($resource, $payload[$resource], $fields);
        }

        return $filtered;
    }

    /**
     * @return array<string,list<string>>
     */
    private function normalizeFieldsets(array $requestedFieldsets): array
    {
        $fieldsets = [];

        foreach ($requestedFieldsets as $resource => $requestedFields) {
            $resource = trim((string) $resource);
            if ($resource === '' || (!is_string($requestedFields) && !is_array($requestedFields))) {
                continue;
            }

            $fields = is_array($requestedFields)
                ? $requestedFields
                : explode(',', $requestedFields);
            $normalized = [];

            foreach ($fields as $field) {
                $field = trim((string) $field);
                if ($field !== '') {
                    $normalized[$field] = true;
                }
            }

            $fieldsets[$resource] = array_keys($normalized);
        }

        return $fieldsets;
    }

    /**
     * @param list<string> $fields
     */
    private function filterResource(string $resource, array $value, array $fields): array
    {
        if (array_is_list($value)) {
            foreach ($value as $index => $item) {
                if (is_array($item)) {
                    $value[$index] = $this->filterObject($resource, $item, $fields);
                }
            }

            return $value;
        }

        return $this->filterObject($resource, $value, $fields);
    }

    /**
     * @param list<string> $fields
     */
    private function filterObject(string $resource, array $value, array $fields): array
    {
        $allowed = array_fill_keys($fields, true);

        if ($resource !== 'items' || !isset($value['values']) || !is_array($value['values'])) {
            return array_intersect_key($value, $allowed);
        }

        $filtered = array_intersect_key($value, $allowed);
        if (isset($allowed['values'])) {
            $filtered['values'] = $value['values'];

            return $filtered;
        }

        $itemValues = array_intersect_key($value['values'], $allowed);
        if ($itemValues !== []) {
            $filtered['values'] = $itemValues;
        }

        return $filtered;
    }
}
