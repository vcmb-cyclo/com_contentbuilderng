<?php

namespace CB\Plugin\ContentbuilderngListaction\Trash\Extension;

/**
 * @version     6.0
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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Database\ParameterType;

final class Trash extends CMSPlugin implements SubscriberInterface
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

        $lang = $this->app->getLanguage();
        $lang->load('plg_contentbuilderng_listaction_trash', JPATH_ADMINISTRATOR);

        foreach ($record_ids as $record_id) {
            $recordIdValue = (string) $record_id;
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__content', 'content'))
                ->innerJoin($this->db->quoteName('#__contentbuilderng_articles', 'article'), 'content.id = article.article_id')
                ->set('content.state = -2')
                ->where('article.form_id = :formId')
                ->where('article.record_id = :recordId')
                ->bind(':formId', $form_id, ParameterType::INTEGER)
                ->bind(':recordId', $recordIdValue);
            $this->db->setQuery($query);
            $this->db->execute();
        }

        $this->app->enqueueMessage(Text::_('COM_CONTENTBUILDERNG_TRASH_SUCCESSFULL'));
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
     * In this case we delete the newly created article if there is a trashed state assigned to this record.
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

        $recordIdValue = (string) $record_id;
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('action'))
            ->from($this->db->quoteName('#__contentbuilderng_list_records', 'lr'))
            ->join('INNER', $this->db->quoteName('#__contentbuilderng_list_states', 'ls'), 'lr.state_id = ls.id AND lr.form_id = ls.form_id')
            ->where('lr.form_id = :formId')
            ->where('lr.record_id = :recordId')
            ->bind(':formId', $form_id, ParameterType::INTEGER)
            ->bind(':recordId', $recordIdValue);
        $this->db->setQuery($query);
        $action = $this->db->loadResult();

        if ($action == 'trash') {
            $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__content'))
                ->where($this->db->quoteName('id') . ' = :articleId')
                ->bind(':articleId', $article_id, ParameterType::INTEGER);
            $this->db->setQuery($query);
            $this->db->execute();
        }

        return '';
    }
}
