<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Storages;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;
use CB\Component\Contentbuilderng\Administrator\Model\StoragesModel;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

/**
 * Vue Storages pour ContentBuilder
 */
class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    protected $lists;
    protected $ordering;

    private function getApp(): CMSApplication
    {
        $app = $this->app;

        if (!$app instanceof CMSApplication) {
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

    /**
     * Méthode d'affichage de la vue
     *
     * @param   string  $tpl  Nom du template alternatif
     * @return  void
     */
    #[\Override]
    public function display($tpl = null)
    {
        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        /** @var StoragesModel $model */
        $model = $this->getModel();

        try {
            // Récupération des données du modèle
            $this->items      = (array) ($model->getItems() ?? []);
            $this->pagination = $model->getPagination();
            $this->state      = $model->getState();
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), (int) $e->getCode(), $e);
        }

        // Préparation des filtres et tris (Joomla standard)
        $this->lists['order_Dir'] = (string) $this->state->get('list.direction', 'ASC');
        $this->lists['order']     = (string) $this->state->get('list.ordering', 'a.ordering');

        // Si tu as un filtre published standard
        $this->lists['state']     = HTMLHelper::_('grid.state', (string) $this->state->get('filter.state', ''));

        // Ton flag ordering (ton template compare à "ordering" mais toi tu utilises souvent "a.ordering")
        $this->ordering = ($this->lists['order'] === 'a.ordering' || $this->lists['order'] === 'ordering');
        $this->previewLinks = $this->buildPreviewLinks($this->items);

        // Ajout du CSS personnalisé (méthode propre)
        $this->addToolbarIcon();

        $this->document->getWebAssetManager()->useScript('table.columns');

        // Barre d'outils
        $this->addToolbar();

        $this->document->getWebAssetManager()->useScript('keepalive');
        parent::display($tpl);
    }

    /**
     * @param array<int,object> $items
     * @return array<int,string>
     */
    private function buildPreviewLinks(array $items): array
    {
        $app = $this->getApp();
        $identity = $app->getIdentity();
        $secret = (string) $app->get('secret');

        if ($secret === '') {
            return [];
        }

        $previewUntil = time() + 600;
        $previewActorId = (int) ($app->getIdentity()->id ?? 0);
        $previewActorName = trim((string) ($app->getIdentity()->name ?? ''));

        if ($previewActorName === '') {
            $previewActorName = trim((string) ($app->getIdentity()->username ?? ''));
        }

        if ($previewActorName === '') {
            $previewActorName = 'administrator';
        }

        $links = [];

        foreach ($items as $item) {
            $storageId = (int) ($item->id ?? 0);
            $isExternal = (int) ($item->bytable ?? 0) === 1;

            if ($storageId < 1 || $isExternal) {
                continue;
            }

            $previewUserId = (int) ($identity->id ?? 0);
            $previewPayload = PreviewLinkHelper::buildPayload('storage:' . $storageId, $previewUntil, $previewActorId, $previewActorName, $previewUserId);
            $previewSig = hash_hmac('sha256', $previewPayload, $secret);

            $links[$storageId] = Route::link(
                'site',
                'index.php?option=com_contentbuilderng&view=list&storage_id=' . $storageId
                    . '&cb_preview=1'
                    . '&cb_preview_until=' . $previewUntil
                    . '&cb_preview_actor_id=' . $previewActorId
                    . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
                    . '&cb_preview_user_id=' . $previewUserId
                    . '&cb_preview_sig=' . $previewSig
                    . '&cb_admin_return=storages',
                false,
                Route::TLS_IGNORE,
                true
            );
        }

        return $links;
    }

    /**
     * Ajoute la barre d'outils
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_CONTENTBUILDERNG') . ' / ' . Text::_('COM_CONTENTBUILDERNG_STORAGES'), 'logo_left');

        ToolbarHelper::addNew('storage.add');
        ToolbarHelper::editList('storage.edit');
        ToolbarHelper::deleteList('COM_CONTENTBUILDERNG_CONFIRM_STORAGE_DELETE_MESSAGE', 'storage.delete');
        /** @var HtmlDocument $document */
        $document = $this->getDocument();
        $toolbar = $document->getToolbar('toolbar');

        $statusDropdown = $toolbar->dropdownButton('storages-status-group');
        $statusDropdown->text(Text::_('COM_CONTENTBUILDERNG_TOOLBAR_ACTIONS'));
        $statusDropdown->toggleSplit(false);
        $statusDropdown->icon('fa fa-ellipsis-h');
        $statusDropdown->buttonClass('btn btn-action');
        $statusDropdown->listCheck(true);

        $statusChildToolbar = $statusDropdown->getChildToolbar();
        $statusChildToolbar->publish('storages.publish')->icon('fa-solid fa-check text-success')->listCheck(true);
        $statusChildToolbar->unpublish('storages.unpublish')->icon('fa-solid fa-circle-xmark text-danger')->listCheck(true);

        ToolbarHelper::preferences('com_contentbuilderng');
        ToolbarHelper::help(
            'COM_CONTENTBUILDERNG_HELP_STORAGES_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilderng&view=storages&layout=help&tmpl=component'
        );
    }

    /**
     * Ajoute l'icône personnalisée pour le titre de la barre d'outils
     */
    protected function addToolbarIcon()
    {
        $document = $this->getDocument();
        $wa = $document->getWebAssetManager();

         // Icon addition.
        $wa->addInlineStyle(
            '.icon-logo_left{
                background-image:url(' . Uri::root(true) . '/media/com_contentbuilderng/images/logo_left.png);
                background-size:contain;
                background-repeat:no-repeat;
                background-position:center;
                display:inline-block;
                width:48px;
                height:48px;
                vertical-align:middle;
            }'
        );

        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useStyle('com_contentbuilderng.admin-toolbar'); // A déclarer dans joomla.asset.json
    }
}
