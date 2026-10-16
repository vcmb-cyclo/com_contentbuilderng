<?php

/**
 * ContentBuilder NG — storage records (Data tab) list model.
 *
 * Reads the storage's physical data table directly, paginated, with the same
 * "show everything" philosophy as the signed front-end preview: no published
 * or ownership filtering is applied here. Mutating a record still goes through
 * the front-end pipeline, which enforces the real _fe ACL.
 *
 * @package     ContentBuilderNG
 * @subpackage  Administrator.Model
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;

class StoragedataModel extends ListModel
{
    private int $storageId = 0;

    /** @var array<int,string> Resolved data columns, id first. */
    private array $columns = [];

    /** @var array<string,string> column name => field title */
    private array $columnLabels = [];

    private string $tableName = '';

    private bool $tableReadable = false;

    private bool $hasPrimaryKey = false;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $this->context = 'com_contentbuilderng.storagedata';

        parent::__construct($config, $factory);
    }

    public function setStorageId(int $storageId): void
    {
        $this->storageId = $storageId;
        $this->setState('storage.id', $storageId);
        $this->resolveTable();
    }

    public function isReadable(): bool
    {
        return $this->tableReadable;
    }

    public function hasPrimaryKey(): bool
    {
        return $this->hasPrimaryKey;
    }

    /** @return array<string,string> */
    public function getColumnLabels(): array
    {
        return $this->columnLabels;
    }

    private function getComponent(): ContentbuilderngComponent
    {
        $component = RuntimeContextHelper::getApplication()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    #[\Override]
    protected function getDatabase(): DatabaseInterface
    {
        return $this->getComponent()->getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Resolve the physical table name and the readable column set for the
     * current storage. Never throws — an unresolved table just yields an empty,
     * non-readable model so the view can show a friendly notice.
     */
    private function resolveTable(): void
    {
        $this->columns = [];
        $this->columnLabels = [];
        $this->tableName = '';
        $this->tableReadable = false;
        $this->hasPrimaryKey = false;

        if ($this->storageId < 1) {
            return;
        }

        try {
            $db = $this->getDatabase();

            $query = $db->getQuery(true)
                ->select($db->quoteName(['name', 'bytable']))
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where($db->quoteName('id') . ' = ' . (int) $this->storageId);
            $db->setQuery($query);
            $storage = $db->loadAssoc();

            $name = trim((string) ($storage['name'] ?? ''));

            if ($name === '') {
                return;
            }

            $isExternal = (int) ($storage['bytable'] ?? 0) > 0;
            $lookupName = $isExternal ? $name : ('#__' . $name);
            $resolved = strtolower($db->replacePrefix($lookupName));
            $tableList = array_map('strtolower', (array) $db->getTableList());

            if (!in_array($resolved, $tableList, true)) {
                return;
            }

            $physicalColumns = array_change_key_case(
                (array) $db->getTableColumns($lookupName, false),
                CASE_LOWER
            );

            if ($physicalColumns === []) {
                return;
            }

            $this->tableName = $lookupName;
            $this->tableReadable = true;
            $this->hasPrimaryKey = \array_key_exists('id', $physicalColumns);

            // Managed fields drive the visible columns and their labels; fall
            // back to the raw physical columns when no field metadata exists.
            $fieldsQuery = $db->getQuery(true)
                ->select($db->quoteName(['name', 'title']))
                ->from($db->quoteName('#__contentbuilderng_storage_fields'))
                ->where($db->quoteName('storage_id') . ' = ' . (int) $this->storageId)
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('ordering') . ' ASC');
            $db->setQuery($fieldsQuery);
            $managed = $db->loadAssocList() ?: [];

            $ordered = [];

            if ($this->hasPrimaryKey) {
                $ordered['id'] = 'id';
                $this->columnLabels['id'] = 'id';
            }

            foreach ($managed as $field) {
                $fieldName = (string) ($field['name'] ?? '');

                if ($fieldName === '' || isset($ordered[$fieldName])) {
                    continue;
                }

                if (\array_key_exists(strtolower($fieldName), $physicalColumns)) {
                    $ordered[$fieldName] = $fieldName;
                    $title = trim((string) ($field['title'] ?? ''));
                    $this->columnLabels[$fieldName] = $title !== '' ? $title : $fieldName;
                }
            }

            if (count($ordered) <= ($this->hasPrimaryKey ? 1 : 0)) {
                // No managed field columns matched: expose the physical layout.
                foreach (array_keys($physicalColumns) as $physical) {
                    $physical = (string) $physical;
                    $ordered[$physical] = $physical;
                    $this->columnLabels[$physical] = $physical;
                }
            }

            $this->columns = array_values($ordered);
        } catch (\Throwable $e) {
            $this->columns = [];
            $this->columnLabels = [];
            $this->tableName = '';
            $this->tableReadable = false;
            $this->hasPrimaryKey = false;
        }
    }

    #[\Override]
    protected function populateState($ordering = null, $direction = null): void
    {
        $app = RuntimeContextHelper::getApplication();
        $input = $app->getInput();

        if ($this->storageId < 1) {
            $this->storageId = $input->getInt('id', 0);
            $this->setState('storage.id', $this->storageId);
            $this->resolveTable();
        }

        $search = $app->getUserStateFromRequest(
            $this->context . '.' . $this->storageId . '.search',
            'data_search',
            '',
            'string'
        );
        $this->setState('data.search', trim((string) $search));

        $orderCol = (string) $input->getCmd('data_ordering', '');
        if ($orderCol === '' || !in_array($orderCol, $this->columns, true)) {
            $orderCol = $this->hasPrimaryKey ? 'id' : (string) ($this->columns[0] ?? '');
        }
        $this->setState('list.ordering', $orderCol);

        $orderDirn = strtolower((string) $input->getCmd('data_direction', ''));
        if ($orderDirn !== 'asc' && $orderDirn !== 'desc') {
            $orderDirn = $this->hasPrimaryKey ? 'desc' : 'asc';
        }
        $this->setState('list.direction', $orderDirn);

        $limit = $input->get('data_limit', null, 'raw');
        $limit = $limit === null
            ? (int) $app->getUserState($this->context . '.list.limit', (int) $app->get('list_limit', 20))
            : max(0, (int) $limit);
        $app->setUserState($this->context . '.list.limit', $limit);

        $start = (int) $input->getInt('data_start', 0);
        $start = ($limit > 0) ? (int) (floor($start / $limit) * $limit) : 0;

        $this->setState('list.limit', $limit);
        $this->setState('list.start', max(0, $start));
    }

    #[\Override]
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        if (!$this->tableReadable || $this->columns === []) {
            // Deliberately impossible query: keeps getItems()/getTotal() safe
            // when the table could not be resolved.
            $query->select('1')->from($db->quoteName('#__contentbuilderng_storages'))->where('0 = 1');

            return $query;
        }

        $query->select(array_map([$db, 'quoteName'], $this->columns))
            ->from($db->quoteName($this->tableName));

        $search = (string) $this->getState('data.search', '');

        if ($search !== '') {
            $escaped = '%' . $db->escape($search, true) . '%';
            $ors = [];

            foreach ($this->columns as $column) {
                $ors[] = $db->quoteName($column) . ' LIKE ' . $db->quote($escaped, false);
            }

            $query->where('(' . implode(' OR ', $ors) . ')');
        }

        $orderCol = (string) $this->getState('list.ordering', '');
        $orderDirn = strtolower((string) $this->getState('list.direction', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        if (in_array($orderCol, $this->columns, true)) {
            $query->order($db->quoteName($orderCol) . ' ' . $orderDirn);
        }

        return $query;
    }
}
