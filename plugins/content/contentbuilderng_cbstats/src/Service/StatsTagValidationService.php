<?php

declare(strict_types=1);

namespace CB\Plugin\Content\ContentbuilderngStats\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Site\Service\StatsHideOptionsService;
use CB\Component\Contentbuilderng\Site\Service\StatsService;
use CB\Component\Contentbuilderng\Site\Service\ContentCardService;

final class StatsTagValidationService
{
    private const OUTPUTS = [
        'total', 'table', 'pie', 'bar', 'histogram', 'line', 'radar', 'json',
        'sum', 'min', 'max', 'avg', 'remaining', 'percentage', 'progress', 'distinct', 'view_name',
    ];
    private const MANUAL_OUTPUTS = ['total', 'table', 'pie', 'bar', 'histogram', 'line', 'radar'];
    private const LIST_OUTPUTS = ['table', 'json', 'pie', 'bar', 'histogram', 'line', 'radar'];
    private const FIELD_OUTPUTS = [
        'table', 'json', 'pie', 'bar', 'histogram', 'line', 'radar',
        'sum', 'min', 'max', 'avg', 'percentage', 'distinct',
    ];
    private const ALLOWED_KEYS = [
        'source', 'id', 'idsum', 'debug', 'output', 'field', 'filter[field]',
        'filter[value]', 'value', 'add', 'titles', 'titleset', 'groups', 'groupset', 'labels',
        'background', 'sort', 'dir', 'values', 'export', 'limit', 'hide',
        'card', 'w', 'width', 'height', 'target', 'config',
    ];

