<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Service\DirectStorageFormProvisioningService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;

class DetailsController extends BaseController
{
    protected $default_view = 'details';

    // ✅ IMPORTANT : force le prefix PSR-4 des vues
    protected $viewPrefix = 'CB\\Component\\Contentbuilderng\\Site\\View';

    private SiteApplication $siteApp;
    private bool $frontend;
    private $_show_back_button = true;

    private function getDatabase(): DatabaseInterface
    {
        return RuntimeContextHelper::getDatabase();
    }

    private function getPermissionService(): PermissionService
    {
        return PermissionService::createFromRuntimeContext();
    }

    private function getDirectStorageFormProvisioningService(): DirectStorageFormProvisioningService
    {
        $component = RuntimeContextHelper::getApplication()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component->getContainer()->get(DirectStorageFormProvisioningService::class);
    }

    public function __construct(
        array $config = [], 
        ?MVCFactoryInterface $factory = null, 
        ?CMSWebApplicationInterface $app = null, 
        ?Input $input = null) {

            // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory, $app, $input);

        if (!$app instanceof SiteApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        $this->siteApp = $app;
        $this->frontend = $this->siteApp->isClient('site');

        if ($this->frontend && $this->siteApp->getInput()->getInt('Itemid', 0)) {

            // try menu item
            $menu = $this->siteApp->getMenu();
            $item = $menu->getActive();
            if (is_object($item)) {
                $params = $item->getParams();
                $menuRecordId = MenuParamHelper::getMenuParam($params, 'record_id', null);

                if ($menuRecordId !== null) {
                    $this->siteApp->getInput()->set('record_id', $menuRecordId);
                    $this->_show_back_button = MenuParamHelper::getResolvedMenuToggle(
                        $params,
                        'cb_show_details_back_button',
                        1
                    ) === 1;
                }
            }
        }

        if ($this->siteApp->getInput()->getWord('view', '') == 'latest') {
            $db = $this->getDatabase();

            $formId = $this->siteApp->getInput()->getInt('id', 0);
            $query = $db->getQuery(true)
                ->select($db->quoteName(['type', 'reference_id']))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('id') . ' = ' . $formId)
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($query);
            $form = $db->loadAssoc();

            if (!is_array($form)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
            }

            $form = FormSourceFactory::getForm($form['type'], $form['reference_id']);

            $labels = $form->getElementLabels();
            $ids = array();
            foreach ($labels as $reference_id => $label) {
                $ids[] = $db->quote($reference_id);
            }

            if (count($ids)) {
                $query = $db->getQuery(true)
                    ->select('DISTINCT ' . implode(', ', $db->quoteName(['label', 'reference_id'])))
                    ->from($db->quoteName('#__contentbuilderng_elements'))
                    ->where($db->quoteName('form_id') . ' = ' . $formId)
                    ->where($db->quoteName('reference_id') . ' IN (' . implode(',', $ids) . ')')
                    ->where($db->quoteName('published') . ' = 1')
                    ->order($db->quoteName('ordering'));
                $db->setQuery($query);
                $rows = $db->loadAssocList();
                $ids = array();
                foreach ($rows as $row) {
                    $ids[] = $row['reference_id'];
                }
            }

            $rec = $form->getListRecords($ids, '', array(), 0, 1, '', array(), 'desc', 0, false, (int) ($this->siteApp->getIdentity()->id ?? 0), 0, -1, -1, -1, -1, array(), true, null);

            if (count($rec) > 0) {
                $rec = $rec[0];
                $rec2 = $form->getRecord($rec->colRecord, false, -1, true);

                $record_id = $rec->colRecord;
                $this->siteApp->getInput()->set('record_id', $record_id);
            }

            if (!$this->siteApp->getInput()->getCmd('record_id', '')) {
                $this->siteApp->getInput()->set('cbIsNew', 1);
                $this->getPermissionService()->setPermissions($this->siteApp->getInput()->getInt('id', 0), 0, $this->frontend ? '_fe' : '');
                $auth = $this->frontend ? $this->getPermissionService()->authorizeFe('new') : $this->getPermissionService()->authorize('new');

                if ($auth) {
                    $state = $this->resolveListState();
                    $listQuery = http_build_query(['list' => [
                        'limit' => $state['limit'],
                        'start' => $state['start'],
                        'ordering' => $state['ordering'],
                        'direction' => $state['direction'],
                    ]]);

                    $this->siteApp->redirect(Route::_('index.php?option=com_contentbuilderng&task=edit.display&latest=1&backtolist=' . $this->siteApp->getInput()->getInt('backtolist', 0) . '&id=' . $this->siteApp->getInput()->getInt('id', 0) . ($this->siteApp->getInput()->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->getInput()->get('tmpl', '', 'string') : '') . ($this->siteApp->getInput()->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->getInput()->get('layout', '', 'string') : '') . '&record_id=' . ($listQuery !== '' ? '' : '') . ($listQuery !== '' ? '&' . $listQuery : ''), false));
                } else {
                    $this->siteApp->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_ADD_ENTRY_FIRST'));
                    $this->siteApp->redirect(Route::_('index.php'));
                }
            }
        }

        if ($this->siteApp->getInput()->getInt('storage_id', 0) <= 0 || $this->siteApp->getInput()->getInt('id', 0) > 0) {
            $this->getPermissionService()->setPermissions($this->siteApp->getInput()->getInt('id', 0), $this->siteApp->getInput()->getCmd('record_id', 0), $this->frontend ? '_fe' : '');
        }
    }

