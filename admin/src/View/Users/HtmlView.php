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

namespace CB\Component\Contentbuilderng\Administrator\View\Users;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use CB\Component\Contentbuilderng\Administrator\Model\UsersModel;
class HtmlView extends BaseHtmlView
{
    /**
     * @var  array
     */
    protected $items;

    /**
     * @var  \JPagination
     */
    protected $pagination;

    /**
     * @var  \Joomla\Registry\Registry
     */
    protected $state;

    private function getApp(): CMSApplicationInterface
    {
        $app = $this->app;

        if (!$app instanceof CMSApplicationInterface) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    #[\Override]
    public function display($tpl = null): void
    {
        /** @var UsersModel $model */
        $model = $this->getModel();
        $this->items      = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state      = $model->getState();

        // Toolbar
        $input = $this->getApp()->getInput();
        $formId = $input->getInt('form_id', 0);
        $tmpl = $input->getCmd('tmpl', '');

        if ($this->pagination) {
            $this->pagination->setAdditionalUrlParam('option', 'com_contentbuilderng');
            $this->pagination->setAdditionalUrlParam('view', 'users');
            $this->pagination->setAdditionalUrlParam('form_id', (string) $formId);

            if ($tmpl !== '') {
                $this->pagination->setAdditionalUrlParam('tmpl', $tmpl);
            }
        }

        $title = Text::_('COM_CONTENTBUILDERNG') . ' / ';

        if ($formId > 0) {
            $title .= Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_FORMS') . ' / #' . $formId . ' / ';
        }

        $title .= Text::_('COM_CONTENTBUILDERNG_USERS');

        ToolbarHelper::title($title, 'users');

        ToolbarHelper::editList();

        parent::display($tpl);
    }
}
