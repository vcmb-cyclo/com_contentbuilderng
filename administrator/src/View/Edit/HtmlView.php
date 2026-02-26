<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Edit;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Plugin\PluginHelper;
use CB\Component\Contentbuilderng\Administrator\Model\EditModel;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
	protected $sectioncategories;
	protected $lists;
	protected $row;
	protected $article_settings;
	protected $article_options;
    private bool $frontend;
    private array $breezingFormsRenderCache = [];

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

    private function hasBreezingFormsPlaceholder(string $markup): bool
    {
        return (bool) preg_match('/\{BreezingForms\s*:/i', $markup);
    }

    private function resolveBreezingFormsComponent(): ?array
    {
        static $resolved = false;
        static $component = null;

        if ($resolved) {
            return $component;
        }

        $resolved = true;
        $candidates = [
            'com_breezingforms' => JPATH_ROOT . '/components/com_breezingforms/breezingforms.php',
            'com_breezingforms_ng' => JPATH_ROOT . '/components/com_breezingforms_ng/breezingforms.php',
        ];

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('element'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->where(
                    $db->quoteName('element') . ' IN ('
                    . $db->quote('com_breezingforms') . ','
                    . $db->quote('com_breezingforms_ng') . ')'
                );

            $db->setQuery($query);
            $installed = (array) $db->loadColumn();

            foreach ($installed as $option) {
                if (isset($candidates[$option]) && is_file($candidates[$option])) {
                    $component = ['option' => $option, 'entry' => $candidates[$option]];
                    return $component;
                }
            }
        } catch (\Throwable $e) {
            // Fall back to file-based detection below.
        }

        foreach ($candidates as $option => $entry) {
            if (is_file($entry)) {
                $component = ['option' => $option, 'entry' => $entry];
                return $component;
            }
        }

        return null;
    }

    private function dispatchContentPrepare($dispatcher, \Joomla\CMS\Table\Content $table, Registry $registry, int $page): void
    {
        $dispatcher->dispatch(
            'onContentPrepare',
            new \Joomla\CMS\Event\Content\ContentPrepareEvent('onContentPrepare', [
                'context' => 'com_content.article',
                'subject' => $table,
                'params'  => $registry,
                'page'    => $page,
            ])
        );
    }

    private function renderBreezingFormsShortcodes(string $markup): string
    {
        if (!$this->hasBreezingFormsPlaceholder($markup)) {
            return $markup;
        }

        $rendered = preg_replace_callback(
            '/\{BreezingForms\s*:\s*([^}]+)\}/i',
            function (array $matches): string {
                $formReference = trim((string) ($matches[1] ?? ''));
                if ($formReference === '') {
                    return $matches[0];
                }

                $cacheKey = strtolower($formReference);
                if (array_key_exists($cacheKey, $this->breezingFormsRenderCache)) {
                    return $this->breezingFormsRenderCache[$cacheKey] !== ''
                        ? $this->breezingFormsRenderCache[$cacheKey]
                        : $matches[0];
                }

                $replacement = $this->renderBreezingFormsByComponent($formReference);
                $this->breezingFormsRenderCache[$cacheKey] = $replacement;

                return $replacement !== '' ? $replacement : $matches[0];
            },
            $markup
        );

        return $rendered ?? $markup;
    }

    private function renderBreezingFormsByComponent(string $formReference): string
    {
        $component = $this->resolveBreezingFormsComponent();
        if (!is_array($component) || !isset($component['entry'], $component['option'])) {
            return '';
        }
        $componentEntry = (string) $component['entry'];
        $componentOption = (string) $component['option'];

        $requestSnapshot = $_REQUEST;
        $getSnapshot = $_GET;
        $postSnapshot = $_POST;

        $globalNames = ['ff_applic', 'plg_editable', 'plg_editable_override', 'xModuleId'];
        $globalSnapshot = [];
        foreach ($globalNames as $name) {
            $globalSnapshot[$name . '_set'] = array_key_exists($name, $GLOBALS);
            $globalSnapshot[$name] = $globalSnapshot[$name . '_set'] ? $GLOBALS[$name] : null;
        }

        try {
            $_REQUEST['option'] = $componentOption;
            $_GET['option'] = $componentOption;
            $_REQUEST['ff_applic'] = 'plg_facileforms';
            $_GET['ff_applic'] = 'plg_facileforms';
            $_REQUEST['ff_task'] = 'view';
            $_GET['ff_task'] = 'view';
            $_REQUEST['ff_page'] = 1;
            $_GET['ff_page'] = 1;
            $_REQUEST['ff_target'] = 1;
            $_GET['ff_target'] = 1;
            $_REQUEST['ff_frame'] = 0;
            $_GET['ff_frame'] = 0;
            $_REQUEST['ff_module_id'] = 0;
            $_GET['ff_module_id'] = 0;
            $_REQUEST['ff_contentid'] = (int) $this->id;
            $_GET['ff_contentid'] = (int) $this->id;

            if (ctype_digit($formReference)) {
                $_REQUEST['ff_form'] = (int) $formReference;
                $_GET['ff_form'] = (int) $formReference;
                unset($_REQUEST['ff_name'], $_GET['ff_name']);
            } else {
                $_REQUEST['ff_name'] = $formReference;
                $_GET['ff_name'] = $formReference;
                unset($_REQUEST['ff_form'], $_GET['ff_form']);
            }

            $GLOBALS['ff_applic'] = 'plg_facileforms';
            $GLOBALS['plg_editable'] = 1;
            // Keep override disabled to avoid destructive replacement of user records.
            $GLOBALS['plg_editable_override'] = 0;
            $GLOBALS['xModuleId'] = 0;

            ob_start();
            include $componentEntry;
            $output = ob_get_clean();

            return is_string($output) ? $output : '';
        } catch (\Throwable $e) {
            return '';
        } finally {
            $_REQUEST = $requestSnapshot;
            $_GET = $getSnapshot;
            $_POST = $postSnapshot;

            foreach ($globalNames as $name) {
                if (!empty($globalSnapshot[$name . '_set'])) {
                    $GLOBALS[$name] = $globalSnapshot[$name];
                } else {
                    unset($GLOBALS[$name]);
                }
            }
        }
    }

	function display($tpl = null)
	{
		// Get data from the model
        $this->frontend = Factory::getApplication()->isClient('site');
		//HTMLHelper::_('bootstrap.tooltip');

		// Get data from the model
		/** @var EditModel $model */
		$model = $this->getModel();
		$subject = (is_object($model) && method_exists($model, 'getData')) ? $model->getData() : null;
		if (!is_object($subject)) {
			$subject = (object) [
				'edit_by_type' => false,
				'form_id' => 0,
				'record_id' => 0,
				'page_title' => '',
				'template' => '',
				'theme_plugin' => '',
				'show_page_heading' => false,
				'back_button' => false,
				'latest' => false,
				'limited_options' => false,
				'frontend' => $this->frontend,
				'create_articles' => false,
				'id' => 0,
				'created' => null,
				'created_by' => null,
				'modified' => null,
				'modified_by' => null,
				'save_button_title' => '',
				'apply_button_title' => '',
			];
		}

		$event = new \stdClass();
		$event->afterDisplayTitle = '';
		$event->beforeDisplayContent = '';
		$event->afterDisplayContent = '';

		$table2 = new \stdClass();
		$table2->toc = '';

		if ($subject->edit_by_type) {

				$db = Factory::getContainer()->get(DatabaseInterface::class);
				$db->setQuery("Select articles.`article_id` From #__contentbuilderng_articles As articles, #__content As content Where content.id = articles.article_id And (content.state = 1 Or content.state = 0) And articles.form_id = " . intval($subject->form_id) . " And articles.record_id = " . $db->quote($subject->record_id));
				$article = $db->loadResult();

				$table = new \Joomla\CMS\Table\Content($db);

			// Required for content plugins that expect com_content/article context.
			$input = Factory::getApplication()->input;
			$previousOption = $input->getCmd('option', '');
			$previousView = $input->getCmd('view', '');
			$input->set('option', 'com_content');
			$input->set('view', 'article');

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
			$registry->loadString($table->attribs ?? '');

			// seems to be a joomla bug. if sef urls is enabled, "start" is used for paging in articles, else "limitstart" will be used
			$limitstart = Factory::getApplication()->input->getInt('limitstart', 0);
			$start = Factory::getApplication()->input->getInt('start', 0);
			$page = $limitstart ? $limitstart : $start;
			$hasBfShortcode = $this->hasBreezingFormsPlaceholder((string) ($table->text ?? ''));

			$dispatcher = Factory::getApplication()->getDispatcher();
			try {
				PluginHelper::importPlugin('content');

				$this->dispatchContentPrepare($dispatcher, $table, $registry, $page);

				if ($hasBfShortcode && $this->hasBreezingFormsPlaceholder((string) ($table->text ?? ''))) {
					$table->text = $this->renderBreezingFormsShortcodes((string) ($table->text ?? ''));
				}
			} finally {
				$input->set('option', $previousOption);
				$input->set('view', $previousView);
			}
			$subject->template = $table->text;

			$eventResult = $dispatcher->dispatch(
				'onContentAfterTitle',
				new \Joomla\CMS\Event\Content\AfterTitleEvent('onContentAfterTitle', [
					'context' => 'com_content.article',
					'subject' => $table,
					'params'  => $registry,
					'page'    => $limitstart ? $limitstart : $start,
				])
			);
			$results = $eventResult->getArgument('result') ?: [];
			$event->afterDisplayTitle = trim(implode("\n", $results));

			$eventResult = $dispatcher->dispatch(
				'onContentBeforeDisplay',
				new \Joomla\CMS\Event\Content\BeforeDisplayEvent('onContentBeforeDisplay', [
					'context' => 'com_content.article',
					'subject' => $table,
					'params'  => $registry,
					'page'    => $limitstart ? $limitstart : $start,
				])
			);
			$results = $eventResult->getArgument('result') ?: [];
			$event->beforeDisplayContent = trim(implode("\n", $results));

			$eventResult = $dispatcher->dispatch(
				'onContentAfterDisplay',
				new \Joomla\CMS\Event\Content\AfterDisplayEvent('onContentAfterDisplay', [
					'context' => 'com_content.article',
					'subject' => $table,
					'params'  => $registry,
					'page'    => $limitstart ? $limitstart : $start,
				])
			);
			$results = $eventResult->getArgument('result') ?: [];

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
					$subject->template = str_replace($match, Route::_('index.php?option=com_contentbuilderng&view=details&id=' . Factory::getApplication()->input->getInt('id') . '&record_id=' . Factory::getApplication()->input->getCmd('record_id', '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . $sub), $subject->template);
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
					$table->toc = str_replace($match, Route::_('index.php?option=com_contentbuilderng&view=details&id=' . Factory::getApplication()->input->getInt('id') . '&record_id=' . Factory::getApplication()->input->getCmd('record_id', '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . $sub), $table->toc);
				}
			}

			if (!isset($table->toc)) {
				$table2->toc = '';
			}

			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$subject->template = preg_replace($pattern, '', $subject->template);
		}

		if (!$this->frontend) {
			ToolbarHelper::title($subject->page_title, 'logo_left');
		}

			$themePlugin = (string) ($subject->theme_plugin ?? '');
			if ($themePlugin === '' || !PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin)) {
				PluginHelper::importPlugin('contentbuilderng_themes', 'joomla6');
			}
		$dispatcher = Factory::getApplication()->getDispatcher();
        $eventResult = $dispatcher->dispatch('onEditableTemplateCss', new \Joomla\CMS\Event\GenericEvent('onEditableTemplateCss', ['theme' => $themePlugin]));
        $results = $eventResult->getArgument('result') ?: [];
		$theme_css = implode('', $results);
		$this->theme_css = $theme_css;

			$dispatcher = Factory::getApplication()->getDispatcher();
        $eventResult = $dispatcher->dispatch('onEditableTemplateJavascript', new \Joomla\CMS\Event\GenericEvent('onEditableTemplateJavascript', ['theme' => $themePlugin]));
        $results = $eventResult->getArgument('result') ?: [];
		$theme_js = implode('', $results);
		$this->theme_js = $theme_js;

		$this->toc = $table2->toc;
		$this->event = $event;
		$this->show_page_heading = $subject->show_page_heading;
		$this->back_button = $subject->back_button;
		$this->latest = $subject->latest;

		$this->limited_options = $subject->limited_options;
		$this->edit_by_type = $subject->edit_by_type;
		$this->frontend = $subject->frontend;

		if (isset($subject->sectioncategories))
			$this->sectioncategories = $subject->sectioncategories;

		if (isset($subject->lists))
			$this->lists = $subject->lists; // special for 1.5
		if (isset($subject->row))
			$this->row = $subject->row; // special for 1.5
		if (isset($subject->article_settings))
			$this->article_settings = $subject->article_settings;
		if (isset($subject->article_options))
			$this->article_options = $subject->article_options;
		$this->create_articles = $subject->create_articles;
		$this->record_id = $subject->record_id;
		$this->id = $subject->id;
		$this->tpl = $subject->template;
		$this->page_title = $subject->page_title;
		$this->created = $subject->created;
		$this->created_by = $subject->created_by;
		$this->modified = $subject->modified;
		$this->modified_by = $subject->modified_by;

		$this->save_button_title = $subject->save_button_title;
		$this->apply_button_title = $subject->apply_button_title;

		parent::display($tpl);
	}
}
