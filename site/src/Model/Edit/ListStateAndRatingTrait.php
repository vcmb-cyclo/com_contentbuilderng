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

namespace CB\Component\Contentbuilderng\Site\Model\Edit;

// No direct access
\defined('_JEXEC') or die('Restricted access');

/**
 * List-state and rating data enrichment extracted from EditModel.
 */
trait ListStateAndRatingTrait
{
    private function appendListStateData(object $data): object
    {
        $data->cb_record_id = $this->listSupportService->getInternalRecordId(
            (string) ($data->type ?? ''),
            $data->reference_id ?? 0,
            $this->_record_id
        );
        $data->list_state = (int) ($data->list_state ?? 0);
        $data->states = [];
        $data->state_ids = [];
        $data->state_titles = [];
        $data->state_colors = [];

        if ($data->list_state !== 1 || (int) $this->_id <= 0) {
            return $data;
        }

        $data->states = $this->listSupportService->getListStates((int) $this->_id);

        if ((int) $this->_record_id <= 0) {
            return $data;
        }

        $recordItems = [(object) ['colRecord' => (int) $this->_record_id]];
        $data->state_ids = $this->listSupportService->getStateIds($recordItems, (int) $this->_id);
        $data->state_titles = $this->listSupportService->getStateTitles($recordItems, (int) $this->_id);
        $data->state_colors = $this->listSupportService->getStateColors($recordItems, (int) $this->_id);

        return $data;
    }

    private function appendRatingData(object $data): object
    {
        $data->list_rating = (int) ($data->list_rating ?? 0);
        $data->rating_slots = (int) ($data->rating_slots ?? 0);
        $data->rating = 0.0;
        $data->rating_count = 0;
        $data->rating_sum = 0;

        if ($data->list_rating !== 1 || (int) $this->_id <= 0 || (int) $this->_record_id <= 0) {
            return $data;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('rating_count'),
                $db->quoteName('rating_sum'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($db->quoteName('record_id') . ' = ' . $db->quote((string) $this->_record_id));

        if (isset($data->type) && (string) $data->type !== '') {
            $query->where($db->quoteName('type') . ' = ' . $db->quote((string) $data->type));
        }

        if (isset($data->reference_id) && (string) $data->reference_id !== '') {
            $query->where($db->quoteName('reference_id') . ' = ' . $db->quote((string) $data->reference_id));
        }

        $query->setLimit(1);
        $db->setQuery($query);
        $ratingRow = $db->loadAssoc();

        if (!$ratingRow) {
            return $data;
        }

        $data->rating_count = (int) ($ratingRow['rating_count'] ?? 0);
        $data->rating_sum = (int) ($ratingRow['rating_sum'] ?? 0);
        $data->rating = $data->rating_count > 0 ? ($data->rating_sum / $data->rating_count) : 0.0;

        return $data;
    }
}
