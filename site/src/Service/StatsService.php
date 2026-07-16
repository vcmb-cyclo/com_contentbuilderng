<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Service;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Service\ApiFieldPermissionService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

final class StatsService
{
    public const CBSTATS_ERROR_INVALID_ADD = 1001;
    public const CBSTATS_ERROR_INVALID_TITLES = 1004;

    public static function isFormDebugEnabled(int $formId): bool
    {
        if ($formId < 1) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('debug_mode'))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('id') . ' = ' . $formId);
            $db->setQuery($query, 0, 1);

            return (int) $db->loadResult() === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getStatsPayload(int $formId, array $options = []): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('title'),
                $db->quoteName('type'),
                $db->quoteName('reference_id'),
                $db->quoteName('published'),
            ])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        $db->setQuery($query, 0, 1);
        $formRow = $db->loadAssoc();

        if (!is_array($formRow) || empty($formRow['type'])) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        if (empty($formRow['reference_id'])) {
            $messageKey = (string) $formRow['type'] === 'com_breezingforms'
                ? 'COM_CONTENTBUILDERNG_BREEZINGFORMS_VIEW_NOT_FOUND'
                : 'COM_CONTENTBUILDERNG_FORM_NOT_FOUND';

            throw new \RuntimeException(Text::_($messageKey), 404);
        }

        $nowSql = Factory::getDate()->toSql();
        $recordWhere = [
            $db->quoteName('type') . ' = ' . $db->quote((string) $formRow['type']),
            $db->quoteName('reference_id') . ' = ' . $db->quote((string) $formRow['reference_id']),
        ];
        $statsFilter = $this->getStatsFilterPayload($formId, $formRow, $options);

        if ($statsFilter !== null) {
            $recordWhere[] = $statsFilter['where'];
        }

        $query = $db->getQuery(true)
            ->select([
                'COUNT(*) AS ' . $db->quoteName('total'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('published') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('published'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('published') . ' = 0 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('unpublished'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('is_future') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('future'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('edited') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('edited'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('publish_up') . ' IS NOT NULL AND ' . $db->quoteName('publish_up') . ' > ' . $db->quote($nowSql) . ' THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('scheduled'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('publish_down') . ' IS NOT NULL AND ' . $db->quoteName('publish_down') . ' < ' . $db->quote($nowSql) . ' THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('expired'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('rating_count') . ' > 0 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('rated_records'),
                'COALESCE(SUM(' . $db->quoteName('rating_count') . '), 0) AS ' . $db->quoteName('rating_count'),
                'COALESCE(SUM(' . $db->quoteName('rating_sum') . '), 0) AS ' . $db->quoteName('rating_sum'),
                'MAX(' . $db->quoteName('last_update') . ') AS ' . $db->quoteName('last_update'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($recordWhere);
        $db->setQuery($query, 0, 1);
        $records = $db->loadAssoc() ?: [];

        $ratingCount = (int) ($records['rating_count'] ?? 0);
        $ratingSum = (int) ($records['rating_sum'] ?? 0);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('lang_code'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($recordWhere)
            ->group($db->quoteName('lang_code'))
            ->order($db->quoteName('lang_code'));
        $db->setQuery($query);
        $languageRows = $db->loadAssocList() ?: [];
        $languages = [];

        foreach ($languageRows as $languageRow) {
            $languages[(string) ($languageRow['lang_code'] ?? '*')] = (int) ($languageRow['total'] ?? 0);
        }

        $fieldStats = $this->getStatsFieldPayload($formId, $formRow, $options, $statsFilter['field_where'] ?? null);

        return [
            'form' => [
                'id' => (int) $formRow['id'],
                'name' => (string) ($formRow['name'] ?? ''),
                'title' => (string) ($formRow['title'] ?? ''),
            ],
            'records' => [
                'total' => (int) ($records['total'] ?? 0),
                'published' => (int) ($records['published'] ?? 0),
                'unpublished' => (int) ($records['unpublished'] ?? 0),
                'future' => (int) ($records['future'] ?? 0),
                'edited' => (int) ($records['edited'] ?? 0),
                'scheduled' => (int) ($records['scheduled'] ?? 0),
                'expired' => (int) ($records['expired'] ?? 0),
                'last_update' => (string) ($records['last_update'] ?? ''),
            ],
            'ratings' => [
                'rated_records' => (int) ($records['rated_records'] ?? 0),
                'rating_count' => $ratingCount,
                'rating_sum' => $ratingSum,
                'average' => $ratingCount > 0 ? round($ratingSum / $ratingCount, 4) : 0.0,
            ],
            'languages' => $languages,
        ] + ($statsFilter !== null ? ['filter' => $statsFilter['payload']] : []) + ($fieldStats !== null ? ['field' => $fieldStats] : []);
    }

    private function getStatsFilterPayload(int $formId, array $formRow, array $options): ?array
    {
        $filter = $this->getRequestedStatsFilter($options);

        if ($filter === null) {
            return null;
        }

        $field = $this->resolveStatsField($formId, $formRow, $filter['field']);
        if ($field === null) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELD_NOT_ALLOWED'), 403);
        }

        return [
            'where' => $this->getStatsFilterWhere($formRow, $field, $filter['value']),
            'field_where' => $this->getStatsFilterWhere($formRow, $field, $filter['value'], 'records'),
            'payload' => [
                'field' => $filter['field'],
                'value' => implode(' | ', $filter['value']),
            ],
        ];
    }

    private function getRequestedStatsFilter(array $options): ?array
    {
        $filter = (array) ($options['filter'] ?? []);
        $field = trim((string) ($filter['field'] ?? ''));
        $values = array_values(array_filter(
            array_map(
                static fn($value): string => trim((string) $value),
                (array) ($filter['values'] ?? [$filter['value'] ?? ''])
            ),
            static fn(string $value): bool => $value !== ''
        ));

        if ($field !== '' && $values !== []) {
            return ['field' => $field, 'value' => array_values(array_unique($values))];
        }

        return null;
    }

    private function getStatsFilterWhere(array $formRow, array $field, array $values, string $recordAlias = ''): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $recordIdColumn = $db->quoteName(($recordAlias !== '' ? $recordAlias . '.' : '') . 'record_id');

        return match ((string) $formRow['type']) {
            'com_contentbuilderng' => $this->getContentbuilderngStatsFilterWhere($formRow, $field, $values, $recordIdColumn),
            'com_breezingforms' => $recordIdColumn . ' IN (' . $this->getBreezingFormsStatsFilterRecordQuery($formRow, $field, $values) . ')',
            default => '1 = 0',
        };
    }

    private function getContentbuilderngStatsFilterWhere(array $formRow, array $field, array $values, string $recordIdColumn): string
    {
        $form = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);
        $properties = is_object($form) && isset($form->properties) && is_object($form->properties) ? $form->properties : null;

        if (!$properties || empty($properties->name)) {
            return '1 = 0';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableName = ((int) ($properties->bytable ?? 0) === 1 ? '' : '#__') . (string) $properties->name;
        $valueColumn = 'TRIM(' . $db->quoteName('source.' . (string) $field['name']) . ')';
        $query = $db->getQuery(true)
            ->select($db->quoteName('source.id'))
            ->from($db->quoteName($tableName, 'source'))
            ->where($this->buildStatsValueCondition($valueColumn, $values));

        return $recordIdColumn . ' IN (' . (string) $query . ')';
    }

    private function getBreezingFormsStatsFilterRecordQuery(array $formRow, array $field, array $values): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $valueColumn = $db->quoteName('subrecords.value');
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('bf_records.id'))
            ->from($db->quoteName('#__facileforms_records', 'bf_records'))
            ->join('INNER', $db->quoteName('#__facileforms_subrecords', 'subrecords') . ' ON ' . $db->quoteName('subrecords.record') . ' = ' . $db->quoteName('bf_records.id'))
            ->where($db->quoteName('bf_records.form') . ' = ' . (int) $formRow['reference_id'])
            ->where('('
                . $db->quoteName('subrecords.element') . ' = ' . (int) $field['reference_id']
                . ' OR ' . $db->quoteName('subrecords.name') . ' = ' . $db->quote((string) $field['name'])
                . ')')
            ->where($this->buildStatsValueCondition('TRIM(' . $valueColumn . ')', $values));

        return (string) $query;
    }

    private function buildStatsValueCondition(string $column, array $values): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $filterValues = new StatsFilterValueService();
        $conditions = [];

        foreach ($values as $value) {
            $value = (string) $value;
            $conditions[] = $filterValues->hasWildcard($value)
                ? $column . ' LIKE ' . $db->quote($filterValues->toSqlLikePattern($value))
                    . ' ESCAPE ' . $db->quote('\\')
                : $column . ' = ' . $db->quote($value);
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function getStatsFieldPayload(int $formId, array $formRow, array $options, ?string $statsFilterWhere): ?array
    {
        $requestedField = trim((string) ($options['field'] ?? ''));

        if ($requestedField === '') {
            return null;
        }

        $field = $this->resolveStatsField($formId, $formRow, $requestedField);
        if ($field === null) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELD_NOT_ALLOWED'), 403);
        }

        $values = match ((string) $formRow['type']) {
            'com_contentbuilderng' => $this->getContentbuilderngStatsFieldValues($formRow, $field, $statsFilterWhere),
            'com_breezingforms' => $this->getBreezingFormsStatsFieldValues($formRow, $field, $statsFilterWhere),
            default => [],
        };

        return [
            'requested' => $requestedField,
            'reference_id' => (int) $field['reference_id'],
            'name' => (string) $field['name'],
            'label' => (string) $field['label'],
            'total' => array_sum($values),
        ] + self::computeFieldAggregates($values) + [
            'values' => $values,
        ];
    }

    /**
     * Aggregates over a value => occurrence-count map. When every distinct
     * value is numeric: count-weighted sum and numeric min/max. When every
     * distinct value is an ISO date (Y-m-d, optional H:i or H:i:s): earliest
     * and latest date as min/max, sum null. All null otherwise or when the
     * map is empty.
     *
     * @return array{sum: float|null, min: float|string|null, max: float|string|null}
     */
    public static function computeFieldAggregates(array $values): array
    {
        $none = ['sum' => null, 'min' => null, 'max' => null];

        if ($values === []) {
            return $none;
        }

        $sum = 0.0;
        $min = null;
        $max = null;
        $allDates = true;

        foreach ($values as $value => $count) {
            if (!is_numeric($value)) {
                $sum = null;
                $min = null;
                $max = null;
                break;
            }

            $number = (float) $value;
            $sum += $number * $count;
            $min = $min === null ? $number : min($min, $number);
            $max = $max === null ? $number : max($max, $number);
        }

        if ($sum !== null) {
            return ['sum' => $sum, 'min' => $min, 'max' => $max];
        }

        foreach ($values as $value => $count) {
            if (!self::isIsoDateValue((string) $value)) {
                $allDates = false;
                break;
            }
        }

        if (!$allDates) {
            return $none;
        }

        $dates = array_map('strval', array_keys($values));
        sort($dates, SORT_STRING);

        return ['sum' => null, 'min' => $dates[0], 'max' => $dates[count($dates) - 1]];
    }

    public static function resolveCbstatsOutput(array $payload, string $output): int|float|string
    {
        return match ($output) {
            'total' => (int) ($payload['records']['total'] ?? 0),
            'form_name' => self::resolveFormName($payload),
            'sum', 'min', 'max' => self::resolveFieldAggregate($payload, $output),
            default => throw new \InvalidArgumentException('Unsupported CBStats scalar output.'),
        };
    }

    private static function resolveFormName(array $payload): string
    {
        $form = (array) ($payload['form'] ?? []);
        $title = trim((string) ($form['title'] ?? ''));

        return $title !== '' ? $title : trim((string) ($form['name'] ?? ''));
    }

    private static function resolveFieldAggregate(array $payload, string $output): int|float|string
    {
        $value = $payload['field'][$output] ?? null;

        return $value === null ? 0 : $value;
    }

    /**
     * @param array<int|string,int|float> $values
     * @param array<int|string,int> $additions
     * @param array<int|string,string> $titles
     * @return list<array{label: string, value: int|float}>
     */
    public static function normalizeFieldStats(
        array $values,
        string $sort = 'none',
        string $dir = 'asc',
        ?string $locale = null,
        array $additions = [],
        array $titles = []
    ): array
    {
        foreach ($additions as $label => $value) {
            $current = $values[$label] ?? 0;
            $value = (int) $value;

            if ($value > 0 && $current > PHP_INT_MAX - $value) {
                throw new \InvalidArgumentException('', self::CBSTATS_ERROR_INVALID_ADD);
            }

            $values[$label] = $current + $value;
        }

        $items = [];

        foreach ($values as $label => $value) {
            $items[] = [
                'label' => array_key_exists($label, $titles) ? $titles[$label] : (string) $label,
                'value' => $value < 0 ? 0 : $value,
            ];
        }

        $sort = strtolower(trim($sort));
        $dir = strtolower(trim($dir));
        $direction = $dir === 'desc' ? -1 : 1;

        if ($sort === 'title') {
            $collator = new \Collator($locale ?? \Locale::getDefault());
            $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
            usort(
                $items,
                static fn(array $left, array $right): int => $direction * $collator->compare($left['label'], $right['label'])
            );
        } elseif ($sort === 'value') {
            usort(
                $items,
                static fn(array $left, array $right): int => $direction * ($left['value'] <=> $right['value'])
            );
        }

        return $items;
    }

    /**
     * @return array<int|string,int>
     */
    public static function parseFieldStatsAdditions(string $add): array
    {
        $add = trim($add);

        if ($add === '') {
            return [];
        }

        $additions = [];

        foreach (explode(';', $add) as $entry) {
            $parts = explode('=', $entry, 2);
            $label = trim((string) ($parts[0] ?? ''));
            $rawValue = trim((string) ($parts[1] ?? ''));

            if ($label === '' || count($parts) !== 2 || !preg_match('/^[+-]?\d+$/D', $rawValue)) {
                throw new \InvalidArgumentException('', self::CBSTATS_ERROR_INVALID_ADD);
            }

            $negative = str_starts_with($rawValue, '-');
            $unsignedValue = ltrim($rawValue, '+-');
            $normalizedValue = ltrim($unsignedValue, '0');
            $normalizedValue = $normalizedValue === '' ? '0' : $normalizedValue;
            $maxInteger = (string) PHP_INT_MAX;

            if (
                strlen($normalizedValue) > strlen($maxInteger)
                || (strlen($normalizedValue) === strlen($maxInteger) && strcmp($normalizedValue, $maxInteger) > 0)
            ) {
                throw new \InvalidArgumentException('', self::CBSTATS_ERROR_INVALID_ADD);
            }

            $value = (int) $normalizedValue * ($negative ? -1 : 1);
            $current = (int) ($additions[$label] ?? 0);

            if (
                ($value > 0 && $current > PHP_INT_MAX - $value)
                || ($value < 0 && $current < -PHP_INT_MAX - $value)
            ) {
                throw new \InvalidArgumentException('', self::CBSTATS_ERROR_INVALID_ADD);
            }

            $additions[$label] = $current + $value;
        }

        return $additions;
    }

    /**
     * @return array<int|string,string>
     */
    public static function parseFieldStatsTitles(string $titles): array
    {
        $titles = trim($titles);

        if ($titles === '') {
            return [];
        }

        $mappings = [];

        foreach (explode(';', $titles) as $entry) {
            $parts = explode('=', $entry, 2);
            $original = trim((string) ($parts[0] ?? ''));
            $display = trim((string) ($parts[1] ?? ''));

            if ($original === '' || $display === '' || count($parts) !== 2) {
                throw new \InvalidArgumentException('', self::CBSTATS_ERROR_INVALID_TITLES);
            }

            $mappings[$original] = $display;
        }

        return $mappings;
    }

    private static function isIsoDateValue(string $value): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})( \d{2}:\d{2}(:\d{2})?)?$/', $value, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private function resolveStatsField(int $formId, array $formRow, string $requestedField): ?array
    {
        $form = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);
        if (!$form || !is_object($form)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_ERROR'), 404);
        }

        $names = method_exists($form, 'getElementNames') ? (array) $form->getElementNames() : [];
        $labels = method_exists($form, 'getElementLabels') ? (array) $form->getElementLabels() : [];
        $allowedReferences = (new ApiFieldPermissionService())->getAllowedReferenceMap($formId);

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('reference_id'), $db->quoteName('label')])
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('api_allowed') . ' = 1')
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $rows = $db->loadAssocList() ?: [];

        $needle = $this->normalizeStatsFieldName($requestedField);

        foreach ($rows as $row) {
            $referenceId = (string) ($row['reference_id'] ?? '');
            if ($referenceId === '' || !isset($allowedReferences[$referenceId])) {
                continue;
            }
            $name = (string) ($names[$referenceId] ?? '');
            $label = (string) (($row['label'] ?? '') !== '' ? $row['label'] : ($labels[$referenceId] ?? ''));
            $candidates = [$referenceId, $name, $label, (string) ($labels[$referenceId] ?? '')];

            if ($label !== '' && $name !== '') {
                $candidates[] = $label . ' (' . $name . ')';
            }

            foreach ($candidates as $candidate) {
                if ($candidate !== '' && $this->normalizeStatsFieldName($candidate) === $needle) {
                    return [
                        'reference_id' => (int) $referenceId,
                        'name' => $name !== '' ? $name : $referenceId,
                        'label' => $label !== '' ? $label : ($name !== '' ? $name : $referenceId),
                    ];
                }
            }
        }

        return null;
    }

    private function normalizeStatsFieldName(string $value): string
    {
        $value = trim($value);

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function getContentbuilderngStatsFieldValues(array $formRow, array $field, ?string $statsFilterWhere): array
    {
        $form = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);
        $properties = is_object($form) && isset($form->properties) && is_object($form->properties) ? $form->properties : null;

        if (!$properties || empty($properties->name)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableName = ((int) ($properties->bytable ?? 0) === 1 ? '' : '#__') . (string) $properties->name;
        $valueColumn = $db->quoteName('source.' . (string) $field['name']);
        $recordWhere = $this->getStatsRecordWhere($formRow, 'records');

        if ($statsFilterWhere !== null) {
            $recordWhere[] = $statsFilterWhere;
        }

        $query = $db->getQuery(true)
            ->select([
                'TRIM(' . $valueColumn . ') AS ' . $db->quoteName('value'),
                'COUNT(DISTINCT ' . $db->quoteName('records.record_id') . ') AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records', 'records'))
            ->join('INNER', $db->quoteName($tableName, 'source') . ' ON ' . $db->quoteName('source.id') . ' = ' . $db->quoteName('records.record_id'))
            ->where($recordWhere)
            ->where('TRIM(' . $valueColumn . ') <> ' . $db->quote(''))
            ->group('TRIM(' . $valueColumn . ')')
            ->order('TRIM(' . $valueColumn . ')');
        $db->setQuery($query);

        return $this->formatStatsFieldRows($db->loadAssocList() ?: []);
    }

    private function getBreezingFormsStatsFieldValues(array $formRow, array $field, ?string $statsFilterWhere): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $valueColumn = $db->quoteName('subrecords.value');
        $recordWhere = $this->getStatsRecordWhere($formRow, 'records');

        if ($statsFilterWhere !== null) {
            $recordWhere[] = $statsFilterWhere;
        }

        $query = $db->getQuery(true)
            ->select([
                'TRIM(' . $valueColumn . ') AS ' . $db->quoteName('value'),
                'COUNT(DISTINCT ' . $db->quoteName('records.record_id') . ') AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records', 'records'))
            ->join('INNER', $db->quoteName('#__facileforms_records', 'bf_records') . ' ON ' . $db->quoteName('bf_records.id') . ' = ' . $db->quoteName('records.record_id'))
            ->join('INNER', $db->quoteName('#__facileforms_subrecords', 'subrecords') . ' ON ' . $db->quoteName('subrecords.record') . ' = ' . $db->quoteName('bf_records.id'))
            ->where($recordWhere)
            ->where($db->quoteName('bf_records.form') . ' = ' . (int) $formRow['reference_id'])
            ->where('('
                . $db->quoteName('subrecords.element') . ' = ' . (int) $field['reference_id']
                . ' OR ' . $db->quoteName('subrecords.name') . ' = ' . $db->quote((string) $field['name'])
                . ')')
            ->where('TRIM(' . $valueColumn . ') <> ' . $db->quote(''))
            ->group('TRIM(' . $valueColumn . ')')
            ->order('TRIM(' . $valueColumn . ')');
        $db->setQuery($query);

        return $this->formatStatsFieldRows($db->loadAssocList() ?: []);
    }

    private function getStatsRecordWhere(array $formRow, string $alias = ''): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $prefix = $alias !== '' ? $alias . '.' : '';

        return [
            $db->quoteName($prefix . 'type') . ' = ' . $db->quote((string) $formRow['type']),
            $db->quoteName($prefix . 'reference_id') . ' = ' . $db->quote((string) $formRow['reference_id']),
        ];
    }

    private function formatStatsFieldRows(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = (string) ($row['value'] ?? '');
            if ($value === '') {
                continue;
            }

            $values[$value] = (int) ($row['total'] ?? 0);
        }

        return $values;
    }
}