    function display($cachable = false, $urlparams = array())
    {
        $this->input->set('view', 'details');
        $storageId = $this->input->getInt('storage_id', 0);
        $isDirectStorageMode = $storageId > 0 && $this->input->getInt('id', 0) <= 0;

        $suffix = '_fe';

        // 1) d'abord depuis l'URL
        $form_id = $this->input->getInt('id', 0);

        if ($isDirectStorageMode) {
            // Vue directe d'un storage : résout (ou crée à la volée, avec ses
            // éléments et ses templates par défaut) le #__contentbuilderng_forms
            // lié, pour que le reste du pipeline (DetailsModel, etc.) fonctionne
            // comme pour un formulaire classique.
            $form_id = $this->getDirectStorageFormProvisioningService()->resolveOrCreateFormId($storageId);
        } elseif (!$form_id) {
            // 2) sinon depuis les params du menu actif
            $menu = $this->siteApp->getMenu()->getActive();
            if ($menu) {
                $form_id = (int) MenuParamHelper::getMenuParam($menu->getParams(), 'form_id', 0);
            }
        }

        // Keep both input bags aligned for downstream model/view access.
        // (En mode storage direct, DetailsModel détermine lui-même son
        // directStorageMode via storage_id/id : ne pas y écrire le formId
        // résolu ici, sous peine de le faire basculer sur le chemin
        // "formulaire classique" et de perdre le rendu direct storage.)
        if (!$isDirectStorageMode) {
            $this->input->set('id', $form_id);
            $this->siteApp->getInput()->set('id', $form_id);
        }

        $recordId = (int) $this->input->getInt('record_id', 0);
        if (!$recordId) {
            $menu = $this->siteApp->getMenu()->getActive();
            if ($menu) {
                $recordId = (int) MenuParamHelper::getMenuParam($menu->getParams(), 'record_id', 0);
            }
        }
        if ($recordId) {
            $this->input->set('record_id', $recordId);
            $this->siteApp->getInput()->set('record_id', $recordId);
        }

        // En mode storage direct, $form_id pointe désormais vers un vrai
        // #__contentbuilderng_forms (résolu/créé plus haut) : les droits
        // configurés dessus (admin > Formulaires) doivent donc être chargés
        // comme pour n'importe quel formulaire classique.
        $this->getPermissionService()->setPermissions($form_id, $recordId, $suffix);

        $isAdminPreview = $isDirectStorageMode
            ? $this->isValidAdminPreviewRequest(0, $storageId)
            : $this->isValidAdminPreviewRequest($form_id);
        $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        $this->siteApp->getInput()->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        if (!$isAdminPreview && $this->siteApp->getInput()->getBool('cb_preview', false)) {
            // Lien de prévisualisation présent mais invalide/expiré : prévenir
            // plutôt que de perdre silencieusement le bandeau "Prévisualisation".
            $this->siteApp->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_PREVIEW_LINK_EXPIRED'), 'warning');
        }
        if ($isDirectStorageMode && $isAdminPreview) {
            $this->getPermissionService()->setStoragePreviewPermissions($storageId, $suffix);
        }
        if (!$isAdminPreview) {
            $this->getPermissionService()->checkPermissions('view', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
        }

        $this->siteApp->getInput()->set('tmpl', $this->siteApp->getInput()->getWord('tmpl', null));
        $this->siteApp->getInput()->set('layout', $this->siteApp->getInput()->getWord('layout', null) == 'latest' ? null : $this->siteApp->getInput()->getWord('layout', null));
        if ($this->siteApp->getInput()->getWord('view', '') == 'latest') {
            $this->siteApp->getInput()->set('cb_latest', 1);
        }

        parent::display();
    }

    private function resolveListState(): array
    {
        $app = $this->siteApp;
        $option = 'com_contentbuilderng';
        $list = (array) $app->getInput()->get('list', [], 'array');
        $stateKeyPrefix = $this->getPaginationStateKeyPrefix();
        $limitKey = $stateKeyPrefix . '.limit';
        $startKey = $stateKeyPrefix . '.start';
        $configuredLimit = MenuParamHelper::getConfiguredListLimit($app, (int) $app->getInput()->getInt('id', 0));
        $explicitLimitRequest = MenuParamHelper::hasExplicitListLimitRequest($this->siteApp);

        $limit = $explicitLimitRequest && isset($list['limit']) ? (int) $list['limit'] : 0;
        if ($limit === 0) {
            $limit = $configuredLimit;
        }
        if ($limit === 0) {
            $limit = (int) $app->getUserState($limitKey, 0);
        }
        if ($limit === 0) {
            $limit = (int) $app->get('list_limit');
        }
        if ($limit < 1) {
            $limit = 20;
        }

        if ($explicitLimitRequest && array_key_exists('start', $list)) {
            $start = max(0, (int) $list['start']);
        } elseif ($configuredLimit > 0) {
            $start = 0;
        } else {
            $start = (int) $app->getUserState($startKey, 0);
        }

        $ordering = isset($list['ordering']) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $list['ordering']) : (string) $app->getUserState($option . 'formsd_filter_order', '');
        $direction = isset($list['direction']) ? strtolower((string) $list['direction']) : (string) $app->getUserState($option . 'formsd_filter_order_Dir', '');
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
        $app = $this->siteApp;
        $option = 'com_contentbuilderng';

        $formId = (int) $app->getInput()->getInt('id', 0);
        if ($formId < 1) {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                $formId = (int) MenuParamHelper::getMenuParam($menu->getParams(), 'form_id', 0);
            }
        }

        $layout = (string) $app->getInput()->getCmd('layout', 'default');
        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $app->getInput()->getInt('Itemid', 0);

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
                $this->siteApp->getInput()->set('cb_preview_actor_id', $actorId);
                $this->siteApp->getInput()->set('cb_preview_actor_name', $actorName);
                return true;
            }
        }

        return false;
    }
}
