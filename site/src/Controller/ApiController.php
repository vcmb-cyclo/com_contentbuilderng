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
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_API_NOT_ALLOWED'), 403);
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
                    'message' => Text::_('COM_CONTENTBUILDERNG_SAVED'),
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
        $requestedPagination = $this->getRequestedListPagination();
        $this->input->set('id', $formId);
        $model = $this->getListModel();
        $dataSet = $model->getData();
        $subject = (is_array($dataSet) && isset($dataSet[0])) ? $dataSet[0] : null;
        if (!is_object($subject) || !isset($subject->items) || !is_array($subject->items)) {
            return $this->getFallbackListPayload($formId);
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

        // If explicit pagination was requested but ListModel ignored it (state leakage),
        // rebuild list through the API fallback path that enforces limit/start from query.
        if (
            $requestedPagination['explicit']
            && (
                $limit !== $requestedPagination['limit']
                || $start !== $requestedPagination['start']
            )
        ) {
            return $this->getFallbackListPayload($formId);
        }

        $missingListFields = !empty($subject->preview_no_list_fields)
            || (isset($subject->visible_cols) && is_array($subject->visible_cols) && count($subject->visible_cols) === 0);
        if ($total === 0 && $missingListFields) {
            return $this->getFallbackListPayload($formId);
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'start' => $start,
            ],
        ];
    }

    private function getFallbackListPayload(int $formId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('type'), $db->quoteName('reference_id'), $db->quoteName('published_only')])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        $db->setQuery($query, 0, 1);
        $row = $db->loadAssoc();

        if (!is_array($row) || empty($row['type']) || empty($row['reference_id'])) {
            return ['items' => [], 'pagination' => ['total' => 0, 'limit' => 0, 'start' => 0]];
        }

        $form = FormSourceFactory::getForm((string) $row['type'], (string) $row['reference_id']);
        if (!$form || !is_object($form) || !method_exists($form, 'getRecord')) {
            return ['items' => [], 'pagination' => ['total' => 0, 'limit' => 0, 'start' => 0]];
        }

        $requestedPagination = $this->getRequestedListPagination();
        $requestedLimit = (int) $requestedPagination['limit'];
        $requestedStart = (int) $requestedPagination['start'];
        $isAdminPreview = (bool) $this->input->getBool('cb_preview_ok', false);
        $publishedOnly = !$isAdminPreview && !empty($row['published_only']);

        $where = [
            $db->quoteName('type') . ' = ' . $db->quote((string) $row['type']),
            $db->quoteName('reference_id') . ' = ' . $db->quote((string) $row['reference_id']),
        ];
        if ($publishedOnly) {
            $where[] = $db->quoteName('published') . ' = 1';
        }

        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('record_id') . ')')
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($where);
        $db->setQuery($query);
        $total = (int) $db->loadResult();

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('record_id'))
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($where)
            ->order($db->quoteName('record_id') . ' DESC');
        $db->setQuery($query, $requestedStart, $requestedLimit);
        $recordIds = array_map('intval', (array) $db->loadColumn());

        $items = [];
        foreach ($recordIds as $recordId) {
            if ($recordId < 1) {
                continue;
            }

            $recordItems = $form->getRecord($recordId, $publishedOnly, -1, true);
            if (!is_array($recordItems) || empty($recordItems)) {
                continue;
            }

            $values = [];
            foreach ($recordItems as $recordItem) {
                if (!is_object($recordItem)) {
                    continue;
                }

                $name = trim((string) ($recordItem->recName ?? ''));
                if ($name === '' || array_key_exists($name, $values)) {
                    continue;
                }

                $values[$name] = $recordItem->recValue ?? null;
            }

            $items[] = [
                'record_id' => $recordId,
                'values' => $values,
            ];
        }

        Logger::info('API fallback list payload used', [
            'form_id' => $formId,
            'type' => (string) $row['type'],
            'reference_id' => (string) $row['reference_id'],
            'total' => $total,
            'limit' => $requestedLimit,
            'start' => $requestedStart,
        ]);

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $requestedLimit,
                'start' => $requestedStart,
            ],
        ];
    }

    private function getRequestedListPagination(): array
    {
        $list = (array) $this->input->get('list', [], 'array');
        $hasExplicitLimit = array_key_exists('limit', $list);
        $hasExplicitStart = array_key_exists('start', $list);

        $limit = $hasExplicitLimit ? (int) $list['limit'] : 0;
        if ($limit <= 0) {
            $limit = (int) $this->siteApp->get('list_limit', 20);
        }

        $start = $hasExplicitStart ? (int) $list['start'] : 0;
        if ($start < 0) {
            $start = 0;
        }

        return [
            'limit' => $limit,
            'start' => $start,
            'explicit' => $hasExplicitLimit || $hasExplicitStart,
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
            $fallbackPayload = $this->getFallbackDetailPayload($formId, $recordId);
            if (is_array($fallbackPayload)) {
                return $fallbackPayload;
            }
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

    private function getFallbackDetailPayload(int $formId, int $recordId): ?array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('type'), $db->quoteName('reference_id'), $db->quoteName('published_only')])
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $formId);
        $db->setQuery($query, 0, 1);
        $row = $db->loadAssoc();

        if (!is_array($row) || empty($row['type']) || empty($row['reference_id'])) {
            return null;
        }

        $form = FormSourceFactory::getForm((string) $row['type'], (string) $row['reference_id']);
        if (!$form || !is_object($form) || !method_exists($form, 'getRecord')) {
            return null;
        }

        // Compatibility fallback: pull the record directly from the form bridge.
        $recordItems = $form->getRecord($recordId, false, -1, true);
        if (!is_array($recordItems) || empty($recordItems)) {
            return null;
        }

        $fields = [];
        foreach ($recordItems as $item) {
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

        if (empty($fields)) {
            return null;
        }

        Logger::info('API fallback detail payload used', [
            'form_id' => $formId,
            'record_id' => $recordId,
            'type' => (string) $row['type'],
            'reference_id' => (string) $row['reference_id'],
        ]);

        return [
            'record_id' => $recordId,
            'form_id' => $formId,
            'fields' => $this->normalizeDetailFields($fields, (bool) $this->input->getBool('verbose', false)),
            'navigation' => $this->resolveSiblingRecordIds(
                (string) $row['type'],
                (string) $row['reference_id'],
                $recordId,
                !empty($row['published_only'])
            ),
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

        if ($until < time() || $sig === '') {
            return false;
        }

        $secret = (string) $this->siteApp->get('secret');
        if ($secret === '') {
            return false;
        }

        $payload = $formId . '|' . $until;
        $expected = hash_hmac('sha256', $payload, $secret);

        $actorPayload = $payload . '|' . $actorId . '|' . $actorName;
        $actorExpected = hash_hmac('sha256', $actorPayload, $secret);

        if (($actorId > 0 || $actorName !== '') && hash_equals($actorExpected, $sig)) {
            $this->input->set('cb_preview_actor_id', $actorId);
            $this->input->set('cb_preview_actor_name', $actorName);
            $this->siteApp->input->set('cb_preview_actor_id', $actorId);
            $this->siteApp->input->set('cb_preview_actor_name', $actorName);
            return true;
        }

        if (hash_equals($expected, $sig)) {
            $this->input->set('cb_preview_actor_id', 0);
            $this->input->set('cb_preview_actor_name', '');
            $this->siteApp->input->set('cb_preview_actor_id', 0);
            $this->siteApp->input->set('cb_preview_actor_name', '');
            return true;
        }

        return false;
    }
}
