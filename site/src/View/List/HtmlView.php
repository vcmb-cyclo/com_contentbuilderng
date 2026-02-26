<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\View\List;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    private $frontend = true;

    function display($tpl = null)
    {
        $this->frontend = Factory::getApplication()->isClient('site');

        // Get data from the model
        $subject = $this->get('Data');
        $themePlugin = (string) ($subject->theme_plugin ?? '');
        if ($themePlugin === '' || !PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin)) {
            $themePlugin = 'joomla6';
            PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin);
        }

        // 1️⃣ Récupération du WebAssetManager
        $document = $this->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->useScript('core');

        if (!$this->frontend) {
            $wa->addInlineStyle(
                '.icon-logo_left{
                    background-image:url(' . Uri::root(true) . '/media/com_contentbuilderng/images/logo_left.png);
                    background-size:contain;
                    background-repeat:no-repeat;
                    background-position:center;
                    display:inline-block;
                    width:24px;
                    height:24px;
                    vertical-align:middle;
                }'
            );


            ToolbarHelper::title($subject->page_title, 'logo_left');
        }


        $pagination = $this->getModel()->getPagination();
        $total = $this->get('Total');

        $state = $this->get('state');
        $lists['order_Dir'] = $state->get('formsd_filter_order_Dir');
        $lists['order'] = $state->get('formsd_filter_order');
        $lists['filter'] = $state->get('formsd_filter');
        $lists['filter_state'] = $state->get('formsd_filter_state');
        $lists['filter_publish'] = $state->get('formsd_filter_publish');
        $lists['filter_language'] = $state->get('formsd_filter_language');
        $lists['liststart'] = (int) $state->get('list.start');

        $dispatcher = Factory::getApplication()->getDispatcher();
        $eventResult = $dispatcher->dispatch('onListViewCss', new \Joomla\CMS\Event\GenericEvent('onListViewCss', ['theme' => $themePlugin]));
        $results = $eventResult->getArgument('result') ?: [];

        $theme_css = implode('', $results);
        $this->theme_css = $theme_css;

        $eventResult = $dispatcher->dispatch('onListViewJavascript', new \Joomla\CMS\Event\GenericEvent('onListViewJavascript', ['theme' => $themePlugin]));
        $results = $eventResult->getArgument('result') ?: [];

        $theme_js = implode('', $results);
        $this->theme_js = $theme_js;

        $this->show_filter = $subject->show_filter;
        $this->show_records_per_page = $subject->show_records_per_page;
        $this->button_bar_sticky = (int) ($subject->button_bar_sticky ?? 0);
        $this->show_preview_link = (int) ($subject->show_preview_link ?? 0);

        $this->page_class = $subject->page_class;
        $this->show_page_heading = $subject->show_page_heading;
        $this->form_name = $subject->name ?? '';
        $this->slug = $subject->slug;
        $this->slug2 = $subject->slug2;
        $this->form_id = $subject->form_id;
        $this->labels = $subject->labels;
        $this->visible_cols = $subject->visible_cols;
        $this->linkable_elements = $subject->linkable_elements;
        $this->show_id_column = $subject->show_id_column;
        $this->page_title = $subject->page_title;
        $this->intro_text = $subject->intro_text;
        $this->export_xls = $subject->export_xls;
        $this->display_filter = $subject->display_filter;
        $this->edit_button = $subject->edit_button;
        $this->new_button = (int) ($subject->new_button ?? 0);
        $this->select_column = $subject->select_column;
        $this->states = $subject->states;
        $this->list_state = $subject->list_state;
        $this->list_publish = $subject->list_publish;
        $this->list_language = $subject->list_language;
        $this->list_article = $subject->list_article;
        $this->list_author = $subject->list_author;
        $this->list_rating = $subject->list_rating;
        $this->rating_slots = $subject->rating_slots;
        $this->state_colors = $subject->state_colors;
        $this->state_titles = $subject->state_titles;
        $this->published_items = $subject->published_items;
        $this->languages = $subject->languages;
        $this->lang_codes = $subject->lang_codes;
        $this->title_field = $subject->title_field;
        $this->lists = $lists;
        $this->items = $subject->items;
        $this->pagination = $pagination;
        $this->total = $total;
        $this->preview_no_list_fields = !empty($subject->preview_no_list_fields);
        $own_only = Factory::getApplication()->isClient('site') ? $subject->own_only_fe : $subject->own_only;
        $this->own_only = $own_only;
        parent::display($tpl);
    }
}
