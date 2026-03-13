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
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

class plgContentbuilderng_validationDate_not_before extends CMSPlugin implements SubscriberInterface
{
        private function pushEventResult(Event $event, string $value): string
        {
            $results = $event->getArgument('result') ?: [];
            if (!is_array($results)) {
                $results = [$results];
            }
            $results[] = $value;
            $event->setArgument('result', $results);

            return $value;
        }

        public static function getSubscribedEvents(): array
        {
            return ['onValidate' => 'onValidate'];
        }
        
        public function onValidate(Event $event): string{
            $args = array_values($event->getArguments());
            $field = isset($args[0]) && is_array($args[0]) ? $args[0] : [];
            $fields = isset($args[1]) && is_array($args[1]) ? $args[1] : [];
            $value = $args[4] ?? null;
            if (!$field) {
                return '';
            }
            
            $lang = Factory::getApplication()->getLanguage();
            $lang->load('plg_contentbuilderng_validation_date_not_before', JPATH_ADMINISTRATOR);

            foreach($fields As $other_field){
                if(isset($other_field['name']) && isset($other_field['value']) && isset($field['name']) && $field['name'].'_later' == $other_field['name']){
                 
                    if(is_array($value)){
                       return $this->pushEventResult($event, Text::_('COM_CONTENTBUILDERNG_VALIDATION_DATE_NOT_BEFORE_GROUPS'));
                    }
                    
                    $other_value = $other_field['value'];
                    $other_value = ContentbuilderngHelper::convertDate($other_value, $other_field['options']->transfer_format, 'YYYY-MM-DD');
                    $value = ContentbuilderngHelper::convertDate($value, $field['options']->transfer_format, 'YYYY-MM-DD');
                    
                    if(is_array($other_value)){
                        return $this->pushEventResult($event, Text::_('COM_CONTENTBUILDERNG_VALIDATION_DATE_NOT_BEFORE_GROUPS'));
                    }
                    
                    $value = preg_replace("/[^0-9]/",'',$value);
                    $other_value = preg_replace("/[^0-9]/",'',$other_value);
                    
                    if($other_value < $value){
                        return $this->pushEventResult($event, Text::_('COM_CONTENTBUILDERNG_VALIDATION_DATE_NOT_BEFORE') . ': ' . $other_field['label'] . ' (' . $other_field['value'] . ')');
                    }
                    
                    return $this->pushEventResult($event, '');
                }
            }
            
            return $this->pushEventResult($event, '');
        }
}
