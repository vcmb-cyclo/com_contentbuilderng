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

namespace CB\Component\Contentbuilderng\Administrator\View\User;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;
use CB\Component\Contentbuilderng\Administrator\Model\UserModel;

class HtmlView extends BaseHtmlView
{
    private function getApp(): CMSApplicationInterface
    {
        $app = $this->app;

        if (!$app instanceof CMSApplicationInterface) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    function display($tpl = null)
    {
        /** @var UserModel $model */
        $model = $this->getModel();
        $subject = $model->getData();
        $this->subject = $subject;
        $app = $this->getApp();
        $formId = $app->getInput()->getInt('form_id', 0);
        $subjectLabel = trim((string) ($subject->name ?? ''));

        if ($subjectLabel === '') {
            $subjectLabel = trim((string) ($subject->username ?? ''));
        }

        if ($subjectLabel === '') {
            $subjectLabel = '#' . (int) ($subject->id ?? 0);
        }

        $title = Text::_('COM_CONTENTBUILDERNG') . ' / ';

        if ($formId > 0) {
            $title .= Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_FORMS') . ' / #' . $formId . ' / ';
        }

        $title .= Text::_('COM_CONTENTBUILDERNG_USERS') . ' / ' . $subjectLabel;

        ToolbarHelper::title($title, 'users');
        parent::display($tpl);
    }
}
