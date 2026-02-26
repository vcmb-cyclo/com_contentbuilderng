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

class plgContentbuilderng_validationEqual extends CMSPlugin implements SubscriberInterface
{
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
            $lang->load('plg_contentbuilderng_validation_equal', JPATH_ADMINISTRATOR);

            foreach($fields As $other_field){
                if(isset($other_field['name']) && isset($other_field['value']) && isset($field['name']) && $field['name'].'_repeat' == $other_field['name']){
                    
                    $value = isset($field['orig_value']) ? $field['orig_value'] : $value;
                    
                    if(is_array($value)){
                       $val_group = '';
                       foreach($value As $val){
                           $val_group .= $val;
                       } 
                       $value = $val_group;
                    }
                    
                    $other_value = isset($other_field['orig_value']) ? $other_field['orig_value'] : $other_field['value'];
                    
                    if(is_array($other_value)){
                        $val_group = '';
                        foreach($value As $val){
                            $val_group .= $val;
                        } 
                        $other_value = $val_group;
                    }
                    
                    if( $value == $other_value ){
                        return '';
                    } else {
                        return Text::_('COM_CONTENTBUILDERNG_VALIDATION_NOT_EQUAL') . ': ' . $field['label'] . ' / ' . $other_field['label'];
                    }
                }
            }
            
            return '';
        }
}
