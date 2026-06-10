<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

// No direct access
\defined('_JEXEC') or die ('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;

class plgContentbuilderng_listactionUntrash extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeAction' => 'onBeforeAction',
            'onAfterAction' => 'onAfterAction',
            'onAfterArticleCreation' => 'onAfterArticleCreation',
        ];
    }

    /**
     * @param int $form_id use it to find the record for the appropriate view
     * @param array $record_ids an array of record_id. Please note that the record_ids may be _non_numeric_
     * @return string error
     */
    public function onBeforeAction(Event $event): string
    {
        $args = array_values($event->getArguments());
        $form_id = isset($args[0]) ? (int) $args[0] : 0;
        $record_ids = $args[1] ?? [];
        if (!is_array($record_ids)) {
            $record_ids = [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_contentbuilderng_listaction_untrash', JPATH_ADMINISTRATOR);

        foreach ($record_ids as $record_id) {

            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__content') . ' AS ' . $db->quoteName('content')
                . ', ' . $db->quoteName('#__contentbuilderng_records') . ' AS ' . $db->quoteName('record')
                . ', ' . $db->quoteName('#__contentbuilderng_articles') . ' AS ' . $db->quoteName('article')
                . ' SET ' . $db->quoteName('content') . '.' . $db->quoteName('state')
                    . ' = ' . $db->quoteName('record') . '.' . $db->quoteName('published')
                . ' WHERE ' . $db->quoteName('article') . '.' . $db->quoteName('record_id')
                    . ' = ' . $db->quoteName('record') . '.' . $db->quoteName('record_id')
                . ' AND ' . $db->quoteName('article') . '.' . $db->quoteName('form_id')
                    . ' = ' . (int) $form_id
                . ' AND ' . $db->quoteName('article') . '.' . $db->quoteName('record_id')
                    . ' = ' . $db->quote($record_id)
                . ' AND ' . $db->quoteName('content') . '.' . $db->quoteName('id')
                    . ' = ' . $db->quoteName('article') . '.' . $db->quoteName('article_id')
            );
            $db->execute();
        }

        Factory::getApplication()->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_UNTRASH_SUCCESSFULL'));

        return ''; // no error
    }

    /**
     *
     * @param int $form_id use it to find the record for the appropriate view
     * @param array $record_ids an array of record_id. Please note that the record_ids may be _non_numeric_
     * @param type $previous_errors error messages thrown by onBeforeAction
     * @return type 
     */
    public function onAfterAction(Event $event): string
    {
        return ''; // no error
    }

    /**
     * This event will be triggered on article creation and update.
     * 
     * It gives you the chance to force the article to stay into previously set states
     * 
     * @param int $form_id
     * @param mixed $record_id
     * @param int $article_id 
     * @return string message
     */
    public function onAfterArticleCreation(Event $event): string
    {
        $args = array_values($event->getArguments());
        $form_id = isset($args[0]) ? (int) $args[0] : 0;
        $record_id = $args[1] ?? null;
        $article_id = isset($args[2]) ? (int) $args[2] : 0;
        return '';
    }
}
