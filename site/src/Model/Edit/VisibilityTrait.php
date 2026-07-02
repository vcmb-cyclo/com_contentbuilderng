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

use CB\Component\Contentbuilderng\Site\Helper\PublishedRecordVisibilityHelper;

/**
 * Menu-filter and published-only visibility checks extracted from EditModel.
 */
trait VisibilityTrait
{
    private function isRecordAllowedByMenuFilter(object $data, array $ids): bool
    {
        if ((int) $this->_record_id <= 0 || empty($this->_menu_filter)) {
            return true;
        }

        $isAdminPreview = $this->app->getInput()->getBool('cb_preview_ok', false);
        $publishedOnly = $isAdminPreview ? false : (bool) ($data->published_only ?? false);
        $ownerFilterUserId = $this->frontend
            ? $this->getEffectiveOwnershipUserId((bool) ($data->own_only_fe ?? false))
            : $this->getEffectiveOwnershipUserId((bool) ($data->own_only ?? false));
        $showAllLanguages = $isAdminPreview ? true : ($this->frontend ? (bool) ($data->show_all_languages_fe ?? false) : true);

        $matches = $data->form->getListRecords(
            $ids,
            '',
            [],
            0,
            1,
            '',
            [],
            'desc',
            (int) $this->_record_id,
            $publishedOnly,
            $ownerFilterUserId,
            0,
            -1,
            -1,
            -1,
            -1,
            $this->_menu_filter,
            $showAllLanguages,
            null
        );

        return is_array($matches) && count($matches) > 0;
    }

    private function shouldRestrictToPublishedOnly(object $data, bool $isAdminPreview): bool
    {
        return PublishedRecordVisibilityHelper::shouldRestrictToPublishedOnly($data, $isAdminPreview);
    }
}
