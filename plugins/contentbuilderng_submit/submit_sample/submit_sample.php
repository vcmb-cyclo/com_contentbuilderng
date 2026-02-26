<?php
/**
 * @version     6.0
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 
 * Plugin example.
*/

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;

class plgContentbuilderng_submitSubmit_sample extends CMSPlugin implements SubscriberInterface
{
        public static function getSubscribedEvents(): array
        {
            return [
                'onBeforeSubmit' => 'onBeforeSubmit',
                'onAfterSubmit' => 'onAfterSubmit',
            ];
        }
        
        public function onBeforeSubmit(Event $event): void
        {
            $args = array_values($event->getArguments());
            $recordId = $args[0] ?? null;
            $form = $args[1] ?? null;
            $values = isset($args[2]) && is_array($args[2]) ? $args[2] : [];
        }
        
        public function onAfterSubmit(Event $event): void
        {
            $args = array_values($event->getArguments());
            $recordId = $args[0] ?? null;
            $articleId = isset($args[1]) ? (int) $args[1] : 0;
            $form = $args[2] ?? null;
            $values = isset($args[3]) && is_array($args[3]) ? $args[3] : [];
        }
}
