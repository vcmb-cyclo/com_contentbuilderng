<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\View\List;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;
use CB\Component\Contentbuilderng\Site\Helper\PreviewThemeHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuThemeHelper;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListFieldFilterService;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListHelpService;

class HtmlView extends BaseHtmlView
{
    private $frontend = true;
    public int $cb_show_top_bar = 1;
    public int $cb_show_bottom_bar = 0;
    public int $cb_show_author = 1;
    public int $cb_filter_in_title = 0;
    public int $cb_prefix_in_title = 0;
    public int $debug_mode = 0;
    public int $debug_show_bf_id = 0;
    public int $debug_enable_logs = 0;
    public int $debug_show_request_logs = 0;
    public int $debug_show_permissions = 0;
    public int $debug_show_filters = 0;
    public int $debug_show_cb_id = 0;
    public int $direct_storage_read_only = 0;
    public int $show_state_filter = 0;
    public int $list_state_bulk = 0;
    public bool $preview_list_access_configured = false;
    public float $render_time_ms = 0;
    /** @var list<string> */
    public array $embedded_list_errors = [];
    public string $embedded_list_help_url = '';
    public string $preview_theme = '';
    public string $stored_theme = '';

    private function getApp(): SiteApplication
    {
        $app = RuntimeContextHelper::getApplication();

        if (!$app instanceof SiteApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    public function display($tpl = null)
    {
        $debugRenderStart = microtime(true);
        $app = $this->getApp();
        $this->frontend = $app->isClient('site');
        $this->embedded_list_errors = [];

        // Get data from the model
        $subject = $this->get('Data');
        $embeddedTitle = EmbeddedListFieldFilterService::isEmbeddedRequest($app->getInput()->getCmd('cblist_embed', ''))
            ? trim((string) $app->getInput()->getString('cblist_title', ''))
            : '';
        $embeddedTitleSet = EmbeddedListFieldFilterService::isEmbeddedRequest($app->getInput()->getCmd('cblist_embed', ''))
            && $app->getInput()->getBool('cblist_title_set', false);
        if ($embeddedTitleSet) {
            $subject->page_title = $embeddedTitle;
            $subject->show_page_heading = 0;
            $subject->intro_text = htmlspecialchars($embeddedTitle, ENT_QUOTES, 'UTF-8');
        }
        $this->applyEmbeddedFieldFilter($subject, $app);
        foreach ((array) ($subject->embedded_list_validation_errors ?? []) as $validationError) {
            if (!is_array($validationError)) {
                continue;
            }

            $parameter = (string) ($validationError['parameter'] ?? '');
            $value = (string) ($validationError['value'] ?? '');
            if ($parameter === 'sort') {
                $this->embedded_list_errors[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_CBLIST_UNKNOWN_SORT_FIELD',
                    $value
                );
            }
        }
        if ($this->embedded_list_errors !== []) {
            $this->embedded_list_help_url = EmbeddedListHelpService::syntaxUrl();
        }
        $this->preview_list_access_configured = !empty($subject->direct_storage_mode);
        if (!$this->preview_list_access_configured) {
            $config = PackedDataHelper::decodePackedData((string) ($subject->config ?? ''), [], true);
            $permissionsFe = is_array($config) ? (array) ($config['permissions_fe'] ?? []) : [];
            $guestGroupId = (int) $app->get('guest_usergroup', 9);
            $this->preview_list_access_configured = !empty($permissionsFe[1]['listaccess'])
                || ($guestGroupId > 0 && !empty($permissionsFe[$guestGroupId]['listaccess']));
        }
        $this->stored_theme = (string) ($subject->theme_plugin ?? '');
        $this->preview_theme = PreviewThemeHelper::resolve(
            $app->getInput(),
            $app->getInput()->getBool('cb_preview_ok', false)
        );
        $menuTheme = MenuThemeHelper::resolve($app->getInput(), $this->stored_theme);
        $themePlugin = PreviewThemeHelper::apply($menuTheme, $this->preview_theme);
        if ($themePlugin === '' || !PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin)) {
            $themePlugin = 'thoth';
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
        $lists['order_Dir'] = $state?->get('formsd_filter_order_Dir');
        $lists['order'] = $state?->get('formsd_filter_order');
        $lists['filter'] = $state?->get('formsd_filter');
        $lists['filter_state'] = $state?->get('formsd_filter_state');
        $lists['filter_publish'] = $state?->get('formsd_filter_publish');
        $lists['filter_language'] = $state?->get('formsd_filter_language');
        $lists['liststart'] = (int) ($pagination->limitstart ?? $state?->get('list.start') ?? 0);

        $dispatcher = $app->getDispatcher();
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
        $this->list_header_sticky = (int) ($subject->list_header_sticky ?? 0);
        $this->show_preview_link = (int) ($subject->show_preview_link ?? 0);
        $this->direct_storage_mode = (int) ($subject->direct_storage_mode ?? 0);
        $this->direct_storage_id = (int) ($subject->direct_storage_id ?? 0);
        $this->direct_storage_read_only = (int) ($subject->direct_storage_read_only ?? 0);
        $this->direct_storage_unpublished = (int) ($subject->direct_storage_unpublished ?? 0);
        $this->cb_show_top_bar = (int) ($subject->cb_show_top_bar ?? 1);
        $this->cb_show_bottom_bar = (int) ($subject->cb_show_bottom_bar ?? 1);
        $this->cb_show_author = (int) ($subject->cb_show_author ?? 1);
        $this->cb_filter_in_title = (int) ($subject->cb_filter_in_title ?? 0);
        $this->cb_prefix_in_title = (int) ($subject->cb_prefix_in_title ?? 0);
        $this->debug_mode = (int) ($subject->debug_mode ?? 0);
        $this->debug_show_bf_id = (int) ($subject->debug_show_bf_id ?? 0);
        $this->debug_enable_logs = (int) ($subject->debug_enable_logs ?? 0);
        $this->debug_show_request_logs = (int) ($subject->debug_show_request_logs ?? 0);
        $this->debug_show_permissions = (int) ($subject->debug_show_permissions ?? 0);
        $this->debug_show_filters = (int) ($subject->debug_show_filters ?? 0);
        $this->debug_show_cb_id = (int) ($subject->debug_show_cb_id ?? 0);

        $this->page_class = $subject->page_class;
        $this->show_page_heading = $subject->show_page_heading;
        $this->form_name = $subject->name ?? '';
        $this->slug = (string) ($subject->slug ?? '');
        $this->slug2 = (string) ($subject->slug2 ?? '');
        $this->form_id = (int) ($subject->form_id ?? 0);
        $this->labels = is_array($subject->labels ?? null) ? $subject->labels : [];
        $this->visible_cols = is_array($subject->visible_cols ?? null) ? $subject->visible_cols : [];
        $this->linkable_elements = is_array($subject->linkable_elements ?? null) ? $subject->linkable_elements : [];
        $this->show_id_column = $subject->show_id_column;
        $this->page_title = (string) ($subject->page_title ?? '');
        $this->intro_text = (string) ($subject->intro_text ?? '');
        $this->export_xls = (int) ($subject->export_xls ?? 0);
        $this->display_filter = (int) ($subject->display_filter ?? 0);
        $this->show_state_filter = (int) ($subject->show_state_filter ?? 0);
        $this->edit_button = (int) ($subject->edit_button ?? 0);
        $this->new_button = (int) ($subject->new_button ?? 0);
        $this->select_column = (int) ($subject->select_column ?? 0);
        $this->states = is_array($subject->states ?? null) ? $subject->states : [];
        $this->list_state = (int) ($subject->list_state ?? 0);
        $this->list_state_bulk = (int) ($subject->list_state_bulk ?? 0);
        $this->list_publish = (int) ($subject->list_publish ?? 0);
        $this->list_language = (int) ($subject->list_language ?? 0);
        $this->list_article = (int) ($subject->list_article ?? 0);
        $this->list_author = (int) ($subject->list_author ?? 0);
        $this->list_last_modification = (int) ($subject->list_last_modification ?? 0);
        $this->list_rating = (int) ($subject->list_rating ?? 0);
        $this->rating_slots = (int) ($subject->rating_slots ?? 0);
        $this->state = $state;
        $this->state_colors = is_array($subject->state_colors ?? null) ? $subject->state_colors : [];
        $this->state_titles = is_array($subject->state_titles ?? null) ? $subject->state_titles : [];
        $this->published_items = is_array($subject->published_items ?? null) ? $subject->published_items : [];
        $this->languages = is_array($subject->languages ?? null) ? $subject->languages : [];
        $this->lang_codes = is_array($subject->lang_codes ?? null) ? $subject->lang_codes : [];
        $this->cb_record_ids = is_array($subject->cb_record_ids ?? null) ? $subject->cb_record_ids : [];
        $this->title_field = (string) ($subject->title_field ?? '');
        $this->form = $subject->form ?? null;
        $this->lists = $lists;
        $this->items               = is_array($subject->items ?? null) ? $subject->items : [];
        $this->source_type         = (string) ($subject->type ?? '');
        $this->source_reference_id = (int) ($subject->reference_id ?? 0);
        $this->pagination = $pagination;
        $this->total = $total;
        $this->preview_no_list_fields = !empty($subject->preview_no_list_fields);
        $this->invalid_list_setup = !empty($subject->invalid_list_setup);
        $own_only = $app->isClient('site') ? $subject->own_only_fe : $subject->own_only;
        $this->own_only = $own_only;

        if (!empty($this->debug_mode)) {
            $this->render_time_ms = round((microtime(true) - $debugRenderStart) * 1000, 1);
        }

        parent::display($tpl);
    }

    private function applyEmbeddedFieldFilter(object $subject, SiteApplication $app): void
    {
        $input = $app->getInput();
        if (!EmbeddedListFieldFilterService::isEmbeddedRequest($input->getCmd('cblist_embed', ''))) {
            return;
        }

        $rawSelectors = trim((string) $input->getString('cblist_fields', ''));
        if ($rawSelectors === '') {
            return;
        }

        $visibleColumns = is_array($subject->visible_cols ?? null) ? $subject->visible_cols : [];
        $labels = is_array($subject->labels ?? null) ? $subject->labels : [];
        $form = $subject->form ?? null;
        $names = is_object($form) && method_exists($form, 'getElementNames')
            ? (array) $form->getElementNames()
            : [];

        try {
            $match = EmbeddedListFieldFilterService::matchFieldSelectors(
                $visibleColumns,
                $names,
                $rawSelectors
            );
        } catch (\InvalidArgumentException) {
            $this->embedded_list_errors[] = Text::_('COM_CONTENTBUILDERNG_CBLIST_INVALID_FIELDS');
            $subject->show_id_column = 0;
            $subject->visible_cols = [];
            $subject->labels = [];
            $subject->linkable_elements = [];
            return;
        }

        foreach ($match['unknown'] as $unknownSelector) {
            $this->embedded_list_errors[] = Text::sprintf(
                'COM_CONTENTBUILDERNG_CBLIST_UNKNOWN_FIELD',
                $unknownSelector
            );
        }

        $filteredColumns = $match['columns'];
        $allowedReferences = array_fill_keys(
            array_map(static fn(int|string $referenceId): string => (string) $referenceId, $filteredColumns),
            true
        );

        $subject->show_id_column = 0;
        $subject->visible_cols = $filteredColumns;
        $subject->labels = [];
        foreach ($filteredColumns as $referenceId) {
            if (isset($allowedReferences[(string) $referenceId])) {
                $subject->labels[$referenceId] = (string) ($labels[$referenceId] ?? $referenceId);
            }
        }
        $subject->linkable_elements = array_values(
            array_filter(
                is_array($subject->linkable_elements ?? null) ? $subject->linkable_elements : [],
                static fn(int|string $referenceId): bool => isset($allowedReferences[(string) $referenceId])
            )
        );
    }
}
