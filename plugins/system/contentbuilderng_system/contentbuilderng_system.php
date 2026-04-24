<?php

/**
 * @version     6.0
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Service\ArticleService;
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
        $input = $this->app->getInput();
        $option = $input->getCmd('option', '');
        $task = $input->getCmd('task', '');
        $task = strtolower($task);
        $method = strtoupper((string) $input->getMethod());

        if (!in_array($option, ['com_contentbuilderng', 'com_content', 'com_breezingforms'], true)) {
            return false;
        }

        // BreezingForms submissions often do not send a Joomla "task".
        // Keep BF sync active for explicit ff_task requests and BF POST submissions.
        if ($option === 'com_breezingforms') {
            if ($input->getBool('confirmStripe')) {
                return true;
            }

            if ($input->getCmd('ff_task', '') !== '') {
                return true;
            }

            if ($method === 'POST') {
                return true;
            }
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
        if (class_exists(FormSourceFactory::class)) {
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
            $base . '/src/Helper/FormSourceFactory.php',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                return false;
            }
            require_once $file;
        }

        return class_exists(FormSourceFactory::class);
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
        $option = $app->getInput()->getCmd('option', '');
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

                $db = $this->db;
                $autoGroupsIntList = implode(',', array_map('intval', $pluginParams->get('auto_groups', [])));
                $autoGroupsRawList = implode(',', array_map('intval', $pluginParams->get('auto_groups', [])));
                $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName('cv.userid'),
                        $db->quoteName('cv.verified_view'),
                        $db->quoteName('cv.verification_date_view'),
                        $db->quoteName('forms.verification_days_view'),
                        $db->quoteName('groups.group_id'),
                        $db->quoteName('groups.user_id'),
                    ])
                    ->from('(' . $db->quoteName('#__contentbuilderng_users', 'cv') . ', ' . $db->quoteName('#__contentbuilderng_forms', 'forms') . ')')
                    ->join('LEFT', $db->quoteName('#__user_usergroup_map', 'groups') . ' ON (' . $db->quoteName('groups.user_id') . ' = ' . $db->quoteName('cv.userid') . ' AND ' . $db->quoteName('groups.group_id') . ' IN (' . $autoGroupsIntList . '))')
                    ->where([
                        $db->quoteName('cv.verification_date_view') . ' IS NOT NULL',
                        $db->quoteName('cv.verified_view') . ' = 1',
                        $db->quoteName('cv.userid') . ' <> 0',
                        $db->quoteName('cv.form_id') . ' = ' . $db->quoteName('forms.id'),
                        $db->quoteName('cv.published') . ' = 1',
                        $db->quoteName('forms.verification_required_view') . ' = 1',
                        $db->quoteName('forms.published') . ' = 1',
                        '(' . $db->quoteName('groups.user_id') . ' IS NULL AND ' . $db->quoteName('groups.group_id') . ' IS NULL OR ' . $db->quoteName('groups.user_id') . ' = ' . $db->quoteName('cv.userid') . ' AND ' . $db->quoteName('groups.group_id') . ' NOT IN (' . $autoGroupsRawList . '))',
                    ]);
                if (count($operateViews)) {
                    $query->where($db->quoteName('forms.id') . ' IN (' . implode(',', $operateViews) . ')');
                }
                $db->setQuery($query);

                $users = $this->db->loadAssocList();

                foreach ($users as $user) {
                    $groups = $pluginParams->get('auto_groups', array());
                    foreach ($groups as $group) {
                        $db = $this->db;
                        $query = $db->getQuery(true)
                            ->insert($db->quoteName('#__user_usergroup_map'))
                            ->columns([$db->quoteName('user_id'), $db->quoteName('group_id')])
                            ->values((int)$user['userid'] . ',' . (int)$group);
                        $db->setQuery($query);
                        $db->execute();
                        if ($kill_kunena_session) {
                            $kunenaQuery = $db->getQuery(true)
                                ->delete($db->quoteName('#__kunena_sessions'))
                                ->where($db->quoteName('userid') . ' = ' . (int) $user['userid']);
                            $db->setQuery($kunenaQuery);
                            $db->execute();
                        }
                    }
                }

                $autoGroupsIntList2 = implode(',', array_map('intval', $pluginParams->get('auto_groups', [])));
                $query2 = $db->getQuery(true)
                    ->select([
                        $db->quoteName('cv.id'),
                        $db->quoteName('groups.user_id'),
                        $db->quoteName('groups.group_id'),
                        $db->quoteName('cv.userid'),
                        $db->quoteName('cv.verified_view'),
                    ])
                    ->from($db->quoteName('#__user_usergroup_map', 'groups'))
                    ->join('LEFT', $db->quoteName('#__contentbuilderng_users', 'cv') . ' ON (' . $db->quoteName('cv.userid') . ' = ' . $db->quoteName('groups.user_id') . ' AND ' . $db->quoteName('groups.group_id') . ' IN (' . $autoGroupsIntList2 . '))')
                    ->where([
                        $db->quoteName('cv.userid') . ' = ' . $db->quoteName('groups.user_id'),
                        $db->quoteName('cv.userid') . ' IS NOT NULL',
                        $db->quoteName('groups.group_id') . ' IN (' . $autoGroupsIntList2 . ')',
                    ])
                    ->group([$db->quoteName('groups.user_id'), $db->quoteName('groups.group_id')])
                    ->having('SUM(' . $db->quoteName('cv.verified_view') . ') = 0');
                $this->db->setQuery($query2);

                $user_groups = $this->db->loadAssocList();

                foreach ($user_groups as $user_group) {
                    $db = $this->db;
                    $query = $db->getQuery(true)
                        ->delete($db->quoteName('#__user_usergroup_map'))
                        ->where($db->quoteName('user_id') . ' = ' . (int)$user_group['user_id'])
                        ->where($db->quoteName('group_id') . ' = ' . (int)$user_group['group_id']);
                    $db->setQuery($query);
                    $db->execute();
                    if ($kill_kunena_session) {
                        $kunenaQuery2 = $db->getQuery(true)
                            ->delete($db->quoteName('#__kunena_sessions'))
                            ->where($db->quoteName('userid') . ' = ' . (int) $user_group['user_id']);
                        $db->setQuery($kunenaQuery2);
                        $db->execute();
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

                $themeQuery = $this->db->getQuery(true)
                    ->select('DISTINCT ' . $this->db->quoteName('forms.theme_plugin'))
                    ->from($this->db->quoteName('#__contentbuilderng_forms', 'forms'))
                    ->from($this->db->quoteName('#__contentbuilderng_articles', 'articles'))
                    ->from($this->db->quoteName('#__content', 'content'))
                    ->where([
                        $this->db->quoteName('forms.id') . ' = ' . $this->db->quoteName('articles.form_id'),
                        $this->db->quoteName('articles.article_id') . ' IN (' . $the_ids . ')',
                        $this->db->quoteName('content.id') . ' = ' . $this->db->quoteName('articles.article_id'),
                        '(' . $this->db->quoteName('content.state') . ' = 1 OR ' . $this->db->quoteName('content.state') . ' = 0)',
                    ]);
                $this->db->setQuery($themeQuery);
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

            $option = $app->getInput()->getCmd('option', '');
            $view = $app->getInput()->getCmd('view', '');
            $task = $app->getInput()->getCmd('task', '');
            $layout = $app->getInput()->getCmd('layout', '');
            $id = $app->getInput()->get('id', 0, 'string');
            $id = explode(':', $id);
            $id = intval($id[0]);
            $a_id = $app->getInput()->get('a_id', 0, 'string');
            $a_id = explode(':', $a_id);
            $a_id = intval($a_id[0]);

            $pluginParams = $this->params;

            // if somebody tries to submit an article through the built-in joomla content submit
            if ($pluginParams->def('disable_new_articles', 0) && trim($app->getInput()->getCmd('option', '')) == 'com_content' && (trim($app->getInput()->getCmd('task', '')) == 'new' || trim($app->getInput()->getCmd('task', '')) == 'article.add' || (trim($app->getInput()->getCmd('view', '')) == 'article' && trim($app->getInput()->getCmd('layout', '')) == 'form') || (trim($app->getInput()->getCmd('view', '')) == 'form' && trim($app->getInput()->getCmd('layout', '')) == 'edit') && $a_id <= 0)) {
                $this->app->getLanguage()->load('com_contentbuilderng');
                $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_NEW_NOT_ALLOWED'), 'error');
                $this->app->redirect('index.php');
            }

            // redirect to content edit if there is a record existing for this article
            if ($option == 'com_content' && (($id && $view == 'article' && $task == 'edit') || ($a_id && $view == 'form' && $layout == 'edit'))) {
                $id = $a_id;
                $redirectQuery = $this->db->getQuery(true)
                    ->select([$this->db->quoteName('article.record_id'), $this->db->quoteName('article.form_id')])
                    ->from($this->db->quoteName('#__contentbuilderng_articles', 'article'))
                    ->from($this->db->quoteName('#__content', 'content'))
                    ->where([
                        $this->db->quoteName('content.id') . ' = ' . (int) $id,
                        '(' . $this->db->quoteName('content.state') . ' = 0 OR ' . $this->db->quoteName('content.state') . ' = 1)',
                        $this->db->quoteName('article.article_id') . ' = ' . $this->db->quoteName('content.id'),
                    ]);
                $this->db->setQuery($redirectQuery);
                $article = $this->db->loadAssoc();
                if (is_array($article)) {
                    $this->app->redirect('index.php?option=com_contentbuilderng&task=edit.display&id=' . $article['form_id'] . "&record_id=" . $article['record_id'] . "&jsback=1&Itemid=" . $app->getInput()->getInt('Itemid', 0));
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
            $formsQuery = $this->db->getQuery(true)
                ->select([$this->db->quoteName('type'), $this->db->quoteName('reference_id')])
                ->from($this->db->quoteName('#__contentbuilderng_forms'))
                ->where($this->db->quoteName('published') . ' = 1');
            $this->db->setQuery($formsQuery);
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

            $this->createMissingArticles();
        }

        if ($isSyncMutationRequest) {
            // managing published states
            $date = Factory::getDate()->toSql();

            $db = $this->db;
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_records'))
                ->set($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('is_future') . ' = 1')
                ->where($db->quoteName('publish_up') . ' IS NOT NULL')
                ->where($db->quoteName('publish_up') . ' <= ' . $db->quote($date));
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_records'))
                ->set($db->quoteName('published') . ' = 0')
                ->where($db->quoteName('publish_down') . ' IS NOT NULL')
                ->where($db->quoteName('publish_down') . ' <= ' . $db->quote($date));
            $db->setQuery($query);
            $db->execute();

            // published states END
        }

        // Keep logout return URLs stable when the return target points to com_contentbuilderng.
        $enc = base64_decode($app->getInput()->get('return', '', 'string'), true);
        if (is_string($enc) && $enc !== '') {
            $enc = explode('?', $enc);
            count($enc) > 1 ? parse_str($enc[1], $out) : $out = array();
            if (isset($out['option']) && $out['option'] == 'com_contentbuilderng') {
                unset($out['view']);
                $return = http_build_query($out, '', '&');
                $app->getInput()->set('return', base64_encode('index.php' . ($return ? '?' : '') . $return));
            }
        }

        $option = $app->getInput()->getCmd('option', '');

        if ($option === 'com_content') {

            $pluginParams = $this->params;

            if ($pluginParams->def('nocache', 1)) {
                $this->caching = $app->getConfig()->get(preg_replace("/^config./", '', 'config.caching', 1), null);
                $this->app->getConfig()->set('config.caching', 0);
            }
        }

        if ($isSyncMutationRequest) {

            // Multi-table UPDATE (MySQL-specific syntax): DDL-style raw string with quoted identifiers.
            $db = $this->db;
            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__contentbuilderng_records', 'records') . ','
                . $db->quoteName('#__contentbuilderng_forms', 'forms') . ','
                . $db->quoteName('#__contentbuilderng_registered_users', 'cbusers') . ','
                . $db->quoteName('#__users', 'users')
                . ' SET ' . $db->quoteName('records.published') . ' = 0'
                . ' WHERE ' . $db->quoteName('records.reference_id') . ' = ' . $db->quoteName('forms.reference_id')
                . ' AND ' . $db->quoteName('records.published') . ' = 1'
                . ' AND ' . $db->quoteName('records.type') . ' = ' . $db->quoteName('forms.type')
                . ' AND ' . $db->quoteName('forms.act_as_registration') . ' = 1'
                . ' AND ' . $db->quoteName('forms.id') . ' = ' . $db->quoteName('cbusers.form_id')
                . ' AND ' . $db->quoteName('records.record_id') . ' = ' . $db->quoteName('cbusers.record_id')
                . ' AND (' . $db->quoteName('users.id') . ' = ' . $db->quoteName('cbusers.user_id')
                . ' AND ' . $db->quoteName('users.block') . ' = 1)'
            );
            $db->execute();

            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__contentbuilderng_records', 'records') . ','
                . $db->quoteName('#__contentbuilderng_forms', 'forms') . ','
                . $db->quoteName('#__contentbuilderng_registered_users', 'cbusers') . ','
                . $db->quoteName('#__users', 'users')
                . ' SET ' . $db->quoteName('records.published') . ' = ' . $db->quoteName('forms.auto_publish')
                . ' WHERE ' . $db->quoteName('records.reference_id') . ' = ' . $db->quoteName('forms.reference_id')
                . ' AND ' . $db->quoteName('records.published') . ' = 0'
                . ' AND ' . $db->quoteName('records.type') . ' = ' . $db->quoteName('forms.type')
                . ' AND ' . $db->quoteName('forms.act_as_registration') . ' = 1'
                . ' AND ' . $db->quoteName('forms.id') . ' = ' . $db->quoteName('cbusers.form_id')
                . ' AND ' . $db->quoteName('records.record_id') . ' = ' . $db->quoteName('cbusers.record_id')
                . ' AND ' . $db->quoteName('users.id') . ' = ' . $db->quoteName('cbusers.user_id')
                . ' AND ' . $db->quoteName('users.block') . ' = 0'
            );
            $db->execute();
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

            // Multi-table UPDATE (MySQL-specific syntax): DDL-style raw string with quoted identifiers.
            $db = $this->db;
            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__contentbuilderng_articles', 'articles') . ','
                . $db->quoteName('#__content', 'content') . ','
                . $db->quoteName('#__contentbuilderng_forms', 'forms') . ','
                . $db->quoteName('#__contentbuilderng_registered_users', 'cbusers') . ','
                . $db->quoteName('#__users', 'users')
                . ' SET ' . $db->quoteName('content.state') . ' = 0'
                . ' WHERE ' . $db->quoteName('articles.article_id') . ' = ' . $db->quoteName('content.id')
                . ' AND ' . $db->quoteName('content.state') . ' = 1'
                . ' AND ' . $db->quoteName('articles.form_id') . ' = ' . $db->quoteName('forms.id')
                . ' AND ' . $db->quoteName('forms.act_as_registration') . ' = 1'
                . ' AND ' . $db->quoteName('forms.id') . ' = ' . $db->quoteName('cbusers.form_id')
                . ' AND ' . $db->quoteName('content.created_by') . ' = ' . $db->quoteName('cbusers.user_id')
                . ' AND (' . $db->quoteName('users.id') . ' = ' . $db->quoteName('cbusers.user_id')
                . ' AND ' . $db->quoteName('users.block') . ' = 1)'
            );
            $db->execute();

            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__contentbuilderng_articles', 'articles') . ','
                . $db->quoteName('#__content', 'content') . ','
                . $db->quoteName('#__contentbuilderng_forms', 'forms') . ','
                . $db->quoteName('#__contentbuilderng_records', 'records') . ','
                . $db->quoteName('#__contentbuilderng_registered_users', 'cbusers') . ','
                . $db->quoteName('#__users', 'users')
                . ' SET ' . $db->quoteName('content.state') . ' = ' . $db->quoteName('forms.auto_publish')
                . ' WHERE ' . $db->quoteName('articles.article_id') . ' = ' . $db->quoteName('content.id')
                . ' AND ' . $db->quoteName('content.state') . ' = 0'
                . ' AND ' . $db->quoteName('articles.form_id') . ' = ' . $db->quoteName('forms.id')
                . ' AND ' . $db->quoteName('forms.act_as_registration') . ' = 1'
                . ' AND ' . $db->quoteName('forms.id') . ' = ' . $db->quoteName('cbusers.form_id')
                . ' AND ' . $db->quoteName('content.created_by') . ' = ' . $db->quoteName('cbusers.user_id')
                . ' AND ' . $db->quoteName('users.id') . ' = ' . $db->quoteName('cbusers.user_id')
                . ' AND ' . $db->quoteName('records.record_id') . ' = ' . $db->quoteName('cbusers.record_id')
                . ' AND ' . $db->quoteName('records.type') . ' = ' . $db->quoteName('forms.type')
                . ' AND ' . $db->quoteName('users.block') . ' = 0'
            );
            $db->execute();

            $this->createMissingArticles();
        }
    }

    private function createMissingArticles(): void
    {
        $db = $this->db;
        $pluginParams = $this->params;

        $syncQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('form.id', 'form_id'),
                $db->quoteName('form.act_as_registration'),
                $db->quoteName('form.default_category'),
                $db->quoteName('form.registration_name_field'),
                $db->quoteName('form.registration_username_field'),
                $db->quoteName('form.registration_email_field'),
                $db->quoteName('form.registration_email_repeat_field'),
                $db->quoteName('form.last_update'),
                $db->quoteName('article.article_id'),
                $db->quoteName('form.title_field'),
                $db->quoteName('form.create_articles'),
                $db->quoteName('form.name'),
                $db->quoteName('form.use_view_name_as_title'),
                $db->quoteName('form.protect_upload_directory'),
                $db->quoteName('form.reference_id'),
                $db->quoteName('records.record_id'),
                $db->quoteName('form.type'),
                $db->quoteName('form.published_only'),
                $db->quoteName('form.own_only'),
                $db->quoteName('form.own_only_fe'),
                $db->quoteName('records.last_update', 'record_last_update'),
                $db->quoteName('article.last_update', 'article_last_update'),
            ])
            ->from($db->quoteName('#__contentbuilderng_records', 'records'))
            ->join('LEFT', $db->quoteName('#__contentbuilderng_forms', 'form') . ' ON (' . $db->quoteName('form.type') . ' = ' . $db->quoteName('records.type') . ' AND ' . $db->quoteName('form.reference_id') . ' = ' . $db->quoteName('records.reference_id') . ')')
            ->join('LEFT', $db->quoteName('#__contentbuilderng_articles', 'article') . ' ON (' . $db->quoteName('form.type') . ' = ' . $db->quoteName('records.type') . ' AND ' . $db->quoteName('form.reference_id') . ' = ' . $db->quoteName('records.reference_id') . ' AND ' . $db->quoteName('article.form_id') . ' = ' . $db->quoteName('form.id') . ' AND ' . $db->quoteName('article.record_id') . ' = ' . $db->quoteName('records.record_id') . ')')
            ->join('LEFT', $db->quoteName('#__content', 'content') . ' ON (' . $db->quoteName('form.type') . ' = ' . $db->quoteName('records.type') . ' AND ' . $db->quoteName('form.reference_id') . ' = ' . $db->quoteName('records.reference_id') . ' AND ' . $db->quoteName('article.article_id') . ' = ' . $db->quoteName('content.id') . ' AND ' . $db->quoteName('article.form_id') . ' = ' . $db->quoteName('form.id') . ' AND ' . $db->quoteName('article.record_id') . ' = ' . $db->quoteName('records.record_id') . ')')
            ->where([
                $db->quoteName('form.published') . ' = 1',
                $db->quoteName('form.create_articles') . ' = 1',
                $db->quoteName('form.type') . ' = ' . $db->quoteName('records.type'),
                $db->quoteName('form.reference_id') . ' = ' . $db->quoteName('records.reference_id'),
                '((' . $db->quoteName('article.form_id') . ' = ' . $db->quoteName('form.id')
                    . ' AND ' . $db->quoteName('article.record_id') . ' = ' . $db->quoteName('records.record_id')
                    . ' AND ' . $db->quoteName('article.article_id') . ' = ' . $db->quoteName('content.id')
                    . ' AND (' . $db->quoteName('content.state') . ' = 1 OR ' . $db->quoteName('content.state') . ' = 0)'
                    . ' AND (' . $db->quoteName('form.last_update') . ' > ' . $db->quoteName('article.last_update')
                    . ' OR ' . $db->quoteName('records.last_update') . ' > ' . $db->quoteName('article.last_update') . ')'
                    . ') OR ('
                    . $db->quoteName('form.id') . ' IS NOT NULL AND ' . $db->quoteName('records.id') . ' IS NOT NULL AND ' . $db->quoteName('content.id') . ' IS NULL AND ' . $db->quoteName('article.id') . ' IS NULL'
                    . '))',
            ])
            ->setLimit((int) $pluginParams->def('limit_per_turn', 50));
        $this->db->setQuery($syncQuery);
        $list = $this->db->loadAssocList();

        if (isset($list[0])) {
            $lang = $this->app->getLanguage();
            $lang->load('com_contentbuilderng', JPATH_ADMINISTRATOR);
        }

        $now = Factory::getDate()->toSql();

        foreach ($list as $data) {
            if (!is_array($data) || !$data['create_articles']) {
                continue;
            }

            $form = FormSourceFactory::getForm($data['type'], $data['reference_id']);
            if (!$form || !$form->exists) {
                continue;
            }

            $data['labels'] = $form->getElementLabels();
            $ids = array();
            foreach ($data['labels'] as $reference_id => $label) {
                $ids[] = $this->db->Quote($reference_id);
            }

            if (count($ids)) {
                $elementsQuery = $this->db->getQuery(true)
                    ->select('DISTINCT ' . $this->db->quoteName('label') . ', ' . $this->db->quoteName('reference_id'))
                    ->from($this->db->quoteName('#__contentbuilderng_elements'))
                    ->where([
                        $this->db->quoteName('form_id') . ' = ' . (int) $data['form_id'],
                        $this->db->quoteName('reference_id') . ' IN (' . implode(',', $ids) . ')',
                        $this->db->quoteName('published') . ' = 1',
                    ])
                    ->order($this->db->quoteName('ordering'));
                $this->db->setQuery($elementsQuery);
                $rows = $this->db->loadAssocList();
                $ids = array();
                foreach ($rows as $row) {
                    $ids[] = $row['reference_id'];
                }
            }

            $data['items'] = $form->getRecord($data['record_id'], false, -1, true);

            $article_id = $this->app->bootComponent('com_contentbuilderng')->getContainer()->get(ArticleService::class)->createArticle($data['form_id'], $data['record_id'], $data['items'], $ids, $data['title_field'], $form->getRecordMetadata($data['record_id']), array(), false, 1, $data['default_category']);

            if ($article_id) {
                $updateArticleQuery = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__contentbuilderng_articles'))
                    ->set($this->db->quoteName('last_update') . ' = ' . $this->db->quote($now))
                    ->where([
                        $this->db->quoteName('article_id') . ' = ' . $this->db->quote($article_id),
                        $this->db->quoteName('record_id') . ' = ' . $this->db->quote($data['record_id']),
                        $this->db->quoteName('form_id') . ' = ' . $this->db->quote($data['form_id']),
                    ]);
                $this->db->setQuery($updateArticleQuery);
                $this->db->execute();
            }
        }
    }
}
