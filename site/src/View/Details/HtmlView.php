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

namespace CB\Component\Contentbuilderng\Site\View\Details;

use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;

// No direct access
\defined('_JEXEC') or die('Restricted access');


use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Event\Content\ContentPrepareEvent; 
use Joomla\CMS\Event\Content\AfterTitleEvent;
use Joomla\CMS\Event\Content\BeforeDisplayEvent;
use Joomla\CMS\Event\Content\AfterDisplayEvent;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;
use CB\Component\Contentbuilderng\Site\Helper\NavigationLinkHelper;

class HtmlView extends BaseHtmlView
{
    private bool $frontend = false;
    protected $state;
    protected $item;
    protected $form;
    public int $prev_record_start = 0;
    public int $next_record_start = 0;
    public int $cb_show_author = 1;
    public int $cb_show_details_top_bar = 1;
    public int $cb_show_details_bottom_bar = 0;
    public int $show_back_button = 1;
    public int $show_title_breadcrumb = 1;
    public int $cb_filter_in_title = 0;
    public int $cb_prefix_in_title = 0;
    public int $debug_mode = 0;
    public int $debug_show_bf_id = 0;
    public int $debug_enable_logs = 0;
    public int $debug_show_request_logs = 0;
    public int $debug_show_permissions = 0;
    public int $debug_show_filters = 0;
    public int $debug_show_cb_id = 0;
    public int $cb_record_id = 0;
    public int $list_state = 0;
    public array $states = [];
    public array $state_ids = [];
    public array $state_titles = [];
    public array $state_colors = [];
    public int $list_rating = 0;
    public int $rating_slots = 0;
    public float $rating = 0.0;
    public int $rating_count = 0;
    public int $rating_sum = 0;

