<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2024-2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSWebApplication;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\MVC\Model\ListModel as BaseListModel;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\PublishedRecordVisibilityHelper;
use CB\Component\Contentbuilderng\Administrator\Service\FormSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\RuntimeUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\ListSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\TemplateRenderService;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;

class ListModel extends BaseListModel
{
    private readonly ListSupportService $listSupportService;
    private readonly RuntimeUtilityService $runtimeUtilityService;
    private readonly TemplateRenderService $templateRenderService;

    protected int $_id = 0;

    protected ?array $_data = null; // si tu veux être propre avec _data aussi

    protected bool $directStorageMode = false;

    protected int $directStorageId = 0;

    /**
     * Items total
     * @var integer
     */
    private $_total = null;

    private $_menu_item = false;

    private $frontend = true;

    private $_menu_filter = array();

    private $_menu_filter_order = array();

    private $_show_page_heading = true;

    private $_page_class = '';

    private $_page_title = '';

    private $_page_heading = '';

    /** @var CMSWebApplication */
    private $app;

    function  __construct($config)
    {
        parent::__construct($config);

        /** @var CMSWebApplication $app */
        $app = Factory::getApplication();
        $this->app = $app;
        $this->listSupportService = new ListSupportService();
        $this->runtimeUtilityService = new RuntimeUtilityService();
        $this->templateRenderService = new TemplateRenderService();

        $this->frontend = $app->isClient('site');
        $option = 'com_contentbuilderng';

        if ($this->frontend) {
            $wa = $app->getDocument()->getWebAssetManager();

            // Charge le manifeste joomla.asset.json du composant
            $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');

            // Utilise la feuille de style déclarée
            $wa->useStyle('com_contentbuilderng.system');
        }

        if ($app->input->getInt('Itemid', 0)) {
            $this->_menu_item = true;
        }

        $id = $app->input->getInt('id', 0);
        $this->directStorageId = max(0, $app->input->getInt('storage_id', 0));

        if (!$id && $this->frontend) {
            $menu = $app->getMenu();
            $item = $menu->getActive();

            if ($item) {
                $id = (int) MenuParamHelper::getMenuParam($item->getParams(), 'form_id', 0);
            }
        }

        $this->setId($id);
        $this->directStorageMode = $this->_id <= 0 && $this->directStorageId > 0;

        if (!$this->_id && !$this->directStorageMode) {
            throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        $list = (array) $app->input->get('list', [], 'array');
        $listOrdering = isset($list['ordering']) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $list['ordering']) : '';
        $listDirection = isset($list['direction']) ? strtolower((string) $list['direction']) : '';
        $listFullordering = isset($list['fullordering']) ? trim((string) $list['fullordering']) : '';

        // Joomla native fullordering takes precedence when present.
        if ($listFullordering !== '' && $listOrdering === '') {
            $parts = preg_split('/\s+/', $listFullordering);
            $listOrdering = isset($parts[0]) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $parts[0]) : '';
            $listDirection = isset($parts[1]) ? strtolower((string) $parts[1]) : $listDirection;
        }

        $previousScreenKey = (string) $app->getSession()->get($option . 'formsd_screen', '');
        $currentScreenKey = $this->getCurrentListScreenKey();
        $screenSwitched = $previousScreenKey !== '' && $previousScreenKey !== $currentScreenKey;
        $filterLanguageStateKey = $this->getScopedListStateKey('filter_language');

        // Hard reset when moving from one CB list screen/menu to another.
        // This avoids bringing back prior filter/sort state that would mask
        // menu-specific settings such as hidden filters or initial limits.
        if ($screenSwitched) {
            $this->resetStateOnFormSwitch();
        }

        if (!$screenSwitched) {
            $filter_order     = (string) $app->getUserState($option . 'formsd_filter_order', '');
            $filter_order_Dir = (string) $app->getUserState($option . 'formsd_filter_order_Dir', '');
            $filter           = $app->getUserStateFromRequest($option . 'formsd_filter', 'filter', '', 'string');
            $filter_state     = $app->getUserStateFromRequest($option . 'formsd_filter_state', 'list_state_filter', 0, 'int');
            $filter_publish   = $app->getUserStateFromRequest($option . 'formsd_filter_publish', 'list_publish_filter', -1, 'int');
            $filter_language  = $app->getUserStateFromRequest($option . 'formsd_filter_language', 'list_language_filter', '', 'cmd');
        } else {
            $filter_order     = $listOrdering;
            $filter_order_Dir = $listDirection;
            $filter           = $app->input->get('filter', '', 'string');
            $filter_state     = $app->input->getInt('list_state_filter', 0);
            $filter_publish   = $app->input->getInt('list_publish_filter', -1);
            $filter_language  = $app->input->getCmd('list_language_filter', '');
        }

        // Keep language filter state per FE screen (form/layout/menu item).
        // This avoids leaking language selection between different list screens.
        if ($app->input->get('list_language_filter', null) === null) {
            $filter_language = (string) $app->getUserState($filterLanguageStateKey, (string) $filter_language);
        }

        // Joomla 6 native list state takes precedence when present.
        if ($listOrdering !== '') {
            $filter_order = $listOrdering;
            $app->setUserState($option . 'formsd_filter_order', $filter_order);
        }
        if ($listDirection !== '') {
            $filter_order_Dir = $listDirection;
            $app->setUserState($option . 'formsd_filter_order_Dir', $filter_order_Dir);
        }

        // Keep list state keys aligned when switching views/forms.
        // Without this, a previous form filter can be restored on pagination.
        $app->setUserState($option . 'formsd_filter', (string) $filter);
        $app->setUserState($option . 'formsd_filter_state', (int) $filter_state);
        $app->setUserState($option . 'formsd_filter_publish', (int) $filter_publish);
        $app->setUserState($option . 'formsd_filter_language', (string) $filter_language);
        $app->setUserState($filterLanguageStateKey, (string) $filter_language);
        $app->setUserState($option . 'formsd_filter_order', (string) $filter_order);
        $app->setUserState($option . 'formsd_filter_order_Dir', (string) $filter_order_Dir);

