<?php

/**
 * @package ContentBuilder
 * @author Markus Bopp / XDA+GIL
 * @link https://breezingforms-ng.vcmb.fr
 * @copyright (C) 2026 by XDA+GIL
 * @license GNU/GPL
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Form;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Application\CMSApplication;
use Joomla\Registry\Registry;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;
use CB\Component\Contentbuilderng\Administrator\Model\FormModel;
use CB\Component\Contentbuilderng\Administrator\Model\ElementsModel;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        $app->getInput()->set('hidemainmenu', true);

        // JS
        /** @var \Joomla\CMS\Document\AdminDocument $document */
        $document = $this->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useScript('com_contentbuilderng.admin-ui');
        HTMLHelper::_('script', 'com_contentbuilderng/admin-ui.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
        $wa->useStyle('com_contentbuilderng.coloris.css');
        $wa->useScript('com_contentbuilderng.coloris.js');

        $wa->addInlineStyle(
            '.icon-logo_left{'
            . 'background-image:url(' . Uri::root(true) . '/media/com_contentbuilderng/images/logo_left.png);'
            . 'background-size:contain;'
            . 'background-repeat:no-repeat;'
            . 'background-position:center;'
            . 'display:inline-block;'
            . 'width:48px;'
            . 'height:48px;'
            . 'vertical-align:middle;'
            . '}'
        );


        // Formulaire JForm
        /** @var FormModel $model */
        $model = $this->getModel();
        $this->form = $model->getForm();

        // Données (l’item)
        $this->item = $model->getItem();

        // Chargement sécurisé des éléments
        $input = $app->getInput();
        $identity = $app->getIdentity();
        $formId = (int) ($this->item->id ?? $input->getInt('id', 0));

        $this->elements = [];
        $this->all_elements = [];
        $this->pagination = null;
        $this->state = null;

        try {
            $formId = (int) ($formId ?? 0);
            if ($formId > 0) {
                /** @var ContentbuilderngComponent $component */
                $component = $app->bootComponent('com_contentbuilderng');
                if (!$component instanceof ContentbuilderngComponent) {
                    throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_COMPONENT_FACTORY_NOT_FOUND'));
                }
                $factory = $component->getMVCFactory();
                /** @var ElementsModel $elementsModel */
                $elementsModel = $factory->createModel('Elements', 'Administrator');

                if (!$elementsModel) {
                    throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ELEMENTS_MODEL_NOT_FOUND'));
                }

                // IMPORTANT : fournir le form id au ListModel
                $elementsModel->setFormId($formId);

                // Charge les items
                $this->elements   = $elementsModel->getItems();
                $this->all_elements = $elementsModel->getAllElements($formId) ?? [];
                $this->pagination = $elementsModel->getPagination();
                $this->state      = $elementsModel->getState();

                if ($this->pagination) {
                    $this->pagination->setAdditionalUrlParam('option', 'com_contentbuilderng');
                    $this->pagination->setAdditionalUrlParam('view', 'form');
                    $this->pagination->setAdditionalUrlParam('layout', 'edit');
                    $this->pagination->setAdditionalUrlParam('id', (string) $formId);
                }
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(
                Text::sprintf('COM_CONTENTBUILDERNG_ELEMENTS_LOAD_ERROR', $e->getMessage()),
                'warning'
            );
        }

        $isNew = ($formId < 1);
        $text  = $isNew ? Text::_('COM_CONTENTBUILDERNG_NEW') : Text::_('COM_CONTENTBUILDERNG_EDIT');
        $formLabel = trim((string) ($this->item->name ?? ''));
        if ($formLabel === '') {
            $formLabel = $isNew ? Text::_('COM_CONTENTBUILDERNG_FORM') : ('#' . $formId);
        }

        ToolbarHelper::title(
            Text::_('COM_CONTENTBUILDERNG') . ' / ' . Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_FORMS') . ' / ' . $formLabel
                . ' <small><small>[ ' . $text . ' ]</small></small>',
            'logo_left'
        );

        ToolbarHelper::saveGroup(
            [
                ['apply', 'form.apply', 'JTOOLBAR_APPLY'],
                ['save', 'form.save', 'JTOOLBAR_SAVE'],
                ['save2new', 'form.save2new', 'JTOOLBAR_SAVE_AND_NEW'],
            ],
            'btn-success'
        );

        /** @var \Joomla\CMS\Toolbar\Toolbar $toolbar */
        /** @var \Joomla\CMS\Toolbar\Toolbar $toolbar */
        $toolbar = $document->getToolbar('toolbar');

        $statusDropdown = $toolbar->dropdownButton('form-status-group');
        $statusDropdown->text(Text::_('COM_CONTENTBUILDERNG_TOOLBAR_ACTIONS'));
        $statusDropdown->toggleSplit(false);
        $statusDropdown->icon('fa fa-ellipsis-h');
        $statusDropdown->buttonClass('btn btn-action');
        $statusDropdown->listCheck(true);

        $statusChildToolbar = $statusDropdown->getChildToolbar();
        $statusChildToolbar->standardButton('list_include')
            ->task('form.list_include')
            ->text('COM_CONTENTBUILDERNG_LIST_INCLUDE')
            ->icon('fa fa-list text-success')
            ->listCheck(true);
        $statusChildToolbar->standardButton('no_list_include')
            ->task('form.no_list_include')
            ->text('COM_CONTENTBUILDERNG_NO_LIST_INCLUDE')
            ->icon('fa fa-list text-danger')
            ->listCheck(true);
        $statusChildToolbar->standardButton('search_include')
            ->task('form.search_include')
            ->text('COM_CONTENTBUILDERNG_SEARCH_INCLUDE')
            ->icon('fa fa-search text-success')
            ->listCheck(true);
        $statusChildToolbar->standardButton('no_search_include')
            ->task('form.no_search_include')
            ->text('COM_CONTENTBUILDERNG_NO_SEARCH_INCLUDE')
            ->icon('fa fa-search text-danger')
            ->listCheck(true);
        $statusChildToolbar->standardButton('linkable')
            ->task('form.linkable')
            ->text('COM_CONTENTBUILDERNG_LINKABLE')
            ->icon('fa fa-link text-success')
            ->listCheck(true);
        $statusChildToolbar->standardButton('not_linkable')
            ->task('form.not_linkable')
            ->text('COM_CONTENTBUILDERNG_NOT_LINKABLE')
            ->icon('fa fa-link text-danger')
            ->listCheck(true);
        $statusChildToolbar->standardButton('editable')
            ->task('form.editable')
            ->text('COM_CONTENTBUILDERNG_EDITABLE')
            ->icon('fa fa-pen text-success')
            ->listCheck(true);
        $statusChildToolbar->standardButton('not_editable')
            ->task('form.not_editable')
            ->text('COM_CONTENTBUILDERNG_NOT_EDITABLE')
            ->icon('fa fa-pen text-danger')
            ->listCheck(true);
        $statusChildToolbar->publish('form.publish')->icon('fa-solid fa-check text-success')->listCheck(true);
        $statusChildToolbar->unpublish('form.unpublish')->icon('fa-solid fa-circle-xmark text-danger')->listCheck(true);

        ToolbarHelper::cancel('form.cancel', 'JTOOLBAR_CLOSE');

        if ($formId > 0) {
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
        $previewPayload = PreviewLinkHelper::buildPayload((string) $formId, $previewUntil, $previewActorId, $previewActorName, $previewUserId);
            $previewSig = hash_hmac('sha256', $previewPayload, (string) $app->get('secret'));
            $previewUrl = Uri::root()
                . 'index.php?option=com_contentbuilderng&task=list.display&id='
                . $formId
                . '&cb_preview=1'
                . '&cb_preview_until=' . $previewUntil
                . '&cb_preview_actor_id=' . $previewActorId
                . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
                . '&cb_preview_user_id=' . $previewUserId
                . '&cb_preview_sig=' . $previewSig;
            $toolbar->appendButton(
                'Link',
                'eye',
                Text::_('COM_CONTENTBUILDERNG_PREVIEW'),
                $previewUrl,
                '_blank'
            );
        }

        ToolbarHelper::help(
            'COM_CONTENTBUILDERNG_HELP_VIEWS_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilderng&view=form&layout=help&tmpl=component'
        );

        // Compat template / listes
        $this->listOrder = (string) $this->state?->get('list.ordering', 'ordering');
        $this->listDirn  = (string) $this->state?->get('list.direction', 'ASC');

        $lists['order']     = $this->listOrder;
        $lists['order_Dir'] = $this->listDirn;

        // ordering actif seulement si tri par ordering
        $this->ordering = ($this->listOrder === 'ordering');


        // Données additionnelles
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $q = $db->getQuery(true)
            ->select([
                'node.title AS ' . $db->quoteName('text'),
                'node.id AS ' . $db->quoteName('value'),
                'node.title AS ' . $db->quoteName('title'),
                '(COUNT(parent.id) - 1) AS ' . $db->quoteName('depth'),
                "GROUP_CONCAT(parent.title ORDER BY parent.lft SEPARATOR ' / ') AS " . $db->quoteName('path'),
            ])
            ->from($db->quoteName('#__usergroups', 'node'))
            ->from($db->quoteName('#__usergroups', 'parent'))
            ->where('node.lft BETWEEN parent.lft AND parent.rgt')
            ->group('node.id')
            ->order('node.lft');

        $db->setQuery($q);
        $this->gmap = $db->loadObjectList() ?? [];
        $this->referencingMenuItems = $this->getReferencingMenuItems($formId);


        $config = PackedDataHelper::decodePackedData($this->item->config ?? null, null, true);
        $this->item->config = is_array($config) ? $config : null;

        $this->list_states_action_plugins = $model->getListStatesActionPlugins();
        $this->verification_plugins       = $model->getVerificationPlugins();
        $this->theme_plugins              = $model->getThemePlugins();

        HTMLHelper::_('behavior.keepalive');
        $this->setLayout('edit');
        parent::display($tpl);
    }

    private function getReferencingMenuItems(int $formId): array
    {
        if ($formId < 1) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('menutype'),
                $db->quoteName('link'),
                $db->quoteName('params'),
                $db->quoteName('published'),
            ])
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_contentbuilderng%'))
            ->order($db->quoteName('menutype') . ' ASC')
            ->order($db->quoteName('title') . ' ASC');
        $db->setQuery($query);

        $rows = (array) $db->loadObjectList();
        $items = [];

        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }

            $menuFormId = $this->extractMenuFormId((string) ($row->params ?? ''), (string) ($row->link ?? ''));

            if ($menuFormId !== $formId) {
                continue;
            }

            $menuId = (int) ($row->id ?? 0);
            $items[] = [
                'id' => $menuId,
                'title' => trim((string) ($row->title ?? '')) ?: ('#' . $menuId),
                'menutype' => (string) ($row->menutype ?? ''),
                'link' => (string) ($row->link ?? ''),
                'published' => (int) ($row->published ?? 0),
                'edit_link' => $menuId > 0
                    ? Route::_('index.php?option=com_menus&view=item&client_id=0&layout=edit&id=' . $menuId, false)
                    : '',
            ];
        }

        return $items;
    }

    private function extractMenuFormId(string $paramsJson, string $link): int
    {
        if ($link !== '') {
            $queryString = parse_url($link, PHP_URL_QUERY);

            if (is_string($queryString)) {
                parse_str($queryString, $queryParams);

                if (!empty($queryParams['id'])) {
                    return (int) $queryParams['id'];
                }
            }
        }

        if ($paramsJson === '') {
            return 0;
        }

        $registry = new Registry();
        $registry->loadString($paramsJson);
        $settings = $registry->get('settings');

        if (is_object($settings) && method_exists($settings, 'get')) {
            return (int) $settings->get('form_id', 0);
        }

        if (is_array($settings) && array_key_exists('form_id', $settings)) {
            return (int) $settings['form_id'];
        }

        if (is_object($settings) && isset($settings->form_id)) {
            return (int) $settings->form_id;
        }

        return (int) $registry->get('settings.form_id', $registry->get('form_id', 0));
    }

}
