<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class ArticleService
{
    private readonly FormResolverService $formResolverService;
    private readonly TemplateRenderService $templateRenderService;
    private readonly TextUtilityService $textUtilityService;

    public function __construct()
    {
        $this->formResolverService = new FormResolverService();
        $this->templateRenderService = new TemplateRenderService();
        $this->textUtilityService = new TextUtilityService();
    }

    public function createArticle($contentbuilderngFormId, $recordId, array $record, array $elementsAllowed, $titleField = '', $metadata = null, $config = [], $full = false, $limitedOptions = true, $menuCatId = null)
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $skipDetailsTemplateOnSave = $app->isClient('site') && $input->getCmd('task', '') === 'edit.save';

        $tz = new \DateTimeZone(Factory::getApplication()->get('offset'));

        foreach (['publish_up', 'created', 'publish_down'] as $dateKey) {
            if (isset($config[$dateKey]) && $config[$dateKey]) {
                $config[$dateKey] = Factory::getDate($config[$dateKey], $tz)->format('Y-m-d H:i:s');
            } else {
                $config[$dateKey] = null;
            }
        }

        $tpl = '';

        if (!$skipDetailsTemplateOnSave) {
            $tpl = $this->templateRenderService->getTemplate($contentbuilderngFormId, $recordId, $record, $elementsAllowed, true);

            if (!$tpl) {
                return 0;
            }
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery('Select * From #__contentbuilderng_forms Where id = ' . (int) $contentbuilderngFormId . ' And published = 1');
        $form = $db->loadAssoc();

        if (!$form) {
            return 0;
        }

        if ($menuCatId !== null && (int) $menuCatId > -2) {
            $form['default_category'] = $menuCatId;
        }

        $user = null;

        if ($form['act_as_registration']) {
            if ($recordId) {
                $formObject = $this->formResolverService->getForm($form['type'], $form['reference_id']);
                $meta = $formObject->getRecordMetadata($recordId);
                $db->setQuery('Select * From #__users Where id = ' . $meta->created_id);
                $user = $db->loadObject();
            } elseif ((int) (Factory::getApplication()->getIdentity()->id ?? 0)) {
                $db->setQuery('Select * From #__users Where id = ' . (int) (Factory::getApplication()->getIdentity()->id ?? 0));
                $user = $db->loadObject();
            }
        }

        $label = '';

        foreach ($record as $rec) {
            if ($rec->recElementId == $titleField) {
                if ($form['act_as_registration'] && $user !== null) {
                    if ($form['registration_name_field'] == $rec->recElementId) {
                        $rec->recValue = $user->name;
                    } elseif ($form['registration_username_field'] == $rec->recElementId) {
                        $rec->recValue = $user->username;
                    } elseif ($form['registration_email_field'] == $rec->recElementId || $form['registration_email_repeat_field'] == $rec->recElementId) {
                        $rec->recValue = $user->email;
                    }
                }

                $label = ContentbuilderngHelper::cbinternal($rec->recValue);
                break;
            }
        }

        if (!$label && !count($record)) {
            $label = 'Unnamed';
        } elseif (!$label && count($record)) {
            $label = ContentbuilderngHelper::cbinternal($record[0]->recValue);
        }

        $introtext = '';
        $fulltext = '';
        $tpl = str_replace('<br>', '<br />', $tpl);
        $pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
        $tagPos = preg_match($pattern, $tpl);

        if ($tagPos == 0) {
            $introtext = $tpl;
        } else {
            [$introtext, $fulltext] = preg_split($pattern, $tpl, 2);
        }

        $db->setQuery(
            'Select published, is_future, publish_up, publish_down From #__contentbuilderng_records'
            . ' Where `type` = ' . $db->quote($form['type'])
            . ' And reference_id = ' . $db->quote($form['reference_id'])
            . ' And record_id = ' . $db->quote($recordId)
        );
        $stateData = $db->loadAssoc();
        $publishUpRecord = $stateData['publish_up'];
        $publishDownRecord = $stateData['publish_down'];
        $state = $stateData['is_future'] ? 1 : $stateData['published'];

        $alias = '';
        $db->setQuery(
            'Select articles.`article_id`, content.`alias`'
            . ' From #__contentbuilderng_articles As articles, #__content As content'
            . ' Where content.id = articles.article_id And (content.state = 1 Or content.state = 0)'
            . ' And articles.form_id = ' . (int) $contentbuilderngFormId
            . ' And articles.record_id = ' . $db->quote($recordId)
        );
        $article = $db->loadAssoc();

        if (is_array($article)) {
            $alias = $article['alias'];
            $article = $article['article_id'];
        }

        if ($skipDetailsTemplateOnSave && (int) $article > 0) {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('introtext'), $db->quoteName('fulltext')])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int) $article);
            $db->setQuery($query);
            $existingContent = $db->loadAssoc();

            if (is_array($existingContent)) {
                $introtext = (string) ($existingContent['introtext'] ?? '');
                $fulltext = (string) ($existingContent['fulltext'] ?? '');
            }
        }

        $attribs = '';
        $meta = '';
        $metakey = '';
        $metadesc = '';
        $createdBy = 0;
        $createdByAlias = '';
        $createdArticle = null;
        $_now = Factory::getDate();
        $createdUp = $publishUpRecord;
        $createdDown = $publishDownRecord;

        if (is_array($article) && isset($article['article_id']) && (int) $form['default_publish_up_days'] != 0) {
            $date = Factory::getDate(strtotime((($createdUp !== null) ? $createdUp : $_now) . ' +' . (int) $form['default_publish_down_days'] . ' days'));
            $createdUp = $date->toSql();
        }

        $publishUp = $createdUp;

        if (is_array($article) && isset($article['article_id']) && (int) $form['default_publish_down_days'] != 0) {
            $date = Factory::getDate(strtotime(($createdUp !== null ? $createdUp : $_now) . ' +' . (int) $form['default_publish_down_days'] . ' days'));
            $createdDown = $date->toSql();
        }

        $publishDown = $createdDown;
        $featured = $form['default_featured'];
        $ignoreLangCode = '*';

        if ($form['default_lang_code_ignore']) {
            $db->setQuery("Select lang_code From #__languages Where published = 1 And sef = " . $db->quote(Factory::getApplication()->input->getCmd('lang', '')));
            $ignoreLangCode = $db->loadResult() ?: '*';
        }

        $language = $form['default_lang_code_ignore'] ? $ignoreLangCode : $form['default_lang_code'];
        $access = $form['default_access'];
        $ordering = 0;

        if ($full) {
            $alias = $config['alias'] ?? $alias;
            $form['default_category'] = $config['catid'] ?? $form['default_category'];
            $access = $config['access'] ?? $access;
            $featured = $config['featured'] ?? 0;
            $language = $config['language'] ?? $language;

            if ($form['article_record_impact_language'] && isset($config['language'])) {
                $db->setQuery('Select sef From #__languages Where published = 1 And lang_code = ' . $db->quote($config['language']));
                $sef = $db->loadResult() ?? '';
                $db->setQuery(
                    'Update #__contentbuilderng_records Set sef = ' . $db->quote($sef)
                    . ', lang_code = ' . $db->quote($config['language'])
                    . ' Where `type` = ' . $db->quote($form['type'])
                    . ' And reference_id = ' . $db->quote($form['reference_id'])
                    . ' And record_id = ' . $db->quote($recordId)
                );
                $db->execute();
            }

            if ($form['article_record_impact_publish'] && isset($config['publish_up']) && $config['publish_up'] != $publishUp) {
                $___now = $_now->toSql();
                $publishUpConfig = $config['publish_up'] ?? null;
                $setPart = '';

                if ($publishUpConfig && strtotime($publishUpConfig) >= strtotime($___now)) {
                    $setPart = 'published = 0, is_future = 1, ';
                }

                $db->setQuery(
                    'UPDATE #__contentbuilderng_records SET ' . $setPart
                    . ' publish_up = ' . ($publishUpConfig ? $db->quote($publishUpConfig) : 'NULL')
                    . ' WHERE `type` = ' . $db->quote($form['type'])
                    . ' AND reference_id = ' . $db->quote($form['reference_id'])
                    . ' AND record_id = ' . $db->quote($recordId)
                );
                $db->execute();
            }

            $publishUp = $config['publish_up'] ?? $publishUp;

            if ($form['article_record_impact_publish'] && isset($config['publish_down']) && $config['publish_down'] != $publishDown) {
                $___now = $_now->toSql();
                $publishDownConfig = $config['publish_down'] ?? null;
                $setPart = '';

                if ($publishDownConfig && strtotime($publishDownConfig) <= strtotime($___now)) {
                    $setPart = 'published = 0, ';
                }

                $db->setQuery(
                    'UPDATE #__contentbuilderng_records SET ' . $setPart
                    . ' publish_down = ' . ($publishDownConfig ? $db->quote($publishDownConfig) : 'NULL')
                    . ' WHERE `type` = ' . $db->quote($form['type'])
                    . ' AND reference_id = ' . $db->quote($form['reference_id'])
                    . ' AND record_id = ' . $db->quote($recordId)
                );
                $db->execute();
            }

            $publishDown = $config['publish_down'] ?? $publishDown;
            $metakey = $config['metakey'] ?? '';
            $metadesc = $config['metadesc'] ?? '';
            $robots = '';
            $author = '';
            $rights = '';
            $xreference = '';

            if (!$limitedOptions) {
                $createdArticle = $config['created'] ?? null;

                if (Factory::getApplication()->isClient('administrator')) {
                    $createdBy = $config['created_by'] ?? 0;
                }

                if (isset($config['attribs']) && is_array($config['attribs'])) {
                    $registry = new Registry();
                    $registry->loadArray($config['attribs']);
                    $attribs = (string) $registry;
                }

                if (isset($config['metadata']) && is_array($config['metadata'])) {
                    $robots = $config['metadata']['robots'] ?? '';
                    $author = $config['metadata']['author'] ?? '';
                    $rights = $config['metadata']['rights'] ?? '';
                    $xreference = $config['metadata']['xreference'] ?? '';

                    $registry = new Registry();
                    $registry->loadArray($config['metadata']);
                    $meta = (string) $registry;
                }
            }

            $createdByAlias = $config['created_by_alias'] ?? '';

            $db->setQuery(
                'Update #__contentbuilderng_records Set robots = ' . $db->quote($robots)
                . ', author = ' . $db->quote($author)
                . ', rights = ' . $db->quote($rights)
                . ', xreference = ' . $db->quote($xreference)
                . ', metakey = ' . $db->quote($metakey)
                . ', metadesc = ' . $db->quote($metadesc)
                . ' Where `type` = ' . $db->quote($form['type'])
                . ' And reference_id = ' . $db->quote($form['reference_id'])
                . ' And record_id = ' . $db->quote($recordId)
            );
            $db->execute();

            $isNew = true;
            $table = new \Joomla\CMS\Table\Content($db);

            if ($article > 0) {
                $table->load($article);
                $isNew = false;
            }

            $dispatcher = Factory::getApplication()->getDispatcher();
            $event = new \Joomla\CMS\Event\Model\BeforeSaveEvent('onContentBeforeSave', [
                'context' => 'com_content.article',
                'subject' => $table,
                'isNew' => $isNew,
                'data' => [],
            ]);
            $dispatcher->dispatch('onContentBeforeSave', $event);
        }

        $createdBy = $createdBy ?: $metadata->created_id;
        $created = $createdArticle ?: ($metadata->created ?: Factory::getDate()->toSql());

        if ($created && strlen(trim($created)) <= 10) {
            $created .= ' 00:00:00';
        }

        if (!$publishUp) {
            $publishUp = $created;
        }

        if (!$publishDown && !$article) {
            $publishDown = null;
        }

        $alias = $alias
            ? $this->textUtilityService->stringURLUnicodeSlug($alias)
            : $this->textUtilityService->stringURLUnicodeSlug($label);

        if (trim(str_replace('-', '', $alias)) == '') {
            $alias = Factory::getDate()->format('%Y-%m-%d-%H-%M-%S');
        }

        if (!$article) {
            $db->setQuery(
                "Insert Into
                    #__content
                        (
                         `images`,`urls`,`title`,`alias`,`introtext`,`fulltext`,`state`,`catid`,`created`,`created_by`,
                         `modified`,`modified_by`,`checked_out`,`checked_out_time`,`publish_up`,`publish_down`,`attribs`,`version`,
                         `metakey`,`metadesc`,`metadata`,`access`,`created_by_alias`,`ordering`,featured,language
                        )
                    Values
                        (
                          '{\"image_intro\":\"\",\"image_intro_alt\":\"\",\"float_intro\":\"\",\"image_intro_caption\":\"\",\"image_fulltext\":\"\",\"image_fulltext_alt\":\"\",\"float_fulltext\":\"\",\"image_fulltext_caption\":\"\"}',
                          '{\"urla\":\"\",\"urlatext\":\"\",\"targeta\":\"\",\"urlb\":\"\",\"urlbtext\":\"\",\"targetb\":\"\",\"urlc\":\"\",\"urlctext\":\"\",\"targetc\":\"\"}',
                          " . $db->quote($label) . ',
                          ' . $db->quote($alias) . ',
                          ' . $db->quote($introtext) . ',
                          ' . $db->quote($fulltext) . ',
                          ' . $db->quote($state) . ',
                          ' . (int) $form['default_category'] . ',
                          ' . $db->quote($created) . ',
                          ' . $db->quote($createdBy ? $createdBy : (int) (Factory::getApplication()->getIdentity()->id ?? 0)) . ',
                          ' . $db->quote($created) . ',
                          ' . $db->quote($createdBy ? $createdBy : (int) (Factory::getApplication()->getIdentity()->id ?? 0)) . ",
                          NULL,NULL,
                          " . ($publishUp ? $db->quote($publishUp) : 'NULL') . ',
                          ' . ($publishDown ? $db->quote($publishDown) : 'NULL') . ',
                          ' . $db->quote($attribs !== '' ? $attribs : '{"article_layout":"","show_title":"","link_titles":"","show_tags":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_hits":"","show_noauth":"","urls_position":"","alternative_readmore":"","article_page_title":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}') . ",
                          '1',
                          " . $db->quote($metakey) . ',
                          ' . $db->quote($metadesc) . ',
                          ' . $db->quote($meta !== '' ? $meta : '{"robots":"","author":"","rights":""}') . ',
                          ' . $db->quote($access) . ',
                          ' . $db->quote($createdByAlias) . ',
                          ' . $db->quote($ordering) . ',
                          ' . $db->quote($featured) . ',
                          ' . $db->quote($language) . '
                        )'
            );
            $db->execute();

            $article = $db->insertid();
            $___datenow = Factory::getDate()->toSql();
            $db->setQuery(
                'Insert Into #__contentbuilderng_articles (`type`,`reference_id`,`last_update`,`article_id`,`record_id`,`form_id`) Values ('
                . $db->quote($form['type']) . ','
                . $db->quote($form['reference_id']) . ','
                . $db->quote($___datenow) . ','
                . $article . ','
                . $db->quote($recordId) . ','
                . (int) $contentbuilderngFormId . ')'
            );
            $db->execute();
            $db->setQuery("Update #__content Set introtext = concat('<div style=\\'display:none;\\'><!--(cbArticleId:$article)--></div>', introtext) Where id = $article");
            $db->execute();

            $db->setQuery('Select * From #__assets Where `name` = ' . $db->quote('com_content.category.' . (int) $form['default_category']));
            $parentAsset = $db->loadAssoc();

            if ($parentAsset) {
                $parentId = $parentAsset['id'];
                $db->setQuery(
                    'Insert Into #__assets (`rules`,`name`,title,parent_id, level, lft, rgt) Values (\'{}\',' . $db->quote('com_content.article.' . $article) . ', ' . $db->quote($label) . ',' . $db->quote($parentId) . ",3,( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__assets As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__assets As mrgt) As filet ))"
                );
                $db->execute();

                $assetId = $db->insertid();
                $db->setQuery('Select max(mrgt.rgt)+1 From #__assets As mrgt');
                $rgt = $db->loadResult();
                $db->setQuery('Update `#__assets` Set rgt = ' . $rgt . " Where `name` = 'root.1' And level = 0");
                $db->execute();
                $db->setQuery('Update `#__content` Set asset_id = ' . $db->quote($assetId) . ' Where `id` = ' . $db->quote($article));
                $db->execute();
                $db->setQuery("Insert Into #__workflow_associations (item_id, stage_id, extension) Values (" . $db->quote($article) . " , 1, 'com_content.article')");
                $db->execute();
            }
        } else {
            $___datenow = Factory::getDate()->toSql();
            $modified = $___datenow;
            $currentUserId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);
            $metadataModifiedBy = isset($metadata->modified_id) ? (int) $metadata->modified_id : 0;
            $modifiedBy = $currentUserId > 0 ? $currentUserId : $metadataModifiedBy;

            if ($full) {
                $db->setQuery(
                    "Update #__content Set
                        `title` = " . $db->quote($label) . ",
                        `alias` = " . $db->quote($alias) . ",
                        `introtext` = " . $db->quote('<div style=\'display:none;\'><!--(cbArticleId:' . $article . ')--></div>' . $introtext) . ",
                        `fulltext` = " . $db->quote($fulltext . '<div style=\'display:none;\'><!--(cbArticleId:' . $article . ')--></div>') . ",
                        `state` = " . $db->quote($state) . ",
                        `catid` = " . (int) $form['default_category'] . ",
                        `modified` = " . $db->quote($modified) . ",
                        `modified_by` = " . $db->quote($modifiedBy ? $modifiedBy : (int) (Factory::getApplication()->getIdentity()->id ?? 0)) . ",
                        `attribs` = " . $db->quote($attribs !== '' ? $attribs : '{"article_layout":"","show_title":"","link_titles":"","show_tags":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_hits":"","show_noauth":"","urls_position":"","alternative_readmore":"","article_page_title":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}') . ",
                        `metakey` = " . $db->quote($metakey) . ",
                        `metadesc` = " . $db->quote($metadesc) . ",
                        `metadata` = " . $db->quote($meta !== '' ? $meta : '{"robots":"","author":"","rights":""}') . ",
                        `version` = `version`+1,
                        `created` = " . $db->quote($created) . ",
                        `created_by` = " . $db->quote($createdBy) . ",
                        `created_by_alias` = " . $db->quote($createdByAlias) . ",
                        `publish_up` = " . ($publishUp !== '' ? $db->quote($publishUp) : 'NULL') . ",
                        `publish_down` = " . ($publishDown !== '' ? $db->quote($publishDown) : 'NULL') . ",
                        `access` = " . $db->quote($access) . ",
                        `ordering` = " . $db->quote($ordering) . ",
                        featured = " . $db->quote($featured) . ",
                        language = " . $db->quote($language) . "
                    Where id = $article"
                );
                $db->execute();

                $db->setQuery('Select * From #__assets Where `name` = ' . $db->quote('com_content.category.' . (int) $form['default_category']));
                $parentAsset = $db->loadAssoc();

                if ($parentAsset) {
                    $db->setQuery('Delete From `#__assets` Where `name` = ' . $db->quote('com_content.article.' . $article));
                    $db->execute();
                    $parentId = $parentAsset['id'];
                    $db->setQuery(
                        'Insert Into #__assets (`rules`,`name`,title,parent_id, level, lft, rgt) Values (\'{}\',' . $db->quote('com_content.article.' . $article) . ', ' . $db->quote($label) . ',' . $db->quote($parentId) . ",3,( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__assets As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__assets As mrgt) As filet ))"
                    );
                    $db->execute();
                    $assetId = $db->insertid();
                    $db->setQuery('Select max(mrgt.rgt)+1 From #__assets As mrgt');
                    $rgt = $db->loadResult();
                    $db->setQuery('Update `#__assets` Set rgt = ' . $rgt . " Where `name` = 'root.1' And level = 0");
                    $db->execute();
                    $db->setQuery('Update `#__content` Set asset_id = ' . $db->quote($assetId) . ' Where `id` = ' . $db->quote($article));
                    $db->execute();
                }
            } else {
                $db->setQuery(
                    "Update #__content Set
                        `title` = " . $db->quote($label) . ",
                        `alias` = " . $db->quote($alias) . ",
                        `introtext` = " . $db->quote('<div style=\'display:none;\'><!--(cbArticleId:' . $article . ')--></div>' . $introtext) . ",
                        `fulltext` = " . $db->quote($fulltext . '<div style=\'display:none;\'><!--(cbArticleId:' . $article . ')--></div>') . ",
                        `state` = " . $db->quote($state) . ",
                        `modified` = " . $db->quote($modified) . ",
                        `modified_by` = " . $db->quote($modifiedBy ? $modifiedBy : (int) (Factory::getApplication()->getIdentity()->id ?? 0)) . ",
                        `version` = `version`+1,
                        language=" . $db->quote($language) . "
                    Where id = $article"
                );
                $db->execute();
            }

            $___datenow = Factory::getDate()->toSql();
            $db->setQuery(
                'Update #__contentbuilderng_articles Set `last_update` = ' . $db->quote($___datenow)
                . ' Where `type` = ' . $db->quote($form['type'])
                . ' And form_id = ' . (int) $contentbuilderngFormId
                . ' And reference_id = ' . $db->quote($form['reference_id'])
                . ' And record_id = ' . $db->quote($recordId)
            );
            $db->execute();
        }

        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        $conf = $app->getConfig();
        $options = [
            'defaultgroup' => 'com_content',
            'cachebase' => $conf->get('cache_path', JPATH_SITE . '/cache'),
        ];
        $this->cleanComponentCaches(['com_content', 'com_contentbuilderng'], ['cachebase' => $options['cachebase']]);

        $dispatcher = Factory::getApplication()->getDispatcher();
        $dispatcher->dispatch('onContentCleanCache', new \Joomla\CMS\Event\Model\AfterCleanCacheEvent('onContentCleanCache', [
            'defaultgroup' => $options['defaultgroup'],
            'cachebase' => $options['cachebase'],
            'result' => true,
        ]));

        $isNew = true;
        $table = new \Joomla\CMS\Table\Content($db);

        if ($article > 0) {
            $table->load($article);
            $isNew = false;
        }

        $event = new \Joomla\CMS\Event\Model\AfterSaveEvent('onContentAfterSave', [
            'context' => 'com_content.article',
            'subject' => $table,
            'isNew' => $isNew,
            'data' => [],
        ]);
        $dispatcher->dispatch('onContentAfterSave', $event);

        PluginHelper::importPlugin('contentbuilderng_listaction');

        $eventResult = $dispatcher->dispatch(
            'onAfterArticleCreation',
            new \Joomla\CMS\Event\GenericEvent('onAfterArticleCreation', [$contentbuilderngFormId, $recordId, $article])
        );
        $results = $eventResult->getArgument('result') ?: [];
        $msg = implode('', $results);

        if ($msg) {
            Factory::getApplication()->enqueueMessage($msg);
        }

        return $article;
    }

    private function cleanComponentCaches(array $groups, array $options = []): void
    {
        $cacheFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);

        foreach ($groups as $group) {
            $cacheOptions = $options;
            $cacheOptions['defaultgroup'] = $group;
            $cacheFactory->createCacheController('callback', $cacheOptions)->clean();
        }
    }
}
