<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Service\ArticleService;
use CB\Component\Contentbuilderng\Administrator\Service\RuntimeUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\ListSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\PathService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Service\TemplateRenderService;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\PublishedRecordVisibilityHelper;

class EditModel extends BaseDatabaseModel
{
    private AdministratorApplication|SiteApplication $app;
    private readonly RuntimeUtilityService $runtimeUtilityService;
    private readonly ListSupportService $listSupportService;
    private readonly TemplateRenderService $templateRenderService;

    private $_record_id = 0;

    private $frontend = false;

    private $_menu_item = false;

    private $_show_back_button = true;

    private $_show_page_heading = true;

    private $_menu_filter = array();

    private $_menu_filter_order = array();

    private $_latest = false;

    private function getMenuToggle(string $key, int $default = 0): int
    {
        return MenuParamHelper::resolveInputOrMenuToggle($this->app, $key, $default);
    }

    private $_page_title = '';

    private $_page_heading = '';

    private function getEffectiveOwnershipUserId(bool $useOwnOnly): int
    {
        if (!$useOwnOnly) {
            return -1;
        }

        if ($this->app->input->getBool('cb_preview_ok', false)) {
            $previewActorId = (int) $this->app->input->getInt('cb_preview_actor_id', 0);

            if ($previewActorId > 0) {
                return $previewActorId;
            }
        }

        return (int) ($this->app->getIdentity()->id ?? 0);
    }

    private function cleanComponentCaches(): void
    {
        $cacheFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
        $cacheBase = (string) $this->app->get('cache_path', JPATH_SITE . '/cache');

        foreach (array('com_content', 'com_contentbuilderng') as $group) {
            $cacheFactory->createCacheController(
                'callback',
                array(
                    'defaultgroup' => $group,
                    'cachebase' => $cacheBase,
                )
            )->clean();
        }
    }

    public function getItem($pk = null)
    {
        $data = $this->getData();

        if (is_array($data)) {
            return $data[0] ?? null;
        }

        return $data ?: null;
    }

    public function getForm($data = [], $loadData = true)
    {
        $item = $this->getItem();

        return is_object($item) && property_exists($item, 'form') ? $item->form : null;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        return preg_replace('#/+#', '/', $path) ?? $path;
    }

    private function toSafePathToken($value): string
    {
        if (is_array($value)) {
            $value = array_values(array_filter($value, static fn($v) => $v !== null && $v !== '' && $v !== 'cbGroupMark'));
            $value = implode('/', array_map(static fn($v) => (string) $v, $value));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '_empty_';
        }

        $value = preg_replace('#[^A-Za-z0-9._/\-]#', '_', $value) ?? $value;
        return $value === '' ? '_empty_' : $value;
    }

    private function isSafeStoragePath(string $path): bool
    {
        $siteRoot = realpath(JPATH_SITE);
        if ($siteRoot === false) {
            return false;
        }

        $siteRoot = rtrim($this->normalizePath($siteRoot), '/');
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }

