<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\View\User;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;
use CB\Component\Contentbuilderng\Administrator\Model\UserModel;

class HtmlView extends BaseHtmlView
{
    function display($tpl = null)
    {
        /** @var UserModel $model */
        $model = $this->getModel();
        $subject = $model->getData();
        $this->subject = $subject;
        $app = Factory::getApplication();
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