    private function getApp(): SiteApplication
    {
        $app = RuntimeContextHelper::getApplication();

        if (!$app instanceof SiteApplication) {
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

    private function resolveSiblingRecordIdsByRecordId(object $subject, int $currentRecordId): array
    {
        $app = $this->getApp();
        $currentList = (array) $app->getInput()->get('list', [], 'array');
        $currentListStart = array_key_exists('start', $currentList) ? max(0, (int) $currentList['start']) : 0;
        if (
            $currentRecordId < 1
            || !isset($subject->type)
            || trim((string) $subject->type) === ''
            || !isset($subject->reference_id)
            || trim((string) $subject->reference_id) === ''
        ) {
            return ['previous' => 0, 'next' => 0, 'previous_start' => $currentListStart, 'next_start' => $currentListStart];
        }

        $db = $this->getDatabase();
        $isAdminPreview = $app->getInput()->getBool('cb_preview_ok', false);

        $baseWhere = [
            $db->quoteName('type') . ' = ' . $db->quote((string) $subject->type),
            $db->quoteName('reference_id') . ' = ' . $db->quote((string) $subject->reference_id),
        ];

        if (!$isAdminPreview && !empty($subject->published_only)) {
            $baseWhere[] = $db->quoteName('published') . ' = 1';
        }

        try {
            $prevQuery = $db->getQuery(true)
                ->select($db->quoteName('record_id'))
                ->from($db->quoteName('#__contentbuilderng_records'))
                ->where($baseWhere)
                ->where($db->quoteName('record_id') . ' < ' . (int) $currentRecordId)
                ->order($db->quoteName('record_id') . ' DESC');

            $db->setQuery($prevQuery, 0, 1);
            $previous = (int) $db->loadResult();

            $nextQuery = $db->getQuery(true)
                ->select($db->quoteName('record_id'))
                ->from($db->quoteName('#__contentbuilderng_records'))
                ->where($baseWhere)
                ->where($db->quoteName('record_id') . ' > ' . (int) $currentRecordId)
                ->order($db->quoteName('record_id') . ' ASC');

            $db->setQuery($nextQuery, 0, 1);
            $next = (int) $db->loadResult();
        } catch (\Throwable $e) {
            return ['previous' => 0, 'next' => 0, 'previous_start' => $currentListStart, 'next_start' => $currentListStart];
        }

        return ['previous' => $previous, 'next' => $next];
    }

    private function getListPaginationStateKeys(int $formId): array
    {
        $app = $this->getApp();
        $option = 'com_contentbuilderng';
        $layout = (string) $app->getInput()->getCmd('layout', 'default');
        $storageId = (int) $app->getInput()->getInt('storage_id', 0);

        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $app->getInput()->getInt('Itemid', 0);
        $scope = $storageId > 0 && $formId <= 0 ? ('storage.' . $storageId) : (string) $formId;
        $prefix = $option . '.liststate.' . $scope . '.' . $layout . '.' . $itemId;

        return [
            'limit' => $prefix . '.limit',
            'start' => $prefix . '.start',
        ];
    }

    private function resolveSiblingRecordIds(object $subject): array
    {
        $app = $this->getApp();
        $currentRecordId = (int) $app->getInput()->getInt('record_id', 0);
        if ($currentRecordId < 1) {
            return ['previous' => 0, 'next' => 0];
        }

        $formId = (int) $app->getInput()->getInt('id', 0);
        $paginationKeys = $this->getListPaginationStateKeys($formId);
        $limitStateBackup = $app->getUserState($paginationKeys['limit'], null);
        $startStateBackup = $app->getUserState($paginationKeys['start'], null);
        $originalList = (array) $app->getInput()->get('list', [], 'array');
        $resolvedList = NavigationLinkHelper::resolveListState(
            $app,
            $originalList,
            $formId,
            (string) $app->getInput()->getCmd('layout', 'default'),
            (int) $app->getInput()->getInt('Itemid', 0),
            (int) ($subject->direct_storage_mode ?? 0) === 1,
            (int) ($subject->direct_storage_id ?? 0)
        );
        $listLimit = (int) $resolvedList['limit'];

        try {
            // Reuse the list model so Previous/Next follows the exact active list ordering and filters.
            $listForNavigation = $resolvedList;
            $listForNavigation['start'] = 0;
            $listForNavigation['limit'] = 1000000;
            $app->getInput()->set('list', $listForNavigation);

            $factory = $app->bootComponent('com_contentbuilderng')->getMVCFactory();
            $listModel = $factory->createModel('List', 'Site', ['ignore_request' => false]);

            if (!$listModel || !method_exists($listModel, 'getData')) {
                return $this->resolveSiblingRecordIdsByRecordId($subject, $currentRecordId);
            }

            $listData = $listModel->getData();
            $items = (is_object($listData) && isset($listData->items) && is_array($listData->items))
                ? $listData->items
                : [];

            if (!$items) {
                return ['previous' => 0, 'next' => 0];
            }

            $recordIds = [];
            foreach ($items as $row) {
                if (is_object($row) && isset($row->colRecord)) {
                    $recordIds[] = (int) $row->colRecord;
                }
            }

            $position = array_search($currentRecordId, $recordIds, true);
            if ($position === false) {
                return ['previous' => 0, 'next' => 0];
            }

            $previousStart = $position > 0 ? (int) (floor(($position - 1) / $listLimit) * $listLimit) : 0;
            $nextStart = ($position + 1) < count($recordIds) ? (int) (floor(($position + 1) / $listLimit) * $listLimit) : 0;

            return [
                'previous' => $position > 0 ? (int) $recordIds[$position - 1] : 0,
                'next' => ($position + 1) < count($recordIds) ? (int) $recordIds[$position + 1] : 0,
                'previous_start' => $previousStart,
                'next_start' => $nextStart,
            ];
        } catch (\Throwable $e) {
            return $this->resolveSiblingRecordIdsByRecordId($subject, $currentRecordId);
        } finally {
            $app->getInput()->set('list', $originalList);
            $app->setUserState($paginationKeys['limit'], $limitStateBackup);
            $app->setUserState($paginationKeys['start'], $startStateBackup);
        }
    }

    private function useFallbackDetailsThemeCss(): void
    {
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useStyle('com_contentbuilderng.details-fallback');
    }


    private function toUnicodeSlug(string $string): string
    {
        // Preserve the established Unicode slug normalization.
        $str = preg_replace('/\xE3\x80\x80/', ' ', $string) ?? $string;
        $str = str_replace('-', ' ', $str);
        $str = preg_replace('#[:\#\*"@+=;!&\.%()\]\/\'\\\\|\[]#', ' ', $str) ?? $str;
        $str = str_replace('?', '', $str);
        $str = trim(strtolower($str));
        $str = preg_replace('#\x20+#', '-', $str) ?? $str;

        return $str;
    }

	function display($tpl = null)
	{
        $app = $this->getApp();
		// Get data from the model
		$this->frontend = $app->isClient('site');
        $subject = $this->get('Data');

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

            ToolbarHelper::title($subject->page_title, 'logo_left');
        }

		$event = new \stdClass();

		$db = $this->getDatabase();
		$formIdValue = (int) $subject->form_id;
		$recordIdValue = (string) $subject->record_id;
		$query = $db->getQuery(true)
			->select('articles.' . $db->quoteName('article_id'))
			->from($db->quoteName('#__contentbuilderng_articles', 'articles'))
			->join('INNER', $db->quoteName('#__content', 'content'), 'content.id = articles.article_id')
			->where('(content.state = 1 OR content.state = 0)')
			->where('articles.form_id = :formId')
			->where('articles.record_id = :recordId')
			->bind(':formId', $formIdValue, ParameterType::INTEGER)
			->bind(':recordId', $recordIdValue);
		$db->setQuery($query);
		$article = $db->loadResult();

		$table = new \Joomla\CMS\Table\Content($db);

		// required for pagebreak plugin
		$app->getInput()->set('view', 'article');

		$isNew = true;
		if ($article > 0) {
			$table->load($article);
			$isNew = false;
		}

		$table->cbrecord = $subject;
		$table->text = $table->cbrecord->template;

            $alias = $table->alias ? $this->toUnicodeSlug((string) $table->alias) : $this->toUnicodeSlug((string) $subject->page_title);
		if (trim(str_replace('-', '', $alias)) == '') {
			$datenow = (new Date());
			$alias = $datenow->format("%Y-%m-%d-%H-%M-%S");
		}

		// we pass the slug with a flag in the end, and see in the end if the slug has been used in the output
		$table->slug = ($article > 0 ? $article : 0) . ':' . $alias . ':contentbuilderng_slug_used';

		$registry = new Registry;
		$registry->loadString($table->attribs ?? '{}', 'json');
		PluginHelper::importPlugin('content');

		// Legacy pagebreak note kept for context; the view now forces page 0 explicitly.

		$limitstart = 0;

		$table->text = "<!-- class=\"system-pagebreak\"  -->\n" . $table->text;

			$dispatcher = $app->getDispatcher();
		$dispatcher->dispatch(
			'onContentPrepare',
			new ContentPrepareEvent('onContentPrepare', ['com_content.article', &$table, &$registry, $limitstart])
		);

		// After title
		$eventObj = new AfterTitleEvent(
			'onContentAfterTitle',
			[
				'context' => 'com_content.article',
				'subject' => $table,
				'params'  => $registry,
				'page'    => $limitstart,
			]
		);
		$dispatcher->dispatch('onContentAfterTitle', $eventObj);
		$results = $eventObj->getArgument('result') ?: [];
		$event->afterDisplayTitle = trim(implode("\n", $results));

		// Before display
		$eventObj = new BeforeDisplayEvent(
			'onContentBeforeDisplay',
			[
				'context' => 'com_content.article',
				'subject' => $table,
				'params'  => $registry,
				'page'    => $limitstart,
			]
		);
		$dispatcher->dispatch('onContentBeforeDisplay', $eventObj);
		$results = $eventObj->getArgument('result') ?: [];
		$event->beforeDisplayContent = trim(implode("\n", $results));

		// After display
		$eventObj = new AfterDisplayEvent(
			'onContentAfterDisplay',
			[
				'context' => 'com_content.article',
				'subject' => $table,
				'params'  => $registry,
				'page'    => $limitstart,
			]
		);
		$dispatcher->dispatch('onContentAfterDisplay', $eventObj);
		$results = $eventObj->getArgument('result') ?: [];
		$event->afterDisplayContent = trim(implode("\n", $results));

		// if the slug has been used, we would like to stay in COM_CONTENTBUILDERNG, so we re-arrange the resulting url a little
		if (strstr($subject->template, 'contentbuilderng_slug_used') !== false) {

			$matches = array(array(), array());
			preg_match_all("/\\\"([^\"]*contentbuilderng_slug_used[^\"]*)\\\"/i", $subject->template, $matches);

			foreach ($matches[1] as $match) {
				$sub = '';
				$parameters = explode('?', $match);
				if (count($parameters) == 2) {
					$parameters[1] = str_replace('&amp;', '&', $parameters[1]);
					$parameter = explode('&', $parameters[1]);
					foreach ($parameter as $par) {
						$keyval = explode('=', $par);
						if ($keyval[0] != '' && $keyval[0] != 'option' && $keyval[0] != 'id' && $keyval[0] != 'record_id' && $keyval[0] != 'view' && $keyval[0] != 'catid' && $keyval[0] != 'Itemid' && $keyval[0] != 'lang') {
							$sub .= '&' . $keyval[0] . '=' . (isset($keyval[1]) ? $keyval[1] : '');
						}
					}
				}
					$subject->template = str_replace($match, Route::_('index.php?option=com_contentbuilderng&task=details.display&id=' . $app->getInput()->getInt('id') . '&record_id=' . $app->getInput()->getCmd('record_id', '') . '&Itemid=' . $app->getInput()->getInt('Itemid', 0) . $sub), $subject->template);
			}
		}

		// the same for the case a toc has been created
		if (isset($table->toc) && strstr($table->toc, 'contentbuilderng_slug_used') !== false) {

			preg_match_all("/\\\"([^\"]*contentbuilderng_slug_used[^\"]*)\\\"/i", $table->toc, $matches);

			foreach ($matches[1] as $match) {
				$sub = '';
				$parameters = explode('?', $match);
				if (count($parameters) == 2) {
					$parameters[1] = str_replace('&amp;', '&', $parameters[1]);
					$parameter = explode('&', $parameters[1]);
					foreach ($parameter as $par) {
						$keyval = explode('=', $par);
						if ($keyval[0] != '' && $keyval[0] != 'option' && $keyval[0] != 'id' && $keyval[0] != 'record_id' && $keyval[0] != 'view' && $keyval[0] != 'catid' && $keyval[0] != 'Itemid' && $keyval[0] != 'lang') {
							$sub .= '&' . $keyval[0] . '=' . (isset($keyval[1]) ? $keyval[1] : '');
						}
					}
				}
					$table->toc = str_replace($match, Route::_('index.php?option=com_contentbuilderng&task=details.display&id=' . $app->getInput()->getInt('id') . '&record_id=' . $app->getInput()->getCmd('record_id', '') . '&Itemid=' . $app->getInput()->getInt('Itemid', 0) . $sub), $table->toc);
			}
		}

		if (!isset($table->toc)) {
			$table->toc = '';
		}

		$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
		$subject->template = preg_replace($pattern, '', $subject->template);

			$themePlugin = (string) ($subject->theme_plugin ?? '');
			$fallbackTheme = false;
			if ($themePlugin === '' || !PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin)) {
				$themePlugin = 'thoth';
				PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin);
				$fallbackTheme = true;
			}

