<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Administrator\Service\RuntimeUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\ListSupportService;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\PublishedRecordVisibilityHelper;

class ExportModel extends BaseDatabaseModel
{
    private readonly RuntimeUtilityService $runtimeUtilityService;
    private readonly ListSupportService $listSupportService;
    private $frontend = false;

    private $_menu_filter = array();

    private $_menu_filter_order = array();

    protected int $_id = 0;

    protected ?array $_data = null;
    private SiteApplication $app;

    function  __construct($config)
    {
        parent::__construct($config);

        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $this->app = $app;
        $this->runtimeUtilityService = new RuntimeUtilityService();
        $this->listSupportService = new ListSupportService();
        $this->frontend = $app->isClient('site');
        $option = 'com_contentbuilderng';

        $id = $app->input->getInt('id', 0);

        if (!$id && $this->frontend) {
            $menu = $app->getMenu();
            $item = $menu->getActive();
            if ($item) {
                $id = (int) MenuParamHelper::getMenuParam($item->getParams(), 'form_id', 0);
            }
        }

        if (!$id) {
            $id = (int) $app->getSession()->get($option . 'formsd_id', 0);
        }

        $this->setId($id);

        if (!$this->_id) {
            throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        if ($app->getSession()->get($option . 'formsd_id', 0) == 0 || $app->getSession()->get($option . 'formsd_id', 0) == $this->_id) {
            $filter_order     = preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $app->getUserStateFromRequest($option . 'formsd_filter_order', 'filter_order', '', 'string'));
            $filter_order_Dir = strtolower((string) $app->getUserStateFromRequest($option . 'formsd_filter_order_Dir', 'filter_order_Dir', '', 'string'));
            $filter           = $app->getUserStateFromRequest($option . 'formsd_filter', 'filter', '', 'string');
            $filter_state     = $app->getUserStateFromRequest($option . 'formsd_filter_state', 'list_state_filter', 0, 'int');
            $filter_publish   = $app->getUserStateFromRequest($option . 'formsd_filter_publish', 'list_publish_filter', -1, 'int');
            $filter_language  = $app->getUserStateFromRequest($option . 'formsd_filter_language', 'list_language_filter', '', 'cmd');
        } else {
            $app->setUserState($option . 'formsd_filter_order', preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $app->input->getString('filter_order', '')));
            $app->setUserState($option . 'formsd_filter_order_Dir', strtolower((string) $app->input->getString('filter_order_Dir', '')));
            $app->setUserState($option . 'formsd_filter', $app->input->get('filter', '', 'string'));
            $app->setUserState($option . 'formsd_filter_state', $app->input->getInt('list_state_filter', 0));
            $app->setUserState($option . 'formsd_filter_publish', $app->input->getInt('list_publish_filter', -1));
            $app->setUserState($option . 'formsd_filter_language', $app->input->getCmd('list_language_filter', ''));
            $filter_order     = $app->input->getCmd('filter_order', '');
            $filter_order_Dir = $app->input->getCmd('filter_order_Dir', '');
            $filter           = $app->input->get('filter', '', 'string');
            $filter_state     = $app->input->getInt('list_state_filter', 0);
            $filter_publish   = $app->input->getInt('list_publish_filter', -1);
            $filter_language  = $app->input->getCmd('list_language_filter', '');
        }

        $this->setState('formsd_filter_state', $filter_state);
        $this->setState('formsd_filter_publish', $filter_publish);
        $this->setState('formsd_filter_language', empty($filter_language) ? null : $filter_language);
        $this->setState('formsd_filter', $filter);
        $this->setState('formsd_filter_order', $filter_order);
        $this->setState('formsd_filter_order_Dir', $filter_order_Dir);

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

