<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Site\Controller;

// No direct access
\defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\MVC\Controller\BaseController;

class PublicformsController extends BaseController
{

    function display($cachable = false, $urlparams = [])
    {
        $this->input->set('tmpl', $this->input->getWord('tmpl', null));
        $this->input->set('layout', $this->input->getWord('layout', null));
        $this->input->set('view', 'publicforms');

        parent::display();
    }
}
