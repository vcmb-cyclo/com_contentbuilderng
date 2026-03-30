<?php
/**
 * ContentBuilder NG Storages controller.
 *
 * Handles actions (copy, delete, publish, ...) for storage list in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Controller
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;

final class StoragesController extends AdminController
{
    /**
     * Nom de la vue liste et item (convention Joomla 6).
     */
    protected $view_list = 'storages';
    protected $view_item = 'storage';

    public function __construct(
        $config,
        MVCFactoryInterface $factory,
        CMSApplicationInterface $app,
        Input $input
    ) {
        // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory, $app, $input);

        // Register Extra tasks
        $this->registerTask('add', 'edit');
        
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel|false  Model object on success; otherwise false on failure.
     */
    public function getModel($name = 'Storage', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }


    /**
     * Retourne les conditions pour limiter le reorder aux enregistrements du même groupe
     * Si tu veux que TOUS les storages soient réordonnés ensemble (pas de groupe), retourne un tableau vide ou ['1 = 1']
     */
    protected function getReorderConditions($table): array
    {
        return [];
    }

    public function display($cachable = false, $urlparams = []): void
    {
        $this->input->set('view', $this->view_list);
        parent::display($cachable, $urlparams);
    }

    /**
     * Copie (custom)
     */
    public function copy(): void
    {
        // Vérif CSRF.
        $this->checkToken();

        $cid = (array) $this->input->get('cid', [], 'array');
        $cid = array_values(array_filter(array_map('intval', $cid)));

        Logger::debug('Click Copy action', [
            'task' => $this->input->getCmd('task'),
            'cid'  => $cid,
        ]);

        $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('StorageModel introuvable');
        }

        try {
            $model->copy($cid);

            // Message Joomla standard (tu peux aussi faire tes propres Text::sprintf)
            $this->setMessage(
                Text::plural('COM_CONTENTBUILDERNG_COPIED', count($cid)),
                'message'
            );
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect(
            Route::_('index.php?option=com_contentbuilderng&task=storages.display&limitstart=' . $this->input->getInt('limitstart'), false),
            Text::_('COM_CONTENTBUILDERNG_COPIED')
        );
    }
}
