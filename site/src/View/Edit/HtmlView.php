<?php

/**
 * ContentBuilder NG Edit view.
 *
 * Edit view of the site interface
 *
 * @package     ContentBuilder NG
 * @subpackage  Site.View
 * @author      Xavier DANO
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 compatibility rewrite.
 */


namespace CB\Component\Contentbuilderng\Site\View\Edit;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use CB\Component\Contentbuilderng\Site\Model\EditModel;

class HtmlView extends BaseHtmlView
{
    public $theme_css = '';
    public $theme_js = '';
    public $show_page_heading = false;
    public $form_name = '';
    public $page_title = '';
    public $event;
    public $record_id = 0;
    public $edit_by_type = false;
    public $latest = false;
    public $back_button = false;
    public $created = null;
    public $created_by = null;
    public $modified = null;
    public $modified_by = null;
    public $create_articles = false;
    public $apply_button_title = '';
    public $save_button_title = '';
    public $id = 0;
    public $article_options = null;
    public $article_settings = null;
    public $limited_options = false;
    public $show_id_column = 0;
    public $toc = null;
    public $tpl = null;
    public $prev_record_id = 0;
    public $next_record_id = 0;
    public int $prev_record_start = 0;
    public int $next_record_start = 0;

    protected $state;
    protected $item;
    protected $form;
    private array $breezingFormsRenderCache = [];

    private function resolveSiblingRecordIdsByRecordId(object $subject, int $currentRecordId): array
    {
        $currentList = (array) Factory::getApplication()->input->get('list', [], 'array');
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
            return ['previous' => 0, 'next' => 0, 'previous_start' => $currentListStart, 'next_start' => $currentListStart];
        }

