<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;
use CB\Component\Contentbuilderng\Administrator\Service\LegacyUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Service\TemplateRenderService;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;

class DetailsModel extends ListModel
{
    private readonly TemplateRenderService $templateRenderService;
    private readonly LegacyUtilityService $legacyUtilityService;
    private $_record_id = 0;

    private $frontend = false;

    private $_show_back_button = true;

    private $_menu_item = false;

    private $_show_page_heading = true;

    private $_menu_filter = array();

    private $_menu_filter_order = array();

    private $_latest = false;

    private $_page_title = '';

    private $_page_heading = '';
    private SiteApplication $app;
    private int $directStorageId = 0;

    public function __construct(
        $config,
        MVCFactoryInterface $factory) {
        // IMPORTANT : on transmet factory/app/input à ListModel
        parent::__construct($config, $factory);

        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $this->app = $app;
        $this->templateRenderService = new TemplateRenderService();
        $this->legacyUtilityService = new LegacyUtilityService();
        $option = 'com_contentbuilderng';
        $this->frontend = $app->isClient('site');
        $this->directStorageId = max(0, $app->input->getInt('storage_id', 0));

        // ATTTENTION: ALSO DEFINED IN DETAILS CONTROLLER!
        if ($this->frontend && $app->input->getInt('Itemid', 0)) {
            $this->_menu_item = true;

            // try menu item

            $menu = $app->getMenu();
            $item = $menu->getActive();
            if (is_object($item)) {
                if ($item->getParams()->get('record_id', null) !== null) {
                    $app->input->set('record_id', $item->getParams()->get('record_id', null));
                    $this->_show_back_button = $item->getParams()->get('show_back_button', null);
                }

                if ($item->getParams()->get('cb_latest', null) !== null) {
                    $this->_latest = $item->getParams()->get('cb_latest', null);
                    $this->_show_back_button = $item->getParams()->get('show_back_button', null);
                }
                if ($item->getParams()->get('show_page_heading', null) !== null) {
                    $this->_show_page_heading = $item->getParams()->get('show_page_heading', null);
                }
                if ($item->getParams()->get('page_title', null) !== null) {
                    $this->_page_title = $item->getParams()->get('page_title', null);
                }
                if ($item->getParams()->get('page_heading', null) !== null) {
                    $this->_page_heading = $item->getParams()->get('page_heading', null);
                }
            }
        }

        $menu_filter = $app->input->get('cb_list_filterhidden', null, 'string');

        if ($menu_filter !== null) {
            $lines = explode("\n", $menu_filter);
            foreach ($lines as $line) {
                $keyval = explode("\t", $line);
                if (count($keyval) == 2) {
                    $keyval[1] = str_replace(array("\n", "\r"), "", $keyval[1]);
                    $keyval[1] = $this->legacyUtilityService->sanitizeHiddenFilterValue($keyval[1]);
                    if ($keyval[1] != '') {
                        $this->_menu_filter[$keyval[0]] = explode('|', $keyval[1]);
                    }
                }
            }
        }

        $menu_filter_order = $app->input->get('cb_list_orderhidden', null, 'string');

        if ($menu_filter_order !== null) {
            $lines = explode("\n", $menu_filter_order);
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

        $this->setIds($app->input->getInt('id', 0), $app->input->getCmd('record_id', ''));
    }

    /*
     * MAIN DETAILS AREA
     */

    /**
     *
     * @param int $id
     */
    function setIds($id, $record_id)
    {
        // Set id and wipe data
        $this->_id = $id;
        $this->_record_id = $record_id;
        $this->_data = null;
    }

    private function isDirectStorageMode(): bool
    {
        return $this->directStorageId > 0 && (int) $this->_id <= 0;
    }

    private function getDirectStorageId(): int
    {
        return $this->directStorageId;
    }

    private function loadDirectStorageDefinition(): object
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

        return $storage;
    }

    private function _buildQuery()
    {
        if ($this->isDirectStorageMode()) {
            return '';
        }

        $isAdminPreview = $this->app->input->getBool('cb_preview_ok', false);
        $query = 'Select * From #__contentbuilderng_forms Where id = ' . intval($this->_id);

        if (!$isAdminPreview) {
            $query .= ' And published = 1';
        }

        return $query;
    }

