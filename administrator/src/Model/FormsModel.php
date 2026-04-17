<?php

/**
 * ContentBuilder NG Forms Model (List).
 *
 * Handles CRUD and publish state for form in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Model
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Model\FormModel;

class FormsModel extends ListModel
{
    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        // IMPORTANT : on transmet factory/app/input à ListModel
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'a.id',
                'a.name',
                'a.tag',
                'a.title',
                'a.type',
                'a.published',
                'a.modified',
                'a.ordering'
            ];
        }

        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'a.ordering', $direction = 'ASC')
    {
        /** @var CMSApplication $app */
        $app = Factory::getApplication();

        // ✅ appels standard ListModel
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

        $input = $app->input;
        $user = $app->getIdentity();
        $profileKey = 'com_contentbuilderng.filter_tag';

        $filterTag = $app->getUserStateFromRequest($this->context . '.filter.tag', 'filter_tag', '', 'string');
        $hasRequestValue = $input->get('filter_tag', null) !== null;

        if ($user && $user->id) {
            if ($hasRequestValue) {
                $this->saveUserProfileValue((int) $user->id, $profileKey, (string) $filterTag);
            } elseif ($filterTag === '') {
                $savedTag = $this->loadUserProfileValue((int) $user->id, $profileKey);
                if ($savedTag !== '') {
                    $filterTag = $savedTag;
                    $app->setUserState($this->context . '.filter.tag', $filterTag);
                }
            }
        }

        $this->setState('filter.tag', (string) $filterTag);
    }

    private function loadUserProfileValue(int $userId, string $profileKey): string
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('profile_value'))
            ->from($db->quoteName('#__user_profiles'))
            ->where($db->quoteName('user_id') . ' = ' . (int) $userId)
            ->where($db->quoteName('profile_key') . ' = ' . $db->quote($profileKey))
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query, 0, 1);
        $value = $db->loadResult();

        if ($value === null) {
            return '';
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
            return $decoded;
        }

        return (string) $value;
    }

    private function saveUserProfileValue(int $userId, string $profileKey, string $value): void
    {
        $db = $this->getDatabase();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__user_profiles'))
                ->where($db->quoteName('user_id') . ' = ' . (int) $userId)
                ->where($db->quoteName('profile_key') . ' = ' . $db->quote($profileKey))
        );
        $db->execute();

        $columns = ['user_id', 'profile_key', 'profile_value', 'ordering'];
        $values = [
            (int) $userId,
            $db->quote($profileKey),
            $db->quote(json_encode($value, JSON_UNESCAPED_SLASHES)),
            1,
        ];

        $db->setQuery(
            $db->getQuery(true)
                ->insert($db->quoteName('#__user_profiles'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values))
        );
        $db->execute();
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Base query
        $query->select('a.*')
            ->from($db->quoteName('#__contentbuilderng_forms', 'a'));

        // Published filter (filter.state : 'P' or 'U')
        $filterState = strtoupper(trim((string) $this->getState('filter.state')));
        $isPublishedFilter = in_array($filterState, ['P', '1', 'PUBLISHED'], true);
        $isUnpublishedFilter = in_array($filterState, ['U', '0', 'UNPUBLISHED'], true);

        if ($isPublishedFilter || $isUnpublishedFilter) {
            $published = $isPublishedFilter ? 1 : 0;
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        // Tag filter.
        $filterTag = (string) $this->getState('filter.tag');
        if ($filterTag !== '') {
            $query->where($db->quoteName('a.tag') . ' = ' . $db->quote($filterTag));
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
                    . ' OR ' . $db->quoteName('a.tag') . ' LIKE ' . $token
                    . ' OR ' . $db->quoteName('a.title') . ' LIKE ' . $token
                    . ')'
                );
            }
        }

        // Ordering
        $ordering  = (string) $this->getState('list.ordering', 'a.ordering');
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC'));

        // Petite sécurité sur la direction
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        // Optionnel : whitelist rapide des colonnes triables
        $allowedOrdering = ['a.id', 'a.name', 'a.tag', 'a.title', 'a.type', 'a.published', 'a.created', 'a.modified', 'a.ordering'];
        if (!in_array($ordering, $allowedOrdering, true)) {
            $ordering = 'a.ordering';
        }

        $query->order($db->escape($ordering . ' ' . $direction));

        return $query;
    }

    /**
     * Adds a resolved source title for list rendering.
     */
    public function getItems(): array
    {
        $items = parent::getItems();

        if (!$items) {
            return $items;
        }

        foreach ($items as $item) {
            $item->source_title = '';

            if (!empty($item->type) && !empty($item->reference_id)) {
                $form = FormSourceFactory::getForm((string) $item->type, (int) $item->reference_id);
                if ($form && !empty($form->exists) && method_exists($form, 'getTitle')) {
                    $item->source_title = trim((string) $form->getTitle());
                }
            }

            if ($item->source_title === '') {
                $item->source_title = trim((string) ($item->title ?? ''));
            }
        }

        return $items;
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

        /** @var FormModel|null $formModel */
        $formModel = $factory->createModel('form', 'Administrator', ['ignore_request' => true]);

        if (!$formModel instanceof FormModel) {
            return false;
        }

        if (!$formModel->delete($pks)) {
            return false;
        }

        return true;
    }


    /*
     *
     * MAIN LIST AREA
     * 
     */

    // Tag non standard.
    public function getTags()
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('tag') . ' AS ' . $db->quoteName('tag'))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('tag') . ' <> ' . $db->quote(''))
            ->order($db->quoteName('tag') . ' ASC');

        $db->setQuery($query);
        return $db->loadObjectList();
    }
}
