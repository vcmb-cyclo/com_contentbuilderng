<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die;

final class PublishedRecordVisibilityHelper
{
    public static function shouldRestrictToPublishedOnly(object $data, bool $isAdminPreview): bool
    {
        return !$isAdminPreview && (bool) ($data->published_only ?? false);
    }
}
