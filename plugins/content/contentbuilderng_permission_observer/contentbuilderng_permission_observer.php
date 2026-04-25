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
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
class plgContentContentbuilderng_permission_observer extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
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

        if (isset ($article->id) && $article->id) {

            $frontend = true;
            if (Factory::getApplication()->isClient('administrator')) {
                $frontend = false;
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $permQuery = $db->getQuery(true)
                ->select([
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
            $db->setQuery($permQuery);
            $data = $db->loadAssoc();
            if (!is_array($data) || empty($data['type']) || !array_key_exists('reference_id', $data)) {
                return true;
            }

            $form = FormSourceFactory::getForm((string) $data['type'], (string) $data['reference_id']);

            if (!$form || !$form->exists) {
                return true;
            }

            if ($form && !(Factory::getApplication()->getInput()->get('option', '', 'string') == 'com_contentbuilderng' && Factory::getApplication()->getInput()->get('controller', '', 'string') == 'edit')) {

                Factory::getApplication()->getLanguage()->load('com_contentbuilderng');
                (new PermissionService())->setPermissions($data['form_id'], $data['record_id'], $frontend ? '_fe' : '');

                if (Factory::getApplication()->getInput()->getCmd('view') == 'article') {
                    (new PermissionService())->checkPermissions('view', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED'), $frontend ? '_fe' : '');
                } else {
                    if ($frontend) {
                        if (!(new PermissionService())->authorizeFe('view')) {
                            $article->text = Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED');
                        }
                    } else {
                        if (!(new PermissionService())->authorize('view')) {
                            $article->text = Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED');
                        }
                    }
                }
            }
        }
        return true;
    }
}
