<?php

/**
 * ContentBuilder NG Elements Model.
 *
 * Handles CRUD and publish state for element in the admin interface.
 *
 * @package     ContentBuilderNG
 * @subpackage  Administrator.Model
 * @author      Xavier DANO
 * @copyright   Copyright © 2024–2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */


namespace CB\Component\Contentbuilderng\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseQuery;
use Joomla\Utilities\ArrayHelper;
use CB\Component\Contentbuilderng\Administrator\Table\ElementoptionsTable;

class ElementsModel extends ListModel
{
    /**
     * ID du formulaire courant (form_id)
     */
    protected int $formId = 0;

    /**
     * Constructor.
     */
    public function __construct(
        array $config = [],
        ?MVCFactoryInterface $factory = null
    ) {
        // IMPORTANT : on transmet factory/app/input à ListModel
        parent::__construct($config, $factory);

        // Colonnes autorisées pour le filtrage et le tri (très important pour la sécurité)
        $this->filter_fields = [
            'id',
            'type',
            'label',
            'published',
            'linkable',
            'editable',
            'api_allowed',
            'list_include',
            'search_include',
            'ordering',
            'order_type'
        ];
    }


    #[\Override]
    public function getTable($name = 'Elementoptions', $prefix = 'CB\\Component\\Contentbuilderng\\Administrator\\Table\\', $options = [])
    {
        $db = $this->getDatabase();

        // Direct instantiation for consistent table resolution.
        // Keep compatibility with both singular and plural callers.
        if ($name === 'Elementoption' || $name === 'Elementoptions') {
            return new ElementoptionsTable($db);
        }

        // Fallback standard Joomla si tu as d'autres tables ailleurs
        return parent::getTable($name, $prefix, $options);
    }


    
    public function setFormId(int $formid): void
    {
        $this->formId = $formid;
    }

    private function getApp(): CMSApplication
    {
        return Factory::getApplication();
    }

    private function getInput()
    {
        return $this->getApp()->getInput();
    }

    private function resolveCurrentFormId(): int
    {
        $formId = (int) $this->getInput()->getInt('id', 0);

        if (!$formId) {
            $formId = (int) $this->getState('form.id', 0);
        }

        return $formId;
    }


