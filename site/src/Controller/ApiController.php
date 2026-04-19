<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Controller;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;
use CB\Component\Contentbuilderng\Site\Model\DetailsModel;
use CB\Component\Contentbuilderng\Site\Model\EditModel;
use CB\Component\Contentbuilderng\Site\Model\ListModel;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Input\Input;
use Joomla\CMS\Session\Session;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;

class ApiController extends BaseController
{
    private SiteApplication $siteApp;
    private bool $frontend;

    public function __construct(
        $config,
        MVCFactoryInterface $factory,
        CMSApplicationInterface $app,
        Input $input
    ) {
        parent::__construct($config, $factory, $app, $input);

        if (!$app instanceof SiteApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        $this->siteApp = $app;
        $this->frontend = $this->siteApp->isClient('site');
    }

    public function display($cachable = false, $urlparams = []): void
    {
        try {
            $formId = (int) $this->input->getInt('id', 0);
            $recordId = (int) $this->input->getInt('record_id', 0);
            $action = trim((string) $this->input->getCmd('action', ''));
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

            Logger::info('API request', [
                'method' => $method,
                'form_id' => $formId,
                'record_id' => $recordId,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'content_type' => (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
                'query' => (string) ($_SERVER['QUERY_STRING'] ?? ''),
            ]);

            if ($formId < 1) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
            }

            $isAdminPreview = $this->isValidAdminPreviewRequest($formId);
            $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
            $this->siteApp->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);

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

            (new PermissionService())->setPermissions($formId, $recordId, $this->frontend ? '_fe' : '');
            if (!$this->can('api')) {
                $action = trim((string) $this->input->getCmd('action', ''));

                if ($action === 'rating') {
                    throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_RATING_NOT_ALLOWED'), 403);
                }

                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_API_NOT_ALLOWED'), 403);
            }

            if ($action !== '') {
                $payload = $this->handleAction($action, $formId, $recordId);
                $this->sendJson($payload);
                return;
            }

            if ($method === 'GET') {
                $payload = $recordId > 0
                    ? $this->getDetailPayload($formId, $recordId)
                    : $this->getListPayload($formId);

                $this->sendJson($payload);
                return;
            }

            if (in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
                if ($recordId < 1) {
                    throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_RECORD_ID_REQUIRED'), 400);
                }

                (new PermissionService())->setPermissions($formId, $recordId, $this->frontend ? '_fe' : '');
                if (!$this->can('edit')) {
                    throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_EDIT_NOT_ALLOWED'), 403);
                }

                $updatedRecordId = $this->updateRecord($formId, $recordId);
                $this->sendJson([
                    'message' => Text::_('COM_CONTENTBUILDERNG_SAVE'),
                    'record_id' => $updatedRecordId,
                    'detail' => $this->getDetailPayload($formId, $updatedRecordId),
                ]);
                return;
            }

            throw new \RuntimeException('Unsupported HTTP method', 405);
        } catch (\Throwable $e) {
            $this->sendJsonError($e);
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
        if (!$this->can('listaccess')) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED'), 403);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('type'),
                $db->quoteName('reference_id'),
                $db->quoteName('published'),
            ])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        $db->setQuery($query, 0, 1);
        $formRow = $db->loadAssoc();

        if (!is_array($formRow) || empty($formRow['type']) || empty($formRow['reference_id'])) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        $nowSql = Factory::getDate()->toSql();
        $recordWhere = [
            $db->quoteName('type') . ' = ' . $db->quote((string) $formRow['type']),
            $db->quoteName('reference_id') . ' = ' . $db->quote((string) $formRow['reference_id']),
        ];

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

