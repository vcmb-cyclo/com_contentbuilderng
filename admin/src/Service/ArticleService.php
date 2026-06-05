<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

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

    public function __construct(TemplateRenderService $templateRenderService)
    {
        $this->formResolverService = new FormResolverService();
        $this->templateRenderService = $templateRenderService;
        $this->textUtilityService = new TextUtilityService();
    }

    private function getApp(): CMSApplication
    {
        return Factory::getApplication();
    }

    private function getCurrentUserId(): int
    {
        $input = $this->getApp()->input;

        if ($input->getBool('cb_preview_ok', false)) {
            $previewActorId = (int) $input->getInt('cb_preview_actor_id', 0);

            if ($previewActorId > 0) {
                return $previewActorId;
            }
        }

        return (int) ($this->getApp()->getIdentity()->id ?? 0);
    }

    public function createArticle($contentbuilderngFormId, $recordId, array $record, array $elementsAllowed, $titleField = '', $metadata = null, $config = [], $full = false, $limitedOptions = true, $menuCatId = null)
    {
        $app = $this->getApp();
        $input = $app->getInput();
        $skipDetailsTemplateOnSave = $app->isClient('site') && $input->getCmd('task', '') === 'edit.save';

        $tz = new \DateTimeZone($app->get('offset'));

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
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . (int)$contentbuilderngFormId)
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
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
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('id') . ' = ' . (int)$meta->created_id);
                $db->setQuery($query);
                $user = $db->loadObject();
            } elseif ($this->getCurrentUserId()) {
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('id') . ' = ' . (int)$this->getCurrentUserId());
                $db->setQuery($query);
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

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('published'),
                $db->quoteName('is_future'),
                $db->quoteName('publish_up'),
                $db->quoteName('publish_down')
            ])
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($db->quoteName('type') . ' = ' . $db->quote($form['type']))
            ->where($db->quoteName('reference_id') . ' = ' . $db->quote($form['reference_id']))
            ->where($db->quoteName('record_id') . ' = ' . $db->quote($recordId));
        $db->setQuery($query);
        $stateData = $db->loadAssoc();
        $publishUpRecord = $stateData['publish_up'];
        $publishDownRecord = $stateData['publish_down'];
        $state = $stateData['is_future'] ? 1 : $stateData['published'];

        $alias = '';
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('articles.article_id'),
                $db->quoteName('content.alias')
            ])
            ->from($db->quoteName('#__contentbuilderng_articles', 'articles'))
            ->innerJoin($db->quoteName('#__content', 'content') . ' ON '
                . $db->quoteName('content.id') . ' = ' . $db->quoteName('articles.article_id'))
            ->where($db->quoteName('content.state') . ' IN (0, 1)')
            ->where($db->quoteName('articles.form_id') . ' = ' . (int)$contentbuilderngFormId)
            ->where($db->quoteName('articles.record_id') . ' = ' . $db->quote($recordId));
        $db->setQuery($query);
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
            $query = $db->getQuery(true)
                ->select($db->quoteName('lang_code'))
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('sef') . ' = ' . $db->quote($input->getCmd('lang', '')));
            $db->setQuery($query);
            $ignoreLangCode = $db->loadResult() ?: '*';
        }

        $language = $form['default_lang_code_ignore'] ? $ignoreLangCode : $form['default_lang_code'];
        $access = $form['default_access'];
        $ordering = 0;
        $categoryId = (int) $form['default_category'];

        if ($full) {
            $alias = $config['alias'] ?? $alias;
            $form['default_category'] = $config['catid'] ?? $form['default_category'];
            $categoryId = (int) $form['default_category'];
            $access = $config['access'] ?? $access;
            $featured = $config['featured'] ?? 0;
            $language = $config['language'] ?? $language;

            if ($form['article_record_impact_language'] && isset($config['language'])) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('sef'))
                    ->from($db->quoteName('#__languages'))
                    ->where($db->quoteName('published') . ' = 1')
                    ->where($db->quoteName('lang_code') . ' = ' . $db->quote($config['language']));
                $db->setQuery($query);
                $sef = $db->loadResult() ?? '';
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__contentbuilderng_records'))
                    ->set($db->quoteName('sef') . ' = ' . $db->quote($sef))
                    ->set($db->quoteName('lang_code') . ' = ' . $db->quote($config['language']))
                    ->where($db->quoteName('type') . ' = ' . $db->quote($form['type']))
                    ->where($db->quoteName('reference_id') . ' = ' . $db->quote($form['reference_id']))
                    ->where($db->quoteName('record_id') . ' = ' . $db->quote($recordId));
                $db->setQuery($query);
                $db->execute();
            }

            if ($form['article_record_impact_publish'] && isset($config['publish_up']) && $config['publish_up'] != $publishUp) {
                $___now = $_now->toSql();
                $publishUpConfig = $config['publish_up'] ?? null;
                
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__contentbuilderng_records'));
                
                if ($publishUpConfig && strtotime($publishUpConfig) >= strtotime($___now)) {
                    $query->set($db->quoteName('published') . ' = 0')
                        ->set($db->quoteName('is_future') . ' = 1');
                }
                
                $query->set($db->quoteName('publish_up') . ' = ' . ($publishUpConfig ? $db->quote($publishUpConfig) : 'NULL'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote($form['type']))
                    ->where($db->quoteName('reference_id') . ' = ' . $db->quote($form['reference_id']))
                    ->where($db->quoteName('record_id') . ' = ' . $db->quote($recordId));
                
                $db->setQuery($query);
                $db->execute();
            }

            $publishUp = $config['publish_up'] ?? $publishUp;

            if ($form['article_record_impact_publish'] && isset($config['publish_down']) && $config['publish_down'] != $publishDown) {
                $___now = $_now->toSql();
                $publishDownConfig = $config['publish_down'] ?? null;
                
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__contentbuilderng_records'));
                
                if ($publishDownConfig && strtotime($publishDownConfig) <= strtotime($___now)) {
                    $query->set($db->quoteName('published') . ' = 0');
                }
                
                $query->set($db->quoteName('publish_down') . ' = ' . ($publishDownConfig ? $db->quote($publishDownConfig) : 'NULL'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote($form['type']))
                    ->where($db->quoteName('reference_id') . ' = ' . $db->quote($form['reference_id']))
                    ->where($db->quoteName('record_id') . ' = ' . $db->quote($recordId));
                
                $db->setQuery($query);
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

                if ($app->isClient('administrator')) {
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

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_records'))
                ->set($db->quoteName('robots') . ' = ' . $db->quote($robots))
                ->set($db->quoteName('author') . ' = ' . $db->quote($author))
                ->set($db->quoteName('rights') . ' = ' . $db->quote($rights))
                ->set($db->quoteName('xreference') . ' = ' . $db->quote($xreference))
                ->set($db->quoteName('metakey') . ' = ' . $db->quote($metakey))
                ->set($db->quoteName('metadesc') . ' = ' . $db->quote($metadesc))
                ->where($db->quoteName('type') . ' = ' . $db->quote($form['type']))
                ->where($db->quoteName('reference_id') . ' = ' . $db->quote($form['reference_id']))
                ->where($db->quoteName('record_id') . ' = ' . $db->quote($recordId));
            $db->setQuery($query);
            $db->execute();

            $isNew = true;
            $table = new \Joomla\CMS\Table\Content($db);

            if ($article > 0) {
                $table->load($article);
                $isNew = false;
            }

            $dispatcher = $app->getDispatcher();
            $event = new \Joomla\CMS\Event\Model\BeforeSaveEvent('onContentBeforeSave', [
                'context' => 'com_content.article',
                'subject' => $table,
                'isNew' => $isNew,
                'data' => [],
            ]);
            $dispatcher->dispatch('onContentBeforeSave', $event);
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = ' . $categoryId)
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' IN (0, 1)');
        $db->setQuery($query);

        if (!$db->loadResult()) {
            return 0;
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
            $defaultAttribs = '{"article_layout":"","show_title":"","link_titles":"","show_tags":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_hits":"","show_noauth":"","urls_position":"","alternative_readmore":"","article_page_title":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}';
            $defaultMeta = '{"robots":"","author":"","rights":""}';
            $defaultImages = '{"image_intro":"","image_intro_alt":"","float_intro":"","image_intro_caption":"","image_fulltext":"","image_fulltext_alt":"","float_fulltext":"","image_fulltext_caption":""}';
            $defaultUrls = '{"urla":"","urlatext":"","targeta":"","urlb":"","urlbtext":"","targetb":"","urlc":"","urlctext":"","targetc":""}';
            $insertCreatedBy = $createdBy ? $createdBy : $this->getCurrentUserId();
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__content'))
                ->columns([
                    $db->quoteName('images'), $db->quoteName('urls'), $db->quoteName('title'),
                    $db->quoteName('alias'), $db->quoteName('introtext'), $db->quoteName('fulltext'),
                    $db->quoteName('state'), $db->quoteName('catid'), $db->quoteName('created'),
                    $db->quoteName('created_by'), $db->quoteName('modified'), $db->quoteName('modified_by'),
                    $db->quoteName('checked_out'), $db->quoteName('checked_out_time'),
                    $db->quoteName('publish_up'), $db->quoteName('publish_down'), $db->quoteName('attribs'),
                    $db->quoteName('version'), $db->quoteName('metakey'), $db->quoteName('metadesc'),
                    $db->quoteName('metadata'), $db->quoteName('access'), $db->quoteName('created_by_alias'),
                    $db->quoteName('ordering'), $db->quoteName('featured'), $db->quoteName('language'),
                ])
                ->values(implode(',', [
                    $db->quote($defaultImages),
                    $db->quote($defaultUrls),
                    $db->quote($label),
                    $db->quote($alias),
                    $db->quote($introtext),
                    $db->quote($fulltext),
                    $db->quote($state),
                    $categoryId,
                    $db->quote($created),
                    (int) $insertCreatedBy,
                    $db->quote($created),
                    (int) $insertCreatedBy,
                    'NULL',
                    'NULL',
                    ($publishUp ? $db->quote($publishUp) : 'NULL'),
                    ($publishDown ? $db->quote($publishDown) : 'NULL'),
                    $db->quote($attribs !== '' ? $attribs : $defaultAttribs),
                    1,
                    $db->quote($metakey),
                    $db->quote($metadesc),
                    $db->quote($meta !== '' ? $meta : $defaultMeta),
                    $db->quote($access),
                    $db->quote($createdByAlias),
                    $db->quote($ordering),
                    $db->quote($featured),
                    $db->quote($language),
                ]));
            $db->setQuery($query);
            $db->execute();

            $article = $db->insertid();
            $___datenow = Factory::getDate()->toSql();
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__contentbuilderng_articles'))
                ->columns([
                    $db->quoteName('type'), $db->quoteName('reference_id'), $db->quoteName('last_update'),
                    $db->quoteName('article_id'), $db->quoteName('record_id'), $db->quoteName('form_id'),
                ])
                ->values(implode(',', [
                    $db->quote($form['type']),
                    $db->quote($form['reference_id']),
                    $db->quote($___datenow),
                    (int) $article,
                    $db->quote($recordId),
                    (int) $contentbuilderngFormId,
                ]));
            $db->setQuery($query);
            $db->execute();
            $cbMarker = '<div style=\'display:none;\'><!--(cbArticleId:' . (int) $article . ')--></div>';
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__content'))
                ->set($db->quoteName('introtext') . ' = CONCAT(' . $db->quote($cbMarker) . ', ' . $db->quoteName('introtext') . ')')
                ->where($db->quoteName('id') . ' = ' . (int) $article);
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__assets'))
                ->where($db->quoteName('name') . ' = ' . $db->quote('com_content.category.' . $categoryId));
            $db->setQuery($query);
            $parentAsset = $db->loadAssoc();

            if ($parentAsset) {
                $parentId = $parentAsset['id'];
                $db->setQuery(
                    'Insert Into ' . $db->quoteName('#__assets')
                    . ' (' . $db->quoteName('rules') . ',' . $db->quoteName('name') . ',' . $db->quoteName('title') . ',' . $db->quoteName('parent_id') . ',' . $db->quoteName('level') . ',' . $db->quoteName('lft') . ',' . $db->quoteName('rgt') . ')'
                    . ' Values (' . $db->quote('{}')
                    . ',' . $db->quote('com_content.article.' . (int) $article)
                    . ',' . $db->quote($label)
                    . ',' . (int) $parentId
                    . ',3'
                    . ',( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From ' . $db->quoteName('#__assets') . ' As mlft) As tbone )'
                    . ',( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From ' . $db->quoteName('#__assets') . ' As mrgt) As filet ))'
                );
                $db->execute();

                $assetId = $db->insertid();
                $query = $db->getQuery(true)
                    ->select('MAX(' . $db->quoteName('mrgt') . '.' . $db->quoteName('rgt') . ')+1')
                    ->from($db->quoteName('#__assets', 'mrgt'));
                $db->setQuery($query);
                $rgt = $db->loadResult();
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__assets'))
                    ->set($db->quoteName('rgt') . ' = ' . (int) $rgt)
                    ->where($db->quoteName('name') . ' = ' . $db->quote('root.1'))
                    ->where($db->quoteName('level') . ' = 0');
                $db->setQuery($query);
                $db->execute();
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__content'))
                    ->set($db->quoteName('asset_id') . ' = ' . (int)$assetId)
                    ->where($db->quoteName('id') . ' = ' . (int)$article);
                $db->setQuery($query);
                $db->execute();
                $stageId = $this->loadDefaultWorkflowStageId($db);

                if ($stageId > 0) {
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__workflow_associations'))
                        ->columns([$db->quoteName('item_id'), $db->quoteName('stage_id'), $db->quoteName('extension')])
                        ->values((int) $article . ', ' . $stageId . ', ' . $db->quote('com_content.article'));
                    $db->setQuery($query);
                    $db->execute();
                }
            }
        } else {
            $___datenow = Factory::getDate()->toSql();
            $modified = $___datenow;
            $currentUserId = $this->getCurrentUserId();
            $metadataModifiedBy = isset($metadata->modified_id) ? (int) $metadata->modified_id : 0;
            $modifiedBy = $currentUserId > 0 ? $currentUserId : $metadataModifiedBy;

            if ($full) {
                $defaultAttribs = '{"article_layout":"","show_title":"","link_titles":"","show_tags":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_hits":"","show_noauth":"","urls_position":"","alternative_readmore":"","article_page_title":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}';
                $defaultMeta = '{"robots":"","author":"","rights":""}';
                $updateModifiedBy = $modifiedBy ? $modifiedBy : $this->getCurrentUserId();
                $introMarker = '<div style=\'display:none;\'><!--(cbArticleId:' . (int) $article . ')--></div>';
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__content'))
                    ->set($db->quoteName('title') . ' = ' . $db->quote($label))
                    ->set($db->quoteName('alias') . ' = ' . $db->quote($alias))
                    ->set($db->quoteName('introtext') . ' = ' . $db->quote($introMarker . $introtext))
                    ->set($db->quoteName('fulltext') . ' = ' . $db->quote($fulltext . $introMarker))
                    ->set($db->quoteName('state') . ' = ' . $db->quote($state))
                    ->set($db->quoteName('catid') . ' = ' . $categoryId)
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($modified))
                    ->set($db->quoteName('modified_by') . ' = ' . (int) $updateModifiedBy)
                    ->set($db->quoteName('attribs') . ' = ' . $db->quote($attribs !== '' ? $attribs : $defaultAttribs))
                    ->set($db->quoteName('metakey') . ' = ' . $db->quote($metakey))
                    ->set($db->quoteName('metadesc') . ' = ' . $db->quote($metadesc))
                    ->set($db->quoteName('metadata') . ' = ' . $db->quote($meta !== '' ? $meta : $defaultMeta))
                    ->set($db->quoteName('version') . ' = ' . $db->quoteName('version') . '+1')
                    ->set($db->quoteName('created') . ' = ' . $db->quote($created))
                    ->set($db->quoteName('created_by') . ' = ' . (int) $createdBy)
                    ->set($db->quoteName('created_by_alias') . ' = ' . $db->quote($createdByAlias))
                    ->set($db->quoteName('publish_up') . ' = ' . ($publishUp !== '' ? $db->quote($publishUp) : 'NULL'))
                    ->set($db->quoteName('publish_down') . ' = ' . ($publishDown !== '' ? $db->quote($publishDown) : 'NULL'))
                    ->set($db->quoteName('access') . ' = ' . $db->quote($access))
                    ->set($db->quoteName('ordering') . ' = ' . $db->quote($ordering))
                    ->set($db->quoteName('featured') . ' = ' . $db->quote($featured))
                    ->set($db->quoteName('language') . ' = ' . $db->quote($language))
                    ->where($db->quoteName('id') . ' = ' . (int) $article);
                $db->setQuery($query);
                $db->execute();

                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__assets'))
                    ->where($db->quoteName('name') . ' = ' . $db->quote('com_content.category.' . $categoryId));
                $db->setQuery($query);
                $parentAsset = $db->loadAssoc();

                if ($parentAsset) {
                    $query = $db->getQuery(true)
                        ->delete($db->quoteName('#__assets'))
                        ->where($db->quoteName('name') . ' = ' . $db->quote('com_content.article.' . (int) $article));
                    $db->setQuery($query);
                    $db->execute();
                    $parentId = $parentAsset['id'];
                    $db->setQuery(
                        'Insert Into ' . $db->quoteName('#__assets')
                        . ' (' . $db->quoteName('rules') . ',' . $db->quoteName('name') . ',' . $db->quoteName('title') . ',' . $db->quoteName('parent_id') . ',' . $db->quoteName('level') . ',' . $db->quoteName('lft') . ',' . $db->quoteName('rgt') . ')'
                        . ' Values (' . $db->quote('{}')
                        . ',' . $db->quote('com_content.article.' . (int) $article)
                        . ',' . $db->quote($label)
                        . ',' . (int) $parentId
                        . ',3'
                        . ',( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From ' . $db->quoteName('#__assets') . ' As mlft) As tbone )'
                        . ',( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From ' . $db->quoteName('#__assets') . ' As mrgt) As filet ))'
                    );
                    $db->execute();
                    $assetId = $db->insertid();
                    $query = $db->getQuery(true)
                        ->select('MAX(' . $db->quoteName('mrgt') . '.' . $db->quoteName('rgt') . ')+1')
                        ->from($db->quoteName('#__assets', 'mrgt'));
                    $db->setQuery($query);
                    $rgt = $db->loadResult();
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__assets'))
                        ->set($db->quoteName('rgt') . ' = ' . (int) $rgt)
                        ->where($db->quoteName('name') . ' = ' . $db->quote('root.1'))
                        ->where($db->quoteName('level') . ' = 0');
                    $db->setQuery($query);
                    $db->execute();
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__content'))
                        ->set($db->quoteName('asset_id') . ' = ' . (int) $assetId)
                        ->where($db->quoteName('id') . ' = ' . (int) $article);
                    $db->setQuery($query);
                    $db->execute();
                }
            } else {
                $introMarker = '<div style=\'display:none;\'><!--(cbArticleId:' . (int) $article . ')--></div>';
                $updateModifiedBy = $modifiedBy ? $modifiedBy : $this->getCurrentUserId();
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__content'))
                    ->set($db->quoteName('title') . ' = ' . $db->quote($label))
                    ->set($db->quoteName('alias') . ' = ' . $db->quote($alias))
                    ->set($db->quoteName('introtext') . ' = ' . $db->quote($introMarker . $introtext))
                    ->set($db->quoteName('fulltext') . ' = ' . $db->quote($fulltext . $introMarker))
                    ->set($db->quoteName('state') . ' = ' . $db->quote($state))
                    ->set($db->quoteName('catid') . ' = ' . $categoryId)
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($modified))
                    ->set($db->quoteName('modified_by') . ' = ' . (int) $updateModifiedBy)
                    ->set($db->quoteName('version') . ' = ' . $db->quoteName('version') . '+1')
                    ->set($db->quoteName('language') . ' = ' . $db->quote($language))
                    ->where($db->quoteName('id') . ' = ' . (int) $article);
                $db->setQuery($query);
                $db->execute();
            }

            $___datenow = Factory::getDate()->toSql();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_articles'))
                ->set($db->quoteName('last_update') . ' = ' . $db->quote($___datenow))
                ->where([
                    $db->quoteName('type') . ' = ' . $db->quote($form['type']),
                    $db->quoteName('form_id') . ' = ' . (int) $contentbuilderngFormId,
                    $db->quoteName('reference_id') . ' = ' . $db->quote($form['reference_id']),
                    $db->quoteName('record_id') . ' = ' . $db->quote($recordId),
                ]);
            $db->setQuery($query);
            $db->execute();
        }

        $app = $this->getApp();
        $conf = $app->getConfig();
        $options = [
            'defaultgroup' => 'com_content',
            'cachebase' => $conf->get('cache_path', JPATH_SITE . '/cache'),
        ];
        $this->cleanComponentCaches(['com_content', 'com_contentbuilderng'], ['cachebase' => $options['cachebase']]);

        $dispatcher = $app->getDispatcher();
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
            $app->enqueueMessage($msg);
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

    private function loadDefaultWorkflowStageId(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('stage.id'))
            ->from($db->quoteName('#__workflow_stages', 'stage'))
            ->join('INNER', $db->quoteName('#__workflows', 'workflow') . ' ON ' . $db->quoteName('workflow.id') . ' = ' . $db->quoteName('stage.workflow_id'))
            ->where($db->quoteName('workflow.extension') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('workflow.published') . ' = 1')
            ->where($db->quoteName('stage.published') . ' = 1')
            ->where($db->quoteName('stage.default') . ' = 1')
            ->order($db->quoteName('stage.id') . ' ASC')
            ->setLimit(1);
        $db->setQuery($query);
        $stageId = (int) $db->loadResult();

        if ($stageId > 0) {
            return $stageId;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('stage.id'))
            ->from($db->quoteName('#__workflow_stages', 'stage'))
            ->join('INNER', $db->quoteName('#__workflows', 'workflow') . ' ON ' . $db->quoteName('workflow.id') . ' = ' . $db->quoteName('stage.workflow_id'))
            ->where($db->quoteName('workflow.extension') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('workflow.published') . ' = 1')
            ->where($db->quoteName('stage.published') . ' = 1')
            ->order($db->quoteName('stage.id') . ' ASC')
            ->setLimit(1);
        $db->setQuery($query);

        return (int) $db->loadResult();
    }
}