        return ['previous' => $previous, 'next' => $next];
    }

    private function getListPaginationStateKeys(int $formId): array
    {
        $app = Factory::getApplication();
        $option = 'com_contentbuilderng';
        $layout = (string) $app->input->getCmd('layout', 'default');

        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $app->input->getInt('Itemid', 0);
        $prefix = $option . '.liststate.' . $formId . '.' . $layout . '.' . $itemId;

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

        $originalList = (array) $app->input->get('list', [], 'array');
        $listLimit = array_key_exists('limit', $originalList) ? (int) $originalList['limit'] : 0;
        if ($listLimit <= 0) {
            $listLimit = (int) $app->get('list_limit', 20);
        }
        if ($listLimit <= 0) {
            $listLimit = 20;
        }

        if ($currentRecordId < 1) {
            return $fallback;
        }

        $formId = (int) $app->input->getInt('id', 0);
        $paginationKeys = $this->getListPaginationStateKeys($formId);
        $limitStateBackup = $app->getUserState($paginationKeys['limit'], null);
        $startStateBackup = $app->getUserState($paginationKeys['start'], null);

        try {
            // Reuse list ordering/filtering so Previous/Next matches the active list context.
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
                'previous_start' => $position > 0 ? (int) (floor(($position - 1) / $listLimit) * $listLimit) : 0,
                'next_start' => ($position + 1) < count($recordIds) ? (int) (floor(($position + 1) / $listLimit) * $listLimit) : 0,
            ];
        } catch (\Throwable $e) {
            return $fallback;
        } finally {
            $app->input->set('list', $originalList);
            $app->setUserState($paginationKeys['limit'], $limitStateBackup);
            $app->setUserState($paginationKeys['start'], $startStateBackup);
        }
    }

    private function toUnicodeSlug(string $string): string
    {
        $str = preg_replace('/\xE3\x80\x80/', ' ', $string) ?? $string;
        $str = str_replace('-', ' ', $str);
        $str = preg_replace('#[:\#\*"@+=;!&\.%()\]\/\'\\\\|\[]#', ' ', $str) ?? $str;
        $str = str_replace('?', '', $str);
        $str = trim(strtolower($str));
        $str = preg_replace('#\x20+#', '-', $str) ?? $str;

        return $str;
    }

    private function rewriteSlugLinks(string $markup): string
    {
        if (strpos($markup, 'contentbuilderng_slug_used') === false) {
            return $markup;
        }

        $matches = array(array(), array());
        preg_match_all('/\"([^\"]*contentbuilderng_slug_used[^\"]*)\"/i', $markup, $matches);

        foreach ($matches[1] as $match) {
            $sub = '';
            $parameters = explode('?', $match, 2);
            if (count($parameters) === 2) {
                $parameters[1] = str_replace('&amp;', '&', $parameters[1]);
                foreach (explode('&', $parameters[1]) as $par) {
                    $keyval = explode('=', $par, 2);
                    $key = $keyval[0] ?? '';
                    $value = $keyval[1] ?? '';
                    if (
                        $key !== ''
                        && $key !== 'option'
                        && $key !== 'id'
                        && $key !== 'record_id'
                        && $key !== 'view'
                        && $key !== 'catid'
                        && $key !== 'Itemid'
                        && $key !== 'lang'
                    ) {
                        $sub .= '&' . $key . '=' . $value;
                    }
                }
            }

            $replacement = Route::_(
                'index.php?option=com_contentbuilderng&view=details&id='
                . Factory::getApplication()->input->getInt('id')
                . '&record_id=' . Factory::getApplication()->input->getCmd('record_id', '')
                . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0)
                . $sub
            );
            $markup = str_replace($match, $replacement, $markup);
        }

        return $markup;
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
                'params' => $registry,
                'page' => $page,
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

    private function applyEditByTypeRendering(): void
    {
        if (!is_object($this->item) || !$this->edit_by_type) {
            return;
        }

        $template = (string) ($this->item->template ?? $this->tpl ?? '');
        if ($template === '') {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery(
            'Select articles.`article_id`'
            . ' From #__contentbuilderng_articles As articles, #__content As content'
            . ' Where content.id = articles.article_id'
            . ' And (content.state = 1 Or content.state = 0)'
            . ' And articles.form_id = ' . (int) $this->id
            . ' And articles.record_id = ' . $db->quote((string) $this->record_id)
        );
        $articleId = (int) $db->loadResult();

        $table = new \Joomla\CMS\Table\Content($db);
        if ($articleId > 0) {
            $table->load($articleId);
        }

        $table->cbrecord = $this->item;
        $table->text = $template;

        $alias = $table->alias
            ? $this->toUnicodeSlug((string) $table->alias)
            : $this->toUnicodeSlug((string) ($this->item->page_title ?? $this->page_title));
        if (trim(str_replace('-', '', $alias)) === '') {
            $alias = Factory::getDate()->format('%Y-%m-%d-%H-%M-%S');
        }
        $table->slug = ($articleId > 0 ? $articleId : 0) . ':' . $alias . ':contentbuilderng_slug_used';

        $registry = new Registry();
        $registry->loadString((string) ($table->attribs ?? ''));

        $limitstart = Factory::getApplication()->input->getInt('limitstart', 0);
        $start = Factory::getApplication()->input->getInt('start', 0);
        $page = $limitstart ? $limitstart : $start;
        $dispatcher = Factory::getApplication()->getDispatcher();
        $hasBfShortcode = $this->hasBreezingFormsPlaceholder($template);

        PluginHelper::importPlugin('content');

        $this->dispatchContentPrepare($dispatcher, $table, $registry, $page);

        if ($hasBfShortcode && $this->hasBreezingFormsPlaceholder((string) ($table->text ?? ''))) {
            $table->text = $this->renderBreezingFormsShortcodes((string) ($table->text ?? ''));
        }

        $eventResult = $dispatcher->dispatch(
            'onContentAfterTitle',
            new \Joomla\CMS\Event\Content\AfterTitleEvent('onContentAfterTitle', [
                'context' => 'com_content.article',
                'subject' => $table,
                'params' => $registry,
                'page' => $page,
            ])
        );
        $results = $eventResult->getArgument('result') ?: [];
        $this->event->afterDisplayTitle = trim(implode("\n", $results));

        $eventResult = $dispatcher->dispatch(
            'onContentBeforeDisplay',
            new \Joomla\CMS\Event\Content\BeforeDisplayEvent('onContentBeforeDisplay', [
                'context' => 'com_content.article',
                'subject' => $table,
                'params' => $registry,
                'page' => $page,
            ])
        );
        $results = $eventResult->getArgument('result') ?: [];
        $this->event->beforeDisplayContent = trim(implode("\n", $results));

        $eventResult = $dispatcher->dispatch(
            'onContentAfterDisplay',
            new \Joomla\CMS\Event\Content\AfterDisplayEvent('onContentAfterDisplay', [
                'context' => 'com_content.article',
                'subject' => $table,
                'params' => $registry,
                'page' => $page,
            ])
        );
        $results = $eventResult->getArgument('result') ?: [];
        $this->event->afterDisplayContent = trim(implode("\n", $results));

        $processedTemplate = (string) ($table->text ?? '');
        $processedTemplate = $this->rewriteSlugLinks($processedTemplate);
        $processedTemplate = preg_replace('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', '', $processedTemplate) ?? $processedTemplate;

        $this->item->template = $processedTemplate;
        $this->tpl = $processedTemplate;

        if (isset($table->toc)) {
            $this->toc = $this->rewriteSlugLinks((string) $table->toc);
        }
    }

    private function getFallbackEditThemeCss(): string
    {
        return <<<'CSS'
.cbEditableWrapper{
    max-width:1120px;
    margin:.55rem auto 1rem;
    padding:.7rem .78rem .72rem;
    border:1px solid rgba(36,61,86,.12);
    border-radius:.85rem;
    background:radial-gradient(circle at top right,rgba(13,110,253,.08),transparent 38%),linear-gradient(180deg,#fff 0,#f8fbff 100%);
    box-shadow:0 .55rem 1.2rem rgba(16,32,56,.08)
}
.cbEditableWrapper .cbToolBar{padding:.38rem .46rem;border:1px solid rgba(45,73,104,.14);border-radius:.72rem;background:rgba(255,255,255,.85)}
.cbEditableWrapper .cbToolBar.mb-5{margin-bottom:.6rem!important}
.cbEditableWrapper .cbToolBar .cbButton.btn{border-radius:999px;font-weight:600;font-size:.85rem;padding:.34rem .78rem}
.cbEditableWrapper fieldset.border.rounded.p-3.mb-3{padding:.52rem!important;margin-bottom:.36rem!important;border-radius:.62rem!important}
.cbEditableWrapper .mb-3{margin-bottom:.34rem!important}
.cbEditableWrapper .form-label,.cbEditableWrapper label{font-size:.82rem;margin-bottom:.14rem}
.cbEditableWrapper :is(input[type="text"],input[type="email"],input[type="number"],input[type="date"],input[type="datetime-local"],input[type="time"],input[type="url"],input[type="password"],textarea,select){min-height:1.82rem;padding:.24rem .42rem}
.cbEditableWrapper .form-select.form-select-sm,.cbEditableWrapper .form-select-sm,.cbEditableWrapper .form-control.form-control-sm{min-height:1.72rem;font-size:.84rem;padding-top:.16rem;padding-bottom:.16rem}
.cbEditableWrapper select,.cbEditableWrapper .form-select,.cbEditableWrapper .form-select-sm{line-height:1.35;vertical-align:middle}
.cbEditableWrapper select:not([multiple]):not([size]),.cbEditableWrapper .form-select:not([multiple]):not([size]),.cbEditableWrapper .form-select-sm:not([multiple]):not([size]){min-height:1.94rem;padding-top:.2rem;padding-bottom:.2rem;padding-right:1.9rem}
.cbEditableWrapper .form-select:not([multiple]):not([size]),.cbEditableWrapper .form-select-sm:not([multiple]):not([size]){background-position:right .62rem center;background-repeat:no-repeat}
@media (max-width:767.98px){
    .cbEditableWrapper{margin-top:.4rem;padding:.58rem .5rem .6rem;border-radius:.68rem}
    .cbEditableWrapper .cbToolBar{padding:.32rem}
    .cbEditableWrapper .cbToolBar .cbButton.btn{width:100%;justify-content:center}
}
CSS;
    }

    public function display($tpl = null): void
    {
        /** @var EditModel|null $model */
        $model = $this->getModel();
        if ($model) {
            $this->state = method_exists($model, 'getState') ? $model->getState() : null;
            $this->item  = method_exists($model, 'getItem') ? $model->getItem() : null;
            $this->form  = method_exists($model, 'getForm') ? $model->getForm() : null;
            $this->event = (object) [
                'afterDisplayTitle' => '',
                'beforeDisplayContent' => '',
                'afterDisplayContent' => '',
            ];

            if (is_object($this->item)) {
                $props = [
                    'theme_css', 'theme_js', 'show_page_heading', 'page_title', 'record_id',
                    'edit_by_type', 'latest', 'back_button', 'created', 'created_by',
                    'modified', 'modified_by', 'create_articles', 'apply_button_title',
                    'save_button_title', 'id', 'article_options', 'article_settings',
                    'limited_options', 'show_id_column', 'toc', 'tpl',
                ];

                foreach ($props as $prop) {
                    if (property_exists($this->item, $prop)) {
                        $this->$prop = $this->item->$prop;
                    }
                }

                // Model exposes the rendered markup as $item->template; accept null/empty here.
                if (($this->tpl === null || $this->tpl === '') && property_exists($this->item, 'template')) {
                    $this->tpl = $this->item->template;
                }

                if ($this->form_name === '' && property_exists($this->item, 'name')) {
                    $this->form_name = (string) $this->item->name;
                }

                if ($this->id === 0 && property_exists($this->item, 'form_id')) {
                    $this->id = (int) $this->item->form_id;
                }

                if ($this->record_id === 0 && property_exists($this->item, 'record_id')) {
                    $this->record_id = (int) $this->item->record_id;
                }

                $siblings = $this->resolveSiblingRecordIds($this->item);
                $this->prev_record_id = (int) ($siblings['previous'] ?? 0);
                $this->next_record_id = (int) ($siblings['next'] ?? 0);
                $this->prev_record_start = (int) ($siblings['previous_start'] ?? 0);
                $this->next_record_start = (int) ($siblings['next_start'] ?? 0);

                $this->applyEditByTypeRendering();

                if ($this->theme_css === '' && $this->theme_js === '' && property_exists($this->item, 'theme_plugin')) {
                    $themePlugin = (string) ($this->item->theme_plugin ?? '');
                    $fallbackTheme = false;
                    if ($themePlugin === '' || !PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin)) {
                        $themePlugin = 'joomla6';
                        PluginHelper::importPlugin('contentbuilderng_themes', $themePlugin);
                        $fallbackTheme = true;
                    }
                    $dispatcher = Factory::getApplication()->getDispatcher();

                    $eventObj = new \Joomla\CMS\Event\GenericEvent('onEditableTemplateCss', ['theme' => $themePlugin]);
                    $dispatcher->dispatch('onEditableTemplateCss', $eventObj);
                    $results = $eventObj->getArgument('result') ?: [];
                    $this->theme_css = trim(implode('', $results));
                    if ($this->theme_css === '' && ($fallbackTheme || $themePlugin === 'joomla6')) {
                        $this->theme_css = $this->getFallbackEditThemeCss();
                    }

                    $eventObj = new \Joomla\CMS\Event\GenericEvent('onEditableTemplateJavascript', ['theme' => $themePlugin]);
                    $dispatcher->dispatch('onEditableTemplateJavascript', $eventObj);
                    $results = $eventObj->getArgument('result') ?: [];
                    $this->theme_js = implode('', $results);
                }
            }
        } else {
            $this->state = null;
            $this->item  = null;
            $this->form  = null;
            $this->event = (object) [
                'afterDisplayTitle' => '',
                'beforeDisplayContent' => '',
                'afterDisplayContent' => '',
            ];
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_CONTENTBUILDERNG') .' : Edit model not found for this request.',
                'warning'
            );
        }

        parent::display($tpl);
    }
}
