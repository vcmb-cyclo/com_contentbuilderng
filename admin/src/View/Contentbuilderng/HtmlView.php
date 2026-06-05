<?php
/**
 * Default page.
 * @package     ContentBuilder NG
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\View\HtmlView  as BaseHtmlView;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        // 1️⃣ Récupération du WebAssetManager
        $document = $this->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useStyle('com_contentbuilderng.system');

        // 2️⃣ Enregistrement + chargement du CSS
        $wa->registerAndUseStyle(
            'com_contentbuilderng.admin',
            'COM_CONTENTBUILDERNG/admin.css',
            [],
            ['media' => 'all']
        );

        // Icon addition.
        $wa->addInlineStyle(
            '.icon-logo_icon_cb{
                background-image:url(' . Uri::root(true) . '/media/com_contentbuilderng/images/logo_icon_cb.png);
                background-size:contain;
                background-repeat:no-repeat;
                background-position:center;
                display:inline-block;
                width:24px;
                height:24px;
            }'
        );

/*
        ToolbarHelper::title(
            Text::_('COM_CONTENTBUILDERNG') .' :: ' . Text::_("COM_CONTENTBUILDERNG'),
            'logo_icon_cb'
        );*/


        // 3️⃣ Affichage du layout
        parent::display($tpl);
    }
}