    /**
     * Méthode pour initialiser les états (filtres, pagination, tri)
     */
    #[\Override]
    protected function populateState($ordering = 'ordering', $direction = 'asc')
    {
        /** @var CMSApplication $app */
        $app = Factory::getApplication();

        // Récupération du form_id depuis l'input (obligatoire pour cette vue)
            // 1) priorité à la propriété (injectée depuis la vue)
        $formId = (int) $this->formId;

        // Fallback si on arrive sans id dans l'URL (cas après save)
        // 2) Sinon URL (admin)
        if (!$formId) {
            $formId = $app->getInput()->getInt('id', 0);
        }

        // 3) Sinon POST
        if (!$formId) {
            $jform  = $app->getInput()->post->get('jform', [], 'array');
            $formId = (int) ($jform['id'] ?? 0);
        }

        $this->formId = $formId;        
        $this->setState('form.id', $formId);
        $this->formId = $formId;


        $context = $this->context ?: 'com_contentbuilderng.elements';
        $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $isContentbuilderReferrer = str_contains($referrer, 'option=com_contentbuilderng');
        $isFormEditReferrer = str_contains($referrer, 'view=form') && str_contains($referrer, 'layout=edit');
        $isFormTaskReferrer = str_contains($referrer, 'task=form.');
        $fromSameFormEdit = $isContentbuilderReferrer && ($isFormEditReferrer || $isFormTaskReferrer);
        $enteredFromOtherView = !$fromSameFormEdit;

        // Filtre sur published
        $published = $app->getUserStateFromRequest('com_contentbuilderng.elements.filter.published', 'filter_published', '', 'string');
        $this->setState('filter.published', $published);

        // Recherche (si tu veux ajouter un champ de recherche sur label ou type)
        $search = $app->getUserStateFromRequest('com_contentbuilderng.elements.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        // Pagination (scope local a la liste des elements)
        $limit = $app->getUserStateFromRequest($context . '.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $this->setState('list.limit', $limit);

        $startDefault = $enteredFromOtherView ? 0 : (int) $app->getUserState($context . '.list.start', 0);
        $value = $app->getUserStateFromRequest($context . '.list.start', 'limitstart', $startDefault, 'int');
        $limitstart = ($limit != 0 ? (floor($value / $limit) * $limit) : 0);
        $this->setState('list.start', $limitstart);

        // Tri
        $list = (array) $app->getInput()->get('list', [], 'array');

        $orderCol = (string) ($list['ordering'] ?? $app->getUserState($context . '.list.ordering', 'ordering'));
        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'ordering';
        }

        $orderDirn = strtoupper((string) ($list['direction'] ?? $app->getUserState($context . '.list.direction', 'ASC')));
        $orderDirn = $orderDirn === 'DESC' ? 'DESC' : 'ASC';

        $app->setUserState($context . '.list.ordering', $orderCol);
        $app->setUserState($context . '.list.direction', $orderDirn);

        parent::populateState($ordering, $direction);

        // Joomla core may reset list.start to 0 when list[] exists without list[limit].
        // Re-apply an explicit effective limit/start from request values used by this screen.
        $listInput = (array) $app->getInput()->get('list', [], 'array');
        if (array_key_exists('limit', $listInput)) {
            $effectiveLimit = (int) $listInput['limit'];
        } elseif ($app->getInput()->get('limit', null, 'raw') !== null) {
            $effectiveLimit = (int) $app->getInput()->getInt('limit', (int) $limit);
        } elseif ($enteredFromOtherView) {
            $effectiveLimit = (int) ($limit ?: $app->get('list_limit'));
        } else {
            $effectiveLimit = (int) $app->getUserState($context . '.list.limit', (int) ($limit ?: $app->get('list_limit')));
        }
        $effectiveLimit = max(0, $effectiveLimit);

        if (array_key_exists('start', $listInput)) {
            $requestedStart = (int) $listInput['start'];
        } elseif ($app->getInput()->get('start', null, 'raw') !== null) {
            $requestedStart = (int) $app->getInput()->getInt('start', 0);
        } elseif ($app->getInput()->get('limitstart', null, 'raw') !== null) {
            $requestedStart = (int) $app->getInput()->getInt('limitstart', 0);
        } elseif ($enteredFromOtherView) {
            $requestedStart = 0;
        } else {
            $requestedStart = (int) $app->getUserState($context . '.list.start', 0);
        }

        $effectiveStart = ($effectiveLimit !== 0) ? (int) (floor($requestedStart / $effectiveLimit) * $effectiveLimit) : 0;
        $effectiveStart = max(0, $effectiveStart);

        $this->setState('list.limit', $effectiveLimit);
        $this->setState('list.start', $effectiveStart);
        // Keep alias fields in sync for consumers still using these keys.
        $this->setState('limit', $effectiveLimit);
        $this->setState('limitstart', $effectiveStart);

        $app->setUserState($context . '.list.limit', $effectiveLimit);
        $app->setUserState($context . '.list.start', $effectiveStart);
        $app->setUserState($context . '.limitstart', $effectiveStart);

        $this->setState('list.ordering', $orderCol);
        $this->setState('list.direction', $orderDirn);
    }

    /**
     * Construction de la requête pour récupérer la liste des éléments
     */

    #[\Override]
    protected function getListQuery(): DatabaseQuery
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Sélectionner les colonnes pertinentes de #__contentbuilderng_elements
        $query->select(
            [
                $db->quoteName('id'),
                $db->quoteName('form_id'),
                $db->quoteName('reference_id'),
                $db->quoteName('type'),
                $db->quoteName('change_type'),
                $db->quoteName('options'),
                $db->quoteName('custom_init_script'),
                $db->quoteName('custom_action_script'),
                $db->quoteName('custom_validation_script'),
                $db->quoteName('validation_message'),
                $db->quoteName('default_value'),
                $db->quoteName('hint'),
                $db->quoteName('label'),
                $db->quoteName('list_include'),
                $db->quoteName('search_include'),
                $db->quoteName('item_wrapper'),
                $db->quoteName('wordwrap'),
                $db->quoteName('linkable'),
                $db->quoteName('editable'),
                $db->quoteName('api_allowed'),
                $db->quoteName('validations'),
                $db->quoteName('published'),
                $db->quoteName('order_type'),
                $db->quoteName('ordering'),
            ]
        )
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $this->getState('form.id'));  // Filtre par form_id

