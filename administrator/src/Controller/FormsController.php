<?php
/**
 * ContentBuilder NG Forms controller.
 *
 * Handles actions (copy, delete, publish, ...) for forms list in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Controller
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright (C) 2011–2026 by XDA+GIL
 * @license     GNU/GPL v2 or later
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 compatibility rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Model\FormModel;

final class FormsController extends AdminController
{
    /**
     * Nom de la vue liste et item (convention Joomla 6).
     */
    protected $view_list = 'forms';
    protected $view_item = 'form';

    public function __construct(
        $config,
        MVCFactoryInterface $factory,
        CMSApplicationInterface $app,
        Input $input
    ) {
        // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory, $app, $input);

        // Si tu veux absolument garder ces paramètres en session (legacy),
        // tu peux le faire proprement via $this->input.
        /** @var CMSApplication $application */
        $application = Factory::getApplication();
        $session = $application->getSession();

        if ($this->input->getInt('email_users', -1) !== -1) {
            $session->set('email_users', $this->input->get('email_users', 'none'), 'com_contentbuilderng');
        }

        if ($this->input->getInt('email_admins', -1) !== -1) {
            $session->set('email_admins', $this->input->get('email_admins', ''), 'com_contentbuilderng');
        }

        if ($this->input->getInt('slideStartOffset', -1) !== -1) {
            $session->set('slideStartOffset', $this->input->getInt('slideStartOffset', 1), 'com_contentbuilderng');
        }

        if ($this->input->getInt('tabStartOffset', -1) !== -1) {
            $session->set('tabStartOffset', $this->input->getInt('tabStartOffset', 0), 'com_contentbuilderng');
        }
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
    public function getModel($name = 'Form', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Retourne les conditions pour limiter le reorder aux enregistrements du même groupe
     * Si tu veux que TOUS les forms soient réordonnés ensemble (pas de groupe), retourne un tableau vide ou ['1 = 1']
     */
    protected function getReorderConditions($table): array
    {
        return [];
    }

    private function resolvePluralMessage(string $key, int $count, string $fallbackKey): string
    {
        $message = Text::plural($key, $count);

        if ($message === $key || str_starts_with($message, $key . '_')) {
            return Text::_($fallbackKey);
        }

        return $message;
    }

    public function delete(): void
    {
        // Vérif CSRF.
        $this->checkToken();

        $cid = (array) $this->input->get('cid', [], 'array');
        $cid = array_values(array_filter(array_map('intval', $cid)));

        Logger::debug('Click Delete action', [
            'task' => $this->input->getCmd('task'),
            'cid'  => $cid,
        ]);

        /** @var FormModel|false $model */
        $model = $this->getModel('Form', 'Administrator', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('FormModel introuvable');
        }

        try {
            $model->delete($cid);

            $count = count($cid);
            $this->setMessage(
                $this->resolvePluralMessage(
                    'JLIB_APPLICATION_N_ITEMS_DELETED',
                    $count,
                    'COM_CONTENTBUILDERNG_ENTRIES_DELETED'
                ),
                'message'
            );
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect('index.php?option=com_contentbuilderng&task=forms.display');
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

        /** @var FormModel|false $model */
        $model = $this->getModel('Form', 'Administrator', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('FormModel introuvable');
        }

        try {
            $model->copy($cid);

            $count = count($cid);

            $this->setMessage(
                $this->resolvePluralMessage(
                    'JLIB_APPLICATION_N_ITEMS_COPIED',
                    $count,
                    'JLIB_APPLICATION_SAVE_SUCCESS'
                ),
                'message'
            );
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect(
            Route::_('index.php?option=com_contentbuilderng&task=forms.display&limitstart=' . $this->input->getInt('limitstart'),
            false));
    }


}