        $query = $db->getQuery(true)
            ->select([
                'COUNT(*) AS ' . $db->quoteName('total'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('published') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('published'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('list_include') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('list_include'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('search_include') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('search_include'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('editable') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('editable'),
                'COALESCE(SUM(CASE WHEN ' . $db->quoteName('linkable') . ' = 1 THEN 1 ELSE 0 END), 0) AS ' . $db->quoteName('linkable'),
            ])
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
        $db->setQuery($query, 0, 1);
        $elements = $db->loadAssoc() ?: [];

        return [
            'form' => [
                'id' => (int) $formRow['id'],
                'name' => (string) ($formRow['name'] ?? ''),
                'type' => (string) $formRow['type'],
                'reference_id' => (string) $formRow['reference_id'],
                'published' => (int) ($formRow['published'] ?? 0),
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
            'elements' => [
                'total' => (int) ($elements['total'] ?? 0),
                'published' => (int) ($elements['published'] ?? 0),
                'list_include' => (int) ($elements['list_include'] ?? 0),
                'search_include' => (int) ($elements['search_include'] ?? 0),
                'editable' => (int) ($elements['editable'] ?? 0),
                'linkable' => (int) ($elements['linkable'] ?? 0),
            ],
        ];
    }

    private function getUniqueValuesPayload(int $formId): array
    {
        if (!$this->can('listaccess')) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED'), 403);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
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

        $values = $form->getUniqueValues(
            $this->input->getCmd('field_reference_id', ''),
            $this->input->getCmd('where_field', ''),
            $this->input->get('where', '', 'string')
        );

        return [
            'code' => 0,
            'field_reference_id' => $this->input->getCmd('field_reference_id', ''),
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

        if (!Session::checkToken('post') && !Session::checkToken('get')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
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

        $now = Factory::getDate();
        $nowSql = $now->toSql();

        $db->setQuery("Delete From #__contentbuilderng_rating_cache Where Datediff(" . $db->quote($nowSql) . ", `date`) >= 1");
        $db->execute();

        $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $db->setQuery(
            "Select `form_id` From #__contentbuilderng_rating_cache"
            . " Where `record_id` = " . $db->quote((string) $recordId)
            . " And `form_id` = " . $formId
            . " And `ip` = " . $db->quote($clientIp)
        );
        $cached = $db->loadResult();
        $rated = $this->siteApp->getSession()->get('rated' . $formId . $recordId, false, 'com_contentbuilderng.rating');

        if ($rated || $cached) {
            return ['code' => 1, 'msg' => Text::_('COM_CONTENTBUILDERNG_RATED_ALREADY')];
        }

        $this->siteApp->getSession()->set('rated' . $formId . $recordId, true, 'com_contentbuilderng.rating');

        $db->setQuery(
            "Update #__contentbuilderng_records"
            . " Set rating_count = rating_count + 1, rating_sum = rating_sum + " . $rating . ", lastip = " . $db->quote($clientIp)
            . " Where `type` = " . $db->quote((string) $result['type'])
            . " And `reference_id` = " . $db->quote((string) $result['reference_id'])
            . " And `record_id` = " . $db->quote((string) $recordId)
        );
        $db->execute();

        $db->setQuery(
            "Insert Into #__contentbuilderng_rating_cache (`record_id`,`form_id`,`ip`,`date`) Values ("
            . $db->quote((string) $recordId) . ", "
            . $formId . ", "
            . $db->quote($clientIp) . ", "
            . $db->quote($nowSql) . ")"
        );
        $db->execute();

        $db->setQuery(
            "Select a.article_id From #__contentbuilderng_articles As a, #__content As c"
            . " Where c.id = a.article_id And (c.state = 1 Or c.state = 0)"
            . " And a.form_id = " . $formId
            . " And a.record_id = " . $db->quote((string) $recordId)
        );
        $articleId = (int) $db->loadResult();

        if ($articleId > 0) {
            $db->setQuery("Select content_id From #__content_rating Where content_id = " . $articleId);
            $exists = $db->loadResult();

            if ($exists) {
                $db->setQuery("
                    Update 
                        #__content_rating As cr, 
                        #__contentbuilderng_records As cbr, 
                        #__contentbuilderng_articles As cba
                    Set
                        cr.rating_count = cbr.rating_count,
                        cr.rating_sum = cbr.rating_sum,
                        cr.lastip = cbr.lastip
                    Where
                        cbr.record_id = " . $db->quote((string) $recordId) . "
                    And
                        cbr.record_id = cba.record_id
                    And
                        cbr.reference_id = " . $db->quote((string) $result['reference_id']) . "
                    And
                        cbr.`type` = " . $db->quote((string) $result['type']) . " 
                    And 
                        cba.form_id = " . $formId . "
                    And
                        cr.content_id = cba.article_id
                ");
                $db->execute();
            } else {
                $db->setQuery("
                    Insert Into #__content_rating (
                        content_id,
                        rating_sum,
                        rating_count,
                        lastip
                    ) Values (
                        " . $articleId . ",
                        " . $rating . ",
                        1,
                        " . $db->quote($clientIp) . "
                    )");
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
        $this->input->set('id', $formId);
        $model = $this->getListModel();
        $dataSet = $model->getData();
        $subject = (is_array($dataSet) && isset($dataSet[0])) ? $dataSet[0] : null;
        if (!is_object($subject) || !isset($subject->items) || !is_array($subject->items)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
        }

        $elementNames = [];
        if (isset($subject->form) && is_object($subject->form) && method_exists($subject->form, 'getElementNames')) {
            $elementNames = (array) $subject->form->getElementNames();
        }

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
        $verbose = (bool) $this->input->getBool('verbose', false);

        $model = $this->getDetailsModel();
        $dataSet = $model->getData();
        $subject = (is_array($dataSet) && isset($dataSet[0])) ? $dataSet[0] : null;
        if (!is_object($subject) || !isset($subject->items) || !is_array($subject->items)) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
        }

        $fields = [];
        foreach ($subject->items as $item) {
            if (!is_object($item)) {
                continue;
            }

            $name = (string) ($item->recName ?? '');
            if ($name === '') {
                continue;
            }

            $fields[$name] = [
                'reference_id' => (string) ($item->recElementId ?? ''),
                'label' => (string) ($item->recLabel ?? $name),
                'value' => $item->recValue ?? null,
            ];
        }

        return [
            'record_id' => $recordId,
            'form_id' => $formId,
            'fields' => $this->normalizeDetailFields($fields, $verbose),
            'navigation' => $this->resolveSiblingRecordIds((string) ($subject->type ?? ''), (string) ($subject->reference_id ?? ''), $recordId, !empty($subject->published_only)),
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

    private function resolveSiblingRecordIds(string $type, string $referenceId, int $recordId, bool $publishedOnly): array
    {
        if ($recordId < 1 || $type === '' || $referenceId === '') {
            return ['previous' => 0, 'next' => 0];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $where = [
            $db->quoteName('type') . ' = ' . $db->quote($type),
            $db->quoteName('reference_id') . ' = ' . $db->quote($referenceId),
        ];
        if ($publishedOnly) {
            $where[] = $db->quoteName('published') . ' = 1';
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('record_id'))
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($where)
            ->where($db->quoteName('record_id') . ' < ' . (int) $recordId)
            ->order($db->quoteName('record_id') . ' DESC');
        $db->setQuery($query, 0, 1);
        $previous = (int) $db->loadResult();

        $query = $db->getQuery(true)
            ->select($db->quoteName('record_id'))
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($where)
            ->where($db->quoteName('record_id') . ' > ' . (int) $recordId)
            ->order($db->quoteName('record_id') . ' ASC');
        $db->setQuery($query, 0, 1);
        $next = (int) $db->loadResult();

        return ['previous' => $previous, 'next' => $next];
    }

    private function updateRecord(int $formId, int $recordId): int
    {
        $fields = $this->extractRequestedFields();
        if ($fields === []) {
            throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_API_FIELDS_REQUIRED'), 400);
        }

        $form = $this->loadFormObject($formId);
        $elementNames = method_exists($form, 'getElementNames') ? (array) $form->getElementNames() : [];
        $nameToRef = [];
        foreach ($elementNames as $ref => $name) {
            $nameToRef[(string) $name] = (string) $ref;
        }

        foreach ($fields as $fieldKey => $fieldValue) {
            $key = (string) $fieldKey;
            $referenceId = $nameToRef[$key] ?? null;
            if ($referenceId === null && ctype_digit($key)) {
                $referenceId = $key;
            }
            if ($referenceId === null) {
                continue;
            }
            $this->input->post->set('cb_' . $referenceId, $fieldValue);
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
        $json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($json) && isset($json['fields']) && is_array($json['fields'])) {
            return $json['fields'];
        }

        $fields = $this->input->post->get('fields', [], 'array');
        return is_array($fields) ? $fields : [];
    }

    private function loadFormObject(int $formId)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
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
            ? (new PermissionService())->authorizeFe($action)
            : (new PermissionService())->authorize($action);
    }

    /**
     * Accept both business record id and tracking row id from #__contentbuilderng_records.
     */
    private function normalizeRequestedRecordId(int $formId, int $requestedRecordId): int
    {
        if ($formId < 1 || $requestedRecordId < 1) {
            return $requestedRecordId;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
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

    private function sendJson(array $payload): void
    {
        $response = [
            'success' => true,
            'message' => null,
            'messages' => null,
            'data' => $payload,
        ];

        $this->siteApp->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json === false ? '{"success":false,"message":"JSON encoding error","messages":null,"data":null}' : $json;
        $this->siteApp->close();
    }

    private function sendJsonError(\Throwable $e): void
    {
        $code = (int) $e->getCode();
        if ($code < 100 || $code > 599) {
            $code = 500;
        }
        if ($code >= 400) {
            http_response_code($code);
        }

        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'messages' => null,
            'data' => null,
        ];

        $this->siteApp->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json === false ? '{"success":false,"message":"JSON encoding error","messages":null,"data":null}' : $json;
        $this->siteApp->close();
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
            $this->siteApp->input->set('cb_preview_actor_id', $actorId);
            $this->siteApp->input->set('cb_preview_actor_name', $actorName);
            return true;
        }

        return false;
    }
}
