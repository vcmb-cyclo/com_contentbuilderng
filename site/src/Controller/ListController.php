<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Site\Controller;

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Factory;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Site\Model\EditModel;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;

class ListController extends BaseController
{
    private function getPermissionService(): PermissionService
    {
        return new PermissionService();
    }

    public function delete(): void
    {
        if (!Session::checkToken('post')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $this->getPermissionService()->checkPermissions(
            'delete',
            Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_DELETE_NOT_ALLOWED'),
            '_fe'
        );

        $selectedItems = array_values(
            array_filter(
                array_map('intval', (array) $this->input->get('cid', [], 'array')),
                static fn(int $id): bool => $id > 0
            )
        );

        if ($selectedItems === []) {
            $state = $this->resolveListState();
            $previewQuery = $this->buildPreviewQuery();
            $link = Route::_(
                'index.php?option=com_contentbuilderng&task=list.display&id='
                . $this->input->getInt('id', 0)
                . '&list[limit]=' . $state['limit']
                . '&list[start]=' . $state['start']
                . '&list[ordering]=' . $state['ordering']
                . '&list[direction]=' . $state['direction']
                . $previewQuery
                . '&Itemid=' . $this->input->getInt('Itemid', 0),
                false
            );
            $this->setRedirect($link, Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');

            return;
        }

        /** @var EditModel|null $model */
        $model = $this->getModel('Edit', 'Site', ['ignore_request' => true])
            ?: $this->getModel('Edit', 'Contentbuilderng', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('EditModel not found');
        }

        if (method_exists($model, 'setIds')) {
            $model->setIds(
                $this->input->getInt('id', 0),
                $this->input->getCmd('record_id', 0)
            );
        }

        $ok = true;
        try {
            // The model may not return a strict boolean; treat "no exception" as success.
            $model->delete();
        } catch (\Throwable $e) {
            $ok = false;
            $this->app->enqueueMessage($e->getMessage(), 'warning');
        }

        if ($ok) {
            $deletedCount = count($selectedItems);
            if ($deletedCount > 1) {
                $msg = Text::plural('JLIB_APPLICATION_N_ITEMS_DELETED', $deletedCount);
                if (
                    $msg === 'JLIB_APPLICATION_N_ITEMS_DELETED'
                    || str_starts_with($msg, 'JLIB_APPLICATION_N_ITEMS_DELETED_')
                ) {
                    $msg = Text::_('COM_CONTENTBUILDERNG_ENTRIES_DELETED') . ' (' . $deletedCount . ')';
                }
            } else {
                $msg = Text::_('COM_CONTENTBUILDERNG_ENTRIES_DELETED');
            }
        } else {
            $msg = Text::_('COM_CONTENTBUILDERNG_ERROR');
        }
        $type = $ok ? 'message' : 'warning';

        // Clear record context to avoid redirects back to a deleted record.
        $this->input->set('record_id', 0);
        Factory::getApplication()->input->set('record_id', 0);

        $state = $this->resolveListState();
        $previewQuery = $this->buildPreviewQuery();
        $link = Route::_(
            'index.php?option=com_contentbuilderng&task=list.display&id='
            . $this->input->getInt('id', 0)
            . '&list[limit]=' . $state['limit']
            . '&list[start]=' . $state['start']
            . '&list[ordering]=' . $state['ordering']
            . '&list[direction]=' . $state['direction']
            . $previewQuery
            . '&Itemid=' . $this->input->getInt('Itemid', 0),
            false
        );

        $this->setRedirect($link, $msg, $type);
    }

    public function state(): void
    {
        if (!Session::checkToken('post')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $this->getPermissionService()->checkPermissions(
            'state',
            Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_STATE_CHANGE_NOT_ALLOWED'),
            '_fe'
        );

        /** @var EditModel|null $model */
        $model = $this->getModel('Edit', 'Site', ['ignore_request' => true])
            ?: $this->getModel('Edit', 'Contentbuilderng', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('EditModel not found');
        }

        if (method_exists($model, 'setIds')) {
            $model->setIds(
                $this->input->getInt('id', 0),
                $this->input->getCmd('record_id', 0)
            );
        }

        $selectedItems = array_values(
            array_unique(
                array_filter(
                    array_map(static fn($id): string => trim((string) $id), (array) $this->input->get('cid', [], 'array')),
                    static fn(string $id): bool => $id !== ''
                )
            )
        );

        if ($selectedItems === []) {
            $state = $this->resolveListState();
            $previewQuery = $this->buildPreviewQuery();
            $link = Route::_(
                'index.php?option=com_contentbuilderng&task=list.display&id='
                . $this->input->getInt('id', 0)
                . '&list[limit]=' . $state['limit']
                . '&list[start]=' . $state['start']
                . '&list[ordering]=' . $state['ordering']
                . '&list[direction]=' . $state['direction']
                . $previewQuery
                . '&Itemid=' . $this->input->getInt('Itemid', 0),
                false
            );
            $this->setRedirect($link, Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');

            return;
        }

        $this->input->set('cid', $selectedItems);
        Factory::getApplication()->input->set('cid', $selectedItems);

        $changedCount = (int) $model->change_list_states();
        $message = Text::_('COM_CONTENTBUILDERNG_STATES_CHANGED') . ' (' . $changedCount . ')';

        $state = $this->resolveListState();
        $previewQuery = $this->buildPreviewQuery();
        $link = Route::_(
            'index.php?option=com_contentbuilderng&task=list.display&id='
            . $this->input->getInt('id', 0)
            . '&list[limit]=' . $state['limit']
            . '&list[start]=' . $state['start']
            . '&list[ordering]=' . $state['ordering']
            . '&list[direction]=' . $state['direction']
            . $previewQuery
            . '&Itemid=' . $this->input->getInt('Itemid', 0),
            false
        );
        $this->setRedirect($link, $message, 'message');
    }

    public function publish(): void
    {
        if (!Session::checkToken('post')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $this->getPermissionService()->checkPermissions(
            'publish',
            Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_PUBLISHING_NOT_ALLOWED'),
            '_fe'
        );

        /** @var EditModel|null $model */
        $model = $this->getModel('Edit', 'Site', ['ignore_request' => true])
            ?: $this->getModel('Edit', 'Contentbuilderng', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('EditModel not found');
        }

        if (method_exists($model, 'setIds')) {
            $model->setIds(
                $this->input->getInt('id', 0),
                $this->input->getCmd('record_id', 0)
            );
        }

        $selectedItems = array_values(
            array_unique(
                array_filter(
                    array_map(static fn($id): string => trim((string) $id), (array) $this->input->get('cid', [], 'array')),
                    static fn(string $id): bool => $id !== ''
                )
            )
        );

        if ($selectedItems === []) {
            $state = $this->resolveListState();
            $previewQuery = $this->buildPreviewQuery();
            $link = Route::_(
                'index.php?option=com_contentbuilderng&task=list.display&id='
                . $this->input->getInt('id', 0)
                . '&list[limit]=' . $state['limit']
                . '&list[start]=' . $state['start']
                . '&list[ordering]=' . $state['ordering']
                . '&list[direction]=' . $state['direction']
                . $previewQuery
                . '&Itemid=' . $this->input->getInt('Itemid', 0),
                false
            );
            $this->setRedirect($link, Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');

            return;
        }

        $this->input->set('cid', $selectedItems);
        Factory::getApplication()->input->set('cid', $selectedItems);

        $changedCount = (int) $model->change_list_publish();

        $msg = $this->input->getInt('list_publish', 0)
            ? Text::_('COM_CONTENTBUILDERNG_PUBLISHED')
            : Text::_('COM_CONTENTBUILDERNG_UNPUBLISHED');
        $msg .= ' (' . $changedCount . ')';

        $state = $this->resolveListState();
        $previewQuery = $this->buildPreviewQuery();
        $link = Route::_(
            'index.php?option=com_contentbuilderng&task=list.display&id='
            . $this->input->getInt('id', 0)
            . '&list[limit]=' . $state['limit']
            . '&list[start]=' . $state['start']
            . '&list[ordering]=' . $state['ordering']
            . '&list[direction]=' . $state['direction']
            . $previewQuery
            . '&Itemid=' . $this->input->getInt('Itemid', 0),
            false
        );
        $this->setRedirect($link, $msg, 'message');
    }

    public function display($cachable = false, $urlparams = [])
    {
        /** @var SiteApplication $app */
        $app   = Factory::getApplication();
        $storageId = $this->input->getInt('storage_id', 0);
        $isDirectStorageMode = $storageId > 0 && $this->input->getInt('id', 0) <= 0;

        $suffix = '_fe';

        // 1) d'abord depuis l'URL
        $formId   = $this->input->getInt('id', 0);
        $recordId = $this->input->getInt('record_id', 0);

        // 2) sinon depuis les params du menu actif
        if (!$formId) {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                $formId = (int) $menu->getParams()->get('form_id', 0);
            }
        }

        // Keep both input bags aligned for downstream model/view access.
        $this->input->set('id', $formId);
        Factory::getApplication()->input->set('id', $formId);

        if ($recordId) {
            $this->input->set('record_id', $recordId);
            Factory::getApplication()->input->set('record_id', $recordId);
        }

        // Contexte CB correct pour cette page
        Factory::getApplication()->input->set('view', 'list');

        // Permissions
        if (!$isDirectStorageMode) {
            $this->getPermissionService()->setPermissions($formId, $recordId, $suffix);
        }
        $isAdminPreview = $this->isValidAdminPreviewRequest($formId, $storageId);
        $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        Factory::getApplication()->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        if ($isAdminPreview && !$isDirectStorageMode) {
            $this->enqueueUnpublishedPreviewNotice($formId);
        }
        if (!$isDirectStorageMode && !$isAdminPreview) {
            $this->getPermissionService()->checkPermissions(
                'listaccess',
                Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_LISTACCESS_NOT_ALLOWED'),
                $suffix
            );
        }

        // Piloter le rendu via l'input Joomla
        $layout = $this->input->getCmd('layout', null);
        $this->input->set('layout', ($layout === 'latest') ? null : $layout);

        return parent::display($cachable, $urlparams);
    }

    private function resolveListState(): array
    {
        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $option = 'com_contentbuilderng';
        $list = (array) $this->input->get('list', [], 'array');
        $stateKeyPrefix = $this->getPaginationStateKeyPrefix();
        $limitKey = $stateKeyPrefix . '.limit';
        $startKey = $stateKeyPrefix . '.start';

        $limit = isset($list['limit']) ? $this->input->getInt('list[limit]', 0) : 0;
        if ($limit === 0) {
            $limit = (int) $app->getUserState($limitKey, 0);
        }
        if ($limit === 0) {
            $limit = (int) $app->get('list_limit');
        }

        if (array_key_exists('start', $list)) {
            $start = max(0, $this->input->getInt('list[start]', 0));
        } else {
            $start = (int) $app->getUserState($startKey, 0);
        }

        $ordering = isset($list['ordering']) ? $this->input->getCmd('list[ordering]', '') : '';
        if ($ordering === '') {
            $ordering = (string) $app->getUserState($option . '.formsd_filter_order', '');
        }

        $direction = isset($list['direction']) ? $this->input->getCmd('list[direction]', '') : '';
        if ($direction === '') {
            $direction = (string) $app->getUserState($option . '.formsd_filter_order_Dir', '');
        }

        return [
            'limit' => (int) $limit,
            'start' => (int) $start,
            'ordering' => (string) $ordering,
            'direction' => (string) $direction,
        ];
    }

    private function getPaginationStateKeyPrefix(): string
    {
        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $option = 'com_contentbuilderng';

        $formId = (int) $this->input->getInt('id', 0);
        if ($formId < 1) {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                $formId = (int) $menu->getParams()->get('form_id', 0);
            }
        }

        $layout = (string) $this->input->getCmd('layout', 'default');
        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $this->input->getInt('Itemid', 0);

        return $option . '.liststate.' . $formId . '.' . $layout . '.' . $itemId;
    }

    /**
     * Validates a short-lived preview signature generated in admin toolbar.
     */
    private function isValidAdminPreviewRequest(int $formId, int $storageId = 0): bool
    {
        if (($formId < 1 && $storageId < 1) || !$this->input->getBool('cb_preview', false)) {
            return false;
        }

        $until = (int) $this->input->getInt('cb_preview_until', 0);
        $sig   = (string) $this->input->getString('cb_preview_sig', '');
        $actorId = (int) $this->input->getInt('cb_preview_actor_id', 0);
        $actorName = trim((string) $this->input->getString('cb_preview_actor_name', ''));

        if ($until < time() || $sig === '') {
            return false;
        }

        $secret = (string) Factory::getApplication()->get('secret');
        if ($secret === '') {
            return false;
        }

        $targets = [];
        if ($formId > 0) {
            $targets[] = (string) $formId;
        }
        if ($storageId > 0) {
            $targets[] = 'storage:' . $storageId;
        }

        foreach ($targets as $target) {
            $payload  = $target . '|' . $until;
            $expected = hash_hmac('sha256', $payload, $secret);
            $actorPayload = $payload . '|' . $actorId . '|' . $actorName;
            $actorExpected = hash_hmac('sha256', $actorPayload, $secret);

            if (($actorId > 0 || $actorName !== '') && hash_equals($actorExpected, $sig)) {
                $this->input->set('cb_preview_actor_id', $actorId);
                $this->input->set('cb_preview_actor_name', $actorName);
                Factory::getApplication()->input->set('cb_preview_actor_id', $actorId);
                Factory::getApplication()->input->set('cb_preview_actor_name', $actorName);
                return true;
            }

            if (hash_equals($expected, $sig)) {
                $this->input->set('cb_preview_actor_id', 0);
                $this->input->set('cb_preview_actor_name', '');
                Factory::getApplication()->input->set('cb_preview_actor_id', 0);
                Factory::getApplication()->input->set('cb_preview_actor_name', '');
                return true;
            }
        }

        return false;
    }

    /**
     * Shows the warning once per preview link when the form is unpublished.
     */
    private function enqueueUnpublishedPreviewNotice(int $formId): void
    {
        if ($formId < 1 || $this->isFormPublished($formId)) {
            return;
        }

        $until = (int) $this->input->getInt('cb_preview_until', 0);
        $sig = (string) $this->input->getString('cb_preview_sig', '');
        $noticeKey = 'com_contentbuilderng.preview_notice.' . hash('sha256', $formId . '|' . $until . '|' . $sig);
        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $session = $app->getSession();

        if ($session->get($noticeKey, false)) {
            return;
        }

        $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_PREVIEW_UNPUBLISHED_NOTICE'), 'warning');
        $session->set($noticeKey, true);
    }

    private function isFormPublished(int $formId): bool
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('published'))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('id') . ' = ' . (int) $formId);
            $db->setQuery($query);
            $published = $db->loadResult();
        } catch (\Throwable $e) {
            return true;
        }

        return (int) $published === 1;
    }

    private function buildPreviewQuery(): string
    {
        if (!$this->input->getBool('cb_preview', false)) {
            return '';
        }

        $until = (int) $this->input->getInt('cb_preview_until', 0);
        $sig = trim((string) $this->input->getString('cb_preview_sig', ''));
        if ($until <= 0 || $sig === '') {
            return '';
        }

        $actorId = (int) $this->input->getInt('cb_preview_actor_id', 0);
        $actorName = trim((string) $this->input->getString('cb_preview_actor_name', ''));
        $adminReturn = trim((string) $this->input->getCmd('cb_admin_return', ''));

        return '&cb_preview=1'
            . '&cb_preview_until=' . $until
            . '&cb_preview_actor_id=' . $actorId
            . '&cb_preview_actor_name=' . rawurlencode($actorName)
            . '&cb_preview_sig=' . rawurlencode($sig)
            . ($adminReturn !== '' ? '&cb_admin_return=' . rawurlencode($adminReturn) : '');
    }
}
