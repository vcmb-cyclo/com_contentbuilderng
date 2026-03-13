<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Site\Controller;

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;

class AjaxController extends BaseController
{
    public function __construct(
        $config,
        MVCFactoryInterface $factory,
        CMSApplicationInterface $app,
        Input $input
    ) {
        // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory, $app, $input);

        $bfrontend = $app->isClient('site');

        (new PermissionService())->setPermissions($this->input->getInt('id', 0), 0, $bfrontend ? '_fe' : '');
    }

    function display($cachable = false, $urlparams = [])
    {
        $this->input->set('tmpl', $this->input->getWord('tmpl', null));
        $this->input->set('layout', $this->input->getWord('layout', null));
        $this->input->set('view', 'ajax');
        $this->input->set('format', 'raw');
        
        parent::display();
    }
}