        $app->getSession()->set($option . 'formsd_id', $this->_id);
    }

    function setId($id)
    {
        // Set id and wipe data
        $this->_id      = $id;
        $this->_data    = null;
    }

    /*
     *
     * MAIN LIST AREA
     *
     */

    /**
     * @return string The query
     */
    private function _buildQuery()
    {
        return 'Select * From #__contentbuilderng_forms Where id = ' . intval($this->_id) . ' And published = 1';
    }

    /**
     * Gets the currencies
     * @return array List of products
     */
    function getData()
    {
        $app = $this->app;

        // Lets load the data if it doesn't already exist
        if (empty($this->_data)) {
            $query = $this->_buildQuery();
            $this->_data = $this->_getList($query, 0, 1);

            if (!count($this->_data)) {
                throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
            }

            $labels = [];
            foreach ($this->_data as $data) {
                $data->items = [];
                $data->labels = [];
                $data->visible_cols = [];
                $data->visible_labels = [];
                $data->invalid_list_setup = false;
                if (!$this->frontend) {
                    throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
                }

                if (is_array($data->export_xls) && !count($data->export_xls)) {
                    throw new \Exception(Text::_('Not exportable error'), 404);
                }
                $data->form_id = $this->_id;
                if ($data->type && $data->reference_id) {
                    $data->form = \CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory::getForm($data->type, $data->reference_id);
                    if (!$data->form->exists) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
                    }
                    $searchable_elements = $this->listSupportService->getListSearchableElements($this->_id);
                    $data->labels = $data->form->getElementLabels();

                    if (
                        $app->getSession()->get('com_contentbuilderng.filter_signal.' . $this->_id, false)

                        && $data->allow_external_filter
                    ) {

                        $orders = array();
                        $filters = array();
                        $filters_from = array();
                        $filters_to = array();
                        $calendar_formats = array();

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

                        $this->_menu_filter = $new_filters;
                        $this->_menu_filter_order = $orders;
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
                    $order_types = array();
                    $ids = array();
                    foreach ($data->labels as $reference_id => $label) {
                        $ids[] = $this->getDatabase()->quote($reference_id);
                    }
                    if (count($ids)) {
                        $this->getDatabase()->setQuery("Select Distinct `id`,`label`, reference_id, `order_type` From #__contentbuilderng_elements Where form_id = " . intval($this->_id) . " And reference_id In (" . implode(',', $ids) . ") And published = 1 Order By ordering");
                        $rows = $this->getDatabase()->loadAssocList();
                        $ids = array();
                        foreach ($rows as $row) {
                            // cleaned up, in desired order
                            $ids[] = $row['reference_id'];
                            $labels[] = $row['label'];
                            $order_types['col' . $row['reference_id']] = $row['order_type'];
                        }
                    }
                    $data->visible_cols = $ids;
                    $data->visible_labels = $labels;
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
                    $isAdminPreview = $app->input->getBool('cb_preview_ok', false);
                    $publishedOnly = PublishedRecordVisibilityHelper::shouldRestrictToPublishedOnly($data, $isAdminPreview);
                    $ownerFilterUserId = $isAdminPreview
                        ? -1
                        : ($this->frontend && $data->own_only_fe ? (int) ($app->getIdentity()->id ?? 0) : -1);
                    $showAllLanguages = $isAdminPreview ? true : ($this->frontend ? $data->show_all_languages_fe : true);
                    $exportOrderDirection = $this->getState('formsd_filter_order_Dir')
                        ? (string) $this->getState('formsd_filter_order_Dir')
                        : (string) ($data->initial_order_dir ?? 'desc');

                    $data->items = $data->form->getListRecords(
                        $ids,
                        $this->getState('formsd_filter'),
                        $searchable_elements,
                        0,
                        0,
                        $this->getState('formsd_filter_order'),
                        $order_types,
                        $exportOrderDirection,
                        0,
                        $publishedOnly,
                        $ownerFilterUserId,
                        $this->getState('formsd_filter_state'),
                        $this->getState('formsd_filter_publish'),
                        $data->initial_sort_order == -1 ? -1 : 'col' . $data->initial_sort_order,
                        $data->initial_sort_order2 == -1 ? -1 : ((string) $data->initial_sort_order2 === '0' ? 'colRecord' : 'col' . $data->initial_sort_order2),
                        $data->initial_sort_order3 == -1 ? -1 : ((string) $data->initial_sort_order3 === '0' ? 'colRecord' : 'col' . $data->initial_sort_order3),
                        $this->_menu_filter,
                        $showAllLanguages,
                        $this->getState('formsd_filter_language'),
                        $act_as_registration,
                        $data,
                        $this->getState('article_category_filter')
                    );
                } else {
                    $data->invalid_list_setup = true;
                }

                return $data;
            }
        }
        return null;
    }
}
