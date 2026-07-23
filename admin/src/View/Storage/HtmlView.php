<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Administrator\View\Storage;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Model\StoragefieldsModel;
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
    public string $wizardReturnUrl = '';

    private function getApp(): CMSApplicationInterface
    {
        $app = RuntimeContextHelper::getApplication();

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

    private function getDatabase(): DatabaseInterface
    {
        return $this->getComponent()->getContainer()->get(DatabaseInterface::class);
    }

    #[\Override]
    public function display($tpl = null): void
    {         
        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        $app = $this->getApp();
        $input = $app->getInput();
        $identity = $app->getIdentity();
        $app->getInput()->set('hidemainmenu', true);

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useScript('com_contentbuilderng.admin-ui');
        HTMLHelper::_('script', 'com_contentbuilderng/admin-ui.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
        Text::script('COM_CONTENTBUILDERNG_CONFIRM_DELETE_ONE');
        Text::script('COM_CONTENTBUILDERNG_CONFIRM_DELETE_MANY');

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
                }
                /* The admin template adds an external-link icon to every target="_blank"
                   anchor; the Preview button already has its own eye icon for that. */
                #toolbar-link::before{content:none;}'
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
                $factory = $this->getComponent()->getMVCFactory();
                $fieldsModel = $factory->createModel('Storagefields', 'Administrator');

                if (!$fieldsModel instanceof StoragefieldsModel) {
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

        $isFromWizard = $input->getBool('wizard', false);
        $breadcrumbMiddle = $isFromWizard
            ? '<a href="' . htmlspecialchars(Route::_('index.php?option=com_contentbuilderng&view=storagewizard', false), ENT_QUOTES, 'UTF-8') . '">'
                . Text::_('COM_CONTENTBUILDERNG_WIZARD_TITLE')
                . ' <span class="fa-solid fa-wand-magic-sparkles mx-2" aria-hidden="true"></span></a>'
            : Text::_('COM_CONTENTBUILDERNG_STORAGES') . ' <span class="fa-solid fa-database mx-2" aria-hidden="true"></span>';

        ToolbarHelper::title(
            Text::_('COM_CONTENTBUILDERNG') . ' &gt; ' . $breadcrumbMiddle . ' &gt; ' . $storageLabel
            . ' <small><small>[ ' . $text . ' ]</small></small>',
            'logo_left'
        );

        // Le retour au fil de l'assistant (bouton "Fermer"/"Enregistrer",
        // géré nativement par FormController::cancel()/save() via `return`)
        // doit continuer sur l'assistant plutôt que sur la liste Storages.
        $this->wizardReturnUrl = $isFromWizard
            ? base64_encode('index.php?option=com_contentbuilderng&view=storagewizard')
            : '';

        $saveButtons = [
            ['apply', 'storage.apply', 'JTOOLBAR_APPLY'],
            ['save', 'storage.save', 'JTOOLBAR_SAVE'],
        ];

        if (!$isFromWizard) {
            $saveButtons[] = ['save2new', 'storage.save2new', 'JTOOLBAR_SAVE_AND_NEW'];
        }

        ToolbarHelper::saveGroup($saveButtons, 'btn-success');

        $toolbar = $this->getDocument()->getToolbar('toolbar');
        $dropdown = $toolbar->dropdownButton('storage-status-group');
        $dropdown->text(Text::_('COM_CONTENTBUILDERNG_TOOLBAR_ACTIONS'));
        $dropdown->toggleSplit(false);
        $dropdown->icon('fa fa-ellipsis-h');
        $dropdown->buttonClass('btn btn-action');
        $dropdown->listCheck(true);
        $dropdown->attributes(['title' => Text::_('COM_CONTENTBUILDERNG_TOOLBAR_ACTIONS_TIP')]);

        $childToolbar = $dropdown->getChildToolbar();
        $childToolbar->publish('storage.publish')
            ->icon('fa-solid fa-check text-success')
            ->listCheck(true)
            ->attributes(['title' => Text::_('COM_CONTENTBUILDERNG_PUBLISH_ELEMENTS_TIP')]);
        $childToolbar->unpublish('storage.unpublish')
            ->icon('fa-solid fa-circle-xmark text-danger')
            ->listCheck(true)
            ->attributes(['title' => Text::_('COM_CONTENTBUILDERNG_UNPUBLISH_ELEMENTS_TIP')]);
        $childToolbar->delete('storage.listDelete', 'COM_CONTENTBUILDERNG_DELETE_FIELDS')
            ->message('COM_CONTENTBUILDERNG_DELETE_FIELDS_CONFIRM')
            ->listCheck(true)
            ->attributes(['title' => Text::_('COM_CONTENTBUILDERNG_DELETE_FIELDS_TIP')]);

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
            $previewUserId = (int) ($identity->id ?? 0);
            $previewPayload = PreviewLinkHelper::buildPayload('storage:' . $id, $previewUntil, $previewActorId, $previewActorName, $previewUserId);
            $previewSig = hash_hmac('sha256', $previewPayload, (string) $app->get('secret'));
            $previewUrl = Route::link(
                'site',
                'index.php?option=com_contentbuilderng&view=list&storage_id=' . $id
                    . '&cb_preview=1'
                    . '&cb_preview_until=' . $previewUntil
                    . '&cb_preview_actor_id=' . $previewActorId
                    . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
                    . '&cb_preview_user_id=' . $previewUserId
                    . '&cb_preview_sig=' . $previewSig,
                false,
                Route::TLS_IGNORE,
                true
            );
            $toolbar->link(Text::_('COM_CONTENTBUILDERNG_PREVIEW'), $previewUrl)
                ->icon('icon-eye')
                ->target('_blank')
                ->attributes(['title' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_TIP')]);

            $toolbar->standardButton('datatable.sync')
                ->task('datatable.sync')
                ->text('COM_CONTENTBUILDERNG_DATATABLE_SYNC')
                ->icon('fa fa-sync')
                ->listCheck(false)
                ->attributes(['title' => Text::_('COM_CONTENTBUILDERNG_DATATABLE_SYNC_TIP')]);
        }

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
            $db = $this->getDatabase();
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
            $db = $this->getDatabase();
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