    /**
     * Gets the currencies
     * @return array List of currencies
     */
    function getData()
    {
        $app = $this->app;

        if ($this->isDirectStorageMode()) {
            $storage = $this->loadDirectStorageDefinition();
            $recordId = (int) $this->_record_id;

            if ($recordId <= 0) {
                throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
            }

            $items = $storage->form->getRecord($recordId, false, -1, true);
            if (!is_array($items) || count($items) === 0) {
                throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
            }

            $metadata = $storage->form->getRecordMetadata($recordId);

            return (object) [
                'type' => 'com_contentbuilderng',
                'reference_id' => (int) $storage->id,
                'theme_plugin' => 'joomla6',
                'form_id' => 0,
                'direct_storage_mode' => 1,
                'direct_storage_id' => (int) $storage->id,
                'direct_storage_unpublished' => (int) ($storage->published ?? 0) === 0 ? 1 : 0,
                'record_id' => $recordId,
                'show_page_heading' => $this->_show_page_heading,
                'show_back_button' => $this->_show_back_button,
                'name' => (string) ($storage->title ?: $storage->name),
                'page_title' => (string) (($this->_page_title !== '' && $this->_show_page_heading) ? $this->_page_title : ($storage->title ?: $storage->name)),
                'items' => $items,
                'template' => $this->buildDirectStorageDetailsTemplate($items),
                'created' => (string) ($metadata->created ?? ''),
                'created_by' => (string) ($metadata->created_by ?? ''),
                'modified' => (string) ($metadata->modified ?? ''),
                'modified_by' => (string) ($metadata->modified_by ?? ''),
                'metadesc' => (string) ($metadata->metadesc ?? ''),
                'metakey' => (string) ($metadata->metakey ?? ''),
                'author' => (string) ($metadata->author ?? ''),
                'rights' => (string) ($metadata->rights ?? ''),
                'robots' => (string) ($metadata->robots ?? ''),
                'xreference' => (string) ($metadata->xreference ?? ''),
                'print_button' => 0,
                'show_id_column' => 1,
                'published_only' => 0,
            ];
        }

        // Lets load the data if it doesn't already exist
        if (empty($this->_data)) {
            $query = $this->_buildQuery();
            $this->_data = $this->_getList($query, 0, 1);

            if (!count($this->_data)) {
                throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
            }

            foreach ($this->_data as $data) {
                $isAdminPreview = $app->input->getBool('cb_preview_ok', false);

                if (!$isAdminPreview) {
                    if (!$this->frontend && $data->display_in == 0) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
                    } else if ($this->frontend && $data->display_in == 1) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
                    }
                }

                $data->form_id = $this->_id;
                $data->record_id = $this->_record_id;

                if ($data->type && $data->reference_id) {

                    $data->form = \CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory::getForm($data->type, $data->reference_id);
                    $isAdminPreview = $app->input->getBool('cb_preview_ok', false);
                    if ($isAdminPreview && method_exists($data->form, 'synchRecords')) {
                        $data->form->synchRecords();
                    }

                    $data->labels = $data->form->getElementLabels();
                    $ids = array();
                    foreach ($data->labels as $reference_id => $label) {
                        $ids[] = $this->getDatabase()->quote($reference_id);
                    }

                    if (count($ids)) {
                        $this->getDatabase()->setQuery("Select Distinct `label`, reference_id From #__contentbuilderng_elements Where form_id = " . intval($this->_id) . " And reference_id In (" . implode(',', $ids) . ") And published = 1 Order By ordering");
                        $rows = $this->getDatabase()->loadAssocList();
                        $ids = array();
                        foreach ($rows as $row) {
                            $ids[] = $row['reference_id'];
                        }
                    }

                    if ($this->_latest) {

                        $rec = $data->form->getListRecords($ids, '', array(), 0, 1, '', array(), 'desc', 0, false, (int) ($app->getIdentity()->id ?? 0), 0, -1, -1, -1, -1, array(), true, null);

                        if (count($rec) > 0) {
                            $rec = $rec[0];
                            $rec2 = $data->form->getRecord($rec->colRecord, false, -1, true);

                            $data->record_id = $rec->colRecord;
                            $app->input->set('record_id', $data->record_id);
                            $this->_record_id = $data->record_id;
                        } else {
                            $app->input->set('cbIsNew', 1);
                            (new PermissionService())->setPermissions($app->input->getInt('id', 0), 0, $this->frontend ? '_fe' : '');
                            $auth = $this->frontend ? (new PermissionService())->authorizeFe('new') : (new PermissionService())->authorize('new');

                            if ($auth) {
                                $state = $this->resolveListState();
                                $listQuery = http_build_query(['list' => [
                                    'limit' => $state['limit'],
                                    'start' => $state['start'],
                                    'ordering' => $state['ordering'],
                                    'direction' => $state['direction'],
                                ]]);

                                $app->redirect(Route::_('index.php?option=com_contentbuilderng&task=edit.display&latest=1&backtolist=' . $app->input->getInt('backtolist', 0) . '&id=' . $this->_id . '&record_id=' . ($listQuery !== '' ? '' : '') . ($listQuery !== '' ? '&' . $listQuery : ''), false));
                            } else {
                                $app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_ADD_ENTRY_FIRST'));
                                $app->redirect('index.php');
                            }
                        }
                    }

                    $data->show_page_heading = $this->_show_page_heading;
                    if (!$data->form->exists) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
                    }
                    $data->page_title = '';
                    if ($app->input->getInt('cb_prefix_in_title', 1)) {
                        if (!$this->_menu_item) {
                            $data->page_title = $data->use_view_name_as_title ? $data->name : $data->form->getPageTitle();
                        } else {
                            $data->page_title = $data->use_view_name_as_title ? $data->name : $app->getDocument()->getTitle();
                        }
                    }
                    if ($this->frontend) {
                        $document = $app->getDocument();
                        $document->setTitle($data->page_title);
                    }
                    $data->show_back_button = $this->_show_back_button;

                    if (isset($rec2) && count($rec2)) {
                        $data->items = $rec2;
                    } else {
                        $isAdminPreview = $app->input->getBool('cb_preview_ok', false);
                        $publishedOnly = $isAdminPreview ? false : (bool) $data->published_only;
                        $ownerFilterUserId = $isAdminPreview
                            ? -1
                            : ($this->frontend
                                ? ($data->own_only_fe ? (int) ($app->getIdentity()->id ?? 0) : -1)
                                : ($data->own_only ? (int) ($app->getIdentity()->id ?? 0) : -1));
                        $showAllLanguages = $isAdminPreview ? true : ($this->frontend ? $data->show_all_languages_fe : true);
                        $data->items = $data->form->getRecord($this->_record_id, $publishedOnly, $ownerFilterUserId, $showAllLanguages);
                    }

                    if (count($data->items)) {

                        $user = null;

                        if ($data->act_as_registration) {
                            $meta = $data->form->getRecordMetadata($this->_record_id);
                            $this->getDatabase()->setQuery("Select * From #__users Where id = " . $meta->created_id);
                            $user = $this->getDatabase()->loadObject();
                        }

                        $label = '';
                        foreach ($data->items as $rec) {

                            if ($rec->recElementId == $data->title_field) {

                                if ($data->act_as_registration && $user !== null) {

                                    if ($data->registration_name_field == $rec->recElementId) {
                                        $rec->recValue = $user->name;
                                    } else
                                        if ($data->registration_username_field == $rec->recElementId) {
                                            $rec->recValue = $user->username;
                                        } else
                                            if ($data->registration_email_field == $rec->recElementId) {
                                                $rec->recValue = $user->email;
                                            } else
                                                if ($data->registration_email_repeat_field == $rec->recElementId) {
                                                    $rec->recValue = $user->email;
                                                }
                                }
                                $label = ContentbuilderngHelper::cbinternal($rec->recValue);
                                break;
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
                                            $out = Text::_('COM_CONTENTBUILDERNG_FROM') . ' ' . trim($val);
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

                        // "buddy quaid hack", should be an option in future versions
                        if ($this->_show_page_heading && $this->_page_title != '' && $this->_page_heading != '' && $this->_page_title == $this->_page_heading) {
                            $data->page_title = $this->_page_title;
                        }

                        if ($this->frontend) {
                            $document = $app->getDocument();
                            $document->setTitle(html_entity_decode($data->page_title, ENT_QUOTES, 'UTF-8'));
                        }

                        $data->template = $this->templateRenderService->getTemplate($this->_id, $this->_record_id, $data->items, $ids, true);

                        if (
                            $app->isClient('administrator')
                            && strpos($data->template, '[[hide-admin-title]]') !== false
                        ) {

                            $data->page_title = '';
                        }

                        $metadata = $data->form->getRecordMetadata($this->_record_id);
                        if ($metadata instanceof \stdClass && $data->metadata) {
                            $data->created = $metadata->created ? $metadata->created : '';
                            $data->created_by = $metadata->created_by ? $metadata->created_by : '';
                            $data->modified = $metadata->modified ? $metadata->modified : '';
                            $data->modified_by = $metadata->modified_by ? $metadata->modified_by : '';
                            $data->metadesc = $metadata->metadesc;
                            $data->metakey = $metadata->metakey;
                            $data->author = $metadata->author;
                            $data->rights = $metadata->rights;
                            $data->robots = $metadata->robots;
                            $data->xreference = $metadata->xreference;
                        } else {
                            $data->created = '';
                            $data->created_by = '';
                            $data->modified = '';
                            $data->modified_by = '';
                            $data->metadesc = '';
                            $data->metakey = '';
                            $data->author = '';
                            $data->rights = '';
                            $data->robots = '';
                            $data->xreference = '';
                        }
                    } else {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
                    }
                }
                return $data;
            }
        }
        return null;
    }

    private function buildDirectStorageDetailsTemplate(array $items): string
    {
        $html = '<ul class="category list-striped list-condensed">';

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }

            $title = htmlspecialchars((string) ($item->recTitle ?? ''), ENT_QUOTES, 'UTF-8');
            $value = (string) ($item->recValue ?? '');
            if (trim(strip_tags($value)) === '') {
                $value = '&nbsp;';
            }

            $html .= '<li><strong class="list-title">' . $title . '</strong><div>' . $value . '</div></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    private function resolveListState(): array
    {
        /** @var SiteApplication $app */
        $app = $this->app;
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
        /** @var SiteApplication $app */
        $app = $this->app;
        $option = 'com_contentbuilderng';

        $formId = (int) $this->_id;
        if ($this->isDirectStorageMode()) {
            $formId = (int) $this->getDirectStorageId();
        }
        if ($formId < 1) {
            $formId = (int) $app->input->getInt('id', 0);
        }
        if ($formId < 1 && $app->isClient('site')) {
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

        $scope = $this->isDirectStorageMode() ? ('storage.' . $formId) : (string) $formId;

        return $option . '.liststate.' . $scope . '.' . $layout . '.' . $itemId;
    }
}