    /**
     * @param array<string, string> $attributes
     *
     * @return list<array{code: string, parameter: string, value: string, detail: string}>
     */
    public static function validationErrors(array $attributes, int $fallbackId = 0, array $quoted = []): array
    {
        $errors = [];

        foreach (array_values(array_diff(array_keys($attributes), self::ALLOWED_KEYS)) as $key) {
            $removed = [
                'title' => 'labels_title',
                'headers' => 'labels_headers',
                'total_label' => 'labels_total',
                'total' => 'labels_total_or_hide',
            ];
            $errors[] = isset($removed[$key])
                ? self::error('removed_option', $key, (string) $attributes[$key], $removed[$key])
                : self::error('unknown_option', $key, (string) $attributes[$key]);
        }
        foreach (['id', 'idsum', 'limit', 'w', 'target'] as $numericKey) {
            if (($quoted[$numericKey] ?? false) === true) {
                $errors[] = self::error(
                    'invalid_value',
                    $numericKey,
                    (string) ($attributes[$numericKey] ?? ''),
                    $numericKey . '_syntax'
                );
            }
        }

        $source = TagSyntaxService::normalizeKeyword((string) ($attributes['source'] ?? 'view'));
        $output = TagSyntaxService::normalizeKeyword((string) ($attributes['output'] ?? 'total'));
        $manual = $source === 'manual';

        if (!in_array($source, ['view', 'manual'], true)) {
            $errors[] = self::error('invalid_value', 'source', (string) ($attributes['source'] ?? ''), 'source');
        }

        $allowedOutputs = $manual ? self::MANUAL_OUTPUTS : self::OUTPUTS;
        if (!in_array($output, $allowedOutputs, true)) {
            $errors[] = self::error('invalid_value', 'output', (string) ($attributes['output'] ?? ''), $manual ? 'manual_output' : 'output');
        }

        $target = trim((string) ($attributes['target'] ?? ''));
        if (in_array($output, ['remaining', 'progress'], true)) {
            if (!self::isPositiveNumber($target)) {
                $errors[] = self::error('invalid_value', 'target', $target, 'target');
            }
        } elseif ($target !== '') {
            $errors[] = self::error('invalid_value', 'target', $target, 'target_output');
        }

        $card = trim((string) ($attributes['card'] ?? ''));
        if ($card !== '' && !ContentCardService::isValid($card)) {
            $errors[] = self::error('invalid_value', 'card', $card, 'card');
        }

        $width = trim((string) ($attributes['w'] ?? ''));
        if ($width !== '' && !ContentCardService::isValidWidth($width)) {
            $errors[] = self::error('invalid_value', 'w', $width, 'w');
        } elseif ($width !== '' && !ContentCardService::isValid($card)) {
            $errors[] = self::error('invalid_value', 'w', $width, 'w_requires_card');
        }

        foreach (['width', 'height'] as $dimension) {
            $value = trim((string) ($attributes[$dimension] ?? ''));
            if ($value !== '' && CssDimensionService::normalize($value) === null) {
                $errors[] = self::error('invalid_value', $dimension, $value, $dimension);
            }
        }

        if ($source === 'view') {
            self::validateViewSource($attributes, $fallbackId, $output, $errors);
        } elseif ($manual) {
            try {
                ManualValuesParser::parse((string) ($attributes['values'] ?? ''));
            } catch (ManualValuesException $exception) {
                $errors[] = self::error(
                    'invalid_value',
                    'values',
                    $exception->getEntry() !== '' ? $exception->getEntry() : (string) ($attributes['values'] ?? ''),
                    'values'
                );
            }
        }

        $usesListOptions = ($manual && in_array($output, self::MANUAL_OUTPUTS, true))
            || (!$manual && in_array($output, self::LIST_OUTPUTS, true));
        if ($usesListOptions) {
            $allowedSort = $manual ? ['none', 'title', 'label', 'value'] : ['none', 'title', 'value'];
            $sort = TagSyntaxService::normalizeKeyword((string) ($attributes['sort'] ?? 'none'));
            if (!in_array($sort, $allowedSort, true)) {
                $errors[] = self::error('invalid_value', 'sort', (string) ($attributes['sort'] ?? ''), $manual ? 'manual_sort' : 'sort');
            }

            $dir = TagSyntaxService::normalizeKeyword((string) ($attributes['dir'] ?? 'asc'));
            if (!in_array($dir, ['asc', 'desc'], true)) {
                $errors[] = self::error('invalid_value', 'dir', (string) ($attributes['dir'] ?? ''), 'dir');
            }
        }

        try {
            DisplayOptionsService::parseLimit($attributes);
        } catch (\InvalidArgumentException) {
            $errors[] = self::error('invalid_value', 'limit', (string) ($attributes['limit'] ?? ''), 'limit');
        }

        if (!array_key_exists('total', $attributes) && in_array($output, $allowedOutputs, true)) {
            try {
                $hide = StatsHideOptionsService::fromAttributes($attributes);
                StatsHideOptionsService::validateForOutput($hide, $output);
            } catch (\InvalidArgumentException $exception) {
                $errors[] = self::error(
                    'invalid_value',
                    'hide',
                    (string) ($attributes['hide'] ?? $exception->getMessage()),
                    'hide'
                );
            }
        }

        $export = TagSyntaxService::normalizeKeyword((string) ($attributes['export'] ?? ''));
        if ($export !== '' && $export !== 'manual') {
            $errors[] = self::error('invalid_value', 'export', (string) $attributes['export'], 'export');
        }

        $background = trim((string) ($attributes['background'] ?? ''));
        if ($background !== '' && TotalPresentationService::validateBackground($background) === '') {
            $errors[] = self::error('invalid_value', 'background', $background, 'background');
        }

        self::validateMappings($attributes, $usesListOptions, $manual, $errors);

        $labels = [];
        try {
            $labels = StatsService::parseFieldStatsLabels((string) ($attributes['labels'] ?? ''));
        } catch (\InvalidArgumentException $exception) {
            $errors[] = self::error('invalid_value', 'labels', $exception->getMessage(), 'labels');
        }

        $config = trim((string) ($attributes['config'] ?? ''));
        if ($config !== '' && !\CB\Component\Contentbuilderng\Site\Service\CbStatsTitleSetService::isValidFilename($config)) {
            $errors[] = self::error('invalid_value', 'config', $config, 'config');
        }
        if ((isset($labels['category']) || isset($labels['value'])) && $output !== 'table') {
            $errors[] = self::error('invalid_value', 'labels', (string) ($attributes['labels'] ?? ''), 'labels_table');
        }
        if (isset($labels['total']) && !in_array($output, ['table', 'pie', 'bar', 'histogram', 'line', 'radar'], true)) {
            $errors[] = self::error('invalid_value', 'labels', (string) ($attributes['labels'] ?? ''), 'labels_total');
        }
        if (isset($labels['title']) && $output === 'json') {
            $errors[] = self::error('invalid_value', 'labels', (string) ($attributes['labels'] ?? ''), 'labels_title');
        }

        return $errors;
    }

