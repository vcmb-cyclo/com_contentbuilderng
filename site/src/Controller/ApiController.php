<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Controller;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Service\ApiPermissionRequirementService;
use CB\Component\Contentbuilderng\Administrator\Service\ApiFieldPermissionService;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Site\Helper\DuplicateKeyViolationHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;
use CB\Component\Contentbuilderng\Site\Model\DetailsModel;
use CB\Component\Contentbuilderng\Site\Model\EditModel;
use CB\Component\Contentbuilderng\Site\Model\ListModel;
use CB\Component\Contentbuilderng\Site\Service\SparseFieldsetService;
use CB\Component\Contentbuilderng\Site\Service\StatsFilterValueService;
use CB\Component\Contentbuilderng\Site\Service\StatsHideOptionsService;
use CB\Component\Contentbuilderng\Site\Service\StatsService;
use CB\Component\Contentbuilderng\Site\Service\CbStatsTitleSetService;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Input\Input;
use Joomla\CMS\Session\Session;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;

class ApiController extends BaseController
{
    private const API_DEFAULT_PAGE_SIZE = 20;
    private const API_MAX_PAGE_SIZE = 100;

    private SiteApplication $siteApp;
    private bool $frontend;

    private function getDatabase(): DatabaseInterface
    {
        return $this->getComponent()->getContainer()->get(DatabaseInterface::class);
    }

    private function getStatsService(): StatsService
    {
        return new StatsService($this->getDatabase());
    }

    private function getApiFieldPermissionService(): ApiFieldPermissionService
    {
        return new ApiFieldPermissionService($this->getDatabase());
    }

    private function getComponent(): ContentbuilderngComponent
    {
        $component = $this->siteApp->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    public function __construct(
        array $config = [],
        ?MVCFactoryInterface $factory = null,
        ?CMSWebApplicationInterface $app = null,
        ?Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        if (!$app instanceof SiteApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        $this->siteApp = $app;
        $this->frontend = $this->siteApp->isClient('site');
    }

    #[\Override]
    public function display($cachable = false, $urlparams = []): void
    {
        $formId = 0;

        try {
            $formId = (int) $this->input->getInt('id', 0);
            $recordId = (int) $this->input->getInt('record_id', 0);
            $action = trim((string) $this->input->getCmd('action', ''));
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

            Logger::debug('API request', [
                'method' => $method,
                'action' => $action,
                'form_id' => $formId,
                'record_id' => $recordId,
            ]);

            if ($formId < 1) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
            }

            $isAdminPreview = $this->isValidAdminPreviewRequest($formId);
            $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
            $this->siteApp->getInput()->set('cb_preview_ok', $isAdminPreview ? 1 : 0);

            $resolvedRecordId = $recordId > 0
                ? $this->normalizeRequestedRecordId($formId, $recordId)
                : 0;
            if ($recordId > 0 && $resolvedRecordId !== $recordId) {
                Logger::info('API record id remapped', [
                    'form_id' => $formId,
                    'requested_record_id' => $recordId,
                    'resolved_record_id' => $resolvedRecordId,
                ]);
            }
            $recordId = $resolvedRecordId;

            (PermissionService::createFromRuntimeContext())->setPermissions($formId, $recordId, $this->frontend ? '_fe' : '');
            $this->assertApiPermissions((new ApiPermissionRequirementService())->getRequiredPermissions($method, $action, $recordId));

            if ($action !== '') {
                if ($action === 'cbstats') {
                    $output = $this->getCbstatsOutput();
                    $payload = $this->getCbstatsPayload($formId, $output);

                    if (in_array($output, ['json', 'table', 'pie', 'bar', 'histogram', 'line', 'radar'], true)) {
                        $this->sendRawJson((array) $payload);
                    } else {
                        $this->sendJson($payload);
                    }
                    return;
                }

                $payload = $this->handleAction($action, $formId, $recordId);
                $payload = $this->applySparseFieldsets($payload, $method);
                $this->sendJson($payload);
                return;
            }

            if ($method === 'GET') {
                $payload = $recordId > 0
                    ? $this->getDetailPayload($formId, $recordId)
                    : $this->getListPayload($formId);

                $payload = $this->applySparseFieldsets($payload, $method);
                $this->sendJson($payload);
                return;
            }

            if (in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
                if ($recordId < 1) {
                    throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_RECORD_ID_REQUIRED'), 400);
                }

                (PermissionService::createFromRuntimeContext())->setPermissions($formId, $recordId, $this->frontend ? '_fe' : '');
                if (!$this->can('edit')) {
                    throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_EDIT_NOT_ALLOWED'), 403);
                }

                $this->assertStateChangingRequestToken();
                $updatedRecordId = $this->updateRecord($formId, $recordId);
                $this->sendJson([
                    'message' => Text::_('COM_CONTENTBUILDERNG_SAVED'),
                    'record_id' => $updatedRecordId,
                    'detail' => $this->getDetailPayload($formId, $updatedRecordId),
                ]);
                return;
            }

            throw new \RuntimeException('Unsupported HTTP method', 405);
        } catch (\Throwable $e) {
            $this->sendJsonError($e, $formId);
        }
    }

    private function handleAction(string $action, int $formId, int $recordId): array
    {
        return match ($action) {
            'get-unique-values' => $this->getUniqueValuesPayload($formId),
            'rating' => $this->ratePayload($formId, $recordId),
            'stats' => $this->getStatsPayload($formId),
            default => throw new \RuntimeException(Text::_('JGLOBAL_RESOURCE_NOT_FOUND'), 404),
        };
    }

    private function getStatsPayload(int $formId): array
    {
        $filter = (array) $this->input->get('filter', [], 'array');

        return $this->getStatsService()->getStatsPayload($formId, [
            'field' => trim((string) $this->input->getString('field', '')),
            'filter' => [
                'field' => trim((string) ($filter['field'] ?? '')),
                'value' => trim((string) ($filter['value'] ?? '')),
            ],
        ]);
    }

    private function getCbstatsOutput(): string
    {
        $output = strtolower(trim((string) $this->input->getCmd('output', 'json')));
        $supportedOutputs = [
            'json', 'table', 'pie', 'bar', 'histogram', 'line', 'radar',
            'total', 'remaining', 'percentage', 'progress', 'distinct',
            'sum', 'min', 'max', 'avg', 'view_name',
        ];

        if (!in_array($output, $supportedOutputs, true)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_OUTPUT'), 400);
        }

        return $output;
    }

    private function getCbstatsPayload(int $formId, string $output): array|int|float|string
    {
        $listOutputs = ['json', 'table', 'pie', 'bar', 'histogram', 'line', 'radar'];
        $fieldOutputs = [...$listOutputs, 'percentage', 'distinct', 'sum', 'min', 'max', 'avg'];
        $field = trim((string) $this->input->getString('field', ''));

        if (in_array($output, $fieldOutputs, true) && $field === '') {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_FIELD_REQUIRED'), 400);
        }

        $target = trim((string) $this->input->getString('target', ''));
        if (in_array($output, ['remaining', 'progress'], true)) {
            if (preg_match('/^(?:[1-9][0-9]*(?:\.[0-9]+)?|0\.[0-9]*[1-9][0-9]*)$/D', $target) !== 1) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_TARGET_REQUIRED'), 400);
            }
        } elseif ($target !== '') {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_TARGET_NOT_APPLICABLE'), 400);
        }

        $selection = trim((string) $this->input->getString('value', ''));
        $selectedValues = (new StatsFilterValueService())->parseAlternatives($selection);
        if ($output === 'percentage' && $selectedValues === []) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_PERCENTAGE_VALUE_REQUIRED'), 400);
        }
        $filter = (array) $this->input->get('filter', [], 'array');
        $rawFilterField = $filter['field'] ?? '';
        $rawFilterValue = $filter['value'] ?? '';

