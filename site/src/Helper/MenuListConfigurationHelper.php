<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Site\Service\EmbeddedListActionFilterService;
use CB\Component\Contentbuilderng\Site\Service\MenuDataFilterService;

final class MenuListConfigurationHelper
{
    public const DISPLAY_ACTION_FIELDS = [
        'export' => 'export_xls',
        'print' => 'print_button',
        'rating' => 'list_rating',
    ];

    /** Apply display preferences only; permission checks remain unchanged. */
    public static function applyDisplayActionOverrides(object $data, array $overrides): void
    {
        foreach (self::DISPLAY_ACTION_FIELDS as $action => $field) {
            $value = self::toggleValue($overrides, $action);
            if ($value !== 'default') {
                $data->{$field} = $value === 'yes' ? 1 : 0;
            }
        }
    }

    /** @param list<int|string> $searchable @return list<int|string> */
    public static function filterSearchableElements(array $searchable, string $rawSelectors): array
    {
        $selectors = array_values(array_unique(array_filter(array_map(
            static fn(string $value): string => trim($value),
            explode('|', $rawSelectors)
        ), static fn(string $value): bool => $value !== '')));
        if ($selectors === []) {
            return $searchable;
        }

        $allowed = array_fill_keys($selectors, true);

        return array_values(array_filter(
            $searchable,
            static fn(int|string $reference): bool => isset($allowed[(string) $reference])
        ));
    }

    /** @param list<int|string> $available @return list<int|string> */
    public static function selectElementsInMenuOrder(array $available, string $rawSelectors): array
    {
        $selectors = array_values(array_unique(array_filter(array_map(
            static fn(string $value): string => trim($value),
            explode('|', $rawSelectors)
        ), static fn(string $value): bool => $value !== '')));
        if ($selectors === []) {
            return $available;
        }

        $availableByReference = [];
        foreach ($available as $reference) {
            $availableByReference[(string) $reference] = $reference;
        }

        return array_values(array_map(
            static fn(string $reference): int|string => $availableByReference[$reference],
            array_filter($selectors, static fn(string $reference): bool => isset($availableByReference[$reference]))
        ));
    }

