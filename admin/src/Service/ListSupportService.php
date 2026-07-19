<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use Joomla\Database\DatabaseInterface;

class ListSupportService
{
    public function __construct(
        private readonly DatabaseInterface $db
    ) {
    }

    public static function createFromRuntimeContext(): self
    {
        return new self(RuntimeContextHelper::getDatabase());
    }

    public function getListRecordMeta(array $items, int $formId, string $type, $referenceId): array
    {
        $recordIds = $this->collectRecordIds($items);

        if ($recordIds === []) {
            return [
                'state_ids' => [],
                'state_colors' => [],
                'state_titles' => [],
                'published_items' => [],
                'lang_codes' => [],
                'cb_record_ids' => [],
            ];
        }

        $db = $this->db;
        $meta = [
            'state_ids' => [],
            'state_colors' => [],
            'state_titles' => [],
            'published_items' => [],
            'lang_codes' => [],
            'cb_record_ids' => [],
        ];

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('states.id', 'state_id'),
                $db->quoteName('states.color'),
                $db->quoteName('states.title'),
                $db->quoteName('records.record_id'),
            ])
            ->from($db->quoteName('#__contentbuilderng_list_records', 'records'))
            ->join(
                'INNER',
                $db->quoteName('#__contentbuilderng_list_states', 'states')
                . ' ON ' . $db->quoteName('states.id') . ' = ' . $db->quoteName('records.state_id')
            )
            ->where($db->quoteName('states.published') . ' = 1')
            ->where($db->quoteName('records.record_id') . ' IN (' . implode(',', array_map([$db, 'quote'], $recordIds)) . ')')
            ->where($db->quoteName('records.form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('states.form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        foreach ((array) $db->loadAssocList() as $row) {
            $meta['state_ids'][$row['record_id']] = (int) $row['state_id'];
            $meta['state_colors'][$row['record_id']] = $row['color'];
            $meta['state_titles'][$row['record_id']] = $row['title'];
        }

        if ($referenceId) {
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('records.id'),
                    $db->quoteName('records.published'),
                    $db->quoteName('records.lang_code'),
                    $db->quoteName('records.record_id'),
                ])
                ->from($db->quoteName('#__contentbuilderng_records', 'records'))
                ->where($db->quoteName('type') . ' = ' . $db->quote($type))
                ->where($db->quoteName('reference_id') . ' = ' . $db->quote($referenceId))
                ->where($db->quoteName('records.record_id') . ' IN (' . implode(',', array_map([$db, 'quote'], $recordIds)) . ')');
            $db->setQuery($query);

            foreach ((array) $db->loadAssocList() as $row) {
                $meta['published_items'][$row['record_id']] = $row['published'];
                $meta['lang_codes'][$row['record_id']] = $row['lang_code'];
                $meta['cb_record_ids'][$row['record_id']] = (int) $row['id'];
            }
        }

        return $meta;
    }

    public function getInternalRecordId(string $type, $referenceId, $recordId): int
    {
        if ($type === '' || !$referenceId || $recordId === null || $recordId === '') {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__contentbuilderng_records'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote($type))
            ->where($this->db->quoteName('reference_id') . ' = ' . $this->db->quote($referenceId))
            ->where($this->db->quoteName('record_id') . ' = ' . $this->db->quote($recordId));

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    public function getListSearchableElements(int $formId): array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('reference_id'))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('search_include') . ' = 1')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('reference_id') . ' >= 0')
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        return (array) $db->loadColumn();
    }

    public function getListLinkableElements(int $formId): array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('reference_id'))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('linkable') . ' = 1')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        return (array) $db->loadColumn();
    }

    public function getListEditableElements(int $formId): array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('reference_id'))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('editable') . ' = 1')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('reference_id') . ' >= 0')
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        return (array) $db->loadColumn();
    }

    public function getListNonEditableElements(int $formId): array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('reference_id'))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where('(' . $db->quoteName('editable') . ' = 0 OR ' . $db->quoteName('published') . ' = 0)')
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        return (array) $db->loadColumn();
    }

    public function getListStates(int $formId): array
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilderng_list_states'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('id'));
        $db->setQuery($query);

        return (array) $db->loadAssocList();
    }

    public function getStateColors(array $items, int $formId): array
    {
        $recordIds = $this->collectRecordIds($items);

        if ($recordIds === []) {
            return [];
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('states.color'),
                $db->quoteName('records.record_id'),
            ])
            ->from($db->quoteName('#__contentbuilderng_list_states', 'states'))
            ->join(
                'INNER',
                $db->quoteName('#__contentbuilderng_list_records', 'records')
                . ' ON ' . $db->quoteName('states.id') . ' = ' . $db->quoteName('records.state_id')
            )
            ->where($db->quoteName('states.published') . ' = 1')
            ->where($db->quoteName('records.record_id') . ' IN (' . implode(',', array_map([$db, 'quote'], $recordIds)) . ')')
            ->where($db->quoteName('records.form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('states.form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        $out = [];
        foreach ((array) $db->loadAssocList() as $row) {
            $out[$row['record_id']] = $row['color'];
        }

        return $out;
    }

    public function getStateIds(array $items, int $formId): array
    {
        $recordIds = $this->collectRecordIds($items);

        if ($recordIds === []) {
            return [];
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('states.id', 'state_id'),
                $db->quoteName('records.record_id'),
            ])
            ->from($db->quoteName('#__contentbuilderng_list_states', 'states'))
            ->join(
                'INNER',
                $db->quoteName('#__contentbuilderng_list_records', 'records')
                . ' ON ' . $db->quoteName('states.id') . ' = ' . $db->quoteName('records.state_id')
            )
            ->where($db->quoteName('states.published') . ' = 1')
            ->where($db->quoteName('records.record_id') . ' IN (' . implode(',', array_map([$db, 'quote'], $recordIds)) . ')')
            ->where($db->quoteName('records.form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('states.form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        $out = [];
        foreach ((array) $db->loadAssocList() as $row) {
            $out[$row['record_id']] = (int) $row['state_id'];
        }

        return $out;
    }

    public function getStateTitles(array $items, int $formId): array
    {
        $recordIds = $this->collectRecordIds($items);

        if ($recordIds === []) {
            return [];
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('states.title'),
                $db->quoteName('records.record_id'),
            ])
            ->from($db->quoteName('#__contentbuilderng_list_states', 'states'))
            ->join(
                'INNER',
                $db->quoteName('#__contentbuilderng_list_records', 'records')
                . ' ON ' . $db->quoteName('states.id') . ' = ' . $db->quoteName('records.state_id')
            )
            ->where($db->quoteName('states.published') . ' = 1')
            ->where($db->quoteName('records.record_id') . ' IN (' . implode(',', array_map([$db, 'quote'], $recordIds)) . ')')
            ->where($db->quoteName('records.form_id') . ' = ' . (int) $formId)
            ->where($db->quoteName('states.form_id') . ' = ' . (int) $formId);
        $db->setQuery($query);

        $out = [];
        foreach ((array) $db->loadAssocList() as $row) {
            $out[$row['record_id']] = $row['title'];
        }

        return $out;
    }

    public function getRecordsPublishInfo(array $items, string $type, $referenceId): array
    {
        if (!$referenceId) {
            return [];
        }

        $recordIds = $this->collectRecordIds($items);

        if ($recordIds === []) {
            return [];
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('records.published'),
                $db->quoteName('records.record_id'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records', 'records'))
            ->where($db->quoteName('type') . ' = ' . $db->quote($type))
            ->where($db->quoteName('reference_id') . ' = ' . $db->quote($referenceId))
            ->where($db->quoteName('records.record_id') . ' IN (' . implode(',', array_map([$db, 'quote'], $recordIds)) . ')');
        $db->setQuery($query);

        $out = [];
        foreach ((array) $db->loadAssocList() as $row) {
            $out[$row['record_id']] = $row['published'];
        }

        return $out;
    }

    public function getRecordsLanguage(array $items, string $type, $referenceId): array
    {
        if (!$referenceId) {
            return [];
        }

        $recordIds = $this->collectRecordIds($items);

        if ($recordIds === []) {
            return [];
        }

        $db = $this->db;
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('records.lang_code'),
                $db->quoteName('records.record_id'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records', 'records'))
            ->where($db->quoteName('reference_id') . ' = ' . $db->quote($referenceId))
            ->where($db->quoteName('records.record_id') . ' IN (' . implode(',', array_map([$db, 'quote'], $recordIds)) . ')');
        $db->setQuery($query);

        $out = [];
        foreach ((array) $db->loadAssocList() as $row) {
            $out[$row['record_id']] = $row['lang_code'];
        }

        return $out;
    }

    private function collectRecordIds(array $items): array
    {
        $recordIds = [];

        foreach ($items as $item) {
            $recordId = is_object($item) ? ($item->colRecord ?? null) : null;

            if ($recordId !== null && $recordId !== '') {
                $recordIds[] = (string) $recordId;
            }
        }

        return array_values(array_unique($recordIds));
    }
}