        if (!is_scalar($rawFilterField) || !is_scalar($rawFilterValue)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_FILTER'), 400);
        }

        $filterField = trim((string) $rawFilterField);
        $filterValue = trim((string) $rawFilterValue);

        if (($filterField === '') !== ($filterValue === '')) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_FILTER'), 400);
        }

        $filterValues = (new StatsFilterValueService())->parseAlternatives($filterValue);

        if ($filterValue !== '' && $filterValues === []) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_FILTER'), 400);
        }

        $payload = $this->getStatsService()->getStatsPayload($formId, [
            'field' => $field,
            'filter' => [
                'field' => $filterField,
                'value' => $filterValue,
                'values' => $filterValues,
            ],
        ]);

        try {
            if ($this->input->exists('total')) {
                throw new \InvalidArgumentException('total', StatsHideOptionsService::LEGACY_TOTAL);
            }

            $hideOptions = StatsHideOptionsService::parse(
                $this->input->exists('hide') ? $this->input->getString('hide', '') : null
            );
            StatsHideOptionsService::validateForOutput($hideOptions, $output);
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException($this->getCbstatsHideErrorMessage($exception), 400, $exception);
        }

        if (in_array($output, $listOutputs, true)) {
            $sort = strtolower(trim((string) $this->input->getCmd('sort', 'none')));
            $dir = strtolower(trim((string) $this->input->getCmd('dir', 'asc')));
            $add = trim((string) $this->input->getString('add', ''));
            $titles = trim((string) $this->input->getString('titles', ''));
            $titleSet = trim((string) $this->input->getString('titleset', ''));
            $groups = trim((string) $this->input->getString('groups', ''));
            $groupSet = trim((string) $this->input->getString('groupset', ''));
            if (!in_array($sort, ['none', 'title', 'value'], true)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_SORT'), 400);
            }

            if (!in_array($dir, ['asc', 'desc'], true)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_DIR'), 400);
            }

            try {
                $groupSetMappings = [];
                if ($groups === '' && $groupSet !== '') {
                    $groupSetResult = (new CbStatsTitleSetService(JPATH_SITE))->resolve($groupSet);
                    if ($groupSetResult['status'] === 'ok') {
                        $groupSetMappings = $groupSetResult['groups'];
                    } elseif ((bool) $this->siteApp->get('debug', false)) {
                        Logger::warning(Text::sprintf(
                            'COM_CONTENTBUILDERNG_API_CBSTATS_TITLESET_WARNING',
                            $groupSet,
                            $groupSetResult['status']
                        ));
                    }
                }
                $groupDefinitions = $groups !== ''
                    ? StatsService::parseFieldStatsGroups($groups)
                    : StatsService::parseFieldStatsGroupSelectors(implode(';', array_keys($groupSetMappings)));
                $inlineGroupMappings = [];
                foreach ($groupDefinitions as $groupDefinition) {
                    if ($groupDefinition['title'] !== null) {
                        $inlineGroupMappings[$groupDefinition['label']] = $groupDefinition['title'];
                    }
                }
                $values = (array) ($payload['field']['values'] ?? []);

                if ($groupDefinitions !== []) {
                    $values = StatsService::applyFieldStatsGroups($values, $groupDefinitions);
                }

                $titleSetMappings = [];
                if ($titleSet !== '') {
                    $titleSetResult = (new CbStatsTitleSetService(JPATH_SITE))->resolve($titleSet);
                    $titleSetMappings = $titleSetResult['titles'];
                    if ($titleSetResult['status'] !== 'ok' && (bool) $this->siteApp->get('debug', false)) {
                        Logger::warning(Text::sprintf(
                            'COM_CONTENTBUILDERNG_API_CBSTATS_TITLESET_WARNING',
                            $titleSet,
                            $titleSetResult['status']
                        ));
                    }
                }

                $items = StatsService::normalizeFieldStats(
                    $values,
                    $groupDefinitions === [] ? $sort : 'none',
                    $dir,
                    $this->siteApp->getLanguage()->getTag(),
                    StatsService::parseFieldStatsAdditions($add),
                    CbStatsTitleSetService::merge(
                        array_replace($groupSetMappings, $inlineGroupMappings, $titleSetMappings),
                        StatsService::parseFieldStatsTitles($titles)
                    )
                );
                $items = array_slice($items, 0, $this->getCbstatsLimit(count($items)));

                if ($output === 'json') {
                    return $items;
                }

                return [
                    'total' => $groupDefinitions === []
                        ? array_sum(array_column($items, 'value'))
                        : (int) ($payload['records']['total'] ?? 0),
                    'items' => $items,
                ];
            } catch (\InvalidArgumentException $exception) {
                throw new \RuntimeException($this->getCbstatsFieldStatsErrorMessage($exception), 400, $exception);
            }
        }

        if ($output === 'remaining') {
            return StatsService::resolveRemainingOutput($payload, (float) $target);
        }
        if ($output === 'progress') {
            return round(StatsService::resolveProgressOutput($payload, (float) $target), 1);
        }
        if ($output === 'percentage') {
            return round(StatsService::resolvePercentageOutput(
                (array) ($payload['field']['values'] ?? []),
                $selectedValues,
                (int) ($payload['records']['total'] ?? 0)
            ), 1);
        }

        return StatsService::resolveCbstatsOutput($payload, $output);
    }

    private function getCbstatsLimit(int $itemCount): int
    {
        $rawLimit = trim((string) $this->input->getString('limit', ''));

        if ($rawLimit === '') {
            return min(self::API_MAX_PAGE_SIZE, $itemCount);
        }

        if (preg_match('/^[1-9][0-9]*$/D', $rawLimit) !== 1) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_LIMIT'), 400);
        }

        return min((int) $rawLimit, self::API_MAX_PAGE_SIZE, $itemCount);
    }

    private function getCbstatsFieldStatsErrorMessage(\InvalidArgumentException $exception): string
    {
        return match ($exception->getCode()) {
            StatsService::CBSTATS_ERROR_INVALID_GROUPS => Text::sprintf(
                'COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_GROUPS',
                $exception->getMessage()
            ),
            StatsService::CBSTATS_ERROR_INVALID_TITLES => Text::_(
                'COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_TITLES'
            ),
            default => Text::_('COM_CONTENTBUILDERNG_API_CBSTATS_INVALID_ADD'),
        };
    }

    private function getCbstatsHideErrorMessage(\InvalidArgumentException $exception): string
    {
        if ($exception->getCode() === StatsHideOptionsService::NOT_APPLICABLE) {
            [$item, $output] = array_pad(explode('|', $exception->getMessage(), 2), 2, '');

            return Text::sprintf('COM_CONTENTBUILDERNG_API_CBSTATS_HIDE_NOT_APPLICABLE', $item, $output);
        }

        return match ($exception->getCode()) {
            StatsHideOptionsService::INVALID_SEPARATOR => Text::sprintf(
                'COM_CONTENTBUILDERNG_API_CBSTATS_HIDE_INVALID_SEPARATOR',
                $exception->getMessage()
            ),
            StatsHideOptionsService::ALL_HIDDEN => Text::_(
                'COM_CONTENTBUILDERNG_API_CBSTATS_HIDE_ALL_HIDDEN'
            ),
            StatsHideOptionsService::LEGACY_TOTAL => Text::_(
                'COM_CONTENTBUILDERNG_API_CBSTATS_HIDE_LEGACY_TOTAL'
            ),
            default => Text::sprintf(
                'COM_CONTENTBUILDERNG_API_CBSTATS_HIDE_INVALID_ITEM',
                $exception->getMessage()
            ),
        };
    }

    private function applySparseFieldsets(array $payload, string $method): array
    {
        if ($method !== 'GET') {
            return $payload;
        }

        $fieldsets = $this->input->get('fields', [], 'array');

        return (new SparseFieldsetService())->filter($payload, is_array($fieldsets) ? $fieldsets : []);
    }

    private function getUniqueValuesPayload(int $formId): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('type'), $db->quoteName('reference_id')])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . $formId);
        $db->setQuery($query);
        $result = $db->loadAssoc();

        $form = is_array($result)
            ? FormSourceFactory::getForm((string) $result['type'], (string) $result['reference_id'])
            : null;

        if (!$form || !$form->exists) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_ERROR'), 404);
        }

        $fieldReferenceId = $this->input->getCmd('field_reference_id', '');
        $whereField = $this->input->getCmd('where_field', '');
        $apiFields = $this->getApiFieldPermissionService();
        if (
            !$apiFields->isReferenceAllowed($formId, $fieldReferenceId)
            || ($whereField !== '' && !$apiFields->isReferenceAllowed($formId, $whereField))
        ) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELD_NOT_ALLOWED'), 403);
        }

        $values = $form->getUniqueValues(
            $fieldReferenceId,
            $whereField,
            $this->input->get('where', '', 'string')
        );
        if (is_array($values)) {
            $values = array_slice($values, 0, self::API_MAX_PAGE_SIZE);
        }

        return [
            'code' => 0,
            'field_reference_id' => $fieldReferenceId,
            'msg' => $values,
        ];
    }

    private function ratePayload(int $formId, int $recordId): array
    {
        if (!$this->can('rating')) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_RATING_NOT_ALLOWED'), 403);
        }

        if (strtoupper((string) $this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $this->assertStateChangingRequestToken();

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('type'), $db->quoteName('reference_id'), $db->quoteName('rating_slots')])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . $formId);
        $db->setQuery($query);
        $result = $db->loadAssoc();

        $form = is_array($result)
            ? FormSourceFactory::getForm((string) $result['type'], (string) $result['reference_id'])
            : null;

        if (!$form || !$form->exists) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_ERROR'), 404);
        }

        $ratingSlots = (int) ($result['rating_slots'] ?? 0);
        $rating = 0;

        switch ($ratingSlots) {
            case 1:
                $rating = 1;
                break;
            case 2:
                $rating = max(0, min(5, (int) $this->input->getInt('rate', 5)));
                if ($rating < 4) {
                    $rating = 0;
                }
                break;
            case 3:
                $rating = max(1, min(3, (int) $this->input->getInt('rate', 3)));
                break;
            case 4:
                $rating = max(1, min(4, (int) $this->input->getInt('rate', 4)));
                break;
            case 5:
                $rating = max(1, min(5, (int) $this->input->getInt('rate', 5)));
                break;
        }

        if ($ratingSlots !== 2 && !$rating) {
            return ['code' => 0, 'msg' => Text::_('COM_CONTENTBUILDERNG_THANK_YOU_FOR_RATING')];
        }

        $now = (new Date());
        $nowSql = $now->toSql();

        $recordIdValue = (string) $recordId;
        $formIdValue = (int) $formId;

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__contentbuilderng_rating_cache'))
            ->where('DATEDIFF(:now, ' . $db->quoteName('date') . ') >= 1')
            ->bind(':now', $nowSql);
        $db->setQuery($query);
        $db->execute();

        $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $query = $db->getQuery(true)
            ->select($db->quoteName('form_id'))
            ->from($db->quoteName('#__contentbuilderng_rating_cache'))
            ->where($db->quoteName('record_id') . ' = :recordId')
            ->where($db->quoteName('form_id') . ' = :formId')
            ->where($db->quoteName('ip') . ' = :ip')
            ->bind(':recordId', $recordIdValue)
            ->bind(':formId', $formIdValue, ParameterType::INTEGER)
            ->bind(':ip', $clientIp);
        $db->setQuery($query);
        $cached = $db->loadResult();
        $ratingSessionKey = 'com_contentbuilderng.rating.rated' . $formId . $recordId;
        $rated = $this->siteApp->getSession()->get($ratingSessionKey, false);

        if ($rated || $cached) {
            return ['code' => 1, 'msg' => Text::_('COM_CONTENTBUILDERNG_RATED_ALREADY')];
        }

        $typeValue = (string) $result['type'];
        $referenceIdValue = (string) $result['reference_id'];
        $ratingValue = (int) $rating;

        $db->transactionStart();
        try {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__contentbuilderng_rating_cache'))
                ->columns($db->quoteName(['record_id', 'form_id', 'ip', 'date']))
                ->values(':recordId, :formId, :ip, :now')
                ->bind(':recordId', $recordIdValue)
                ->bind(':formId', $formIdValue, ParameterType::INTEGER)
                ->bind(':ip', $clientIp)
                ->bind(':now', $nowSql);
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_records'))
                ->set($db->quoteName('rating_count') . ' = ' . $db->quoteName('rating_count') . ' + 1')
                ->set($db->quoteName('rating_sum') . ' = ' . $db->quoteName('rating_sum') . ' + :rating')
                ->set($db->quoteName('lastip') . ' = :ip')
                ->where($db->quoteName('type') . ' = :type')
                ->where($db->quoteName('reference_id') . ' = :referenceId')
                ->where($db->quoteName('record_id') . ' = :recordId')
                ->bind(':rating', $ratingValue, ParameterType::INTEGER)
                ->bind(':ip', $clientIp)
                ->bind(':type', $typeValue)
                ->bind(':referenceId', $referenceIdValue)
                ->bind(':recordId', $recordIdValue);
            $db->setQuery($query);
            $db->execute();

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            if (DuplicateKeyViolationHelper::isDuplicateKeyViolation($e)) {
                return ['code' => 1, 'msg' => Text::_('COM_CONTENTBUILDERNG_RATED_ALREADY')];
            }

            throw $e;
        }

        $this->siteApp->getSession()->set($ratingSessionKey, true);

        $query = $db->getQuery(true)
            ->select('a.' . $db->quoteName('article_id'))
            ->from($db->quoteName('#__contentbuilderng_articles', 'a'))
            ->join('INNER', $db->quoteName('#__content', 'c'), 'c.id = a.article_id')
            ->where('(c.state = 1 OR c.state = 0)')
            ->where('a.form_id = :formId')
            ->where('a.record_id = :recordId')
            ->bind(':formId', $formIdValue, ParameterType::INTEGER)
            ->bind(':recordId', $recordIdValue);
        $db->setQuery($query);
        $articleId = (int) $db->loadResult();

        if ($articleId > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('content_id'))
                ->from($db->quoteName('#__content_rating'))
                ->where($db->quoteName('content_id') . ' = :articleId')
                ->bind(':articleId', $articleId, ParameterType::INTEGER);
            $db->setQuery($query);
            $exists = $db->loadResult();

            if ($exists) {
                $query = $db->getQuery(true)
                    ->update(
                        $db->quoteName('#__content_rating', 'cr')
                        . ', ' . $db->quoteName('#__contentbuilderng_records', 'cbr')
                        . ', ' . $db->quoteName('#__contentbuilderng_articles', 'cba')
                    )
                    ->set('cr.rating_count = cbr.rating_count')
                    ->set('cr.rating_sum = cbr.rating_sum')
                    ->set('cr.lastip = cbr.lastip')
                    ->where('cbr.record_id = :recordId')
                    ->where('cbr.record_id = cba.record_id')
                    ->where('cbr.reference_id = :referenceId')
                    ->where('cbr.' . $db->quoteName('type') . ' = :type')
                    ->where('cba.form_id = :formId')
                    ->where('cr.content_id = cba.article_id')
                    ->bind(':recordId', $recordIdValue)
                    ->bind(':referenceId', $referenceIdValue)
                    ->bind(':type', $typeValue)
                    ->bind(':formId', $formIdValue, ParameterType::INTEGER);
                $db->setQuery($query);
                $db->execute();
            } else {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__content_rating'))
                    ->columns($db->quoteName(['content_id', 'rating_sum', 'rating_count', 'lastip']))
                    ->values(':articleId, :rating, 1, :ip')
                    ->bind(':articleId', $articleId, ParameterType::INTEGER)
                    ->bind(':rating', $ratingValue, ParameterType::INTEGER)
                    ->bind(':ip', $clientIp);
                $db->setQuery($query);
                $db->execute();
            }
        }

        return ['code' => 0, 'msg' => Text::_('COM_CONTENTBUILDERNG_THANK_YOU_FOR_RATING')];
    }

    private function getListModel(): ListModel
    {
        $model = $this->getModel('List', 'Site', ['ignore_request' => false]);
        if (!$model instanceof ListModel) {
            throw new \RuntimeException('ListModel not found');
        }
        return $model;
    }

    private function getDetailsModel(): DetailsModel
    {
        $model = $this->getModel('Details', 'Site', ['ignore_request' => false]);
        if (!$model instanceof DetailsModel) {
            throw new \RuntimeException('DetailsModel not found');
        }
        return $model;
    }

    private function getEditModel(): EditModel
    {
        $model = $this->getModel('Edit', 'Site', ['ignore_request' => true]);
        if (!$model instanceof EditModel) {
            throw new \RuntimeException('EditModel not found');
        }
        return $model;
    }

    private function getListPayload(int $formId): array
    {
        $list = (array) $this->input->get('list', [], 'array');
        $requestedLimit = isset($list['limit']) ? (int) $list['limit'] : self::API_DEFAULT_PAGE_SIZE;
        $list['limit'] = min(
            self::API_MAX_PAGE_SIZE,
            $requestedLimit > 0 ? $requestedLimit : self::API_DEFAULT_PAGE_SIZE
        );
        $list['start'] = max(0, isset($list['start']) ? (int) $list['start'] : 0);
        $this->input->set('list', $list);
        $this->input->get->set('list', $list);
        $this->siteApp->getInput()->set('list', $list);
        $this->siteApp->getInput()->get->set('list', $list);

        $this->input->set('id', $formId);
        $this->input->set('record_id', 0);
        $this->input->set('view', 'list');
        $this->siteApp->getInput()->set('id', $formId);
        $this->siteApp->getInput()->set('record_id', 0);
        $this->siteApp->getInput()->set('view', 'list');

        $model = $this->getListModel();
        $dataSet = $model->getData();
        $subject = is_object($dataSet)
            ? $dataSet
            : ((is_array($dataSet) && isset($dataSet[0]) && is_object($dataSet[0])) ? $dataSet[0] : null);
        if (!is_object($subject) || !isset($subject->items) || !is_array($subject->items)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
        }

        $elementNames = [];
        if (isset($subject->form) && is_object($subject->form) && method_exists($subject->form, 'getElementNames')) {
            $elementNames = (array) $subject->form->getElementNames();
        }
        $allowedReferences = $this->getApiFieldPermissionService()->getAllowedReferenceMap($formId);

        $items = [];
        foreach ($subject->items as $row) {
            if (!is_object($row)) {
                continue;
            }

            $entry = [
                'record_id' => (int) ($row->colRecord ?? 0),
                'values' => [],
            ];

            foreach (get_object_vars($row) as $prop => $value) {
                if (!str_starts_with((string) $prop, 'col') || $prop === 'colRecord') {
                    continue;
                }
                $referenceId = substr((string) $prop, 3);
                if (!isset($allowedReferences[$referenceId])) {
                    continue;
                }
                $key = (string) ($elementNames[$referenceId] ?? $referenceId);
                $entry['values'][$key] = $value;
            }

            $items[] = $entry;
        }

        $limit = (int) $model->getState('list.limit', 0);
        $start = (int) $model->getState('list.start', 0);
        $total = (int) $model->getTotal();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'start' => $start,
            ],
        ];
    }

    private function getDetailPayload(int $formId, int $recordId): array
    {
        $this->input->set('id', $formId);
        $this->input->set('record_id', $recordId);
        $this->input->set('view', 'details');
        $this->siteApp->getInput()->set('id', $formId);
        $this->siteApp->getInput()->set('record_id', $recordId);
        $this->siteApp->getInput()->set('view', 'details');

        $verbose = (bool) $this->input->getBool('verbose', false);

        $model = $this->getDetailsModel();
        $dataSet = $model->getData();
        $subject = is_object($dataSet)
            ? $dataSet
            : ((is_array($dataSet) && isset($dataSet[0]) && is_object($dataSet[0])) ? $dataSet[0] : null);
        if (!is_object($subject) || !isset($subject->items) || !is_array($subject->items)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
        }

        $allowedReferences = $this->getApiFieldPermissionService()->getAllowedReferenceMap($formId);
        $fields = [];
        foreach ($subject->items as $item) {
            if (!is_object($item)) {
                continue;
            }

            $referenceId = (string) ($item->recElementId ?? '');
            if ($referenceId === '' || !isset($allowedReferences[$referenceId])) {
                continue;
            }

            $name = (string) ($item->recName ?? '');
            if ($name === '') {
                continue;
            }

            $fields[$name] = [
                'reference_id' => $referenceId,
                'label' => (string) ($item->recLabel ?? $name),
                'value' => $item->recValue ?? null,
            ];
        }

        return [
            'record_id' => $recordId,
            'form_id' => $formId,
            'fields' => $this->normalizeDetailFields($fields, $verbose),
        ];
    }

    /**
     * Default API detail format is unitary key => value.
     * Add verbose=1 to keep label/reference metadata per field.
     */
    private function normalizeDetailFields(array $fields, bool $verbose): array
    {
        if ($verbose) {
            return $fields;
        }

        $normalized = [];
        foreach ($fields as $name => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $normalized[(string) $name] = $meta['value'] ?? null;
        }

        return $normalized;
    }

    private function updateRecord(int $formId, int $recordId): int
    {
        $fields = $this->extractRequestedFields();
        if ($fields === []) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELDS_REQUIRED'), 400);
        }

        $form = $this->loadFormObject($formId);
        $elementNames = method_exists($form, 'getElementNames') ? (array) $form->getElementNames() : [];
        $allowedReferences = $this->getApiFieldPermissionService()->getAllowedReferenceMap($formId);
        $nameToRef = [];
        foreach ($elementNames as $ref => $name) {
            $nameToRef[(string) $name] = (string) $ref;
        }

        $hasAllowedField = false;
        foreach ($fields as $fieldKey => $fieldValue) {
            $key = (string) $fieldKey;
            $referenceId = $nameToRef[$key] ?? null;
            if ($referenceId === null && ctype_digit($key)) {
                $referenceId = $key;
            }
            if ($referenceId === null) {
                continue;
            }
            if (!isset($allowedReferences[(string) $referenceId])) {
                continue;
            }
            $hasAllowedField = true;
            $this->input->post->set('cb_' . $referenceId, $fieldValue);
        }

        if (!$hasAllowedField) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELD_NOT_ALLOWED'), 403);
        }

        $this->input->set('id', $formId);
        $this->input->set('record_id', $recordId);
        $this->input->post->set('id', $formId);
        $this->input->post->set('record_id', $recordId);

        $model = $this->getEditModel();
        $savedRecordId = (int) $model->store();
        if ($savedRecordId < 1) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ERROR'), 500);
        }

        return $savedRecordId;
    }

    private function extractRequestedFields(): array
    {
        $raw = file_get_contents('php://input');
        $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (str_starts_with($contentType, 'application/json')) {
            if (!is_string($raw) || trim($raw) === '') {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELDS_REQUIRED'), 400);
            }

            try {
                $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_ERROR_INVALID_REQUEST'), 400, $exception);
            }

            return is_array($json) && isset($json['fields']) && is_array($json['fields'])
                ? $json['fields']
                : [];
        }

        $fields = $this->input->post->get('fields', [], 'array');
        return is_array($fields) ? $fields : [];
    }

    private function assertStateChangingRequestToken(): void
    {
        $headerToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $formToken = Session::getFormToken();

        if ($headerToken !== '' && hash_equals($formToken, $headerToken)) {
            $this->input->post->set($formToken, '1');
            $this->siteApp->getInput()->post->set($formToken, '1');
        }

        if (!Session::checkToken('post') && !Session::checkToken('get')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }
    }

    private function loadFormObject(int $formId)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('type'), $db->quoteName('reference_id')])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        $db->setQuery($query, 0, 1);
        $row = $db->loadAssoc();

        if (!is_array($row) || empty($row['type']) || empty($row['reference_id'])) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        $form = FormSourceFactory::getForm((string) $row['type'], (string) $row['reference_id']);
        if (!$form || !is_object($form)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        return $form;
    }

    private function can(string $action): bool
    {
        return $this->frontend
            ? (PermissionService::createFromRuntimeContext())->authorizeFe($action)
            : (PermissionService::createFromRuntimeContext())->authorize($action);
    }

    /**
     * @param list<string> $permissions
     */
    private function assertApiPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                continue;
            }

            throw new \RuntimeException(Text::_($this->getPermissionMessageKey($permission)), 403);
        }
    }

    private function getPermissionMessageKey(string $permission): string
    {
        return match ($permission) {
            'api' => 'COM_CONTENTBUILDERNG_PERMISSIONS_API_NOT_ALLOWED',
            'view' => 'COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED',
            'listaccess' => 'COM_CONTENTBUILDERNG_PERMISSIONS_LISTACCESS_NOT_ALLOWED',
            'edit' => 'COM_CONTENTBUILDERNG_PERMISSIONS_EDIT_NOT_ALLOWED',
            'rating' => 'COM_CONTENTBUILDERNG_RATING_NOT_ALLOWED',
            'stats' => 'COM_CONTENTBUILDERNG_PERMISSIONS_STATS_NOT_ALLOWED',
            default => 'COM_CONTENTBUILDERNG_PERMISSIONS_API_NOT_ALLOWED',
        };
    }

    /**
     * Accept both business record id and tracking row id from #__contentbuilderng_records.
     */
    private function normalizeRequestedRecordId(int $formId, int $requestedRecordId): int
    {
        if ($formId < 1 || $requestedRecordId < 1) {
            return $requestedRecordId;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('type'), $db->quoteName('reference_id')])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        $db->setQuery($query, 0, 1);
        $form = $db->loadAssoc();

        if (!is_array($form) || empty($form['type']) || empty($form['reference_id'])) {
            return $requestedRecordId;
        }

        $where = [
            $db->quoteName('type') . ' = ' . $db->quote((string) $form['type']),
            $db->quoteName('reference_id') . ' = ' . $db->quote((string) $form['reference_id']),
        ];

        // Standard case: caller already sends the business record_id.
        $query = $db->getQuery(true)
            ->select($db->quoteName('record_id'))
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($where)
            ->where($db->quoteName('record_id') . ' = ' . (int) $requestedRecordId);
        $db->setQuery($query, 0, 1);
        $direct = (int) $db->loadResult();
        if ($direct > 0) {
            return $direct;
        }

        // Compatibility: caller may send tracking table primary key.
        $query = $db->getQuery(true)
            ->select($db->quoteName('record_id'))
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($where)
            ->where($db->quoteName('id') . ' = ' . (int) $requestedRecordId);
        $db->setQuery($query, 0, 1);
        $mapped = (int) $db->loadResult();

        return $mapped > 0 ? $mapped : $requestedRecordId;
    }

    private function sendJson(mixed $payload): void
    {
        $response = [
            'success' => true,
            'messages' => [],
            'data' => $payload,
        ];

        $this->setJsonResponseHeaders();
        $this->siteApp->sendHeaders();
        $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json === false ? '{"success":false,"messages":["JSON encoding error"],"data":null}' : $json;
        $this->siteApp->close();
    }

    private function sendRawJson(array $payload): void
    {
        $this->setJsonResponseHeaders();
        $this->siteApp->sendHeaders();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json === false ? '[]' : $json;
        $this->siteApp->close();
    }

    private function sendJsonError(\Throwable $e, int $formId): void
    {
        $code = (int) $e->getCode();
        if ($code < 100 || $code > 599) {
            $code = 500;
        }
        if ($code >= 400) {
            http_response_code($code);
        }

        Logger::warning('API request failed', [
            'form_id' => $formId,
            'status' => $code,
        ]);

        $showDetails = $code >= 400
            && $code < 500
            && $this->getStatsService()->isFormDebugEnabled($formId);
        $response = [
            'success' => false,
            'messages' => [$showDetails ? $e->getMessage() : $this->getPublicApiErrorMessage($code)],
            'data' => null,
        ];

        $this->setJsonResponseHeaders();
        $this->siteApp->sendHeaders();
        $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json === false ? '{"success":false,"messages":["JSON encoding error"],"data":null}' : $json;
        $this->siteApp->close();
    }

    private function setJsonResponseHeaders(): void
    {
        $this->siteApp->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->siteApp->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        $this->siteApp->setHeader('Pragma', 'no-cache', true);
        $this->siteApp->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->siteApp->setHeader('Vary', 'Authorization, Cookie', true);
    }

    private function getPublicApiErrorMessage(int $code): string
    {
        return match (true) {
            $code === 400, $code === 405 => Text::_('COM_CONTENTBUILDERNG_API_ERROR_INVALID_REQUEST'),
            $code === 401, $code === 403 => Text::_('COM_CONTENTBUILDERNG_API_ERROR_ACCESS_DENIED'),
            default => Text::_('COM_CONTENTBUILDERNG_API_ERROR_RESOURCE_UNAVAILABLE'),
        };
    }

    /**
     * Validates the same short-lived admin preview signature used by list/details/edit.
     */
    private function isValidAdminPreviewRequest(int $formId): bool
    {
        if ($formId < 1 || !$this->input->getBool('cb_preview', false)) {
            return false;
        }

        $until = (int) $this->input->getInt('cb_preview_until', 0);
        $sig = trim((string) $this->input->getString('cb_preview_sig', ''));
        $actorId = (int) $this->input->getInt('cb_preview_actor_id', 0);
        $actorName = trim((string) $this->input->getString('cb_preview_actor_name', ''));
        $userId = (int) $this->input->getInt('cb_preview_user_id', 0);
        if ($userId < 1) {
            return false;
        }

        if ($until < time() || $sig === '') {
            return false;
        }

        $secret = (string) $this->siteApp->get('secret');
        if ($secret === '') {
            return false;
        }

        $payload = PreviewLinkHelper::buildPayload((string) $formId, $until, $actorId, $actorName, $userId);

        if (hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
            $this->input->set('cb_preview_actor_id', $actorId);
            $this->input->set('cb_preview_actor_name', $actorName);
            $this->siteApp->getInput()->set('cb_preview_actor_id', $actorId);
            $this->siteApp->getInput()->set('cb_preview_actor_name', $actorName);
            return true;
        }

        return false;
    }
}