        $this->setState('formsd_filter_state', $filter_state);
        $this->setState('formsd_filter_publish', $filter_publish);
        $this->setState('formsd_filter_language', empty($filter_language) ? null : $filter_language);
        $this->setState('formsd_filter', $filter);
        $this->setState('formsd_filter_order', $filter_order);
        $this->setState('formsd_filter_order_Dir', $filter_order_Dir);

        if ($this->frontend && $app->input->getInt('Itemid', 0)) {

            // try menu item
            $menu = $app->getMenu();
            $item = $menu->getActive();
            if (is_object($item)) {
                if ($item->getParams()->get('show_page_heading', null) !== null) {
                    $this->_show_page_heading = MenuParamHelper::resolvePageHeadingToggle(
                        $item->getParams()->get('show_page_heading', null),
                        $this->_show_page_heading ? 1 : 0
                    );
                }
                if ($item->getParams()->get('page_title', null) !== null) {
                    $this->_page_title = $item->getParams()->get('page_title', null);
                }
                if ($item->getParams()->get('page_heading', null) !== null) {
                    $this->_page_heading = $item->getParams()->get('page_heading', null);
                }
                if ($item->getParams()->get('pageclass_sfx', null) !== null) {
                    $this->_page_class = $item->getParams()->get('pageclass_sfx', null);
                }
            }
        }

        $menu_filter = $app->input->get('cb_list_filterhidden', null, 'raw');
        if (($menu_filter === null || $menu_filter === '') && $app->isClient('site')) {
            $activeMenu = $app->getMenu()->getActive();
            if ($activeMenu) {
                $menu_filter = MenuParamHelper::getMenuParam($activeMenu->getParams(), 'cb_list_filterhidden', null);
            }
        }

        if ($menu_filter !== null) {
            $lines  = explode("\n", $menu_filter);
            foreach ($lines as $line) {
                $keyval = explode("\t", $line);
                if (count($keyval) == 2) {
                    $keyval[1] = $this->runtimeUtilityService->sanitizeHiddenFilterValue($keyval[1]);
                    if ($keyval[1] != '') {
                        $this->_menu_filter[$keyval[0]] = explode('|', $keyval[1]);
                    }
                }
            }
        }

        $menu_filter_order = $app->input->get('cb_list_orderhidden', null, 'raw');
        if (($menu_filter_order === null || $menu_filter_order === '') && $app->isClient('site')) {
            $activeMenu = $app->getMenu()->getActive();
            if ($activeMenu) {
                $menu_filter_order = MenuParamHelper::getMenuParam($activeMenu->getParams(), 'cb_list_orderhidden', null);
            }
        }

        if ($menu_filter_order !== null) {
            $lines  = explode("\n", $menu_filter_order);
            foreach ($lines as $line) {
                $keyval = explode("\t", $line);
                if (count($keyval) == 2) {
                    $keyval[1] = str_replace(array("\n", "\r"), "", $keyval[1]);
                    if ($keyval[1] != '') {
                        $this->_menu_filter_order[$keyval[0]] = intval($keyval[1]);
                    }
                }
            }
        }

        @natsort($this->_menu_filter_order);

