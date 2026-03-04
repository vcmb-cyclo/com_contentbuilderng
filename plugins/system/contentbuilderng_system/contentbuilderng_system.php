<?php

/**
 * @version     6.0
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;

class plgSystemContentbuilderng_system extends CMSPlugin implements SubscriberInterface
{
    /**
     * Application object.
     *
     * @var    \Joomla\CMS\Application\CMSApplication
     * @since  5.0.0
     */
    protected $app;

    /**
     * Database object.
     *
     * @var    \Joomla\Database\DatabaseDriver
     * @since  5.0.0
     */
    protected $db;

    private $caching = 0;

    /**
     * True when current request is a write mutation for CB/Joomla/BF.
     * Display/read requests must not trigger synchronization work.
     */
    private function isSyncMutationRequest(): bool
    {
        $input = $this->app->input;
        $option = $input->getCmd('option', '');
        $task = $input->getCmd('task', '');
        $task = strtolower($task);

        if (!in_array($option, ['com_contentbuilderng', 'com_content', 'com_breezingforms'], true)) {
            return false;
        }

        if ($task === '') {
            return false;
        }

        // Explicit CB mutation handlers.
        if ($option === 'com_contentbuilderng' && (str_starts_with($task, 'edit.') || str_starts_with($task, 'details.'))) {
            return true;
        }

        // Generic mutation tasks for Joomla content / BF.
        return (bool) preg_match('/(^|\.)(save|apply|publish|unpublish|archive|trash|delete|remove|batch)$/', $task);
    }

    /**
     * Ensure ContentBuilder NG helper classes are available for this plugin lifecycle.
     */
    private function bootstrapContentbuilder(): bool
    {
        if (class_exists(ContentbuilderLegacyHelper::class)) {
            return true;
        }

        $base = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng';
        if (!is_dir($base)) {
            return false;
        }

        $files = [
            $base . '/src/Helper/PackedDataHelper.php',
            $base . '/src/Helper/Logger.php',
            $base . '/src/Helper/ContentbuilderngHelper.php',
            $base . '/src/Helper/ContentbuilderLegacyHelper.php',
            $base . '/src/Helper/FormSourceFactory.php',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                return false;
            }
            require_once $file;
        }

        return class_exists(ContentbuilderLegacyHelper::class);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterDispatch' => 'onAfterDispatch',
            'onAfterInitialise' => 'onAfterInitialise',
            'onAfterRoute' => 'onAfterRoute',
            'onBeforeRender' => 'onBeforeRender',
        ];
    }

    function onBeforeRender()
    {
        $pluginParams = $this->params;
        //        CBCompat::getPluginParams($this, 'system', 'contentbuilderng_system');

        if ($pluginParams->def('nocache', 1)) {
            $this->app->getConfig()->set('config.caching', $this->caching);
        }
    }

    function onAfterDispatch()
    {
        if (!$this->bootstrapContentbuilder()) {
            return;
        }

        // Managing auto-groups
        $app = $this->app;
        $option = $app->input->getCmd('option', '');
        if ($option === 'com_kunena' || $option === 'com_contentbuilderng') {

            $pluginParams = $this->params;

            if (intval($pluginParams->get('is_auto_groups', 0)) == 1 && count($pluginParams->get('auto_groups', array()))) {
                $operateViews = array();
                if ($pluginParams->get('auto_groups_limit_views', '') != '') {
                    $operateViews = explode(',', $pluginParams->get('auto_groups_limit_views', ''));
                    $operateViewsCnt = count($operateViews);
                    for ($i = 0; $i < $operateViewsCnt; $i++) {
                        $operateViews[$i] = intval($operateViews[$i]);
                    }
                }

                // KUNENA SUPPORT, REMOVES THE KUNENA SESSION IF EXISTING ON GROUP UPDATES
                $kill_kunena_session = false;
                if (is_dir(JPATH_SITE . '/administrator/components/com_kunena/')) {
                    $kill_kunena_session = true;
                }

                $this->db->setQuery("
                        Select cv.userid, cv.verified_view, cv.verification_date_view, forms.verification_days_view, groups.group_id, groups.user_id
                            From 
                        (
                            #__contentbuilderng_users As cv,
                            #__contentbuilderng_forms As forms
                        )
                        Left Join #__user_usergroup_map As groups On ( groups.user_id = cv.userid And groups.group_id In (" . implode(',', $pluginParams->get('auto_groups', array())) . ") )
                            Where 
                        cv.verification_date_view IS NOT NULL  
                            And 
                        cv.verified_view = 1
                            And
                        cv.userid <> 0
                            And
                        cv.form_id = forms.id 
                            And
                        cv.published = 1
                            And
                        forms.verification_required_view = 1
                            And
                        " . (count($operateViews) ? ' forms.id In (' . implode(',', $operateViews) . ') And ' : '') . "
                        forms.published = 1
                            And
                        (
                            (
                               groups.user_id Is Null And groups.group_id Is Null
                                Or
                               groups.user_id = cv.userid And groups.group_id Not In (" . implode(',', $pluginParams->get('auto_groups', array())) . ")
                            )
                        )");

                $users = $this->db->loadAssocList();

                foreach ($users as $user) {
                    $groups = $pluginParams->get('auto_groups', array());
                    foreach ($groups as $group) {
                        $this->db->setQuery("Insert Ignore Into #__user_usergroup_map (user_id, group_id) Values (" . $user['userid'] . ", " . intval($group) . ")");
                        $this->db->execute();
                        if ($kill_kunena_session) {
                            $this->db->setQuery("Delete From #__kunena_sessions Where userid = " . $user['userid']);
                            $this->db->execute();
                        }
                    }
                }

                $this->db->setQuery(
                    "
                        Select cv.id, groups.user_id, groups.group_id, cv.userid, cv.verified_view
                            From 
                        #__user_usergroup_map As groups
                            Left Join #__contentbuilderng_users As cv On ( cv.userid = groups.user_id And groups.group_id In (" . implode(',', $pluginParams->get('auto_groups', array())) . ") ) 
                        Where 
                            cv.userid = groups.user_id
                        And
                            cv.userid Is Not Null
                        And
                            groups.group_id In (" . implode(',', $pluginParams->get('auto_groups', array())) . ")
                        Group By groups.user_id, groups.group_id
                            Having Sum(cv.verified_view) = 0"
                );

                $user_groups = $this->db->loadAssocList();

                foreach ($user_groups as $user_group) {
                    $this->db->setQuery("Delete From #__user_usergroup_map Where user_id = " . $user_group['user_id'] . " And group_id = " . intval($user_group['group_id']) . "");
                    $this->db->execute();
                    if ($kill_kunena_session) {
                        $this->db->setQuery("Delete From #__kunena_sessions Where userid = " . $user_group['user_id']);
                        $this->db->execute();
                    }
                }
            }
        }
        // managing auto-groups END

        if ($this->app->isClient('site')) {
            // loading the required themes, if any
            $document = $this->app->getDocument();

            if (method_exists($document, 'getBuffer')) {
                $body = (string) $document->getBuffer('component');
            } else {
                return;
            }
            preg_match_all("/<!--\(cbArticleId:(\d{1,})\)-->/si", $body, $matched_ids);

            $ids = array();
            if (isset($matched_ids[1]) && is_array($matched_ids[1])) {
                foreach ($matched_ids[1] as $id) {
                    if (!in_array(intval($id), $ids)) {
                        $ids[] = intval($id);
                    }
                }
            }
            $the_ids = implode(',', $ids);

            if ($the_ids) {
                $wa = $this->app->getDocument()->getWebAssetManager();

                // Charge le manifeste joomla.asset.json du composant
                $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
                $wa->useScript('com_contentbuilderng.contentbuilderng');

                $this->db->setQuery("Select Distinct forms.theme_plugin From #__contentbuilderng_forms As forms, #__contentbuilderng_articles As articles, #__content As content Where forms.id = articles.form_id And articles.article_id In (" . $the_ids . ") And content.id = articles.article_id And (content.state = 1 Or content.state = 0)");
                $themes = $this->db->loadColumn();
                foreach ($themes as $theme) {
                    if ($theme) {
                        if (!PluginHelper::importPlugin('contentbuilderng_themes', $theme)) {
                            PluginHelper::importPlugin('contentbuilderng_themes', 'joomla6');
                        }
                        $dispatcher = $this->app->getDispatcher();
                        $eventresults_css = $dispatcher->dispatch('onContentTemplateCss', new \Joomla\CMS\Event\GenericEvent('onContentTemplateCss', ['theme' => $theme]));
                        $eventresults_js = $dispatcher->dispatch('onContentTemplateJavascript', new \Joomla\CMS\Event\GenericEvent('onContentTemplateJavascript', ['theme' => $theme]));
                        $results_css = $eventresults_css->getArgument('result');
                        $results_js = $eventresults_js->getArgument('result');

                        $css = is_array($results_css) ? implode('', $results_css) : (string) $results_css;
                        $js  = is_array($results_js)  ? implode('', $results_js)  : (string) $results_js;

                        if ($css !== '') $wa->addInlineStyle($css);
                        if ($js !== '')  $wa->addInlineScript($js);
                    }
                }
            }
            // theme loading end

            $option = $app->input->getCmd('option', '');
            $view = $app->input->getCmd('view', '');
            $task = $app->input->getCmd('task', '');
            $layout = $app->input->getCmd('layout', '');
            $id = $app->input->get('id', 0, 'string');
            $id = explode(':', $id);
            $id = intval($id[0]);
            $a_id = $app->input->get('a_id', 0, 'string');
            $a_id = explode(':', $a_id);
            $a_id = intval($a_id[0]);

            $pluginParams = $this->params;

            // if somebody tries to submit an article through the built-in joomla content submit
            if ($pluginParams->def('disable_new_articles', 0) && trim($app->input->getCmd('option', '')) == 'com_content' && (trim($app->input->getCmd('task', '')) == 'new' || trim($app->input->getCmd('task', '')) == 'article.add' || (trim($app->input->getCmd('view', '')) == 'article' && trim($app->input->getCmd('layout', '')) == 'form') || (trim($app->input->getCmd('view', '')) == 'form' && trim($app->input->getCmd('layout', '')) == 'edit') && $a_id <= 0)) {
                $this->app->getLanguage()->load('com_contentbuilderng');
                $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_NEW_NOT_ALLOWED'), 'error');
                $this->app->redirect('index.php');
            }

            // redirect to content edit if there is a record existing for this article
            if ($option == 'com_content' && (($id && $view == 'article' && $task == 'edit') || ($a_id && $view == 'form' && $layout == 'edit'))) {
                $id = $a_id;
                $this->db->setQuery("Select article.record_id, article.form_id From #__contentbuilderng_articles As article, #__content As content Where content.id = " . intval($id) . " And (content.state = 0 Or content.state = 1) And article.article_id = content.id");
                $article = $this->db->loadAssoc();
                if (is_array($article)) {
                    $this->app->redirect('index.php?option=com_contentbuilderng&task=edit.display&id=' . $article['form_id'] . "&record_id=" . $article['record_id'] . "&jsback=1&Itemid=" . $app->input->getInt('Itemid', 0));
                }
            }
        }
    }

    function onAfterRoute()
    {
        if (!$this->bootstrapContentbuilder()) {
            return;
        }

        $app = $this->app;
        $isSyncMutationRequest = $this->isSyncMutationRequest();
        // register non-existent records
        if ($isSyncMutationRequest) {
            $this->db->setQuery("Select `type`, `reference_id` From #__contentbuilderng_forms Where published = 1");
            $views = $this->db->loadAssocList();
            $typeview = array();
            foreach ($views as $view) {
                if (!isset($typeview[$view['type'] . $view['reference_id']])) {
                    $typeview[$view['type'] . $view['reference_id']] = true;
                    $form = FormSourceFactory::getForm($view['type'], $view['reference_id']);
                    if (is_object($form)) {
                        $form->synchRecords();
                    }
                }
            }
        }

        if ($isSyncMutationRequest) {
            // managing published states
            $date = Factory::getDate()->toSql();

            $this->db->setQuery("Update #__contentbuilderng_records Set published = 1 Where is_future = 1 And publish_up IS NOT NULL And publish_up <= '" . $date . "'");
            $this->db->execute();

            $this->db->setQuery("Update #__contentbuilderng_records Set published = 0 Where publish_down IS NOT NULL And publish_down <= '" . $date . "'");
            $this->db->execute();

            // published states END
        }

        // Keep logout return URLs stable when the return target points to com_contentbuilderng.
        $enc = base64_decode($app->input->get('return', '', 'string'), true);
        if (is_string($enc) && $enc !== '') {
            $enc = explode('?', $enc);
            count($enc) > 1 ? parse_str($enc[1], $out) : $out = array();
            if (isset($out['option']) && $out['option'] == 'com_contentbuilderng') {
                unset($out['view']);
                $return = http_build_query($out, '', '&');
                $app->input->set('return', base64_encode('index.php' . ($return ? '?' : '') . $return));
            }
        }

        $option = $app->input->getCmd('option', '');

        if ($option === 'com_content') {

            $pluginParams = $this->params;

            if ($pluginParams->def('nocache', 1)) {
                $this->caching = $app->getConfig()->get(preg_replace("/^config./", '', 'config.caching', 1), null);
                $this->app->getConfig()->set('config.caching', 0);
            }
        }

        if ($isSyncMutationRequest) {

            $this->db->setQuery("
                    Update 
                        #__contentbuilderng_records As records,
                        #__contentbuilderng_forms As forms,
                        #__contentbuilderng_registered_users As cbusers,
                        #__users As users
                    Set 
                        records.published = 0
                    Where
                        records.reference_id = forms.reference_id
                    And
                        records.published = 1
                    And
                        records.`type` = forms.`type`
                    And
                        forms.act_as_registration = 1
                    And
                        forms.id = cbusers.form_id
                    And
                        records.record_id = cbusers.record_id
                    And
                      (
                        (
                            users.id = cbusers.user_id
                          And
                            users.block = 1
                        )
                      )
                    ");
            $this->db->execute();

            $this->db->setQuery("
                    Update 
                        #__contentbuilderng_records As records,
                        #__contentbuilderng_forms As forms,
                        #__contentbuilderng_registered_users As cbusers,
                        #__users As users
                    Set 
                        records.published = forms.auto_publish
                    Where
                        records.reference_id = forms.reference_id
                    And
                        records.published = 0
                    And
                        records.`type` = forms.`type`
                    And
                        forms.act_as_registration = 1
                    And
                        forms.id = cbusers.form_id
                    And
                        records.record_id = cbusers.record_id
                    And
                        users.id = cbusers.user_id
                    And
                        users.block = 0
                    ");
            $this->db->execute();
        }
    }

    function onAfterInitialise()
    {
        $this->onAfterInitialize();
    }

    function onAfterInitialize()
    {
        if (!$this->bootstrapContentbuilder()) {
            return;
        }

        $app = $this->app;

        if (!$app->isClient('site')) {
            return;
        }

        // Keep this plugin passive on non-mutation frontend requests.
        if (!$this->isSyncMutationRequest()) {
            return;
        }

        // synch the records if there are any changes
        if ($app->isClient('site')) {
            $user = $this->app->getIdentity();

            $this->db->setQuery("
                    Update
                        #__contentbuilderng_articles As articles,
                        #__content As content, 
                        #__contentbuilderng_forms As forms,
                        #__contentbuilderng_registered_users As cbusers,
                        #__users As users
                    Set 
                        content.state = 0
                    Where 
                        articles.article_id = content.id
                    And
                        content.state = 1
                    And
                        articles.form_id = forms.id
                    And
                        forms.act_as_registration = 1
                    And
                        forms.id = cbusers.form_id
                    And
                        content.created_by = cbusers.user_id
                    And
                      (
                        (
                            users.id = cbusers.user_id
                          And
                            users.block = 1
                        )
                      )
                    ");
            $this->db->execute();

            $this->db->setQuery("
                    Update 
                        #__contentbuilderng_articles As articles,
                        #__content As content, 
                        #__contentbuilderng_forms As forms,
                        #__contentbuilderng_records As records,
                        #__contentbuilderng_registered_users As cbusers,
                        #__users As users
                    Set 
                        content.state = forms.auto_publish
                    Where 
                        articles.article_id = content.id
                    And
                        content.state = 0
                    And
                        articles.form_id = forms.id
                    And
                        forms.act_as_registration = 1
                    And
                        forms.id = cbusers.form_id
                    And
                        content.created_by = cbusers.user_id
                    And
                        users.id = cbusers.user_id
                    And
                        records.record_id = cbusers.record_id
                    And
                        records.`type` = forms.`type`
                    And
                        users.block = 0
                    ");
            $this->db->execute();

            $pluginParams = $this->params;

            $this->db->setQuery("
                Select 
                    form.id As form_id,
                    form.act_as_registration,
                    form.default_category,
                    form.registration_name_field, 
                    form.registration_username_field, 
                    form.registration_email_field, 
                    form.registration_email_repeat_field, 
                    form.`last_update`,
                    article.`article_id`,
                    form.`title_field`,
                    form.`create_articles`,
                    form.`name`,
                    form.`use_view_name_as_title`,
                    form.`protect_upload_directory`,
                    form.`reference_id`,
                    records.`record_id`,
                    form.`type`,
                    form.`published_only`,
                    form.`own_only`,
                    form.`own_only_fe`,
                    records.`last_update` As record_last_update,
                    article.`last_update` As article_last_update
                From
                    #__contentbuilderng_records As records
                    Left Join #__contentbuilderng_forms As form On ( form.`type` = records.`type` And form.reference_id = records.reference_id )
                    Left Join #__contentbuilderng_articles As article On ( form.`type` = records.`type` And form.reference_id = records.reference_id And article.form_id = form.id And article.record_id = records.record_id )
                    Left Join #__content As content On ( form.`type` = records.`type` And form.reference_id = records.reference_id And article.article_id = content.id And article.form_id = form.id And article.record_id = records.record_id )
                Where 
                    form.`published` = 1
                And
                    form.create_articles = 1
                And
                    form.`type` = records.`type`
                And 
                    form.reference_id = records.reference_id
                And
                   (
                     (
                        article.form_id = form.id 
                      And 
                        article.record_id = records.record_id
                      And 
                        article.article_id = content.id 
                      And 
                        ( content.state = 1 Or content.state = 0 )
                      And
                      (
                        form.`last_update` > article.`last_update`   
                       Or
                        records.`last_update` > article.`last_update`
                      )
                     )
                     Or
                     (
                        form.id Is Not Null And records.id Is Not Null And content.id Is Null And article.id Is Null
                     )
                   )
                Limit " . intval($pluginParams->def('limit_per_turn', 50)));
            $list = $this->db->loadAssocList();

            if (isset($list[0])) {
                $lang = $this->app->getLanguage();
                $lang->load('com_contentbuilderng', JPATH_ADMINISTRATOR);
            }

            $now = Factory::getDate()->toSql();

            foreach ($list as $data) {

                if (is_array($data)) {

                    $form = FormSourceFactory::getForm($data['type'], $data['reference_id']);
                    if (!$form || !$form->exists) {
                        return;
                    }

                    // creating the article
                    if ($data['create_articles']) {

                        $data['labels'] = $form->getElementLabels();
                        $ids = array();
                        foreach ($data['labels'] as $reference_id => $label) {
                            $ids[] = $this->db->Quote($reference_id);
                        }

                        if (count($ids)) {
                            $this->db->setQuery("Select Distinct `label`, reference_id From #__contentbuilderng_elements Where form_id = " . intval($data['form_id']) . " And reference_id In (" . implode(',', $ids) . ") And published = 1 Order By ordering");
                            $rows = $this->db->loadAssocList();
                            $ids = array();
                            foreach ($rows as $row) {
                                $ids[] = $row['reference_id'];
                            }
                        }

                        $data['items'] = $form->getRecord($data['record_id'], false, -1, true);

                        $article_id = ContentbuilderLegacyHelper::createArticle($data['form_id'], $data['record_id'], $data['items'], $ids, $data['title_field'], $form->getRecordMetadata($data['record_id']), array(), false, 1, $data['default_category']);

                        if ($article_id) {
                            $this->db->setQuery("Update #__contentbuilderng_articles Set `last_update`=" . $this->db->Quote($now) . " Where article_id = " . $this->db->Quote($article_id) . " And record_id = " . $this->db->Quote($data['record_id']) . " And form_id = " . $this->db->Quote($data['form_id']));
                            $this->db->execute();
                        }
                    }
                }
            }
        }
    }
}
