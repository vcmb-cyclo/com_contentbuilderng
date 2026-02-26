<?php

/**
 * Contrôleur servant à intéragir sur la table décrite par Storage.
 * @package     ContentBuilder NG
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU/GPL
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use CB\Component\Contentbuilderng\Administrator\Service\DatatableService;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;

class DatatableController extends BaseController
{
    public function create(): bool
    {
        $this->checkToken();

        $storageId = (int) $this->input->getInt('id', 0);

        if ($storageId < 1) {
            $jform = $this->input->post->get('jform', [], 'array');
            $storageId = (int) ($jform['id'] ?? 0);
        }

        if (!$storageId) {
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=storage', false), 'Missing storage_id', 'error');
            return false;
        }

        try {
            $component = Factory::getApplication()->bootComponent('com_contentbuilderng');
            if (!$component instanceof ContentbuilderngComponent) {
                throw new \RuntimeException('Unexpected component instance');
            }

            $container = $component->getContainer();
            $service   = $container->get(DatatableService::class);

            $breturn = $service->createForStorage($storageId);
            if ($breturn) {
                $this->setRedirect(
                    Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . $storageId, false),
                    Text::_('COM_CONTENTBUILDERNG_TABLE_CREATED'),
                    'message'
                );
            } else {
                $this->setRedirect(
                    Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . $storageId, false),
                    Text::_('COM_CONTENTBUILDERNG_TABLE_ALREADY_EXISTS'),
                    'warning'
                );
            }
            return true;

        } catch (\Throwable $e) {
            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . $storageId, false),
                $e->getMessage(),
                'error'
            );
            return false;
        }
    }

    public function sync(): bool
    {
        $this->checkToken();

        $storageId = (int) $this->input->getInt('id', 0);

        if ($storageId < 1) {
            $jform = $this->input->post->get('jform', [], 'array');
            $storageId = (int) ($jform['id'] ?? 0);
        }

        if (!$storageId) {
            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&view=storage', false),
                'Missing storage_id',
                'error'
            );
            return false;
        }

        try {
            (new DatatableService())->syncColumnsFromFields($storageId);

            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . $storageId, false),
                Text::_('COM_CONTENTBUILDERNG_DATATABLE_SYNCED'),
                'message'
            );
            return true;
        } catch (\Throwable $e) {
            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . $storageId, false),
                $e->getMessage(),
                'error'
            );
            return false;
        }
    }
}
