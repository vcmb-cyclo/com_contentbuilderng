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
use Joomla\CMS\HTML\HTMLHelper;
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
        $input = $app->input;
        $identity = $app->getIdentity();
        $app->input->set('hidemainmenu', true);

        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useScript('com_contentbuilderng.admin-ui');
        HTMLHelper::_('script', 'com_contentbuilderng/admin-ui.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);

		if (!$this->frontend) {
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
        $storageId = (int) ($this->item->id ?? $input->getInt('id', 0));

        $this->fields = [];
        $this->pagination = null;
        $this->state = null;

        try {
            $storageId = (int) ($this->item->id ?? $input->getInt('id', 0));
            if ($storageId > 0) {
                $factory = $app->bootComponent('com_contentbuilderng')->getMVCFactory();
                $fieldsModel = $factory->createModel('Storagefields', 'Administrator');

                if (!$fieldsModel) {
                    throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_STORAGEFIELDS_MODEL_NOT_FOUND'));
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
                Text::sprintf('COM_CONTENTBUILDERNG_STORAGE_LOAD_FIELDS_ERROR', $e->getMessage()),
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

        if ($id > 0 && !$isExternalTable) {
            $previewUntil = time() + 600;
            $previewActorId = (int) ($identity->id ?? 0);
            $previewActorName = trim((string) ($identity->name ?? ''));
            if ($previewActorName === '') {
                $previewActorName = trim((string) ($identity->username ?? ''));
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
                $this->storageTableErrorMessage = Text::sprintf('COM_CONTENTBUILDERNG_STORAGE_TABLE_DOES_NOT_EXIST', $db->replacePrefix($lookupName));
            }
        } catch (\Throwable $e) {
            $this->storageTableExists = null;
            $this->storageTableErrorMessage = $e->getMessage();
        }
    }
}
