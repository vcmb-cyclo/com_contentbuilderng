<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\Database\QueryInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

class UsersModel extends ListModel
{
    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                // #__users
                'u.id',
                'u.name',
                'u.username',
                'u.email',
                // #__contentbuilderng_users (alias a)
                'a.verified_view', 
                'a.verified_new', 
                'a.verified_edit',
                'a.records', 
                'a.published'
            ];
        }

        // IMPORTANT : on transmet factory/app/input à ListModel
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'u.id', $direction = 'ASC')
    {
        /** @var CMSApplication $app */
        $app = Factory::getApplication();

        parent::populateState($ordering, $direction);

        // Recherche (champ standard dans la toolbar / layout)
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        // Exemple de filtre state (si tu l’utilises)
        $state = $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'cmd');
        $this->setState('filter.state', $state);

        // form_id (tu en as besoin pour le JOIN)
        $formId = $app->input->getInt('form_id', 0);
        $this->setState('filter.form_id', $formId);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $formId = (int) $this->getState('filter.form_id', 0);

        $query
            ->select([
                'u.*',
                // Valeurs CB, avec défauts si pas de ligne jointe
                'COALESCE(a.verified_view, 0) AS verified_view',
                'COALESCE(a.verified_new, 0)  AS verified_new',
                'COALESCE(a.verified_edit, 0) AS verified_edit',
                'COALESCE(a.records, 0)       AS records',
                'COALESCE(a.published, 1)     AS published',
            ])
            ->from($db->quoteName('#__users', 'u'))
            ->join(
                'LEFT',
                $db->quoteName('#__contentbuilderng_users', 'a')
                . ' ON ' . $db->quoteName('a.userid') . ' = ' . $db->quoteName('u.id')
                . ' AND ' . $db->quoteName('a.form_id') . ' = ' . (int) $formId
            );

        // filter.search (name/username/email/id)
        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $like = '%' . $db->escape($search, true) . '%';

            $conditions = [
                $db->quoteName('u.name')     . ' LIKE ' . $db->quote($like, false),
                $db->quoteName('u.username') . ' LIKE ' . $db->quote($like, false),
                $db->quoteName('u.email')    . ' LIKE ' . $db->quote($like, false),
            ];

            if (ctype_digit($search)) {
                $conditions[] = $db->quoteName('u.id') . ' = ' . (int) $search;
            }

            $query->where('(' . implode(' OR ', $conditions) . ')');
        }

        // filter.state : exemple (à adapter à ton UI)
        // On conserve ici le mapping historique : P=published, U=unpublished sur a.published
        $state = (string) $this->getState('filter.state');
        if ($state === 'P') {
            $query->where('COALESCE(a.published, 1) = 1');
        } elseif ($state === 'U') {
            $query->where('COALESCE(a.published, 1) = 0');
        }

        // ORDER BY standard ListModel
        $ordering  = (string) $this->getState('list.ordering', 'u.id');
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC'));
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $allowed = [
            'u.id', 'u.name', 'u.username', 'u.email',
            'a.verified_view', 'a.verified_new', 'a.verified_edit',
            'a.records', 'a.published',
        ];

        if (!in_array($ordering, $allowed, true)) {
            $ordering = 'u.id';
        }

        $query->order($db->escape($ordering . ' ' . $direction));

        return $query;
    }

    // Modernized publish/unpublish actions with query builder and typed input.
    public function setPublished(): void
    {
        $app  = Factory::getApplication();
        $db   = $this->getDatabase();
        $formId = (int) $app->input->getInt('form_id', 0);

        $cids = (array) $app->input->get('cid', [], 'array');
        ArrayHelper::toInteger($cids);
        $cids = array_values(array_filter($cids));

        if (!$formId || !$cids) {
            return;
        }

        // Assure une ligne pour chaque user (upsert simple)
        foreach ($cids as $uid) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__contentbuilderng_users'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
                ->where($db->quoteName('userid') . ' = ' . (int) $uid);
            $db->setQuery($query);

            if (!$db->loadResult()) {
                $insert = $db->getQuery(true)
                    ->insert($db->quoteName('#__contentbuilderng_users'))
                    ->columns([$db->quoteName('form_id'), $db->quoteName('userid'), $db->quoteName('published')])
                    ->values((int) $formId . ', ' . (int) $uid . ', 1');
                $db->setQuery($insert)->execute();
            }
        }

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__contentbuilderng_users'))
            ->set($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('userid') . ' IN (' . implode(',', $cids) . ')');

        $db->setQuery($update)->execute();
    }

    public function setUnpublished(): void
    {
        $app  = Factory::getApplication();
        $db   = $this->getDatabase();
        $formId = (int) $app->input->getInt('form_id', 0);

        $cids = (array) $app->input->get('cid', [], 'array');
        ArrayHelper::toInteger($cids);
        $cids = array_values(array_filter($cids));

        if (!$formId || !$cids) {
            return;
        }

        foreach ($cids as $uid) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__contentbuilderng_users'))
                ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
                ->where($db->quoteName('userid') . ' = ' . (int) $uid);
            $db->setQuery($query);

            if (!$db->loadResult()) {
                $insert = $db->getQuery(true)
                    ->insert($db->quoteName('#__contentbuilderng_users'))
                    ->columns([$db->quoteName('form_id'), $db->quoteName('userid'), $db->quoteName('published')])
                    ->values((int) $formId . ', ' . (int) $uid . ', 1');
                $db->setQuery($insert)->execute();
            }
        }

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__contentbuilderng_users'))
            ->set($db->quoteName('published') . ' = 0')
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('userid') . ' IN (' . implode(',', $cids) . ')');

        $db->setQuery($update)->execute();
    }
}
