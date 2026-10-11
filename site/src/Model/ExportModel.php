<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Model;

use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Administrator\Service\RuntimeUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\ListSupportService;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuListConfigurationHelper;
use CB\Component\Contentbuilderng\Site\Helper\PublishedRecordVisibilityHelper;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListFieldFilterService;
use CB\Component\Contentbuilderng\Site\Service\MenuDataFilterService;

class ExportModel extends BaseDatabaseModel
{
    private function getComponent(): ContentbuilderngComponent
    {
        $component = RuntimeContextHelper::getApplication()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private readonly RuntimeUtilityService $runtimeUtilityService;
    private readonly ListSupportService $listSupportService;
    private $frontend = false;

    /** @var array<string, list<string>> */
    private array $recordFilters = [];

    /** @var array<string, int|string> */
    private array $recordFilterOrder = [];

    protected int $_id = 0;

    protected ?array $_data = null;
    private SiteApplication $app;

    public function __construct($config)
    {
        parent::__construct($config);

        $container = $this->getComponent()->getContainer();
        $app = $container->get(CMSApplicationInterface::class);
        if (!$app instanceof SiteApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }
        $this->app = $app;
        $this->runtimeUtilityService = new RuntimeUtilityService($app);
        $this->listSupportService = $container->get(ListSupportService::class);
        $this->frontend = $app->isClient('site');
        $option = 'com_contentbuilderng';

        $id = $app->getInput()->getInt('id', 0);

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
            $app->setUserState($option . 'formsd_filter_order', preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $app->getInput()->getString('filter_order', '')));
            $app->setUserState($option . 'formsd_filter_order_Dir', strtolower((string) $app->getInput()->getString('filter_order_Dir', '')));
            $app->setUserState($option . 'formsd_filter', $app->getInput()->get('filter', '', 'string'));
            $app->setUserState($option . 'formsd_filter_state', $app->getInput()->getInt('list_state_filter', 0));
            $app->setUserState($option . 'formsd_filter_publish', $app->getInput()->getInt('list_publish_filter', -1));
            $app->setUserState($option . 'formsd_filter_language', $app->getInput()->getCmd('list_language_filter', ''));
            $filter_order     = $app->getInput()->getCmd('filter_order', '');
            $filter_order_Dir = $app->getInput()->getCmd('filter_order_Dir', '');
            $filter           = $app->getInput()->get('filter', '', 'string');
            $filter_state     = $app->getInput()->getInt('list_state_filter', 0);
            $filter_publish   = $app->getInput()->getInt('list_publish_filter', -1);
            $filter_language  = $app->getInput()->getCmd('list_language_filter', '');
        }

        $this->setState('formsd_filter_state', $filter_state);
        $this->setState('formsd_filter_publish', $filter_publish);
        $this->setState('formsd_filter_language', empty($filter_language) ? null : $filter_language);
        $this->setState('formsd_filter', $filter);
        $this->setState('formsd_filter_order', $filter_order);
        $this->setState('formsd_filter_order_Dir', $filter_order_Dir);

        $this->recordFilters = MenuDataFilterService::decode(
            $app->getInput()->get(MenuDataFilterService::INPUT_NAME, '', 'raw'),
            $this->runtimeUtilityService
        );