		$eventObj = new \Joomla\CMS\Event\GenericEvent('onContentTemplateCss', ['theme' => $themePlugin]);
		$dispatcher->dispatch('onContentTemplateCss', $eventObj);
		$results = $eventObj->getArgument('result') ?: [];
		$this->theme_css = trim(implode('', $results));
        if ($this->theme_css === '' && ($fallbackTheme || $themePlugin === 'thoth')) {
            $this->useFallbackDetailsThemeCss();
        }


		$eventObj = new \Joomla\CMS\Event\GenericEvent('onContentTemplateJavascript', ['theme' => $themePlugin]);
		$dispatcher->dispatch('onContentTemplateJavascript', $eventObj);
		$results = $eventObj->getArgument('result') ?: [];
		$this->theme_js = implode('', $results);

		$this->toc = $table->toc;
		$this->event = $event;

		$this->show_page_heading = $subject->show_page_heading;
		$this->tpl = $subject->template;
		$this->form = $subject->form ?? null;
		$this->form_name = $subject->name ?? '';
		$this->page_title = $subject->page_title;
		$this->created = $subject->created;
		$this->created_by = $subject->created_by;
		$this->modified = $subject->modified;
		$this->modified_by = $subject->modified_by;

		$this->metadesc = $subject->metadesc;
		$this->metakey = $subject->metakey;
		$this->author = $subject->author;
		$this->rights = $subject->rights;
		$this->robots = $subject->robots;
		$this->xreference = $subject->xreference;

