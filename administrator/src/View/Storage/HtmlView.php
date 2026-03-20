<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Administrator\View\Storage;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $form;
    public $fields;
    public $tables;
    public $pagination;
    public $ordering;
    public $item;
    public $state;
    public bool $frontend = false;
    public ?int $storageRecordsCount = null;
    public ?bool $storageTableExists = null;
    public string $storageTableLookupName = '';
    public string $storageTableErrorMessage = '';

    public function display($tpl = null): void
    {         
        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        $app = Factory::getApplication();
        $app->input->set('hidemainmenu', true);

        // JS
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');

		if (!$this->frontend) {
            // 1️⃣ Récupération du WebAssetManager
            $document = $this->getDocument();
            $wa = $document->getWebAssetManager();
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
        }    
            
        // Formulaire JForm
        $this->form = $this->getModel()->getForm();

        // Données (l’item)
        $this->item = $this->getModel()->getItem();
        $this->loadStorageTableStatus($this->item);
        $this->storageRecordsCount = $this->getStorageRecordsCount($this->item);

        $this->tables     = $this->get('DbTables');

        // Chargement sécurisé des éléments
        $storageId = (int) ($this->item->id ?? $app->input->getInt('id', 0));

        $this->fields = [];
        $this->pagination = null;
        $this->state = null;

        try {
            $storageId  = (int) ($this->item->id ?? $app->input->getInt('id', 0));
            if ($storageId > 0) {
                $factory = $app->bootComponent('com_contentbuilderng')->getMVCFactory();
                $fieldsModel = $factory->createModel('Storagefields', 'Administrator');

                if (!$fieldsModel) {
                    throw new \RuntimeException('Modèle Storagefields introuvable (factory)');
                }

                // IMPORTANT : fournir le form id au ListModel
                $fieldsModel->setStorageId($storageId);

                // Charge les items
                $this->fields     = $fieldsModel->getItems();
                $this->pagination = $fieldsModel->getPagination();
                $this->state      = $fieldsModel->getState();
                $this->ordering   = ($this->state && $this->state->get('list.ordering') === 'ordering');
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(
                'Erreur lors du chargement des champs : ' . $e->getMessage(),
                'warning'
            );
        }

        $isNew = ((int) ($this->item->id ?? 0) < 1);
        $text  = $isNew ? Text::_('COM_CONTENTBUILDERNG_NEW') : Text::_('COM_CONTENTBUILDERNG_EDIT');
        $storageLabel = trim((string) ($this->item->title ?? ''));
        if ($storageLabel === '') {
            $storageLabel = trim((string) ($this->item->name ?? ''));
        }
        if ($storageLabel === '') {
            $storageLabel = $isNew ? Text::_('COM_CONTENTBUILDERNG_STORAGES') : ('#' . $storageId);
        }

        ToolbarHelper::title(
            Text::_('COM_CONTENTBUILDERNG') . ' / ' . Text::_('COM_CONTENTBUILDERNG_STORAGES') . ' / ' . $storageLabel
            . ' <small><small>[ ' . $text . ' ]</small></small>',
            'logo_left'
        );

        ToolbarHelper::saveGroup(
            [
                ['apply', 'storage.apply', 'JTOOLBAR_APPLY'],
                ['save', 'storage.save', 'JTOOLBAR_SAVE'],
                ['save2new', 'storage.save2new', 'JTOOLBAR_SAVE_AND_NEW'],
            ],
            'btn-success'
        );

        $toolbar = $app->getDocument()->getToolbar('toolbar');
        $dropdown = $toolbar->dropdownButton('storage-status-group');
        $dropdown->text('Actions');
        $dropdown->toggleSplit(false);
        $dropdown->icon('fa fa-ellipsis-h');
        $dropdown->buttonClass('btn btn-action');
        $dropdown->listCheck(true);

        $childToolbar = $dropdown->getChildToolbar();
        $childToolbar->publish('storage.publish')->icon('fa-solid fa-check text-success')->listCheck(true);
        $childToolbar->unpublish('storage.unpublish')->icon('fa-solid fa-circle-xmark text-danger')->listCheck(true);

        $id = (int) ($this->item->id ?? 0);
        $isExternalTable = ((int) ($this->item->bytable ?? 0) === 1);

        $wa->addInlineStyle('.cb-toolbar-preview{margin-inline-start:auto!important;}');
        $applyTip = json_encode(Text::_('JTOOLBAR_APPLY'), JSON_UNESCAPED_UNICODE);
        $saveTip = json_encode(Text::_('JTOOLBAR_SAVE'), JSON_UNESCAPED_UNICODE);
        $saveNewTip = json_encode(Text::_('JTOOLBAR_SAVE_AND_NEW'), JSON_UNESCAPED_UNICODE);
        $closeTip = json_encode(Text::_($isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE'), JSON_UNESCAPED_UNICODE);
        $previewTip = json_encode(Text::_('COM_CONTENTBUILDERNG_PREVIEW'), JSON_UNESCAPED_UNICODE);
        $helpTip = json_encode(Text::_('COM_CONTENTBUILDERNG_HELP_STORAGES_TITLE'), JSON_UNESCAPED_UNICODE);
        $syncLabelTip = json_encode(Text::_('COM_CONTENTBUILDERNG_DATATABLE_SYNC'), JSON_UNESCAPED_UNICODE);
        $wa->addInlineScript(
            "(function () {
                function getToolbarHost() {
                    return document.getElementById('toolbar')
                        || document.querySelector('joomla-toolbar')
                        || document.querySelector('.toolbar');
                }

                function resolveToolbarButtonHost(node) {
                    if (!node) {
                        return null;
                    }

                    var host = node.closest('joomla-toolbar-button, .toolbar-button, .btn-wrapper');
                    if (host) {
                        return host;
                    }

                    if (typeof node.getRootNode === 'function') {
                        var root = node.getRootNode();
                        if (root && root.host) {
                            return root.host;
                        }
                    }

                    return node.parentElement || null;
                }

                function findHostByHref(fragment) {
                    var selector = 'a[href*=\"' + fragment + '\"],button[href*=\"' + fragment + '\"]';
                    var direct = document.querySelector(selector);
                    if (direct) {
                        return resolveToolbarButtonHost(direct);
                    }

                    var toolbarButtons = document.querySelectorAll('joomla-toolbar-button');
                    for (var i = 0; i < toolbarButtons.length; i++) {
                        var host = toolbarButtons[i];
                        var shadow = host.shadowRoot;
                        if (shadow && shadow.querySelector(selector)) {
                            return host;
                        }
                    }

                    return null;
                }

                function getHelpHost() {
                    var helpById = document.getElementById('toolbar-help')
                        || document.querySelector('[id*=\"toolbar-help\"]');
                    if (helpById) {
                        return resolveToolbarButtonHost(helpById);
                    }

                    var byHref = findHostByHref('layout=help');
                    if (byHref) {
                        return byHref;
                    }

                    return null;
                }

                function getPreviewHost() {
                    var previewById = document.getElementById('toolbar-preview')
                        || document.querySelector('[id*=\"toolbar-preview\"]');
                    if (previewById) {
                        return resolveToolbarButtonHost(previewById);
                    }

                    return findHostByHref('task=list.display&storage_id=');
                }

                function getButtonByTask(task) {
                    return document.querySelector('[data-task=\"' + task + '\"]')
                        || document.querySelector('[onclick*=\"' + task + '\"]');
                }

                function applyTooltip(node, message) {
                    var button = resolveToolbarButtonHost(node) || node;
                    if (!button || !message) {
                        return;
                    }
                    button.setAttribute('title', message);
                    button.setAttribute('data-bs-title', message);
                    button.setAttribute('data-bs-toggle', 'tooltip');
                    button.setAttribute('data-bs-placement', 'bottom');

                    if (window.bootstrap && window.bootstrap.Tooltip) {
                        window.bootstrap.Tooltip.getOrCreateInstance(button);
                    }
                }

                function applyStaticTooltips() {
                    applyTooltip(getButtonByTask('storage.apply'), " . $applyTip . ");
                    applyTooltip(getButtonByTask('storage.save'), " . $saveTip . ");
                    applyTooltip(getButtonByTask('storage.save2new'), " . $saveNewTip . ");
                    applyTooltip(getButtonByTask('storage.cancel'), " . $closeTip . ");
                    applyTooltip(getPreviewHost(), " . $previewTip . ");
                    applyTooltip(findHostByHref('layout=help'), " . $helpTip . ");
                    applyTooltip(getButtonByTask('datatable.sync'), " . $syncLabelTip . ");
                }

                function alignPreviewNearHelp() {
                    var previewHost = getPreviewHost();
                    if (!previewHost) {
                        return;
                    }

                    previewHost.classList.add('cb-toolbar-preview');

                    var helpHost = getHelpHost();
                    if (!helpHost || !helpHost.parentNode || previewHost === helpHost) {
                        return;
                    }

                    if (previewHost.parentNode !== helpHost.parentNode || previewHost.nextElementSibling !== helpHost) {
                        helpHost.parentNode.insertBefore(previewHost, helpHost);
                    }
                }

                function init() {
                    var toolbarHost = getToolbarHost();
                    applyStaticTooltips();
                    alignPreviewNearHelp();

                    if (toolbarHost && typeof MutationObserver === 'function') {
                        var observer = new MutationObserver(function () {
                            applyStaticTooltips();
                            alignPreviewNearHelp();
                        });
                        observer.observe(toolbarHost, { childList: true, subtree: true });
                        window.setTimeout(function () {
                            observer.disconnect();
                        }, 6000);
                    }

                    window.setTimeout(applyStaticTooltips, 0);
                    window.setTimeout(applyStaticTooltips, 120);
                    window.setTimeout(applyStaticTooltips, 400);
                    window.setTimeout(alignPreviewNearHelp, 0);
                    window.setTimeout(alignPreviewNearHelp, 120);
                    window.setTimeout(alignPreviewNearHelp, 400);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init, { once: true });
                } else {
                    init();
                }
            }());"
        );

        if ($id > 0 && !$isExternalTable) {
            $previewUntil = time() + 600;
            $previewActorId = (int) ($app->getIdentity()->id ?? 0);
            $previewActorName = trim((string) ($app->getIdentity()->name ?? ''));
            if ($previewActorName === '') {
                $previewActorName = trim((string) ($app->getIdentity()->username ?? ''));
            }
            if ($previewActorName === '') {
                $previewActorName = 'administrator';
            }
            $previewPayload = 'storage:' . $id . '|' . $previewUntil . '|' . $previewActorId . '|' . $previewActorName;
            $previewSig = hash_hmac('sha256', $previewPayload, (string) $app->get('secret'));
            $previewUrl = Uri::root()
                . 'index.php?option=com_contentbuilderng&task=list.display&storage_id='
                . $id
                . '&cb_preview=1'
                . '&cb_preview_until=' . $previewUntil
                . '&cb_preview_actor_id=' . $previewActorId
                . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
                . '&cb_preview_sig=' . $previewSig;
            $toolbar->appendButton(
                'Link',
                'eye',
                Text::_('COM_CONTENTBUILDERNG_PREVIEW'),
                $previewUrl,
                '_blank'
            );

            ToolbarHelper::custom('datatable.sync', 'refresh', '', Text::_('COM_CONTENTBUILDERNG_DATATABLE_SYNC'), false);

            $syncTip = json_encode(Text::_('COM_CONTENTBUILDERNG_DATATABLE_SYNC_TIP'), JSON_UNESCAPED_UNICODE);

            $wa->addInlineScript(
                "(function () {
                    function getButton(task) {
                        return document.querySelector('[data-task=\"' + task + '\"]')
                            || document.querySelector('[onclick*=\"' + task + '\"]');
                    }

                    function applyTooltip(task, message) {
                        var button = getButton(task);
                        if (!button || !message) {
                            return;
                        }
                        button.setAttribute('title', message);
                        button.setAttribute('data-bs-title', message);
                        button.setAttribute('data-bs-toggle', 'tooltip');
                        button.setAttribute('data-bs-placement', 'bottom');

                        if (window.bootstrap && window.bootstrap.Tooltip) {
                            window.bootstrap.Tooltip.getOrCreateInstance(button);
                        }
                    }

                    function init() {
                        applyTooltip('datatable.sync', " . $syncTip . ");
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', init, { once: true });
                    } else {
                        init();
                    }
                }());"
            );
        }
        
        ToolbarHelper::deleteList(
            Text::_('COM_CONTENTBUILDERNG_DELETE_FIELDS_CONFIRM'),
            'storage.listDelete',
            Text::_('COM_CONTENTBUILDERNG_DELETE_FIELDS')
        );

        ToolbarHelper::cancel('storage.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        ToolbarHelper::help(
            'COM_CONTENTBUILDERNG_HELP_STORAGES_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilderng&view=storage&layout=help&tmpl=component'
        );

        parent::display($tpl);
    }

    private function getStorageRecordsCount(object $item): ?int
    {
        if ($this->storageTableExists !== true || $this->storageTableLookupName === '') {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('COUNT(1)')
                ->from($db->quoteName($this->storageTableLookupName));

            $db->setQuery($query);

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function loadStorageTableStatus(object $item): void
    {
        $name = trim((string) ($item->name ?? ''));

        $this->storageTableExists = null;
        $this->storageTableLookupName = '';
        $this->storageTableErrorMessage = '';

        if ($name === '') {
            return;
        }

        $isExternalTable = ((int) ($item->bytable ?? 0) === 1);
        $lookupName = $isExternalTable ? $name : ('#__' . $name);
        $this->storageTableLookupName = $lookupName;

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $tableList = array_map('strtolower', (array) $db->getTableList());
            $resolvedName = strtolower($db->replacePrefix($lookupName));

            $this->storageTableExists = in_array($resolvedName, $tableList, true);

            if ($this->storageTableExists === false) {
                $this->storageTableErrorMessage = 'Table "' . $db->replacePrefix($lookupName) . '" does not exist.';
            }
        } catch (\Throwable $e) {
            $this->storageTableExists = null;
            $this->storageTableErrorMessage = $e->getMessage();
        }
    }
}