    /**
     * @param array<string, string> $attributes
     * @param list<array{code: string, parameter: string, value: string, detail: string}> $errors
     */
    private static function validateViewSource(array $attributes, int $fallbackId, string $output, array &$errors): void
    {
        $id = trim((string) ($attributes['id'] ?? ''));
        $idSum = trim((string) ($attributes['idsum'] ?? ''));

        if ($id !== '' && $idSum !== '') {
            $errors[] = self::error('invalid_value', 'id/idsum', $id . ' / ' . $idSum, 'id_conflict');
        } elseif ($idSum !== '') {
            try {
                IdSumService::parseIds($idSum);
            } catch (IdSumException) {
                $errors[] = self::error('invalid_value', 'idsum', $idSum, 'idsum');
            }
        } elseif (!self::isPositiveInteger($id) && $fallbackId < 1) {
            $errors[] = self::error('invalid_value', 'id', $id, 'id');
        }

        $field = trim((string) ($attributes['field'] ?? ''));
        if ((in_array($output, self::FIELD_OUTPUTS, true) || $idSum !== '') && $field === '') {
            $errors[] = self::error('invalid_value', 'field', '', 'field');
        }

        if ($output === 'percentage') {
            if (trim((string) ($attributes['value'] ?? '')) === '') {
                $errors[] = self::error('invalid_value', 'value', '', 'percentage_value');
            }
        }

        if ($idSum !== '' && $output === 'view_name') {
            $errors[] = self::error('invalid_value', 'output', $output, 'idsum_output');
        }

        $filter = TagSyntaxService::resolveFilter($attributes);
        if (($filter['field'] === '') !== ($filter['value'] === '')) {
            $errors[] = self::error(
                'invalid_value',
                $filter['field'] === '' ? 'filter[field]' : 'filter[value]',
                '',
                'filter'
            );
        }
    }

    /**
     * @param array<string, string> $attributes
     * @param list<array{code: string, parameter: string, value: string, detail: string}> $errors
     */
    private static function validateMappings(array $attributes, bool $usesListOptions, bool $manual, array &$errors): void
    {
        $checks = [];

        if ($usesListOptions) {
            $checks['add'] = static fn(string $value): array => StatsService::parseFieldStatsAdditions($value);
            $checks['titles'] = static fn(string $value): array => StatsService::parseFieldStatsTitles($value);
        }
        if (!$manual) {
            $checks['groups'] = static fn(string $value): array => StatsService::parseFieldStatsGroups($value);
        }

        foreach ($checks as $parameter => $parser) {
            $value = trim((string) ($attributes[$parameter] ?? ''));
            if ($value === '') {
                continue;
            }

            try {
                $parser($value);
            } catch (\InvalidArgumentException $exception) {
                $errors[] = self::error(
                    'invalid_value',
                    $parameter,
                    $exception->getMessage() !== '' ? $exception->getMessage() : $value,
                    $parameter
                );
            }
        }
    }

    private static function isPositiveInteger(string $value): bool
    {
        return preg_match('/^[1-9][0-9]*$/D', $value) === 1;
    }

    private static function isPositiveNumber(string $value): bool
    {
        return preg_match('/^(?:[1-9][0-9]*(?:\.[0-9]+)?|0\.[0-9]*[1-9][0-9]*)$/D', $value) === 1;
    }

    /** @return array{code: string, parameter: string, value: string, detail: string} */
    private static function error(string $code, string $parameter, string $value, string $detail = ''): array
    {
        return compact('code', 'parameter', 'value', 'detail');
    }
}
