<?php

/**
 * @package     Extension
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

// administrator/src/Controller/DisplayController.php

namespace CB\Component\Contentbuilderng\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;

class DisplayController extends BaseController
{
    protected $default_view = 'storages';

    private function getApp(): CMSApplicationInterface
    {
        $app = $this->app;

        if (!$app instanceof CMSApplicationInterface) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    #[\Override]
    public function display($cachable = false, $urlparams = [])
    {
        $app = $this->getApp();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        if ($app->getInput()->getBool('market')) {
            $app->redirect('https://breezingforms-ng.vcmb.fr');
        }

        return parent::display($cachable, $urlparams);
    }
}