    /** @return array<string, mixed> */
    public static function decode(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function isNewListMenu(object $item): bool
    {
        $query = is_array($item->query ?? null) ? $item->query : [];

        return (string) ($query['view'] ?? '') === 'list'
            && in_array(
                (string) ($query['layout'] ?? 'default'),
                ['', 'default', 'listcard', 'listcompact', 'listtiles'],
                true
            );
    }

    /** @return array<string, string|int|null> */
    public static function requestParameters(array $config, int $viewMaximumRecords = 0): array
    {
        $customColumns = (string) ($config['columnsMode'] ?? 'default') === 'custom';
        $hasColumnSelection = is_array($config['columns'] ?? null);
        $searchFields = self::selectorList($config, 'columnsMode', 'searchFields');
        if ($customColumns && is_array($config['searchFields'] ?? null) && $searchFields === '') {
            $searchFields = '__none__';
        }
        $linkFields = self::selectorList($config, 'columnsMode', 'linkFields');
        if ($customColumns && is_array($config['linkFields'] ?? null) && $linkFields === '') {
            $linkFields = '__none__';
        }
        $detailFields = self::selectorList($config, 'columnsMode', 'detailFields');
        if ($customColumns && is_array($config['detailFields'] ?? null) && $detailFields === '') {
            $detailFields = '__none__';
        }
        $editFields = self::selectorList($config, 'columnsMode', 'editFields');
        if ($customColumns && is_array($config['editFields'] ?? null) && $editFields === '') {
            $editFields = '__none__';
        }
        $exportFields = self::selectorList($config, 'columnsMode', 'exportFields');
        if ($customColumns && is_array($config['exportFields'] ?? null) && $exportFields === '') {
            $exportFields = '__none__';
        }
        $publishedFields = self::selectorList($config, 'columnsMode', 'publishedFields');
        if ($customColumns && is_array($config['publishedFields'] ?? null) && $publishedFields === '') {
            $publishedFields = '__none__';
        }
        $parameters = [
            MenuDataFilterService::INPUT_NAME => MenuDataFilterService::encode(
                (array) ($config['filters'] ?? []),
                $publishedFields === '' ? null : array_values(array_filter(
                    explode('|', $publishedFields),
                    static fn(string $reference): bool => ctype_digit($reference)
                ))
            ),
            'cb_menu_search_fields' => $searchFields,
            'cb_menu_link_fields' => $linkFields,
            'cb_menu_detail_fields' => $detailFields,
            'cb_menu_edit_fields' => $editFields,
            'cb_menu_export_fields' => $exportFields,
            'cb_menu_published_fields' => $publishedFields,
        ];
        if ($customColumns && $hasColumnSelection) {
            $parameters['cb_new_list_menu'] = 1;
        }
        foreach (
            [
            'searchShow' => 'cb_new_show_search',
            'stateShow' => 'cb_new_show_state',
            'stateBulkShow' => 'cb_new_show_state_bulk',
            'stateFilterShow' => 'cb_new_show_state_filter',
            'editListButton' => 'cb_new_show_list_edit',
            ] as $configKey => $requestKey
        ) {
            $value = self::toggleValue($config, $configKey);
            if ($value !== 'default') {
                $parameters[$requestKey] = $value;
            }
        }

        foreach (self::DISPLAY_ACTION_FIELDS as $action => $field) {
            $value = self::toggleValue((array) ($config['action'] ?? []), $action);
            if ($value !== 'default') {
                $parameters['cb_new_show_' . $action] = $value;
            }
        }

        $titleMode = (string) ($config['titleMode'] ?? 'default');
        if ($titleMode === 'custom') {
            $parameters['cblist_title'] = self::normalizeTitle((string) ($config['title'] ?? ''));
            $parameters['cblist_title_set'] = 1;
        } elseif ($titleMode === 'hidden') {
            $parameters['cblist_title'] = '';
            $parameters['cblist_title_set'] = 1;
        }

        $fields = self::selectorList($config, 'columnsMode', 'columns');
        if ($fields !== '') {
            $parameters['cblist_fields'] = $fields;
        }

        if ((string) ($config['sortMode'] ?? 'default') === 'custom') {
            $sortFields = [];
            $sortDirections = [];
            foreach ((array) ($config['sort'] ?? []) as $sort) {
                if (!is_array($sort)) {
                    continue;
                }
                $field = trim((string) ($sort['field'] ?? ''));
                if ($field === '' || in_array($field, $sortFields, true)) {
                    continue;
                }
                $sortFields[] = $field;
                $sortDirections[] = strtolower((string) ($sort['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
                if (count($sortFields) === 3) {
                    break;
                }
            }
            if ($sortFields !== []) {
                $parameters['cblist_sort'] = implode('|', $sortFields);
                $parameters['cblist_dir'] = implode('|', $sortDirections);
            }
        }

        $maximumRecords = array_key_exists('maximumRecords', $config)
            ? (int) $config['maximumRecords']
            : -1;
        $maximumRecords = $maximumRecords < 0
            ? max(0, $viewMaximumRecords)
            : min(5000, $maximumRecords);
        if ($maximumRecords > 0) {
            $parameters['cblist_limit'] = $maximumRecords;
        }

        if ((string) ($config['exportFilenameMode'] ?? 'default') === 'custom') {
            $parameters['cb_export_filename_mode'] = 'custom';
            $parameters['cb_export_filename'] = self::normalizeExportFilenameInput(
                (string) ($config['exportFilename'] ?? '')
            );
        }

        $restrictedActions = self::restrictedActions($config);
        if ($restrictedActions !== null) {
            $parameters['cblist_actions'] = implode('|', $restrictedActions);
        }

        $listBehaviourParameters = array_diff_key($parameters, array_flip([
            'cb_export_filename_mode',
            'cb_export_filename',
        ]));
        if (count($listBehaviourParameters) > 7 || $fields !== '' || $searchFields !== '' || $linkFields !== '' || $detailFields !== '' || $editFields !== '' || $exportFields !== '' || $publishedFields !== '' || $parameters[MenuDataFilterService::INPUT_NAME] !== '') {
            $parameters['cblist_embed'] = 'content-plugin';
        }

        return $parameters;
    }

    private static function selectorList(array $config, string $modeKey, string $valuesKey): string
    {
        if ((string) ($config[$modeKey] ?? 'default') !== 'custom') {
            return '';
        }

        $values = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => trim((string) $value),
            is_array($config[$valuesKey] ?? null) ? $config[$valuesKey] : []
        ), static fn(string $value): bool => $value !== '')));

        return implode('|', $values);
    }

    /** @return list<string>|null */
    private static function restrictedActions(array $config): ?array
    {
        $actions = is_array($config['action'] ?? null) ? $config['action'] : [];
        $security = is_array($config['security'] ?? null) ? $config['security'] : [];
        $disabled = [];
        foreach (['new', 'detail', 'edit', 'delete', 'publish', 'state'] as $action) {
            if ((string) ($security[$action] ?? 'inherit') === 'disabled') {
                $disabled[] = $action;
            }
        }
        foreach (['export', 'print', 'rating'] as $action) {
            if ((string) ($actions[$action] ?? 'default') === 'no') {
                $disabled[] = $action;
            }
        }
        if ((string) ($config['searchShow'] ?? 'default') === 'no') {
            $disabled[] = 'search';
        }
        if ($disabled === []) {
            return null;
        }

        return array_values(array_diff(EmbeddedListActionFilterService::ACTIONS, array_unique($disabled)));
    }

    private static function normalizeTitle(string $title): string
    {
        $title = str_replace(["\r\n", "\r"], "\n", $title);
        $title = preg_replace_callback(
            '/[\p{Cc}\p{Cf}]/u',
            static fn(array $match): string => in_array($match[0], ["\n", "\u{200D}"], true) ? $match[0] : '',
            $title
        ) ?? $title;
        $title = trim($title);

        return mb_substr($title, 0, 255, 'UTF-8');
    }

    private static function normalizeExportFilenameInput(string $title): string
    {
        $title = trim($title);

        return function_exists('mb_substr')
            ? (string) mb_substr($title, 0, 150, 'UTF-8')
            : (string) substr($title, 0, 150);
    }

    private static function toggleValue(array $config, string $key): string
    {
        $value = (string) ($config[$key] ?? 'default');

        return in_array($value, ['yes', 'no'], true) ? $value : 'default';
    }
}
