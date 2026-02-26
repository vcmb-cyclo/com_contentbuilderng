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
\defined('_JEXEC') or die ('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;

class plgContentbuilderng_listactionTrash extends CMSPlugin implements SubscriberInterface
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
            $this->db->setQuery("Update #__content As content, #__contentbuilderng_articles As article Set content.state = -2 Where article.form_id = " . intval($form_id) . " And article.record_id = " . $this->db->Quote($record_id) . " And content.id = article.article_id");
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

        $this->db->setQuery("Select action From #__contentbuilderng_list_records As lr, #__contentbuilderng_list_states As ls Where lr.state_id = ls.id And lr.form_id = ls.form_id And lr.form_id = " . intval($form_id) . " And lr.record_id = " . $this->db->Quote($record_id));
        $action = $this->db->loadResult();

        if ($action == 'trash') {
            $this->db->setQuery("Delete From #__content Where id = " . intval($article_id));
            $this->db->execute();
        }

        return '';
    }
}
