<?php
/**
 * @version 6.0
 * @package ContentBuilder NG Permission observer
 * @copyright (C) 2011 by Markus Bopp
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license Released under the terms of the GNU General Public License
 **/

/** ensure this file is being included by a parent file */
\defined('_JEXEC') or die ('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;

class plgContentContentbuilderng_permission_observer extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    /**
     * Joomla 1.5 compatibility
     */
    function onPrepareContent(&$article, &$params, $limitstart = 0)
    {
        $this->onContentPrepare('', $article, $params, $limitstart);
    }

    function onContentPrepare($context = '', $article = null, $params = null, $limitstart = 0)
    {
        if ($context instanceof \Joomla\Event\EventInterface) {
            $event = $context;
            $context = (string) ($event->getArgument('context') ?? '');
            $article = $event->getArgument('subject') ?? $event->getArgument('article') ?? $event->getArgument('item');
            $params = $event->getArgument('params') ?? $params;
            $limitstart = (int) ($event->getArgument('page') ?? $event->getArgument('limitstart') ?? $limitstart);
        }

        if (!file_exists(JPATH_SITE .'/administrator/components/com_contentbuilderng/src/contentbuilderng.php')) {
            return true;
        }

        if (isset ($article->id) && $article->id) {

            $frontend = true;
            if (Factory::getApplication()->isClient('administrator')) {
                $frontend = false;
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery("Select form.`reference_id`,article.`record_id`,article.`form_id`,form.`type`,form.`published_only`,form.`own_only`,form.`own_only_fe` From #__contentbuilderng_articles As article, #__contentbuilderng_forms As form Where form.`published` = 1 And form.id = article.`form_id` And article.`article_id` = " . $db->quote($article->id));
            $data = $db->loadAssoc();

            require_once (JPATH_SITE .'/administrator/components/com_contentbuilderng/src/contentbuilderng.php');
            $form = ContentbuilderLegacyHelper::getForm($data['type'], $data['reference_id']);

            if (!$form || !$form->exists) {
                return true;
            }

            if ($form && !(Factory::getApplication()->input->get('option', '', 'string') == 'com_contentbuilderng' && Factory::getApplication()->input->get('controller', '', 'string') == 'edit')) {

                Factory::getApplication()->getLanguage()->load('com_contentbuilderng');
                ContentbuilderLegacyHelper::setPermissions($data['form_id'], $data['record_id'], $frontend ? '_fe' : '');

                if (Factory::getApplication()->input->getCmd('view') == 'article') {
                    ContentbuilderLegacyHelper::checkPermissions('view', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED'), $frontend ? '_fe' : '');
                } else {
                    if ($frontend) {
                        if (!ContentbuilderLegacyHelper::authorizeFe('view')) {
                            $article->text = Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED');
                        }
                    } else {
                        if (!ContentbuilderLegacyHelper::authorize('view')) {
                            $article->text = Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED');
                        }
                    }
                }
            }
        }
        return true;
    }
}
