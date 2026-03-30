<?php

/**
 * ContentBuilder NG Verify view.
 *
 * Verify view of the site interface
 *
 * @package     ContentBuilder NG
 * @subpackage  Site.View
 * @author      Xavier DANO
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 */


namespace CB\Component\Contentbuilderng\Site\View\Verify;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    protected $state;
    protected $item;
    protected $form;

    public function display($tpl = null): void
    {
        $this->state = $this->getModel()->getState();
        $this->item  = $this->getModel()->getItem();   // si ton modèle fournit Item
        $this->form  = $this->getModel()->getForm();   // si c’est une vue avec formulaire

        parent::display($tpl);
    }
}