        $realPath = $this->normalizePath($realPath);
        return strncasecmp($realPath, $siteRoot . '/', strlen($siteRoot) + 1) === 0 || strcasecmp($realPath, $siteRoot) === 0;
    }

    function createPathByTokens($path, array $names)
    {
        $path = (string) $path;
        if (trim($path) === '') {
            return '';
        }

        $path = str_replace('|', '/', $path);
        $path = str_replace(array('{CBSite}', '{cbsite}'), JPATH_SITE, $path);

        foreach ($names as $id => $name) {
            $value = $this->app->input->post->get('cb_' . $id, '', 'raw');
            $value = $this->toSafePathToken($value);
            $path = str_replace('{' . strtolower($name) . ':value}', $value, $path);
        }

        $path = str_replace('{userid}', (string) (int) ($this->app->getIdentity()->id ?? 0), $path);
        $path = str_replace('{username}', $this->toSafePathToken((string) ($this->app->getIdentity()->username ?? 'anonymous') . '_' . (int) ($this->app->getIdentity()->id ?? 0)), $path);
        $path = str_replace('{name}', $this->toSafePathToken((string) ($this->app->getIdentity()->name ?? 'Anonymous') . '_' . (int) ($this->app->getIdentity()->id ?? 0)), $path);

        $_now = Factory::getDate();

        $path = str_replace('{date}', $_now->format('Y-m-d'), $path);
        $path = str_replace('{time}', $_now->format('His'), $path);
        $path = str_replace('{datetime}', $_now->format('Y-m-d_His'), $path);

        $endpath = (new PathService())->makeSafeFolder($path);
        $endpath = $this->normalizePath($endpath);

        $isAbsolute = strpos($endpath, '/') === 0 || (bool) preg_match('#^[A-Za-z]:/#', $endpath);
        if (!$isAbsolute) {
            $endpath = rtrim($this->normalizePath(JPATH_SITE), '/') . '/' . ltrim($endpath, '/');
        }

        if (!is_dir($endpath) && !Folder::create($endpath)) {
            return '';
        }

        if (!$this->isSafeStoragePath($endpath) || !ContentbuilderngHelper::is_internal_path($endpath)) {
            return '';
        }

        $real = realpath($endpath);
        return $real === false ? '' : $this->normalizePath($real);
    }

    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory);

        /** @var AdministratorApplication|SiteApplication $app */
        $app = Factory::getApplication();
        $this->app = $app;
        $this->runtimeUtilityService = new RuntimeUtilityService();
        $this->listSupportService = new ListSupportService();
        $this->templateRenderService = new TemplateRenderService();
        $this->_db = Factory::getContainer()->get(DatabaseInterface::class);
        $option = 'com_contentbuilderng';

        $this->app->input->set('cb_category_id', null);

        $this->frontend = $this->app->isClient('site');

        if ($this->frontend && $this->app->input->getInt('Itemid', 0)) {
            $this->_menu_item = true;

            // try menu item
            $menu = $this->app->getMenu();
            $item = $menu->getActive();

            if (is_object($item)) {
                $params = $item->getParams();
                $this->app->input->set('cb_category_id', (int) MenuParamHelper::getMenuParam($params, 'cb_category_id', 0));

                if ($this->app->input->getString('cb_controller', '') == 'edit') {
                    $this->_show_back_button = MenuParamHelper::getResolvedMenuToggle(
                        $params,
                        'cb_show_details_back_button',
                        1,
                        'show_back_button'
                    ) === 1;
                }

                if (MenuParamHelper::getMenuParam($params, 'cb_latest', null) !== null) {
                    $this->_latest = MenuParamHelper::getMenuParam($params, 'cb_latest', null);
                }

                if ($item->getParams()->get('show_page_heading', null) !== null) {
                    $this->_show_page_heading = MenuParamHelper::resolvePageHeadingToggle(
                        $item->getParams()->get('show_page_heading', null),
                        $this->_show_page_heading ? 1 : 0
                    );
                }

                if ($item->getParams()->get('page_title', null) !== null) {
                    $this->_page_title = $item->getParams()->get('page_title', null);
                }

                if ($item->getParams()->get('page_heading', null) !== null) {
                    $this->_page_heading = $item->getParams()->get('page_heading', null);
                }
            }
        }

        $menu_filter = $this->app->input->get('cb_list_filterhidden', null, 'raw');
        if (($menu_filter === null || $menu_filter === '') && $this->app->isClient('site')) {
            $activeMenu = $this->app->getMenu()->getActive();
            if ($activeMenu) {
                $menu_filter = MenuParamHelper::getMenuParam($activeMenu->getParams(), 'cb_list_filterhidden', null);
            }
        }

        if ($menu_filter !== null) {
            $lines = explode("\n", $menu_filter);
            foreach ($lines as $line) {
                $keyval = explode("\t", $line);
                if (count($keyval) == 2) {
                    $keyval[1] = str_replace(array("\n", "\r"), "", $keyval[1]);
                    $keyval[1] = $this->runtimeUtilityService->sanitizeHiddenFilterValue($keyval[1]);
                    if ($keyval[1] != '') {
                        $this->_menu_filter[$keyval[0]] = explode('|', $keyval[1]);
                    }
                }
            }
        }

        $menu_filter_order = $this->app->input->get('cb_list_orderhidden', null, 'raw');
        if (($menu_filter_order === null || $menu_filter_order === '') && $this->app->isClient('site')) {
            $activeMenu = $this->app->getMenu()->getActive();
            if ($activeMenu) {
                $menu_filter_order = MenuParamHelper::getMenuParam($activeMenu->getParams(), 'cb_list_orderhidden', null);
            }
        }

        if ($menu_filter_order !== null) {
            $lines = explode("\n", $menu_filter_order);
            foreach ($lines as $line) {
                $keyval = explode("\t", $line);
                if (count($keyval) == 2) {
                    $keyval[1] = str_replace(array("\n", "\r"), "", $keyval[1]);
                    if ($keyval[1] != '') {
                        $this->_menu_filter_order[$keyval[0]] = intval($keyval[1]);
                    }
                }
            }
        }

        @natsort($this->_menu_filter_order);

        $this->setIds($this->app->input->getInt('id', 0), $this->app->input->getCmd('record_id', 0));

        if (!$this->frontend) {
            $this->app->getLanguage()->load('com_content');
        } else {
            $this->app->getLanguage()->load('com_content', JPATH_SITE . '/administrator');
            $this->app->getLanguage()->load('joomla', JPATH_SITE . '/administrator');
        }
    }

    /*
     * MAIN DETAILS AREA
     */

    /**
     *
     * @param int $id
     */
    function setIds($id, $record_id)
    {
        // Set id and wipe data
        $this->_id = $id;
        $this->_record_id = $record_id;
        $this->_data = null;
    }

    private function _buildQuery()
    {
        $isAdminPreview = $this->app->input->getBool('cb_preview_ok', false);
        $query = 'Select * From #__contentbuilderng_forms Where id = ' . intval($this->_id);

        if (!$isAdminPreview) {
            $query .= ' And published = 1';
        }

        return $query;
    }

    /**
     * Gets the currencies
     * @return array List of currencies
     */
    function getData()
    {
        // Lets load the data if it doesn't already exist
        if (empty($this->_data)) {
            $query = $this->_buildQuery();
            $this->_data = $this->_getList($query, 0, 1);

            if (!count($this->_data)) {
                throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
            }

            foreach ($this->_data as $data) {
                $isAdminPreview = $this->app->input->getBool('cb_preview_ok', false);

                if (!$isAdminPreview) {
                    if (!$this->frontend) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
                    }
                }

                $data->show_page_heading = $this->_show_page_heading;
                $data->limited_options = $this->frontend ? $data->limited_article_options_fe : $data->limited_article_options;
                $data->form_id = $this->_id;
                $data->record_id = $this->_record_id;
                if ($data->type && $data->reference_id) {

                    // article options
                    $db = $this->getDatabase();
                    $query = $db->getQuery(true)
                        ->select(['content.id', 'content.modified_by', 'content.version', 'content.hits', 'content.catid'])
                        ->from($db->quoteName('#__contentbuilderng_articles', 'articles'))
                        ->innerJoin($db->quoteName('#__content', 'content') . ' ON content.id = articles.article_id')
                        ->where('(content.state = 1 OR content.state = 0)')
                        ->where($db->quoteName('articles.form_id') . ' = ' . (int)$this->_id)
                        ->where($db->quoteName('articles.record_id') . ' = ' . $db->quote($this->_record_id));
                    $db->setQuery($query);
                    $article = $db->loadAssoc();

                    if ($data->create_articles) {
                        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/forms');
                        Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_content/models/fields');
                        $formGetInstance = 'getInstance';
                        $form = Form::{$formGetInstance}('com_content.article', 'article', array('control' => 'Form', 'load_data' => true));

                        if (is_array($article)) {

                            $table = new \Joomla\CMS\Table\Content($this->getDatabase());
                            $loaded = $table->load($article['id']);
                            if ($loaded) {
                                // Convert to stdClass before adding other data.
                                $properties = [];
                                foreach (array_keys((array) $table->getFields()) as $fieldName) {
                                    $properties[$fieldName] = $table->$fieldName ?? null;
                                }
                                $item = ArrayHelper::toObject($properties, \stdClass::class);

                                if (property_exists($item, 'params')) {
                                    $registry = new Registry;
                                    $registry->loadString($item->params);
                                    $item->params = $registry->toArray();
                                }

                                // Convert the params field to an array.
                                $registry = new Registry;
                                $registry->loadString($item->attribs);
                                $item->attribs = $registry->toArray();

                                // Convert the params field to an array.
                                $registry = new Registry;
                                $registry->loadString($item->metadata);
                                $item->metadata = $registry->toArray();
                                $item->articletext = trim($item->fulltext) != '' ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;

                                // Import the approriate plugin group.
                                PluginHelper::importPlugin('content');

                                // Trigger the form preparation event.
                                $dispatcher = $this->app->getDispatcher();
                                $eventResult = $dispatcher->dispatch(
                                    'onContentPrepareForm',
                                    new PrepareFormEvent('onContentPrepareForm', [
                                        'subject' => $form,
                                        'data'    => $item,
                                    ])
                                );
                                $results = $eventResult->getArgument('result') ?: [];

                                // Keep event results for compatibility; exceptions are handled upstream.

                                $form->bind($item);

                                $data->sectioncategories = array();
                                $data->row = $item;
                                $data->lists = array();
                            } else {
                                $data->sectioncategories = array();
                                $data->row = new \stdClass();
                                $data->row->title = '';
                                $data->row->alias = ''; // special for 1.5
                                $data->lists = array('state' => '', 'frontpage' => '', 'sectionid' => '', 'catid' => ''); // special for 1.5
                            }

                            $data->article_settings = new \stdClass();
                            $data->article_settings->modified_by = $article['modified_by'];
                            $data->article_settings->version = $article['version'];
                            $data->article_settings->hits = $article['hits'];
                            $data->article_settings->catid = $article['catid'];
                        } else {
                            $data->article_settings = new \stdClass();
                            $data->article_settings->modified_by = 0;
                            $data->article_settings->version = 0;
                            $data->article_settings->hits = 0;
                            $data->article_settings->catid = 0;
                        }

                        $data->article_options = $form;
                    }

                    $this->_show_back_button = MenuParamHelper::resolveInputOrMenuToggle(
                        $this->app,
                        'cb_show_details_back_button',
                        (int) ($data->show_back_button ?? 1),
                        'show_back_button'
                    ) === 1;
                    $data->back_button = $this->app->input->getBool('latest', 0) && !$this->app->input->getCmd('record_id', 0) ? false : $this->_show_back_button;
                    $data->latest = $this->_latest;
                    $data->frontend = $this->frontend;
                    $data->form = FormSourceFactory::getForm($data->type, $data->reference_id);
                    if (!$data->form->exists) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
                    }
                    $prefixInTitle = $this->getMenuToggle('cb_prefix_in_title', (int) ($data->cb_prefix_in_title ?? 0));
                    $baseTitle = '';
                    if ($this->_show_page_heading && $this->_page_title !== '') {
                        $baseTitle = (string) $this->_page_title;
                    } elseif ($this->_menu_item) {
                        $baseTitle = (string) $this->app->getDocument()->getTitle();
                    } else {
                        $baseTitle = (string) $data->form->getPageTitle();
                    }

                    $viewTitle = $data->use_view_name_as_title ? (string) $data->name : $baseTitle;
                    $data->page_title = $viewTitle;
                    if ($prefixInTitle === 1 && $data->use_view_name_as_title && $baseTitle !== '') {
                        $data->page_title = trim($viewTitle . ' - ' . $baseTitle);
                    }

                    $data->labels = $data->form->getElementLabels();
                    $ids = array();
                    foreach ($data->labels as $reference_id => $label) {
                        $ids[] = $this->getDatabase()->quote($reference_id);
                    }

                    if (count($ids)) {
                        $db = $this->getDatabase();
                        $query = $db->getQuery(true)
                            ->select([$db->quoteName('label'), $db->quoteName('reference_id')])
                            ->from($db->quoteName('#__contentbuilderng_elements'))
                            ->where($db->quoteName('form_id') . ' = ' . (int)$this->_id)
                            ->where($db->quoteName('reference_id') . ' IN (' . implode(',', $ids) . ')')
                            ->where($db->quoteName('published') . ' = 1')
                            ->order($db->quoteName('ordering'));
                        $db->setQuery($query);
                        $rows = $db->loadAssocList();
                        $ids = array();
                        foreach ($rows as $row) {
                            $ids[] = $row['reference_id'];
                        }
                    }

                    if (!$this->isRecordAllowedByMenuFilter($data, $ids)) {
                        throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
                    }

                    $isAdminPreview = $this->app->input->getBool('cb_preview_ok', false);
                    $publishedOnly = $this->shouldRestrictToPublishedOnly($data, $isAdminPreview);

                    $data->items = $data->form->getRecord(
                        $this->_record_id,
                        $publishedOnly,
                        $this->frontend ? $this->getEffectiveOwnershipUserId((bool) $data->own_only_fe) : $this->getEffectiveOwnershipUserId((bool) $data->own_only),
                        $this->frontend ? $data->show_all_languages_fe : true
                    );

                    if (count($data->items)) {

                        $user = null;

                        if ($data->act_as_registration) {
                            $meta = $data->form->getRecordMetadata($this->_record_id);
                            $db = $this->getDatabase();
                            $query = $db->getQuery(true)
                                ->select('*')
                                ->from($db->quoteName('#__users'))
                                ->where($db->quoteName('id') . ' = ' . (int)$meta->created_id);
                            $db->setQuery($query);
                            $user = $db->loadObject();
                        }

                        $label = '';
                        foreach ($data->items as $rec) {
                            if ($rec->recElementId == $data->title_field) {
                                if ($data->act_as_registration && $user !== null) {
                                    if ($data->registration_name_field == $rec->recElementId) {
                                        $rec->recValue = $user->name;
                                    } else
                                        if ($data->registration_username_field == $rec->recElementId) {
                                        $item->recValue = $user->username;
                                    } else
                                            if ($data->registration_email_field == $item->recElementId) {
                                        $rec->recValue = $user->email;
                                    } else
                                                if ($data->registration_email_repeat_field == $rec->recElementId) {
                                        $rec->recValue = $user->email;
                                    }
                                }
                                $candidateLabel = trim((string) ContentbuilderngHelper::cbinternal($rec->recValue));
                                if ($candidateLabel !== '') {
                                    $label = $candidateLabel;
                                    break;
                                }
                            }
                        }


                        // Preserve the computed CB title when Prefix In Title is enabled.
                        // Fall back to the Joomla menu title only when no CB title was built.
                        $prefixTitle = (string) $data->page_title;
                        if ($prefixTitle === '' && $this->_show_page_heading && $this->_page_title !== '') {
                            $data->page_title = (string) $this->_page_title;
                        }

                        if ($this->frontend) {
                            $document = $this->app->getDocument();
                            $document->setTitle(html_entity_decode($data->page_title, ENT_QUOTES, 'UTF-8'));
                        }
                    }

                    //if(!$data->edit_by_type){

                    $i = 0;
                    $api_items = '';
                    $api_names = $data->form->getElementNames();
                    $cntItems = count($api_names);
                    foreach ($api_names as $reference_id => $api_name) {
                        $api_items .= '"' . addslashes($api_name) . '": "' . addslashes($reference_id) . '"' . ($i + 1 < $cntItems ? ',' : '');
                        $i++;
                    }
                    $items = $api_items;

                    $this->app->getDocument()->getWebAssetManager()->addInlineScript(
                        '
<!--
var contentbuilderng = new function(){

   this.items = {' . $items . '};
   var items = this.items;

   this._ = function(name){
     var els = document.getElementsByName("cb_"+items[name]);
     if(els.length == 0){
        els = document.getElementsByName("cb_"+items[name]+"[]");
     }
     return els.length == 1 ? els[0] : els;
   };
   
   var _ = this._;

   this.urldecode = function (str) {
       return decodeURIComponent((str+\'\').replace(/\+/g, \'%20\'));
   };

   this.getQuery = function ( name ){
       name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");  
       var regexS = "[\\?&]"+name+"=([^&#]*)";  
       var regex = new RegExp( regexS );
       var results = regex.exec( window.location.href ); 
       if( results == null ){
           return null;
       } else {
           return this.urldecode(results[1]);
       }
   };

   this.onClick = function(name, func){
        if(typeof func != "function") return;
        var els = document.getElementsByName("cb_"+items[name]);
        if(els.length == 0){
            els = document.getElementsByName("cb_"+items[name]+"[]");
        }
        for(var i = 0; i < els.length; i++){
            els[i].onclick = func;
        }
   };
   this.onFocus = function(name, func){
        if(typeof func != "function") return;
        var els = document.getElementsByName("cb_"+items[name]);
        if(els.length == 0){
            els = document.getElementsByName("cb_"+items[name]+"[]");
        }
        for(var i = 0; i < els.length; i++){
            els[i].onfocus = func;
        }
   };
   this.onBlur = function(name, func){
        if(typeof func != "function") return;
        var els = document.getElementsByName("cb_"+items[name]);
        if(els.length == 0){
            els = document.getElementsByName("cb_"+items[name]+"[]");
        }
        for(var i = 0; i < els.length; i++){
            els[i].onblur = func;
        }
   };
   this.onChange = function(name, func){
        if(typeof func != "function") return;
        var els = document.getElementsByName("cb_"+items[name]);
        if(els.length == 0){
            els = document.getElementsByName("cb_"+items[name]+"[]");
        }
        for(var i = 0; i < els.length; i++){
            els[i].onchange = func;
        }
   };
   this.onSelect = function(name, func){
        if(typeof func != "function") return;
        var els = document.getElementsByName("cb_"+items[name]);
        if(els.length == 0){
            els = document.getElementsByName("cb_"+items[name]+"[]");
        }
        for(var i = 0; i < els.length; i++){
            els[i].onselect = func;
        }
   };
   
   this.submitReady = function(){ return true; };
   var _submitReady = this.submitReady;
   this.onSubmit = function(){ if(arguments.length > 0 && typeof arguments[0] == "function") { _submitReady = arguments[0]; return; } if(typeof _submitReady == "function" && _submitReady()) { document.forms.adminForm.submit(); } };
}
//-->
'
                    );
                    //}

                    $data->template = $this->templateRenderService->getEditableTemplate($this->_id, $this->_record_id, $data->items, $ids, !$data->edit_by_type);

                    if (
                        $this->app->isClient('administrator')
                        && strpos($data->template, '[[hide-admin-title]]') !== false
                    ) {

                        $data->page_title = '';
                    }

                    $metadata = $data->form->getRecordMetadata($this->_record_id);

                    if ($metadata instanceof \stdClass && $data->metadata) {
                        $data->created = $metadata->created ? $metadata->created : '';
                        $data->created_by = $metadata->created_by ? $metadata->created_by : '';
                        $data->modified = $metadata->modified ? $metadata->modified : '';
                        $data->modified_by = $metadata->modified_by ? $metadata->modified_by : '';
                    } else {
                        $data->created = '';
                        $data->created_by = '';
                        $data->modified = '';
                        $data->modified_by = '';
                    }
                }
                return $data;
            }
        }
        return null;
    }

    public static function customValidate($code, $field, $fields, $record_id, $form, $value)
    {
        $msg = '';
        eval($code);
        return $msg;
    }

    public static function customAction($code, $record_id, $article_id, $form, $field, $fields, array $values)
    {
        $msg = '';
        eval($code);
        return $msg;
    }

    private function isRecordAllowedByMenuFilter(object $data, array $ids): bool
    {
        if ((int) $this->_record_id <= 0 || empty($this->_menu_filter)) {
            return true;
        }

        $isAdminPreview = $this->app->input->getBool('cb_preview_ok', false);
        $publishedOnly = $isAdminPreview ? false : (bool) ($data->published_only ?? false);
        $ownerFilterUserId = $this->frontend
            ? $this->getEffectiveOwnershipUserId((bool) ($data->own_only_fe ?? false))
            : $this->getEffectiveOwnershipUserId((bool) ($data->own_only ?? false));
        $showAllLanguages = $isAdminPreview ? true : ($this->frontend ? (bool) ($data->show_all_languages_fe ?? false) : true);

        $matches = $data->form->getListRecords(
            $ids,
            '',
            [],
            0,
            1,
            '',
            [],
            'desc',
            (int) $this->_record_id,
            $publishedOnly,
            $ownerFilterUserId,
            0,
            -1,
            -1,
            -1,
            -1,
            $this->_menu_filter,
            $showAllLanguages,
            null
        );

        return is_array($matches) && count($matches) > 0;
    }

    private function shouldRestrictToPublishedOnly(object $data, bool $isAdminPreview): bool
    {
        return PublishedRecordVisibilityHelper::shouldRestrictToPublishedOnly($data, $isAdminPreview);
    }

    function store()
    {

        if (!\Joomla\CMS\Session\Session::checkToken('post')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        PluginHelper::importPlugin('contentbuilderng_submit');
        $session = $this->app->getSession();
        $session->clear('cb_failed_values', 'com_contentbuilderng.' . $this->_id);
        $this->app->input->set('cb_submission_failed', 0);

        $query = $this->_buildQuery();
        $this->_data = $this->_getList($query, 0, 1);

        if (!count($this->_data)) {
            throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
        }

        $isAdminPreview = $this->app->input->getBool('cb_preview_ok', false);

        foreach ($this->_data as $data) {
            if (!$isAdminPreview) {
                if (!$this->frontend) {
                    throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
                }
            }

            $data->form_id = $this->_id;

            if ($data->type && $data->reference_id) {

                $values = array();
                $data->form = FormSourceFactory::getForm($data->type, $data->reference_id);
                $meta = $data->form->getRecordMetadata($this->_record_id);
                if (!$data->edit_by_type) {

                    $noneditable_fields = $this->listSupportService->getListNonEditableElements($this->_id);
                    $names = $data->form->getElementNames();

                    $db = $this->getDatabase();
                    $query = $db->getQuery(true)
                        ->select('*')
                        ->from($db->quoteName('#__contentbuilderng_elements'))
                        ->where($db->quoteName('form_id') . ' = ' . (int)$this->_id)
                        ->where($db->quoteName('published') . ' = 1')
                        ->where($db->quoteName('editable') . ' = 1');
                    $db->setQuery($query);
                    $fields = $db->loadAssocList();

                    $the_fields = array();
                    $the_name_field = null;
                    $the_username_field = null;
                    $the_password_field = null;
                    $the_password_repeat_field = null;
                    $the_email_field = null;
                    $the_email_repeat_field = null;
                    $the_html_fields = array();
                    $the_upload_fields = array();
                    $the_captcha_field = null;
                    $the_failed_registration_fields = array();

                    foreach ($fields as $special_field) {
                        switch ($special_field['type']) {
                            case 'text':
                            case 'upload':
                            case 'captcha':
                            case 'textarea':
                                if ($special_field['type'] == 'upload') {
                                    $options = PackedDataHelper::decodePackedData($special_field['options'], null, false);
                                    if (!is_object($options)) {
                                        $options = new \stdClass();
                                    }
                                    $special_field['options'] = $options;
                                    $the_upload_fields[$special_field['reference_id']] = $special_field;
                                } else if ($special_field['type'] == 'captcha') {
                                    $options = PackedDataHelper::decodePackedData($special_field['options'], null, false);
                                    if (!is_object($options)) {
                                        $options = new \stdClass();
                                    }
                                    $special_field['options'] = $options;
                                    $the_captcha_field = $special_field;
                                } else if ($special_field['type'] == 'textarea') {
                                    $options = PackedDataHelper::decodePackedData($special_field['options'], null, false);
                                    if (!is_object($options)) {
                                        $options = new \stdClass();
                                    }
                                    $special_field['options'] = $options;
                                    if (isset($special_field['options']->allow_html) && $special_field['options']->allow_html) {
                                        $the_html_fields[$special_field['reference_id']] = $special_field;
                                    } else {
                                        $the_fields[$special_field['reference_id']] = $special_field;
                                    }
                                } else if ($special_field['type'] == 'text') {
                                    $options = PackedDataHelper::decodePackedData($special_field['options'], null, false);
                                    if (!is_object($options)) {
                                        $options = new \stdClass();
                                    }
                                    $special_field['options'] = $options;
                                    if ($data->act_as_registration && $data->registration_username_field == $special_field['reference_id']) {
                                        $the_username_field = $special_field;
                                    } else if ($data->act_as_registration && $data->registration_name_field == $special_field['reference_id']) {
                                        $the_name_field = $special_field;
                                    } else if ($data->act_as_registration && $data->registration_password_field == $special_field['reference_id']) {
                                        $the_password_field = $special_field;
                                    } else if ($data->act_as_registration && $data->registration_password_repeat_field == $special_field['reference_id']) {
                                        $the_password_repeat_field = $special_field;
                                    } else if ($data->act_as_registration && $data->registration_email_field == $special_field['reference_id']) {
                                        $the_email_field = $special_field;
                                    } else if ($data->act_as_registration && $data->registration_email_repeat_field == $special_field['reference_id']) {
                                        $the_email_repeat_field = $special_field;
                                    } else {
                                        $the_fields[$special_field['reference_id']] = $special_field;
                                    }
                                }
                                break;
                            default:
                                $options = PackedDataHelper::decodePackedData($special_field['options'], null, false);
                                if (!is_object($options)) {
                                    $options = new \stdClass();
                                }
                                $special_field['options'] = $options;
                                $the_fields[$special_field['reference_id']] = $special_field;
                        }
                    }

                    // we have defined a captcha, so let's test it
                    if ($the_captcha_field !== null && !in_array($the_captcha_field['reference_id'], $noneditable_fields)) {

                        if (!class_exists('Securimage')) {
                            $composerAutoload = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/vendor/autoload.php';
                            $vendorSecurimage = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/vendor/bgli100/securimage/securimage.php';

                            if (is_file($composerAutoload)) {
                                require_once $composerAutoload;
                            }

                            if (!class_exists('Securimage') && is_file($vendorSecurimage)) {
                                require_once $vendorSecurimage;
                            }
                        }

                        $securimage = new \Securimage();
                        $cap_value = $this->app->input->post->get('cb_' . $the_captcha_field['reference_id'], null, 'raw');
                        if ($securimage->check($cap_value) == false) {
                            $this->app->input->set('cb_submission_failed', 1);
                            $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_CAPTCHA_FAILED'), 'error');
                        }
                        $values[$the_captcha_field['reference_id']] = $cap_value;
                        $noneditable_fields[] = $the_captcha_field['reference_id'];
                    }

                    // now let us see if we have a registration
                    // make sure to wait for previous errors
                    if ($data->act_as_registration && $the_name_field !== null && $the_email_field !== null && $the_email_repeat_field !== null && $the_password_field !== null && $the_password_repeat_field !== null && $the_username_field !== null) {

                        $pw1 = $this->app->input->post->get('cb_' . $the_password_field['reference_id'], '', 'raw');
                        $pw2 = $this->app->input->post->get('cb_' . $the_password_repeat_field['reference_id'], '', 'raw');
                        $email = $this->app->input->post->get('cb_' . $the_email_field['reference_id'], '', 'raw');
                        $email2 = $this->app->input->post->get('cb_' . $the_email_repeat_field['reference_id'], '', 'raw');
                        $name = $this->app->input->post->get('cb_' . $the_name_field['reference_id'], '', 'raw');
                        $username = $this->app->input->post->get('cb_' . $the_username_field['reference_id'], '', 'raw');
                        $usernameLength = function_exists('mb_strlen') ? mb_strlen($username, 'UTF-8') : strlen($username);

                        if (!$this->app->input->get('cb_submission_failed', 0, 'string')) {

                            if (!trim($name)) {
                                $this->app->input->set('cb_submission_failed', 1);
                                $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_NAME_EMPTY'), 'error');
                            }

                            if (!trim($username)) {
                                $this->app->input->set('cb_submission_failed', 1);
                                $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_USERNAME_EMPTY'), 'error');
                            } else if (preg_match("#[<>\"'%;()&]#i", $username) || $usernameLength < 2) {
                                $this->app->input->set('cb_submission_failed', 1);
                                $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_USERNAME_INVALID'), 'error');
                            }

                            if (!trim($email)) {
                                $this->app->input->set('cb_submission_failed', 1);
                                $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_EMAIL_EMPTY'), 'error');
                            } else if (!ContentbuilderngHelper::isEmail($email)) {
                                $this->app->input->set('cb_submission_failed', 1);
                                $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_EMAIL_INVALID'), 'error');
                            } else if ($email != $email2) {
                                $this->app->input->set('cb_submission_failed', 1);
                                $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_EMAIL_MISMATCH'), 'error');
                            }

                            if (!$meta->created_id && !(int) ($this->app->getIdentity()->id ?? 0)) {

                                $this->getDatabase()->setQuery("Select count(id) From #__users Where `username` = " . $this->getDatabase()->quote($username));
                                if ($this->getDatabase()->loadResult()) {
                                    $this->app->input->set('cb_submission_failed', 1);
                                    $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_USERNAME_NOT_AVAILABLE'), 'error');
                                }

                                $this->getDatabase()->setQuery("Select count(id) From #__users Where `email` = " . $this->getDatabase()->quote($email));
                                if ($this->getDatabase()->loadResult()) {
                                    $this->app->input->set('cb_submission_failed', 1);
                                    $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_EMAIL_EMPTY'), 'error');
                                }

                                if ($pw1 != $pw2) {
                                    $this->app->input->set('cb_submission_failed', 1);
                                    $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_PASSWORD_MISMATCH'), 'error');

                                    $this->app->input->set('cb_' . $the_password_field['reference_id'], '');
                                    $this->app->input->set('cb_' . $the_password_repeat_field['reference_id'], '');
                                } else if (!trim($pw1)) {
                                    $this->app->input->set('cb_submission_failed', 1);
                                    $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_PASSWORD_EMPTY'), 'error');

                                    $this->app->input->set('cb_' . $the_password_field['reference_id'], '');
                                    $this->app->input->set('cb_' . $the_password_repeat_field['reference_id'], '');
                                }
                            } else {
                                if ($meta->created_id && $meta->created_id != (int) ($this->app->getIdentity()->id ?? 0)) {
                                    $this->getDatabase()->setQuery("Select count(id) From #__users Where id <> " . $this->getDatabase()->quote($meta->created_id) . " And `username` = " . $this->getDatabase()->quote($username));
                                    if ($this->getDatabase()->loadResult()) {
                                        $this->app->input->set('cb_submission_failed', 1);
                                        $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_USERNAME_NOT_AVAILABLE'), 'error');
                                    }

                                    $this->getDatabase()->setQuery("Select count(id) From #__users Where id <> " . $this->getDatabase()->quote($meta->created_id) . " And `email` = " . $this->getDatabase()->quote($email));
                                    if ($this->getDatabase()->loadResult()) {
                                        $this->app->input->set('cb_submission_failed', 1);
                                        $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_EMAIL_EMPTY'), 'error');
                                    }
                                } else {
                                    $this->getDatabase()->setQuery("Select count(id) From #__users Where id <> " . $this->getDatabase()->quote((int) ($this->app->getIdentity()->id ?? 0)) . " And `username` = " . $this->getDatabase()->quote($username));
                                    if ($this->getDatabase()->loadResult()) {
                                        $this->app->input->set('cb_submission_failed', 1);
                                        $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_USERNAME_NOT_AVAILABLE'), 'error');
                                    }

                                    $this->getDatabase()->setQuery("Select count(id) From #__users Where id <> " . $this->getDatabase()->quote((int) ($this->app->getIdentity()->id ?? 0)) . " And `email` = " . $this->getDatabase()->quote($email));
                                    if ($this->getDatabase()->loadResult()) {
                                        $this->app->input->set('cb_submission_failed', 1);
                                        $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_EMAIL_EMPTY'), 'error');
                                    }
                                }

                                if (trim($pw1) != '' || trim($pw2) != '') {

                                    if ($pw1 != $pw2) {
                                        $this->app->input->set('cb_submission_failed', 1);
                                        $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_PASSWORD_MISMATCH'), 'error');

                                        $this->app->input->set('cb_' . $the_password_field['reference_id'], '');
                                        $this->app->input->set('cb_' . $the_password_repeat_field['reference_id'], '');
                                    } else if (!trim($pw1)) {
                                        $this->app->input->set('cb_submission_failed', 1);
                                        $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_PASSWORD_EMPTY'), 'error');

                                        $this->app->input->set('cb_' . $the_password_field['reference_id'], '');
                                        $this->app->input->set('cb_' . $the_password_repeat_field['reference_id'], '');
                                    }
                                }
                            }

                            if (!$this->app->input->get('cb_submission_failed', 0, 'string')) {

                                //$noneditable_fields[] = $the_name_field['reference_id'];
                                $noneditable_fields[] = $the_password_field['reference_id'];
                                $noneditable_fields[] = $the_password_repeat_field['reference_id'];
                                //$noneditable_fields[] = $the_email_field['reference_id'];
                                $noneditable_fields[] = $the_email_repeat_field['reference_id'];
                                //$noneditable_fields[] = $the_username_field['reference_id'];

                            } else {

                                $the_failed_registration_fields[$the_name_field['reference_id']] = $the_name_field;
                                //$the_failed_registration_fields[$the_password_field['reference_id']] = $the_password_field;
                                //$the_failed_registration_fields[$the_password_repeat_field['reference_id']] = $the_password_repeat_field;
                                $the_failed_registration_fields[$the_email_field['reference_id']] = $the_email_field;
                                $the_failed_registration_fields[$the_email_repeat_field['reference_id']] = $the_email_repeat_field;
                                $the_failed_registration_fields[$the_username_field['reference_id']] = $the_username_field;
                            }
                        } else {
                            $the_failed_registration_fields[$the_name_field['reference_id']] = $the_name_field;
                            //$the_failed_registration_fields[$the_password_field['reference_id']] = $the_password_field;
                            //$the_failed_registration_fields[$the_password_repeat_field['reference_id']] = $the_password_repeat_field;
                            $the_failed_registration_fields[$the_email_field['reference_id']] = $the_email_field;
                            $the_failed_registration_fields[$the_email_repeat_field['reference_id']] = $the_email_repeat_field;
                            $the_failed_registration_fields[$the_username_field['reference_id']] = $the_username_field;
                        }
                    }

                    $form_elements_objects = array();

                    $isAdminPreview = $this->app->input->getBool('cb_preview_ok', false);
                    $publishedOnly = $this->shouldRestrictToPublishedOnly($data, $isAdminPreview);

                    $_items = $data->form->getRecord(
                        $this->_record_id,
                        $publishedOnly,
                        $this->frontend ? $this->getEffectiveOwnershipUserId((bool) $data->own_only_fe) : $this->getEffectiveOwnershipUserId((bool) $data->own_only),
                        $this->frontend ? $data->show_all_languages_fe : true
                    );

                    // asigning the proper names first
                    foreach ($names as $id => $name) {

                        if ($noneditable_fields == null || !in_array($id, $noneditable_fields)) {
                            $value = '';
                            $isGroupField = $data->form->isGroup($id);
                            if ($isGroupField) {
                                $groupValue = $this->app->input->post->get('cb_' . $id, [], 'array');
                                if (!is_array($groupValue)) {
                                    $groupValue = array($groupValue);
                                }
                                $value = array_values(array_filter($groupValue, static fn($v) => $v !== null && $v !== '' && $v !== 'cbGroupMark'));
                            } elseif (isset($the_fields[$id]['options']->allow_raw) && $the_fields[$id]['options']->allow_raw) {
                                $value = $this->app->input->post->get('cb_' . $id, '', 'raw');
                            } else if (isset($the_fields[$id]['options']->allow_html) && $the_fields[$id]['options']->allow_html) {
                                $value = $this->app->input->post->get('cb_' . $id, '', 'html');
                            } else {
                                $value = $this->app->input->post->get('cb_' . $id, '', 'raw');
                            }
                            if (!$isGroupField && isset($the_fields[$id]['options']->transfer_format)) {
                                $value = ContentbuilderngHelper::convertDate($value, $the_fields[$id]['options']->format, $the_fields[$id]['options']->transfer_format);
                            }

                            if (isset($the_html_fields[$id])) {
                                $the_html_fields[$id]['name'] = $name;
                                $the_html_fields[$id]['value'] = $value;
                            } else if (isset($the_failed_registration_fields[$id])) {
                                $the_failed_registration_fields[$id]['name'] = $name;
                                $the_failed_registration_fields[$id]['value'] = $value;
                            } else if (isset($the_upload_fields[$id])) {
                                $the_upload_fields[$id]['name'] = $name;
                                $the_upload_fields[$id]['value'] = '';
                                $the_upload_fields[$id]['orig_value'] = '';

                                if ($id == $the_upload_fields[$id]['reference_id']) {

                                    // delete if triggered
                                    if ($this->app->input->getInt('cb_delete_' . $id, 0) == 1 && isset($the_upload_fields[$id]['validations']) && $the_upload_fields[$id]['validations'] == '') {
                                        if (count($_items)) {
                                            foreach ($_items as $_item) {
                                                if ($_item->recElementId == $the_upload_fields[$id]['reference_id']) {
                                                    $_value = $_item->recValue;
                                                    $_files = explode("\n", str_replace("\r", '', $_value));
                                                    foreach ($_files as $_file) {
                                                        if (strpos(strtolower($_file), '{cbsite}') === 0) {
                                                            $_file = str_replace(array('{cbsite}', '{CBSite}'), array(JPATH_SITE, JPATH_SITE), $_file);
                                                        }
                                                        if (ContentbuilderngHelper::is_internal_path($_file) && file_exists($_file)) {
                                                            File::delete($_file);
                                                        }
                                                        $values[$id] = '';
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    $file = $this->app->input->files->get('cb_' . $id, null, 'array');

                                    if (trim(File::makeSafe($file['name'])) != '' && $file['size'] > 0) {

                                        $filename = trim(File::makeSafe($file['name']));
                                        $infile = $filename;

                                        $src = $file['tmp_name'];
                                        $dest = '';
                                        $tmp_dest = '';
                                        $tmp_upload_field_dir = '';
                                        $tmp_upload_dir = '';

                                        if (isset($the_upload_fields[$id]['options']) && isset($the_upload_fields[$id]['options']->upload_directory) && $the_upload_fields[$id]['options']->upload_directory != '') {
                                            $tmp_upload_field_dir = $the_upload_fields[$id]['options']->upload_directory;
                                            $tmp_dest = $tmp_upload_field_dir;
                                        } else if ($data->upload_directory != '') {
                                            $tmp_upload_dir = $data->upload_directory;
                                            $tmp_dest = $tmp_upload_dir;
                                        }

                                        if (isset($the_upload_fields[$id]['options']) && isset($the_upload_fields[$id]['options']->upload_directory) && $the_upload_fields[$id]['options']->upload_directory != '') {

                                            $dest = str_replace(array('{CBSite}', '{cbsite}'), JPATH_SITE, $the_upload_fields[$id]['options']->upload_directory);
                                        } else if ($data->upload_directory != '') {

                                            $dest = str_replace(array('{CBSite}', '{cbsite}'), JPATH_SITE, $data->upload_directory);
                                        }

                                        // create dest path by tokens
                                        $dest = $this->createPathByTokens($dest, $names);

                                        $msg = '';
                                        $uploaded = false;

                                        // FILE SIZE TEST

                                        if ($dest != '' && isset($the_upload_fields[$id]['options']) && isset($the_upload_fields[$id]['options']->max_filesize) && $the_upload_fields[$id]['options']->max_filesize > 0) {

                                            $val = $the_upload_fields[$id]['options']->max_filesize;
                                            $val = trim($val);
                                            $last = strtolower($val[strlen($val) - 1]);
                                            switch ($last) {
                                                case 'g':
                                                    $val *= 1024;
                                                case 'm':
                                                    $val *= 1024;
                                                case 'k':
                                                    $val *= 1024;
                                            }

                                            if ($file['size'] > $val) {
                                                $msg = Text::_('COM_CONTENTBUILDERNG_FILESIZE_EXCEEDED') . ' ' . $the_upload_fields[$id]['options']->max_filesize . 'b';
                                            }
                                        }

                                        // FILE EXT TEST

                                        if ($dest != '' && isset($the_upload_fields[$id]['options']) && isset($the_upload_fields[$id]['options']->allowed_file_extensions) && $the_upload_fields[$id]['options']->allowed_file_extensions != '') {

                                            $allowed = explode(',', str_replace(' ', '', strtolower($the_upload_fields[$id]['options']->allowed_file_extensions)));
                                            $ext = strtolower(File::getExt($filename));

                                            if (!in_array($ext, $allowed)) {
                                                $msg = Text::_('COM_CONTENTBUILDERNG_FILE_EXTENSION_NOT_ALLOWED');
                                            }
                                        }

                                        // UPLOAD

                                        if ($dest != '' && $msg == '') {

                                            // limit file's name size
                                            $ext = strtolower(File::getExt($filename));
                                            $stripped = File::stripExt($filename);
                                            // in some apache configurations unknown file extensions could lead to security risks
                                            // because it will try to find an executable extensions within the chain of dots. So we simply remove them.
                                            $filename = str_replace(array(' ', '.'), '_', $stripped) . '.' . $ext;

                                            $maxnamesize = 100;
                                            if (function_exists('mb_strlen')) {
                                                if (mb_strlen($filename) > $maxnamesize) {
                                                    $filename = mb_substr($filename, mb_strlen($filename) - $maxnamesize);
                                                }
                                            } else {
                                                if (strlen($filename) > $maxnamesize) {
                                                    $filename = substr($filename, strlen($filename) - $maxnamesize);
                                                }
                                            }

                                            // take care of existing filenames
                                            if (file_exists($dest . '/' . $filename)) {
                                                $filename = md5(mt_rand(0, mt_getrandmax()) . time()) . '_' . $filename;
                                            }

                                            // create pseudo security index.html
                                            if (!file_exists($dest . '/index.html')) {
                                                File::write($dest . '/index.html', $buffer = '');
                                            }

                                            if (count($_items)) {
                                                $files_to_delete = array();

                                                foreach ($_items as $_item) {
                                                    if ($_item->recElementId == $the_upload_fields[$id]['reference_id']) {
                                                        $_value = $_item->recValue;
                                                        $_files = explode("\n", str_replace("\r", '', $_value));
                                                        foreach ($_files as $_file) {
                                                            if (strpos(strtolower($_file), '{cbsite}') === 0) {
                                                                $_file = str_replace(array('{cbsite}', '{CBSite}'), array(JPATH_SITE, JPATH_SITE), $_file);
                                                            }
                                                            $files_to_delete[] = $_file;
                                                        }
                                                        break;
                                                    }
                                                }
                                                foreach ($files_to_delete as $file_to_delete) {
                                                    if (ContentbuilderngHelper::is_internal_path($file_to_delete) && file_exists($file_to_delete)) {
                                                        File::delete($file_to_delete);
                                                    }
                                                }
                                            }

                                            // final upload file moving
                                            if (!ContentbuilderngHelper::is_internal_path($dest)) {
                                                $uploaded = false;
                                                $msg = Text::_('COM_CONTENTBUILDERNG_UPLOAD_FAILED');
                                            } else {
                                                $uploaded = File::upload($src, $dest . '/' . $filename, false, true);
                                            }

                                            if (!$uploaded) {
                                                $msg = Text::_('COM_CONTENTBUILDERNG_UPLOAD_FAILED');
                                            }
                                        }

                                        if ($dest == '' || $uploaded !== true) {
                                            $this->app->input->set('cb_submission_failed', 1);
                                            $this->app->enqueueMessage($msg . ' (' . $infile . ')', 'error');
                                            $the_upload_fields[$id]['value'] = '';
                                        } else {
                                            if (strpos(strtolower($tmp_dest), '{cbsite}') === 0) {
                                                $dest = str_replace(array(JPATH_SITE, JPATH_SITE), array('{cbsite}', '{CBSite}'), $dest);
                                            }
                                            $values[$id] = $dest . '/' . $filename;
                                            $the_upload_fields[$id]['value'] = $values[$id];
                                        }

                                        $the_upload_fields[$id]['orig_value'] = File::makeSafe($file['name']);
                                    }

                                    if (trim($the_upload_fields[$id]['custom_validation_script'])) {
                                        $msg = self::customValidate(trim($the_upload_fields[$id]['custom_validation_script']), $the_upload_fields[$id], array_merge($the_upload_fields, $the_fields, $the_html_fields), $this->app->input->getCmd('record_id', 0), $data->form, isset($values[$id]) ? $values[$id] : '');
                                        $msg = trim($msg);
                                        if (!empty($msg)) {
                                            $this->app->input->set('cb_submission_failed', 1);
                                            $this->app->enqueueMessage(trim($msg), 'error');
                                        }
                                    }

                                    $validations = explode(',', $the_upload_fields[$id]['validations']);

                                    foreach ($validations as $validation) {
                                        \Joomla\CMS\Plugin\PluginHelper::importPlugin('contentbuilderng_validation', $validation);
                                    }

                                    $dispatcher = $this->app->getDispatcher();
                                    $eventResult = $dispatcher->dispatch('onValidate', new \Joomla\CMS\Event\GenericEvent('onValidate', array($the_upload_fields[$id], array_merge($the_upload_fields, $the_fields, $the_html_fields), $this->app->input->getCmd('record_id', 0), $data->form, isset($values[$id]) ? $values[$id] : '')));
                                    $results = $eventResult->getArgument('result') ?: [];

                                    $all_errors = implode('', $results);
                                    if (!empty($all_errors)) {
                                        if (isset($values[$id]) && ContentbuilderngHelper::is_internal_path($values[$id]) && file_exists($values[$id])) {
                                            File::delete($values[$id]);
                                        }
                                        $this->app->input->set('cb_submission_failed', 1);
                                        foreach ($results as $result) {
                                            $result = trim($result);
                                            if (!empty($result)) {
                                                $this->app->enqueueMessage(trim($result), 'error');
                                            }
                                        }
                                    }
                                }
                            } else if (isset($the_fields[$id])) {
                                $the_fields[$id]['name'] = $name;
                                $the_fields[$id]['value'] = $value;
                            }
                        }
                    }

                    foreach ($names as $id => $name) {

                        if ($noneditable_fields == null || !in_array($id, $noneditable_fields)) {

                            if (isset($the_upload_fields[$id]) && $id == $the_upload_fields[$id]['reference_id']) {
                                // nothing, done above already
                            } else {
                                $f = null;

                                if (isset($the_html_fields[$id])) {
                                    $value = $this->app->input->post->get('cb_' . $id, '', 'html');
                                    $f = $the_html_fields[$id];
                                    $the_html_fields[$id]['value'] = $value;
                                }

                                if (isset($the_failed_registration_fields[$id])) {
                                    $value = $this->app->input->post->get('cb_' . $id, '', 'raw');
                                    $f = $the_failed_registration_fields[$id];
                                    $the_failed_registration_fields[$id]['value'] = $value;
                                }

                                if (isset($the_fields[$id])) {
                                    $isGroupField = $data->form->isGroup($id);
                                    if ($isGroupField) {
                                        $groupValue = $this->app->input->post->get('cb_' . $id, [], 'array');
                                        if (!is_array($groupValue)) {
                                            $groupValue = array($groupValue);
                                        }
                                        $value = array_values(array_filter($groupValue, static fn($v) => $v !== null && $v !== '' && $v !== 'cbGroupMark'));
                                    } elseif (isset($the_fields[$id]['options']->allow_raw) && $the_fields[$id]['options']->allow_raw) {
                                        $value = $this->app->input->post->get('cb_' . $id, '', 'raw');
                                    } else if (isset($the_fields[$id]['options']->allow_html) && $the_fields[$id]['options']->allow_html) {
                                        $value = $this->app->input->post->get('cb_' . $id, '', 'html');
                                    } else {
                                        $value = $this->app->input->post->get('cb_' . $id, '', 'raw');
                                    }
                                    if (!$isGroupField && isset($the_fields[$id]['options']->transfer_format)) {
                                        $value = ContentbuilderngHelper::convertDate($value, $the_fields[$id]['options']->format, $the_fields[$id]['options']->transfer_format);
                                    }
                                    $f = $the_fields[$id];
                                    $the_fields[$id]['value'] = $value;
                                }

                                if ($f !== null) {

                                    if (trim($f['custom_validation_script'] ?? '')) {
                                        $msg = self::customValidate(trim($f['custom_validation_script']), $f, array_merge($the_upload_fields, $the_fields, $the_html_fields), $this->app->input->getCmd('record_id', 0), $data->form, $value);
                                        $msg = trim($msg);
                                        if (!empty($msg)) {
                                            $this->app->input->set('cb_submission_failed', 1);
                                            $this->app->enqueueMessage(trim($msg), 'error');
                                        }
                                    }

                                    $validations = explode(',', $f['validations'] ?? '');

                                    foreach ($validations as $validation) {
                                        \Joomla\CMS\Plugin\PluginHelper::importPlugin('contentbuilderng_validation', $validation);
                                    }

                                    $dispatcher = $this->app->getDispatcher();
                                    $eventResult = $dispatcher->dispatch('onValidate', new \Joomla\CMS\Event\GenericEvent('onValidate', array($f, array_merge($the_upload_fields, $the_fields, $the_html_fields), $this->app->input->getCmd('record_id', 0), $data->form, $value)));
                                    $results = $eventResult->getArgument('result') ?: [];

                                    $all_errors = implode('', $results);
                                    $values[$id] = $value;
                                    if (!empty($all_errors)) {
                                        $this->app->input->set('cb_submission_failed', 1);
                                        foreach ($results as $result) {
                                            $result = trim($result);
                                            if (!empty($result)) {
                                                $this->app->enqueueMessage(trim($result), 'error');
                                            }
                                        }
                                    } else {

                                        \Joomla\CMS\Plugin\PluginHelper::importPlugin('contentbuilderng_form_elements', $f['type']);

                                        $dispatcher = $this->app->getDispatcher();
                                        $eventResult = $dispatcher->dispatch(
                                            'onAfterValidationSuccess',
                                            new \Joomla\CMS\Event\GenericEvent(
                                                'onAfterValidationSuccess',
                                                array($f, $m = array_merge($the_upload_fields, $the_fields, $the_html_fields), $this->app->input->getCmd('record_id', 0), $data->form, $value)
                                            )
                                        );
                                        $plugin_validations = $eventResult->getArgument('result') ?: [];
                                        $dispatcher->clearListeners('onAfterValidationSuccess');

                                        if (!empty($plugin_validations)) {
                                            $form_elements_objects[] = $plugin_validations[0];
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $dispatcher = $this->app->getDispatcher();
                    $submit_before_result = $dispatcher->dispatch('onBeforeSubmit', new \Joomla\CMS\Event\GenericEvent('onBeforeSubmit', array($this->app->input->getCmd('record_id', 0), $data->form, $values)));

                    if ($this->app->input->get('cb_submission_failed', 0, 'string')) {
                        $session->set('cb_failed_values', $values, 'com_contentbuilderng.' . $this->_id);
                        return $this->app->input->getCmd('record_id', 0);
                    }

                    $record_return = $data->form->saveRecord($this->app->input->getCmd('record_id', 0), $values);

                    foreach ($form_elements_objects as $form_elements_object) {
                        if ($form_elements_object instanceof \CBFormElementAfterValidation) {
                            $form_elements_object->onSaveRecord($record_return);
                        }
                    }

                    if ($data->act_as_registration && $record_return) {

                        $meta = $data->form->getRecordMetadata($record_return);


                        if (!$data->registration_bypass_plugin || $meta->created_id) {

                            $user_id = $this->register(
                                '',
                                '',
                                '',
                                $meta->created_id,
                                $this->app->input->post->get('cb_' . $the_name_field['reference_id'], '', 'raw'),
                                $this->app->input->post->get('cb_' . $the_username_field['reference_id'], '', 'raw'),
                                $this->app->input->post->get('cb_' . $the_email_field['reference_id'], '', 'raw'),
                                $this->app->input->post->get('cb_' . $the_password_field['reference_id'], '', 'raw')
                            );

                            if (intval($user_id) > 0) {

                                $session->set('cb_last_record_user_id', $user_id, 'com_contentbuilderng');

                                $data->form->saveRecordUserData(
                                    $record_return,
                                    $user_id,
                                    $this->app->input->post->get('cb_' . $the_name_field['reference_id'], '', 'raw'),
                                    $this->app->input->post->get('cb_' . $the_username_field['reference_id'], '', 'raw')
                                );
                            } else {

                                // rollback upon registration problems
                                $data->form->clearDirtyRecordUserData($record_return);

                                throw new \Exception('Failed attempt to register user');
                            }
                        } else {

                            if (!$meta->created_id) {

                                $bypass = new \stdClass();
                                $verification_name = str_replace(array(';', '___', '|'), '-', trim($data->registration_bypass_verification_name) ? trim($data->registration_bypass_verification_name) : $data->title);
                                $verify_view = trim($data->registration_bypass_verify_view) ? trim($data->registration_bypass_verify_view) : $data->id;
                                $bypass->text = $orig_text = '{CBVerify plugin: ' . $data->registration_bypass_plugin . '; verification-name: ' . $verification_name . '; verify-view: ' . $verify_view . '; ' . str_replace(array("\r", "\n"), '', $data->registration_bypass_plugin_params) . '}';
                                $params = new \stdClass();

                                PluginHelper::importPlugin('content', 'contentbuilderng_verify');

                                $dispatcher = $this->app->getDispatcher();
                                $bypass_result = $dispatcher->dispatch('onPrepareContent', new \Joomla\CMS\Event\GenericEvent('onPrepareContent', array(&$bypass, &$params)));

                                $verification_id = '';

                                if ($bypass->text != $orig_text) {
                                    $verification_id = md5(uniqid('', true) . mt_rand(0, mt_getrandmax()) . (int) ($this->app->getIdentity()->id ?? 0));
                                }

                                $user_id = $this->register(
                                    $data->registration_bypass_plugin,
                                    $verification_name,
                                    $verification_id,
                                    $meta->created_id,
                                    $this->app->input->post->get('cb_' . $the_name_field['reference_id'], '', 'raw'),
                                    $this->app->input->post->get('cb_' . $the_username_field['reference_id'], '', 'raw'),
                                    $this->app->input->post->get('cb_' . $the_email_field['reference_id'], '', 'raw'),
                                    $this->app->input->post->get('cb_' . $the_password_field['reference_id'], '', 'raw')
                                );

                                if (intval($user_id) > 0) {

                                    $session->set('cb_last_record_user_id', $user_id, 'com_contentbuilderng');

                                    $data->form->saveRecordUserData(
                                        $record_return,
                                        $user_id,
                                        $this->app->input->post->get('cb_' . $the_name_field['reference_id'], '', 'raw'),
                                        $this->app->input->post->get('cb_' . $the_username_field['reference_id'], '', 'raw')
                                    );
                                } else {

                                    // rollback upon registration problems
                                    $data->form->clearDirtyRecordUserData($record_return);

                                    throw new \Exception('Failed attempt to register user');
                                }

                                if ($bypass->text != $orig_text && intval($user_id) > 0) {

                                    $_now = Factory::getDate();

                                    $setup = $session->get($data->registration_bypass_plugin . $verification_name, '', 'com_contentbuilderng.verify.' . $data->registration_bypass_plugin . $verification_name);
                                    $session->clear($data->registration_bypass_plugin . $verification_name, 'com_contentbuilderng.verify.' . $data->registration_bypass_plugin . $verification_name);
                                    $___now = $_now->toSql();

                                    $this->getDatabase()->setQuery("
                                            Insert Into #__contentbuilderng_verifications
                                            (
                                            `verification_hash`,
                                            `start_date`,
                                            `verification_data`,
                                            `user_id`,
                                            `plugin`,
                                            `ip`,
                                            `setup`,
                                            `client`
                                            )
                                            Values
                                            (
                                            " . $this->getDatabase()->quote($verification_id) . ",
                                            " . $this->getDatabase()->quote($___now) . ",
                                            " . $this->getDatabase()->quote('type=registration&') . ",
                                            " . $user_id . ",
                                            " . $this->getDatabase()->quote($data->registration_bypass_plugin) . ",
                                            " . $this->getDatabase()->quote($_SERVER['REMOTE_ADDR']) . ",
                                            " . $this->getDatabase()->quote($setup) . ",
                                            " . intval($this->app->isClient('administrator') ? 1 : 0) . "
                                            )
                                    ");
                                    $this->getDatabase()->execute();
                                }
                            }
                        }
                    }

                    if ($this->frontend && !$this->app->input->getCmd('record_id', 0) && $record_return && !$this->app->input->get('return', '', 'string')) {

                        if ($data->force_login) {
                            if (!(int) ($this->app->getIdentity()->id ?? 0)) {
                                $this->app->input->set('return', base64_encode(Route::_('index.php?option=com_users&view=login&Itemid=' . $this->app->input->getInt('Itemid', 0), false)));
                            } else {
                                $this->app->input->set('return', base64_encode(Route::_('index.php?option=com_users&view=profile&Itemid=' . $this->app->input->getInt('Itemid', 0), false)));
                            }
                        } else if (trim($data->force_url)) {
                            $this->app->input->set('ContentbuilderngHelper::cbinternalCheck', 0);
                            $this->app->input->set('return', base64_encode(trim($data->force_url)));
                        }
                    }

                    if ($record_return) {

                        $sef = '';
                        $ignore_lang_code = '*';
                        if ($data->default_lang_code_ignore) {
                            $this->getDatabase()->setQuery("Select lang_code From #__languages Where published = 1 And sef = " . $this->getDatabase()->quote(trim($this->app->input->getCmd('lang', ''))));
                            $ignore_lang_code = $this->getDatabase()->loadResult();
                            if (!$ignore_lang_code) {
                                $ignore_lang_code = '*';
                            }

                            $sef = trim($this->app->input->getCmd('lang', ''));
                            if ($ignore_lang_code == '*') {
                                $sef = '';
                            }
                        } else {
                            $this->getDatabase()->setQuery("Select sef From #__languages Where published = 1 And lang_code = " . $this->getDatabase()->quote($data->default_lang_code));
                            $sef = $this->getDatabase()->loadResult();
                        }

                        $language = $data->default_lang_code_ignore ? $ignore_lang_code : $data->default_lang_code;

                        $this->getDatabase()->setQuery("Select id, edited From #__contentbuilderng_records Where `type` = " . $this->getDatabase()->quote($data->type) . " And `reference_id` = " . $this->getDatabase()->quote($data->form->getReferenceId()) . " And record_id = " . $this->getDatabase()->quote($record_return));
                        $res = $this->getDatabase()->loadAssoc();
                        $last_update = Factory::getDate();
                        $last_update = $last_update->toSql();

                        if (!is_array($res)) {

                            $is_future = 0;
                            $created_up = Factory::getDate();
                            $created_up = $created_up->toSql();

                            if (intval($data->default_publish_up_days) != 0) {
                                $is_future = 1;
                                $date = Factory::getDate(strtotime('now +' . intval($data->default_publish_up_days) . ' days'));
                                $created_up = $date->toSql();
                            }
                            $created_down = null;
                            if (intval($data->default_publish_down_days) != 0) {
                                $date = Factory::getDate(strtotime($created_up . ' +' . intval($data->default_publish_down_days) . ' days'));
                                $created_down = $date->toSql();
                            }
                            $publishDownValue = (!empty($created_down)) ? $this->getDatabase()->quote($created_down) : 'NULL';
                            $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_records (session_id,`type`,last_update,is_future,lang_code, sef, published, record_id, reference_id, publish_up, publish_down) Values ('" . $session->getId() . "'," . $this->getDatabase()->quote($data->type) . "," . $this->getDatabase()->quote($last_update) . ",$is_future," . $this->getDatabase()->quote($language) . "," . $this->getDatabase()->quote(trim($sef)) . "," . $this->getDatabase()->quote($data->auto_publish && !$is_future ? 1 : 0) . ", " . $this->getDatabase()->quote($record_return) . ", " . $this->getDatabase()->quote($data->form->getReferenceId()) . ", " . $this->getDatabase()->quote($created_up) . ", " . $publishDownValue . ")");
                            $this->getDatabase()->execute();
                        } else {
                            $this->getDatabase()->setQuery("Update #__contentbuilderng_records Set last_update = " . $this->getDatabase()->quote($last_update) . ",lang_code = " . $this->getDatabase()->quote($language) . ", sef = " . $this->getDatabase()->quote(trim($sef ?? '')) . ", edited = edited + 1 Where `type` = " . $this->getDatabase()->quote($data->type) . " And  `reference_id` = " . $this->getDatabase()->quote($data->form->getReferenceId()) . " And record_id = " . $this->getDatabase()->quote($record_return));
                            $this->getDatabase()->execute();
                        }
                    }
                } else {

                    $record_return = $this->app->input->getCmd('record_id', 0);
                }

                $isAdminPreview = $this->app->input->getBool('cb_preview_ok', false);
                $publishedOnly = $this->shouldRestrictToPublishedOnly($data, $isAdminPreview);

                $data->items = $data->form->getRecord(
                    $record_return,
                    $publishedOnly,
                    $this->frontend ? $this->getEffectiveOwnershipUserId((bool) $data->own_only_fe) : $this->getEffectiveOwnershipUserId((bool) $data->own_only),
                    true
                );

                $data_email_items = $data->form->getRecord($record_return, false, -1, true);

                $this->getDatabase()->setQuery("Select * From #__contentbuilderng_records");

                $data->labels = $data->form->getElementLabels();
                $ids = array();
                foreach ($data->labels as $reference_id => $label) {
                    $ids[] = $this->getDatabase()->quote($reference_id);
                }
                $data->labels = array();
                if (count($ids)) {
                    $db = $this->getDatabase();
                    $query = $db->getQuery(true)
                        ->select([$db->quoteName('label'), $db->quoteName('reference_id')])
                        ->from($db->quoteName('#__contentbuilderng_elements'))
                        ->where($db->quoteName('form_id') . ' = ' . (int)$this->_id)
                        ->where($db->quoteName('reference_id') . ' IN (' . implode(',', $ids) . ')')
                        ->where($db->quoteName('published') . ' = 1')
                        ->order($db->quoteName('ordering'));
                    $db->setQuery($query);
                    $rows = $db->loadAssocList();
                    $ids = array();
                    foreach ($rows as $row) {
                        $ids[] = $row['reference_id'];
                    }
                }

                $article_id = 0;

                // creating the article
                if ($data->create_articles && count($data->items)) {

                    $data->page_title = $data->use_view_name_as_title ? $data->name : $data->form->getPageTitle();

                    //if(!count($data->items)){
                    //     throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
                    //}

                    $this->getDatabase()->setQuery("Select articles.`id` From #__contentbuilderng_articles As articles, #__content As content Where content.id = articles.article_id And (content.state = 1 Or content.state = 0) And articles.form_id = " . intval($this->_id) . " And articles.record_id = " . $this->getDatabase()->quote($record_return));
                    $article = $this->getDatabase()->loadResult();

                    $config = array();
                    if ($article) {
                        $config = $this->app->input->post->get('Form', [], 'array');
                    }

                    $permissionService = new PermissionService();
                    $full = $this->frontend ? $permissionService->authorizeFe('fullarticle') : $permissionService->authorize('fullarticle');
                    $article_id = (new ArticleService())->createArticle($this->_id, $record_return, $data->items, $ids, $data->title_field, $data->form->getRecordMetadata($record_return), $config, $full, $this->frontend ? $data->limited_article_options_fe : $data->limited_article_options, $this->app->input->get('cb_category_id', null, 'string'));

                    if (isset($form_elements_objects)) {
                        foreach ($form_elements_objects as $form_elements_object) {
                            if ($form_elements_object instanceof \CBFormElementAfterValidation) {
                                $form_elements_object->onSaveArticle($article_id);
                            }
                        }
                    }
                }

                // required to determine blocked users in system plugin
                if ($data->act_as_registration && isset($user_id) && intval($user_id) > 0) {
                    $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_registered_users (user_id, form_id, record_id) Values (" . intval($user_id) . ", " . $this->_id . ", " . $this->getDatabase()->quote($record_return) . ")");
                    $this->getDatabase()->execute();
                }

                if (!$data->edit_by_type) {

                    $cleanedValues = array();
                    foreach ($values as $rawvalue) {
                        if (is_array($rawvalue)) {
                            if (isset($rawvalue[0]) && $rawvalue[0] == 'cbGroupMark') {
                                unset($rawvalue[0]);
                                $cleanedValues[] = array_values($rawvalue);
                            } else {
                                $cleanedValues[] = $rawvalue;
                            }
                        } else {
                            $cleanedValues[] = $rawvalue;
                        }
                    }

                    $dispatcher = $this->app->getDispatcher();
                    $submit_after_result = $dispatcher->dispatch('onAfterSubmit', new \Joomla\CMS\Event\GenericEvent('onAfterSubmit', array($record_return, $article_id, $data->form, $cleanedValues)));

                    foreach ($fields as $actionField) {
                        if (trim($actionField['custom_action_script'] ?? '')) {
                            self::customAction(trim($actionField['custom_action_script']), $record_return, $article_id, $data->form, $actionField, $fields, $cleanedValues);
                        }
                    }

                    if ((!$this->app->input->getCmd('record_id', 0) && $data->email_notifications) || ($this->app->input->getCmd('record_id', 0) && $data->email_update_notifications)) {
                        $from = $MailFrom = (string) $this->app->get('mailfrom');
                        $fromname = (string) $this->app->get('fromname');


                        $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

                        $email_admin_template = '';
                        $email_template = '';

                        // admin email
                        if (trim($data->email_admin_recipients)) {

                            // sender
                            if (trim($data->email_admin_alternative_from)) {
                                foreach ($data->items as $item) {
                                    $data->email_admin_alternative_from = str_replace('{' . $item->recName . '}', ContentbuilderngHelper::cbinternal($item->recValue), $data->email_admin_alternative_from);
                                }
                                $from = $data->email_admin_alternative_from;
                            }

                            if (trim($data->email_admin_alternative_fromname)) {
                                foreach ($data->items as $item) {
                                    $data->email_admin_alternative_fromname = str_replace('{' . $item->recName . '}', ContentbuilderngHelper::cbinternal($item->recValue), $data->email_admin_alternative_fromname);
                                }
                                $fromname = $data->email_admin_alternative_fromname;
                            }

                            $mailer->setSender(array(trim($MailFrom), trim($fromname)));
                            $mailer->addReplyTo($from, $fromname);

                            // recipients
                            foreach ($data->items as $item) {
                                $data->email_admin_recipients = str_replace('{' . $item->recName . '}', ContentbuilderngHelper::cbinternal($item->recValue), $data->email_admin_recipients);
                            }

                            $recipients_checked_admin = array();
                            $recipients_admin = explode(';', $data->email_admin_recipients);

                            foreach ($recipients_admin as $recipient_admin) {
                                if (ContentbuilderngHelper::isEmail(trim($recipient_admin))) {
                                    $recipients_checked_admin[] = trim($recipient_admin);
                                }
                            }

                            $main_recipient = '';

                            if (count($recipients_checked_admin) > 0) {
                                $main_recipient = $recipients_checked_admin[0];
                                unset($recipients_checked_admin[0]);
                                $empty_array = array();
                                // fixing indexes
                                $recipients_checked_admin = array_merge($recipients_checked_admin, $empty_array);
                                // sending all the others
                                $mailer->addBCC($recipients_checked_admin);
                            }

                            $mailer->addRecipient($main_recipient);

                            $recipients_checked_admin = array_merge(array($main_recipient), $recipients_checked_admin);

                            $email_admin_template = $this->templateRenderService->getEmailTemplate($this->_id, $record_return, $data_email_items, $ids, true);

                            // subject
                            $subject_admin = Text::_('COM_CONTENTBUILDERNG_EMAIL_RECORD_RECEIVED');
                            if (trim($data->email_admin_subject)) {
                                foreach ($data->items as $item) {
                                    $data->email_admin_subject = str_replace('{' . $item->recName . '}', ContentbuilderngHelper::cbinternal($item->recValue), $data->email_admin_subject);
                                }
                                $subject_admin = $data->email_admin_subject;
                                $subject_admin = str_replace(array('{RECORD_ID}', '{record_id}'), $record_return, $subject_admin);
                                $subject_admin = str_replace(array('{USER_ID}', '{user_id}'), $this->app->getIdentity()->id, $subject_admin);
                                $subject_admin = str_replace(array('{USERNAME}', '{username}'), $this->app->getIdentity()->username, $subject_admin);
                                $subject_admin = str_replace(array('{USER_FULL_NAME}', '{user_full_name}'), $this->app->getIdentity()->name, $subject_admin);
                                $subject_admin = str_replace(array('{EMAIL}', '{email}'), $this->app->getIdentity()->email, $subject_admin);
                                $subject_admin = str_replace(array('{VIEW_NAME}', '{view_name}'), $data->name, $subject_admin);
                                $subject_admin = str_replace(array('{VIEW_ID}', '{view_id}'), $this->_id, $subject_admin);
                                $subject_admin = str_replace(array('{IP}', '{ip}'), $_SERVER['REMOTE_ADDR'], $subject_admin);
                            }

                            $mailer->setSubject($subject_admin);

                            // attachments
                            foreach ($data->items as $item) {
                                $data->email_admin_recipients_attach_uploads = str_replace('{' . $item->recName . '}', $item->recValue, $data->email_admin_recipients_attach_uploads);
                            }

                            $attachments_admin = explode(';', $data->email_admin_recipients_attach_uploads);

                            $attached_admin = array();
                            foreach ($attachments_admin as $attachment_admin) {
                                $attachment_admin = explode("\n", str_replace("\r", "", trim($attachment_admin)));
                                foreach ($attachment_admin as $att_admin) {
                                    if (strpos(strtolower($att_admin), '{cbsite}') === 0) {
                                        $att_admin = str_replace(array('{cbsite}', '{CBSite}'), array(JPATH_SITE, JPATH_SITE), $att_admin);
                                    }
                                    if (file_exists(trim($att_admin))) {
                                        $attached_admin[] = trim($att_admin);
                                    }
                                }
                            }

                            $mailer->addAttachment($attached_admin);

                            $mailer->isHTML($data->email_admin_html);
                            $mailer->setBody($email_admin_template);

                            if (count($recipients_checked_admin)) {

                                $send = $mailer->Send();

                                if ($send !== true) {
                                    $this->app->enqueueMessage('Error sending email: ' . $mailer->ErrorInfo, 'error');
                                }
                            }

                            $mailer->ClearAddresses();
                            $mailer->ClearAllRecipients();
                            $mailer->ClearAttachments();
                        }

                        // public email
                        if (trim($data->email_recipients)) {

                            // sender
                            if (trim($data->email_alternative_from)) {
                                foreach ($data->items as $item) {
                                    $data->email_alternative_from = str_replace('{' . $item->recName . '}', ContentbuilderngHelper::cbinternal($item->recValue), $data->email_alternative_from);
                                }
                                $from = $data->email_alternative_from;
                            }

                            if (trim($data->email_alternative_fromname)) {
                                foreach ($data->items as $item) {
                                    $data->email_alternative_fromname = str_replace('{' . $item->recName . '}', ContentbuilderngHelper::cbinternal($item->recValue), $data->email_alternative_fromname);
                                }
                                $fromname = $data->email_alternative_fromname;
                            }

                            $mailer->setSender(array(trim($MailFrom), trim($fromname)));
                            $mailer->addReplyTo($from, $fromname);

                            // recipients
                            foreach ($data->items as $item) {
                                $data->email_recipients = str_replace('{' . $item->recName . '}', ContentbuilderngHelper::cbinternal($item->recValue), $data->email_recipients);
                            }

                            $recipients_checked = array();
                            $recipients = explode(';', $data->email_recipients);

                            foreach ($recipients as $recipient) {
                                if (ContentbuilderngHelper::isEmail($recipient)) {
                                    $recipients_checked[] = $recipient;
                                }
                            }

                            $main_recipient = '';

                            if (count($recipients_checked) > 0) {
                                $main_recipient = $recipients_checked[0];
                                unset($recipients_checked[0]);
                                $empty_array = array();
                                // fixing indexes
                                $recipients_checked_admin = array_merge($recipients_checked, $empty_array);
                                // sending all the others
                                $mailer->addBCC($recipients_checked);
                            }

                            $mailer->addRecipient($main_recipient);

                            $recipients_checked = array_merge(array($main_recipient), $recipients_checked);

                            $email_template = $this->templateRenderService->getEmailTemplate($this->_id, $record_return, $data_email_items, $ids, false);

                            // subject
                            $subject = Text::_('COM_CONTENTBUILDERNG_EMAIL_RECORD_RECEIVED');
                            if (trim($data->email_subject)) {
                                foreach ($data->items as $item) {
                                    $data->email_subject = str_replace('{' . $item->recName . '}', ContentbuilderngHelper::cbinternal($item->recValue), $data->email_subject);
                                }
                                $subject = $data->email_subject;
                                $subject = str_replace(array('{RECORD_ID}', '{record_id}'), $record_return, $subject);
                                $subject = str_replace(array('{USER_ID}', '{user_id}'), $this->app->getIdentity()->id, $subject);
                                $subject = str_replace(array('{USERNAME}', '{username}'), $this->app->getIdentity()->username, $subject);
                                $subject = str_replace(array('{EMAIL}', '{email}'), $this->app->getIdentity()->email, $subject);
                                $subject = str_replace(array('{USER_FULL_NAME}', '{user_full_name}'), $this->app->getIdentity()->name, $subject);
                                $subject = str_replace(array('{VIEW_NAME}', '{view_name}'), $data->name, $subject);
                                $subject = str_replace(array('{VIEW_ID}', '{view_id}'), $this->_id, $subject);
                                $subject = str_replace(array('{IP}', '{ip}'), $_SERVER['REMOTE_ADDR'], $subject);
                            }

                            $mailer->setSubject($subject);

                            // attachments
                            foreach ($data->items as $item) {
                                $data->email_recipients_attach_uploads = str_replace('{' . $item->recName . '}', $item->recValue, $data->email_recipients_attach_uploads);
                            }

                            $attachments = explode(';', $data->email_recipients_attach_uploads);

                            $attached = array();
                            foreach ($attachments as $attachment) {
                                $attachment = explode("\n", str_replace("\r", "", trim($attachment)));
                                foreach ($attachment as $att) {
                                    if (strpos(strtolower($att), '{cbsite}') === 0) {
                                        $att = str_replace(array('{cbsite}', '{CBSite}'), array(JPATH_SITE, JPATH_SITE), $att);
                                    }
                                    if (file_exists(trim($att))) {
                                        $attached[] = trim($att);
                                    }
                                }
                            }

                            $mailer->addAttachment($attached);

                            $mailer->isHTML($data->email_html);
                            $mailer->setBody($email_template);

                            if (count($recipients_checked)) {

                                $send = $mailer->Send();

                                if ($send !== true) {
                                    $this->app->enqueueMessage('Error sending email: ' . $mailer->ErrorInfo, 'error');
                                }
                            }

                            $mailer->ClearAddresses();
                            $mailer->ClearAllRecipients();
                            $mailer->ClearAttachments();
                        }
                    }
                }

                return $record_return;
            }
        }

        $this->cleanComponentCaches();

        return false;
    }

    function register($bypass_plugin, $bypass_verification_name, $verification_id, $user_id, $the_name_field, $the_username_field, $the_email_field, $the_password_field)
    {
        if ($the_name_field === null || $the_email_field === null || $the_password_field === null || $the_username_field === null) {
            return 0;
        }

        if ($user_id) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $pw = '';
            if (!empty($the_password_field)) {
                $crypt = UserHelper::hashPassword($the_password_field);
                $pw = $crypt;
            }

            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__users'))
                ->set($db->quoteName('name') . ' = ' . $db->quote($the_name_field))
                ->set($db->quoteName('username') . ' = ' . $db->quote($the_username_field))
                ->set($db->quoteName('email') . ' = ' . $db->quote($the_email_field));
            if (!empty($pw)) {
                $query->set($db->quoteName('password') . ' = ' . $db->quote($pw));
            }
            $query->where($db->quoteName('id') . ' = ' . (int)$user_id);
            $db->setQuery($query);
            $db->execute();

            return $user_id;
        }

        // else execute the registration
        $this->app->getLanguage()->load('com_users', JPATH_SITE);

        $params = ComponentHelper::getParams('com_users');

        // Initialise the table with User.
        $user = new User;
        $data = array();
        $data['activation'] = '';
        $data['block'] = 0;

        // Prepare the data for the user object.
        $data['email'] = $the_email_field;
        $data['password'] = $the_password_field;
        $data['password_clear'] = $the_password_field;
        $data['name'] = $the_name_field;
        $data['username'] = $the_username_field;
        $data['groups'] = array($params->get('new_usertype'));
        $useractivation = $params->get('useractivation');

        // Check if the user needs to activate their account.
        if (($useractivation == 1) || ($useractivation == 2)) {
            $data['activation'] = ApplicationHelper::getHash(UserHelper::genRandomPassword());
            $data['block'] = 1;
        }

        // Bind the data.
        if (!$user->bind($data)) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_USERS_REGISTRATION_BIND_FAILED', Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED')),
                'error'
            );
            return false;
        }

        // Load the users plugin group.
        PluginHelper::importPlugin('user');

        // Store the data.
        if (!$user->save()) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_USERS_REGISTRATION_SAVE_FAILED', Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED')),
                'error'
            );
            return false;
        }

        $query = Factory::getContainer()->get(DatabaseInterface::class)->getQuery(true);

        // Compile the notification mail values.
        $data['name'] = (string) ($user->name ?? '');
        $data['email'] = (string) ($user->email ?? '');
        $data['username'] = (string) ($user->username ?? '');
        $data['activation'] = (string) ($user->activation ?? '');
        if (!isset($data['password_clear'])) {
            $data['password_clear'] = (string) $the_password_field;
        }

        $data['fromname'] = (string) $this->app->get('fromname');
        $data['mailfrom'] = (string) $this->app->get('mailfrom');
        $data['sitename'] = (string) $this->app->get('sitename');
        $data['siteurl'] = Uri::root();

        // Handle account activation/confirmation emails.
        if ($useractivation == 2) {
            // Set the link to confirm the user email.
            $uri = Uri::getInstance();
            $base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
            $data['activate'] = $base . Route::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

            $emailSubject = Text::_('COM_USERS_EMAIL_ACCOUNT_DETAILS');
            $emailSubject = str_replace('{NAME}', $data['name'], $emailSubject);
            $emailSubject = str_replace('{SITENAME}', $data['sitename'], $emailSubject);

            $siteurl = $data['siteurl'] . 'index.php?option=com_users&task=registration.activate&token=' . $data['activation'];
            if ($bypass_plugin) {
                $siteurl = $data['siteurl'] . 'index.php?option=com_contentbuilderng&view=verify&plugin=' . urlencode($bypass_plugin) . '&verification_name=' . urlencode($bypass_verification_name) . '&token=' . $data['activation'] . '&verification_id=' . $verification_id . '&format=raw';
            }

            $emailBody = Text::_('COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY');
            $emailBody = str_replace('{NAME}', $data['name'], $emailBody);
            $emailBody = str_replace('{SITENAME}', $data['sitename'], $emailBody);
            $emailBody = str_replace('{ACTIVATE}', $siteurl, $emailBody);
            $emailBody = str_replace('{SITEURL}', $data['siteurl'], $emailBody);
            $emailBody = str_replace('{USERNAME}', $data['username'], $emailBody);
            $emailBody = str_replace('{PASSWORD_CLEAR}', $data['password_clear'], $emailBody);
        } else if ($useractivation == 1) {
            // Set the link to activate the user account.
            $uri = Uri::getInstance();
            $base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
            $data['activate'] = $base . Route::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

            $emailSubject = Text::_('COM_USERS_EMAIL_ACCOUNT_DETAILS');
            $emailSubject = str_replace('{NAME}', $data['name'], $emailSubject);
            $emailSubject = str_replace('{SITENAME}', $data['sitename'], $emailSubject);

            $siteurl = $data['siteurl'] . 'index.php?option=com_users&task=registration.activate&token=' . $data['activation'];
            if ($bypass_plugin) {
                $siteurl = $data['siteurl'] . 'index.php?option=com_contentbuilderng&view=verify&plugin=' . urlencode($bypass_plugin) . '&verification_name=' . urlencode($bypass_verification_name) . '&token=' . $data['activation'] . '&verification_id=' . $verification_id . '&format=raw';
            }

            $emailBody = Text::_('COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY');
            $emailBody = str_replace('{NAME}', $data['name'], $emailBody);
            $emailBody = str_replace('{SITENAME}', $data['sitename'], $emailBody);
            $emailBody = str_replace('{ACTIVATE}', $siteurl, $emailBody);
            $emailBody = str_replace('{SITEURL}', $data['siteurl'], $emailBody);
            $emailBody = str_replace('{USERNAME}', $data['username'], $emailBody);
            $emailBody = str_replace('{PASSWORD_CLEAR}', $data['password_clear'], $emailBody);
        } else {

            $emailSubject = Text::_('COM_USERS_EMAIL_ACCOUNT_DETAILS');
            $emailSubject = str_replace('{NAME}', $data['name'], $emailSubject);
            $emailSubject = str_replace('{SITENAME}', $data['sitename'], $emailSubject);

            $emailBody = Text::_('COM_USERS_EMAIL_REGISTERED_BODY');
            $emailBody = str_replace('{NAME}', $data['name'], $emailBody);
            $emailBody = str_replace('{SITENAME}', $data['sitename'], $emailBody);
            $emailBody = str_replace('{SITEURL}', $data['siteurl'], $emailBody);
        }

        // Send the registration email.
        $return = false;

        try {
            $return = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer()->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);
        } catch (\Exception $e) {
        }

        // Send Notification mail to administrators
        if (($params->get('useractivation') < 2) && ($params->get('mail_to_admin') == 1)) {

            $emailSubject = Text::_('COM_USERS_EMAIL_ACCOUNT_DETAILS');
            $emailSubject = str_replace('{NAME}', $data['name'], $emailSubject);
            $emailSubject = str_replace('{SITENAME}', $data['sitename'], $emailSubject);

            $emailBodyAdmin = Text::_('COM_USERS_EMAIL_REGISTERED_NOTIFICATION_TO_ADMIN_BODY');
            $emailBodyAdmin = str_replace('{NAME}', $data['name'], $emailBodyAdmin);
            $emailBodyAdmin = str_replace('{USERNAME}', $data['username'], $emailBodyAdmin);
            $emailBodyAdmin = str_replace('{SITEURL}', $data['siteurl'], $emailBodyAdmin);

            // Get all admin users
            $query->clear()
                ->select(Factory::getContainer()->get(DatabaseInterface::class)->quoteName(array('name', 'email', 'sendEmail')))
                ->from(Factory::getContainer()->get(DatabaseInterface::class)->quoteName('#__users'))
                ->where(Factory::getContainer()->get(DatabaseInterface::class)->quoteName('sendEmail') . ' = ' . 1);

            Factory::getContainer()->get(DatabaseInterface::class)->setQuery($query);

            try {
                $rows = Factory::getContainer()->get(DatabaseInterface::class)->loadObjectList();
            } catch (\RuntimeException $e) {
                $this->app->enqueueMessage(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 'error');
                return false;
            }

            // Send mail to all superadministrators id
            foreach ($rows as $row) {
                $return = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer()->sendMail($data['mailfrom'], $data['fromname'], $row->email, $emailSubject, $emailBodyAdmin);

                // Check for an error.
                if ($return !== true) {
                    $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'), 'error');
                    return false;
                }
            }
        }

        if ($useractivation == 0) {
            $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_SAVE_SUCCESS'));
        } elseif ($useractivation == 1) {
            $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_COMPLETE_ACTIVATE'));
        } else {
            $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_COMPLETE_VERIFY'));
        }

        // Check for an error.
        if ($return !== true) {

            $this->app->enqueueMessage(Text::_('COM_USERS_REGISTRATION_SEND_MAIL_FAILED'), 'error');

            // Send a system message to administrators receiving system mails
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $q = "SELECT id
                        FROM #__users
                        WHERE block = 0
                        AND sendEmail = 1";
            $db->setQuery($q);
            $sendEmail = $db->loadColumn();

            if (count($sendEmail) > 0) {
                $Date = new Date();
                // Build the query to add the messages
                $q = "INSERT INTO `#__messages` (`user_id_from`, `user_id_to`, `date_time`, `subject`, `message`)
                                VALUES ";
                $messages = array();
                $___Date = $Date->toSql();

                foreach ($sendEmail as $userid) {
                $subject   = $db->quote(Text::_('COM_USERS_MAIL_SEND_FAILURE_SUBJECT'));
                $body      = $db->quote(Text::sprintf('COM_USERS_MAIL_SEND_FAILURE_BODY', $return, $data['username']));
                $messages[] = "(" . $userid . ", " . $userid . ", '" . $___Date . "', " . $subject . ", " . $body . ")";
                }
                $q .= implode(',', $messages);
                $db->setQuery($q);
                $db->execute();
            }
            return $user->id;
        }

        return $user->id;
    }

    function _sendMail($bypass_plugin, $bypass_verification_name, $verification_id, &$user, $password)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $name = (string) ($user->name ?? '');
        $email = (string) ($user->email ?? '');
        $username = (string) ($user->username ?? '');

        $usersConfig = ComponentHelper::getParams('com_users');
        $sitename = $this->app->get('sitename');
        $useractivation = $usersConfig->get('useractivation');
        $mailfrom = $this->app->get('mailfrom');
        $fromname = $this->app->get('fromname');
        $siteURL = Uri::base();

        $subject = sprintf(Text::_('Account details for'), $name, $sitename);
        $subject = html_entity_decode($subject, ENT_QUOTES);

        $siteurl_ = $siteURL . "index.php?option=com_users&task=registration.activate&token=" . (string) ($user->activation ?? '');
        if ($bypass_plugin) {
            $siteurl_ = $siteURL . 'index.php?option=com_contentbuilderng&view=verify&plugin=' . urlencode($bypass_plugin) . '&verification_name=' . urlencode($bypass_verification_name) . '&token=' . (string) ($user->activation ?? '') . '&verification_id=' . $verification_id . '&format=raw';
        }

        if ($useractivation == 1) {
            $message = sprintf(Text::_('SEND_MSG_ACTIVATE'), $name, $sitename, $siteurl_, $siteURL, $username, $password);
        } else {
            $message = sprintf(Text::_('SEND_MSG'), $name, $sitename, $siteURL);
        }

        $message = html_entity_decode($message, ENT_QUOTES);

        //get all super administrator
        $query = 'SELECT name, email, sendEmail' .
            ' FROM #__users' .
            ' WHERE LOWER( usertype ) = "super administrator"';
        $db->setQuery($query);
        $rows = $db->loadObjectList();

        // Send email to user
        if (!$mailfrom || !$fromname) {
            $fromname = $rows[0]->name;
            $mailfrom = $rows[0]->email;
        }

        Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer()->sendMail(
            $mailfrom,
            $fromname,
            $email,
            $subject,
            $message
        );

        // Send notification to all administrators
        $subject2 = sprintf(Text::_('Account details for'), $name, $sitename);
        $subject2 = html_entity_decode($subject2, ENT_QUOTES);

        // get superadministrators id
        foreach ($rows as $row) {
            if ($row->sendEmail) {
                $message2 = sprintf(Text::_('SEND_MSG_ADMIN'), $row->name, $sitename, $name, $email, $username);
                $message2 = html_entity_decode($message2, ENT_QUOTES);
                Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer()->sendMail(
                    $mailfrom,
                    $fromname,
                    $row->email,
                    $subject2,
                    $message2
                );
            }
        }
    }


    function delete()
    {
        $items = $this->app->input->get('cid', [], 'array');
        if (empty($this->_data)) {
            $query = $this->_buildQuery();
            $this->_data = $this->_getList($query, 0, 1);

            if (!count($this->_data)) {
                throw new \Exception(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), 404);
            }

            foreach ($this->_data as $data) {
                if (!$this->frontend) {
                    throw new \Exception(Text::_('COM_CONTENTBUILDERNG_RECORD_NOT_FOUND'), 404);
                }
                $data->form_id = $this->_id;
                if ($data->type && $data->reference_id) {
                    $data->form = FormSourceFactory::getForm($data->type, $data->reference_id);
                    $res = $data->form->delete($items, $data->form_id);
                    $cnt = count($items);
                    $new_items = array();
                    if ($res && $cnt) {
                        for ($i = 0; $i < $cnt; $i++) {
                            $new_items[] = $this->getDatabase()->quote($items[$i]);
                        }
                        $new_items = implode(',', $new_items);
                        $this->getDatabase()->setQuery("Delete From #__contentbuilderng_list_records Where form_id = " . intval($this->_id) . " And record_id In ($new_items)");
                        $this->getDatabase()->execute();
                        $this->getDatabase()->setQuery("Delete From #__contentbuilderng_records Where `type` = " . $this->getDatabase()->quote($data->type) . " And  `reference_id` = " . $this->getDatabase()->quote($data->form->getReferenceId()) . " And record_id In ($new_items)");
                        $this->getDatabase()->execute();
                        if ($data->delete_articles) {
                            $this->getDatabase()->setQuery("Select article_id From #__contentbuilderng_articles Where `type` = " . $this->getDatabase()->quote($data->type) . " And reference_id = " . $this->getDatabase()->quote($data->form->getReferenceId()) . " And record_id In ($new_items)");
                            $articles = $this->getDatabase()->loadColumn();

                            if (count($articles)) {
                                $article_items = array();
                                $article_ids = array();
                                foreach ($articles as $article) {
                                    $article_items[] = $this->getDatabase()->quote('com_content.article.' . $article);
                                    $article_ids[] = $article;
                                    $table = new \Joomla\CMS\Table\Content($this->getDatabase());
                                    // Trigger the onContentBeforeDelete event.
                                    if ($table->load($article)) {
                                        $dispatcher = $this->app->getDispatcher();
                                        $event = new \Joomla\CMS\Event\Model\BeforeDeleteEvent('onContentBeforeDelete', [
                                            'context' => 'com_content.article',
                                            'subject' => $table,
                                        ]);
                                        $dispatcher->dispatch('onContentBeforeDelete', $event);
                                    }
                                    $this->getDatabase()->setQuery("Delete From #__content Where id = " . intval($article));
                                    $this->getDatabase()->execute();
                                    // Trigger the onContentAfterDelete event.
                                    $table->reset();
                                    $dispatcher = $this->app->getDispatcher();
                                    $event = new \Joomla\CMS\Event\Model\AfterDeleteEvent('onContentAfterDelete', [
                                        'context' => 'com_content.article',
                                        'subject' => $table,
                                    ]);
                                    $dispatcher->dispatch('onContentAfterDelete', $event);
                                }
                                $db = $this->getDatabase();
                                // Safe implode of quoted article asset names
                                $assetNames = array_map(function($item) use ($db) { return $db->quote($item); }, $article_items);
                                $query = $db->getQuery(true)
                                    ->delete($db->quoteName('#__assets'))
                                    ->where($db->quoteName('name') . ' IN (' . implode(',', $assetNames) . ')');
                                $db->setQuery($query);
                                $db->execute();

                                // Safe implode of integer article IDs
                                $safeArticleIds = array_map('intval', $article_ids);
                                $query = $db->getQuery(true)
                                    ->delete($db->quoteName('#__workflow_associations'))
                                    ->where($db->quoteName('item_id') . ' IN (' . implode(',', $safeArticleIds) . ')');
                                $db->setQuery($query);
                                $db->execute();

                            }
                        }

                        $this->getDatabase()->setQuery("Delete From #__contentbuilderng_articles Where `type` = " . $this->getDatabase()->quote($data->type) . " And reference_id = " . $this->getDatabase()->quote($data->form->getReferenceId()) . " And record_id In ($new_items)");
                        $this->getDatabase()->execute();
                    }
                }
            }
        }

        $this->cleanComponentCaches();
    }

    function change_list_states()
    {

        $this->getDatabase()->setQuery('Select reference_id From #__contentbuilderng_forms Where id = ' . intval($this->_id));
        $reference_id = $this->getDatabase()->loadResult();
        if (!$reference_id) {
            return 0;
        }

        $listState = $this->app->input->getInt('list_state', 0);
        $items = $this->app->input->get('cid', [], 'array');
        if (!count($items)) {
            return 0;
        }

        $changedCount = 0;

        if ($listState < 0) {
            return 0;
        }

        if ($listState === 0) {
            $quotedItems = array();

            foreach ($items as $item) {
                $quotedItems[] = $this->getDatabase()->quote((string) $item);
            }

            if (count($quotedItems)) {
                $this->getDatabase()->setQuery(
                    "Select Count(1) From #__contentbuilderng_list_records Where form_id = "
                    . intval($this->_id)
                    . " And record_id In ("
                    . implode(',', $quotedItems)
                    . ')'
                );
                $changedCount = (int) $this->getDatabase()->loadResult();

                $this->getDatabase()->setQuery(
                    "Delete From #__contentbuilderng_list_records Where form_id = "
                    . intval($this->_id)
                    . " And record_id In ("
                    . implode(',', $quotedItems)
                    . ')'
                );
                $this->getDatabase()->execute();
            }

            return $changedCount;
        }

        // prevent from changing to an unpublished state
        $this->getDatabase()->setQuery("Select id, action From #__contentbuilderng_list_states Where published = 1 And id = " . $listState . " And form_id = " . $this->_id);
        $res = $this->getDatabase()->loadAssoc();
        if (!is_array($res)) {
            return 0;
        }

        PluginHelper::importPlugin('contentbuilderng_listaction', $res['action']);

        $dispatcher = $this->app->getDispatcher();
        $eventResult = $dispatcher->dispatch('onBeforeAction', new \Joomla\CMS\Event\GenericEvent('onBeforeAction', array($this->_id, $items)));
        $results = $eventResult->getArgument('result') ?: [];
        $error = implode('', $results);

        if ($error) {
            $this->app->enqueueMessage($error);
        }

        foreach ($items as $item) {
            $this->getDatabase()->setQuery("Select id, state_id From #__contentbuilderng_list_records Where form_id = " . $this->_id . " And record_id = " . $this->getDatabase()->quote($item));
            $res = $this->getDatabase()->loadAssoc();
            if (!is_array($res)) {
                $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_list_records (state_id, form_id, record_id, reference_id) Values (" . $listState . ", " . $this->_id . ", " . $this->getDatabase()->quote($item) . ", " . $this->getDatabase()->quote($reference_id) . ")");
                $this->getDatabase()->execute();
                $changedCount++;
            } else {
                if ((int) $res['state_id'] === $listState) {
                    continue;
                }
                $this->getDatabase()->setQuery("Update #__contentbuilderng_list_records Set state_id = " . $listState . " Where form_id = " . $this->_id . " And record_id = " . $this->getDatabase()->quote($item));
                $this->getDatabase()->execute();
                $changedCount++;
            }
        }

        $dispatcher = $this->app->getDispatcher();
        $eventResult = $dispatcher->dispatch('onAfterAction', new \Joomla\CMS\Event\GenericEvent('onAfterAction', array($this->_id, $items, $error)));
        $results = $eventResult->getArgument('result') ?: [];
        $error = implode('', $results);

        if ($error) {
            $this->app->enqueueMessage($error);
        }

        return $changedCount;
    }

    function change_list_language()
    {
        $this->getDatabase()->setQuery('Select reference_id,`type` From #__contentbuilderng_forms Where id = ' . intval($this->_id));
        $typeref = $this->getDatabase()->loadAssoc();

        if (!is_array($typeref)) {
            return;
        }

        $reference_id = $typeref['reference_id'];
        $type = $typeref['type'];

        $items = $this->app->input->get('cid', [], 'array');

        $sef = '';
        $this->getDatabase()->setQuery("Select sef From #__languages Where published = 1 And lang_code = " . $this->getDatabase()->quote($this->app->input->get('list_language', '*', 'string')));
        $sef = $this->getDatabase()->loadResult();

        foreach ($items as $item) {
            $this->getDatabase()->setQuery("Select id From #__contentbuilderng_records Where `type` = " . $this->getDatabase()->quote($type) . " And `reference_id` = " . $this->getDatabase()->quote($reference_id) . " And record_id = " . $this->getDatabase()->quote($item));
            $res = $this->getDatabase()->loadResult();
            if (!$res) {
                $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_records (`type`,lang_code, sef, record_id, reference_id) Values (" . $this->getDatabase()->quote($type) . "," . $this->getDatabase()->quote($this->app->input->get('list_language', '*', 'string')) . ", " . $this->getDatabase()->quote($sef) . ", " . $this->getDatabase()->quote($item) . ", " . $this->getDatabase()->quote($reference_id) . ")");
                $this->getDatabase()->execute();
            } else {
                $this->getDatabase()->setQuery("Update #__contentbuilderng_records Set sef = " . $this->getDatabase()->quote($sef) . ", lang_code = " . $this->getDatabase()->quote($this->app->input->get('list_language', '*', 'string')) . " Where `type` = " . $this->getDatabase()->quote($type) . " And `reference_id` = " . $this->getDatabase()->quote($reference_id) . " And record_id = " . $this->getDatabase()->quote($item));
                $this->getDatabase()->execute();
            }

            $this->getDatabase()->setQuery("Update #__contentbuilderng_articles As articles, #__content As content Set content.language = " . $this->getDatabase()->quote($this->app->input->get('list_language', '*', 'string')) . " Where ( content.state = 1 Or content.state = 0 ) And content.id = articles.article_id And articles.`type` = " . intval($type) . " And articles.reference_id = " . $this->getDatabase()->quote($reference_id) . " And articles.record_id = " . $this->getDatabase()->quote($item));
            $this->getDatabase()->execute();
        }

        $this->cleanComponentCaches();
    }

    function change_list_publish()
    {
        $storageId = (int) $this->app->input->getInt('storage_id', 0);
        $typeref = null;

        if ((int) $this->_id > 0) {
            $this->getDatabase()->setQuery('Select reference_id,`type` From #__contentbuilderng_forms Where id = ' . intval($this->_id));
            $typeref = $this->getDatabase()->loadAssoc();
        } elseif ($storageId > 0) {
            $typeref = [
                'reference_id' => $storageId,
                'type' => 'com_contentbuilderng',
            ];
        }

        if (!is_array($typeref) || !isset($typeref['reference_id'], $typeref['type'])) {
            return 0;
        }

        $reference_id = $typeref['reference_id'];
        $type = $typeref['type'];

        $items = $this->app->input->get('cid', [], 'array');
        if (!count($items)) {
            return 0;
        }

        $publish = $this->app->input->getInt('list_publish', 0) ? 1 : 0;
        $changedCount = 0;

        $this->getDatabase()->setQuery("SET @ids := null");
        $this->getDatabase()->execute();

        $created_up = Factory::getDate();
        $created_up = $created_up->toSql();

        foreach ($items as $item) {
            $this->getDatabase()->setQuery("Select id, publish_up, published From #__contentbuilderng_records Where `type` = " . $this->getDatabase()->quote($type) . " And `reference_id` = " . $this->getDatabase()->quote($reference_id) . " And record_id = " . $this->getDatabase()->quote($item));
            $res = $this->getDatabase()->loadAssoc();
            $currentPublished = is_array($res) ? (int) ($res['published'] ?? 0) : 0;
            if ($currentPublished !== $publish) {
                $changedCount++;
            }

            if (!is_array($res)) {
                $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_records (`type`,published, record_id, reference_id) Values (" . $this->getDatabase()->quote($type) . "," . $publish . ", " . $this->getDatabase()->quote($item) . ", " . $this->getDatabase()->quote($reference_id) . ")");
                $this->getDatabase()->execute();
            } else {
                $this->getDatabase()->setQuery(
                    "UPDATE #__contentbuilderng_records 
                    SET 
                        is_future = 0, 
                        publish_up = " . ($publish ? $this->getDatabase()->quote($created_up) : 'NULL') . ", 
                        publish_down = NULL, 
                        published = " . ($publish ? 1 : 0) . " 
                    WHERE `type` = " . $this->getDatabase()->quote($type) . " 
                    AND `reference_id` = " . $this->getDatabase()->quote($reference_id) . " 
                    AND record_id = " . $this->getDatabase()->quote($item)
                );
                $this->getDatabase()->execute();
            }

            $publishUpValue = $publish
                ? $this->getDatabase()->quote($created_up)
                : $this->getDatabase()->quote(is_array($res) ? $res['publish_up'] : $created_up);

            $this->getDatabase()->setQuery(
                "UPDATE #__contentbuilderng_articles AS articles
                INNER JOIN #__content AS content ON content.id = articles.article_id
                SET 
                    content.publish_up = " . $publishUpValue . ",
                    content.publish_down = NULL,
                    content.state = " . ($publish ? 1 : 0) . "
                WHERE articles.`type` = " . $this->getDatabase()->quote($type) . " 
                AND articles.reference_id = " . $this->getDatabase()->quote($reference_id) . " 
                AND articles.record_id = " . $this->getDatabase()->quote($item) . "
                AND (content.state = 0 OR content.state = 1)"
            );
            $this->getDatabase()->execute();
        }
        $this->getDatabase()->setQuery("SELECT @ids");
        $select_ids = $this->getDatabase()->loadResult();
        $affected_articles = [];
        if ($select_ids) {
            $affected_articles = explode(',', $select_ids);
        }
        $this->cleanComponentCaches();

        // Trigger the onContentChangeState event.
        $dispatcher = $this->app->getDispatcher();
        $context = 'com_content.article';
        $value = $this->app->input->getInt('list_publish', 0);
        $event = new \Joomla\CMS\Event\Model\AfterChangeStateEvent('onContentChangeState', [
            'context' => $context,
            'subject' => $affected_articles,
            'value' => $value,
        ]);
        $eventResult = $dispatcher->dispatch('onContentChangeState', $event);
        $result = $eventResult->getArgument('result') ?: [];

        return $changedCount;
    }
}
