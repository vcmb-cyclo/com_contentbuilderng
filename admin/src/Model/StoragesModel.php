<?php

/**
 * ContentBuilder NG Storages Model (List).
 *
 * Handles CRUD and publish state for storage in the admin interface.
 *
 * @package     ContentBuilderNG
 * @subpackage  Administrator.Model
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @copyright   Copyright © 2024–2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
class StoragesModel extends ListModel
{
    private function getComponent(): ContentbuilderngComponent
    {
        $component = RuntimeContextHelper::getApplication()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private function getApp(): CMSApplication
    {
        $app = $this->getComponent()->getContainer()->get(CMSApplicationInterface::class);

        if (!$app instanceof CMSApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    // Optionnel mais recommandé : définir le nom de la table (sans postfix)
    protected $table = 'Storage';

    public function __construct(
        array $config = [],
        ?MVCFactoryInterface $factory = null
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

    #[\Override]
    protected function populateState($ordering = 'a.ordering', $direction = 'ASC')
    {
        $app = $this->getApp();

        // ✅ appels standard StorageModel
        parent::populateState($ordering, $direction);

        // Joomla 6 admin lists post list[limit]; also accept the flat limit field.
        $list = $app->getInput()->get('list', [], 'array');
        if (is_array($list) && array_key_exists('limit', $list)) {
            $limit = (int) $list['limit'];
            $this->setState('list.limit', $limit);
            $app->setUserState($this->context . '.list.limit', $limit);
        } elseif ($app->getInput()->get('limit', null, 'raw') !== null) {
            $limit = (int) $app->getInput()->get('limit', 0, 'int');
            $this->setState('list.limit', $limit);
            $app->setUserState($this->context . '.list.limit', $limit);
        }

        // ✅ tes filtres custom, mais stockés dans l’état
        $filterState = $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'cmd');
        $this->setState('filter.state', $filterState);

        $search = trim((string) $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string'));
        $this->setState('filter.search', $search);
    }


    #[\Override]
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Base query
        $query->select('a.*')
            ->from($db->quoteName('#__contentbuilderng_storages') . ' AS ' . $db->quoteName('a'));

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

        $factory = $this->getComponent()->getMVCFactory();

        /** @var StorageModel|null $storageModel */
        $storageModel = $factory->createModel('storage', 'Administrator', ['ignore_request' => true]);

        if (!$storageModel instanceof StorageModel) {
            return false;
        }

        if (!$storageModel->delete($pks)) {
            return false;
        }

        return true;
    }

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
        $input = $this->getApp()->getInput();
        $items = $input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);

        $total = count($items);
        $row = $this->getTable('Storage');
        $groupings = array();

        $order = $input->post->get('order', [], 'array');
        ArrayHelper::toInteger($order);

        // update ordering values
        for ($i = 0; $i < $total; $i++) {
            $row->load($items[$i]);
            if ($row->ordering != $order[$i]) {
                $row->ordering = $order[$i];
                if (!$row->check() || !$row->store()) {
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
