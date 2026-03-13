<?php

/**
 * ContentBuilder NG Storages Model (List).
 *
 * Handles CRUD and publish state for storage in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Model
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 compatibility rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
class StoragesModel extends ListModel
{
    // Optionnel mais recommandé : définir le nom de la table (sans postfix)
    protected $table = 'Storage';

    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'a.id',
                'a.name',
                'a.title',
                'a.bytable',
                'a.display_in',
                'a.published',
                'a.modified'
            ];
        }

        // IMPORTANT : on transmet factory/app/input à AdminModel
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'a.ordering', $direction = 'ASC')
    {
        /** @var CMSApplication $app */
        $app = Factory::getApplication();

        // ✅ appels standard StorageModel
        parent::populateState($ordering, $direction);

        // Joomla 6 admin lists post list[limit]; also accept the flat limit field.
        $list = $app->input->get('list', [], 'array');
        if (is_array($list) && array_key_exists('limit', $list)) {
            $limit = (int) $list['limit'];
            $this->setState('list.limit', $limit);
            $app->setUserState($this->context . '.list.limit', $limit);
        } elseif ($app->input->get('limit', null, 'raw') !== null) {
            $limit = (int) $app->input->get('limit', 0, 'int');
            $this->setState('list.limit', $limit);
            $app->setUserState($this->context . '.list.limit', $limit);
        }

        // ✅ tes filtres custom, mais stockés dans l’état
        $filterState = $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'cmd');
        $this->setState('filter.state', $filterState);

        $search = trim((string) $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string'));
        $this->setState('filter.search', $search);
    }


    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Base query
        $query->select('a.*')
            ->from($db->quoteName('#__contentbuilderng_storages', 'a'));

        // Published filter.
        $filterState = strtoupper(trim((string) $this->getState('filter.state')));
        $isPublishedFilter = in_array($filterState, ['P', '1', 'PUBLISHED'], true);
        $isUnpublishedFilter = in_array($filterState, ['U', '0', 'UNPUBLISHED'], true);

        if ($isPublishedFilter || $isUnpublishedFilter) {
            $published = $isPublishedFilter ? 1 : 0;
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        // Text search (supports "id:123").
        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);

                if ($id > 0) {
                    $query->where($db->quoteName('a.id') . ' = ' . $id);
                }
            } else {
                $token = $db->quote('%' . $db->escape($search, true) . '%', false);

                $query->where(
                    '('
                    . $db->quoteName('a.name') . ' LIKE ' . $token
                    . ' OR ' . $db->quoteName('a.title') . ' LIKE ' . $token
                    . ')'
                );
            }
        }

        // Ordering (equivalent à ton buildOrderBy())
        $ordering  = (string) $this->getState('list.ordering', 'a.ordering');
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC'));

        // Petite sécurité sur la direction
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        // Optionnel : whitelist rapide des colonnes triables
        $allowedOrdering = ['a.id', 'a.name', 'a.title', 'a.bytable', 'a.published', 'a.ordering', 'a.modified'];
        if (!in_array($ordering, $allowedOrdering, true)) {
            $ordering = 'a.ordering';
        }

        $query->order($db->escape($ordering . ' ' . $direction));

        return $query;
    }


    /**
     * Supprime plusieurs formulaires
     * Appelée automatiquement par AdminController
     */
    public function delete($pks = null): bool
    {
        $pks = (array) $pks;
        ArrayHelper::toInteger($pks);

        $pks = array_values(array_filter($pks));
        if (!$pks) {
            return false;
        }

        $component = Factory::getApplication()->bootComponent('com_contentbuilderng');
        if (!$component instanceof ContentbuilderngComponent) {
            return false;
        }
        $factory = $component->getMVCFactory();

        $formModel = $factory->createModel('storage', 'Administrator', ['ignore_request' => true]);

        if (!$formModel) {
            return false;
        }

        if (!$formModel->delete($pks)) {
            return false;
        }

        return true;
    }

    /*
    function setPublished()
    {
        $cids = Factory::getApplication()->input->get('cid', [], 'array');
        ArrayHelper::toInteger($cids);
        $this->getDatabase()->setQuery(' Update #__contentbuilderng_storages ' .
            '  Set published = 1 Where id In ( ' . implode(',', $cids) . ')');
        $this->getDatabase()->execute();

    }

    function setUnpublished()
    {
        $cids = Factory::getApplication()->input->get('cid', [], 'array');
        ArrayHelper::toInteger($cids);
        $this->getDatabase()->setQuery(' Update #__contentbuilderng_storages ' .
            '  Set published = 0 Where id In ( ' . implode(',', $cids) . ')');
        $this->getDatabase()->execute();
    }*/

    /*
     *
     * MAIN LIST AREA
     * 
     */
/*
    private function buildOrderBy()
    {
        $app = Factory::getApplication();
        $option = 'com_contentbuilderng';

        $orderby = '';
        $filter_order = $this->getState('storages_filter_order');
        $filter_order_Dir = $this->getState('storages_filter_order_Dir');

        // Error handling is never a bad thing.
        if (!empty($filter_order) && !empty($filter_order_Dir)) {
            $orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
        }

        return $orderby;
    }
*/

    /**
     * @return string The query
     */
    /*
    private function _buildQuery()
    {
        $where = '';

        // PUBLISHED FILTER SELECTED?
        $filter_state = '';
        if ($this->getState('storages_filter_state') == 'P' || $this->getState('storages_filter_state') == 'U') {
            $published = 0;
            if ($this->getState('storages_filter_state') == 'P') {
                $published = 1;
            }

            $and = ' And';

            $filter_state .= ' published = ' . $published;
        }

        if ($filter_state != '') {
            $where = ' Where ';
        }

        return 'Select SQL_CALC_FOUND_ROWS * From #__contentbuilderng_storages ' . $where . $filter_state . $this->buildOrderBy();
    }*/


    function saveOrder()
    {
        $items = Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);

        $total = count($items);
        $row = $this->getTable('Storage');
        $groupings = array();

        $order = Factory::getApplication()->input->post->get('order', [], 'array');
        ArrayHelper::toInteger($order);

        // update ordering values
        for ($i = 0; $i < $total; $i++) {
            $row->load($items[$i]);
            if ($row->ordering != $order[$i]) {
                $row->ordering = $order[$i];
                if (!$row->save()) {
                    return false;
                }
            } // if
        } // for


        $row->reorder('');
    }

    /**
     * Gets the currencies
     * @return array List of products
     */
    /*    function getData()
    {
        // Lets load the data if it doesn't already exist
        if (empty($this->_data)) {
            $query = $this->_buildQuery();
            $this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_data;
    }
*/
}