        // Filtre publié (si défini)
        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $query->where($db->quoteName('published') . ' = ' . (int) $published);
        }

        // Recherche sur le label ou le type (si défini)
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(' . $db->quoteName('label') . ' LIKE ' . $search .
                ' OR ' . $db->quoteName('type') . ' LIKE ' . $search . ')');
        }

        // Tri sécurisé (grâce à filter_fields)
        $orderCol  = (string) $this->getState('list.ordering', 'ordering');
        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'ordering';
        }
        $orderDirn = strtoupper((string) $this->getState('list.direction', 'ASC'));
        $orderDirn = $orderDirn === 'DESC' ? 'DESC' : 'ASC';

        // Application du tri (tri principal + ordre de secours sur "ordering")
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn) . ', ' . $db->escape('ordering') . ' ASC');

        return $query;
    }



    public function move(int $direction): bool
    {
        // Assure formId même si populateState n’a pas tourné
        $formId = $this->resolveCurrentFormId();
        if (!$formId) {
            return false;
        }

        $cid = $this->getInput()->get('cid', [], 'array');
        ArrayHelper::toInteger($cid);
        $pk = (int) ($cid[0] ?? 0);

        if (!$pk) {
            return false;
        }

        $table = $this->getTable('Elementoptions');

        if (!$table->load($pk)) {
            return false;
        }

        // Grouper le déplacement dans le formulaire
        return (bool) $table->move((int) $direction, 'form_id = ' . (int) $formId);
    }

    public function saveorder($pks = null, $order = null): bool
    {
        $formId = $this->resolveCurrentFormId();
        if (!$formId) {
            return false;
        }

        $pks   = array_values((array) ($pks ?? []));
        $order = array_values((array) ($order ?? []));

        ArrayHelper::toInteger($pks);
        ArrayHelper::toInteger($order);

        if (count($pks) !== count($order)) {
            return false;
        }

        $submittedOrder = [];
        foreach ($pks as $i => $id) {
            if ($id > 0) {
                $submittedOrder[$id] = max(0, (int) ($order[$i] ?? 0));
            }
        }

        if (empty($submittedOrder)) {
            return false;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('ordering'),
            ])
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($query);
        $rows = (array) $db->loadAssocList();

        $rowsById = [];
        $moveIds = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $currentOrder = (int) ($row['ordering'] ?? 0);
            $rowsById[$id] = [
                'id' => $id,
                'target' => array_key_exists($id, $submittedOrder) ? max(1, $submittedOrder[$id]) : max(1, $currentOrder),
                'current' => $currentOrder,
            ];

            if (array_key_exists($id, $submittedOrder) && max(1, $submittedOrder[$id]) !== max(1, $currentOrder)) {
                $moveIds[$id] = true;
            }
        }

        $orderedRows = [];
        $movedRows = [];

        foreach ($rowsById as $id => $row) {
            if (isset($moveIds[$id])) {
                $movedRows[] = $row;
                continue;
            }

            $orderedRows[] = $row;
        }

        usort($movedRows, static function (array $a, array $b): int {
            if ($a['target'] !== $b['target']) {
                return $a['target'] <=> $b['target'];
            }

            return $a['current'] <=> $b['current'];
        });

        foreach ($movedRows as $row) {
            $position = min(max(1, (int) $row['target']), count($orderedRows) + 1);
            array_splice($orderedRows, $position - 1, 0, [$row]);
        }

        $table = $this->getTable('Elementoptions');

        $n = 1;
        foreach ($orderedRows as $row) {
            if (!$table->load((int) $row['id'])) {
                return false;
            }

            // sécurité : ne touche que ce form_id
            if ((int) $table->form_id !== $formId) {
                continue;
            }

            $table->ordering = $n++;

            if (!$table->store()) {
                return false;
            }
        }

        $table->reorder('form_id = ' . (int) $formId);

        return true;
    }


    public function reorder($pks = null, $delta = 0, $where = ''): bool
    {
        $formId = $this->resolveCurrentFormId();
        if (!$formId) {
            return false;
        }

        $table = $this->getTable('Elementoptions');
        return (bool) $table->reorder('form_id = ' . (int) $formId);
    }



    private function buildOrderBy()
    {
        $orderby = '';
        $filter_order = $this->getState('elements_filter_order');
        $filter_order_Dir = $this->getState('elements_filter_order_Dir');

        /* Error handling is never a bad thing*/
        if (!empty($filter_order) && !empty($filter_order_Dir)) {
            $orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir . ' , ordering ';
        } else {
            $orderby = ' ORDER BY ordering ';
        }

        return $orderby;
    }


    function _buildQuery()
    {
        $filter_state = '';
        if ($this->getState('elements_filter_state') == 'P' || $this->getState('elements_filter_state') == 'U') {
            $published = 0;
            if ($this->getState('elements_filter_state') == 'P') {
                $published = 1;
            }

            $filter_state .= ' And published = ' . $published;
        }

        return "Select * From #__contentbuilderng_elements Where form_id = " . $this->formId . $filter_state . $this->buildOrderBy();
    }

    // Deprecated compatibility path
    function getData(int $formId)
    {
        $this->formId = $formId;
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $this->formId)
            ->order($db->quoteName('ordering') . ' ASC')
            ->setLimit(
                (int) $this->getState('limit', 0),
                (int) $this->getState('limitstart', 0)
            );
        $db->setQuery($query);
        $elements = $db->loadObjectList();

        return $elements;
    }

    // Deprecated path: unused?
    function getAllElements(int $formId)
    {
        $this->formId = $formId;
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $this->formId)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Retourne le nombre de pages d'éléments (utilisé pour la pagination dans l'interface)
     * À adapter selon la logique originale (souvent basé sur le total d'éléments)
     */
    /*
    public function getPagesCounter()
    {
        // Exemple simple : total d'éléments / limite par page, arrondi au supérieur
        $total = $this->getTotal(); // Méthode héritée de ListModel
        $limit = $this->getState('list.limit', 10);
        if ($limit == 0) {
            return 1;
        }
        return (int) ceil($total / $limit);
    }*/
}
