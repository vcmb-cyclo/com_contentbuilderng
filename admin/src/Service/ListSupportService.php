<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

class ListSupportService
{
    public function __construct(
        private readonly DatabaseInterface $db
    ) {
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
            ];
        }

        $db = $this->db;
        $quotedIds = array_map([$db, 'quote'], $recordIds);
        $meta = [
            'state_ids' => [],
            'state_colors' => [],
            'state_titles' => [],
            'published_items' => [],
            'lang_codes' => [],
        ];

        $db->setQuery(
            'Select states.id As state_id, states.color, states.title, records.record_id'
            . ' From #__contentbuilderng_list_records As records'
            . ' Inner Join #__contentbuilderng_list_states As states On states.id = records.state_id'
            . ' Where states.published = 1'
            . ' And records.record_id In (' . implode(',', $quotedIds) . ')'
            . ' And records.form_id = ' . (int) $formId
            . ' And states.form_id = ' . (int) $formId
        );

        foreach ((array) $db->loadAssocList() as $row) {
            $meta['state_ids'][$row['record_id']] = (int) $row['state_id'];
            $meta['state_colors'][$row['record_id']] = $row['color'];
            $meta['state_titles'][$row['record_id']] = $row['title'];
        }

        if ($referenceId) {
            $db->setQuery(
                'Select records.published, records.lang_code, records.record_id'
                . ' From #__contentbuilderng_records As records'
                . ' Where `type` = ' . $db->quote($type)
                . ' And reference_id = ' . $db->quote($referenceId)
                . ' And records.record_id In (' . implode(',', $quotedIds) . ')'
            );

            foreach ((array) $db->loadAssocList() as $row) {
                $meta['published_items'][$row['record_id']] = $row['published'];
                $meta['lang_codes'][$row['record_id']] = $row['lang_code'];
            }
        }

        return $meta;
    }

    public function getListSearchableElements(int $formId): array
    {
        $db = $this->db;
        $db->setQuery(
            'Select reference_id From #__contentbuilderng_elements'
            . ' Where search_include = 1 And published = 1 And form_id = ' . (int) $formId
        );

        return (array) $db->loadColumn();
    }

    public function getListLinkableElements(int $formId): array
    {
        $db = $this->db;
        $db->setQuery(
            'Select reference_id From #__contentbuilderng_elements'
            . ' Where linkable = 1 And published = 1 And form_id = ' . (int) $formId
        );

        return (array) $db->loadColumn();
    }

    public function getListEditableElements(int $formId): array
    {
        $db = $this->db;
        $db->setQuery(
            'Select reference_id From #__contentbuilderng_elements'
            . ' Where editable = 1 And published = 1 And form_id = ' . (int) $formId
        );

        return (array) $db->loadColumn();
    }

    public function getListNonEditableElements(int $formId): array
    {
        $db = $this->db;
        $db->setQuery(
            'Select reference_id From #__contentbuilderng_elements'
            . ' Where ( editable = 0 Or published = 0 ) And form_id = ' . (int) $formId
        );

        return (array) $db->loadColumn();
    }

    public function getListStates(int $formId): array
    {
        $db = $this->db;
        $db->setQuery(
            'Select * From #__contentbuilderng_list_states'
            . ' where form_id = ' . (int) $formId
            . ' And published = 1 Order By id'
        );

        return (array) $db->loadAssocList();
    }

    public function getStateColors(array $items, int $formId): array
    {
        $recordIds = $this->collectRecordIds($items);

        if ($recordIds === []) {
            return [];
        }

        $db = $this->db;
        $quotedIds = array_map([$db, 'quote'], $recordIds);

        $db->setQuery(
            'Select states.color, records.record_id'
            . ' From #__contentbuilderng_list_states As states, #__contentbuilderng_list_records As records'
            . ' Where states.published = 1'
            . ' And states.id = records.state_id'
            . ' And records.record_id In (' . implode(',', $quotedIds) . ')'
            . ' And records.form_id = ' . (int) $formId
            . ' And states.form_id = ' . (int) $formId
        );

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
        $quotedIds = array_map([$db, 'quote'], $recordIds);

        $db->setQuery(
            'Select states.id As state_id, records.record_id'
            . ' From #__contentbuilderng_list_states As states, #__contentbuilderng_list_records As records'
            . ' Where states.published = 1'
            . ' And states.id = records.state_id'
            . ' And records.record_id In (' . implode(',', $quotedIds) . ')'
            . ' And records.form_id = ' . (int) $formId
            . ' And states.form_id = ' . (int) $formId
        );

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
        $quotedIds = array_map([$db, 'quote'], $recordIds);

        $db->setQuery(
            'Select states.title, records.record_id'
            . ' From #__contentbuilderng_list_states As states, #__contentbuilderng_list_records As records'
            . ' Where states.published = 1'
            . ' And states.id = records.state_id'
            . ' And records.record_id In (' . implode(',', $quotedIds) . ')'
            . ' And records.form_id = ' . (int) $formId
            . ' And states.form_id = ' . (int) $formId
        );

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
        $quotedIds = array_map([$db, 'quote'], $recordIds);
        $db->setQuery(
            'Select records.published, records.record_id'
            . ' From #__contentbuilderng_records As records'
            . ' Where `type` = ' . $db->quote($type)
            . ' And reference_id = ' . $db->quote($referenceId)
            . ' And records.record_id In (' . implode(',', $quotedIds) . ')'
        );

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
        $quotedIds = array_map([$db, 'quote'], $recordIds);
        $db->setQuery(
            'Select records.lang_code, records.record_id'
            . ' From #__contentbuilderng_records As records'
            . ' Where reference_id = ' . $db->quote($referenceId)
            . ' And records.record_id In (' . implode(',', $quotedIds) . ')'
        );

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
