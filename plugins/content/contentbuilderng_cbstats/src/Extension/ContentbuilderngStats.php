<?php

namespace CB\Plugin\Content\ContentbuilderngStats\Extension;

/**
 * @package     ContentBuilderNG Stats
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use CB\Component\Contentbuilderng\Site\Service\StatsService;
use CB\Component\Contentbuilderng\Site\Service\StatsFilterValueService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Plugin\Content\ContentbuilderngStats\Service\PiePresentationService;
use CB\Plugin\Content\ContentbuilderngStats\Service\TotalPresentationService;
use CB\Plugin\Content\ContentbuilderngStats\Service\ManualValuesException;
use CB\Plugin\Content\ContentbuilderngStats\Service\ManualValuesParser;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

final class ContentbuilderngStats extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    private static int $pieInstance = 0;
    private static int $barInstance = 0;
    private static bool $pieAssetsLoaded = false;
    private static bool $barAssetsLoaded = false;
    private static bool $chartAssetRegistryLoaded = false;

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    public function onContentPrepare(EventInterface $event): void
    {
        $article = $event->getArgument('subject');

        if (!is_object($article) || !isset($article->text) || stripos((string) $article->text, '{CBStats') === false) {
            return;
        }

        $article->text = preg_replace_callback(
            '/\{CBStats\b([^}]*)\}/i',
            fn(array $match): string => $this->renderStatsTag((string) ($match[1] ?? '')),
            (string) $article->text
        );
    }

    private function renderStatsTag(string $rawAttributes): string
    {
        $attributes = $this->parseAttributes($rawAttributes);
        $source = strtolower(trim((string) ($attributes['source'] ?? 'view')));
        $manual = $source === 'manual';
        $formId = (int) (($attributes['id'] ?? '') !== '' ? $attributes['id'] : $this->extractId($rawAttributes));
        $debugRequested = $this->isEnabled((string) ($attributes['debug'] ?? '0'));
        $debug = false;
        $output = strtolower(trim((string) ($attributes['output'] ?? 'total')));
        $allowedOutputs = ['total', 'table', 'form_name', 'sum', 'min', 'max', 'json', 'pie', 'bar'];
        $field = trim((string) ($attributes['field'] ?? ''));
        $filterField = trim((string) ($attributes['filter[field]'] ?? ''));
        $filterValue = trim((string) ($attributes['filter[value]'] ?? ''));
        $value = trim((string) ($attributes['value'] ?? ''));
        $add = trim((string) ($attributes['add'] ?? ''));
        $titles = trim((string) ($attributes['titles'] ?? ''));
        $title = trim((string) ($attributes['title'] ?? ''));
        $background = TotalPresentationService::validateBackground((string) ($attributes['background'] ?? ''));
        $sort = strtolower(trim((string) ($attributes['sort'] ?? 'none')));
        $dir = strtolower(trim((string) ($attributes['dir'] ?? 'asc')));
        $values = (string) ($attributes['values'] ?? '');

        if ($filterField === '' && $field !== '' && $value !== '') {
            $filterField = $field;
            $filterValue = $value;
        }

        try {
            if (!in_array($source, ['view', 'manual'], true)) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_SOURCE'), 400);
            }

            if ($manual) {
                return $this->renderManualStats($values, $output, $sort, $dir, $add, $titles, $title, $background);
            }

            if (!class_exists(StatsService::class)) {
                $servicePath = JPATH_ROOT . '/components/com_contentbuilderng/src/Service/StatsService.php';

                if (is_file($servicePath)) {
                    require_once $servicePath;
                }
            }
            if (!class_exists(StatsFilterValueService::class)) {
                $servicePath = JPATH_ROOT . '/components/com_contentbuilderng/src/Service/StatsFilterValueService.php';

                if (is_file($servicePath)) {
                    require_once $servicePath;
                }
            }

            $debug = $debugRequested && StatsService::isFormDebugEnabled($formId);

            if ($formId < 1) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_ID_REQUIRED'), 400);
            }

            if (!$this->canViewStats($formId)) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_ACCESS_DENIED'), 403);
            }

            if (!in_array($output, $allowedOutputs, true)) {
                throw new \RuntimeException(
                    Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_OUTPUT', $output),
                    400
                );
            }

            $fieldOutputs = ['table', 'json', 'pie', 'bar', 'sum', 'min', 'max'];

            if (in_array($output, $fieldOutputs, true) && $field === '') {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_FIELD_REQUIRED'), 400);
            }

            if (($filterField === '') !== ($filterValue === '')) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_FILTER'), 400);
            }

            $filterValues = (new StatsFilterValueService())->parseAlternatives($filterValue);

            if ($filterValue !== '' && $filterValues === []) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_FILTER'), 400);
            }

            if (in_array($output, ['table', 'json', 'pie', 'bar'], true)) {
                if (!in_array($sort, ['none', 'title', 'value'], true)) {
                    throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_SORT'), 400);
                }

                if (!in_array($dir, ['asc', 'desc'], true)) {
                    throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_DIR'), 400);
                }
            }

            $payload = (new StatsService(RuntimeContextHelper::getDatabase()))->getStatsPayload($formId, [
                'field' => $field,
                'filter' => [
                    'field' => $filterField,
                    'value' => $filterValue,
                    'values' => $filterValues,
                ],
            ]);
            $total = $payload['records']['total'] ?? null;

            if ($debug && $total === null) {
                return Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_UNAVAILABLE');
            }

            if ($debug && $output === 'total') {
                return $this->renderDebugMessage(
                    Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_TOTAL', $formId, (int) ($total ?? 0))
                );
            }

            if ($debug && in_array($output, ['sum', 'min', 'max'], true)) {
                $numericValue = $payload['field'][$output] ?? null;
                return $this->renderDebugMessage(Text::sprintf(
                    'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_AGGREGATE',
                    $formId,
                    $output,
                    $numericValue !== null
                        ? (string) $numericValue
                        : Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_NON_NUMERIC')
                ));
            }

            $locale = Factory::getApplication()->getLanguage()->getTag();
            $fieldStats = $this->getFieldStats(
                $payload,
                $sort,
                $dir,
                $locale,
                in_array($output, ['table', 'json', 'pie', 'bar'], true) ? $add : '',
                in_array($output, ['table', 'json', 'pie', 'bar'], true) ? $titles : ''
            );

            if ($debug && in_array($output, ['json', 'pie', 'bar'], true)) {
                return $this->renderDebugMessage(Text::sprintf(
                    'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_ITEMS',
                    $formId,
                    $output,
                    count($fieldStats)
                ));
            }

            return match ($output) {
                'form_name' => htmlspecialchars($this->getFormName($payload), ENT_QUOTES, 'UTF-8'),
                'table' => $this->renderTable($payload, $fieldStats, $title, $background),
                'json' => $this->renderJson($fieldStats),
                'pie' => $this->renderPie($payload, $fieldStats, $title, $background),
                'bar' => $this->renderBar($payload, $fieldStats, $title, $background),
                'total' => (string) StatsService::resolveCbstatsOutput($payload, 'total'),
                'sum' => $this->renderSum($payload),
                'min' => $this->renderNumericFieldValue($payload, 'min'),
                'max' => $this->renderNumericFieldValue($payload, 'max'),
            };
        } catch (\Throwable $exception) {
            if ($exception instanceof ManualValuesException) {
                $message = $exception->getEntry() === ''
                    ? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_MANUAL_VALUES_REQUIRED')
                    : Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_MANUAL_VALUE_INVALID', $exception->getEntry());

                return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            }

            if (!$debug) {
                return (int) $exception->getCode() === 400 || $exception instanceof \InvalidArgumentException
                    ? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_INVALID_REQUEST')
                    : Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_UNAVAILABLE');
            }

            if ($exception instanceof \InvalidArgumentException) {
                return $this->renderDebugMessage($this->getFieldStatsErrorMessage($exception));
            }

            $code = (int) $exception->getCode();

            return $code >= 400 && $code < 500
                ? $this->renderDebugMessage($exception->getMessage())
                : Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_UNAVAILABLE');
        }
    }

    private function renderDebugMessage(string $message): string
    {
        return htmlspecialchars(
            Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_MESSAGE', $message),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    private function getFieldStatsErrorMessage(\InvalidArgumentException $exception): string
    {
        return match ($exception->getCode()) {
            StatsService::CBSTATS_ERROR_INVALID_TITLES => Text::_(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_TITLES'
            ),
            default => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_ADD'),
        };
    }

    private function getFormName(array $payload): string
    {
        return (string) StatsService::resolveCbstatsOutput($payload, 'form_name');
    }

    private function canViewStats(int $formId): bool
    {
        try {
            $app = Factory::getApplication();
            $frontend = $app->isClient('site');
            $permissions = PermissionService::createFromRuntimeContext();

            if ($frontend) {
                $permissions->setPermissions($formId, 0, '_fe');

                if ($permissions->authorizeFe('stats')) {
                    return true;
                }
            }

            $permissions->setPermissions($formId);

            return $permissions->authorize('stats');
        } catch (\Throwable) {
            return false;
        }
    }

    private function parseAttributes(string $rawAttributes): array
    {
        $attributes = [];
        $rawAttributes = $this->normalizeAttributes($rawAttributes);

        preg_match_all('/([A-Za-z0-9_\-\[\]]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s]+))/u', $rawAttributes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = strtolower((string) $match[1]);
            $value = '';

            foreach ([3, 4, 5] as $index) {
                if (isset($match[$index]) && (string) $match[$index] !== '') {
                    $value = (string) $match[$index];
                    break;
                }
            }

            if ($key !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $attributes;
    }

    private function extractId(string $rawAttributes): int
    {
        $rawAttributes = $this->normalizeAttributes($rawAttributes);

        if (preg_match('/\bid\s*[:=]\s*["\']?([0-9]+)/iu', $rawAttributes, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    private function normalizeAttributes(string $rawAttributes): string
    {
        $rawAttributes = str_replace('&nbsp;', ' ', $rawAttributes);
        $rawAttributes = html_entity_decode($rawAttributes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawAttributes = strip_tags($rawAttributes);
        $rawAttributes = str_replace("\u{00A0}", ' ', $rawAttributes);

        return preg_replace('/\s+/u', ' ', $rawAttributes) ?? $rawAttributes;
    }

    private function isEnabled(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function renderSum(array $payload): string
    {
        return $this->renderNumericFieldValue($payload, 'sum');
    }

    private function renderNumericFieldValue(array $payload, string $key): string
    {
        $value = StatsService::resolveCbstatsOutput($payload, $key);

        return $value == (int) $value ? (string) (int) $value : (string) $value;
    }

    /**
     * @return list<array{label: string, value: int|float}>
     */
    private function getFieldStats(
        array $payload,
        string $sort,
        string $dir,
        string $locale,
        string $add,
        string $titles
    ): array
    {
        $field = (array) ($payload['field'] ?? []);
        $additions = $field === [] ? [] : StatsService::parseFieldStatsAdditions($add);
        $titleMappings = $field === [] ? [] : StatsService::parseFieldStatsTitles($titles);

        return StatsService::normalizeFieldStats(
            (array) ($field['values'] ?? []),
            $sort,
            $dir,
            $locale,
            $additions,
            $titleMappings
        );
    }

    /**
     * @param list<array{label: string, value: int|float}> $fieldStats
     */
    private function renderJson(array $fieldStats): string
    {
        $json = json_encode($fieldStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '[]' : $json;
    }

    /**
     * @param list<array{label: string, value: int|float}> $fieldStats
     */
    private function renderTable(array $payload, array $fieldStats, string $title, string $background): string
    {
        $field = (array) ($payload['field'] ?? []);

        if ($fieldStats === []) {
            return '<span class="cbstats cbstats-empty">0</span>';
        }

        $this->loadDataTableAssets();

        $label = (string) ($field['label'] ?? $field['requested'] ?? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'));
        $html = '<div class="cbstats-table-wrapper cbstats-card"' . $this->renderBackgroundStyle($background) . '><table class="table table-sm cbstats-table">'
            . '<thead><tr>'
            . '<th scope="col" class="cbstats-table-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th scope="col" class="cbstats-table-number">' . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TOTAL'), ENT_QUOTES, 'UTF-8') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($fieldStats as $item) {
            $html .= '<tr>'
                . '<td class="cbstats-table-label">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="cbstats-table-number">' . $this->formatNumber($item['value']) . '</td>'
                . '</tr>';
        }

        $total = array_sum(array_column($fieldStats, 'value'));

        return $html . '</tbody><tfoot><tr class="cbstats-total-row"><th scope="row" class="cbstats-table-label cbstats-total-label">'
            . $this->renderTotalLabel($title) . '</th><td class="cbstats-table-number cbstats-total-value"><strong>'
            . $this->formatNumber($total) . '</strong></td></tr></tfoot></table></div>';
    }

    /**
     * @param list<array{label: string, value: int|float}> $fieldStats
     */
    private function renderPie(array $payload, array $fieldStats, string $title, string $background): string
    {
        if ($fieldStats === []) {
            return '<span class="cbstats-pie-empty">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_NO_DATA'), ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        $this->loadPieAssets();

        $field = (array) ($payload['field'] ?? []);
        $fieldLabel = (string) ($field['label'] ?? $field['requested'] ?? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'));
        $locale = Factory::getApplication()->getLanguage()->getTag();
        $presentation = PiePresentationService::prepare($fieldStats, $locale);
        $total = $presentation['total'];
        $items = $presentation['items'];

        $instanceId = 'cbstats-pie-' . ++self::$pieInstance;
        $json = json_encode(
            ['items' => $items],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        $encodedPayload = htmlspecialchars($json === false ? '{"items":[]}' : $json, ENT_QUOTES, 'UTF-8');
        $ariaLabel = Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_PIE_ARIA_LABEL', $fieldLabel);
        $html = '<section class="cbstats-pie cbstats-card" data-cbstats-pie="' . $encodedPayload . '"'
            . $this->renderBackgroundStyle($background) . '>'
            . '<div class="cbstats-pie-chart">'
            . '<canvas id="' . $instanceId . '" class="cbstats-pie-canvas" role="img" aria-label="'
            . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_CHART_UNAVAILABLE'), ENT_QUOTES, 'UTF-8')
            . '</canvas></div>';

        return $html . $this->renderChartDetails($items, $total, $title);
    }

    /**
     * @param list<array{label: string, value: int|float}> $fieldStats
     */
    private function renderBar(array $payload, array $fieldStats, string $title, string $background): string
    {
        if ($fieldStats === []) {
            return '<span class="cbstats-bar-empty">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_NO_DATA'), ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        $this->loadBarAssets();

        $field = (array) ($payload['field'] ?? []);
        $fieldLabel = (string) ($field['label'] ?? $field['requested'] ?? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'));
        $locale = Factory::getApplication()->getLanguage()->getTag();
        $presentation = PiePresentationService::prepare($fieldStats, $locale);
        $total = $presentation['total'];
        $items = $presentation['items'];
        $instanceId = 'cbstats-bar-' . ++self::$barInstance;
        $json = json_encode(
            ['items' => $items],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        $encodedPayload = htmlspecialchars($json === false ? '{"items":[]}' : $json, ENT_QUOTES, 'UTF-8');
        $ariaLabel = Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_BAR_ARIA_LABEL', $fieldLabel);
        $html = '<section class="cbstats-bar cbstats-card" data-cbstats-bar="' . $encodedPayload . '"'
            . $this->renderBackgroundStyle($background) . '>'
            . '<div class="cbstats-bar-chart" style="--cbstats-bar-items:' . count($items) . '">'
            . '<canvas id="' . $instanceId . '" class="cbstats-bar-canvas" role="img" aria-label="'
            . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_CHART_UNAVAILABLE'), ENT_QUOTES, 'UTF-8')
            . '</canvas></div>';

        return $html . $this->renderChartDetails($items, $total, $title);
    }

    /**
     * @param list<array{label: string, value: int|float, percentage: float, percentageLabel: string, color: string}> $items
     */
    private function renderChartDetails(array $items, int|float $total, string $title): string
    {
        $html = '<div class="cbstats-pie-legend" role="list">';

        foreach ($items as $item) {
            $html .= '<div class="cbstats-pie-legend-row" role="listitem">'
                . '<span class="cbstats-pie-swatch" style="--cbstats-color:'
                . htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span>'
                . '<span class="cbstats-pie-label">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '<span class="cbstats-pie-value"><span aria-hidden="true">&mdash; </span>'
                . $this->formatNumber($item['value']) . ' (' . htmlspecialchars($item['percentageLabel'], ENT_QUOTES, 'UTF-8') . '&nbsp;%)</span>'
                . '</div>';
        }

        return $html . '</div><div class="cbstats-total-box"><span class="cbstats-total-label">'
            . $this->renderTotalLabel($title) . '</span> <strong class="cbstats-total-value">'
            . $this->formatNumber($total) . '</strong></div></section>';
    }

    private function formatNumber(int|float $value): string
    {
        return floor((float) $value) === (float) $value
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }

    private function renderManualStats(
        string $values,
        string $output,
        string $sort,
        string $dir,
        string $add,
        string $titles,
        string $title,
        string $background
    ): string {
        if (!in_array($output, ['total', 'table', 'pie', 'bar'], true)) {
            throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_MANUAL_OUTPUT'), 400);
        }

        $sort = $sort === 'label' ? 'title' : $sort;

        if (!in_array($sort, ['none', 'title', 'value'], true)) {
            throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_SORT'), 400);
        }

        if (!in_array($dir, ['asc', 'desc'], true)) {
            throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_DIR'), 400);
        }

        $payload = [
            'records' => ['total' => 0],
            'field' => [
                'requested' => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'),
                'label' => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'),
                'values' => ManualValuesParser::parse($values),
            ],
        ];
        $locale = Factory::getApplication()->getLanguage()->getTag();
        $fieldStats = $this->getFieldStats($payload, $sort, $dir, $locale, $add, $titles);
        $total = array_sum(array_column($fieldStats, 'value'));
        $payload['records']['total'] = $total;

        return match ($output) {
            'table' => $this->renderTable($payload, $fieldStats, $title, $background),
            'pie' => $this->renderPie($payload, $fieldStats, $title, $background),
            'bar' => $this->renderBar($payload, $fieldStats, $title, $background),
            'total' => $this->formatNumber($total),
        };
    }

    private function renderTotalLabel(string $title): string
    {
        return htmlspecialchars(TotalPresentationService::formatLabel(
            $title,
            Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TOTAL'),
            Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TOTAL_SEPARATOR')
        ), ENT_QUOTES, 'UTF-8');
    }

    private function renderBackgroundStyle(string $background): string
    {
        return $background === '' ? '' : ' style="--cbstats-background:' . $background . '"';
    }

    private function loadPieAssets(): void
    {
        if (self::$pieAssetsLoaded) {
            return;
        }

        $wa = $this->getCbstatsWebAssetManager();
        $wa->usePreset('plg_content_contentbuilderng_cbstats.pie');
        self::$pieAssetsLoaded = true;
    }

    private function loadBarAssets(): void
    {
        if (self::$barAssetsLoaded) {
            return;
        }

        $wa = $this->getCbstatsWebAssetManager();
        $wa->usePreset('plg_content_contentbuilderng_cbstats.bar');
        self::$barAssetsLoaded = true;
    }

    private function loadDataTableAssets(): void
    {
        $this->getCbstatsWebAssetManager()->useStyle('plg_content_contentbuilderng_cbstats.data');
    }

    private function getCbstatsWebAssetManager(): WebAssetManager
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        if (!self::$chartAssetRegistryLoaded) {
            $wa->getRegistry()->addRegistryFile('media/plg_content_contentbuilderng_cbstats/joomla.asset.json');
            self::$chartAssetRegistryLoaded = true;
        }

        return $wa;
    }

}
