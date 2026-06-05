<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Elementoptions;

// No direct access
\defined('_JEXEC') or die('Restricted access');

ini_set('display_errors', 1);
// error_reporting(E_ALL);

use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\Model\ElementoptionsModel;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    function display($tpl = null)
    {
        // Get data from the model
        /** @var ElementoptionsModel $model */
        $model = $this->getModel();
        $element = $model->getData();
        $validations = $model->getValidationPlugins();
        $this->validations = $validations;
        $this->element = $element;
        $groupdef = $model->getGroupDefinition();
        $this->group_definition = $groupdef;
        parent::display($tpl);
    }
}