        $app->getSession()->set($option . 'formsd_id', $this->directStorageMode ? 0 : $this->_id);
        $app->getSession()->set($option . 'formsd_screen', $currentScreenKey);
    }

    function setId($id)
    {
        // Set id and wipe data
        $this->_id      = $id;
        $this->_data    = null;
    }

    protected function isDirectStorageMode(): bool
    {
        return $this->directStorageMode && $this->directStorageId > 0;
    }

    protected function getDirectStorageId(): int
    {
        return $this->directStorageId;
    }

    protected function populateState($ordering = null, $direction = null)
    {
        /** @var CMSWebApplication $app */
        $app = Factory::getApplication();
        parent::populateState($ordering, $direction);

        $list = (array) $app->input->get('list', [], 'array');
        $paginationStateKey = $this->getPaginationStateKeyPrefix();
        $limitKey = $paginationStateKey . '.limit';
        $startKey = $paginationStateKey . '.start';
        $configuredLimit = $this->getConfiguredListLimit();

        // Priority for state-backed menu overrides:
        // explicit request > menu setting > persisted session state > global default.
        $limit = isset($list['limit']) ? (int) $list['limit'] : 0;
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

        if (array_key_exists('start', $list)) {
            $start = max(0, (int) $list['start']);
        } elseif ($configuredLimit > 0) {
            // When the menu defines the initial page size, reopen the list from the
            // first page unless the request explicitly asks for another page.
            $start = 0;
        } else {
            $start = (int) $app->getUserState($startKey, 0);
        }

        // ✅ RESET page si on change un filtre (ou clique Search/Reset)
        if (
            $app->input->get('filter', null) !== null ||
            $app->input->get('list_state_filter', null) !== null ||
            $app->input->get('list_publish_filter', null) !== null ||
            $app->input->get('list_language_filter', null) !== null ||
            $app->input->getBool('filter_reset', false)
        ) {
            $start = 0;
        }

        // Persist pagination state across bulk actions and redirects.
        $app->setUserState($limitKey, (int) $limit);
        $app->setUserState($startKey, (int) $start);

        $this->setState('list.limit', (int) $limit);
        $this->setState('list.start', (int) $start);
    }

    private function getPaginationStateKeyPrefix(): string
    {
        /** @var CMSWebApplication $app */
        $app = Factory::getApplication();
        $option = 'com_contentbuilderng';

        $formId = (int) $this->_id;
        if ($this->isDirectStorageMode()) {
            $formId = (int) $this->directStorageId;
        }
        if ($formId < 1) {
            $formId = (int) $app->input->getInt('id', 0);
        }

        if ($formId < 1 && $app->isClient('site')) {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                $formId = (int) MenuParamHelper::getMenuParam($menu->getParams(), 'form_id', 0);
            }
        }

        $layout = (string) $app->input->getCmd('layout', 'default');
        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $app->input->getInt('Itemid', 0);

        $scope = $this->isDirectStorageMode() ? ('storage.' . $formId) : (string) $formId;

        return $option . '.liststate.' . $scope . '.' . $layout . '.' . $itemId;
    }

    private function getCurrentListScreenKey(): string
    {
        /** @var CMSWebApplication $app */
        $app = Factory::getApplication();

        $scope = $this->isDirectStorageMode()
            ? 'storage.' . (int) $this->directStorageId
            : 'form.' . (int) $this->_id;
        $layout = (string) $app->input->getCmd('layout', 'default');
        $itemId = (int) $app->input->getInt('Itemid', 0);

        return $scope . '.' . $layout . '.' . $itemId;
    }

    private function getConfiguredListLimit(): int
    {
        return MenuParamHelper::getConfiguredListLimit(Factory::getApplication());
    }

    private function getMenuToggle(string $key, int $default = 0): int
    {
        return MenuParamHelper::resolveInputOrMenuToggle(Factory::getApplication(), $key, $default);
    }

    private function getScopedListStateKey(string $suffix): string
    {
        return $this->getPaginationStateKeyPrefix() . '.' . $suffix;
    }

    private function resetStateOnFormSwitch(): void
    {
        $app = $this->app;
        $session = $app->getSession();
        $option = 'com_contentbuilderng';
        $paginationStateKey = $this->getPaginationStateKeyPrefix();

        // Reset list state keys.
        $app->setUserState($option . 'formsd_filter', '');
        $app->setUserState($option . 'formsd_filter_state', 0);
        $app->setUserState($option . 'formsd_filter_publish', -1);
        $app->setUserState($option . 'formsd_filter_language', '');
        $app->setUserState($option . 'formsd_filter_order', '');
        $app->setUserState($option . 'formsd_filter_order_Dir', '');
        $app->setUserState($paginationStateKey . '.limit', 0);
        $app->setUserState($paginationStateKey . '.start', 0);

        // Reset current form external filter state.
        $session->clear('com_contentbuilderng.filter_signal.' . $this->_id);
        $session->clear('com_contentbuilderng.filter.' . $this->_id);
        $session->clear('com_contentbuilderng.calendar_filter_from.' . $this->_id);
        $session->clear('com_contentbuilderng.calendar_filter_to.' . $this->_id);
        $session->clear('com_contentbuilderng.calendar_formats.' . $this->_id);
        $session->clear('com_contentbuilderng.filter_keywords.' . $this->_id);
        $session->clear('com_contentbuilderng.filter_article_categories.' . $this->_id);
    }

    protected function loadDirectStorageDefinition(): object
    {
        $storageId = $this->getDirectStorageId();
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('title'),
                $db->quoteName('bytable'),
                $db->quoteName('published'),
            ])
            ->from($db->quoteName('#__contentbuilderng_storages'))
            ->where($db->quoteName('id') . ' = ' . (int) $storageId);
        $db->setQuery($query, 0, 1);
        $storage = $db->loadObject();

        if (!$storage || (int) ($storage->bytable ?? 0) === 1) {
            throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        $storage->form = FormSourceFactory::getForm('com_contentbuilderng', $storageId);
        if ((!$storage->form || !$storage->form->exists) && class_exists('CB\\Component\\Contentbuilderng\\Administrator\\types\\contentbuilderng_com_contentbuilderng')) {
            $storage->form = new \CB\Component\Contentbuilderng\Administrator\types\contentbuilderng_com_contentbuilderng($storageId, false);
        }
        if (!$storage->form || !$storage->form->exists) {
            throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        if (method_exists($storage->form, 'synchRecords')) {
            $storage->form->synchRecords();
        }

        $fieldQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('title'),
            ])
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId)
            ->where('COALESCE(' . $db->quoteName('published') . ', 1) = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($fieldQuery);
        $storage->fieldRows = (array) $db->loadAssocList();

        return $storage;
    }

    protected function getDirectStorageListSubject(): object
    {
        $app = $this->app;
        $option = 'com_contentbuilderng';
        $storage = $this->loadDirectStorageDefinition();
        $ids = [];
        $labels = [];
        $orderTypes = [];

        foreach ($storage->fieldRows as $fieldRow) {
            $fieldId = (int) ($fieldRow['id'] ?? 0);
            if ($fieldId <= 0) {
                continue;
            }

            $ids[] = $fieldId;
            $labels[$fieldId] = (string) (($fieldRow['title'] ?? '') !== '' ? $fieldRow['title'] : ($fieldRow['name'] ?? $fieldId));
            $orderTypes['col' . $fieldId] = 'CHAR';
        }

        $data = (object) [
            'type' => 'com_contentbuilderng',
            'reference_id' => (int) $storage->id,
            'theme_plugin' => 'joomla6',
            'show_filter' => 1,
            'show_records_per_page' => 1,
            'button_bar_sticky' => 0,
            'list_header_sticky' => 0,
            'show_preview_link' => 1,
            'show_page_heading' => $this->_show_page_heading,
            'page_class' => $this->_page_class,
            'name' => (string) ($storage->title ?: $storage->name),
            'slug' => (string) ($storage->title ?: $storage->name),
            'slug2' => '',
            'form_id' => 0,
            'direct_storage_mode' => 1,
            'direct_storage_id' => (int) $storage->id,
            'direct_storage_unpublished' => (int) ($storage->published ?? 0) === 0 ? 1 : 0,
            'labels' => $labels,
            'visible_cols' => $ids,
            'linkable_elements' => [],
            'show_id_column' => 1,
            'page_title' => (string) (($this->_page_title !== '' && $this->_show_page_heading) ? $this->_page_title : ($storage->title ?: $storage->name)),
            'intro_text' => '',
            'export_xls' => 0,
            'display_filter' => !empty($ids),
            'edit_button' => 1,
            'new_button' => 1,
            'select_column' => 0,
            'states' => [],
            'list_state' => 0,
            'list_publish' => 0,
            'list_language' => 0,
            'list_article' => 0,
            'list_author' => 0,
            'list_last_modification' => 0,
            'list_rating' => 0,
            'rating_slots' => 0,
            'state_colors' => [],
            'state_titles' => [],
            'published_items' => [],
            'languages' => [],
            'lang_codes' => [],
            'title_field' => $ids[0] ?? 0,
            'preview_no_list_fields' => empty($ids),
            'own_only' => 0,
            'own_only_fe' => 0,
            'form' => $storage->form,
            'items' => [],
            'published_only' => 0,
            'show_all_languages_fe' => 1,
        ];

        $list = (array) $app->input->get('list', [], 'array');
        $ordering = isset($list['ordering']) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $list['ordering']) : '';
        $direction = isset($list['direction']) ? strtolower((string) $list['direction']) : '';
        if ($ordering === '' && isset($list['fullordering'])) {
            $parts = preg_split('/\s+/', trim((string) $list['fullordering']));
            $ordering = isset($parts[0]) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $parts[0]) : '';
            $direction = isset($parts[1]) ? strtolower((string) $parts[1]) : $direction;
        }
        if ($ordering === '') {
            $ordering = (string) $this->getState('formsd_filter_order');
        } else {
            $app->setUserState($option . 'formsd_filter_order', $ordering);
        }
        if ($direction === '') {
            $direction = $this->getState('formsd_filter_order_Dir')
                ? (string) $this->getState('formsd_filter_order_Dir')
                : 'asc';
        } else {
            $app->setUserState($option . 'formsd_filter_order_Dir', $direction);
        }
        if ($direction !== 'asc' && $direction !== 'desc') {
            $direction = 'asc';
        }
        if ($ordering !== '' && !isset($orderTypes[$ordering]) && $ordering !== 'colRecord') {
            $ordering = '';
        }

        $searchableElements = $ids;
        if ($ids !== []) {
            $data->items = $storage->form->getListRecords(
                $ids,
                $this->getState('formsd_filter'),
                $searchableElements,
                $this->getState('list.start'),
                $this->getState('list.limit'),
                $ordering,
                $orderTypes,
                $direction,
                0,
                false,
                -1,
                0,
                -1,
                -1,
                -1,
                -1,
                [],
                true,
                null,
                [],
                $data,
                -1
            );
            $this->_total = $storage->form->getListRecordsTotal($ids, $this->getState('formsd_filter'), $searchableElements);
            $recordMeta = $this->listSupportService->getListRecordMeta($data->items, (int) $storage->id, (string) $data->type, $data->reference_id);
            $data->published_items = $recordMeta['published_items'];
        } else {
            $data->items = [];
            $this->_total = 0;
        }

        return $data;
    }



    /*
     *
     * MAIN LIST AREA
     * 
     */

    private function buildOrderBy()
    {
        $orderby = '';
        $filter_order     = $this->getState('formsd_filter_order');
        $filter_order_Dir = $this->getState('formsd_filter_order_Dir') ? $this->getState('formsd_filter_order_Dir') : 'desc';

        /* Error handling is never a bad thing*/
        if (!empty($filter_order) && !empty($filter_order_Dir)) {
            $orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
        }

        return $orderby;
    }


    /**
     * @return \Joomla\Database\QueryInterface The query
     */
    private function _buildQuery()
    {
        $app = $this->app;
        $isAdminPreview = $app->input->getBool('cb_preview_ok', false);
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int) $this->_id);

        if (!$isAdminPreview) {
            $query->where($db->quoteName('published') . ' = 1');
        }

        return $query;
    }

    /**
     * Gets the currencies
     * @return array List of products
     */
    function getData()
    {
        $app = $this->app;
        $option = 'com_contentbuilderng';

        if ($this->isDirectStorageMode()) {
            return $this->getDirectStorageListSubject();
        }

        // Lets load the data if it doesn't already exist
        if (empty($this->_data)) {
            $query = $this->_buildQuery();
            $this->_data = $this->_getList($query, 0, 1);

            if (!count($this->_data)) {
                throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
            }

            foreach ($this->_data as $data) {
                $data->items = [];
                $data->published_items = [];
                $data->states = [];
                $data->state_colors = [];
                $data->state_titles = [];
                $data->languages = [];
                $data->lang_codes = [];
                $data->labels = [];
                $data->visible_cols = [];
                $data->linkable_elements = [];
                $data->preview_no_list_fields = false;
                $data->invalid_list_setup = false;
                $data->list_header_sticky = (int) ($data->list_header_sticky ?? 0);
                $isAdminPreview = $app->input->getBool('cb_preview_ok', false);

                if (!$isAdminPreview) {
                    if (!$this->frontend) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
                    }
                }

                // filter by category if requested by menu item
                if ($app->input->getInt('cb_category_menu_filter', 0) === 1) {
                    if ($app->input->getInt('cb_category_id', -1) > -2) {
                        $this->setState('article_category_filter', $app->input->getInt('cb_category_id', -1));
                    } else {
                        $this->setState('article_category_filter', $data->default_category);
                    }
                }

                $data->show_page_heading = $this->_show_page_heading;
                $data->page_class = $this->_page_class;
                $data->form_id = $this->_id;
                if ($data->type && $data->reference_id) {
                    $data->form = FormSourceFactory::getForm($data->type, $data->reference_id);
                    if (!$data->form->exists) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
                    }
                    $isAdminPreview = $app->input->getBool('cb_preview_ok', false);
                    $data->preview_no_list_fields = false;
                    if ($isAdminPreview && method_exists($data->form, 'synchRecords')) {
                        $data->form->synchRecords();
                    }
                    $prefixInTitle = $this->getMenuToggle('cb_prefix_in_title', (int) ($data->cb_prefix_in_title ?? 0));
                    $filterInTitle = $this->getMenuToggle('cb_filter_in_title', (int) ($data->cb_filter_in_title ?? 0));
                    $baseTitle = '';
                    if ($this->_show_page_heading && $this->_page_title !== '') {
                        $baseTitle = (string) $this->_page_title;
                    } elseif ($this->_menu_item) {
                        $baseTitle = (string) $app->getDocument()->getTitle();
                    } else {
                        $baseTitle = (string) $data->form->getPageTitle();
                    }

                    $viewTitle = $data->use_view_name_as_title ? (string) $data->name : $baseTitle;
                    $data->page_title = $viewTitle;
                    if ($prefixInTitle === 1 && $data->use_view_name_as_title && $baseTitle !== '') {
                        $data->page_title = trim($viewTitle . ' - ' . $baseTitle);
                    }

                    // enables the record randomizer
                    $now = Factory::getDate();
                    $data->rand_update = intval($data->rand_update);
                    if ($data->rand_update < 1) {
                        $data->rand_update = 86400;
                    }
                    $___now = $now->toSql();

                    if (
                        $data->initial_sort_order == 'Rand' &&
                        (empty($data->rand_date_update) || $now->toUnix() - strtotime($data->rand_date_update) >= $data->rand_update)
                    ) {
                        $db = $this->getDatabase();
                        $randQuery = $db->getQuery(true)
                            ->update($db->quoteName('#__contentbuilderng_records'))
                            ->set($db->quoteName('rand_date') . " = " . $db->quote($___now) . " + interval rand()*10000 day")
                            ->where($db->quoteName('type') . ' = ' . $db->quote($data->type))
                            ->where($db->quoteName('reference_id') . ' = ' . $db->quote($data->reference_id));
                        $db->setQuery($randQuery);
                        $db->execute();
                        $randDateQuery = $db->getQuery(true)
                            ->update($db->quoteName('#__contentbuilderng_forms'))
                            ->set($db->quoteName('rand_date_update') . ' = ' . $db->quote($___now));
                        $db->setQuery($randDateQuery);
                        $db->execute();
                    }

                    $data->labels = $data->form->getElementLabels();

                    if ($app->input->getBool('filter_reset', false)) {

                        $app->getSession()->clear('com_contentbuilderng.filter_signal.' . $this->_id);
                        $app->getSession()->clear('com_contentbuilderng.filter.' . $this->_id);
                        $app->getSession()->clear('com_contentbuilderng.calendar_filter_from.' . $this->_id);
                        $app->getSession()->clear('com_contentbuilderng.calendar_filter_to.' . $this->_id);
                        $app->getSession()->clear('com_contentbuilderng.calendar_formats.' . $this->_id);
                        $app->getSession()->clear('com_contentbuilderng.filter_keywords.' . $this->_id);
                        $app->getSession()->clear('com_contentbuilderng.filter_article_categories.' . $this->_id);
                    } else if (
                        (
                            $app->getSession()->get('com_contentbuilderng.filter_signal.' . $this->_id, false)
                            ||
                            $app->input->getBool('contentbuilderng_filter_signal', false)
                        )
                        && $data->allow_external_filter
                    ) {

                        $orders = array();
                        $filters = array();
                        $filters_from = array();
                        $filters_to = array();
                        $calendar_formats = array();

                        // renew on request
                        if ($app->input->getBool('contentbuilderng_filter_signal', false)) {

                            if ($app->input->get('cbListFilterKeywords', '', 'string')) {
                                $this->setState('formsd_filter', $app->input->get('cbListFilterKeywords', '', 'string'));
                            }

                            if ($app->input->get('cbListFilterArticleCategories', -1, 'string') > -1) {
                                $this->setState('article_category_filter', $app->input->getInt('cbListFilterArticleCategories', -1));
                            }

                            $filters = $app->input->post->get('cb_filter', [], 'array');
                            $filters_from = $app->input->post->get('cbListFilterCalendarFrom', [], 'array');
                            $filters_to = $app->input->post->get('cbListFilterCalendarTo', [], 'array');
                            $calendar_formats = $app->input->post->get('cb_filter_calendar_format', [], 'array');

                            $app->getSession()->set('com_contentbuilderng.filter_signal.' . $this->_id, true);
                            $app->getSession()->set('com_contentbuilderng.filter.' . $this->_id, $filters);
                            $app->getSession()->set('com_contentbuilderng.filter_keywords.' . $this->_id, $app->input->get('cbListFilterKeywords', '', 'string'));
                            $app->getSession()->set('com_contentbuilderng.filter_article_categories.' . $this->_id, $app->input->getInt('cbListFilterArticleCategories', -1));
                            $app->getSession()->set('com_contentbuilderng.calendar_filter_from.' . $this->_id, $filters_from);
                            $app->getSession()->set('com_contentbuilderng.calendar_filter_to.' . $this->_id, $filters_to);
                            $app->getSession()->set('com_contentbuilderng.calendar_formats.' . $this->_id, $calendar_formats);

                            // else pick from session
                        } else if ($app->getSession()->get('com_contentbuilderng.filter_signal.' . $this->_id, false)) {

                            $filters = $app->getSession()->get('com_contentbuilderng.filter.' . $this->_id, array());
                            $filters_from = $app->getSession()->get('com_contentbuilderng.calendar_filter_from.' . $this->_id, array());
                            $filters_to = $app->getSession()->get('com_contentbuilderng.calendar_filter_to.' . $this->_id, array());
                            $calendar_formats = $app->getSession()->get('com_contentbuilderng.calendar_formats.' . $this->_id, array());
                            $filter_keywords = $app->getSession()->get('com_contentbuilderng.filter_keywords.' . $this->_id, '');
                            $filter_cats = $app->getSession()->get('com_contentbuilderng.filter_article_categories.' . $this->_id, -1);

                            if ($filter_keywords != '') {
                                $this->setState('formsd_filter', $filter_keywords);
                            }

                            if ($filter_cats != -1) {
                                $this->setState('article_category_filter', $filter_cats);
                            }
                        }

                        foreach ($calendar_formats as $col => $calendar_format) {
                            if (isset($filters[$col])) {
                                $filter_exploded = explode('/', $filters[$col]);
                                if (isset($filter_exploded[2])) {
                                    $to_exploded = explode('to', $filter_exploded[2]);
                                    switch (count($to_exploded)) {
                                        case 2:
                                            if ($to_exploded[0] != '') {
                                                $filters[$col] = '@range/date/' .  ContentbuilderngHelper::convertDate(trim($to_exploded[0]), $calendar_format) . ' to ' . ContentbuilderngHelper::convertDate(trim($to_exploded[1]), $calendar_format);
                                            } else {
                                                $filters[$col] = '@range/date/to ' . ContentbuilderngHelper::convertDate(trim($to_exploded[1]), $calendar_format);
                                            }
                                            break;
                                        case 1:
                                            $filters[$col] = '@range/date/' .  ContentbuilderngHelper::convertDate(trim($to_exploded[0]), $calendar_format);
                                            break;
                                    }
                                    if (isset($to_exploded[0]) && isset($to_exploded[1]) && trim($to_exploded[0]) == '' && trim($to_exploded[1]) == '') {
                                        $filters[$col] = '';
                                    }
                                    if (isset($to_exploded[0]) && !isset($to_exploded[1]) && trim($to_exploded[0]) == '') {
                                        $filters[$col] = '';
                                    }
                                }
                            }
                        }

                        $new_filters = array();
                        $i = 1;
                        foreach ($filters as $filter_key => $filter) {
                            if ($filter != '') {
                                $orders[$filter_key] = $i;
                                $new_filters[$filter_key] = explode('|', $filter);
                            }
                            $i++;
                        }

                        foreach ($new_filters as $filterKey => $filterTerms) {
                            $this->_menu_filter[$filterKey] = $filterTerms;
                        }

                        foreach ($orders as $filterKey => $orderValue) {
                            $this->_menu_filter_order[$filterKey] = $orderValue;
                        }
                    }

                    $ordered_extra_title = '';
                    foreach ($this->_menu_filter_order as $order_key => $order) {
                        if (isset($this->_menu_filter[$order_key])) {
                            // range test
                            $is_range = strstr(strtolower(implode(',', $this->_menu_filter[$order_key])), '@range') !== false;
                            $is_match = strstr(strtolower(implode(',', $this->_menu_filter[$order_key])), '@match') !== false;
                            if ($is_range) {
                                $ex = explode('/', implode(', ', $this->_menu_filter[$order_key]));
                                if (count($ex) == 3) {
                                    $ex2 = explode('to', trim($ex[2]));
                                    $out = '';
                                    $val = $ex2[0];
                                    $val2 = '';
                                    if (isset($ex2[1])) {
                                        $val2 = $ex2[1];
                                    }
                                    if (strtolower(trim($ex[1])) == 'date') {
                                        $val = HTMLHelper::_('date', $ex2[0], Text::_('DATE_FORMAT_LC5'));
                                        if (isset($ex2[1])) {
                                            $val2 = HTMLHelper::_('date', $ex2[1], Text::_('DATE_FORMAT_LC5'));
                                        }
                                    }
                                    if (count($ex2) == 2) {
                                        $out = (trim($ex2[0]) ? Text::_('COM_CONTENTBUILDERNG_FROM') . ' ' . trim($val) : '') . ' ' . Text::_('COM_CONTENTBUILDERNG_TO') . ' ' . trim($val2);
                                    } else if (count($ex2) > 0) {
                                        $out = Text::_('COM_CONTENTBUILDERNG_FROM2') . ' ' . trim($val);
                                    }
                                    if ($out) {
                                        $this->_menu_filter[$order_key] = $ex;
                                        $ordered_extra_title .= ' &raquo; ' . htmlentities($data->labels[$order_key], ENT_QUOTES, 'UTF-8') . ': ' . htmlentities($out, ENT_QUOTES, 'UTF-8');
                                    }
                                }
                            } else if ($is_match) {
                                $ex = explode('/', implode(', ', $this->_menu_filter[$order_key]));
                                if (count($ex) == 2) {
                                    $ex2 = explode(';', trim($ex[1]));
                                    $out = '';
                                    $size = count($ex2);
                                    $i = 0;
                                    foreach ($ex2 as $val) {
                                        if ($i + 1 < $size) {
                                            $out .= trim($val) . ' ' . Text::_('COM_CONTENTBUILDERNG_AND') . ' ';
                                        } else {
                                            $out .= trim($val);
                                        }
                                        $i++;
                                    }
                                    if ($out) {
                                        $this->_menu_filter[$order_key] = $ex;
                                        $ordered_extra_title .= ' &raquo; ' . htmlentities($data->labels[$order_key], ENT_QUOTES, 'UTF-8') . ': ' . htmlentities($out, ENT_QUOTES, 'UTF-8');
                                    }
                                }
                            } else {
                                $ordered_extra_title .= ' &raquo; ' . htmlentities($data->labels[$order_key], ENT_QUOTES, 'UTF-8') . ': ' . htmlentities(implode(', ', $this->_menu_filter[$order_key]), ENT_QUOTES, 'UTF-8');
                            }
                        }
                    }

                    $data->slug = $data->page_title;
                    $data->slug2 = '';

                    // "buddy quaid hack", should be an option in future versions

                    $custom_page_heading = '';

                    if (!$app->isClient('administrator')) {
                        $prefixTitle = (string) $data->page_title;
                        $menuTitle = '';

                        if ($this->_show_page_heading && $this->_page_heading != '') {
                            $menuTitle = (string) $this->_page_heading;
                        } elseif ($this->_show_page_heading && $this->_page_title != '') {
                            $menuTitle = (string) $this->_page_title;
                        } elseif ($this->_page_title != '') {
                            $menuTitle = (string) $this->_page_title;
                        }

                        if ($prefixInTitle === 1) {
                            if ($prefixTitle !== '' && $menuTitle !== '') {
                                $data->page_title = trim($prefixTitle . ' ' . $menuTitle);
                            } else {
                                $data->page_title = $prefixTitle !== '' ? $prefixTitle : $menuTitle;
                            }
                        } else {
                            $data->page_title = $menuTitle !== '' ? $menuTitle : $prefixTitle;
                        }

                        if ($filterInTitle === 1 && $ordered_extra_title !== '') {
                            $normalizedExtraTitle = ltrim(preg_replace('/^(?:\s*&raquo;\s*)+/', '', $ordered_extra_title) ?? '', ' ');
                            $normalizedExtraTitle = preg_replace('/\s*:\s*/', ' : ', $normalizedExtraTitle) ?? $normalizedExtraTitle;
                            $data->slug2 = str_replace(' &raquo; ', '', $ordered_extra_title);
                            $data->page_title .= ($data->page_title !== '' ? ' ' : '') . $normalizedExtraTitle;
                        }
                    }

                    $ids = array();
                    foreach ($data->labels as $reference_id => $label) {
                        $ids[] = $this->getDatabase()->quote($reference_id);
                    }
                    $searchable_elements = $this->listSupportService->getListSearchableElements($this->_id);
                    $data->display_filter = count($searchable_elements) && $data->show_filter;
                    $data->linkable_elements = $this->listSupportService->getListLinkableElements($this->_id);
                    $data->labels = array();
                    $order_types = array();
                    if (count($ids)) {
                        $db = $this->getDatabase();
                        $elemQuery = $db->getQuery(true)
                            ->select('DISTINCT ' . implode(', ', [
                                $db->quoteName('id'),
                                $db->quoteName('label'),
                                $db->quoteName('reference_id'),
                                $db->quoteName('order_type'),
                            ]))
                            ->from($db->quoteName('#__contentbuilderng_elements'))
                            ->where($db->quoteName('form_id') . ' = ' . (int) $this->_id)
                            ->where($db->quoteName('reference_id') . ' IN (' . implode(',', $ids) . ')')
                            ->where($db->quoteName('published') . ' = 1')
                            ->where($db->quoteName('list_include') . ' = 1')
                            ->order($db->quoteName('ordering'));
                        $db->setQuery($elemQuery);
                        $rows = $db->loadAssocList();
                        $ids = array();
                        foreach ($rows as $row) {
                            // cleaned up, in desired order
                            $data->labels[$row['reference_id']] = $row['label'];
                            $ids[] = $row['reference_id'];
                            $order_types['col' . $row['reference_id']] = $row['order_type'];
                        }

                        if ($isAdminPreview && !count($rows)) {
                            $data->preview_no_list_fields = true;
                        }
                    }
                    // Allow sorting on the published state column.
                    $order_types['colPublished'] = 'UNSIGNED';
                    // Allow sorting on the list state title column.
                    $order_types['colState'] = 'CHAR';
                    // Allow sorting on the language code column.
                    $order_types['colLanguage'] = 'CHAR';

                    $act_as_registration = array();

                    if (
                        $data->act_as_registration &&
                        $data->registration_username_field &&
                        $data->registration_name_field &&
                        $data->registration_email_field &&
                        $data->registration_email_repeat_field &&
                        $data->registration_password_field &&
                        $data->registration_password_repeat_field
                    ) {
                        $act_as_registration[$data->registration_username_field] = 'registration_username_field';
                        $act_as_registration[$data->registration_name_field] = 'registration_name_field';
                        $act_as_registration[$data->registration_email_field] = 'registration_email_field';
                    }

                    // Derive ordering directly from the request to stay Joomla 6-native.
                    $list = (array) $app->input->get('list', [], 'array');
                    $ordering = isset($list['ordering']) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $list['ordering']) : '';
                    $direction = isset($list['direction']) ? strtolower((string) $list['direction']) : '';
                    if ($ordering === '' && isset($list['fullordering'])) {
                        $parts = preg_split('/\s+/', trim((string) $list['fullordering']));
                        $ordering = isset($parts[0]) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $parts[0]) : '';
                        $direction = isset($parts[1]) ? strtolower((string) $parts[1]) : $direction;
                    }
                    if ($ordering === '') {
                        $ordering = (string) $this->getState('formsd_filter_order');
                    } else {
                        $app->setUserState($option . 'formsd_filter_order', $ordering);
                    }
                    if ($direction === '') {
                        $direction = $this->getState('formsd_filter_order_Dir')
                            ? (string) $this->getState('formsd_filter_order_Dir')
                            : $data->initial_order_dir;
                    } else {
                        $app->setUserState($option . 'formsd_filter_order_Dir', $direction);
                    }
                    if ($direction !== 'asc' && $direction !== 'desc') {
                        $direction = 'asc';
                    }

                    // Guard against ordering by a column that is not part of the SELECT.
                    $knownOrderKeys = ['colRecord', 'colState', 'colPublished', 'colLanguage', 'colRating', 'colArticleId', 'colAuthor'];
                    if ($ordering !== '' && !isset($order_types[$ordering]) && !in_array($ordering, $knownOrderKeys, true)) {
                        $ordering = '';
                    }

                    $isAdminPreview = $app->input->getBool('cb_preview_ok', false);
                    $publishedOnly = PublishedRecordVisibilityHelper::shouldRestrictToPublishedOnly($data, $isAdminPreview);
                    $ownerFilterUserId = $isAdminPreview
                        ? -1
                        : ($this->frontend
                            ? ($data->own_only_fe ? (int) ($app->getIdentity()->id ?? 0) : -1)
                            : ($data->own_only ? (int) ($app->getIdentity()->id ?? 0) : -1));
                    $showAllLanguages = $isAdminPreview ? true : ($this->frontend ? $data->show_all_languages_fe : true);

                    $initialSortOrder = (isset($data->initial_sort_order) && (string) $data->initial_sort_order !== '' && (string) $data->initial_sort_order !== '-1' && ctype_digit((string) $data->initial_sort_order))
                        ? 'col' . $data->initial_sort_order
                        : -1;
                    $initialSortOrder2 = (isset($data->initial_sort_order2) && (string) $data->initial_sort_order2 !== '' && (string) $data->initial_sort_order2 !== '-1' && ctype_digit((string) $data->initial_sort_order2))
                        ? ((string) $data->initial_sort_order2 === '0' ? 'colRecord' : 'col' . $data->initial_sort_order2)
                        : -1;
                    $initialSortOrder3 = (isset($data->initial_sort_order3) && (string) $data->initial_sort_order3 !== '' && (string) $data->initial_sort_order3 !== '-1' && ctype_digit((string) $data->initial_sort_order3))
                        ? ((string) $data->initial_sort_order3 === '0' ? 'colRecord' : 'col' . $data->initial_sort_order3)
                        : -1;

                    $data->items = $data->form->getListRecords(
                        $ids,
                        $this->getState('formsd_filter'),
                        $searchable_elements,
                        $this->getState('list.start'),
                        $this->getState('list.limit'),
                        $ordering,
                        $order_types,
                        $direction,
                        0,
                        $publishedOnly,
                        $ownerFilterUserId,
                        $this->getState('formsd_filter_state'),
                        $this->getState('formsd_filter_publish'),
                        $initialSortOrder,
                        $initialSortOrder2,
                        $initialSortOrder3,
                        $this->_menu_filter,
                        $showAllLanguages,
                        $this->getState('formsd_filter_language'),
                        $act_as_registration,
                        $data,
                        $this->getState('article_category_filter')
                    );

                    if ($data->items === null) {
                        $app->setUserState($option . 'formsd_filter_order', '');
                        throw new \Exception(Text::_('Stale list setup detected. Please reload view.'), 500);
                    }
                    $data->items = $this->templateRenderService->applyItemWrappers($this->_id, $data->items, $data);
                    $this->_total = $data->form->getListRecordsTotal($ids, $this->getState('formsd_filter'), $searchable_elements);
                    $data->visible_cols = $ids;

                    $data->states = array();
                    $data->state_colors = array();
                    $data->state_titles = array();
                    $data->published_items = array();
                    $data->states = $this->listSupportService->getListStates($this->_id);
                    $recordMeta = $this->listSupportService->getListRecordMeta($data->items, $this->_id, (string) $data->type, $data->reference_id);
                    if ($data->list_state) {
                        $data->state_colors = $recordMeta['state_colors'];
                        $data->state_titles = $recordMeta['state_titles'];
                    }
                    if ($data->list_publish) {
                        $data->published_items = $recordMeta['published_items'];
                    }
                    $data->lang_codes = array();
                    if ($data->list_language) {
                        $data->lang_codes = $recordMeta['lang_codes'];
                    }
                    $data->languages = (new FormSupportService())->getLanguageCodes();

                    // Search for the {readmore} tag and split the text up accordingly.
                    $pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
                    $tagPos = preg_match($pattern, $data->intro_text);

                    $fulltext = '';

                    if ($tagPos == 0) {
                        $introtext = $data->intro_text;
                    } else {
                        list($introtext, $fulltext) = preg_split($pattern, $data->intro_text, 2);
                    }

                    $data->intro_text = $introtext . ($fulltext ? '<br/><br/>' . $fulltext : '');

                    // Plugin call
                    $limitstart = (int) $this->getState('list.start');
                    $start      = $app->input->getInt('start', 0);
                    $table = new \Joomla\CMS\Table\Content($this->getDatabase());
                    $registry = new Registry;
                    $registry->loadString($table->attribs ?? '');
                    PluginHelper::importPlugin('content');
                    $table->text = $data->intro_text;
                    $table->text .= "<!-- workaround for J! pagebreak bug: class=\"system-pagebreak\" -->\n";

                    $dispatcher = $app->getDispatcher();
                    $dispatcher->dispatch('onContentPrepare', new ContentPrepareEvent('onContentPrepare', array('com_content.article', &$table, &$registry, $limitstart ? $limitstart : $start)));

                    $data->intro_text = $table->text;

                    if (
                        $app->isClient('administrator')
                        && strpos($data->intro_text, '[[hide-admin-title]]') !== false
                    ) {

                        $data->page_title = '';
                    }
                } else {
                    $data->invalid_list_setup = true;
                    $data->preview_no_list_fields = true;
                    $this->_total = 0;
                }

                return $data;
            }
        }
        return null;
    }

    public function getItems()
    {
        $data = $this->getData(); // ton getData() récupère déjà $data->items
        return $data->items ?? [];
    }

    public function getTotal()
    {
        // soit tu fais calculer $_total dans getData() comme actuellement
        $this->getData();
        return (int) $this->_total;
    }

    public function getPagination()
    {
        return parent::getPagination();
    }


    function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
}
