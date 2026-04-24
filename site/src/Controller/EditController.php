<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\NavigationLinkHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;
use CB\Component\Contentbuilderng\Site\Model\EditModel;

class EditController extends BaseController
{
    private SiteApplication $siteApp;
    private bool $frontend;

    private function getPermissionService(): PermissionService
    {
        return new PermissionService();
    }

    private function getEditModel(array $config = ['ignore_request' => true]): EditModel
    {
        $model = $this->getModel('Edit', 'Site', $config)
            ?: $this->getModel('Edit', 'Contentbuilderng', $config);

        if (!$model instanceof EditModel) {
            throw new \RuntimeException('EditModel not found');
        }

        return $model;
    }

    private function applyPreviewContextForAction(): bool
    {
        $formId = (int) $this->input->getInt('id', 0);
        $storageId = (int) $this->input->getInt('storage_id', 0);
        $isAdminPreview = $this->isValidAdminPreviewRequest($formId, $storageId);
        $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        $this->siteApp->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        return $isAdminPreview;
    }

    private function checkPermissionForAjax(string $action, string $message): bool
    {
        try {
            $this->getPermissionService()->checkPermissions($action, $message, $this->frontend ? '_fe' : '');
        } catch (NotAllowed $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return false;
            }

            throw $e;
        }

