<?php

/**
 * Contrôleur servant à intéragir sur la table décrite par Storage.
 * @package     ContentBuilderNG
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Application\CMSApplicationInterface;
use CB\Component\Contentbuilderng\Administrator\Service\DatatableService;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;

class DatatableController extends BaseController
{
    private function getApp(): CMSApplicationInterface
    {
        $app = $this->app;

        if (!$app instanceof CMSApplicationInterface) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    private function getComponent(): ContentbuilderngComponent
    {
        $component = $this->getApp()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private function getDatatableService(): DatatableService
    {
        return $this->getComponent()->getContainer()->get(DatatableService::class);
    }

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
            $breturn = $this->getDatatableService()->createForStorage($storageId);
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
            $service = $this->getDatatableService();
            $service->syncColumnsFromFields($storageId);

            foreach ($service->getLastSyncWarnings() as $warning) {
                $this->getApp()->enqueueMessage($warning, 'warning');
            }

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
