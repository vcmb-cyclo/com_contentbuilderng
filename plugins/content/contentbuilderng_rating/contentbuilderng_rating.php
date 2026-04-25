<?php
/**
 * @version     6.0
 * @package     ContentBuilder NG Rating
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     Released under the terms of the GNU General Public License
 **/


/** ensure this file is being included by a parent file */
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Helper\RatingHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;

class plgContentContentbuilderng_rating extends CMSPlugin implements SubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    function onContentPrepare($context = '', $article = null, $params = null, $limitstart = 0, $is_list = false, $form = null, $item = null)
    {
        if ($context instanceof \Joomla\Event\EventInterface) {
            $event = $context;
            $context = (string) ($event->getArgument('context') ?? '');
            $article = $event->getArgument('subject') ?? $event->getArgument('article') ?? $event->getArgument('item');
            $params = $event->getArgument('params') ?? $params;
            $limitstart = (int) ($event->getArgument('page') ?? $event->getArgument('limitstart') ?? $limitstart);
        }

        $protect = false;

        $plugin = PluginHelper::getPlugin('content', 'contentbuilderng_rating');
        $pluginParams = (new Registry)->loadString($plugin->params);

        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_content_contentbuilderng_rating', JPATH_ADMINISTRATOR);

        /*
         * As of Joomla! 1.6 there is just the text passed if the article data is not passed in article context.
         * (for instance with categories).
         * But we need the article id, so we use the article id flag from content generation.
         */
        if (is_object($article) && !isset($article->id) && !isset($article->cbrecord) && isset($article->text) && $article->text) {
            preg_match_all("/<!--\(cbArticleId:(\d{1,})\)-->/si", $article->text, $matched_id);
            if (isset($matched_id[1]) && isset($matched_id[1][0])) {
                $article->id = intval($matched_id[1][0]);
            }
        }

        // if this content plugin has been called from within list context
        if ($is_list) {

            if (!trim($article->text)) {
                return true;
            }

            $article->cbrecord = $form;
            $article->cbrecord->items = array();
            $article->cbrecord->items[0] = $item;
            $article->cbrecord->record_id = $item->colRecord;
        }

        if (!is_dir(JPATH_SITE .'/media/contentbuilderng')) {
            Folder::create(JPATH_SITE .'/media/contentbuilderng');
        }

        if (!file_exists(JPATH_SITE .'/media/contentbuilderng/index.html'))
            File::write(JPATH_SITE .'/media/contentbuilderng/index.html', $def = '');

        if (!is_dir(JPATH_SITE .'/media/contentbuilderng/plugins')) {
            Folder::create(JPATH_SITE .'/media/contentbuilderng/plugins');
        }

        if (!file_exists(JPATH_SITE .'/media/contentbuilderng/plugins/index.html'))
            File::write(JPATH_SITE .'/media/contentbuilderng/plugins/index.html', $def = '');

        if (isset($article->id) || isset($article->cbrecord)) {

            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $matches = array();

            preg_match_all("/\{CBRating([^}]*)\}/i", $article->text, $matches);

            if (isset($matches[0]) && is_array($matches[0]) && isset($matches[1]) && is_array($matches[1])) {

                $form_id = 0;
                $record_id = 0;

                $frontend = true;
                if (Factory::getApplication()->isClient('administrator')) {
                    $frontend = false;
                }

                if (isset($article->id) && $article->id && !isset($article->cbrecord)) {

                    // try to obtain the record id if if this is just an article
                    $ratingQuery = $db->getQuery(true)
                        ->select([
                            $db->quoteName('form.rating_slots'),
                            $db->quoteName('form.title_field'),
                            $db->quoteName('form.protect_upload_directory'),
                            $db->quoteName('form.reference_id'),
                            $db->quoteName('article.record_id'),
                            $db->quoteName('article.form_id'),
                            $db->quoteName('form.type'),
                            $db->quoteName('form.published_only'),
                            $db->quoteName('form.own_only'),
                            $db->quoteName('form.own_only_fe'),
                        ])
                        ->from($db->quoteName('#__contentbuilderng_articles', 'article'))
                        ->join('INNER', $db->quoteName('#__contentbuilderng_forms', 'form')
                            . ' ON ' . $db->quoteName('form.id') . ' = ' . $db->quoteName('article.form_id'))
                        ->where($db->quoteName('form.published') . ' = 1')
                        ->where($db->quoteName('article.article_id') . ' = ' . $db->quote($article->id));
                    $db->setQuery($ratingQuery);
                    $data = $db->loadAssoc();
                    if (!is_array($data) || empty($data['type']) || !array_key_exists('reference_id', $data)) {
                        return true;
                    }

                    $form = FormSourceFactory::getForm((string) $data['type'], (string) $data['reference_id']);
                    if (!$form || !$form->exists) {
                        return true;
                    }

                    if ($form) {

                        $form_id = $data['form_id'];
                        $record_id = $data['record_id'];
                        $rating_slots = $data['rating_slots'];
                    }

                } else if (isset($article->cbrecord) && isset($article->cbrecord->id) && $article->cbrecord->id) {

                    $form = $article->cbrecord->form;
                    $form_id = $article->cbrecord->id;
                    $record_id = $article->cbrecord->record_id;
                    $rating_slots = $article->cbrecord->rating_slots;

                }

                $rating = 0;
                $rating_count = 0;
                $rating_sum = 0;

                if (!is_object($form)) {
                    return true;
                }

                $record = $form->getRecord($record_id, false, -1, true);

                if (count($record)) {
                    $rating = $record[0]->recRating;
                    $rating_count = $record[0]->recRatingCount;
                    $rating_sum = $record[0]->recRatingSum;
                }

                $rating_allowed = true;

                if (!$is_list) {

                    (new PermissionService())->setPermissions($form_id, $record_id, $frontend ? '_fe' : '');

                    if ($frontend) {
                        if (!(new PermissionService())->authorizeFe('rating')) {
                            $rating_allowed = false;
                        }
                    } else {
                        if (!(new PermissionService())->authorize('rating')) {
                            $rating_allowed = false;
                        }
                    }
                }

                $i = 0;
                foreach ($matches[1] as $match) {

                    $options = explode(';', trim($match));
                    foreach ($options as $option) {
                        $keyval = explode(':', trim($option), 2);
                        if (count($keyval) == 2) {

                            $value = trim($keyval[1]);
                            switch (strtolower(trim($keyval[0]))) {
                                default:
                            }
                        }
                    }

                    $out = RatingHelper::getRating($form_id, $record_id, $rating, $rating_slots, Factory::getApplication()->getInput()->getCmd('lang', ''), $rating_allowed, $rating_count, $rating_sum);

                    $article->text = str_replace($matches[0][$i], $out, $article->text);

                    $i++;
                }
            }
        }

        return true;
    }
}
