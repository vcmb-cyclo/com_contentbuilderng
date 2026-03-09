<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;

class DetailsController extends BaseController
{
    protected $default_view = 'details';

    // ✅ IMPORTANT : force le prefix PSR-4 des vues
    protected $viewPrefix = 'CB\\Component\\Contentbuilderng\\Site\\View';

    private SiteApplication $siteApp;
    private bool $frontend;
    private $_show_back_button = true;

    public function __construct(
        $config,
        MVCFactoryInterface $factory,
        CMSApplicationInterface $app,
        Input $input) {

            // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory, $app, $input);

        if (!$app instanceof SiteApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        $this->siteApp = $app;
        $this->frontend = $this->siteApp->isClient('site');

        if ($this->frontend && $this->siteApp->input->getInt('Itemid', 0)) {

            // try menu item
            $menu = $this->siteApp->getMenu();
            $item = $menu->getActive();
            if (is_object($item)) {
                if ($item->getParams()->get('record_id', null) !== null) {
                    $this->siteApp->input->set('record_id', $item->getParams()->get('record_id', null));
                    $this->_show_back_button = $item->getParams()->get('show_back_button', null);
                }
            }
        }

        if ($this->siteApp->input->getWord('view', '') == 'latest') {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $db->setQuery('Select `type`, `reference_id` From #__contentbuilderng_forms Where id = ' . intval($this->siteApp->input->getInt('id', 0)) . ' And published = 1');
            $form = $db->loadAssoc();
            $form = FormSourceFactory::getForm($form['type'], $form['reference_id']);

            $labels = $form->getElementLabels();
            $ids = array();
            foreach ($labels as $reference_id => $label) {
                $ids[] = $db->quote($reference_id);
            }

            if (count($ids)) {
                $db->setQuery("Select Distinct `label`, reference_id From #__contentbuilderng_elements Where form_id = " . intval($this->siteApp->input->getInt('id', 0)) . " And reference_id In (" . implode(',', $ids) . ") And published = 1 Order By ordering");
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
                $this->siteApp->input->set('record_id', $record_id);
            }

            if (!$this->siteApp->input->getCmd('record_id', '')) {
                $this->siteApp->input->set('cbIsNew', 1);
                ContentbuilderLegacyHelper::setPermissions($this->siteApp->input->getInt('id', 0), 0, $this->frontend ? '_fe' : '');
                $auth = $this->frontend ? ContentbuilderLegacyHelper::authorizeFe('new') : ContentbuilderLegacyHelper::authorize('new');

                if ($auth) {
                    $state = $this->resolveListState();
                    $listQuery = http_build_query(['list' => [
                        'limit' => $state['limit'],
                        'start' => $state['start'],
                        'ordering' => $state['ordering'],
                        'direction' => $state['direction'],
                    ]]);

                    $this->siteApp->redirect(Route::_('index.php?option=com_contentbuilderng&task=edit.display&latest=1&backtolist=' . $this->siteApp->input->getInt('backtolist', 0) . '&id=' . $this->siteApp->input->getInt('id', 0) . ($this->siteApp->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $this->siteApp->input->get('tmpl', '', 'string') : '') . ($this->siteApp->input->get('layout', '', 'string') != '' ? '&layout=' . $this->siteApp->input->get('layout', '', 'string') : '') . '&record_id=' . ($listQuery !== '' ? '' : '') . ($listQuery !== '' ? '&' . $listQuery : ''), false));
                } else {
                    $this->siteApp->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_ADD_ENTRY_FIRST'));
                    $this->siteApp->redirect(Route::_('index.php'));
                }
            }
        }

        if ($this->siteApp->input->getInt('storage_id', 0) <= 0 || $this->siteApp->input->getInt('id', 0) > 0) {
            ContentbuilderLegacyHelper::setPermissions($this->siteApp->input->getInt('id', 0), $this->siteApp->input->getCmd('record_id', 0), $this->frontend ? '_fe' : '');
        }
    }

    function display($cachable = false, $urlparams = array())
    {
        $this->input->set('view', 'details');
        $storageId = $this->input->getInt('storage_id', 0);
        $isDirectStorageMode = $storageId > 0 && $this->input->getInt('id', 0) <= 0;

        // Si tu gardes le suffixe pour compat legacy :
        //$frontend = $this->siteApp->isClient('site');
        $suffix = '_fe';

        // 1) d'abord depuis l'URL
        $form_id = $this->input->getInt('id', 0);

        // 2) sinon depuis les params du menu actif
        if (!$form_id) {
            $menu = $this->siteApp->getMenu()->getActive();
            if ($menu) {
                $form_id = (int) $menu->getParams()->get('form_id', 0);
            }
        }

        // Synchroniser l'input pour les appels legacy encore présents.
        $this->input->set('id', $form_id);
        $this->siteApp->input->set('id', $form_id);

        $recordId = (int) $this->input->getInt('record_id', 0);
        if (!$recordId) {
            $menu = $this->siteApp->getMenu()->getActive();
            if ($menu) {
                $recordId = (int) $menu->getParams()->get('record_id', 0);
            }
        }
        if ($recordId) {
            $this->input->set('record_id', $recordId);
            $this->siteApp->input->set('record_id', $recordId);
        }

        if (!$isDirectStorageMode) {
            ContentbuilderLegacyHelper::setPermissions($form_id, $recordId, $suffix);
        }
        $isAdminPreview = !$isDirectStorageMode && $this->isValidAdminPreviewRequest($form_id);
        $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        $this->siteApp->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        if (!$isDirectStorageMode && !$isAdminPreview) {
            ContentbuilderLegacyHelper::checkPermissions('view', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
        }

        $this->siteApp->input->set('tmpl', $this->siteApp->input->getWord('tmpl', null));
        $this->siteApp->input->set('layout', $this->siteApp->input->getWord('layout', null) == 'latest' ? null : $this->siteApp->input->getWord('layout', null));
        if ($this->siteApp->input->getWord('view', '') == 'latest') {
            $this->siteApp->input->set('cb_latest', 1);
        }

        parent::display();
    }

    private function resolveListState(): array
    {
        $app = $this->siteApp;
        $option = 'com_contentbuilderng';
        $list = (array) $app->input->get('list', [], 'array');
        $stateKeyPrefix = $this->getPaginationStateKeyPrefix();
        $limitKey = $stateKeyPrefix . '.limit';
        $startKey = $stateKeyPrefix . '.start';

        $limit = isset($list['limit']) ? $app->input->getInt('list[limit]', 0) : 0;
        if ($limit === 0) {
            $limit = (int) $app->getUserState($limitKey, 0);
        }
        if ($limit === 0) {
            $limit = (int) $app->get('list_limit');
        }

        if (array_key_exists('start', $list)) {
            $start = max(0, $app->input->getInt('list[start]', 0));
        } else {
            $start = (int) $app->getUserState($startKey, 0);
        }

        $ordering = isset($list['ordering']) ? $app->input->getCmd('list[ordering]', '') : (string) $app->getUserState($option . 'formsd_filter_order', '');
        $direction = isset($list['direction']) ? $app->input->getCmd('list[direction]', '') : (string) $app->getUserState($option . 'formsd_filter_order_Dir', '');

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

        $formId = (int) $app->input->getInt('id', 0);
        if ($formId < 1) {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                $formId = (int) $menu->getParams()->get('form_id', 0);
            }
        }

        $layout = (string) $app->input->getCmd('layout', 'default');
        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $app->input->getInt('Itemid', 0);

        return $option . '.liststate.' . $formId . '.' . $layout . '.' . $itemId;
    }

    /**
     * Validates a short-lived preview signature generated in admin toolbar.
     */
    private function isValidAdminPreviewRequest(int $formId): bool
    {
        if ($formId < 1 || !$this->input->getBool('cb_preview', false)) {
            return false;
        }

        $until = (int) $this->input->getInt('cb_preview_until', 0);
        $sig   = (string) $this->input->getString('cb_preview_sig', '');
        $actorId = (int) $this->input->getInt('cb_preview_actor_id', 0);
        $actorName = trim((string) $this->input->getString('cb_preview_actor_name', ''));

        if ($until < time() || $sig === '') {
            return false;
        }

        $secret = (string) $this->siteApp->get('secret');
        if ($secret === '') {
            return false;
        }

        $payload  = $formId . '|' . $until;
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
