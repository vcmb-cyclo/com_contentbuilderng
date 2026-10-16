<?php

/**
 * @package     ContentBuilderNG Stats
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

namespace CB\Plugin\Content\ContentbuilderngStats\Extension;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use CB\Component\Contentbuilderng\Site\Service\StatsService;
use CB\Component\Contentbuilderng\Site\Service\StatsFilterValueService;
use CB\Component\Contentbuilderng\Site\Service\StatsHideOptionsService;
use CB\Component\Contentbuilderng\Site\Service\CbstatsHelpService;
use CB\Component\Contentbuilderng\Site\Service\ContentCardService;
use CB\Component\Contentbuilderng\Site\Service\EditorialCardService;
use CB\Component\Contentbuilderng\Site\Service\CbStatsTitleSetService;
use CB\Component\Contentbuilderng\Site\Service\CbStatsConfigService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Plugin\Content\ContentbuilderngStats\Service\PiePresentationService;
use CB\Plugin\Content\ContentbuilderngStats\Service\TotalPresentationService;
use CB\Plugin\Content\ContentbuilderngStats\Service\ManualValuesException;
use CB\Plugin\Content\ContentbuilderngStats\Service\ManualValuesParser;
use CB\Plugin\Content\ContentbuilderngStats\Service\ManualExportService;
use CB\Plugin\Content\ContentbuilderngStats\Service\TagSyntaxService;
use CB\Plugin\Content\ContentbuilderngStats\Service\IdSumException;
use CB\Plugin\Content\ContentbuilderngStats\Service\IdSumService;
use CB\Plugin\Content\ContentbuilderngStats\Service\DisplayOptionsService;
use CB\Plugin\Content\ContentbuilderngStats\Service\TableHeaderService;
use CB\Plugin\Content\ContentbuilderngStats\Service\StatsTagValidationService;
use CB\Plugin\Content\ContentbuilderngStats\Service\CssDimensionService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

final class ContentbuilderngStats extends CMSPlugin implements SubscriberInterface
{
    private const RADAR_MIN_AXES = 3;
    private const RADAR_MAX_AXES = 8;

    protected $autoloadLanguage = true;
    private static int $pieInstance = 0;
    private static int $barInstance = 0;
    private static int $chartInstance = 0;
    private static bool $pieAssetsLoaded = false;
    private static bool $barAssetsLoaded = false;
    private static bool $chartAssetsLoaded = false;
    private static bool $chartAssetRegistryLoaded = false;
    private static bool $manualExportAssetsLoaded = false;
    private ?CbStatsTitleSetService $titleSetService = null;
    private ?CbStatsConfigService $configService = null;
    /** @var array<string, true> */
    private array $warnedTitleSets = [];
    /** @var array<string, true> */
    private array $warnedConfigs = [];
    /** @var array{width: string, height: string} */
    private array $currentDimensions = ['width' => '', 'height' => ''];

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    public function onContentPrepare(EventInterface $event): void
    {
        $article = $event->getArgument('subject');

        if (!is_object($article) || !isset($article->text)) {
            return;
        }

        $text = (string) $article->text;
        $hasStats = stripos($text, '{CBStats') !== false;
        $hasEditorialCards = EditorialCardService::containsMarker($text);

        if (!$hasStats && !$hasEditorialCards) {
            return;
        }

        if ($hasEditorialCards) {
            $text = EditorialCardService::transform($text);
            $wa = $this->getCbstatsWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
            $wa->useStyle('com_contentbuilderng.cards');
        }

        if ($hasStats) {
            $text = preg_replace_callback(
                TagSyntaxService::TAG_PATTERN,
                fn(array $match): string => $this->renderArticleTag($match[1]),
                $text
            );
        }

        $article->text = $text;
    }

    private function renderArticleTag(string $rawAttributes): string
    {
        $syntax = $this->resolveConfigSyntax($rawAttributes);
        $attributes = $syntax['attributes'];
        $html = $this->renderStatsTag($rawAttributes, $syntax);
        $card = ContentCardService::normalize((string) ($attributes['card'] ?? ''));

        if (str_contains($html, 'class="alert alert-warning"')) {
            return $html;
        }

        $labels = StatsService::parseFieldStatsLabels((string) ($attributes['labels'] ?? ''));
        $hideOptions = StatsHideOptionsService::fromAttributes($attributes);
        $blockTitle = $hideOptions['title'] ? '' : (string) ($labels['title'] ?? '');

        if ($card === '') {
            return $blockTitle === ''
                ? $html
                : '<h3 class="cbstats-title">' . htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8') . '</h3>' . $html;
        }

        $wa = $this->getCbstatsWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useStyle('com_contentbuilderng.cards');

        return ContentCardService::render(
            $html,
            $card,
            $blockTitle,
            ContentCardService::normalizeWidth((string) ($attributes['w'] ?? ''))
        );
    }

    /** @param array{attributes: array<string, string>, quoted: array<string, bool>}|null $resolvedSyntax */
    private function renderStatsTag(string $rawAttributes, ?array $resolvedSyntax = null): string
    {
        $syntax = $resolvedSyntax ?? $this->resolveConfigSyntax($rawAttributes);
        $attributes = $syntax['attributes'];
        $source = TagSyntaxService::normalizeKeyword((string) ($attributes['source'] ?? 'view'));
        $manual = $source === 'manual';
        $idValue = trim((string) ($attributes['id'] ?? ''));
        $idSumValue = trim((string) ($attributes['idsum'] ?? ''));
        $formId = (int) ($idValue !== '' ? $idValue : $this->extractId($rawAttributes));
        $formIds = [];

        if (!class_exists(StatsService::class)) {
            $servicePath = JPATH_ROOT . '/components/com_contentbuilderng/src/Service/StatsService.php';

            if (is_file($servicePath)) {
                require_once $servicePath;
            }
        }

        if (!class_exists(StatsService::class)) {
            return $this->renderErrorMessages([Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_UNAVAILABLE')]);
        }

        $validationErrors = StatsTagValidationService::validationErrors($attributes, $formId, $syntax['quoted']);
        if ($validationErrors !== []) {
            return $this->renderValidationErrors($validationErrors);
        }

        $this->currentDimensions = [
            'width' => CssDimensionService::normalize((string) ($attributes['width'] ?? '')) ?? '',
            'height' => CssDimensionService::normalize((string) ($attributes['height'] ?? '')) ?? '',
        ];

        $debugRequested = $this->isEnabled((string) ($attributes['debug'] ?? '0'));
        $debug = false;
        $output = TagSyntaxService::normalizeKeyword((string) ($attributes['output'] ?? 'total'));
        $allowedOutputs = [
            'total', 'table', 'pie', 'bar', 'histogram', 'line', 'radar', 'json',
            'sum', 'min', 'max', 'avg', 'remaining', 'percentage', 'progress', 'distinct', 'view_name',
        ];
        $listOutputs = ['table', 'json', 'pie', 'bar', 'histogram', 'line', 'radar'];
        $field = trim((string) ($attributes['field'] ?? ''));
        $filter = $output === 'percentage'
            ? [
                'field' => trim((string) ($attributes['filter[field]'] ?? '')),
                'value' => trim((string) ($attributes['filter[value]'] ?? '')),
            ]
            : TagSyntaxService::resolveFilter($attributes);
        $filterField = $filter['field'];
        $filterValue = $filter['value'];
        $add = trim((string) ($attributes['add'] ?? ''));
        $titles = trim((string) ($attributes['titles'] ?? ''));
        $titleSet = trim((string) ($attributes['titleset'] ?? ''));
        $titleSetMappings = in_array($output, $listOutputs, true)
            ? $this->resolveTitleSet($titleSet)
            : [];
        $groups = trim((string) ($attributes['groups'] ?? ''));
        $groupSet = trim((string) ($attributes['groupset'] ?? ''));
        $labelsRaw = trim((string) ($attributes['labels'] ?? ''));
        $background = TotalPresentationService::validateBackground((string) ($attributes['background'] ?? ''));
        $sort = TagSyntaxService::normalizeKeyword((string) ($attributes['sort'] ?? 'none'));
        $dir = TagSyntaxService::normalizeKeyword((string) ($attributes['dir'] ?? 'asc'));
        $values = (string) ($attributes['values'] ?? '');
        $exportManual = ManualExportService::isRequested((string) ($attributes['export'] ?? ''));
        $target = (float) ($attributes['target'] ?? 0);
        $selectedValues = (new StatsFilterValueService())->parseAlternatives(
            trim((string) ($attributes['value'] ?? ''))
        );

        try {
            $limit = DisplayOptionsService::parseLimit($attributes);
            $hideOptions = StatsHideOptionsService::fromAttributes($attributes);
            $labelMappings = StatsService::parseFieldStatsLabels($labelsRaw);
            $totalLabel = (string) ($labelMappings['total'] ?? '');

            if (!in_array($source, ['view', 'manual'], true)) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_SOURCE'), 400);
            }

            if ($manual) {
                return $this->renderManualStats(
                    $values,
                    $output,
                    $sort,
                    $dir,
                    $add,
                    $titles,
                    $titleSetMappings,
                    $labelsRaw,
                    $labelMappings,
                    $totalLabel,
                    $background,
                    $exportManual,
                    $limit,
                    $hideOptions
                );
            }

            $formIds = IdSumService::resolveSourceIds($idValue, $idSumValue, $formId);

            if (!class_exists(StatsFilterValueService::class)) {
                $servicePath = JPATH_ROOT . '/components/com_contentbuilderng/src/Service/StatsFilterValueService.php';

                if (is_file($servicePath)) {
                    require_once $servicePath;
                }
            }

            $statsService = new StatsService(RuntimeContextHelper::getDatabase());
            $debug = $debugRequested
                && $formIds !== []
                && array_reduce(
                    $formIds,
                    static fn(bool $enabled, int $id): bool => $enabled && $statsService->isFormDebugEnabled($id),
                    true
                );

            if ($formIds === [] || min($formIds) < 1) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_ID_REQUIRED'), 400);
            }

            foreach ($formIds as $id) {
                if (!$this->canViewStats($id)) {
                    throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_ACCESS_DENIED'), 403);
                }
            }

            if (!in_array($output, $allowedOutputs, true)) {
                throw new \RuntimeException(
                    Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_OUTPUT', $output),
                    400
                );
            }

            StatsHideOptionsService::validateForOutput($hideOptions, $output);

            $fieldOutputs = [...$listOutputs, 'distinct', 'sum', 'min', 'max', 'avg'];
            if ((in_array($output, $fieldOutputs, true) || $idSumValue !== '') && $field === '') {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_FIELD_REQUIRED'), 400);
            }

            if ($idSumValue !== '' && $output === 'view_name') {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_IDSUM_VIEW_NAME'), 400);
            }

            if (($filterField === '') !== ($filterValue === '')) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_FILTER'), 400);
            }

            $filterValues = (new StatsFilterValueService())->parseAlternatives($filterValue);

            if ($filterValue !== '' && $filterValues === []) {
                throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_FILTER'), 400);
            }

            if (in_array($output, $listOutputs, true)) {
                if (!in_array($sort, ['none', 'title', 'value'], true)) {
                    throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_SORT'), 400);
                }

                if (!in_array($dir, ['asc', 'desc'], true)) {
                    throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_DIR'), 400);
                }
            }

            $payloads = IdSumService::collectPayloads(
                $formIds,
                static fn(int $id, array $options): array => $statsService->getStatsPayload($id, $options),
                [
                    'field' => $field,
                    'filter' => [
                        'field' => $filterField,
                        'value' => $filterValue,
                        'values' => $filterValues,
                    ],
                ]
            );

            $payload = $idSumValue !== '' ? IdSumService::mergePayloads($payloads) : $payloads[0];
            $total = $payload['records']['total'] ?? null;
            $debugSource = $idSumValue !== '' ? implode('+', $formIds) : (string) $formId;

            if ($debug && $total === null) {
                return Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_UNAVAILABLE');
            }

            if ($debug && $output === 'total') {
                return $this->renderDebugMessage(
                    Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_TOTAL', $debugSource, (int) ($total ?? 0))
                );
            }

            if ($debug && $output === 'remaining') {
                return $this->renderDebugMessage(Text::sprintf(
                    'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_REMAINING',
                    $debugSource,
                    $this->formatNumber($target),
                    $this->formatNumber(StatsService::resolveRemainingOutput($payload, $target))
                ));
            }

            if ($debug && $output === 'progress') {
                return $this->renderDebugMessage(Text::sprintf(
                    'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_PROGRESS',
                    $debugSource,
                    $this->formatNumber($target),
                    $this->formatPercentage(StatsService::resolveProgressOutput($payload, $target))
                ));
            }

            if ($debug && $output === 'percentage') {
                return $this->renderDebugMessage(Text::sprintf(
                    'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_PERCENTAGE',
                    $debugSource,
                    trim((string) ($attributes['value'] ?? '')),
                    $this->formatPercentage(StatsService::resolvePercentageOutput(
                        (array) ($payload['field']['values'] ?? []),
                        $selectedValues,
                        (int) ($payload['records']['total'] ?? 0)
                    ))
                ));
            }

            if ($debug && in_array($output, ['distinct', 'sum', 'min', 'max', 'avg'], true)) {
                $numericValue = $payload['field'][$output] ?? null;
                return $this->renderDebugMessage(Text::sprintf(
                    'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_AGGREGATE',
                    $debugSource,
                    $output,
                    $numericValue !== null
                        ? (string) $numericValue
                        : Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_NON_NUMERIC')
                ));
            }

            $locale = Factory::getApplication()->getLanguage()->getTag();
            $groupSetMappings = $groups === '' ? $this->resolveGroupSet($groupSet) : [];
            $groupDefinitions = $groups !== ''
                ? StatsService::parseFieldStatsGroups($groups)
                : StatsService::parseFieldStatsGroupSelectors(implode(';', array_keys($groupSetMappings)));
            $inlineGroupMappings = [];
            foreach ($groupDefinitions as $groupDefinition) {
                if ($groupDefinition['title'] !== null) {
                    $inlineGroupMappings[$groupDefinition['label']] = $groupDefinition['title'];
                }
            }
            $usesGroups = $groupDefinitions !== [];
            $fullFieldStats = $this->getFieldStats(
                $payload,
                $usesGroups ? 'none' : $sort,
                $dir,
                $locale,
                in_array($output, $listOutputs, true) ? $add : '',
                in_array($output, $listOutputs, true) ? $titles : '',
                in_array($output, $listOutputs, true)
                    ? array_replace($groupSetMappings, $inlineGroupMappings, $titleSetMappings)
                    : [],
                $groupDefinitions
            );
            $fieldStats = in_array($output, $listOutputs, true)
                ? DisplayOptionsService::applyLimit($fullFieldStats, $limit)
                : $fullFieldStats;
            $fieldTotal = array_sum(array_column($fieldStats, 'value'));
            $displayTotal = $fieldTotal;

            if ($debug && in_array($output, ['json', 'pie', 'bar', 'histogram', 'line', 'radar'], true)) {
                return $this->renderDebugMessage(Text::sprintf(
                    'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_ITEMS',
                    $debugSource,
                    $output,
                    count($fieldStats)
                ));
            }

            return match ($output) {
                'view_name' => htmlspecialchars($this->getViewName($payload), ENT_QUOTES, 'UTF-8'),
                'table' => $this->renderTable(
                    $payload,
                    $fieldStats,
                    $displayTotal,
                    $totalLabel,
                    $background,
                    $labelMappings,
                    $labelsRaw,
                    $exportManual,
                    $hideOptions
                ),
                'json' => $this->renderJson($fieldStats),
                'pie' => $this->renderPie($payload, $fieldStats, $displayTotal, $totalLabel, $background, $labelsRaw, $exportManual, $hideOptions),
                'bar' => $this->renderBar($payload, $fieldStats, $displayTotal, $totalLabel, $background, $labelsRaw, $exportManual, $hideOptions),
                'histogram', 'line', 'radar' => $this->renderGenericChart(
                    $payload,
                    $fieldStats,
                    $displayTotal,
                    $output,
                    $totalLabel,
                    $background,
                    $labelsRaw,
                    $exportManual,
                    $hideOptions
                ),
                'total' => (string) StatsService::resolveCbstatsOutput($payload, 'total'),
                'remaining' => $this->formatNumber(StatsService::resolveRemainingOutput($payload, $target)),
                'percentage' => $this->formatPercentage(StatsService::resolvePercentageOutput(
                    (array) ($payload['field']['values'] ?? []),
                    $selectedValues,
                    (int) ($payload['records']['total'] ?? 0)
                )),
                'progress' => $this->formatPercentage(StatsService::resolveProgressOutput($payload, $target)),
                'distinct' => $this->renderNumericFieldValue($payload, 'distinct'),
                'sum' => $this->renderSum($payload),
                'min' => $this->renderNumericFieldValue($payload, 'min'),
                'max' => $this->renderNumericFieldValue($payload, 'max'),
                'avg' => $this->renderNumericFieldValue($payload, 'avg'),
            };
        } catch (\Throwable $exception) {
            if ($exception instanceof ManualValuesException) {
                $message = $exception->getEntry() === ''
                    ? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_MANUAL_VALUES_REQUIRED')
                    : Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_MANUAL_VALUE_INVALID', $exception->getEntry());

                return $this->renderErrorMessages([$message]);
            }

            if ($exception instanceof IdSumException) {
                $message = match ($exception->getCode()) {
                    IdSumException::TOO_FEW => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_IDSUM_TOO_FEW'),
                    IdSumException::TOO_MANY => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_IDSUM_TOO_MANY'),
                    IdSumException::DUPLICATE_ID => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_IDSUM_DUPLICATE'),
                    IdSumException::CONFLICT => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_ID_AND_IDSUM'),
                    default => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_IDSUM_INVALID'),
                };

                return $this->renderErrorMessages([$message]);
            }

            if (
                $exception instanceof \InvalidArgumentException
                && $exception->getCode() === DisplayOptionsService::INVALID_LIMIT
            ) {
                $message = Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_LIMIT');

                return $this->renderErrorMessages([$message]);
            }

            if (
                $exception instanceof \InvalidArgumentException
                && in_array($exception->getCode(), [
                    StatsHideOptionsService::INVALID_ITEM,
                    StatsHideOptionsService::INVALID_SEPARATOR,
                    StatsHideOptionsService::NOT_APPLICABLE,
                    StatsHideOptionsService::ALL_HIDDEN,
                    StatsHideOptionsService::LEGACY_TOTAL,
                ], true)
            ) {
                return $this->renderErrorMessages([$this->getHideOptionsErrorMessage($exception)]);
            }

            if (
                $exception instanceof \InvalidArgumentException
                && $exception->getCode() === StatsService::CBSTATS_ERROR_INVALID_GROUPS
            ) {
                $message = $this->getFieldStatsErrorMessage($exception);

                return $this->renderErrorMessages([
                    $debug ? Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_MESSAGE', $message) : $message,
                ]);
            }

            if (!$debug) {
                return $this->renderErrorMessages([
                    (int) $exception->getCode() === 400 || $exception instanceof \InvalidArgumentException
                        ? ((int) $exception->getCode() === 400 && $exception->getMessage() !== ''
                            ? $exception->getMessage()
                            : $this->getFieldStatsErrorMessage($exception))
                        : Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_UNAVAILABLE'),
                ]);
            }

            if ($exception instanceof \InvalidArgumentException) {
                return $this->renderErrorMessages([Text::sprintf(
                    'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_MESSAGE',
                    $this->getFieldStatsErrorMessage($exception)
                )]);
            }

            $code = (int) $exception->getCode();

            return $this->renderErrorMessages([
                $code >= 400 && $code < 500
                    ? Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_MESSAGE', $exception->getMessage())
                    : Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_UNAVAILABLE'),
            ]);
        }
    }

    /** @return array{attributes: array<string, string>, quoted: array<string, bool>} */
    private function resolveConfigSyntax(string $rawAttributes): array
    {
        $syntax = TagSyntaxService::parse($rawAttributes);
        $filename = trim((string) ($syntax['attributes']['config'] ?? ''));
        if ($filename === '') {
            return $syntax;
        }

        $this->configService ??= new CbStatsConfigService(JPATH_SITE);
        $result = $this->configService->resolve($filename);
        if ($result['status'] === 'ok' && !$this->isValidConfigValues($result['values'])) {
            $result['status'] = 'invalid_value';
            $result['values'] = [];
        }
        if ($result['status'] !== 'ok') {
            if ((bool) Factory::getApplication()->get('debug') && !isset($this->warnedConfigs[$filename])) {
                Log::add(
                    Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_CONFIG_WARNING', $filename, $result['status']),
                    Log::WARNING,
                    'com_contentbuilderng.cbstats'
                );
                $this->warnedConfigs[$filename] = true;
            }

            return $syntax;
        }

        try {
            $configured = $result['values'];
            $output = TagSyntaxService::normalizeKeyword((string) ($syntax['attributes']['output'] ?? 'total'));
            if ($output !== 'table') {
                unset($configured['category'], $configured['value']);
            }
            if (!in_array($output, ['table', 'pie', 'bar', 'histogram', 'line', 'radar'], true)) {
                unset($configured['total']);
            }
            if ($output === 'json') {
                unset($configured['title']);
            }
            $syntax['attributes'] = CbStatsConfigService::merge($configured, $syntax['attributes']);
        } catch (\InvalidArgumentException) {
            return $syntax;
        }

        return $syntax;
    }

    /** @param array<string, string> $values */
    private function isValidConfigValues(array $values): bool
    {
        $labelValues = array_intersect_key($values, array_flip(['title', 'category', 'value', 'total']));
        if ($labelValues !== []) {
            try {
                StatsService::parseFieldStatsLabels(implode(';', array_map(
                    static fn(string $key, string $value): string => $key . '=' . $value,
                    array_keys($labelValues),
                    array_values($labelValues)
                )));
            } catch (\InvalidArgumentException) {
                return false;
            }
        }
        if (isset($values['background']) && TotalPresentationService::validateBackground($values['background']) === '') {
            return false;
        }
        if (isset($values['card']) && !ContentCardService::isValid($values['card'])) {
            return false;
        }
        if (isset($values['w']) && !ContentCardService::isValidWidth($values['w'])) {
            return false;
        }
        foreach (['width', 'height'] as $dimension) {
            if (isset($values[$dimension]) && CssDimensionService::normalize($values[$dimension]) === null) {
                return false;
            }
        }
        if (isset($values['sort']) && !in_array(TagSyntaxService::normalizeKeyword($values['sort']), ['none', 'title', 'value'], true)) {
            return false;
        }
        if (isset($values['dir']) && !in_array(TagSyntaxService::normalizeKeyword($values['dir']), ['asc', 'desc'], true)) {
            return false;
        }
        try {
            if (isset($values['limit'])) {
                DisplayOptionsService::parseLimit(['limit' => $values['limit']]);
            }
            if (isset($values['hide'])) {
                StatsHideOptionsService::fromAttributes(['hide' => $values['hide']]);
            }
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @param list<array{code: string, parameter: string, value: string, detail: string}> $errors
     */
    private function renderValidationErrors(array $errors): string
    {
        return $this->renderErrorMessages(array_map(
            fn(array $error): string => $this->validationMessage($error),
            $errors
        ));
    }

    /** @param list<string> $messages */
    private function renderErrorMessages(array $messages): string
    {
        $items = array_map(
            static fn(string $message): string => '<li>'
                . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
                . '</li>',
            $messages
        );
        $helpUrl = CbstatsHelpService::syntaxUrl();

        return '<div class="alert alert-warning" role="alert">'
            . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2"><strong>'
            . '<span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALIDATION_INTRO'), ENT_QUOTES, 'UTF-8')
            . '</strong><a class="ms-3" href="' . htmlspecialchars($helpUrl, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener noreferrer">'
            . '<span class="fa-solid fa-circle-question me-1" aria-hidden="true"></span>'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_SYNTAX_HELP'), ENT_QUOTES, 'UTF-8')
            . '</a></div><ul class="mb-0 mt-2">' . implode('', $items) . '</ul></div>';
    }

    /** @param array{code: string, parameter: string, value: string, detail: string} $error */
    private function validationMessage(array $error): string
    {
        $value = $error['value'] !== ''
            ? $error['value']
            : Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EMPTY_VALUE');

        if ($error['code'] === 'unknown_option') {
            return Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_UNKNOWN_OPTION',
                $error['parameter'],
                $value
            );
        }

        if ($error['code'] === 'removed_option') {
            return Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_REMOVED_OPTION',
                $error['parameter'],
                Text::_(match ($error['detail']) {
                    'labels_title' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS_TITLE',
                    'labels_headers' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS_HEADERS',
                    'labels_total_or_hide' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS_TOTAL_OR_HIDE',
                    default => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS_TOTAL',
                })
            );
        }

        $detailKey = match ($error['detail']) {
            'source' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_SOURCE',
            'id_syntax', 'idsum_syntax', 'limit_syntax', 'w_syntax', 'target_syntax'
                => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_NUMERIC_SYNTAX',
            'id' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_ID',
            'idsum' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_IDSUM',
            'id_conflict' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_ID_CONFLICT',
            'output' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_OUTPUT',
            'target' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_TARGET',
            'target_output' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_TARGET_OUTPUT',
            'percentage_value' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_PERCENTAGE_VALUE',
            'manual_output' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_MANUAL_OUTPUT',
            'idsum_output' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_IDSUM_OUTPUT',
            'field' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_FIELD',
            'filter' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_FILTER',
            'sort' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_SORT',
            'manual_sort' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_MANUAL_SORT',
            'dir' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_DIR',
            'limit' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LIMIT',
            'hide' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_HIDE',
            'export' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_EXPORT',
            'background' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_BACKGROUND',
            'card' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_CARD',
            'w' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_W',
            'w_requires_card' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_W_CARD',
            'width' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_WIDTH',
            'height' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_HEIGHT',
            'config' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_CONFIG',
            'add' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_ADD',
            'titles' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_TITLES',
            'labels' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS',
            'labels_table' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS_TABLE',
            'labels_total' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS_TOTAL',
            'labels_title' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS_TITLE',
            'labels_headers' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_LABELS_HEADERS',
            'groups' => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_GROUPS',
            default => 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPECTED_VALUES',
        };

        return Text::sprintf(
            'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_INVALID_OPTION_VALUE',
            $error['parameter'],
            $value,
            Text::_($detailKey)
        );
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
            StatsService::CBSTATS_ERROR_INVALID_GROUPS => Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_GROUPS',
                $exception->getMessage()
            ),
            StatsService::CBSTATS_ERROR_INVALID_TITLES => Text::_(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_TITLES'
            ),
            StatsService::CBSTATS_ERROR_INVALID_LABELS => Text::_(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_LABELS'
            ),
            default => Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_ADD'),
        };
    }

    private function getHideOptionsErrorMessage(\InvalidArgumentException $exception): string
    {
        if ($exception->getCode() === StatsHideOptionsService::NOT_APPLICABLE) {
            [$item, $output] = array_pad(explode('|', $exception->getMessage(), 2), 2, '');

            return Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_HIDE_NOT_APPLICABLE',
                $item,
                $output
            );
        }

        return match ($exception->getCode()) {
            StatsHideOptionsService::INVALID_SEPARATOR => Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_HIDE_INVALID_SEPARATOR',
                $exception->getMessage()
            ),
            StatsHideOptionsService::ALL_HIDDEN => Text::_(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_HIDE_ALL_HIDDEN'
            ),
            StatsHideOptionsService::LEGACY_TOTAL => Text::_(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_HIDE_LEGACY_TOTAL'
            ),
            default => Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_HIDE_INVALID_ITEM',
                $exception->getMessage()
            ),
        };
    }

    private function getViewName(array $payload): string
    {
        return (string) StatsService::resolveCbstatsOutput($payload, 'view_name');
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

    private function extractId(string $rawAttributes): int
    {
        $rawAttributes = TagSyntaxService::normalizeMarkup($rawAttributes);

        if (preg_match('/\bid\s*[:=]\s*["\']?([0-9]+)/iu', $rawAttributes, $match)) {
            return (int) $match[1];
        }

        return 0;
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

        if ($key === 'avg') {
            $formatter = new \NumberFormatter(
                Factory::getApplication()->getLanguage()->getTag(),
                \NumberFormatter::DECIMAL
            );
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
            $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);
            $formatted = $formatter->format((float) $value, \NumberFormatter::TYPE_DOUBLE);

            return $formatted === false ? $this->formatNumber(round((float) $value, 2)) : $formatted;
        }

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
        string $titles,
        array $titleSetMappings = [],
        array $groups = []
    ): array {
        $field = (array) ($payload['field'] ?? []);
        $additions = $field === [] ? [] : StatsService::parseFieldStatsAdditions($add);
        $inlineTitleMappings = $field === [] ? [] : StatsService::parseFieldStatsTitles($titles);
        $titleMappings = CbStatsTitleSetService::merge($titleSetMappings, $inlineTitleMappings);
        $values = (array) ($field['values'] ?? []);

        if ($groups !== []) {
            $values = StatsService::applyFieldStatsGroups($values, $groups);
        }

        return StatsService::normalizeFieldStats(
            $values,
            $sort,
            $dir,
            $locale,
            $additions,
            $titleMappings
        );
    }

    /** @return array<string, string> */
    private function resolveTitleSet(string $filename): array
    {
        if ($filename === '') {
            return [];
        }

        $this->titleSetService ??= new CbStatsTitleSetService(JPATH_SITE);
        $result = $this->titleSetService->resolve($filename);
        if (
            $result['status'] !== 'ok'
            && !isset($this->warnedTitleSets[$filename])
            && (bool) Factory::getApplication()->get('debug', false)
        ) {
            $key = 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TITLESET_WARNING_'
                . strtoupper($result['status']);
            Log::add(Text::sprintf($key, $filename), Log::WARNING, 'com_contentbuilderng.cbstats');
            $this->warnedTitleSets[$filename] = true;
        }

        return $result['titles'];
    }

    /** @return array<string, string> */
    private function resolveGroupSet(string $filename): array
    {
        if ($filename === '') {
            return [];
        }

        $this->titleSetService ??= new CbStatsTitleSetService(JPATH_SITE);
        $result = $this->titleSetService->resolve($filename);
        if (
            $result['status'] !== 'ok'
            && !isset($this->warnedTitleSets[$filename])
            && (bool) Factory::getApplication()->get('debug', false)
        ) {
            $key = 'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TITLESET_WARNING_'
                . strtoupper($result['status']);
            Log::add(Text::sprintf($key, $filename), Log::WARNING, 'com_contentbuilderng.cbstats');
            $this->warnedTitleSets[$filename] = true;
        }

        return $result['status'] === 'ok' ? $result['groups'] : [];
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
    private function renderTable(
        array $payload,
        array $fieldStats,
        int|float $total,
        string $totalLabel,
        string $background,
        array $labelMappings,
        string $labelsRaw,
        bool $exportManual = false,
        array $hideOptions = ['title' => false, 'total' => false, 'values' => false, 'graph' => false]
    ): string {
        $field = (array) ($payload['field'] ?? []);

        if ($fieldStats === []) {
            return '<span class="cbstats cbstats-empty">0</span>';
        }

        $this->loadDataTableAssets();

        $resolvedHeaders = TableHeaderService::resolve(
            $field,
            $labelMappings,
            Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'),
            Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_COUNT')
        );
        $html = '<div class="cbstats-table-wrapper cbstats-card"' . $this->renderBackgroundStyle($background) . '><table class="table table-sm cbstats-table">'
            . '<thead><tr>'
            . '<th scope="col" class="cbstats-table-label">'
            . htmlspecialchars($resolvedHeaders['category'], ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th scope="col" class="cbstats-table-number">'
            . htmlspecialchars($resolvedHeaders['value'], ENT_QUOTES, 'UTF-8') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($fieldStats as $item) {
            $html .= '<tr>'
                . '<td class="cbstats-table-label">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="cbstats-table-number">' . $this->formatNumber($item['value']) . '</td>'
                . '</tr>';
        }

        $html .= '</tbody>';

        if (!$hideOptions['total']) {
            $html .= '<tfoot><tr class="cbstats-total-row"><th scope="row" class="cbstats-table-label cbstats-total-label">'
                . $this->renderTotalLabel($totalLabel) . '</th><td class="cbstats-table-number cbstats-total-value"><strong>'
                . $this->formatNumber($total) . '</strong></td></tr></tfoot>';
        }

        $html .= '</table></div>';

        return $html . ($exportManual ? $this->renderManualExport(
            $fieldStats,
            'table',
            $labelsRaw,
            $background,
            $hideOptions
        ) : '');
    }

    /**
     * @param list<array{label: string, value: int|float}> $fieldStats
     */
    private function renderPie(
        array $payload,
        array $fieldStats,
        int|float $total,
        string $totalLabel,
        string $background,
        string $labelsRaw,
        bool $exportManual = false,
        array $hideOptions = ['title' => false, 'total' => false, 'values' => false, 'graph' => false]
    ): string {
        if ($fieldStats === []) {
            return '<span class="cbstats-pie-empty">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_NO_DATA'), ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        $field = (array) ($payload['field'] ?? []);
        $fieldLabel = (string) ($field['label'] ?? $field['requested'] ?? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'));
        $locale = Factory::getApplication()->getLanguage()->getTag();
        $presentation = PiePresentationService::prepare($fieldStats, $locale, $total);
        $items = $presentation['items'];
        $html = '<section class="cbstats-pie cbstats-card"';

        if (!$hideOptions['graph']) {
            $this->loadPieAssets();
            $instanceId = 'cbstats-pie-' . ++self::$pieInstance;
            $json = json_encode(
                ['items' => $items],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            $encodedPayload = htmlspecialchars($json === false ? '{"items":[]}' : $json, ENT_QUOTES, 'UTF-8');
            $ariaLabel = Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_PIE_ARIA_LABEL', $fieldLabel);
            $html .= ' data-cbstats-pie="' . $encodedPayload . '"'
                . $this->renderBackgroundStyle($background) . '>'
                . '<div class="cbstats-pie-chart"' . $this->renderDimensionAttributes() . '>'
                . '<canvas id="' . $instanceId . '" class="cbstats-pie-canvas" role="img" aria-label="'
                . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_CHART_UNAVAILABLE'), ENT_QUOTES, 'UTF-8')
                . '</canvas></div>';
        } else {
            $html .= $this->renderBackgroundStyle($background) . '>';
        }

        return $html . $this->renderChartDetails(
            $items,
            $total,
            $totalLabel,
            'pie',
            $background,
            $labelsRaw,
            $exportManual,
            $hideOptions
        );
    }

    /**
     * @param list<array{label: string, value: int|float}> $fieldStats
     */
    private function renderBar(
        array $payload,
        array $fieldStats,
        int|float $total,
        string $totalLabel,
        string $background,
        string $labelsRaw,
        bool $exportManual = false,
        array $hideOptions = ['title' => false, 'total' => false, 'values' => false, 'graph' => false]
    ): string {
        if ($fieldStats === []) {
            return '<span class="cbstats-bar-empty">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_NO_DATA'), ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        $field = (array) ($payload['field'] ?? []);
        $fieldLabel = (string) ($field['label'] ?? $field['requested'] ?? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'));
        $locale = Factory::getApplication()->getLanguage()->getTag();
        $presentation = PiePresentationService::prepare($fieldStats, $locale, $total);
        $items = $presentation['items'];
        $html = '<section class="cbstats-bar cbstats-card"';

        if (!$hideOptions['graph']) {
            $this->loadBarAssets();
            $instanceId = 'cbstats-bar-' . ++self::$barInstance;
            $json = json_encode(
                ['items' => $items],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            $encodedPayload = htmlspecialchars($json === false ? '{"items":[]}' : $json, ENT_QUOTES, 'UTF-8');
            $ariaLabel = Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_BAR_ARIA_LABEL', $fieldLabel);
            $html .= ' data-cbstats-bar="' . $encodedPayload . '"'
                . $this->renderBackgroundStyle($background) . '>'
                . '<div class="cbstats-bar-chart"' . $this->renderDimensionAttributes([
                    '--cbstats-bar-items' => (string) count($items),
                ]) . '>'
                . '<canvas id="' . $instanceId . '" class="cbstats-bar-canvas" role="img" aria-label="'
                . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_CHART_UNAVAILABLE'), ENT_QUOTES, 'UTF-8')
                . '</canvas></div>';
        } else {
            $html .= $this->renderBackgroundStyle($background) . '>';
        }

        return $html . $this->renderChartDetails(
            $items,
            $total,
            $totalLabel,
            'bar',
            $background,
            $labelsRaw,
            $exportManual,
            $hideOptions
        );
    }

    /**
     * @param list<array{label: string, value: int|float}> $fieldStats
     */
    private function renderGenericChart(
        array $payload,
        array $fieldStats,
        int|float $total,
        string $output,
        string $totalLabel,
        string $background,
        string $labelsRaw,
        bool $exportManual = false,
        array $hideOptions = ['title' => false, 'total' => false, 'values' => false, 'graph' => false]
    ): string {
        if ($fieldStats === []) {
            return '<span class="cbstats-chart-empty">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_NO_DATA'), ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        if ($output === 'radar' && count($fieldStats) < self::RADAR_MIN_AXES) {
            return '<span class="cbstats-chart-message">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_RADAR_TOO_FEW'), ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        if ($output === 'radar' && count($fieldStats) > self::RADAR_MAX_AXES) {
            return '<span class="cbstats-chart-message">'
                . htmlspecialchars(
                    Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_RADAR_TOO_MANY', self::RADAR_MAX_AXES),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</span>';
        }

        $field = (array) ($payload['field'] ?? []);
        $fieldLabel = (string) ($field['label'] ?? $field['requested'] ?? Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE'));
        $locale = Factory::getApplication()->getLanguage()->getTag();
        $items = PiePresentationService::prepare($fieldStats, $locale, $total)['items'];
        $html = '<section class="cbstats-chart cbstats-chart-' . $output . ' cbstats-card"';

        if (!$hideOptions['graph']) {
            $this->loadChartAssets();
            $instanceId = 'cbstats-chart-' . ++self::$chartInstance;
            $json = json_encode(
                ['type' => $output, 'items' => $items],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            $encodedPayload = htmlspecialchars($json === false ? '{"type":"","items":[]}' : $json, ENT_QUOTES, 'UTF-8');
            $ariaLabel = Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_CHART_ARIA_LABEL',
                $output,
                $fieldLabel
            );
            $html .= ' data-cbstats-chart="' . $encodedPayload . '"'
                . $this->renderBackgroundStyle($background) . '>'
                . '<div class="cbstats-chart-viewport"><div class="cbstats-chart-canvas-wrapper"'
                . $this->renderDimensionAttributes([
                    '--cbstats-chart-items' => (string) count($items),
                ]) . '>'
                . '<canvas id="' . $instanceId . '" class="cbstats-chart-canvas" role="img" aria-label="'
                . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_CHART_UNAVAILABLE'), ENT_QUOTES, 'UTF-8')
                . '</canvas></div></div>';
        } else {
            $html .= $this->renderBackgroundStyle($background) . '>';
        }

        return $html . $this->renderChartDetails(
            $items,
            $total,
            $totalLabel,
            $output,
            $background,
            $labelsRaw,
            $exportManual,
            $hideOptions
        );
    }

    /**
     * @param list<array{label: string, value: int|float, percentage: float, percentageLabel: string, color: string}> $items
     */
    private function renderChartDetails(
        array $items,
        int|float $total,
        string $totalLabel,
        string $output,
        string $background,
        string $labelsRaw,
        bool $exportManual,
        array $hideOptions
    ): string {
        $html = '';

        if (!$hideOptions['values']) {
            $html .= '<div class="cbstats-pie-legend" role="list">';

            foreach ($items as $item) {
                $html .= '<div class="cbstats-pie-legend-row" role="listitem">'
                    . '<span class="cbstats-pie-swatch" style="--cbstats-color:'
                    . htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span>'
                    . '<span class="cbstats-pie-label">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="cbstats-pie-value"><span aria-hidden="true">&mdash; </span>'
                    . $this->formatNumber($item['value']) . ' ('
                    . htmlspecialchars($item['percentageLabel'], ENT_QUOTES, 'UTF-8') . '&nbsp;%)</span>'
                    . '</div>';
            }

            $html .= '</div>';
        }

        if (!$hideOptions['total']) {
            $html .= '<div class="cbstats-total-box"><span class="cbstats-total-label">'
                . $this->renderTotalLabel($totalLabel) . '</span> <strong class="cbstats-total-value">'
                . $this->formatNumber($total) . '</strong></div>';
        }

        if ($exportManual) {
            $finalDisplayedItems = array_map(
                static fn(array $item): array => ['label' => $item['label'], 'value' => $item['value']],
                $items
            );
            $html .= $this->renderManualExport(
                $finalDisplayedItems,
                $output,
                $labelsRaw,
                $background,
                $hideOptions
            );
        }

        return $html . '</section>';
    }

    private function formatNumber(int|float $value): string
    {
        return floor((float) $value) === (float) $value
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }

    private function formatPercentage(float $value): string
    {
        return PiePresentationService::formatPercentageOutput(
            $value,
            Factory::getApplication()->getLanguage()->getTag()
        );
    }

    private function renderManualStats(
        string $values,
        string $output,
        string $sort,
        string $dir,
        string $add,
        string $titles,
        array $titleSetMappings,
        string $labelsRaw,
        array $labelMappings,
        string $totalLabel,
        string $background,
        bool $exportManual,
        ?int $limit,
        array $hideOptions
    ): string {
        if (!in_array($output, ['total', 'table', 'pie', 'bar', 'histogram', 'line', 'radar'], true)) {
            throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_MANUAL_OUTPUT'), 400);
        }

        StatsHideOptionsService::validateForOutput($hideOptions, $output);

        $sort = $sort === 'label' ? 'title' : $sort;

        if (!in_array($sort, ['none', 'title', 'value'], true)) {
            throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_SORT'), 400);
        }

        if (!in_array($dir, ['asc', 'desc'], true)) {
            throw new \RuntimeException(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_DEBUG_INVALID_DIR'), 400);
        }

        $manualFieldKey = Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_VALUE');
        $payload = [
            'records' => ['total' => 0],
            'field' => [
                'requested' => $manualFieldKey,
                'label' => $manualFieldKey,
                'values' => ManualValuesParser::parse($values),
            ],
        ];
        $locale = Factory::getApplication()->getLanguage()->getTag();
        $fullFieldStats = $this->getFieldStats(
            $payload,
            $sort,
            $dir,
            $locale,
            $add,
            $titles,
            $titleSetMappings
        );
        $fieldStats = DisplayOptionsService::applyLimit($fullFieldStats, $limit);
        $total = array_sum(array_column($fieldStats, 'value'));
        $payload['records']['total'] = $total;

        return match ($output) {
            'table' => $this->renderTable(
                $payload,
                $fieldStats,
                $total,
                $totalLabel,
                $background,
                $labelMappings,
                $labelsRaw,
                $exportManual,
                $hideOptions
            ),
            'pie' => $this->renderPie($payload, $fieldStats, $total, $totalLabel, $background, $labelsRaw, $exportManual, $hideOptions),
            'bar' => $this->renderBar($payload, $fieldStats, $total, $totalLabel, $background, $labelsRaw, $exportManual, $hideOptions),
            'histogram', 'line', 'radar' => $this->renderGenericChart(
                $payload,
                $fieldStats,
                $total,
                $output,
                $totalLabel,
                $background,
                $labelsRaw,
                $exportManual,
                $hideOptions
            ),
            'total' => $this->formatNumber($total),
        };
    }

    private function renderTotalLabel(string $totalLabel): string
    {
        return htmlspecialchars(TotalPresentationService::formatLabel(
            $totalLabel,
            Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TOTAL'),
            Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TOTAL_SEPARATOR')
        ), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param list<array{label: string, value: int|float}> $fieldStats
     */
    private function renderManualExport(
        array $fieldStats,
        string $output,
        string $labelsRaw,
        string $background,
        array $hideOptions = ['title' => false, 'total' => false, 'values' => false, 'graph' => false]
    ): string {
        $this->loadManualExportAssets();
        $total = array_sum(array_column($fieldStats, 'value'));
        $labels = implode(' ; ', array_column($fieldStats, 'label'));
        $values = implode(' ; ', array_map(
            fn(array $item): string => $this->formatNumber($item['value']),
            $fieldStats
        ));
        $labelMappings = StatsService::parseFieldStatsLabels($labelsRaw);
        $displayTitle = TotalPresentationService::formatLabel(
            (string) ($labelMappings['total'] ?? ''),
            Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TOTAL'),
            Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_TOTAL_SEPARATOR')
        );
        $syntax = ManualExportService::buildSyntax(
            $fieldStats,
            $output,
            $labelsRaw,
            $background,
            StatsHideOptionsService::serialize($hideOptions)
        );
        $escapedSyntax = htmlspecialchars($syntax, ENT_QUOTES, 'UTF-8');

        return '<aside class="cbstats-manual-export">'
            . '<h3 class="cbstats-manual-export-title">'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_TITLE'), ENT_QUOTES, 'UTF-8')
            . '</h3><dl class="cbstats-manual-export-summary">'
            . $this->renderManualExportSummary('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_SUMMARY_TITLE', $displayTitle)
            . $this->renderManualExportSummary('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_SUMMARY_TITLES', $labels)
            . ($hideOptions['values'] ? '' : $this->renderManualExportSummary(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_SUMMARY_VALUES',
                $values
            ))
            . ($hideOptions['total'] ? '' : $this->renderManualExportSummary(
                'PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_SUMMARY_TOTAL',
                $this->formatNumber($total)
            ))
            . '</dl><label class="cbstats-manual-export-label">'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_SYNTAX_LABEL'), ENT_QUOTES, 'UTF-8')
            . '<textarea readonly rows="3" class="cbstats-manual-export-syntax">' . $escapedSyntax . '</textarea></label>'
            . '<div class="cbstats-manual-export-actions"><button type="button" class="cbstats-manual-copy">'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_COPY'), ENT_QUOTES, 'UTF-8')
            . '</button></div><p class="cbstats-manual-copy-status" aria-live="polite" data-success="'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_COPIED'), ENT_QUOTES, 'UTF-8')
            . '" data-failure="'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_EXPORT_COPY_FAILED'), ENT_QUOTES, 'UTF-8')
            . '"></p></aside>';
    }

    private function renderManualExportSummary(string $languageKey, string $value): string
    {
        return '<div><dt>' . htmlspecialchars(Text::_($languageKey), ENT_QUOTES, 'UTF-8') . '</dt><dd>'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</dd></div>';
    }

    private function renderBackgroundStyle(string $background): string
    {
        return $background === '' ? '' : ' style="--cbstats-background:' . $background . '"';
    }

    /** @param array<string, string> $properties */
    private function renderDimensionAttributes(array $properties = []): string
    {
        $attributes = '';
        if ($this->currentDimensions['width'] !== '') {
            $attributes .= ' data-cbstats-custom-width="1"';
            $properties['--cbstats-chart-width'] = $this->currentDimensions['width'];
        }
        if ($this->currentDimensions['height'] !== '') {
            $attributes .= ' data-cbstats-custom-height="1"';
            $properties['--cbstats-chart-height'] = $this->currentDimensions['height'];
        }

        if ($properties === []) {
            return $attributes;
        }

        $declarations = [];
        foreach ($properties as $name => $value) {
            $declarations[] = $name . ':' . $value;
        }

        return $attributes . ' style="' . htmlspecialchars(implode(';', $declarations), ENT_QUOTES, 'UTF-8') . '"';
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

    private function loadChartAssets(): void
    {
        if (self::$chartAssetsLoaded) {
            return;
        }

        $this->getCbstatsWebAssetManager()->usePreset('plg_content_contentbuilderng_cbstats.charts');
        self::$chartAssetsLoaded = true;
    }

    private function loadDataTableAssets(): void
    {
        $this->getCbstatsWebAssetManager()->useStyle('plg_content_contentbuilderng_cbstats.data');
    }

    private function loadManualExportAssets(): void
    {
        if (self::$manualExportAssetsLoaded) {
            return;
        }

        $wa = $this->getCbstatsWebAssetManager();
        $wa->useStyle('plg_content_contentbuilderng_cbstats.manual-export');
        $wa->useScript('plg_content_contentbuilderng_cbstats.manual-export');
        self::$manualExportAssetsLoaded = true;
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
