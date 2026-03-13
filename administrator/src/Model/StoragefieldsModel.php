<?php
/**
 * ContentBuilder NG Storage fields list model.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Model
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;
use Joomla\Utilities\ArrayHelper;
use CB\Component\Contentbuilderng\Administrator\Table\StorageFieldsTable;

class StoragefieldsModel extends ListModel
{
    /**
     * Storage id to filter on.
     *
     * @var int
     */
    private int $storageId = 0;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $this->filter_fields = [
            'id',
            'name',
            'title',
            'group_definition',
            'published',
            'ordering'
        ];

        parent::__construct($config, $factory);
    }

    public function setStorageId(int $storageId): void
    {
        $this->storageId = $storageId;
    }

    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = 'ordering', $direction = 'asc'): void
    {
        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        $context = $this->context ?: 'com_contentbuilderng.storagefields';
        $storageId = (int) $this->storageId;

        if (!$storageId) {
            $storageId = $app->input->getInt('id', 0);
            if (!$storageId) {
                $jform = $app->input->post->get('jform', [], 'array');
                $storageId = (int) ($jform['id'] ?? 0);
            }
        }

        $this->storageId = $storageId;
        $this->setState('storage.id', $storageId);

        $published = $app->getUserStateFromRequest(
            'com_contentbuilderng.storagefields.filter.published',
            'filter_published',
            '',
            'string'
        );
        $this->setState('filter.published', $published);

        parent::populateState($ordering, $direction);

        // Joomla core may reset list.start to 0 when list[] exists without list[limit].
        // Re-apply a consistent pagination state for this screen.
        $listInput = (array) $app->input->get('list', [], 'array');
        if (array_key_exists('limit', $listInput)) {
            $effectiveLimit = (int) $listInput['limit'];
        } elseif ($app->input->get('limit', null, 'raw') !== null) {
            $effectiveLimit = (int) $app->input->getInt('limit', (int) $app->get('list_limit'));
        } else {
            $effectiveLimit = (int) $app->getUserState($context . '.list.limit', (int) $app->get('list_limit'));
        }
        $effectiveLimit = max(0, $effectiveLimit);

        // Joomla admin pagination links set "limitstart"; prefer it over stale list[start].
        if ($app->input->get('limitstart', null, 'raw') !== null) {
            $requestedStart = (int) $app->input->getInt('limitstart', 0);
        } elseif (array_key_exists('start', $listInput)) {
            $requestedStart = (int) $listInput['start'];
        } elseif ($app->input->get('start', null, 'raw') !== null) {
            $requestedStart = (int) $app->input->getInt('start', 0);
        } else {
            $requestedStart = (int) $app->getUserState($context . '.list.start', 0);
        }

        $effectiveStart = ($effectiveLimit !== 0) ? (int) (floor($requestedStart / $effectiveLimit) * $effectiveLimit) : 0;
        $effectiveStart = max(0, $effectiveStart);

        $this->setState('list.limit', $effectiveLimit);
        $this->setState('list.start', $effectiveStart);
        // Keep alias fields in sync for consumers still reading these keys.
        $this->setState('limit', $effectiveLimit);
        $this->setState('limitstart', $effectiveStart);

        $app->setUserState($context . '.list.limit', $effectiveLimit);
        $app->setUserState($context . '.list.start', $effectiveStart);
        $app->setUserState($context . '.limitstart', $effectiveStart);
    }

    /**
     * {@inheritDoc}
     */
    protected function getListQuery(): DatabaseQuery
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
            'id',
            'storage_id',
            'name',
            'title',
            'is_group',
            'group_definition',
            'ordering',
            'published'
        ]))
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $this->getState('storage.id', 0));

        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $query->where($db->quoteName('published') . ' = ' . (int) $published);
        }

        $orderCol  = (string) $this->getState('list.ordering', '');
        $orderDirn = strtolower((string) $this->getState('list.direction', ''));

        $input = Factory::getApplication()->input;
        $list = (array) $input->get('list', [], 'array');
        $requestedOrder = isset($list['ordering']) ? preg_replace('/[^a-zA-Z0-9_\\.]/', '', (string) $list['ordering']) : '';
        $requestedDir = strtolower((string) ($list['direction'] ?? ''));

        if ($requestedOrder !== '') {
            $orderCol = $requestedOrder;
            $this->setState('list.ordering', $orderCol);
        }

        if ($requestedDir === 'asc' || $requestedDir === 'desc') {
            $orderDirn = $requestedDir;
            $this->setState('list.direction', $orderDirn);
        }

        $orderCol  = $orderCol ?: 'ordering';
        $orderDirn = $orderDirn ?: 'asc';

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    public function getTable($name = 'StorageFields', $prefix = 'CB\\Component\\Contentbuilderng\\Administrator\\Table\\', $options = [])
    {
        if ($name === 'StorageFields') {
            return new StorageFieldsTable($this->getDatabase());
        }

        return parent::getTable($name, $prefix, $options);
    }

    public function move(int $direction): bool
    {
        $storageId = (int) $this->getState('storage.id', 0);
        if (!$storageId) {
            return false;
        }

        $cid = $this->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($cid);
        $pk = (int) ($cid[0] ?? 0);

        if (!$pk) {
            return false;
        }

        $table = $this->getTable();
        if (!$table->load($pk)) {
            return false;
        }

        return (bool) $table->move($direction, 'storage_id = ' . $storageId);
    }

    public function saveorder(?array $pks = null, ?array $order = null): bool
    {
        $storageId = (int) $this->getState('storage.id', 0);
        if (!$storageId) {
            return false;
        }

        $pks = array_values((array) ($pks ?? []));
        $order = array_values((array) ($order ?? []));

        if (empty($pks) || empty($order)) {
            return false;
        }

        $table = $this->getTable();

        try {
            foreach ($pks as $i => $pk) {
                if (!$table->load((int) $pk)) {
                    return false;
                }
                $table->ordering = (int) ($order[$i] ?? 0);
                if (!$table->store()) {
                    return false;
                }
            }

            $table->reorder('storage_id = ' . $storageId);
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    public function publish(array $pks, int $value = 1): bool
    {
        $pks = (array) $pks;

        if (empty($pks)) {
            throw new \RuntimeException(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
        }

        ArrayHelper::toInteger($pks);
        $pks = array_filter($pks);

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__contentbuilderng_storage_fields'))
            ->set($db->quoteName('published') . ' = ' . (int) $value)
            ->where($db->quoteName('id') . ' IN (' . implode(',', $pks) . ')');

        $db->setQuery($query);

        try {
            $db->execute();
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        return true;
    }
}