		$this->print_button = $subject->print_button;
		$this->show_back_button = $subject->show_back_button;
		$this->show_title_breadcrumb = (int) ($subject->show_title_breadcrumb ?? 1);
		$this->cb_show_author = (int) ($subject->cb_show_author ?? 1);
		$this->cb_show_details_top_bar = (int) ($subject->cb_show_details_top_bar ?? 1);
		$this->cb_show_details_bottom_bar = (int) ($subject->cb_show_details_bottom_bar ?? 0);
		$this->cb_filter_in_title = (int) ($subject->cb_filter_in_title ?? 0);
		$this->cb_prefix_in_title = (int) ($subject->cb_prefix_in_title ?? 0);
		$this->debug_mode = (int) ($subject->debug_mode ?? 0);
		$this->debug_show_bf_id = (int) ($subject->debug_show_bf_id ?? 0);
		$this->debug_enable_logs = (int) ($subject->debug_enable_logs ?? 0);
		$this->debug_show_request_logs = (int) ($subject->debug_show_request_logs ?? 0);
		$this->debug_show_permissions = (int) ($subject->debug_show_permissions ?? 0);
		$this->debug_show_filters = (int) ($subject->debug_show_filters ?? 0);
		$this->debug_show_cb_id = (int) ($subject->debug_show_cb_id ?? 0);
		$this->cb_record_id = (int) ($subject->cb_record_id ?? 0);
		$this->show_id_column = (int) ($subject->show_id_column ?? 0);
		$this->list_state = (int) ($subject->list_state ?? 0);
		$this->states = (array) ($subject->states ?? []);
		$this->state_ids = (array) ($subject->state_ids ?? []);
		$this->state_titles = (array) ($subject->state_titles ?? []);
		$this->state_colors = (array) ($subject->state_colors ?? []);
		$this->list_rating = (int) ($subject->list_rating ?? 0);
		$this->rating_slots = (int) ($subject->rating_slots ?? 0);
		$this->rating = (float) ($subject->rating ?? 0);
		$this->rating_count = (int) ($subject->rating_count ?? 0);
		$this->rating_sum = (int) ($subject->rating_sum ?? 0);
		$this->direct_storage_mode = (int) ($subject->direct_storage_mode ?? 0);
		$this->direct_storage_id = (int) ($subject->direct_storage_id ?? 0);
		$this->direct_storage_unpublished = (int) ($subject->direct_storage_unpublished ?? 0);
		$siblings = $this->resolveSiblingRecordIds($subject);
		$this->prev_record_id = (int) ($siblings['previous'] ?? 0);
		$this->next_record_id = (int) ($siblings['next'] ?? 0);
		$this->prev_record_start = (int) ($siblings['previous_start'] ?? 0);
		$this->next_record_start = (int) ($siblings['next_start'] ?? 0);

		parent::display($tpl);
	}
}
