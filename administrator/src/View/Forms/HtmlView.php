<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Forms;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    function display($tpl = null)
    {
        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        $wa = $this->document->getWebAssetManager();
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

        // Et pour le title, garde un identifiant cohérent :
        ToolbarHelper::title(Text::_('COM_CONTENTBUILDERNG') . ' / ' . Text::_('COM_CONTENTBUILDERNG_FORMS'), 'logo_left');
        ToolbarHelper::addNew('form.add');
        ToolbarHelper::custom('forms.copy', 'copy', '', Text::_('COM_CONTENTBUILDERNG_COPY'));
        ToolbarHelper::editList('form.edit');

        $toolbar = Factory::getApplication()->getDocument()->getToolbar('toolbar');

        $statusDropdown = $toolbar->dropdownButton('forms-status-group');
        $statusDropdown->text('Actions');
        $statusDropdown->toggleSplit(false);
        $statusDropdown->icon('fa fa-ellipsis-h');
        $statusDropdown->buttonClass('btn btn-action');
        $statusDropdown->listCheck(true);

        $statusChildToolbar = $statusDropdown->getChildToolbar();
        $statusChildToolbar->publish('forms.publish')->icon('fa-solid fa-check text-success')->listCheck(true);
        $statusChildToolbar->unpublish('forms.unpublish')->icon('fa-solid fa-circle-xmark text-danger')->listCheck(true);
        ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'forms.delete');
        ToolbarHelper::preferences('com_contentbuilderng');
        ToolbarHelper::help(
            'COM_CONTENTBUILDERNG_HELP_VIEWS_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilderng&view=forms&layout=help&tmpl=component'
        );

        $items      = $this->getModel()->getItems();
        $pagination = $this->getModel()->getPagination();
        $state      = $this->getModel()->getState();

        $tags = $this->getModel()->getTags();

        $lists['order']      = (string) $state->get('list.ordering', 'a.ordering');
        $lists['order_Dir']  = (string) $state->get('list.direction', 'ASC');
        $lists['state']      = HTMLHelper::_('grid.state', (string) $state->get('filter.state', ''));
        $lists['filter_state'] = (string) $state->get('filter.state', '');
        $lists['filter_search'] = (string) $state->get('filter.search', '');
        $lists['filter_tag'] = (string) $state->get('filter.tag', '');

        $ordering = ($lists['order'] === 'a.ordering');

        $this->ordering = $ordering;
        $this->tags = $tags;
        $this->state = $state;
        $this->lists = $lists;
        $this->items = $items;
        $this->pagination = $pagination;
        $this->previewLinks = $this->buildPreviewLinks($items);

        parent::display($tpl);
    }

    /**
     * @param array<int,object> $items
     * @return array<int,string>
     */
    private function buildPreviewLinks(array $items): array
    {
        $app = Factory::getApplication();
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
            $formId = (int) ($item->id ?? 0);

            if ($formId < 1) {
                continue;
            }

            $previewPayload = $formId . '|' . $previewUntil . '|' . $previewActorId . '|' . $previewActorName;
            $previewSig = hash_hmac('sha256', $previewPayload, $secret);

            $links[$formId] = Uri::root()
                . 'index.php?option=com_contentbuilderng&task=list.display&id='
                . $formId
                . '&cb_preview=1'
                . '&cb_preview_until=' . $previewUntil
                . '&cb_preview_actor_id=' . $previewActorId
                . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
                . '&cb_preview_sig=' . $previewSig
                . '&cb_admin_return=forms';
        }

        return $links;
    }
}