        return true;
    }

    public function __construct(
        $config,
        MVCFactoryInterface $factory,
        CMSApplicationInterface $app,
        Input $input
    ) {
        // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory, $app, $input);

        if (!$app instanceof SiteApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        $this->siteApp = $app;
        $this->frontend = $this->siteApp->isClient('site');
       
        $this->siteApp->input->set('cbIsNew', 0);
        $storageId = (int) $this->siteApp->input->getInt('storage_id', 0);
        $isDirectStorageMode = $storageId > 0 && $this->siteApp->input->getInt('id', 0) <= 0;
        $isAdminPreview = $isDirectStorageMode ? $this->isValidAdminPreviewRequest(0, $storageId) : false;

        $task = (string) $this->siteApp->input->getCmd('task', '');
        $taskAction = str_contains($task, '.') ? substr($task, strrpos($task, '.') + 1) : $task;

        if ($isDirectStorageMode && $isAdminPreview) {
            $this->getPermissionService()->setStoragePreviewPermissions($storageId, $this->frontend ? '_fe' : '');
        } elseif (in_array($taskAction, ['delete', 'state', 'publish', 'language'], true)) {
            $items = $this->siteApp->input->get('cid', [], 'array');
            $this->getPermissionService()->setPermissions($this->siteApp->input->getInt('id', 0), $items, $this->frontend ? '_fe' : '');
        } else {
            if (!$isDirectStorageMode && $this->siteApp->input->getCmd('record_id', '')) {
                $this->getPermissionService()->setPermissions($this->siteApp->input->getInt('id', 0), $this->siteApp->input->getCmd('record_id', ''), $this->frontend ? '_fe' : '');
            } elseif (!$isDirectStorageMode) {
                $this->siteApp->input->set('cbIsNew', 1);
                $this->getPermissionService()->setPermissions($this->siteApp->input->getInt('id', 0), 0, $this->frontend ? '_fe' : '');
            }
        }
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel|false  Model object on success; otherwise false on failure.
     */
    public function getModel($name = 'Edit', $prefix = 'Site', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function save($apply = false)
    {
        $isAdminPreview = $this->applyPreviewContextForAction();

        if ($this->siteApp->isClient('site') && $this->siteApp->input->getInt('Itemid', 0)) {
            $menu = $this->siteApp->getMenu();
            $item = $menu->getActive();
            if (is_object($item)) {
                $params = $item->getParams();
                $this->siteApp->input->set('cb_controller', MenuParamHelper::getMenuParam($params, 'cb_controller', null));
                $this->siteApp->input->set('cb_category_id', (int) MenuParamHelper::getMenuParam($params, 'cb_category_id', 0));
            }
        }

        $this->siteApp->input->set('cbIsNew', 0);
        $this->siteApp->input->set('ContentbuilderngHelper::cbinternalCheck', 1);

        $isEdit = (bool) $this->siteApp->input->getCmd('record_id', '');
        if (!$isEdit) {
            $this->siteApp->input->set('cbIsNew', 1);
        }

        if (!$isAdminPreview) {
            if ($isEdit) {
                $this->getPermissionService()->checkPermissions('edit', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_EDIT_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
            } else {
                $this->getPermissionService()->checkPermissions('new', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_NEW_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
            }
        }

        $model = $this->getEditModel(['ignore_request' => true]);
        $id = $model->store();

        $submission_failed = $this->siteApp->input->getBool('cb_submission_failed', false);
        $cb_submit_msg = $this->siteApp->input->set('cb_submit_msg', '');

        $type = 'message';
        if ($id && !$submission_failed) {

            $msg = Text::_('COM_CONTENTBUILDERNG_SAVED');
            $return = NavigationLinkHelper::decodeInternalReturn((string) $this->siteApp->input->get('return', '', 'string'));
            if ($return !== '') {
                $this->siteApp->enqueueMessage($msg, 'message');
                $this->siteApp->redirect($return);
            }

        } else {
            $apply = true; // forcing to stay in form on errors
            $type = 'error';
        }

        if ($isAdminPreview) {
            // In admin preview we keep users on the form page.
            $apply = true;
        }

        $previewQuery = $this->buildPreviewQuery();
        $listQuery = $this->buildListQuery();

        if ($this->siteApp->input->getString('cb_controller', '') == 'edit') {
            $link = Route::_('index.php?option=com_contentbuilderng&title=' . $this->siteApp->input->get('title', '', 'string') . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '') . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '') . '&task=edit.display&return=' . NavigationLinkHelper::encodeInternalReturn((string) $this->siteApp->input->get('return', '', 'string')) . '&Itemid=' . $this->siteApp->input->getInt('Itemid', 0) . $previewQuery, false);
        } else if ($apply) {
            $link = Route::_('index.php?option=com_contentbuilderng&title=' . $this->siteApp->input->get('title', '', 'string') . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '') . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '') . '&task=edit.display&return=' . NavigationLinkHelper::encodeInternalReturn((string) $this->siteApp->input->get('return', '', 'string')) . '&backtolist=' . $this->siteApp->input->getInt('backtolist', 0) . '&id=' . $this->siteApp->input->getInt('id', 0) . '&record_id=' . $id . '&Itemid=' . $this->siteApp->input->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : '') . $previewQuery, false);
        } else {
            $link = Route::_('index.php?option=com_contentbuilderng&title=' . $this->siteApp->input->get('title', '', 'string') . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '') . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '') . '&task=list.display&id=' . $this->siteApp->input->getInt('id', 0) . ($listQuery !== '' ? '&' . $listQuery : '') . '&Itemid=' . $this->siteApp->input->getInt('Itemid', 0), false);
        }
        $this->setRedirect($link, $msg, $type);
    }

    public function apply()
    {
        $this->save(true);
    }

    public function delete()
    {
        $isAdminPreview = $this->applyPreviewContextForAction();
        if (!$isAdminPreview) {
            $this->getPermissionService()->checkPermissions('delete', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_DELETE_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
        }

        $selectedItems = array_values(
            array_filter(
                array_map('intval', (array) $this->input->get('cid', [], 'array')),
                static fn(int $id): bool => $id > 0
            )
        );

        if ($selectedItems === []) {
            $listQuery = $this->buildListQuery();
            $previewQuery = $this->buildPreviewQuery();
            $link = Route::_(
                'index.php?option=com_contentbuilderng&task=list.display&backtolist=1&id='
                . $this->siteApp->input->getInt('id', 0)
                . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '')
                . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '')
                . '&record_id='
                . ($listQuery !== '' ? '&' . $listQuery : '')
                . $previewQuery
                . '&Itemid=' . $this->siteApp->input->getInt('Itemid', 0),
                false
            );
            $this->setRedirect($link, Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');

            return;
        }

        $model = $this->getEditModel(['ignore_request' => true]);
        $ok = true;
        try {
            // The model may not return a strict boolean; treat "no exception" as success.
            $model->delete();
        } catch (\Throwable $e) {
            $ok = false;
            $this->siteApp->enqueueMessage($e->getMessage(), 'warning');
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
                $msg = Text::_('COM_CONTENTBUILDERNG_ENTRY_DELETED');
            }
        } else {
            $msg = Text::_('COM_CONTENTBUILDERNG_ERROR');
        }
        $type = $ok ? 'message' : 'warning';

        // Clear record context to avoid redirects back to details/edit for a deleted record.
        $this->input->set('record_id', 0);
        $this->siteApp->input->set('record_id', 0);

        $listQuery = $this->buildListQuery();
        $previewQuery = $this->buildPreviewQuery();
        $link = Route::_(
            'index.php?option=com_contentbuilderng&task=list.display&backtolist=1&id='
            . $this->siteApp->input->getInt('id', 0)
            . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '')
            . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '')
            . '&record_id='
            . ($listQuery !== '' ? '&' . $listQuery : '')
            . $previewQuery
            . '&Itemid=' . $this->siteApp->input->getInt('Itemid', 0),
            false
        );

        $this->setRedirect($link, $msg, $type);
    }

    public function state()
    {
        $isAdminPreview = $this->applyPreviewContextForAction();
        if (!$isAdminPreview) {
            if (!$this->checkPermissionForAjax('state', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_STATE_CHANGE_NOT_ALLOWED'))) {
                return;
            }
        }

        $model = $this->getEditModel(['ignore_request' => true]);
        $changedCount = (int) $model->change_list_states();
        $msg = Text::_($changedCount === 1 ? 'COM_CONTENTBUILDERNG_STATE_CHANGED' : 'COM_CONTENTBUILDERNG_STATES_CHANGED');

        if ($this->isAjaxCall()) {
            $this->respondAjax(true, $msg);
            return;
        }

        $listQuery = $this->buildListQuery();
        $link = Route::_('index.php?option=com_contentbuilderng&task=list.display&id=' . $this->siteApp->input->getInt('id', 0) . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '') . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '') . ($listQuery !== '' ? '&' . $listQuery : '') . '&Itemid=' . $this->siteApp->input->getInt('Itemid', 0), false);
        $this->setRedirect($link, $msg, 'message');
    }

    public function publish()
    {
        $storageId = (int) $this->siteApp->input->getInt('storage_id', 0);
        $isDirectStorageMode = $storageId > 0 && $this->siteApp->input->getInt('id', 0) <= 0;
        $isAdminPreview = $this->applyPreviewContextForAction();

        if ($isDirectStorageMode && !$isAdminPreview) {
            if (!$this->siteApp->getIdentity()->authorise('core.edit.state', 'com_contentbuilderng')) {
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_PUBLISHING_NOT_ALLOWED'));
                    return;
                }

                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_PUBLISHING_NOT_ALLOWED'), 403);
            }
        } elseif (!$isAdminPreview) {
            if (!$this->checkPermissionForAjax('publish', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_PUBLISHING_NOT_ALLOWED'))) {
                return;
            }
        }

        $model = $this->getEditModel(['ignore_request' => true]);
        $model->setIds($this->siteApp->input->getInt('id', 0), $this->siteApp->input->getCmd('record_id', 0));
        $model->change_list_publish();
        if ($this->siteApp->input->getInt('list_publish', 0)) {
            $msg = Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED');
        } else {
            $msg = Text::_('COM_CONTENTBUILDERNG_UNPUBLISHED');
        }

        if ($this->isAjaxCall()) {
            $this->respondAjax(true, $msg);
            return;
        }

        $listQuery = $this->buildListQuery();
        $previewQuery = $this->buildPreviewQuery();
        $link = Route::_(
            'index.php?option=com_contentbuilderng&task=list.display&'
            . ($isDirectStorageMode ? 'storage_id=' . $storageId : 'id=' . $this->siteApp->input->getInt('id', 0))
            . ($listQuery !== '' ? '&' . $listQuery : '')
            . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '')
            . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '')
            . $previewQuery
            . '&Itemid=' . $this->siteApp->input->getInt('Itemid', 0),
            false
        );
        $this->setRedirect($link, $msg, 'message');
    }

    public function language()
    {
        $isAdminPreview = $this->applyPreviewContextForAction();
        if (!$isAdminPreview) {
            if (!$this->checkPermissionForAjax('language', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_CHANGE_LANGUAGE_NOT_ALLOWED'))) {
                return;
            }
        }

        $model = $this->getEditModel(['ignore_request' => true]);
        $model->change_list_language();
        $msg = Text::_('COM_CONTENTBUILDERNG_LANGUAGE_CHANGED');
        $listQuery = $this->buildListQuery();
        $link = Route::_('index.php?option=com_contentbuilderng&task=list.display&id=' . $this->siteApp->input->getInt('id', 0) . ($listQuery !== '' ? '&' . $listQuery : '') . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '') . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '') . '&Itemid=' . $this->siteApp->input->getInt('Itemid', 0), false);
        $this->setRedirect($link, $msg, 'message');
    }

    private function buildListQuery(): string
    {
        $state = $this->resolveListState();

        return http_build_query(['list' => [
            'limit' => $state['limit'],
            'start' => $state['start'],
            'ordering' => $state['ordering'],
            'direction' => $state['direction'],
        ]]);
    }

    private function resolveListState(): array
    {
        $option = 'com_contentbuilderng';
        $list = (array) $this->input->get('list', [], 'array');
        $stateKeyPrefix = $this->getPaginationStateKeyPrefix();
        $limitKey = $stateKeyPrefix . '.limit';
        $startKey = $stateKeyPrefix . '.start';
        $configuredLimit = MenuParamHelper::getConfiguredListLimit($this->siteApp, (int) $this->input->getInt('id', 0));
        $explicitLimitRequest = MenuParamHelper::hasExplicitListLimitRequest();

        $limit = $explicitLimitRequest && isset($list['limit']) ? (int) $list['limit'] : 0;
        if ($limit === 0) {
            $limit = $configuredLimit;
        }
        if ($limit === 0) {
            $limit = (int) $this->siteApp->getUserState($limitKey, 0);
        }
        if ($limit === 0) {
            $limit = (int) $this->siteApp->get('list_limit');
        }
        if ($limit < 1) {
            $limit = 20;
        }

        if ($explicitLimitRequest && array_key_exists('start', $list)) {
            $start = max(0, (int) $list['start']);
        } elseif ($configuredLimit > 0) {
            $start = 0;
        } else {
            $start = (int) $this->siteApp->getUserState($startKey, 0);
        }

        $ordering = isset($list['ordering']) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $list['ordering']) : '';
        if ($ordering === '') {
            $ordering = (string) $this->siteApp->getUserState($option . 'formsd_filter_order', '');
        }

        $direction = isset($list['direction']) ? strtolower((string) $list['direction']) : '';
        if ($direction === '') {
            $direction = (string) $this->siteApp->getUserState($option . 'formsd_filter_order_Dir', '');
        }
        if ($ordering === '' && isset($list['fullordering'])) {
            $parts = preg_split('/\s+/', trim((string) $list['fullordering']));
            $ordering = isset($parts[0]) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $parts[0]) : '';
            $direction = isset($parts[1]) ? strtolower((string) $parts[1]) : $direction;
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
        
        $option = 'com_contentbuilderng';

        $formId = (int) $this->input->getInt('id', 0);
        if ($formId < 1) {
            $menu = $this->siteApp->getMenu()->getActive();
            if ($menu) {
                $formId = (int) MenuParamHelper::getMenuParam($menu->getParams(), 'form_id', 0);
            }
        }

        $layout = (string) $this->input->getCmd('layout', 'default');
        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $this->input->getInt('Itemid', 0);

        return $option . '.liststate.' . $formId . '.' . $layout . '.' . $itemId;
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
        $userId = (int) $this->input->getInt('cb_preview_user_id', 0);
        $adminReturn = trim((string) $this->input->getCmd('cb_admin_return', ''));

        if ($userId < 1) {
            return '';
        }

        return PreviewLinkHelper::buildQuery($until, $actorId, $actorName, $userId, $sig, $adminReturn);
    }

    private function isAjaxCall(): bool
    {
        return (bool) $this->input->getInt('cb_ajax', 0);
    }

    private function respondAjax(bool $success, string $message = ''): void
    {
        echo new JsonResponse(['ok' => $success], $message, !$success);
        $this->siteApp->close();
    }

    public function display($cachable = false, $urlparams = array())
    {
        $app   = $this->siteApp;

        $suffix = '_fe';
        $storageId = (int) $this->input->getInt('storage_id', 0);
        $isDirectStorageMode = $storageId > 0 && $this->input->getInt('id', 0) <= 0;

        // 1) d'abord depuis l'URL
        $formId   = $this->input->getInt('id', 0);
        $recordId = $this->input->getInt('record_id', 0);

        // 2) sinon depuis les params du menu actif
        if (!$formId) {
            $menu = $this->siteApp->getMenu()->getActive();
            if ($menu) {
                $formId = (int) MenuParamHelper::getMenuParam($menu->getParams(), 'form_id', 0);
            }
        }

        // Keep both input bags aligned for downstream model/view access.
        $this->input->set('id', $formId);
        $this->siteApp->input->set('id', $formId);
        $this->input->set('view', 'edit');

        if ($recordId) {
            $this->input->set('record_id', $recordId);
            $this->siteApp->input->set('record_id', $recordId);
        }

        // Contexte CB correct pour cette page
        $this->siteApp->input->set('view', 'edit');

        // Permissions
        $isAdminPreview = $isDirectStorageMode
            ? $this->isValidAdminPreviewRequest(0, $storageId)
            : $this->isValidAdminPreviewRequest($formId);
        $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        $this->siteApp->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);

        if ($isDirectStorageMode && $isAdminPreview) {
            $this->getPermissionService()->setStoragePreviewPermissions($storageId, $this->frontend ? '_fe' : '');
        } elseif (!$isDirectStorageMode) {
            $this->getPermissionService()->setPermissions($formId, $recordId, $suffix);
        }
        if (!$isAdminPreview) {
            if ($this->siteApp->input->getCmd('record_id', '')) {
                $this->getPermissionService()->checkPermissions('edit', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_EDIT_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
            } else {
                $this->getPermissionService()->checkPermissions('new', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_NEW_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
            }
        }

        $this->siteApp->input->set('tmpl', $this->siteApp->input->getWord('tmpl', null));
        $this->siteApp->input->set('layout', $this->siteApp->input->getWord('layout', null) == 'latest' ? null : $this->siteApp->input->getWord('layout', null));
        $this->siteApp->input->set('view', 'edit');

        parent::display();
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

        $targets = [];
        if ($formId > 0) {
            $targets[] = (string) $formId;
        }
        if ($storageId > 0) {
            $targets[] = 'storage:' . $storageId;
        }

        foreach ($targets as $target) {
            $payload = PreviewLinkHelper::buildPayload($target, $until, $actorId, $actorName, $userId);
            if (hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
                $this->input->set('cb_preview_actor_id', $actorId);
                $this->input->set('cb_preview_actor_name', $actorName);
                $this->siteApp->input->set('cb_preview_actor_id', $actorId);
                $this->siteApp->input->set('cb_preview_actor_name', $actorName);
                return true;
            }
        }

        return false;
    }
}
