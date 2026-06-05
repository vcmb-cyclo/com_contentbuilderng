<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

final class ConfigtransferController extends BaseController
{
    protected $default_view = 'configtransfer';

    public function back(): void
    {
        $this->redirectToAbout();
    }

    public function export(): void
    {
        $this->redirectToWorkflow('export');
    }

    public function import(): void
    {
        $this->redirectToWorkflow('import');
    }

    private function redirectToWorkflow(string $mode): void
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $mode = in_array($mode, ['export', 'import'], true) ? $mode : 'export';
        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=' . $mode, false));
    }

    private function redirectToAbout(): void
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }
}
