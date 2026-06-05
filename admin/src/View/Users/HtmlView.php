<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Users;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
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

    public function display($tpl = null): void
    {
        /** @var UsersModel $model */
        $model = $this->getModel();
        $this->items      = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state      = $model->getState();

        // Toolbar
        $input = Factory::getApplication()->getInput();
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