        $app->getSession()->set($option . 'formsd_id', $this->_id);
    }

    public function setId($id)
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
    private function buildQuery()
    {
        return 'Select * From #__contentbuilderng_forms Where id = ' . intval($this->_id) . ' And published = 1';
    }

    /**
     * Gets the currencies
     * @return array List of products
     */
    public function getData()
    {
        $app = $this->app;

        // Lets load the data if it doesn't already exist
        if (empty($this->_data)) {
            $query = $this->buildQuery();
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
                    throw new \Exception(Text::_('COM_CONTENTBUILDERNG_NOT_EXPORTABLE_ERROR'), 404);
                }
                $data->form_id = $this->_id;
                if ($data->type && $data->reference_id) {
                    $data->form = \CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory::getForm($data->type, $data->reference_id);
                    if (!$data->form->exists) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
                    }
                    $searchable_elements = $this->listSupportService->getListSearchableElements($this->_id);
                    $searchable_elements = MenuListConfigurationHelper::filterSearchableElements(
                        $searchable_elements,
                        (string) $app->getInput()->getString('cb_menu_search_fields', '')
                    );
                    $data->labels = $data->form->getElementLabels();

                    if (
                        $app->getSession()->get('com_contentbuilderng.filter_signal.' . $this->_id, false)

                        && $data->allow_external_filter
                    ) {
                        $orders = array();
                        $filters = array();
                        $filters_from = array();
                        $filters_to = array();

                        $filters = $app->getSession()->get('com_contentbuilderng.filter.' . $this->_id, array());
                        $filters_from = $app->getSession()->get('com_contentbuilderng.calendar_filter_from.' . $this->_id, array());
                        $filters_to = $app->getSession()->get('com_contentbuilderng.calendar_filter_to.' . $this->_id, array());
                        $filter_keywords = $app->getSession()->get('com_contentbuilderng.filter_keywords.' . $this->_id, '');
                        $filter_cats = $app->getSession()->get('com_contentbuilderng.filter_article_categories.' . $this->_id, -1);

                        if ($filter_keywords != '') {
                            $this->setState('formsd_filter', $filter_keywords);
                        }

                        if ($filter_cats != -1) {
                            $this->setState('article_category_filter', $filter_cats);
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
                            $this->recordFilters[$filterKey] = $filterTerms;
                        }

                        foreach ($orders as $filterKey => $orderValue) {
                            $this->recordFilterOrder[$filterKey] = $orderValue;
                        }
                    }

                    $ordered_extra_title = '';
                    foreach ($this->recordFilterOrder as $order_key => $order) {
                        if (isset($this->recordFilters[$order_key])) {
                            // range test
                            $is_range = strstr(strtolower(implode(',', $this->recordFilters[$order_key])), '@range') !== false;
                            $is_match = strstr(strtolower(implode(',', $this->recordFilters[$order_key])), '@match') !== false;
                            if ($is_range) {
                                $ex = explode('/', implode(', ', $this->recordFilters[$order_key]));
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
                                    } elseif (count($ex2) > 0) {
                                        $out = Text::_('COM_CONTENTBUILDERNG_FROM2') . ' ' . trim($val);
                                    }
                                    if ($out) {
                                        $this->recordFilters[$order_key] = $ex;
                                        $ordered_extra_title .= ' &raquo; ' . htmlspecialchars($data->labels[$order_key], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($out, ENT_QUOTES, 'UTF-8');
                                    }
                                }
                            } elseif ($is_match) {
                                $ex = explode('/', implode(', ', $this->recordFilters[$order_key]));
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
                                        $this->recordFilters[$order_key] = $ex;
                                        $ordered_extra_title .= ' &raquo; ' . htmlspecialchars($data->labels[$order_key], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($out, ENT_QUOTES, 'UTF-8');
                                    }
                                }
                            } else {
                                $ordered_extra_title .= ' &raquo; ' . htmlspecialchars($data->labels[$order_key], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars(implode(', ', $this->recordFilters[$order_key]), ENT_QUOTES, 'UTF-8');
                            }
                        }
                    }
                    $newMenuCustomColumns = $app->getInput()->getInt('cb_new_list_menu', 0) === 1;
                    $order_types = array();
                    $ids = array();
                    $db = $this->getDatabase();
                    foreach ($data->labels as $reference_id => $label) {
                        $ids[] = $db->quote($reference_id);
                    }
                    if (count($ids)) {
                        $query = $db->getQuery(true)
                            ->select('DISTINCT ' . implode(', ', $db->quoteName(['id', 'label', 'reference_id', 'order_type'])))
                            ->from($db->quoteName('#__contentbuilderng_elements'))
                            ->where($db->quoteName('form_id') . ' = ' . (int) $this->_id)
                            ->where($db->quoteName('reference_id') . ' IN (' . implode(',', $ids) . ')')
                            ->where($db->quoteName('published') . ' = 1')
                            ->where($db->quoteName('export_include') . ' = 1')
                            ->where($db->quoteName('type') . ' <> ' . $db->quote('hidden'))
                            ->order($db->quoteName('ordering'));
                        $db->setQuery($query);
                        $rows = $this->getDatabase()->loadAssocList();
                        $ids = array();
                        foreach ($rows as $row) {
                            // cleaned up, in desired order
                            $ids[] = $row['reference_id'];
                            $labels[] = $row['label'];
                            $order_types['col' . $row['reference_id']] = $row['order_type'];
                        }
                        if ($newMenuCustomColumns) {
                            $ids = MenuListConfigurationHelper::selectElementsInMenuOrder(
                                $ids,
                                (string) $app->getInput()->getString('cb_menu_export_fields', '')
                            );
                            $ids = MenuListConfigurationHelper::filterSearchableElements(
                                $ids,
                                (string) $app->getInput()->getString('cb_menu_published_fields', '')
                            );
                            $labelByReference = [];
                            foreach ($rows as $row) {
                                $labelByReference[(string) $row['reference_id']] = (string) $row['label'];
                            }
                            $labels = array_map(
                                static fn(int|string $reference): string => $labelByReference[(string) $reference] ?? (string) $reference,
                                $ids
                            );
                        }
                        $rawExportFields = trim((string) $app->getInput()->getString('cblist_fields', ''));
                        if (
                            !$newMenuCustomColumns
                            && $rawExportFields !== ''
                            && EmbeddedListFieldFilterService::isEmbeddedRequest($app->getInput()->getCmd('cblist_embed', ''))
                        ) {
                            $match = EmbeddedListFieldFilterService::matchFieldSelectors(
                                $ids,
                                (array) $data->form->getElementNames(),
                                $rawExportFields
                            );
                            $labelByReference = [];
                            foreach ($rows as $row) {
                                $labelByReference[(string) $row['reference_id']] = (string) $row['label'];
                            }
                            $ids = $match['columns'];
                            $labels = array_map(
                                static fn(int|string $reference): string => $labelByReference[(string) $reference] ?? (string) $reference,
                                $ids
                            );
                        }
                    }
                    if ($newMenuCustomColumns) {
                        $order_types += MenuListConfigurationHelper::getMenuSortTypes(
                            $this->getDatabase(),
                            (int) $this->_id,
                            (string) $app->getInput()->getString('cb_menu_published_fields', '')
                        );
                    }
                    $data->visible_cols = $ids;
                    $data->visible_labels = $labels;
                    $data->export_order_types = $order_types;
                    $data->export_source_types = [];
                    if (($data->type ?? '') === 'com_breezingformsng') {
                        $positiveIds = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
                        if ($positiveIds !== []) {
                            $sourceTypeQuery = $db->getQuery(true)
                                ->select($db->quoteName(['id', 'type']))
                                ->from($db->quoteName('#__facileforms_elements'))
                                ->where($db->quoteName('id') . ' IN (' . implode(',', $positiveIds) . ')');
                            $db->setQuery($sourceTypeQuery);
                            foreach ((array) $db->loadAssocList() as $sourceElement) {
                                $data->export_source_types['col' . (int) $sourceElement['id']] = (string) $sourceElement['type'];
                            }
                        }
                    } elseif (method_exists($data->form, 'getEditableElementTypes')) {
                        foreach ((array) $data->form->getEditableElementTypes() as $referenceId => $sourceType) {
                            $data->export_source_types['col' . $referenceId] = (string) $sourceType;
                        }
                    }
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
                    $isAdminPreview = $app->getInput()->getBool('cb_preview_ok', false);
                    $publishedOnly = PublishedRecordVisibilityHelper::shouldRestrictToPublishedOnly($data, $isAdminPreview);
                    $ownerFilterUserId = $isAdminPreview
                        ? -1
                        : ($this->frontend && $data->own_only_fe ? (int) ($app->getIdentity()->id ?? 0) : -1);
                    $showAllLanguages = $isAdminPreview ? true : ($this->frontend ? $data->show_all_languages_fe : true);
                    $exportOrdering = (string) $this->getState('formsd_filter_order');
                    $exportOrderDirection = $this->getState('formsd_filter_order_Dir')
                        ? (string) $this->getState('formsd_filter_order_Dir')
                        : (string) ($data->initial_order_dir ?? 'desc');
                    $embeddedSort = trim((string) $app->getInput()->getString('cblist_sort', ''));
                    if (
                        $exportOrdering === ''
                        && $embeddedSort !== ''
                        && EmbeddedListFieldFilterService::isEmbeddedRequest(
                            $app->getInput()->getCmd('cblist_embed', '')
                        )
                    ) {
                        $sortColumns = [];
                        $unknownSortFields = [];
                        foreach (EmbeddedListFieldFilterService::parseSelectors($embeddedSort) as $selector) {
                            if (strcasecmp($selector, 'ID') === 0) {
                                $sortColumns[] = 'Record';
                                continue;
                            }
                            $match = EmbeddedListFieldFilterService::matchSelectors(
                                $newMenuCustomColumns
                                    ? array_map(static fn(string $key): string => substr($key, 3), array_keys($order_types))
                                    : $ids,
                                (array) $data->form->getElementNames(),
                                $selector
                            );
                            if ($match['unknown'] !== [] || $match['columns'] === []) {
                                $unknownSortFields[] = $selector;
                                continue;
                            }
                            $sortColumns[] = (string) $match['columns'][0];
                        }
                        if ($unknownSortFields === []) {
                            $directions = explode('|', strtolower(trim(
                                (string) $app->getInput()->getString('cblist_dir', 'asc')
                            )));
                            $exportOrdering = implode('|', array_map(
                                static fn(int|string $column): string => 'col' . $column,
                                $sortColumns
                            ));
                            $exportOrderDirection = implode('|', array_map(
                                static fn(string $direction): string => $direction === 'desc' ? 'desc' : 'asc',
                                $directions
                            ));
                        }
                    }
                    $embeddedLimit = $app->getInput()->getInt('cblist_limit', 0);
                    if ($embeddedLimit < 1 || $embeddedLimit > 5000) {
                        $embeddedLimit = 0;
                    }

                    $data->items = $data->form->getListRecords(
                        $ids,
                        $this->getState('formsd_filter'),
                        $searchable_elements,
                        0,
                        $embeddedLimit,
                        $exportOrdering,
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
                        $this->recordFilters,
                        $showAllLanguages,
                        $this->getState('formsd_filter_language'),
                        $act_as_registration,
                        $data,
                        $this->getState('article_category_filter'),
                        false
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
