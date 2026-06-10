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

namespace CB\Component\Contentbuilderng\Site\View\PublicForms;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;
use CB\Component\Contentbuilderng\Site\Model\PublicformsModel;

class HtmlView extends BaseHtmlView
{
    function display($tpl = null)
    {
        /** @var PublicformsModel $model */
        $model = $this->getModel();

        // Get data from the model
        $items = $model->getData();
        $perms = $model->getPermissions();
        $page_heading = $model->getShowPageHeading();
        $introtext = $model->getShowIntrotext();
        $show_tags = $model->getShowTags();
        $show_id = $model->getShowId();
        $show_permissions = $model->getShowPermissions();
        $show_permissions_new = $model->getShowPermissionsNew();
        $show_permissions_edit = $model->getShowPermissionsEdit();
        $pagination = $model->getPagination();
        $tags = $model->getTags();

        $state = $model->getState();

        $lists['order_Dir'] = $state?->get('forms_filter_order_Dir') ?? 'desc';
        $lists['order'] = $state?->get('forms_filter_order') ?? '`name`';
        $lists['state'] = HTMLHelper::_('grid.state', $state?->get('forms_filter_state') ?? '');
        $lists['limitstart'] = $state?->get('limitstart') ?? 0;
        $lists['filter_tag'] = $state?->get('forms_filter_tag') ?? '';

        $ordering = ($lists['order'] == 'ordering');

        $this->show_permissions = $show_permissions;
        $this->show_permissions_new = $show_permissions_new;
        $this->show_permissions_edit = $show_permissions_edit;
        $this->page_heading = $page_heading;
        $this->show_tags = $show_tags;
        $this->show_id = $show_id;
        $this->introtext = $introtext;
        $this->perms = $perms;
        $this->ordering = $ordering;
        $this->tags = $tags;
        $this->lists = $lists;

        $this->items = $items;
        $this->pagination = $pagination;
        parent::display($tpl);
    }
}
