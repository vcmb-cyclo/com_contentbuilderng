<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\View\Details;

// No direct access
\defined('_JEXEC') or die('Restricted access');


use Joomla\CMS\Factory;
use Joomla\CMS\Event\Content\ContentPrepareEvent; 
use Joomla\CMS\Event\Content\AfterTitleEvent;
use Joomla\CMS\Event\Content\BeforeDisplayEvent;
use Joomla\CMS\Event\Content\AfterDisplayEvent;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    private bool $frontend = false;
    protected $state;
    protected $item;
    protected $form;

    private function resolveSiblingRecordIdsByRecordId(object $subject, int $currentRecordId): array
    {
        if (
            $currentRecordId < 1
            || !isset($subject->type)
            || trim((string) $subject->type) === ''
            || !isset($subject->reference_id)
            || trim((string) $subject->reference_id) === ''
        ) {
            return ['previous' => 0, 'next' => 0];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $isAdminPreview = Factory::getApplication()->input->getBool('cb_preview_ok', false);

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
            return ['previous' => 0, 'next' => 0];
        }

        return ['previous' => $previous, 'next' => $next];
    }

    private function getListPaginationStateKeys(int $formId): array
    {
        $app = Factory::getApplication();
        $option = 'com_contentbuilderng';
        $layout = (string) $app->input->getCmd('layout', 'default');
        $storageId = (int) $app->input->getInt('storage_id', 0);

        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $app->input->getInt('Itemid', 0);
        $scope = $storageId > 0 && $formId <= 0 ? ('storage.' . $storageId) : (string) $formId;
        $prefix = $option . '.liststate.' . $scope . '.' . $layout . '.' . $itemId;

        return [
            'limit' => $prefix . '.limit',
            'start' => $prefix . '.start',
        ];
    }

    private function resolveSiblingRecordIds(object $subject): array
    {
        $app = Factory::getApplication();
        $currentRecordId = (int) $app->input->getInt('record_id', 0);
        $fallback = $this->resolveSiblingRecordIdsByRecordId($subject, $currentRecordId);

        if ($currentRecordId < 1) {
            return $fallback;
        }

        $originalList = (array) $app->input->get('list', [], 'array');
        $formId = (int) $app->input->getInt('id', 0);
        $paginationKeys = $this->getListPaginationStateKeys($formId);
        $limitStateBackup = $app->getUserState($paginationKeys['limit'], null);
        $startStateBackup = $app->getUserState($paginationKeys['start'], null);

        try {
            // Reuse the list model so Previous/Next follows the exact active list ordering and filters.
            $listForNavigation = $originalList;
            $listForNavigation['start'] = 0;
            $listForNavigation['limit'] = 1000000;
            $app->input->set('list', $listForNavigation);

            $factory = $app->bootComponent('com_contentbuilderng')->getMVCFactory();
            $listModel = $factory->createModel('List', 'Site', ['ignore_request' => false]);

            if (!$listModel || !method_exists($listModel, 'getData')) {
                return $fallback;
            }

            $listData = $listModel->getData();
            $items = (is_object($listData) && isset($listData->items) && is_array($listData->items))
                ? $listData->items
                : [];

            if (!$items) {
                return $fallback;
            }

            $recordIds = [];
            foreach ($items as $row) {
                if (is_object($row) && isset($row->colRecord)) {
                    $recordIds[] = (int) $row->colRecord;
                }
            }

            $position = array_search($currentRecordId, $recordIds, true);
            if ($position === false) {
                return $fallback;
            }

            return [
                'previous' => $position > 0 ? (int) $recordIds[$position - 1] : 0,
                'next' => ($position + 1) < count($recordIds) ? (int) $recordIds[$position + 1] : 0,
            ];
        } catch (\Throwable $e) {
            return $fallback;
        } finally {
            $app->input->set('list', $originalList);
            $app->setUserState($paginationKeys['limit'], $limitStateBackup);
            $app->setUserState($paginationKeys['start'], $startStateBackup);
        }
    }

    private function getFallbackDetailsThemeCss(): string
    {
        return <<<'CSS'
.cbDetailsWrapper{
    max-width:1120px;
    margin:.7rem auto 1.35rem;
    padding:.82rem .92rem .98rem;
    border:1px solid rgba(36,61,86,.12);
    border-radius:.86rem;
    background:radial-gradient(circle at top right,rgba(13,110,253,.08),transparent 38%),linear-gradient(180deg,#fff 0,#f8fbff 100%);
    box-shadow:0 .6rem 1.35rem rgba(16,32,56,.08)
}
.cbDetailsWrapper>h1.display-6{margin-bottom:1rem!important;font-weight:700;letter-spacing:.01em}
.cbDetailsWrapper>h1.display-6::after{content:"";display:block;width:4.5rem;height:.24rem;margin-top:.55rem;border-radius:999px;background:linear-gradient(90deg,#0d6efd 0,#3f8cff 100%)}
.cbDetailsWrapper .cbToolBar{padding:.35rem 0;background:transparent}
.cbDetailsWrapper .cbToolBar .cbButton.btn{border-radius:999px;font-weight:600;padding-inline:.95rem;box-shadow:0 .32rem .85rem rgba(16,32,56,.12)}
.cbDetailsWrapper .cbTitleRecordNav .cbButton.btn{border-radius:999px;font-weight:600;letter-spacing:.01em;font-size:.84rem;padding:.32rem .76rem;box-shadow:0 .2rem .56rem rgba(16,32,56,.11);display:inline-flex;align-items:center}
.cbDetailsWrapper .cbTitleRecordNav .cbButton.btn [class^="fa-"],.cbDetailsWrapper .cbTitleRecordNav .cbButton.btn [class*=" fa-"]{color:#0d6efd}
.cbDetailsWrapper .cbDetailsBody{margin:.24rem 0 .4rem;padding:.54rem .58rem .24rem;border:1px solid rgba(36,61,86,.14);border-radius:.64rem;background:#fff;box-shadow:0 .24rem .62rem rgba(16,32,56,.05)}
.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed{margin:0;padding:0;list-style:none;display:grid;gap:.3rem}
.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed>li{margin:0;padding:.4rem .5rem;border:1px solid rgba(36,61,86,.14);border-radius:.54rem;background:linear-gradient(180deg,#fff 0,#f7fbff 100%);display:grid;grid-template-columns:minmax(190px,31%) 1fr;gap:.42rem;align-items:start;box-shadow:0 .14rem .42rem rgba(16,32,56,.04)}
.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed>li strong.list-title{margin:0;color:#2b4a70;font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;line-height:1.22}
.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed>li>div{margin:0;color:#162f4d;font-size:.86rem;line-height:1.3;overflow-wrap:anywhere}
@media (max-width:767.98px){
    .cbDetailsWrapper{margin-top:.45rem;padding:.62rem .56rem .66rem;border-radius:.72rem}
    .cbDetailsWrapper .cbToolBar .cbButton.btn{width:100%;justify-content:center}
    .cbDetailsWrapper .cbDetailsBody{padding:.52rem .48rem .2rem}
    .cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed>li{grid-template-columns:1fr;gap:.28rem;padding:.44rem .48rem}
}
CSS;
    }

    private function getBlinkCss(): string
    {
        return <<<'CSS'
.cb-prepare-blink{
    animation:cb-blink 0.8s steps(1,start) infinite;
}

@keyframes cb-blink{
    50%{opacity:0}
}
CSS;
    }

    private function toUnicodeSlug(string $string): string
    {
        // Keep legacy slug behavior while decoupling from ContentbuilderLegacyHelper.
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
		// Get data from the model
        $this->frontend = Factory::getApplication()->isClient('site');
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

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$db->setQuery("Select articles.`article_id` From #__contentbuilderng_articles As articles, #__content As content Where content.id = articles.article_id And (content.state = 1 Or content.state = 0) And articles.form_id = " . intval($subject->form_id) . " And articles.record_id = " . $db->quote($subject->record_id));
		$article = $db->loadResult();

		$table = new \Joomla\CMS\Table\Content($db);

		// required for pagebreak plugin
		Factory::getApplication()->input->set('view', 'article');

		$isNew = true;
		if ($article > 0) {
			$table->load($article);
			$isNew = false;
		}

		$table->cbrecord = $subject;
		$table->text = $table->cbrecord->template;

            $alias = $table->alias ? $this->toUnicodeSlug((string) $table->alias) : $this->toUnicodeSlug((string) $subject->page_title);
		if (trim(str_replace('-', '', $alias)) == '') {
			$datenow = Factory::getDate();
			$alias = $datenow->format("%Y-%m-%d-%H-%M-%S");
		}

		// we pass the slug with a flag in the end, and see in the end if the slug has been used in the output
		$table->slug = ($article > 0 ? $article : 0) . ':' . $alias . ':contentbuilderng_slug_used';

		$registry = new Registry;
		$registry->loadString($table->attribs ?? '{}', 'json');
		PluginHelper::importPlugin('content');

		// seems to be a joomla bug. if sef urls is enabled, "start" is used for paging in articles, else "limitstart" will be used
		//$limitstart = Factory::getApplication()->input->getInt('limitstart', 0);
		//$start      = Factory::getApplication()->input->getInt('start', 0);

		$limitstart = 0;

		$table->text = "<!-- class=\"system-pagebreak\"  -->\n" . $table->text;

		$dispatcher = Factory::getApplication()->getDispatcher();
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
				$subject->template = str_replace($match, Route::_('index.php?option=com_contentbuilderng&task=details.display&id=' . Factory::getApplication()->input->getInt('id') . '&record_id=' . Factory::getApplication()->input->getCmd('record_id', '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . $sub), $subject->template);
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
				$table->toc = str_replace($match, Route::_('index.php?option=com_contentbuilderng&task=details.display&id=' . Factory::getApplication()->input->getInt('id') . '&record_id=' . Factory::getApplication()->input->getCmd('record_id', '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . $sub), $table->toc);
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
				$themePlugin = 'joomla6';
				PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin);
				$fallbackTheme = true;
			}

		$eventObj = new \Joomla\CMS\Event\GenericEvent('onContentTemplateCss', ['theme' => $themePlugin]);
		$dispatcher->dispatch('onContentTemplateCss', $eventObj);
		$results = $eventObj->getArgument('result') ?: [];
		$this->theme_css = trim(implode('', $results));
        if ($this->theme_css === '' && ($fallbackTheme || $themePlugin === 'joomla6')) {
            $this->theme_css = $this->getFallbackDetailsThemeCss();
        }

        $this->theme_css .= $this->getBlinkCss();

		$eventObj = new \Joomla\CMS\Event\GenericEvent('onContentTemplateJavascript', ['theme' => $themePlugin]);
		$dispatcher->dispatch('onContentTemplateJavascript', $eventObj);
		$results = $eventObj->getArgument('result') ?: [];
		$this->theme_js = implode('', $results);

		$this->toc = $table->toc;
		$this->event = $event;

		$this->show_page_heading = $subject->show_page_heading;
		$this->tpl = $subject->template;
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
		$this->show_id_column = (int) ($subject->show_id_column ?? 0);
		$this->direct_storage_mode = (int) ($subject->direct_storage_mode ?? 0);
		$this->direct_storage_id = (int) ($subject->direct_storage_id ?? 0);
		$this->direct_storage_unpublished = (int) ($subject->direct_storage_unpublished ?? 0);
		$siblings = $this->resolveSiblingRecordIds($subject);
		$this->prev_record_id = (int) ($siblings['previous'] ?? 0);
		$this->next_record_id = (int) ($siblings['next'] ?? 0);

		parent::display($tpl);
	}
}
